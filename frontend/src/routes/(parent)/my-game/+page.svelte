<script lang="ts">
  import { getToday, setChecklistItem, submitOccurrence } from '#lib/api/endpoints/occurrences.ts';
  import { listShop, redeemReward } from '#lib/api/endpoints/rewards.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import { TIMES_OF_DAY, type Occurrence, type ShopReward, type Today } from '#lib/api/types.ts';
  import Celebration from '#lib/components/child/Celebration.svelte';
  import PointsCounter from '#lib/components/child/PointsCounter.svelte';
  import TaskCard from '#lib/components/child/TaskCard.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  // Parents play with their own session: no explicit token
  let today = $state<Today | null>(null);
  let shop = $state<ShopReward[]>([]);
  let busyId = $state<number | null>(null);
  let celebration = $state<number | null>(null);
  let unavailable = $state(false);

  const occurrences = $derived(today ? TIMES_OF_DAY.flatMap((g) => today?.groups[g] ?? []) : []);

  function fail(e: unknown) {
    if (e instanceof ApiError && e.code === 'forbidden') unavailable = true;
    else toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function load() {
    try {
      [today, shop] = await Promise.all([getToday(), listShop()]);
    } catch (e) {
      fail(e);
    }
  }

  $effect(() => {
    load();
  });

  async function toggle(o: Occurrence, itemId: number, done: boolean) {
    try {
      await setChecklistItem(undefined, o.id, itemId, done);
      await load();
    } catch (e) {
      fail(e);
    }
  }

  async function submit(o: Occurrence) {
    busyId = o.id;
    try {
      const result = await submitOccurrence(undefined, o.id);
      if (result.occurrence.status === 'approved') celebration = result.points_awarded;
      await load();
    } catch (e) {
      fail(e);
      await load();
    } finally {
      busyId = null;
    }
  }

  async function redeem(reward: ShopReward) {
    try {
      await redeemReward(undefined, reward.id);
      toasts.success(t('shop.requested'));
      await load();
    } catch (e) {
      fail(e);
    }
  }
</script>

<svelte:head>
  <title>{t('my_game.title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-3xl flex-col gap-6">
  <header class="flex flex-wrap items-center justify-between gap-2">
    <div>
      <h1 class="text-2xl font-semibold">{t('my_game.title')}</h1>
      <p class="text-ink-muted">{t('my_game.intro')}</p>
    </div>
    {#if today}
      <p class="text-xl font-semibold"><PointsCounter points={today.child.points_balance} /></p>
    {/if}
  </header>

  {#if unavailable}
    <p class="text-ink-muted">{t('my_game.no_player')}</p>
  {:else if today}
    <section class="flex flex-col gap-3">
      <h2 class="text-lg font-semibold">{t('my_game.tasks')}</h2>
      {#each occurrences as occurrence (occurrence.id)}
        <TaskCard
          {occurrence}
          busy={busyId === occurrence.id}
          ontoggle={(itemId, done) => toggle(occurrence, itemId, done)}
          onsubmit={() => submit(occurrence)}
        />
      {:else}
        <p class="rounded-card bg-surface p-5 text-ink-muted">{t('child.today.empty')}</p>
      {/each}
    </section>

    {#if shop.length > 0}
      <section class="flex flex-col gap-2">
        <h2 class="text-lg font-semibold">{t('my_game.shop')}</h2>
        <ul class="grid gap-2 sm:grid-cols-2">
          {#each shop as reward (reward.id)}
            <li class="flex items-center gap-3 rounded-card bg-surface p-4 shadow-sm">
              <span class="text-3xl" aria-hidden="true">{reward.icon}</span>
              <span class="flex flex-1 flex-col">
                <span class="font-semibold">{reward.title}</span>
                <span class="text-sm text-ink-muted">{t('shop.cost', { cost: reward.cost })}</span>
              </span>
              <Button
                variant="secondary"
                disabled={!reward.affordable || !reward.available}
                onclick={() => redeem(reward)}>{t('shop.want')}</Button
              >
            </li>
          {/each}
        </ul>
      </section>
    {/if}
  {:else}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {/if}
</div>

{#if celebration !== null}
  <Celebration points={celebration} onclose={() => (celebration = null)} />
{/if}
