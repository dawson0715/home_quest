<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { page } from '$app/state';
  import { listShoppingCategories } from '#lib/api/endpoints/meals.ts';
  import {
    clearCheckedItems,
    createShoppingList,
    deleteShoppingList,
    generateShoppingList,
    listFavoriteItems,
    listShoppingLists,
    setCategoryOrder,
    setPantry
  } from '#lib/api/endpoints/shopping.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type {
    FavoriteItem,
    ShoppingCategory,
    ShoppingItem,
    ShoppingListSummary
  } from '#lib/api/types.ts';
  import ShoppingItems from '#lib/components/shopping/ShoppingItems.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';
  import { ShoppingStore } from '#lib/stores/shopping.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { categoryName } from '#lib/utils/categories.ts';
  import { todayInRome, weekStartOf } from '#lib/utils/dates.ts';

  const store = new ShoppingStore();
  let lists = $state<ShoppingListSummary[]>([]);
  let categories = $state<ShoppingCategory[]>([]);
  let favorites = $state<FavoriteItem[]>([]);
  let label = $state('');
  let newListOpen = $state(false);
  let newListName = $state('');
  let reorderOpen = $state(false);

  const listId = $derived(Number(page.params.id ?? 0) || lists.find((l) => l.is_default)?.id || 0);
  const settings = $derived(session.me?.family?.settings);

  function fail(e: unknown) {
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function refreshMeta() {
    [lists, categories, favorites] = await Promise.all([
      listShoppingLists(),
      listShoppingCategories(),
      listFavoriteItems()
    ]);
  }

  $effect(() => {
    refreshMeta().catch(fail);
  });

  $effect(() => {
    const id = listId;
    if (!id) return;
    store.load(id).catch(fail);
    return store.startPolling(id, settings?.shopping_poll_seconds ?? 5);
  });

  async function add(event: SubmitEvent) {
    event.preventDefault();
    const text = label.trim();
    if (!text) return;
    label = '';
    await store.add({ label: text }).catch(fail);
  }

  async function addFavorite(fav: FavoriteItem) {
    await store
      .add({
        label: fav.label,
        category_id: fav.category_id,
        ...(fav.ingredient_id ? { ingredient_id: fav.ingredient_id } : {})
      })
      .catch(fail);
  }

  async function have(item: ShoppingItem) {
    if (!store.list || !item.ingredient_id) return;
    try {
      store.apply(await setPantry(store.list.id, item.ingredient_id, true));
    } catch (e) {
      fail(e);
    }
  }

  async function generate() {
    if (!store.list) return;
    try {
      const res = await generateShoppingList(
        store.list.id,
        weekStartOf(todayInRome(), settings?.week_starts_on ?? 1)
      );
      store.apply(res.data);
      toasts.success(
        t('shopping.generated', {
          added: Number(res.meta.added ?? 0),
          updated: Number(res.meta.updated ?? 0)
        })
      );
    } catch (e) {
      fail(e);
    }
  }

  async function clearChecked() {
    if (!store.list) return;
    try {
      store.apply(await clearCheckedItems(store.list.id));
    } catch (e) {
      fail(e);
    }
  }

  async function createList(event: SubmitEvent) {
    event.preventDefault();
    try {
      const list = await createShoppingList(newListName);
      newListOpen = false;
      newListName = '';
      await refreshMeta();
      await goto(resolve('/(parent)/shopping/[[id]]', { id: String(list.id) }));
    } catch (e) {
      fail(e);
    }
  }

  async function deleteList() {
    if (!store.list || store.list.is_default) return;
    try {
      await deleteShoppingList(store.list.id);
      await refreshMeta();
      await goto(resolve('/(parent)/shopping/[[id]]', {}));
    } catch (e) {
      fail(e);
    }
  }

  async function move(index: number, delta: number) {
    const next = [...categories];
    const [c] = next.splice(index, 1);
    next.splice(index + delta, 0, c);
    categories = next;
    try {
      await setCategoryOrder(next.map((x) => x.id));
    } catch (e) {
      fail(e);
    }
  }
</script>

<svelte:head>
  <title>{t('shopping.title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-3xl flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-2xl font-semibold">{t('shopping.title')}</h1>
    <div class="flex flex-wrap gap-2">
      <Button variant="secondary" onclick={generate}>🍽️ {t('shopping.generate')}</Button>
      <Button variant="ghost" onclick={() => (reorderOpen = true)}>{t('shopping.reorder')}</Button>
    </div>
  </div>

  <nav class="flex flex-wrap gap-1">
    {#each lists as l (l.id)}
      <a
        href={resolve('/(parent)/shopping/[[id]]', { id: String(l.id) })}
        class="flex min-h-11 items-center rounded-full px-4 {l.id === listId
          ? 'bg-brand text-brand-contrast'
          : 'bg-surface'}"
        aria-current={l.id === listId ? 'page' : undefined}>{l.name} ({l.open_count})</a
      >
    {/each}
    <button
      type="button"
      class="min-h-11 rounded-full px-4 hover:bg-surface"
      onclick={() => (newListOpen = true)}>+ {t('shopping.new_list')}</button
    >
  </nav>

  {#if store.offline}
    <p class="rounded-lg bg-brand/10 p-3 text-sm" role="status">📡 {t('shopping.offline')}</p>
  {:else if store.pending > 0}
    <p class="text-sm text-ink-muted" role="status">
      {t('shopping.pending', { n: store.pending })}
    </p>
  {/if}

  <form class="flex gap-2" onsubmit={add}>
    <input
      class="min-h-12 flex-1 rounded-card border border-line bg-surface px-4 text-lg"
      placeholder={t('shopping.add_placeholder')}
      aria-label={t('shopping.add_placeholder')}
      bind:value={label}
      maxlength={120}
    />
    <Button type="submit" size="lg">{t('shopping.add')}</Button>
  </form>
  {#if favorites.length > 0}
    <div class="flex flex-wrap items-center gap-1" aria-label={t('shopping.favorites')}>
      <span class="text-sm text-ink-muted">{t('shopping.favorites')}:</span>
      {#each favorites.slice(0, 12) as fav (fav.id)}
        <button
          type="button"
          class="min-h-9 rounded-full bg-surface px-3 text-sm hover:bg-brand/10"
          onclick={() => addFavorite(fav)}>+ {fav.label}</button
        >
      {/each}
    </div>
  {/if}

  {#if store.list}
    {#if store.list.items.length === 0}
      <p class="rounded-card bg-surface p-6 text-center text-ink-muted">{t('shopping.empty')}</p>
    {:else}
      <ShoppingItems
        items={store.list.items}
        {categories}
        ontoggle={(item) => store.setChecked(item, !item.checked).catch(fail)}
        onremove={(item) => store.remove(item).catch(fail)}
        onhave={have}
      />
    {/if}
    <div class="flex flex-wrap justify-between gap-2">
      <Button variant="ghost" onclick={clearChecked}>{t('shopping.clear_checked')}</Button>
      {#if !store.list.is_default}
        <Button variant="danger" onclick={deleteList}>{t('shopping.delete_list')}</Button>
      {/if}
    </div>
  {:else}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {/if}
</div>

<Modal bind:open={newListOpen} title={t('shopping.new_list')}>
  <form class="flex items-end gap-2" onsubmit={createList}>
    <Input
      id="new-list-name"
      class="flex-1"
      label={t('shopping.new_list_name')}
      bind:value={newListName}
      maxlength={60}
      required
    />
    <Button type="submit">{t('common.save')}</Button>
  </form>
</Modal>

<Modal bind:open={reorderOpen} title={t('shopping.reorder_title')}>
  <ol class="flex flex-col gap-1">
    {#each categories as category, index (category.id)}
      <li class="flex items-center gap-2 rounded-lg bg-surface-muted px-3">
        <span class="flex-1">{category.icon} {categoryName(category)}</span>
        <button
          type="button"
          class="size-11 disabled:opacity-30"
          aria-label={t('shopping.move_up', { name: categoryName(category) })}
          disabled={index === 0}
          onclick={() => move(index, -1)}>↑</button
        >
        <button
          type="button"
          class="size-11 disabled:opacity-30"
          aria-label={t('shopping.move_down', { name: categoryName(category) })}
          disabled={index === categories.length - 1}
          onclick={() => move(index, 1)}>↓</button
        >
      </li>
    {/each}
  </ol>
</Modal>
