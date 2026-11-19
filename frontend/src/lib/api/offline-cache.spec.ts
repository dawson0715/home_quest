import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { connectivity } from '../stores/connectivity.svelte';
import { configureClient, request } from './client';
import { ApiError } from './errors';
import { clearCache, isCacheable } from './offline-cache';

function memoryStorage() {
  const map = new Map<string, string>();
  return {
    getItem: (k: string) => map.get(k) ?? null,
    setItem: (k: string, v: string) => void map.set(k, v),
    removeItem: (k: string) => void map.delete(k),
    key: (i: number) => [...map.keys()][i] ?? null,
    get length() {
      return map.size;
    },
    map
  };
}

const plan = { week_start: '2026-11-16', slots: [] };
const ok = (data: unknown) => new Response(JSON.stringify({ data, meta: {} }), { status: 200 });
const offline = () => Promise.reject(new TypeError('Failed to fetch'));

describe('offline cache', () => {
  let storage: ReturnType<typeof memoryStorage>;
  beforeEach(() => {
    storage = memoryStorage();
    vi.stubGlobal('localStorage', storage);
    configureClient({ getToken: () => 'parent-token' });
    connectivity.markOnline();
  });
  afterEach(() => vi.unstubAllGlobals());

  it('caches only menu, shopping and session reads', () => {
    expect(isCacheable('me')).toBe(true);
    expect(isCacheable('meal-plans/2026-11-16')).toBe(true);
    expect(isCacheable('shopping-lists/3')).toBe(true);
    expect(isCacheable('recipes?q=pasta')).toBe(true);
    expect(isCacheable('occurrences?date=2026-11-16')).toBe(false);
    expect(isCacheable('children')).toBe(false);
  });

  it('serves the last copy when the network is down and marks the app offline', async () => {
    await request('GET', 'meal-plans/2026-11-16', undefined, { fetchFn: async () => ok(plan) });
    expect([...storage.map.keys()].join()).not.toContain('parent-token');

    const res = await request('GET', 'meal-plans/2026-11-16', undefined, { fetchFn: offline });

    expect(res.data).toEqual(plan);
    expect(res.meta.offline).toBe(true);
    expect(connectivity.offline).toBe(true);
    expect(connectivity.cachedAt).toBe(res.meta.cached_at);

    await request('GET', 'me', undefined, { fetchFn: async () => ok({}) });
    expect(connectivity.offline).toBe(false);
  });

  it('keeps sessions apart and still fails when nothing was saved', async () => {
    await request('GET', 'me', undefined, { fetchFn: async () => ok({ who: 'parent' }) });

    const kid = await request('GET', 'me', undefined, { token: 'c_1', fetchFn: offline }).catch(
      (e: unknown) => e
    );
    expect(kid).toBeInstanceOf(ApiError);
    expect((kid as ApiError).code).toBe('network_error');

    const write = await request(
      'POST',
      'meal-plans/2026-11-16/copy-from',
      {},
      {
        fetchFn: offline
      }
    ).catch((e: unknown) => e);
    expect(write).toBeInstanceOf(ApiError);
  });

  it('drops the copies of a session on logout', async () => {
    await request('GET', 'me', undefined, { fetchFn: async () => ok({ who: 'parent' }) });
    await request('GET', 'me', undefined, {
      token: 'c_1',
      fetchFn: async () => ok({ who: 'kid' })
    });

    clearCache('parent-token');

    const parent = await request('GET', 'me', undefined, { fetchFn: offline }).catch(() => null);
    const kid = await request('GET', 'me', undefined, { token: 'c_1', fetchFn: offline });
    expect(parent).toBeNull();
    expect(kid.data).toEqual({ who: 'kid' });
  });
});
