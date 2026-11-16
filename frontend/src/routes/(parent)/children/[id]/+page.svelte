<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { archiveChild, setChildPin, updateChild } from '#lib/api/endpoints/children.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Child, ChildInput } from '#lib/api/types.ts';
  import ChildForm from '#lib/components/parent/ChildForm.svelte';
  import PointsPanel from '#lib/components/parent/PointsPanel.svelte';
  import { session } from '#lib/stores/session.svelte.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();

  const PIN_PATTERN = '[0-9]{4}';

  // svelte-ignore state_referenced_locally
  let child = $state<Child>(data.child);
  let pin = $state('');
  let pinErrors = $state<string[]>([]);
  let pinSaving = $state(false);
  let confirmArchive = $state(false);

  async function save(input: ChildInput) {
    child = await updateChild(child.id, input);
    toasts.success(t('children.saved'));
  }

  async function savePin(value: string | null) {
    pinSaving = true;
    pinErrors = [];
    try {
      child = await setChildPin(child.id, value);
      pin = '';
      toasts.success(value ? t('children.pin.saved') : t('children.pin.removed'));
    } catch (e) {
      const code = e instanceof ApiError ? e.code : 'unknown_error';
      if (code === 'validation_failed') pinErrors = [code];
      else toasts.error(errorMessage(code));
    } finally {
      pinSaving = false;
    }
  }

  async function archive() {
    try {
      await archiveChild(child.id);
      toasts.success(t('children.archived', { name: child.name }));
      await goto(resolve('/(parent)/children'));
    } catch (e) {
      toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
    }
  }
</script>

<svelte:head>
  <title>{child.name} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-2xl flex-col gap-6">
  <a href={resolve('/(parent)/children')} class="text-sm text-ink-muted hover:underline">
    ← {t('common.back')}
  </a>

  <section class="rounded-card bg-surface p-5 shadow-sm">
    <h1 class="mb-4 text-2xl font-semibold">{t('children.edit_title', { name: child.name })}</h1>
    {#key child.id}
      <ChildForm {child} onsubmit={save} />
    {/key}
  </section>

  {#if !child.archived}
    <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-sm">
      <h2 class="text-lg font-semibold">{t('children.pin.title')}</h2>
      <p class="text-sm text-ink-muted">{t('children.pin.description')}</p>
      <form
        class="flex flex-wrap items-end gap-2"
        onsubmit={(e) => {
          e.preventDefault();
          savePin(pin);
        }}
      >
        <Input
          id="child-pin"
          class="w-40"
          label={t('children.pin.label')}
          bind:value={pin}
          inputmode="numeric"
          pattern={PIN_PATTERN}
          maxlength={4}
          autocomplete="off"
          errors={pinErrors}
        />
        <Button type="submit" variant="secondary" loading={pinSaving}>
          {t('children.pin.set')}
        </Button>
        {#if child.has_pin}
          <Button variant="ghost" onclick={() => savePin(null)}>{t('children.pin.remove')}</Button>
        {/if}
      </form>
    </section>

    <PointsPanel
      {child}
      penaltiesAllowed={session.me?.family?.settings.allow_penalties ?? false}
      onchange={(updated) => (child = updated)}
    />

    <section class="flex justify-end">
      <Button variant="danger" onclick={() => (confirmArchive = true)}>
        {t('children.archive')}
      </Button>
    </section>
  {/if}
</div>

<Modal bind:open={confirmArchive} title={t('children.archive_confirm_title', { name: child.name })}>
  <p>{t('children.archive_confirm_text')}</p>
  {#snippet footer()}
    <Button variant="ghost" onclick={() => (confirmArchive = false)}>{t('common.cancel')}</Button>
    <Button variant="danger" onclick={archive}>{t('children.archive')}</Button>
  {/snippet}
</Modal>
