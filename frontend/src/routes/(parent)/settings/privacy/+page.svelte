<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { deleteFamily, exportFamily } from '#lib/api/endpoints/family.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  const isAdmin = $derived(session.role === 'admin');
  const familyName = $derived(session.me?.family?.name ?? '');
  let confirmOpen = $state(false);
  let confirmName = $state('');
  let busy = $state(false);

  function fail(e: unknown) {
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function download() {
    busy = true;
    try {
      const data = await exportFamily();
      const url = URL.createObjectURL(
        new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
      );
      const a = document.createElement('a');
      a.href = url;
      a.download = 'homequest-export.json';
      a.click();
      URL.revokeObjectURL(url);
      toasts.success(t('privacy.exported'));
    } catch (e) {
      fail(e);
    } finally {
      busy = false;
    }
  }

  async function remove(event: SubmitEvent) {
    event.preventDefault();
    busy = true;
    try {
      await deleteFamily(confirmName);
      session.clear();
      await goto(resolve('/(auth)/login'));
    } catch (e) {
      fail(e);
    } finally {
      busy = false;
    }
  }
</script>

<svelte:head>
  <title>{t('privacy.title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-2xl flex-col gap-6">
  <h1 class="text-2xl font-semibold">{t('privacy.title')}</h1>
  <section class="flex flex-col gap-2 rounded-card bg-surface p-5 shadow-sm">
    <h2 class="text-lg font-semibold">{t('privacy.info_title')}</h2>
    <ul class="list-disc space-y-1 pl-5">
      {#each [1, 2, 3, 4] as n (n)}
        <li>{t(`privacy.info.${n}`)}</li>
      {/each}
    </ul>
  </section>
  {#if isAdmin}
    <section class="flex flex-wrap gap-3 rounded-card bg-surface p-5 shadow-sm">
      <Button variant="secondary" loading={busy} onclick={download}>⬇️ {t('privacy.export')}</Button
      >
      <Button variant="danger" onclick={() => (confirmOpen = true)}>{t('privacy.delete')}</Button>
    </section>
  {:else}
    <p class="text-ink-muted">{t('privacy.admin_only')}</p>
  {/if}
</div>

<Modal bind:open={confirmOpen} title={t('privacy.delete_title')}>
  <form class="flex flex-col gap-4" onsubmit={remove}>
    <p>{t('privacy.delete_text')}</p>
    <Input
      id="confirm-name"
      label={t('privacy.confirm_label', { name: familyName })}
      bind:value={confirmName}
      autocomplete="off"
      required
    />
    <div class="flex justify-end gap-2">
      <Button variant="ghost" onclick={() => (confirmOpen = false)}>{t('common.cancel')}</Button>
      <Button
        type="submit"
        variant="danger"
        loading={busy}
        disabled={confirmName.trim() !== familyName}>{t('privacy.delete')}</Button
      >
    </div>
  </form>
</Modal>
