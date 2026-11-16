import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { ShoppingList } from '../api/types';

function memoryStorage() {
  const map = new Map<string, string>();
  return {
    getItem: (k: string) => map.get(k) ?? null,
    setItem: (k: string, v: string) => void map.set(k, v),
    removeItem: (k: string) => void map.delete(k),
    map
  };
}

const base: ShoppingList = {
  id: 1,
  name: 'Spesa',
  is_default: true,
  version: 1,
  linked_week_id: null,
  pantry: [],
  items: [
    {
      id: 10,
      ingredient_id: null,
      label: 'Latte',
      quantity: null,
      unit: null,
      category_id: 3,
      source: 'manual',
      checked: false,
      checked_at: null,
      client_uuid: null
    }
  ]
};

function ok(list: ShoppingList) {
  return new Response(JSON.stringify({ data: list, meta: {} }), { status: 200 });
}

describe('ShoppingStore', () => {
  let fetchMock: ReturnType<typeof vi.fn>;
  beforeEach(() => {
    fetchMock = vi.fn();
    vi.stubGlobal('fetch', fetchMock);
    vi.stubGlobal('localStorage', memoryStorage());
  });
  afterEach(() => vi.unstubAllGlobals());

  it('loads with ETag and keeps the list on 304', async () => {
    const { ShoppingStore } = await import('./shopping.svelte');
    fetchMock
      .mockResolvedValueOnce(ok(base))
      .mockResolvedValueOnce(new Response(null, { status: 304 }));
    const store = new ShoppingStore();

    await store.load(1);
    await store.load(1);

    expect(store.list?.items[0].label).toBe('Latte');
    const second = fetchMock.mock.calls[1][1] as RequestInit;
    expect((second.headers as Record<string, string>)['If-None-Match']).toBe('"v1"');
  });

  it('queues operations while offline and flushes them with /sync', async () => {
    const { ShoppingStore } = await import('./shopping.svelte');
    fetchMock.mockResolvedValueOnce(ok(base));
    const store = new ShoppingStore();
    await store.load(1);

    fetchMock.mockRejectedValue(new TypeError('offline'));
    await store.setChecked(store.list!.items[0], true);
    await store.add({ label: 'Pane' });

    expect(store.offline).toBe(true);
    expect(store.pending).toBe(2);
    expect(store.list?.items.map((i) => [i.label, i.checked])).toEqual([
      ['Latte', true],
      ['Pane', false]
    ]);

    const synced = { ...base, version: 3, items: [{ ...base.items[0], checked: true }] };
    fetchMock.mockReset();
    fetchMock
      .mockResolvedValueOnce(ok(synced))
      .mockResolvedValueOnce(new Response(null, { status: 304 }));
    await store.load(1);

    const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
    expect(url).toBe('/api/v1/shopping-lists/1/sync');
    const ops = JSON.parse(init.body as string).ops;
    expect(ops.map((o: { op: string }) => o.op)).toEqual(['check', 'add']);
    expect(ops[1].client_uuid).toBe(ops[1].data.client_uuid);
    expect(store.pending).toBe(0);
    expect(store.offline).toBe(false);
  });

  it('uses the child token when given', async () => {
    const { ShoppingStore } = await import('./shopping.svelte');
    fetchMock.mockResolvedValueOnce(ok(base));
    await new ShoppingStore('c_kid').load(1);
    const init = fetchMock.mock.calls[0][1] as RequestInit;
    expect((init.headers as Record<string, string>).Authorization).toBe('Bearer c_kid');
  });
});
