<script lang="ts">
  import { listShoppingLists } from '#lib/api/endpoints/shopping.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import ShoppingItems from '#lib/components/shopping/ShoppingItems.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';
  import { ShoppingStore } from '#lib/stores/shopping.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  const store = new ShoppingStore(deviceSession.childToken ?? undefined);
  let allowed = $state(true);

  function fail(e: unknown) {
    if (e instanceof ApiError && e.status === 403) allowed = false;
    else toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  $effect(() => {
    let stop: (() => void) | undefined;
    listShoppingLists(store.opts)
      .then(async (lists) => {
        const list = lists.find((l) => l.is_default) ?? lists[0];
        if (!list) return;
        await store.load(list.id);
        stop = store.startPolling(
          list.id,
          deviceSession.family?.settings.shopping_poll_seconds ?? 5
        );
      })
      .catch(fail);
    return () => stop?.();
  });
</script>

<svelte:head>
  <title>{t('kid_shopping.title')} · {t('app.name')}</title>
</svelte:head>

<main class="mx-auto flex max-w-3xl flex-col gap-6 p-4 sm:p-6">
  <h1 class="text-3xl font-bold">🛒 {t('kid_shopping.title')}</h1>
  {#if !allowed}
    <p class="rounded-card bg-surface p-6 text-center text-xl">{t('kid_shopping.not_allowed')}</p>
  {:else if store.list}
    {#if store.list.items.length === 0}
      <p class="rounded-card bg-surface p-6 text-center text-xl">{t('kid_shopping.empty')}</p>
    {:else}
      <ShoppingItems
        items={store.list.items}
        categories={[]}
        kid
        ontoggle={(item) => store.setChecked(item, !item.checked).catch(fail)}
      />
    {/if}
  {:else}
    <p class="text-center text-ink-muted">{t('common.loading')}</p>
  {/if}
</main>
