<script lang="ts">
  import { getStats } from '#lib/api/endpoints/stats.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Stats } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { addDays, formatShortDay, todayInRome } from '#lib/utils/dates.ts';

  let days = $state<7 | 30>(7);
  let stats = $state<Stats | null>(null);

  $effect(() => {
    const to = todayInRome();
    getStats({ from: addDays(to, -(days - 1)), to })
      .then((s) => (stats = s))
      .catch((e) => toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error')));
  });
</script>

<svelte:head>
  <title>{t('stats.title')} · {t('app.name')}</title>
</svelte:head>

<div class="flex flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-2xl font-semibold">{t('stats.title')}</h1>
    <div class="flex gap-1" role="radiogroup">
      {#each [7, 30] as const as option (option)}
        <button
          type="button"
          role="radio"
          aria-checked={days === option}
          class="min-h-11 rounded-full px-4 {days === option
            ? 'bg-brand text-brand-contrast'
            : 'bg-surface'}"
          onclick={() => (days = option)}
          >{t(option === 7 ? 'stats.last_7' : 'stats.last_30')}</button
        >
      {/each}
    </div>
  </div>

  {#if stats === null}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {:else if stats.children.length === 0}
    <p class="rounded-card bg-surface p-6 text-center text-ink-muted">{t('stats.empty')}</p>
  {:else}
    <div class="grid gap-4 lg:grid-cols-2">
      {#each stats.children as s (s.child.id)}
        {@const max = Math.max(1, ...s.per_day.map((d) => d.approved + d.missed))}
        <section class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-sm">
          <h2 class="flex items-center gap-3 text-lg font-semibold">
            <ChildAvatar avatar={s.child.avatar} color={s.child.color} size="sm" decorative />
            {s.child.name}
          </h2>
          <dl class="grid grid-cols-3 gap-3 text-center">
            <div>
              <dt class="text-sm text-ink-muted">{t('stats.completed')}</dt>
              <dd class="text-2xl font-bold">{s.tasks.approved}</dd>
            </div>
            <div>
              <dt class="text-sm text-ink-muted">{t('stats.missed')}</dt>
              <dd class="text-2xl font-bold">{s.tasks.missed}</dd>
            </div>
            <div>
              <dt class="text-sm text-ink-muted">{t('stats.rate')}</dt>
              <dd class="text-2xl font-bold">
                {s.completion_rate === null
                  ? t('stats.no_data')
                  : `${Math.round(s.completion_rate * 100)}%`}
              </dd>
            </div>
            <div>
              <dt class="text-sm text-ink-muted">{t('stats.earned')}</dt>
              <dd class="text-xl font-semibold text-success">+{s.points_earned}</dd>
            </div>
            <div>
              <dt class="text-sm text-ink-muted">{t('stats.spent')}</dt>
              <dd class="text-xl font-semibold">{s.points_spent}</dd>
            </div>
            <div>
              <dt class="text-sm text-ink-muted">{t('stats.streak')}</dt>
              <dd class="text-xl font-semibold">🔥 {s.current_streak}</dd>
            </div>
          </dl>
          <ul
            class="flex h-32 items-end gap-0.5"
            aria-label={t('stats.chart_label', { name: s.child.name })}
          >
            {#each s.per_day as d (d.date)}
              <li
                class="flex h-full flex-1 flex-col justify-end"
                aria-label={t('stats.day_bar', {
                  date: formatShortDay(d.date),
                  approved: d.approved,
                  missed: d.missed
                })}
                title={t('stats.day_bar', {
                  date: formatShortDay(d.date),
                  approved: d.approved,
                  missed: d.missed
                })}
              >
                <span class="block bg-danger/60" style:height="{(d.missed / max) * 100}%"></span>
                <span
                  class="block rounded-t-sm bg-success"
                  style:height="{(d.approved / max) * 100}%"
                ></span>
              </li>
            {/each}
          </ul>
        </section>
      {/each}
    </div>
  {/if}
</div>
