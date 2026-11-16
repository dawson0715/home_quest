<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { getChildMe } from '#lib/api/endpoints/devices.ts';
  import { listMyBadges } from '#lib/api/endpoints/rewards.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { BadgeStatus, Child } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { levelProgress, xpForLevel } from '#lib/utils/levels.ts';

  let child = $state<Child | null>(deviceSession.child);
  let badges = $state<BadgeStatus[] | null>(null);
  const token = $derived(deviceSession.childToken ?? '');

  $effect(() => {
    Promise.all([getChildMe(token), listMyBadges(token)])
      .then(([me, list]) => {
        child = me.child;
        badges = list;
      })
      .catch(async (e) => {
        if (e instanceof ApiError && e.status === 401) {
          deviceSession.switchProfile();
          await goto(resolve('/(child)/profiles'));
        } else toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
      });
  });
</script>

<svelte:head>
  <title>{t('trophies.title')} · {t('app.name')}</title>
</svelte:head>

<main class="mx-auto flex max-w-3xl flex-col gap-6 p-4 sm:p-6">
  <h1 class="text-3xl font-bold">🏆 {t('trophies.title')}</h1>

  {#if child}
    <section class="flex items-center gap-4 rounded-card bg-surface p-5 shadow-sm">
      <ChildAvatar avatar={child.avatar} color={child.color} size="lg" decorative />
      <div class="flex flex-1 flex-col gap-2">
        <p class="text-2xl font-bold">{t('trophies.level', { level: child.level })}</p>
        <span
          class="h-4 overflow-hidden rounded-full bg-line"
          role="progressbar"
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={levelProgress(child.xp_total, child.level)}
        >
          <span
            class="block h-full bg-brand"
            style:width="{levelProgress(child.xp_total, child.level)}%"
          ></span>
        </span>
        <p class="text-ink-muted">
          {t('trophies.xp', { xp: child.xp_total, next: xpForLevel(child.level + 1) })}
        </p>
      </div>
    </section>

    <section class="grid grid-cols-2 gap-4">
      <div class="rounded-card bg-surface p-5 text-center shadow-sm">
        <p class="text-4xl" aria-hidden="true">🔥</p>
        <p class="text-ink-muted">{t('trophies.streak')}</p>
        <p class="text-2xl font-bold" data-testid="streak">
          {t('trophies.days', { n: child.current_streak })}
        </p>
      </div>
      <div class="rounded-card bg-surface p-5 text-center shadow-sm">
        <p class="text-4xl" aria-hidden="true">🏅</p>
        <p class="text-ink-muted">{t('trophies.best_streak')}</p>
        <p class="text-2xl font-bold">{t('trophies.days', { n: child.best_streak })}</p>
      </div>
    </section>
  {/if}

  {#if badges}
    <ul class="grid grid-cols-2 gap-3 sm:grid-cols-4">
      {#each badges as badge (badge.code)}
        <li
          class="flex flex-col items-center gap-1 rounded-card bg-surface p-4 text-center shadow-sm {badge.earned_at
            ? ''
            : 'opacity-40 grayscale'}"
        >
          <span class="text-5xl" aria-hidden="true">{badge.earned_at ? badge.icon : '🔒'}</span>
          <span class="font-bold">{t(`badges.${badge.code}.name`)}</span>
          <span class="text-sm text-ink-muted">{t(`badges.${badge.code}.description`)}</span>
          <span class="sr-only"
            >{badge.earned_at ? t('trophies.earned') : t('trophies.locked')}</span
          >
        </li>
      {/each}
    </ul>
  {/if}
</main>
