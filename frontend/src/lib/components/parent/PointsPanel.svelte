<script lang="ts">
  import { adjustPoints, listTransactions } from '#lib/api/endpoints/rewards.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Child, PointTransaction } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { formatDateTime } from '#lib/utils/dates.ts';

  interface Props {
    child: Child;
    penaltiesAllowed: boolean;
    onchange: (child: Child) => void;
  }

  let { child, penaltiesAllowed, onchange }: Props = $props();

  let items = $state<PointTransaction[]>([]);
  let page = $state(1);
  let pageCount = $state(1);
  let type = $state<'bonus' | 'penalty'>('bonus');
  let points = $state<number>(10);
  let note = $state('');
  let saving = $state(false);
  let fieldErrors = $state<Record<string, string[]>>({});
  const adjustTypes = $derived<('bonus' | 'penalty')[]>(
    penaltiesAllowed ? ['bonus', 'penalty'] : ['bonus']
  );

  async function load(next = 1) {
    try {
      const res = await listTransactions(child.id, next);
      items = next === 1 ? res.data : [...items, ...res.data];
      page = next;
      pageCount = res.meta.pagination?.pageCount ?? 1;
    } catch (e) {
      toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
    }
  }

  $effect(() => {
    void child.id;
    load(1);
  });

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    saving = true;
    fieldErrors = {};
    try {
      const res = await adjustPoints(child.id, { type, points: Number(points), note });
      note = '';
      toasts.success(t('points.given', { points: res.transaction.points, name: child.name }));
      onchange(res.child);
      await load(1);
    } catch (e) {
      const code = e instanceof ApiError ? e.code : 'unknown_error';
      if (code === 'validation_failed' && e instanceof ApiError) fieldErrors = e.fieldErrors;
      else toasts.error(errorMessage(code));
    } finally {
      saving = false;
    }
  }
</script>

<section class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-sm">
  <h2 class="text-lg font-semibold">{t('points.adjust_title')}</h2>
  <form class="flex flex-wrap items-end gap-3" onsubmit={submit}>
    <div class="flex gap-1" role="radiogroup">
      {#each adjustTypes as option (option)}
        <button
          type="button"
          role="radio"
          aria-checked={type === option}
          class="min-h-11 rounded-full border px-4 {type === option
            ? 'border-brand bg-brand text-brand-contrast'
            : 'border-line'}"
          onclick={() => (type = option)}>{t(`points.${option}`)}</button
        >
      {/each}
    </div>
    <Input
      id="adjust-points"
      type="number"
      min={1}
      max={1000}
      class="w-28"
      label={t('points.amount')}
      bind:value={points}
      required
      errors={fieldErrors.points}
    />
    <Input
      id="adjust-note"
      class="min-w-52 flex-1"
      label={t('points.reason')}
      bind:value={note}
      maxlength={255}
      required
      errors={fieldErrors.note}
    />
    <Button type="submit" loading={saving}>{t('points.give')}</Button>
  </form>
  {#if !penaltiesAllowed}
    <p class="text-sm text-ink-muted">{t('points.penalties_off')}</p>
  {/if}

  <h2 class="text-lg font-semibold">{t('points.history')}</h2>
  {#if items.length === 0}
    <p class="text-ink-muted">{t('points.empty')}</p>
  {:else}
    <ul class="flex flex-col divide-y divide-line" data-testid="ledger">
      {#each items as tx (tx.id)}
        <li class="flex items-center justify-between gap-3 py-2">
          <span class="flex flex-col">
            <span>{t(`points.type.${tx.type}`)}{tx.note ? ` · ${tx.note}` : ''}</span>
            <span class="text-sm text-ink-muted">{formatDateTime(tx.created_at)}</span>
          </span>
          <span class="font-semibold tabular-nums {tx.points < 0 ? 'text-danger' : 'text-success'}">
            {tx.points > 0 ? '+' : ''}{tx.points}
          </span>
        </li>
      {/each}
    </ul>
    {#if page < pageCount}
      <div>
        <Button variant="ghost" onclick={() => load(page + 1)}>{t('points.load_more')}</Button>
      </div>
    {/if}
  {/if}
</section>
