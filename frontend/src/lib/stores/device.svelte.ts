import { untrack } from 'svelte';
import {
  getChildMe,
  getDeviceMe,
  listProfiles,
  pairDevice,
  selectChild
} from '../api/endpoints/devices';
import { ApiError } from '../api/errors';
import { clearCache } from '../api/offline-cache';
import type { Child, ChildProfileCard, Family } from '../api/types';

const DEVICE_KEY = 'hq.device';
const CHILD_KEY = 'hq.child';

function read(key: string): string | null {
  try {
    return globalThis.localStorage?.getItem(key) ?? null;
  } catch {
    return null;
  }
}

function write(key: string, value: string | null): void {
  try {
    if (value === null) globalThis.localStorage?.removeItem(key);
    else globalThis.localStorage?.setItem(key, value);
  } catch {
    /* ignore */
  }
}

/**
 * Kids' device: device token (d_…, after pairing) and the current child session
 * token (c_…, after choosing the profile). Explicit tokens are passed to the
 * API so they never mix with a parent session on the same browser.
 */
class DeviceSession {
  deviceToken = $state<string | null>(null);
  childToken = $state<string | null>(null);
  deviceName = $state<string | null>(null);
  family = $state<Family | null>(null);
  child = $state<Child | null>(null);
  #ready: Promise<void> | null = null;

  get isPaired(): boolean {
    return this.deviceToken !== null && this.deviceName !== null;
  }

  get isChildLoggedIn(): boolean {
    return this.isPaired && this.childToken !== null && this.child !== null;
  }

  ready(): Promise<void> {
    this.#ready ??= this.#restore();
    return this.#ready;
  }

  async #restore(): Promise<void> {
    const device = read(DEVICE_KEY);
    if (!device) return;
    this.deviceToken = device;
    try {
      const me = await getDeviceMe(device);
      this.deviceName = me.device.name;
      this.family = me.family;
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) this.forget();
      return;
    }
    const child = read(CHILD_KEY);
    if (!child) return;
    this.childToken = child;
    try {
      this.child = (await getChildMe(child)).child;
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) this.switchProfile();
    }
  }

  async pair(code: string, name: string): Promise<void> {
    const result = await pairDevice(code, name);
    this.forget();
    this.deviceToken = result.device_token;
    this.deviceName = result.device.name;
    write(DEVICE_KEY, result.device_token);
    this.#ready = Promise.resolve();
  }

  async profiles(): Promise<ChildProfileCard[]> {
    if (!this.deviceToken) return [];
    try {
      return await listProfiles(this.deviceToken);
    } catch (e) {
      if (e instanceof ApiError && e.status === 401) this.forget();
      throw e;
    }
  }

  async selectChild(childId: number, pin?: string): Promise<void> {
    if (!this.deviceToken) throw new ApiError(401, [{ code: 'unauthorized', message: '' }]);
    const result = await selectChild(this.deviceToken, childId, pin);
    this.childToken = result.child_token;
    this.child = result.child;
    write(CHILD_KEY, result.child_token);
  }

  /** Back to the profile picker (the child session token is dropped). */
  switchProfile(): void {
    // untracked: callers run this inside effects (profiles page), which must not
    // start depending on the token they are clearing
    const child = untrack(() => this.childToken);
    if (child) clearCache(child);
    this.childToken = null;
    this.child = null;
    write(CHILD_KEY, null);
  }

  /** Unpairs locally (e.g. after revocation). */
  forget(): void {
    this.switchProfile();
    const device = untrack(() => this.deviceToken);
    if (device) clearCache(device);
    this.deviceToken = null;
    this.deviceName = null;
    this.family = null;
    write(DEVICE_KEY, null);
  }
}

export const deviceSession = new DeviceSession();
