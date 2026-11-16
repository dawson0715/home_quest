<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { ApiError } from '#lib/api/errors.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();

  // svelte-ignore state_referenced_locally
  let code = $state(data.code);
  let name = $state(t('device.name_default'));
  let loading = $state(false);
  let error = $state<string | null>(null);

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    loading = true;
    error = null;
    try {
      await deviceSession.pair(code, name);
      await goto(resolve('/(child)/profiles'));
    } catch (e) {
      error = errorMessage(e instanceof ApiError ? e.code : 'unknown_error');
    } finally {
      loading = false;
    }
  }
</script>

<svelte:head>
  <title>{t('device.title')} · {t('app.name')}</title>
</svelte:head>

<main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 p-6">
  <header class="flex flex-col gap-2">
    <p class="text-3xl font-bold text-brand">{t('app.name')}</p>
    <h1 class="text-2xl font-semibold">{t('device.title')}</h1>
    <p class="text-ink-muted">{t('device.intro')}</p>
  </header>

  <form class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-sm" onsubmit={submit}>
    <Input
      id="pair-code"
      label={t('device.code_label')}
      bind:value={code}
      class="[&_input]:font-mono [&_input]:text-2xl [&_input]:tracking-[0.3em] [&_input]:uppercase"
      autocomplete="one-time-code"
      autocapitalize="characters"
      maxlength={7}
      required
    />
    <Input
      id="device-name"
      label={t('device.name_label')}
      bind:value={name}
      maxlength={60}
      required
    />
    {#if error}
      <p class="text-sm text-danger" role="alert">{error}</p>
    {/if}
    <Button type="submit" size="xl" {loading}>{t('device.submit')}</Button>
  </form>

  <a href={resolve('/(auth)/login')} class="text-center text-ink-muted underline"
    >{t('device.parent_link')}</a
  >
</main>
