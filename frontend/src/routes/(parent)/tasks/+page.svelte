<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { listPlayers } from '#lib/api/endpoints/children.ts';
  import { listTasks, listTaskTemplates } from '#lib/api/endpoints/tasks.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Child, Task, TaskTemplate } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import RecentCompletions from '#lib/components/parent/RecentCompletions.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { describeRecurrence } from '#lib/utils/recurrence.ts';

  let tasks = $state<Task[]>([]);
  let children = $state<Child[]>([]);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let libraryOpen = $state(false);
  let templates = $state<TaskTemplate[]>([]);
  let libraryAge = $state<number | ''>('');

  const childById = $derived(new Map(children.map((c) => [c.id, c])));
  const active = $derived(tasks.filter((x) => x.is_active));
  const inactive = $derived(tasks.filter((x) => !x.is_active));

  $effect(() => {
    Promise.all([listTasks(), listPlayers()])
      .then(([tk, ch]) => {
        tasks = tk;
        children = ch;
      })
      .catch((e) => (error = errorMessage(e instanceof ApiError ? e.code : 'unknown_error')))
      .finally(() => (loading = false));
  });

  $effect(() => {
    if (!libraryOpen) return;
    listTaskTemplates(libraryAge === '' ? undefined : Number(libraryAge)).then(
      (list) => (templates = list)
    );
  });

  async function useTemplate(tpl: TaskTemplate) {
    libraryOpen = false;
    await goto(resolve(`/(parent)/tasks/new?template=${tpl.id}`));
  }
</script>

<svelte:head>
  <title>{t('tasks.title')} · {t('app.name')}</title>
</svelte:head>

{#snippet taskRow(task: Task)}
  <li>
    <a
      href={resolve('/(parent)/tasks/[id]', { id: String(task.id) })}
      class="flex items-center gap-4 rounded-card bg-surface p-4 shadow-sm hover:shadow-md"
    >
      <span class="text-3xl" aria-hidden="true">{task.icon}</span>
      <span class="flex flex-1 flex-col">
        <span class="font-semibold">{task.title}</span>
        <span class="text-sm text-ink-muted">
          {describeRecurrence(task)} · {t(`time_of_day.${task.time_of_day}`)} · {t('tasks.points', {
            points: task.points
          })}{task.requires_approval ? ` · ${t('tasks.requires_approval')}` : ''}{task.is_open
            ? ` · 🏃 ${t('tasks.open_badge')}`
            : ''}
        </span>
      </span>
      <span class="flex -space-x-2">
        {#each task.assignee_ids as id (id)}
          {@const child = childById.get(id)}
          {#if child}
            <ChildAvatar avatar={child.avatar} color={child.color} size="sm" />
          {/if}
        {/each}
      </span>
    </a>
  </li>
{/snippet}

<div class="flex flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-2xl font-semibold">{t('tasks.title')}</h1>
    <div class="flex gap-2">
      <Button variant="secondary" onclick={() => (libraryOpen = true)}>
        {t('tasks.from_library')}
      </Button>
      <Button onclick={() => goto(resolve('/(parent)/tasks/new'))}>{t('tasks.new')}</Button>
    </div>
  </div>

  {#if loading}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {:else if error}
    <p class="text-danger" role="alert">{error}</p>
  {:else if tasks.length === 0}
    <p class="rounded-card bg-surface p-6 text-center text-ink-muted">{t('tasks.empty')}</p>
  {:else}
    <ul class="flex flex-col gap-2">
      {#each active as task (task.id)}
        {@render taskRow(task)}
      {/each}
    </ul>
    {#if inactive.length}
      <h2 class="mt-4 text-lg font-semibold text-ink-muted">{t('tasks.inactive')}</h2>
      <ul class="flex flex-col gap-2 opacity-70">
        {#each inactive as task (task.id)}
          {@render taskRow(task)}
        {/each}
      </ul>
    {/if}
  {/if}

  <RecentCompletions title={t('tasks.completed')} limit={30} headingId="tasks-completed-title" />
</div>

<Modal bind:open={libraryOpen} title={t('tasks.library_title')}>
  <label class="mb-3 flex items-center gap-2">
    <span class="text-sm">{t('tasks.library_age')}</span>
    <select class="min-h-11 rounded-lg border border-line bg-surface px-2" bind:value={libraryAge}>
      <option value="">{t('tasks.library_all_ages')}</option>
      {#each Array.from({ length: 12 }, (_, i) => i + 3) as age (age)}
        <option value={age}>{age}</option>
      {/each}
    </select>
  </label>
  <ul class="flex max-h-[60vh] flex-col gap-1 overflow-y-auto">
    {#each templates as tpl (tpl.id)}
      <li class="flex items-center gap-3 rounded-lg p-2 hover:bg-surface-muted">
        <span class="text-2xl" aria-hidden="true">{tpl.icon}</span>
        <span class="flex-1">
          {t(`task_templates.${tpl.title_key}`)}
          <span class="block text-xs text-ink-muted">
            {t(`time_of_day.${tpl.time_of_day}`)} · {t('tasks.points', {
              points: tpl.default_points
            })}
          </span>
        </span>
        <Button variant="ghost" onclick={() => useTemplate(tpl)}>{t('tasks.use')}</Button>
      </li>
    {/each}
  </ul>
</Modal>
