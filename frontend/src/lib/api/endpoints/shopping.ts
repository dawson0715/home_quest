import { api } from '../client';
import type {
  Envelope,
  FavoriteItem,
  ShoppingItemInput,
  ShoppingList,
  ShoppingListSummary,
  SyncOp
} from '../types';

type Opts = { token?: string };

export async function listShoppingLists(opts: Opts = {}): Promise<ShoppingListSummary[]> {
  return (await api.get<ShoppingListSummary[]>('shopping-lists', opts)).data;
}

/** null when unchanged since `etag` (304). */
export async function getShoppingList(
  id: number,
  etag?: string,
  opts: Opts = {}
): Promise<ShoppingList | null> {
  const res = await api.get<ShoppingList | null>(`shopping-lists/${id}`, {
    ...opts,
    ifNoneMatch: etag
  });
  return res.meta.not_modified ? null : res.data;
}

export async function createShoppingList(name: string): Promise<ShoppingList> {
  return (await api.post<ShoppingList>('shopping-lists', { name })).data;
}

export async function deleteShoppingList(id: number): Promise<void> {
  await api.delete(`shopping-lists/${id}`);
}

export async function addShoppingItem(
  listId: number,
  input: ShoppingItemInput
): Promise<ShoppingList> {
  return (await api.post<ShoppingList>(`shopping-lists/${listId}/items`, input)).data;
}

export async function deleteShoppingItem(itemId: number): Promise<ShoppingList> {
  return (await api.delete<ShoppingList>(`shopping-items/${itemId}`)).data;
}

export async function setShoppingItemChecked(
  itemId: number,
  checked: boolean,
  opts: Opts = {}
): Promise<ShoppingList> {
  return (
    await api.post<ShoppingList>(
      `shopping-items/${itemId}/${checked ? 'check' : 'uncheck'}`,
      {},
      opts
    )
  ).data;
}

export async function clearCheckedItems(listId: number): Promise<ShoppingList> {
  return (await api.post<ShoppingList>(`shopping-lists/${listId}/clear-checked`)).data;
}

export async function syncShoppingList(
  listId: number,
  ops: SyncOp[],
  opts: Opts = {}
): Promise<ShoppingList> {
  return (await api.post<ShoppingList>(`shopping-lists/${listId}/sync`, { ops }, opts)).data;
}

export async function generateShoppingList(
  listId: number,
  weekStart: string
): Promise<Envelope<ShoppingList>> {
  return api.post<ShoppingList>(`shopping-lists/${listId}/generate`, { week_start: weekStart });
}

export async function setPantry(
  listId: number,
  ingredientId: number,
  excluded: boolean
): Promise<ShoppingList> {
  const path = `shopping-lists/${listId}/pantry/${ingredientId}`;
  return (excluded ? await api.put<ShoppingList>(path) : await api.delete<ShoppingList>(path)).data;
}

export async function setCategoryOrder(ids: number[]): Promise<number[]> {
  return (await api.put<{ ids: number[] }>('shopping-categories/order', { ids })).data.ids;
}

export async function listFavoriteItems(): Promise<FavoriteItem[]> {
  return (await api.get<FavoriteItem[]>('favorite-items')).data;
}
