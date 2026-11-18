<script lang="ts">
  import { resolve } from '$app/paths';
  import { listOccurrences } from '#lib/api/endpoints/occurrences.ts';
  import { listRedemptions } from '#lib/api/endpoints/rewards.ts';
  import type { Occurrence } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import OnboardingCard from '#lib/components/parent/OnboardingCard.svelte';
  import { t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';
  import { formatDateTime, formatDay } from '#lib/utils/dates.ts';

  const RECENT_LIMIT = 10;

  const me = $derived(session.me);
  let pending = $state<number | null>(null);
  let rewardRequests = $state(0);
  let recent = $state<Occurrence[] | null>(null);

  $effect(() => {
    listOccurrences({ status: 'submitted' })
      .then((list) => (pending = list.length))
      .catch(() => (pending = null));
    listRedemptions('requested')
      .then((list) => (rewardRequests = list.length))
      .catch(() => (rewardRequests = 0));
    listOccurrences({ status: 'approved,submitted', sort: 'recent', limit: RECENT_LIMIT })
      .then((list) => (recent = list))
      .catch(() => (recent = null));
  });

  const when = (o: Occurrence) =>
    o.submitted_at ? formatDateTime(o.submitted_at) : formatDay(o.due_date);
</script>

<svelte:head>
  <title>{t('nav.dashboard')} · {t('app.name')}</title>
</svelte:head>

{#if me?.family}
  <section class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-semibold">
        {t('dashboard.hello', { name: me.user.name ?? me.user.username })}
      </h1>
      <p class="text-ink-muted">
        {t('dashboard.family', { name: me.family.name })} · {t(`dashboard.role.${me.role}`)}
      </p>
    </div>
    <OnboardingCard />
    {#if pending !== null}
      <a
        href={resolve('/(parent)/approvals')}
        class="rounded-card bg-surface p-5 shadow-sm hover:shadow-md {pending > 0
          ? 'border-l-4 border-brand'
          : ''}"
      >
        {pending > 0
          ? t('dashboard.approvals.some', { count: pending })
          : t('dashboard.approvals.none')}{rewardRequests > 0
          ? ` · ${t('dashboard.rewards.some', { count: rewardRequests })}`
          : ''}
      </a>
    {/if}
    {#if recent !== null}
      <section class="flex flex-col gap-2" aria-labelledby="recent-title">
        <h2 id="recent-title" class="text-lg font-semibold">{t('dashboard.recent.title')}</h2>
        {#if recent.length === 0}
          <p class="rounded-card bg-surface p-5 text-ink-muted shadow-sm">
            {t('dashboard.recent.empty')}
          </p>
        {:else}
          <ul class="flex flex-col gap-2">
            {#each recent as o (o.id)}
              <li class="flex items-center gap-3 rounded-card bg-surface p-3 shadow-sm">
                {#if o.child}
                  <ChildAvatar avatar={o.child.avatar} color={o.child.color} size="sm" decorative />
                {/if}
                <span class="text-2xl" aria-hidden="true">{o.icon}</span>
                <span class="flex min-w-0 flex-1 flex-col">
                  <span class="truncate font-semibold">{o.title}</span>
                  <span class="text-sm text-ink-muted">
                    {o.child?.name ?? ''} · {when(o)}
                  </span>
                </span>
                {#if o.status === 'submitted'}
                  <a
                    href={resolve('/(parent)/approvals')}
                    class="shrink-0 rounded-full border border-brand px-2 py-0.5 text-sm text-brand"
                  >
                    {t('dashboard.recent.waiting')}
                  </a>
                {:else}
                  <span class="shrink-0 text-sm font-semibold">
                    {t('tasks.points', { points: o.points_awarded ?? o.points })}
                  </span>
                {/if}
              </li>
            {/each}
          </ul>
        {/if}
      </section>
    {/if}
  </section>
{/if}
