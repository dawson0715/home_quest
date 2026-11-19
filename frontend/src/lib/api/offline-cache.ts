/**
 * Last good copy of a few GET responses, so menu and shopping stay readable offline.
 * Stored in localStorage per token (hashed, never the token itself) and dropped on logout.
 * Only small, family-scoped reads are cached: session (/me), menu, recipes, shopping.
 */

const PREFIX = 'hq.cache.';
const MAX_ENTRIES = 40;

/** Paths (without /api/v1/) whose last response is kept for offline use. */
const CACHEABLE = [
  /^me$/,
  /^meal-plans\/\d{4}-\d{2}-\d{2}$/,
  /^recipes(\?.*)?$/,
  /^shopping-categories$/,
  /^shopping-lists(\/\d+)?$/,
  /^favorite-items$/
];

export interface CachedResponse {
  /** unix ms of the server response */
  at: number;
  data: unknown;
}

export function isCacheable(path: string): boolean {
  return CACHEABLE.some((re) => re.test(path));
}

/** FNV-1a: separates sessions without storing the bearer token. */
function tokenKey(token: string | null): string {
  let h = 0x811c9dc5;
  for (const ch of token ?? '') {
    h ^= ch.charCodeAt(0);
    h = Math.imul(h, 0x01000193);
  }
  return (h >>> 0).toString(36);
}

function storage(): Storage | null {
  try {
    return globalThis.localStorage ?? null;
  } catch {
    return null;
  }
}

function keysOf(store: Storage, prefix: string): string[] {
  const keys: string[] = [];
  for (let i = 0; i < store.length; i++) {
    const k = store.key(i);
    if (k?.startsWith(prefix)) keys.push(k);
  }
  return keys;
}

export function readCache(token: string | null, path: string): CachedResponse | null {
  try {
    const raw = storage()?.getItem(`${PREFIX}${tokenKey(token)}:${path}`);
    return raw ? (JSON.parse(raw) as CachedResponse) : null;
  } catch {
    return null;
  }
}

export function writeCache(token: string | null, path: string, data: unknown): void {
  const store = storage();
  if (!store) return;
  const entry: CachedResponse = { at: Date.now(), data };
  try {
    store.setItem(`${PREFIX}${tokenKey(token)}:${path}`, JSON.stringify(entry));
    prune(store);
  } catch {
    // quota exceeded: start over rather than failing the request
    clearCache();
  }
}

/** Keeps the most recent entries (old weeks of the menu pile up otherwise). */
function prune(store: Storage): void {
  const keys = keysOf(store, PREFIX);
  if (keys.length <= MAX_ENTRIES) return;
  const dated = keys.map((k) => {
    try {
      return { k, at: (JSON.parse(store.getItem(k) ?? '{}') as CachedResponse).at ?? 0 };
    } catch {
      return { k, at: 0 };
    }
  });
  dated.sort((a, b) => a.at - b.at);
  for (const { k } of dated.slice(0, dated.length - MAX_ENTRIES)) store.removeItem(k);
}

/** Drops the copies of one session (logout), or everything when `token` is omitted. */
export function clearCache(token?: string | null): void {
  const store = storage();
  if (!store) return;
  const prefix = token === undefined ? PREFIX : `${PREFIX}${tokenKey(token)}:`;
  try {
    for (const k of keysOf(store, prefix)) store.removeItem(k);
  } catch {
    /* ignore */
  }
}
