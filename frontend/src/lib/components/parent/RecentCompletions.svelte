<script lang="ts">
  import { resolve } from '$app/paths';
  import { listOccurrences } from '#lib/api/endpoints/occurrences.ts';
  import type { Occurrence } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import { t } from '#lib/i18n/index.ts';
  import { formatDateTime, formatDay } from '#lib/utils/dates.ts';

  /** Latest completed occurrences (approved or waiting for approval), newest first. */
  interface Props {
    title: string;
    limit?: number;
    /** Unique id for the heading, so the component can appear on any page. */
    headingId?: string;
  }

  let { title, limit = 10, headingId = 'recent-title' }: Props = $props();

  let recent = $state<Occurrence[] | null>(null);

  $effect(() => {
    listOccurrences({ status: 'approved,submitted', sort: 'recent', limit })
      .then((list) => (recent = list))
      .catch(() => (recent = null));
  });

  const when = (o: Occurrence) =>
    o.submitted_at ? formatDateTime(o.submitted_at) : formatDay(o.due_date);
</script>

{#if recent !== null}
  <section class="flex flex-col gap-2" aria-labelledby={headingId}>
    <h2 id={headingId} class="text-lg font-semibold">{title}</h2>
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
