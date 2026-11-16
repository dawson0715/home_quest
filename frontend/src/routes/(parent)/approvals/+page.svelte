<script lang="ts">
  import {
    approveOccurrence,
    bulkApprove,
    listOccurrences,
    rejectOccurrence
  } from '#lib/api/endpoints/occurrences.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import {
    approveRedemption,
    fulfillRedemption,
    listRedemptions,
    rejectRedemption
  } from '#lib/api/endpoints/rewards.ts';
  import type { Occurrence, Redemption } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { formatDay } from '#lib/utils/dates.ts';

  let items = $state<Occurrence[]>([]);
  let redemptions = $state<Redemption[]>([]);
  let rejectingRedemption = $state<Redemption | null>(null);
  let loading = $state(true);
  let selected = $state<number[]>([]);
  let rejecting = $state<Occurrence | null>(null);
  let rejectNote = $state('');
  let busy = $state(false);

  function fail(e: unknown) {
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function load() {
    try {
      [items, redemptions] = await Promise.all([
        listOccurrences({ status: 'submitted' }),
        listRedemptions('requested,approved')
      ]);
      selected = selected.filter((id) => items.some((o) => o.id === id));
    } catch (e) {
      fail(e);
    } finally {
      loading = false;
    }
  }

  $effect(() => {
    load();
  });

  async function approve(o: Occurrence) {
    busy = true;
    try {
      const result = await approveOccurrence(o.id);
      toasts.success(
        t('approvals.approved', { points: result.points_awarded, name: o.child?.name ?? '' })
      );
      items = items.filter((x) => x.id !== o.id);
    } catch (e) {
      fail(e);
      await load();
    } finally {
      busy = false;
    }
  }

  async function approveSelected() {
    busy = true;
    try {
      const result = await bulkApprove(selected);
      toasts.success(t('approvals.bulk_approved', { count: result.approved.length }));
      selected = [];
      await load();
    } catch (e) {
      fail(e);
    } finally {
      busy = false;
    }
  }

  async function reviewRedemption(r: Redemption, action: 'approve' | 'reject' | 'fulfill') {
    busy = true;
    try {
      if (action === 'approve') {
        await approveRedemption(r.id);
        toasts.success(t('rewards.approved', { cost: r.cost, name: r.child.name }));
      } else if (action === 'reject') {
        await rejectRedemption(r.id, rejectNote || undefined);
        rejectNote = '';
        toasts.info(t('rewards.rejected'));
      } else {
        await fulfillRedemption(r.id);
        toasts.success(t('rewards.fulfilled'));
      }
    } catch (e) {
      fail(e);
    } finally {
      busy = false;
      rejectingRedemption = null;
      await load();
    }
  }

  async function confirmReject() {
    const o = rejecting;
    if (!o) return;
    rejecting = null;
    try {
      await rejectOccurrence(o.id, rejectNote || undefined);
      toasts.info(t('approvals.rejected'));
      items = items.filter((x) => x.id !== o.id);
    } catch (e) {
      fail(e);
    }
    rejectNote = '';
  }
</script>

<svelte:head>
  <title>{t('approvals.title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-3xl flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-2xl font-semibold">{t('approvals.title')}</h1>
    {#if selected.length > 0}
      <Button onclick={approveSelected} loading={busy}>
        {t('approvals.approve_all', { count: selected.length })}
      </Button>
    {/if}
  </div>

  {#if redemptions.length > 0}
    <section class="flex flex-col gap-2">
      <h2 class="text-lg font-semibold">{t('rewards.requests_title')}</h2>
      <ul class="flex flex-col gap-2">
        {#each redemptions as r (r.id)}
          <li class="flex flex-wrap items-center gap-3 rounded-card bg-surface p-4 shadow-sm">
            <ChildAvatar avatar={r.child.avatar} color={r.child.color} size="sm" decorative />
            <span class="text-2xl" aria-hidden="true">{r.icon}</span>
            <span class="flex min-w-40 flex-1 flex-col">
              <span class="font-semibold">{r.title}</span>
              <span class="text-sm text-ink-muted">
                {r.child.name} · {t('tasks.points', { points: r.cost })}{r.status === 'approved'
                  ? ` · ${t('rewards.to_deliver')}`
                  : ''}
              </span>
            </span>
            <span class="flex gap-2">
              {#if r.status === 'requested'}
                <Button variant="ghost" disabled={busy} onclick={() => (rejectingRedemption = r)}>
                  {t('approvals.reject')}
                </Button>
                <Button disabled={busy} onclick={() => reviewRedemption(r, 'approve')}>
                  {t('approvals.approve')}
                </Button>
              {:else}
                <Button
                  variant="secondary"
                  disabled={busy}
                  onclick={() => reviewRedemption(r, 'fulfill')}
                >
                  {t('rewards.fulfill')}
                </Button>
              {/if}
            </span>
          </li>
        {/each}
      </ul>
    </section>
    <h2 class="text-lg font-semibold">{t('approvals.tasks_section')}</h2>
  {/if}

  {#if loading}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {:else if items.length === 0}
    <p class="rounded-card bg-surface p-6 text-center text-ink-muted">{t('approvals.empty')}</p>
  {:else}
    <ul class="flex flex-col gap-2">
      {#each items as o (o.id)}
        <li class="flex flex-wrap items-center gap-3 rounded-card bg-surface p-4 shadow-sm">
          <input
            type="checkbox"
            class="size-5 accent-brand"
            value={o.id}
            bind:group={selected}
            aria-label={t('approvals.select', { title: o.title, name: o.child?.name ?? '' })}
          />
          {#if o.child}
            <ChildAvatar avatar={o.child.avatar} color={o.child.color} size="sm" decorative />
          {/if}
          <span class="text-2xl" aria-hidden="true">{o.icon}</span>
          <span class="flex min-w-40 flex-1 flex-col">
            <span class="font-semibold">{o.title}</span>
            <span class="text-sm text-ink-muted">
              {o.child?.name} · {t('approvals.submitted_on', { date: formatDay(o.due_date) })} · {t(
                'tasks.points',
                { points: o.points }
              )}
            </span>
          </span>
          <span class="flex gap-2">
            <Button variant="ghost" disabled={busy} onclick={() => (rejecting = o)}>
              {t('approvals.reject')}
            </Button>
            <Button disabled={busy} onclick={() => approve(o)}>{t('approvals.approve')}</Button>
          </span>
        </li>
      {/each}
    </ul>
  {/if}
</div>

<Modal
  open={rejecting !== null}
  title={t('approvals.reject_title', { title: rejecting?.title ?? '' })}
  onclose={() => (rejecting = null)}
>
  <Input
    id="reject-note"
    label={t('approvals.reject_note')}
    bind:value={rejectNote}
    maxlength={255}
  />
  {#snippet footer()}
    <Button variant="ghost" onclick={() => (rejecting = null)}>{t('common.cancel')}</Button>
    <Button variant="danger" onclick={confirmReject}>{t('approvals.reject')}</Button>
  {/snippet}
</Modal>

<Modal
  open={rejectingRedemption !== null}
  title={t('approvals.reject_title', { title: rejectingRedemption?.title ?? '' })}
  onclose={() => (rejectingRedemption = null)}
>
  <Input
    id="reject-redemption-note"
    label={t('approvals.reject_note')}
    bind:value={rejectNote}
    maxlength={255}
  />
  {#snippet footer()}
    <Button variant="ghost" onclick={() => (rejectingRedemption = null)}
      >{t('common.cancel')}</Button
    >
    <Button
      variant="danger"
      onclick={() => rejectingRedemption && reviewRedemption(rejectingRedemption, 'reject')}
      >{t('approvals.reject')}</Button
    >
  {/snippet}
</Modal>
