<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { deleteTask, listTasks, updateTask } from '#lib/api/endpoints/tasks.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { TaskInput } from '#lib/api/types.ts';
  import TaskForm from '#lib/components/parent/TaskForm.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { todayInRome } from '#lib/utils/dates.ts';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();
  let confirmDelete = $state(false);

  async function save(input: TaskInput) {
    await updateTask(data.task.id, input);
    toasts.success(t('tasks.saved'));
    await goto(resolve('/(parent)/tasks'));
  }

  async function remove() {
    confirmDelete = false;
    try {
      await deleteTask(data.task.id);
      const stillThere = (await listTasks()).some((x) => x.id === data.task.id);
      toasts.success(stillThere ? t('tasks.deactivated') : t('tasks.deleted'));
      await goto(resolve('/(parent)/tasks'));
    } catch (e) {
      toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
    }
  }
</script>

<svelte:head>
  <title>{t('tasks.edit_title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-2xl flex-col gap-4">
  <a href={resolve('/(parent)/tasks')} class="text-sm text-ink-muted hover:underline"
    >← {t('common.back')}</a
  >
  <section class="rounded-card bg-surface p-5 shadow-sm">
    <h1 class="mb-4 text-2xl font-semibold">{t('tasks.edit_title')}</h1>
    {#key data.task.id}
      <TaskForm
        task={data.task}
        childrenList={data.children}
        today={todayInRome()}
        onsubmit={save}
      />
    {/key}
  </section>
  <div class="flex justify-end">
    <Button variant="danger" onclick={() => (confirmDelete = true)}>{t('tasks.delete')}</Button>
  </div>
</div>

<Modal
  bind:open={confirmDelete}
  title={t('tasks.delete_confirm_title', { title: data.task.title })}
>
  <p>{t('tasks.delete_confirm_text')}</p>
  {#snippet footer()}
    <Button variant="ghost" onclick={() => (confirmDelete = false)}>{t('common.cancel')}</Button>
    <Button variant="danger" onclick={remove}>{t('tasks.delete')}</Button>
  {/snippet}
</Modal>
