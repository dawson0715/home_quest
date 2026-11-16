<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { ApiError } from '#lib/api/errors.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';

  let token = $state('');
  let loading = $state(false);
  let error = $state<string | null>(null);

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    if (!token.trim()) return;
    loading = true;
    error = null;
    try {
      await session.login(token);
      await goto(resolve('/(parent)/dashboard'));
    } catch (e) {
      const code = e instanceof ApiError ? e.code : 'unknown_error';
      error = code === 'unauthorized' ? t('auth.login.invalid_token') : errorMessage(code);
    } finally {
      loading = false;
    }
  }
</script>

<svelte:head>
  <title>{t('auth.login.title')} · {t('app.name')}</title>
</svelte:head>

<main class="mx-auto flex min-h-dvh max-w-md flex-col justify-center gap-6 p-6">
  <header class="flex flex-col gap-2">
    <p class="text-3xl font-bold text-brand">{t('app.name')}</p>
    <h1 class="text-2xl font-semibold">{t('auth.login.title')}</h1>
    <p class="text-ink-muted">{t('auth.login.intro')}</p>
  </header>

  <form class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-sm" onsubmit={submit}>
    <Input
      id="token"
      label={t('auth.login.token_label')}
      hint={t('auth.login.token_hint')}
      bind:value={token}
      type="password"
      autocomplete="current-password"
      required
    />
    {#if error}
      <p class="text-sm text-danger" role="alert">{error}</p>
    {/if}
    <Button type="submit" size="lg" {loading}>{t('auth.login.submit')}</Button>
  </form>
  <a href={resolve('/(auth)/device')} class="text-center text-ink-muted underline"
    >{t('auth.login.child_link')}</a
  >
</main>
