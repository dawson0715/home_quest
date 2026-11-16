<script lang="ts">
  import { resolve } from '$app/paths';
  import { renderSVG } from 'uqr';
  import { createPairingCode, listDevices, revokeDevice } from '#lib/api/endpoints/devices.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Device, PairingCode } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { formatDateTime, formatTime } from '#lib/utils/dates.ts';

  let devices = $state<Device[]>([]);
  let loading = $state(true);
  let pairing = $state<PairingCode | null>(null);
  let generating = $state(false);
  let revoking = $state<Device | null>(null);

  const pairUrl = $derived(
    pairing
      ? new URL(resolve('/(auth)/device') + `?code=${pairing.code}`, window.location.origin).href
      : ''
  );
  // uqr output is generated locally from our own code: safe to inline
  const qrSvg = $derived(pairUrl ? renderSVG(pairUrl, { border: 2 }) : '');

  function fail(e: unknown) {
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function load() {
    loading = true;
    try {
      devices = await listDevices();
    } catch (e) {
      fail(e);
    } finally {
      loading = false;
    }
  }

  async function generate() {
    generating = true;
    try {
      pairing = await createPairingCode();
    } catch (e) {
      fail(e);
    } finally {
      generating = false;
    }
  }

  async function revoke() {
    const device = revoking;
    if (!device) return;
    revoking = null;
    try {
      await revokeDevice(device.id);
      devices = devices.filter((d) => d.id !== device.id);
      toasts.success(t('devices.revoked', { name: device.name }));
    } catch (e) {
      fail(e);
    }
  }

  $effect(() => {
    load();
  });
</script>

<svelte:head>
  <title>{t('devices.title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-2xl flex-col gap-6">
  <header class="flex flex-col gap-2">
    <h1 class="text-2xl font-semibold">{t('devices.title')}</h1>
    <p class="text-ink-muted">{t('devices.intro')}</p>
  </header>

  <section class="flex flex-col items-start gap-4 rounded-card bg-surface p-5 shadow-sm">
    <Button onclick={generate} loading={generating}>{t('devices.generate')}</Button>
    {#if pairing}
      <div class="flex w-full flex-col items-center gap-3 sm:flex-row sm:items-start">
        <div
          class="w-48 shrink-0 rounded-lg bg-white p-2"
          role="img"
          aria-label={t('devices.qr_label', { code: pairing.code })}
        >
          <!-- eslint-disable-next-line svelte/no-at-html-tags -->
          {@html qrSvg}
        </div>
        <div class="flex flex-col gap-2">
          <h2 class="text-sm font-medium text-ink-muted">{t('devices.code_title')}</h2>
          <p class="font-mono text-4xl font-bold tracking-[0.3em]" data-testid="pairing-code">
            {pairing.code}
          </p>
          <p class="text-sm text-ink-muted">
            {t('devices.code_expires', { time: formatTime(pairing.expires_at) })}
          </p>
          <p class="text-sm">{t('devices.code_instructions')}</p>
        </div>
      </div>
    {/if}
  </section>

  <section class="flex flex-col gap-3">
    <h2 class="text-lg font-semibold">{t('devices.list_title')}</h2>
    {#if loading}
      <p class="text-ink-muted">{t('common.loading')}</p>
    {:else if devices.length === 0}
      <p class="rounded-card bg-surface p-5 text-ink-muted">{t('devices.empty')}</p>
    {:else}
      <ul class="flex flex-col gap-2">
        {#each devices as device (device.id)}
          <li class="flex items-center justify-between gap-3 rounded-card bg-surface p-4 shadow-sm">
            <span class="flex flex-col">
              <span class="font-semibold">{device.name}</span>
              <span class="text-sm text-ink-muted">
                {device.last_seen_at
                  ? t('devices.last_seen', { when: formatDateTime(device.last_seen_at) })
                  : t('devices.never_seen')}
              </span>
            </span>
            <Button variant="ghost" onclick={() => (revoking = device)}
              >{t('devices.revoke')}</Button
            >
          </li>
        {/each}
      </ul>
    {/if}
  </section>
</div>

<Modal
  open={revoking !== null}
  title={t('devices.revoke_confirm_title', { name: revoking?.name ?? '' })}
  onclose={() => (revoking = null)}
>
  <p>{t('devices.revoke_confirm_text')}</p>
  {#snippet footer()}
    <Button variant="ghost" onclick={() => (revoking = null)}>{t('common.cancel')}</Button>
    <Button variant="danger" onclick={revoke}>{t('devices.revoke')}</Button>
  {/snippet}
</Modal>
