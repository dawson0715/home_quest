<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { createTask } from '#lib/api/endpoints/tasks.ts';
  import type { TaskInput } from '#lib/api/types.ts';
  import TaskForm from '#lib/components/parent/TaskForm.svelte';
  import { t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { todayInRome } from '#lib/utils/dates.ts';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();

  const draft = $derived(
    data.template
      ? {
          title: t(`task_templates.${data.template.title_key}`),
          icon: data.template.icon,
          points: data.template.default_points,
          time_of_day: data.template.time_of_day,
          recurrence_type: data.template.recurrence_type
        }
      : data.mealSlot
        ? {
            title: data.mealSlot.title,
            icon: '👩‍🍳',
            points: 15,
            recurrence_type: 'none' as const,
            time_of_day: 'evening' as const,
            due_date: data.mealSlot.date,
            start_date: data.mealSlot.date,
            linked_meal_slot_id: data.mealSlot.id
          }
        : {}
  );

  async function save(input: TaskInput) {
    await createTask(input);
    toasts.success(t('tasks.saved'));
    await goto(resolve('/(parent)/tasks'));
  }
</script>

<svelte:head>
  <title>{t('tasks.new_title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-2xl flex-col gap-4">
  <a href={resolve('/(parent)/tasks')} class="text-sm text-ink-muted hover:underline"
    >← {t('common.back')}</a
  >
  <section class="rounded-card bg-surface p-5 shadow-sm">
    <h1 class="mb-4 text-2xl font-semibold">{t('tasks.new_title')}</h1>
    {#if data.children.length === 0}
      <p class="text-ink-muted">{t('tasks.no_children')}</p>
    {:else}
      <TaskForm {draft} childrenList={data.children} today={todayInRome()} onsubmit={save} />
    {/if}
  </section>
</div>
