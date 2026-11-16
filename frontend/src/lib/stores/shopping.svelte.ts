import { getShoppingList, syncShoppingList } from '../api/endpoints/shopping';
import { ApiError, NETWORK_ERROR } from '../api/errors';
import type { ShoppingItem, ShoppingItemInput, ShoppingList, SyncOp } from '../api/types';

const QUEUE_KEY = 'hq.shopping.queue';

function readQueue(): Record<string, SyncOp[]> {
  try {
    return JSON.parse(globalThis.localStorage?.getItem(QUEUE_KEY) ?? '{}') as Record<
      string,
      SyncOp[]
    >;
  } catch {
    return {};
  }
}

function writeQueue(queue: Record<string, SyncOp[]>): void {
  try {
    globalThis.localStorage?.setItem(QUEUE_KEY, JSON.stringify(queue));
  } catch {
    /* ignore */
  }
}

/** Plain helper: the timestamp is data, not reactive state. */
function nowIso(): string {
  return new Date(Date.now()).toISOString();
}

function uuid(): string {
  return (
    globalThis.crypto?.randomUUID?.() ??
    'xxxxxxxx-xxxx-4xxx-8xxx-xxxxxxxxxxxx'.replace(/x/g, () =>
      ((Math.random() * 16) | 0).toString(16)
    )
  );
}

/**
 * One open shopping list: optimistic changes, polling with ETag, and an offline
 * queue (localStorage) flushed through POST /sync — idempotent thanks to client_uuid.
 * Parents pass no token; kids' devices pass their child token.
 */
export class ShoppingStore {
  list = $state<ShoppingList | null>(null);
  pending = $state(0);
  offline = $state(false);
  #etag: string | undefined;
  #token: string | undefined;
  #timer: ReturnType<typeof setInterval> | undefined;

  constructor(token?: string) {
    this.#token = token;
  }

  get opts(): { token?: string } {
    return this.#token ? { token: this.#token } : {};
  }

  async load(listId: number): Promise<void> {
    if (this.list?.id !== listId) {
      this.list = null;
      this.#etag = undefined;
    }
    await this.flush(listId);
    const fresh = await getShoppingList(listId, this.#etag, this.opts);
    if (fresh) this.apply(fresh);
    this.offline = false;
  }

  /** Polls while the page is visible. Returns a stop function. */
  startPolling(listId: number, seconds: number): () => void {
    this.stopPolling();
    this.#timer = setInterval(() => {
      if (globalThis.document?.visibilityState === 'hidden') return;
      this.load(listId).catch(() => (this.offline = true));
    }, seconds * 1000);
    const online = () => this.load(listId).catch(() => undefined);
    globalThis.addEventListener?.('online', online);
    return () => {
      this.stopPolling();
      globalThis.removeEventListener?.('online', online);
    };
  }

  stopPolling(): void {
    clearInterval(this.#timer);
  }

  apply(list: ShoppingList): void {
    this.list = list;
    this.#etag = `"v${list.version}"`;
    this.#reapplyQueue();
  }

  async add(input: ShoppingItemInput): Promise<void> {
    const list = this.list;
    if (!list) return;
    const clientUuid = uuid();
    list.items = [
      ...list.items,
      {
        id: -Date.now(),
        ingredient_id: input.ingredient_id ?? null,
        label: input.label ?? '',
        quantity: input.quantity ?? null,
        unit: input.unit ?? null,
        category_id: input.category_id ?? 0,
        source: 'manual',
        checked: false,
        checked_at: null,
        client_uuid: clientUuid
      }
    ];
    await this.#send(list.id, {
      op: 'add',
      client_uuid: clientUuid,
      data: { ...input, client_uuid: clientUuid },
      at: nowIso()
    });
  }

  async setChecked(item: ShoppingItem, checked: boolean): Promise<void> {
    const list = this.list;
    if (!list) return;
    item.checked = checked;
    await this.#send(list.id, {
      op: checked ? 'check' : 'uncheck',
      ...(item.id > 0 ? { item_id: item.id } : { client_uuid: item.client_uuid ?? undefined }),
      at: nowIso()
    });
  }

  async remove(item: ShoppingItem): Promise<void> {
    const list = this.list;
    if (!list) return;
    list.items = list.items.filter((i) => i !== item);
    await this.#send(list.id, {
      op: 'delete',
      ...(item.id > 0 ? { item_id: item.id } : { client_uuid: item.client_uuid ?? undefined }),
      at: nowIso()
    });
  }

  /** Sends queued operations (if any) in one /sync call. */
  async flush(listId: number): Promise<void> {
    const queue = readQueue();
    const ops = queue[listId] ?? [];
    if (ops.length === 0) return;
    const list = await syncShoppingList(listId, ops, this.opts);
    delete queue[listId];
    writeQueue(queue);
    this.pending = 0;
    this.apply(list);
  }

  async #send(listId: number, op: SyncOp): Promise<void> {
    const queue = readQueue();
    queue[listId] = [...(queue[listId] ?? []), op];
    writeQueue(queue);
    this.pending = queue[listId].length;
    try {
      await this.flush(listId);
      this.offline = false;
    } catch (e) {
      if (e instanceof ApiError && e.code === NETWORK_ERROR) {
        this.offline = true; // stays queued, retried on next poll / online event
        return;
      }
      // rejected by the server: drop the queue and resync
      const q = readQueue();
      delete q[listId];
      writeQueue(q);
      this.pending = 0;
      await this.load(listId).catch(() => undefined);
      throw e;
    }
  }

  /** Re-applies still-queued ops on top of a server snapshot (offline view). */
  #reapplyQueue(): void {
    const list = this.list;
    if (!list) return;
    const ops = readQueue()[list.id] ?? [];
    this.pending = ops.length;
    for (const op of ops) {
      const match = (i: ShoppingItem) =>
        ('item_id' in op && op.item_id !== undefined && i.id === op.item_id) ||
        (op.client_uuid !== undefined && i.client_uuid === op.client_uuid);
      if (op.op === 'add' && !list.items.some(match)) {
        list.items.push({
          id: -1,
          ingredient_id: op.data.ingredient_id ?? null,
          label: op.data.label ?? '',
          quantity: op.data.quantity ?? null,
          unit: op.data.unit ?? null,
          category_id: op.data.category_id ?? 0,
          source: 'manual',
          checked: false,
          checked_at: null,
          client_uuid: op.client_uuid
        });
      } else if (op.op === 'check' || op.op === 'uncheck') {
        const item = list.items.find(match);
        if (item) item.checked = op.op === 'check';
      } else if (op.op === 'delete') {
        list.items = list.items.filter((i) => !match(i));
      }
    }
  }
}
