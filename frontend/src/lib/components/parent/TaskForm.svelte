<script lang="ts">
  import { ApiError } from '#lib/api/errors.ts';
  import {
    TIMES_OF_DAY,
    type Child,
    type RecurrenceType,
    type Task,
    type TaskInput,
    type TimeOfDay
  } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import RecurrencePicker from '#lib/components/parent/RecurrencePicker.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';

  interface Draft {
    title: string;
    icon: string;
    points: number;
    recurrence_type: RecurrenceType;
    time_of_day: TimeOfDay;
    due_date: string;
    start_date: string;
    linked_meal_slot_id: number;
  }

  interface Props {
    task?: Task | null;
    /** Prefill for new tasks (e.g. from the template library). */
    draft?: Partial<Draft>;
    childrenList: Child[];
    today: string;
    onsubmit: (input: TaskInput) => Promise<void>;
  }

  let { task = null, draft = {}, childrenList, today, onsubmit }: Props = $props();

  // svelte-ignore state_referenced_locally
  const init = { task, draft, childrenList, today };
  let title = $state(init.task?.title ?? init.draft.title ?? '');
  let icon = $state(init.task?.icon ?? init.draft.icon ?? '⭐');
  let description = $state(init.task?.description ?? '');
  let points = $state<number>(init.task?.points ?? init.draft.points ?? 10);
  let xp = $state<number | null>(init.task ? init.task.xp : null);
  let requiresApproval = $state(init.task?.requires_approval ?? true);
  let recurrenceType = $state<RecurrenceType>(
    init.task?.recurrence_type ?? init.draft.recurrence_type ?? 'daily'
  );
  let recurrenceDays = $state<number | null>(init.task?.recurrence_days ?? null);
  let intervalDays = $state<number | null>(init.task?.interval_days ?? null);
  let dueDate = $state<string | null>(init.task?.due_date ?? init.draft.due_date ?? null);
  let startDate = $state(init.task?.start_date ?? init.draft.start_date ?? init.today);
  const linkedMealSlotId = init.task?.linked_meal_slot_id ?? init.draft.linked_meal_slot_id ?? null;
  let endDate = $state<string | null>(init.task?.end_date ?? null);
  let timeOfDay = $state<TimeOfDay>(init.task?.time_of_day ?? init.draft.time_of_day ?? 'any');
  let isActive = $state(init.task?.is_active ?? true);
  let assignees = $state<number[]>(
    init.task?.assignee_ids ?? (init.childrenList.length === 1 ? [init.childrenList[0].id] : [])
  );
  let checklist = $state<{ id?: number; label: string; icon: string | null }[]>(
    init.task?.checklist.map((i) => ({ ...i })) ?? []
  );
  let saving = $state(false);
  let fieldErrors = $state<Record<string, string[]>>({});
  let formError = $state<string | null>(null);

  const ICONS = ['⭐', '🌅', '🌙', '🪥', '🛏️', '🧸', '🍽️', '📚', '🎒', '🐾', '🧹', '🚿'];

  function move(index: number, delta: number) {
    const next = [...checklist];
    const [item] = next.splice(index, 1);
    next.splice(index + delta, 0, item);
    checklist = next;
  }

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    saving = true;
    fieldErrors = {};
    formError = null;
    try {
      await onsubmit({
        title,
        icon,
        description: description || null,
        points: Number(points),
        ...(xp !== null && xp !== undefined && String(xp) !== '' ? { xp: Number(xp) } : {}),
        requires_approval: requiresApproval,
        recurrence_type: recurrenceType,
        recurrence_days: recurrenceDays,
        interval_days: intervalDays === null ? null : Number(intervalDays),
        due_date: dueDate || null,
        start_date: startDate,
        end_date: endDate || null,
        time_of_day: timeOfDay,
        is_active: isActive,
        ...(linkedMealSlotId ? { linked_meal_slot_id: linkedMealSlotId } : {}),
        assignee_ids: assignees,
        checklist: checklist
          .filter((i) => i.label.trim() !== '')
          .map((i) => ({ ...(i.id ? { id: i.id } : {}), label: i.label, icon: i.icon }))
      });
    } catch (e) {
      if (e instanceof ApiError && e.code === 'validation_failed') fieldErrors = e.fieldErrors;
      else formError = errorMessage(e instanceof ApiError ? e.code : 'unknown_error');
    } finally {
      saving = false;
    }
  }
</script>

<form class="flex flex-col gap-6" onsubmit={submit}>
  <div class="flex flex-wrap items-start gap-3">
    <Input
      id="task-icon"
      class="w-24 [&_input]:text-center [&_input]:text-2xl"
      label={t('tasks.form.icon')}
      bind:value={icon}
      maxlength={8}
      required
      errors={fieldErrors.icon}
    />
    <Input
      id="task-title"
      class="min-w-60 flex-1"
      label={t('tasks.form.title')}
      bind:value={title}
      maxlength={120}
      required
      errors={fieldErrors.title}
    />
  </div>
  <div class="-mt-3 flex flex-wrap gap-1" aria-hidden="true">
    {#each ICONS as option (option)}
      <button
        type="button"
        tabindex="-1"
        class="size-10 rounded-lg text-xl hover:bg-surface-muted"
        onclick={() => (icon = option)}>{option}</button
      >
    {/each}
  </div>

  {#if linkedMealSlotId}
    <p class="-mt-3 text-sm text-ink-muted">🍽️ {t('tasks.help_cook_note')}</p>
  {/if}

  <Input
    id="task-description"
    label={t('tasks.form.description')}
    bind:value={description}
    maxlength={2000}
  />

  <div class="flex flex-wrap gap-3">
    <Input
      id="task-points"
      type="number"
      min={0}
      max={1000}
      class="w-32"
      label={t('tasks.form.points')}
      bind:value={points}
      required
      errors={fieldErrors.points}
    />
    <Input
      id="task-xp"
      type="number"
      min={0}
      max={1000}
      class="w-72"
      label={t('tasks.form.xp')}
      bind:value={xp}
      errors={fieldErrors.xp}
    />
  </div>

  <label class="flex min-h-11 items-center gap-3">
    <input type="checkbox" class="size-5 accent-brand" bind:checked={requiresApproval} />
    <span>{t('tasks.form.requires_approval')}</span>
  </label>

  <RecurrencePicker
    bind:type={recurrenceType}
    bind:days={recurrenceDays}
    bind:intervalDays
    bind:dueDate
    bind:startDate
    bind:endDate
    errors={fieldErrors}
  />

  <fieldset class="flex flex-col gap-2">
    <legend class="mb-2 text-sm font-medium">{t('tasks.form.time_of_day')}</legend>
    <div class="flex flex-wrap gap-2">
      {#each TIMES_OF_DAY as option (option)}
        <label
          class="flex min-h-11 cursor-pointer items-center rounded-full border px-4 has-checked:border-brand has-checked:bg-brand has-checked:text-brand-contrast has-focus-visible:outline-2 has-focus-visible:outline-brand"
        >
          <input type="radio" class="sr-only" value={option} bind:group={timeOfDay} />
          {t(`time_of_day.${option}`)}
        </label>
      {/each}
    </div>
  </fieldset>

  <fieldset class="flex flex-col gap-2">
    <legend class="mb-2 text-sm font-medium">{t('tasks.form.assignees')}</legend>
    <div class="flex flex-wrap gap-2">
      {#each childrenList as child (child.id)}
        <label
          class="flex min-h-11 cursor-pointer items-center gap-2 rounded-full border border-line bg-surface py-1 pr-4 pl-1 has-checked:border-brand has-checked:bg-brand/10 has-focus-visible:outline-2 has-focus-visible:outline-brand"
        >
          <input type="checkbox" class="sr-only" value={child.id} bind:group={assignees} />
          <ChildAvatar avatar={child.avatar} color={child.color} size="sm" decorative />
          {child.name}
        </label>
      {/each}
    </div>
    {#if fieldErrors.assignee_ids}
      <p class="text-sm text-danger" role="alert">{t('tasks.no_children')}</p>
    {/if}
  </fieldset>

  <fieldset class="flex flex-col gap-2">
    <legend class="mb-2 text-sm font-medium">{t('tasks.form.checklist')}</legend>
    <ol class="flex flex-col gap-2">
      {#each checklist as item, index (index)}
        <li class="flex items-center gap-2">
          <input
            class="min-h-11 w-14 rounded-lg border border-line bg-surface text-center text-xl"
            aria-label={t('tasks.form.icon')}
            bind:value={item.icon}
            maxlength={8}
          />
          <input
            class="min-h-11 flex-1 rounded-lg border border-line bg-surface px-3"
            aria-label={t('tasks.form.checklist_item', { n: index + 1 })}
            bind:value={item.label}
            maxlength={120}
          />
          <button
            type="button"
            class="size-11 rounded-lg hover:bg-surface-muted disabled:opacity-30"
            aria-label={t('tasks.form.checklist_up')}
            disabled={index === 0}
            onclick={() => move(index, -1)}>↑</button
          >
          <button
            type="button"
            class="size-11 rounded-lg hover:bg-surface-muted disabled:opacity-30"
            aria-label={t('tasks.form.checklist_down')}
            disabled={index === checklist.length - 1}
            onclick={() => move(index, 1)}>↓</button
          >
          <button
            type="button"
            class="size-11 rounded-lg text-danger hover:bg-surface-muted"
            aria-label={t('tasks.form.checklist_remove', { n: index + 1 })}
            onclick={() => (checklist = checklist.filter((_, i) => i !== index))}>✕</button
          >
        </li>
      {/each}
    </ol>
    <div>
      <Button
        variant="secondary"
        onclick={() => (checklist = [...checklist, { label: '', icon: null }])}
      >
        {t('tasks.form.checklist_add')}
      </Button>
    </div>
    {#if fieldErrors.checklist}
      <p class="text-sm text-danger" role="alert">{t('errors.validation_failed')}</p>
    {/if}
  </fieldset>

  {#if task}
    <label class="flex min-h-11 items-center gap-3">
      <input type="checkbox" class="size-5 accent-brand" bind:checked={isActive} />
      <span>{t('tasks.form.active')}</span>
    </label>
  {/if}

  {#if formError}
    <p class="text-sm text-danger" role="alert">{formError}</p>
  {/if}

  <div class="flex justify-end">
    <Button type="submit" size="lg" loading={saving}>{t('common.save')}</Button>
  </div>
</form>
