<script lang="ts">
  import {
    createReward,
    deleteReward,
    listRewards,
    updateReward
  } from '#lib/api/endpoints/rewards.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Reward, RewardInput } from '#lib/api/types.ts';
  import RewardForm from '#lib/components/parent/RewardForm.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  let rewards = $state<Reward[] | null>(null);
  let editing = $state<Reward | 'new' | null>(null);

  async function load() {
    try {
      rewards = await listRewards();
    } catch (e) {
      toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
    }
  }

  $effect(() => {
    load();
  });

  async function save(input: RewardInput) {
    if (editing === 'new') await createReward(input);
    else if (editing) await updateReward(editing.id, input);
    editing = null;
    toasts.success(t('rewards.saved'));
    await load();
  }

  async function remove(reward: Reward) {
    editing = null;
    try {
      await deleteReward(reward.id);
      toasts.success(t('rewards.deleted'));
      await load();
    } catch (e) {
      toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
    }
  }
</script>

<svelte:head>
  <title>{t('rewards.title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-3xl flex-col gap-4">
  <div class="flex items-center justify-between gap-2">
    <h1 class="text-2xl font-semibold">{t('rewards.title')}</h1>
    <Button onclick={() => (editing = 'new')}>{t('rewards.new')}</Button>
  </div>
  {#if rewards === null}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {:else if rewards.length === 0}
    <p class="rounded-card bg-surface p-6 text-center text-ink-muted">{t('rewards.empty')}</p>
  {:else}
    <ul class="flex flex-col gap-2">
      {#each rewards as reward (reward.id)}
        <li>
          <button
            type="button"
            class="flex w-full items-center gap-4 rounded-card bg-surface p-4 text-left shadow-sm hover:shadow-md {reward.is_active
              ? ''
              : 'opacity-60'}"
            onclick={() => (editing = reward)}
          >
            <span class="text-3xl" aria-hidden="true">{reward.icon}</span>
            <span class="flex flex-1 flex-col">
              <span class="font-semibold">{reward.title}</span>
              <span class="text-sm text-ink-muted">
                {t('tasks.points', { points: reward.cost })}{reward.limit_period
                  ? ` · ${t('rewards.limit', { n: reward.limit_per_period ?? 1, period: t(`rewards.period.${reward.limit_period}`) })}`
                  : ''}{reward.stock !== null
                  ? ` · ${t('rewards.stock', { n: reward.stock })}`
                  : ''}{reward.is_active ? '' : ` · ${t('rewards.inactive')}`}
              </span>
            </span>
          </button>
        </li>
      {/each}
    </ul>
  {/if}
</div>

<Modal
  open={editing !== null}
  title={editing === 'new' ? t('rewards.new_title') : t('rewards.edit_title')}
  onclose={() => (editing = null)}
>
  {#if editing !== null}
    {#key editing}
      <RewardForm
        reward={editing === 'new' ? null : editing}
        onsubmit={save}
        oncancel={() => (editing = null)}
      />
    {/key}
    {#if editing !== 'new'}
      {@const current = editing}
      <div class="mt-4 border-t border-line pt-4">
        <Button variant="danger" onclick={() => remove(current)}>{t('rewards.delete')}</Button>
      </div>
    {/if}
  {/if}
</Modal>
