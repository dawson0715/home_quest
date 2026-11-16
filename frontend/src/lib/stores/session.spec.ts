import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const me = {
  user: { id: 2, username: 'anna', name: 'Anna' },
  family: { id: 1, name: 'Rossi', settings: {} },
  role: 'admin'
};

function respond(status: number, data: unknown, errors?: unknown[]) {
  return new Response(JSON.stringify({ data, meta: errors ? { errors } : {} }), { status });
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

async function freshSession() {
  vi.resetModules();
  return (await import('./session.svelte')).session;
}

describe('session', () => {
  let fetchMock: ReturnType<typeof vi.fn>;

  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
  });
  afterEach(() => vi.unstubAllGlobals());

  it('logs in with a valid token, stores it and sends it as bearer', async () => {
    const storage = memoryStorage();
    vi.stubGlobal('localStorage', storage);
    fetchMock.mockResolvedValue(respond(200, me));
    const session = await freshSession();

    await session.login('  tok123 ');

    expect(session.isAuthenticated).toBe(true);
    expect(session.role).toBe('admin');
    expect(storage.map.get('hq.token')).toBe('tok123');
    const init = fetchMock.mock.calls[0][1] as RequestInit;
    expect((init.headers as Record<string, string>).Authorization).toBe('Bearer tok123');
  });

  it('rejects an invalid token and keeps nothing', async () => {
    const storage = memoryStorage();
    vi.stubGlobal('localStorage', storage);
    fetchMock.mockResolvedValue(respond(401, null, [{ code: 'unauthorized', message: 'x' }]));
    const session = await freshSession();

    await expect(session.login('bad')).rejects.toMatchObject({ code: 'unauthorized' });
    expect(session.isAuthenticated).toBe(false);
    expect(storage.map.size).toBe(0);
  });

  it('rejects users without a family', async () => {
    vi.stubGlobal('localStorage', memoryStorage());
    fetchMock.mockResolvedValue(respond(200, { ...me, family: null, role: null }));
    const session = await freshSession();

    await expect(session.login('tok')).rejects.toMatchObject({ code: 'no_family' });
    expect(session.token).toBeNull();
  });

  it('restores a stored token and drops it when revoked', async () => {
    vi.stubGlobal('localStorage', memoryStorage({ 'hq.token': 'old' }));
    fetchMock.mockResolvedValue(respond(401, null, [{ code: 'unauthorized', message: 'x' }]));
    const session = await freshSession();

    await session.ready();

    expect(session.isAuthenticated).toBe(false);
    expect(localStorage.getItem('hq.token')).toBeNull();
  });

  it('keeps the token when the server is unreachable', async () => {
    vi.stubGlobal('localStorage', memoryStorage({ 'hq.token': 'tok' }));
    fetchMock.mockRejectedValue(new TypeError('Failed to fetch'));
    const session = await freshSession();

    await session.ready();

    expect(session.token).toBe('tok');
    expect(session.isAuthenticated).toBe(false);
  });

  it('works without localStorage', async () => {
    fetchMock.mockResolvedValue(respond(200, me));
    const session = await freshSession();

    await session.ready();
    await session.login('tok');

    expect(session.isAuthenticated).toBe(true);
  });
});
