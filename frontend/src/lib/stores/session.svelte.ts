import { configureClient } from '../api/client';
import { ApiError } from '../api/errors';
import { untrack } from 'svelte';
import { getMe } from '../api/endpoints/family';
import { clearCache } from '../api/offline-cache';
import type { FamilyRole, ParentMe } from '../api/types';

const TOKEN_KEY = 'hq.token';

/** localStorage may be missing (tests, private mode) or throw: never let it break the app. */
const storage = {
  get(): string | null {
    try {
      return globalThis.localStorage?.getItem(TOKEN_KEY) ?? null;
    } catch {
      return null;
    }
  },
  set(token: string | null): void {
    try {
      if (token === null) globalThis.localStorage?.removeItem(TOKEN_KEY);
      else globalThis.localStorage?.setItem(TOKEN_KEY, token);
    } catch {
      /* ignore */
    }
  }
};

/**
 * Parent session (static bearer token, docs/02-architettura.md §5).
 * The token lives in localStorage; /me is the source of truth for user/family/role.
 */
class Session {
  token = $state<string | null>(null);
  me = $state<ParentMe | null>(null);
  #ready: Promise<void> | null = null;

  get isAuthenticated(): boolean {
    return this.token !== null && this.me?.family != null;
  }

  get role(): FamilyRole | null {
    return this.me?.role ?? null;
  }

  /** Restores the session once (idempotent); resolves when /me has been checked. */
  ready(): Promise<void> {
    this.#ready ??= this.#restore();
    return this.#ready;
  }

  async #restore(): Promise<void> {
    const token = storage.get();
    if (!token) return;
    this.token = token;
    try {
      await this.#loadMe();
    } catch (e) {
      // Offline/server errors keep the token for a later retry; invalid tokens are dropped.
      if (e instanceof ApiError && (e.status === 401 || e.code === 'no_family')) this.clear();
    }
  }

  /**
   * Validates `token` with /me and stores it.
   * @throws ApiError (`unauthorized`, `no_family`, `network_error`, ...)
   */
  async login(token: string): Promise<void> {
    this.token = token.trim();
    try {
      await this.#loadMe();
    } catch (e) {
      this.clear();
      throw e;
    }
    storage.set(this.token);
    this.#ready = Promise.resolve();
  }

  async refresh(): Promise<void> {
    await this.#loadMe();
  }

  clear(): void {
    const token = untrack(() => this.token); // may run inside an effect
    if (token) clearCache(token);
    this.token = null;
    this.me = null;
    storage.set(null);
  }

  async #loadMe(): Promise<void> {
    const me = await getMe();
    // A device/child token pasted here is not a parent session
    if (!('user' in me) || !me.family) {
      throw new ApiError(403, [{ code: 'no_family', message: 'No family' }]);
    }
    this.me = me;
  }
}

export const session = new Session();

configureClient({
  getToken: () => session.token,
  onUnauthorized: () => session.clear()
});
