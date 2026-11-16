<script lang="ts">
  import type { RecurrenceType } from '#lib/api/types.ts';
  import Input from '#lib/components/ui/Input.svelte';
  import { t } from '#lib/i18n/index.ts';
  import {
    EVERY_DAY,
    hasDay,
    toggleDay,
    WEEKDAYS_MON_FRI,
    WEEKEND
  } from '#lib/utils/recurrence.ts';

  interface Props {
    type: RecurrenceType;
    days: number | null;
    intervalDays: number | null;
    dueDate: string | null;
    startDate: string;
    endDate: string | null;
    errors?: Record<string, string[]>;
  }

  let {
    type = $bindable(),
    days = $bindable(),
    intervalDays = $bindable(),
    dueDate = $bindable(),
    startDate = $bindable(),
    endDate = $bindable(),
    errors = {}
  }: Props = $props();

  const types: RecurrenceType[] = ['daily', 'weekly', 'interval', 'none'];

  function setType(next: RecurrenceType) {
    type = next;
    if (next === 'weekly' && !days) days = WEEKDAYS_MON_FRI;
    if (next === 'interval' && !intervalDays) intervalDays = 2;
  }
</script>

<fieldset class="flex flex-col gap-3">
  <legend class="mb-2 text-sm font-medium">{t('tasks.form.recurrence')}</legend>
  <div class="flex flex-wrap gap-2" role="radiogroup">
    {#each types as option (option)}
      <button
        type="button"
        role="radio"
        aria-checked={type === option}
        class="min-h-11 rounded-full border px-4 {type === option
          ? 'border-brand bg-brand text-brand-contrast'
          : 'border-line bg-surface'}"
        onclick={() => setType(option)}
      >
        {t(`recurrence.type.${option}`)}
      </button>
    {/each}
  </div>

  {#if type === 'weekly'}
    <div class="flex flex-col gap-2">
      <div class="flex flex-wrap gap-1">
        {#each [0, 1, 2, 3, 4, 5, 6] as i (i)}
          <button
            type="button"
            aria-pressed={hasDay(days ?? 0, i)}
            aria-label={t(`recurrence.weekday_long.${i}`)}
            class="size-11 rounded-full border font-semibold {hasDay(days ?? 0, i)
              ? 'border-brand bg-brand text-brand-contrast'
              : 'border-line bg-surface'}"
            onclick={() => (days = toggleDay(days ?? 0, i))}
          >
            {t(`recurrence.weekday_short.${i}`).slice(0, 2)}
          </button>
        {/each}
      </div>
      <div class="flex gap-2 text-sm">
        <button type="button" class="underline" onclick={() => (days = WEEKDAYS_MON_FRI)}>
          {t('recurrence.quick.weekdays')}
        </button>
        <button type="button" class="underline" onclick={() => (days = WEEKEND)}>
          {t('recurrence.quick.weekend')}
        </button>
        <button type="button" class="underline" onclick={() => (days = EVERY_DAY)}>
          {t('recurrence.quick.all')}
        </button>
      </div>
      {#if errors.recurrence_days}
        <p class="text-sm text-danger" role="alert">{t('errors.validation_failed')}</p>
      {/if}
    </div>
  {:else if type === 'interval'}
    <Input
      id="task-interval"
      type="number"
      min={1}
      max={365}
      class="w-40"
      label={t('recurrence.every_n_days')}
      bind:value={intervalDays}
      errors={errors.interval_days}
    />
  {:else if type === 'none'}
    <Input
      id="task-due"
      type="date"
      class="w-48"
      label={t('recurrence.due_date')}
      bind:value={dueDate}
      errors={errors.due_date}
    />
  {/if}

  <div class="flex flex-wrap gap-3">
    <Input
      id="task-start"
      type="date"
      class="w-48"
      label={t('recurrence.start_date')}
      bind:value={startDate}
      errors={errors.start_date}
    />
    {#if type !== 'none'}
      <Input
        id="task-end"
        type="date"
        class="w-48"
        label={t('recurrence.end_date')}
        bind:value={endDate}
        errors={errors.end_date}
      />
    {/if}
  </div>
</fieldset>
