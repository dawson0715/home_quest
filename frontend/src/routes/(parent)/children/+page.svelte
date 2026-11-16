<script lang="ts">
  import { resolve } from '$app/paths';
  import { createChild, listChildren } from '#lib/api/endpoints/children.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Child, ChildInput } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import ChildForm from '#lib/components/parent/ChildForm.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  let showArchived = $state(false);
  let children = $state<Child[]>([]);
  let loading = $state(true);
  let error = $state<string | null>(null);
  let adding = $state(false);

  async function load() {
    loading = true;
    error = null;
    try {
      children = await listChildren(showArchived);
    } catch (e) {
      error = errorMessage(e instanceof ApiError ? e.code : 'unknown_error');
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    void showArchived;
    load();
  });

  async function create(input: ChildInput) {
    const child = await createChild(input);
    adding = false;
    toasts.success(t('children.saved'));
    if (!showArchived) children = [...children, child];
  }
</script>

<svelte:head>
  <title>{t('children.title')} · {t('app.name')}</title>
</svelte:head>

<div class="flex flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-2xl font-semibold">{t('children.title')}</h1>
    <div class="flex gap-2">
      <Button variant="ghost" onclick={() => (showArchived = !showArchived)}>
        {showArchived ? t('children.active_toggle') : t('children.archived_toggle')}
      </Button>
      {#if !showArchived}
        <Button onclick={() => (adding = true)}>{t('children.add')}</Button>
      {/if}
    </div>
  </div>

  {#if loading}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {:else if error}
    <p class="text-danger" role="alert">{error}</p>
  {:else if children.length === 0}
    <p class="rounded-card bg-surface p-6 text-center text-ink-muted">{t('children.empty')}</p>
  {:else}
    <ul class="grid gap-3 sm:grid-cols-2">
      {#each children as child (child.id)}
        <li>
          <a
            href={resolve('/(parent)/children/[id]', { id: String(child.id) })}
            class="flex items-center gap-4 rounded-card bg-surface p-4 shadow-sm hover:shadow-md"
          >
            <ChildAvatar avatar={child.avatar} color={child.color} decorative />
            <span class="flex flex-col">
              <span class="text-lg font-semibold">{child.name}</span>
              <span class="text-sm text-ink-muted">
                {t('children.level', { level: child.level })} · {t('children.points', {
                  points: child.points_balance
                })}{child.has_pin ? ` · ${t('children.has_pin')}` : ''}
              </span>
            </span>
          </a>
        </li>
      {/each}
    </ul>
  {/if}
</div>

<Modal bind:open={adding} title={t('children.new_title')}>
  {#if adding}
    <ChildForm onsubmit={create} oncancel={() => (adding = false)} />
  {/if}
</Modal>
