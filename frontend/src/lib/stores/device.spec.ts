import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const family = { id: 1, name: 'Rossi', settings: {} };
const child = { id: 7, name: 'Luca', avatar: 'fox', color: '#E07400', has_pin: false };

function ok(data: unknown, status = 200) {
  return new Response(JSON.stringify({ data, meta: {} }), { status });
}
function unauthorized() {
  return new Response(
    JSON.stringify({ data: null, meta: { errors: [{ code: 'unauthorized', message: 'x' }] } }),
    { status: 401 }
  );
}
function memoryStorage(initial: Record<string, string> = {}) {
  const map = new Map(Object.entries(initial));
  return {
    getItem: (k: string) => map.get(k) ?? null,
    setItem: (k: string, v: string) => void map.set(k, v),
    removeItem: (k: string) => void map.delete(k),
    map
  };
}
async function fresh() {
  vi.resetModules();
  return (await import('./device.svelte')).deviceSession;
}
function authOf(call: unknown[]): string | undefined {
  return ((call[1] as RequestInit).headers as Record<string, string>).Authorization;
}

describe('deviceSession', () => {
  let fetchMock: ReturnType<typeof vi.fn>;
  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });
  afterEach(() => vi.unstubAllGlobals());

  it('pairs, picks a profile and uses the right token for each call', async () => {
    const storage = memoryStorage();
    vi.stubGlobal('localStorage', storage);
    fetchMock
      .mockResolvedValueOnce(ok({ device_token: 'd_dev', device: { id: 1, name: 'Tablet' } }, 201))
      .mockResolvedValueOnce(ok([child]))
      .mockResolvedValueOnce(ok({ child_token: 'c_kid', child }, 201));
    const s = await fresh();

    await s.pair('abc-def', 'Tablet');
    expect(authOf(fetchMock.mock.calls[0])).toBeUndefined();
    expect(await s.profiles()).toHaveLength(1);
    expect(authOf(fetchMock.mock.calls[1])).toBe('Bearer d_dev');
    await s.selectChild(7);

    expect(s.isChildLoggedIn).toBe(true);
    expect(storage.map.get('hq.device')).toBe('d_dev');
    expect(storage.map.get('hq.child')).toBe('c_kid');

    s.switchProfile();
    expect(storage.map.has('hq.child')).toBe(false);
    expect(s.isPaired).toBe(true);
  });

  it('restores device and child sessions', async () => {
    vi.stubGlobal('localStorage', memoryStorage({ 'hq.device': 'd_dev', 'hq.child': 'c_kid' }));
    fetchMock
      .mockResolvedValueOnce(ok({ device: { id: 1, name: 'Tablet' }, family, role: 'device' }))
      .mockResolvedValueOnce(ok({ child, family, role: 'child' }));
    const s = await fresh();

    await s.ready();

    expect(s.isChildLoggedIn).toBe(true);
    expect(s.child?.name).toBe('Luca');
    expect(authOf(fetchMock.mock.calls[1])).toBe('Bearer c_kid');
  });

  it('forgets a revoked device', async () => {
    const storage = memoryStorage({ 'hq.device': 'd_dev', 'hq.child': 'c_kid' });
    vi.stubGlobal('localStorage', storage);
    fetchMock.mockResolvedValueOnce(unauthorized());
    const s = await fresh();

    await s.ready();

    expect(s.isPaired).toBe(false);
    expect(storage.map.size).toBe(0);
  });

  it('drops only the child session when it is no longer valid', async () => {
    const storage = memoryStorage({ 'hq.device': 'd_dev', 'hq.child': 'c_old' });
    vi.stubGlobal('localStorage', storage);
    fetchMock
      .mockResolvedValueOnce(ok({ device: { id: 1, name: 'Tablet' }, family, role: 'device' }))
      .mockResolvedValueOnce(unauthorized());
    const s = await fresh();

    await s.ready();

    expect(s.isPaired).toBe(true);
    expect(s.isChildLoggedIn).toBe(false);
    expect(storage.map.has('hq.child')).toBe(false);
  });
});
