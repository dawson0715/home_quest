<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { ApiError } from '#lib/api/errors.ts';
  import type { ChildProfileCard } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import PinPad from '#lib/components/child/PinPad.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';

  let profiles = $state<ChildProfileCard[] | null>(null);
  let error = $state<string | null>(null);
  let selected = $state<ChildProfileCard | null>(null);
  let pinError = $state<string | null>(null);
  let busy = $state(false);
  let pad = $state<PinPad>();

  $effect(() => {
    deviceSession.switchProfile();
    deviceSession
      .profiles()
      .then((p) => (profiles = p))
      .catch(async (e) => {
        if (!deviceSession.isPaired) await goto(resolve('/(auth)/device'));
        else error = errorMessage(e instanceof ApiError ? e.code : 'unknown_error');
      });
  });

  async function enter(profile: ChildProfileCard, pin?: string) {
    busy = true;
    pinError = null;
    try {
      await deviceSession.selectChild(profile.id, pin);
      await goto(resolve('/(child)/today'));
    } catch (e) {
      const code = e instanceof ApiError ? e.code : 'unknown_error';
      pinError =
        code === 'pin_invalid'
          ? t('profiles.pin_wrong')
          : code === 'rate_limited'
            ? t('profiles.pin_locked')
            : errorMessage(code);
      pad?.clear();
    } finally {
      busy = false;
    }
  }

  function choose(profile: ChildProfileCard) {
    if (profile.has_pin) selected = profile;
    else enter(profile);
  }

  async function unlink() {
    deviceSession.forget();
    await goto(resolve('/(auth)/device'));
  }
</script>

<svelte:head>
  <title>{t('profiles.title')} · {t('app.name')}</title>
</svelte:head>

<main class="mx-auto flex min-h-dvh max-w-3xl flex-col items-center justify-center gap-8 p-6">
  {#if selected}
    <ChildAvatar avatar={selected.avatar} color={selected.color} size="xl" decorative />
    <h1 class="text-center text-3xl font-bold">
      {t('profiles.pin_title', { name: selected.name })}
    </h1>
    <PinPad
      bind:this={pad}
      disabled={busy}
      oncomplete={(pin) => selected && enter(selected, pin)}
    />
    {#if pinError}
      <p class="text-xl font-semibold text-danger" role="alert">{pinError}</p>
    {/if}
    <Button variant="ghost" size="xl" onclick={() => ((selected = null), (pinError = null))}>
      ← {t('common.back')}
    </Button>
  {:else}
    <h1 class="text-4xl font-bold">{t('profiles.title')}</h1>
    {#if error}
      <p class="text-danger" role="alert">{error}</p>
    {:else if profiles === null}
      <p class="text-ink-muted">{t('common.loading')}</p>
    {:else if profiles.length === 0}
      <p class="text-center text-ink-muted">{t('profiles.empty')}</p>
    {:else}
      <ul class="flex flex-wrap justify-center gap-6">
        {#each profiles as profile (profile.id)}
          <li>
            <button
              type="button"
              class="flex min-w-36 flex-col items-center gap-3 rounded-card bg-surface p-5 shadow-sm active:scale-95 motion-reduce:active:scale-100"
              disabled={busy}
              onclick={() => choose(profile)}
            >
              <ChildAvatar avatar={profile.avatar} color={profile.color} size="xl" decorative />
              <span class="text-2xl font-bold">{profile.name}</span>
            </button>
          </li>
        {/each}
      </ul>
      {#if pinError}
        <p class="text-danger" role="alert">{pinError}</p>
      {/if}
    {/if}
    <button type="button" class="mt-8 text-sm text-ink-muted underline" onclick={unlink}>
      {t('profiles.unlink')}
    </button>
  {/if}
</main>
