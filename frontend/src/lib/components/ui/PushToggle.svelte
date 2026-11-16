<script lang="ts">
  import Button from '#lib/components/ui/Button.svelte';
  import { t } from '#lib/i18n/index.ts';
  import { disablePush, enablePush, pushState, type PushState } from '#lib/utils/push.ts';

  interface Props {
    /** Child session token on kids' devices. */
    token?: string;
    label?: string;
    size?: 'md' | 'lg' | 'xl';
  }

  let { token, label, size = 'md' }: Props = $props();

  let status = $state<PushState | null>(null);
  let busy = $state(false);

  $effect(() => {
    pushState().then((s) => (status = s));
  });

  // Permission is requested only here, after an explicit tap (docs/05-frontend.md)
  async function toggle() {
    busy = true;
    try {
      status = status === 'on' ? await disablePush(token) : await enablePush(token);
    } catch {
      status = 'unavailable';
    } finally {
      busy = false;
    }
  }
</script>

{#if status === 'on' || status === 'off'}
  <div class="flex flex-wrap items-center gap-3">
    <Button
      variant={status === 'on' ? 'ghost' : 'secondary'}
      {size}
      loading={busy}
      onclick={toggle}
    >
      🔔 {status === 'on' ? t('notifications.disable') : (label ?? t('notifications.enable'))}
    </Button>
    {#if status === 'on'}
      <span class="text-sm text-ink-muted" role="status">{t('notifications.enabled')}</span>
    {/if}
  </div>
{:else if status}
  <p class="text-sm text-ink-muted" role="status">{t(`notifications.${status}`)}</p>
{/if}
