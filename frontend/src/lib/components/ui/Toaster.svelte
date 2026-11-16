<script lang="ts">
  import { t } from '#lib/i18n/index.ts';
  import { toasts, type ToastKind } from '#lib/stores/toast.svelte.ts';

  const styles: Record<ToastKind, string> = {
    success: 'bg-success text-white',
    error: 'bg-danger text-white',
    info: 'bg-ink text-surface'
  };
</script>

<div
  class="pointer-events-none fixed inset-x-0 bottom-4 z-50 flex flex-col items-center gap-2 px-4"
  aria-live="polite"
>
  {#each toasts.items as toast (toast.id)}
    <div
      class="pointer-events-auto flex w-full max-w-md items-center justify-between gap-3 rounded-card px-4 py-3 shadow-lg {styles[
        toast.kind
      ]}"
      role={toast.kind === 'error' ? 'alert' : 'status'}
    >
      <span>{toast.message}</span>
      <button
        type="button"
        class="grid size-8 shrink-0 place-items-center rounded-full hover:bg-white/20"
        aria-label={t('toast.dismiss')}
        onclick={() => toasts.dismiss(toast.id)}
      >
        <span aria-hidden="true">✕</span>
      </button>
    </div>
  {/each}
</div>
