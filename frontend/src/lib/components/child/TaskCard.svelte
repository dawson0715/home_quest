<script lang="ts">
  import type { Occurrence } from '#lib/api/types.ts';
  import { t } from '#lib/i18n/index.ts';

  interface Props {
    occurrence: Occurrence;
    busy?: boolean;
    ontoggle: (itemId: number, done: boolean) => void;
    onsubmit: () => void;
  }

  let { occurrence: o, busy = false, ontoggle, onsubmit }: Props = $props();

  const open = $derived(o.status === 'pending' || o.status === 'rejected');
  const doneCount = $derived(o.checklist.filter((i) => i.done).length);
  const complete = $derived(doneCount === o.checklist.length);
</script>

<article
  class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-sm {o.status === 'approved'
    ? 'opacity-80 ring-2 ring-success'
    : ''}"
  aria-labelledby="task-{o.id}-title"
>
  <header class="flex items-center gap-4">
    <span class="text-5xl" aria-hidden="true">{o.icon}</span>
    <h3 id="task-{o.id}-title" class="flex-1 text-2xl font-bold">{o.title}</h3>
    <span class="rounded-full bg-brand/15 px-3 py-1 text-lg font-bold text-brand">
      ⭐ {t('child.today.points_short', { points: o.points })}
    </span>
  </header>

  {#if o.status === 'rejected' && o.review_note}
    <p class="rounded-lg bg-danger/10 p-3 text-danger">💬 {o.review_note}</p>
  {/if}

  {#if o.checklist.length > 0}
    <ul
      class="flex flex-wrap gap-3"
      aria-label={t('child.today.checklist_progress', {
        done: doneCount,
        total: o.checklist.length
      })}
    >
      {#each o.checklist as item (item.id)}
        <li>
          <button
            type="button"
            class="flex min-h-16 items-center gap-2 rounded-full border-4 px-4 text-xl font-semibold transition active:scale-95 disabled:cursor-default motion-reduce:transition-none motion-reduce:active:scale-100 {item.done
              ? 'border-success bg-success text-white'
              : 'border-line bg-surface'}"
            aria-pressed={item.done}
            disabled={!open || busy}
            onclick={() => ontoggle(item.id, !item.done)}
          >
            <span aria-hidden="true">{item.done ? '✓' : (item.icon ?? '○')}</span>
            {item.label}
          </button>
        </li>
      {/each}
    </ul>
  {/if}

  <footer>
    {#if open}
      <button
        type="button"
        class="min-h-16 w-full rounded-card bg-brand text-2xl font-bold text-brand-contrast shadow active:scale-[0.98] disabled:opacity-40 motion-reduce:active:scale-100"
        disabled={!complete || busy}
        onclick={onsubmit}
      >
        {o.status === 'rejected' ? `🔁 ${t('child.today.done')}` : `✅ ${t('child.today.done')}`}
      </button>
    {:else if o.status === 'submitted'}
      <p class="text-center text-xl">⏳ {t('child.today.waiting')}</p>
    {:else if o.status === 'approved'}
      <p class="text-center text-xl font-bold text-success">
        🎉 {t('child.today.approved', { points: o.points_awarded ?? o.points })}
      </p>
    {:else}
      <p class="text-center text-xl text-ink-muted">{t('child.today.missed')}</p>
    {/if}
  </footer>
</article>
