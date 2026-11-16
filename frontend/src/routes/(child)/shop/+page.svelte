<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { getChildMe } from '#lib/api/endpoints/devices.ts';
  import {
    listMyRedemptions,
    listShop,
    redeemReward,
    setSavingsGoal
  } from '#lib/api/endpoints/rewards.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Child, Redemption, ShopReward } from '#lib/api/types.ts';
  import PointsCounter from '#lib/components/child/PointsCounter.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  let child = $state<Child | null>(deviceSession.child);
  let shop = $state<ShopReward[] | null>(null);
  let requests = $state<Redemption[]>([]);
  let busy = $state<number | null>(null);

  const token = $derived(deviceSession.childToken ?? '');
  const goal = $derived(shop?.find((r) => r.is_goal) ?? null);
  const pending = $derived(
    requests.filter((r) => r.status === 'requested' || r.status === 'approved')
  );

  async function fail(e: unknown) {
    if (e instanceof ApiError && e.status === 401) {
      deviceSession.switchProfile();
      await goto(resolve('/(child)/profiles'));
      return;
    }
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function load() {
    try {
      const [me, list, mine] = await Promise.all([
        getChildMe(token),
        listShop(token),
        listMyRedemptions(token)
      ]);
      child = me.child;
      shop = list;
      requests = mine;
    } catch (e) {
      await fail(e);
    }
  }

  $effect(() => {
    load();
  });

  async function want(reward: ShopReward) {
    busy = reward.id;
    try {
      await redeemReward(token, reward.id);
      toasts.success(t('shop.requested'));
      await load();
    } catch (e) {
      await fail(e);
    } finally {
      busy = null;
    }
  }

  async function toggleGoal(reward: ShopReward) {
    busy = reward.id;
    try {
      await setSavingsGoal(token, reward.is_goal ? null : reward.id);
      if (!reward.is_goal) toasts.success(t('shop.goal_set'));
      await load();
    } catch (e) {
      await fail(e);
    } finally {
      busy = null;
    }
  }

  const missing = (r: ShopReward) => Math.max(0, r.cost - (child?.points_balance ?? 0));
</script>

<svelte:head>
  <title>{t('shop.title')} · {t('app.name')}</title>
</svelte:head>

<main class="mx-auto flex max-w-3xl flex-col gap-6 p-4 sm:p-6">
  <header class="flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-3xl font-bold">🎁 {t('shop.title')}</h1>
    {#if child}
      <p class="text-2xl font-semibold"><PointsCounter points={child.points_balance} /></p>
    {/if}
  </header>

  {#if goal && child}
    <section class="flex flex-col gap-2 rounded-card bg-surface p-5 shadow-sm" data-testid="goal">
      <p class="text-xl font-semibold">
        <span aria-hidden="true">{goal.icon}</span>
        {t('child.today.goal', { title: goal.title })}
      </p>
      <span
        class="h-5 overflow-hidden rounded-full bg-line"
        role="progressbar"
        aria-valuemin={0}
        aria-valuemax={goal.cost}
        aria-valuenow={Math.min(goal.cost, child.points_balance)}
      >
        <span
          class="block h-full bg-success"
          style:width="{Math.min(100, (child.points_balance / goal.cost) * 100)}%"
        ></span>
      </span>
      <p class="text-ink-muted">
        {t('child.today.goal_progress', {
          points: Math.min(goal.cost, child.points_balance),
          cost: goal.cost
        })}
      </p>
    </section>
  {/if}

  {#if shop === null}
    <p class="text-center text-ink-muted">{t('common.loading')}</p>
  {:else if shop.length === 0}
    <p class="rounded-card bg-surface p-6 text-center">{t('shop.empty')}</p>
  {:else}
    <ul class="grid gap-4 sm:grid-cols-2">
      {#each shop as reward (reward.id)}
        <li class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-sm">
          <div class="flex items-center gap-3">
            <span class="text-5xl" aria-hidden="true">{reward.icon}</span>
            <span class="flex flex-1 flex-col">
              <span class="text-xl font-bold">{reward.title}</span>
              <span class="font-semibold text-brand"
                >⭐ {t('shop.cost', { cost: reward.cost })}</span
              >
            </span>
            <button
              type="button"
              class="grid size-14 place-items-center rounded-full text-3xl hover:bg-surface-muted"
              aria-pressed={reward.is_goal}
              aria-label={reward.is_goal ? t('shop.unset_goal') : t('shop.set_goal')}
              disabled={busy === reward.id}
              onclick={() => toggleGoal(reward)}>{reward.is_goal ? '★' : '☆'}</button
            >
          </div>
          {#if !reward.available}
            <p class="text-center text-ink-muted">{t('shop.unavailable')}</p>
          {:else if !reward.affordable}
            <p class="text-center text-ink-muted">
              {t('shop.not_enough', { missing: missing(reward) })}
            </p>
          {:else}
            <button
              type="button"
              class="min-h-16 rounded-card bg-brand text-2xl font-bold text-brand-contrast active:scale-[0.98] disabled:opacity-40 motion-reduce:active:scale-100"
              disabled={busy === reward.id}
              onclick={() => want(reward)}
            >
              {t('shop.want')}
            </button>
          {/if}
        </li>
      {/each}
    </ul>
  {/if}

  {#if pending.length > 0}
    <section class="flex flex-col gap-2">
      <h2 class="text-xl font-semibold text-ink-muted">{t('shop.my_requests')}</h2>
      <ul class="flex flex-col gap-2">
        {#each pending as r (r.id)}
          <li class="flex items-center gap-3 rounded-card bg-surface p-4">
            <span class="text-3xl" aria-hidden="true">{r.icon}</span>
            <span class="flex-1 font-semibold">{r.title}</span>
            <span>{r.status === 'requested' ? '⏳' : '🎉'} {t(`shop.status.${r.status}`)}</span>
          </li>
        {/each}
      </ul>
    </section>
  {/if}
</main>
