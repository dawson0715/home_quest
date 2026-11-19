import { connectivity } from '../stores/connectivity.svelte';
import { ApiError, NETWORK_ERROR } from './errors';
import { isCacheable, readCache, writeCache } from './offline-cache';
import type { Envelope } from './types';

export const API_BASE = '/api/v1';

type TokenProvider = () => string | null;
type UnauthorizedHandler = () => void;

let getToken: TokenProvider = () => null;
let onUnauthorized: UnauthorizedHandler = () => {};

/** Wired by the session store (M1.4). */
export function configureClient(opts: {
  getToken?: TokenProvider;
  onUnauthorized?: UnauthorizedHandler;
}): void {
  if (opts.getToken) getToken = opts.getToken;
  if (opts.onUnauthorized) onUnauthorized = opts.onUnauthorized;
}

export type Method = 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE';

export interface RequestOptions {
  /** Overrides the session token (device flows); `null` sends no Authorization. */
  token?: string | null;
  fetchFn?: typeof fetch;
  /** Conditional GET: a 304 resolves to `{ data: null, meta: { not_modified: true } }`. */
  ifNoneMatch?: string;
}

/**
 * Calls the API and returns the unwrapped envelope.
 * Throws ApiError on HTTP errors (with backend error codes) and network failures.
 * Cacheable GETs (offline-cache.ts) fall back to the last good copy when the network
 * is down: `meta.offline = true`, `meta.cached_at` = unix ms of that copy.
 */
export async function request<T>(
  method: Method,
  path: string,
  body?: unknown,
  opts: RequestOptions = {}
): Promise<Envelope<T>> {
  const fetchFn = opts.fetchFn ?? fetch;
  const headers: Record<string, string> = { Accept: 'application/json' };
  const isForm = typeof FormData !== 'undefined' && body instanceof FormData;
  if (body !== undefined && !isForm) headers['Content-Type'] = 'application/json';
  const token = opts.token !== undefined ? opts.token : getToken();
  if (token) headers.Authorization = `Bearer ${token}`;
  if (opts.ifNoneMatch) headers['If-None-Match'] = opts.ifNoneMatch;

  const cleanPath = path.replace(/^\//, '');
  const cacheable = method === 'GET' && isCacheable(cleanPath);

  let res: Response;
  try {
    res = await fetchFn(`${API_BASE}/${cleanPath}`, {
      method,
      headers,
      body: body === undefined ? undefined : isForm ? (body as FormData) : JSON.stringify(body)
    });
  } catch {
    const cached = cacheable ? readCache(token, cleanPath) : null;
    connectivity.markOffline(cached?.at);
    if (cached) return { data: cached.data as T, meta: { offline: true, cached_at: cached.at } };
    throw new ApiError(0, [{ code: NETWORK_ERROR, message: 'Network error' }]);
  }
  connectivity.markOnline();

  if (res.status === 204) return { data: null as T, meta: {} };
  if (res.status === 304) return { data: null as T, meta: { not_modified: true } };

  const json = (await res.json().catch(() => null)) as Envelope<T> | null;

  if (!res.ok) {
    // Only the session token is cleared on 401; explicit tokens are handled by their caller
    if (res.status === 401 && opts.token === undefined) onUnauthorized();
    const errors = json?.meta?.errors ?? [{ code: 'server_error', message: `HTTP ${res.status}` }];
    throw new ApiError(res.status, errors);
  }

  if (cacheable && json) writeCache(token, cleanPath, json.data);
  return json ?? { data: null as T, meta: {} };
}

/**
 * Updates the offline copy of a GET resource with a fresh version received another way
 * (e.g. the list returned by POST /sync), so the copy shown offline is the latest one.
 */
export function rememberResponse(path: string, data: unknown, opts: { token?: string } = {}): void {
  const token = opts.token !== undefined ? opts.token : getToken();
  if (isCacheable(path)) writeCache(token, path, data);
}

export const api = {
  get: <T>(path: string, opts?: RequestOptions) => request<T>('GET', path, undefined, opts),
  post: <T>(path: string, body?: unknown, opts?: RequestOptions) =>
    request<T>('POST', path, body ?? {}, opts),
  put: <T>(path: string, body?: unknown, opts?: RequestOptions) =>
    request<T>('PUT', path, body ?? {}, opts),
  patch: <T>(path: string, body?: unknown, opts?: RequestOptions) =>
    request<T>('PATCH', path, body ?? {}, opts),
  delete: <T>(path: string, opts?: RequestOptions) => request<T>('DELETE', path, undefined, opts)
};
