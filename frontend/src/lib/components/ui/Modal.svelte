<script lang="ts">
  import type { Snippet } from 'svelte';
  import { t } from '#lib/i18n/index.ts';

  interface Props {
    open?: boolean;
    /** Already translated title. */
    title: string;
    onclose?: () => void;
    children: Snippet;
    footer?: Snippet;
  }

  let { open = $bindable(false), title, onclose, children, footer }: Props = $props();

  let dialog = $state<HTMLDialogElement>();
  const titleId = `modal-title-${Math.random().toString(36).slice(2, 8)}`;

  $effect(() => {
    if (!dialog) return;
    if (open && !dialog.open) dialog.showModal();
    if (!open && dialog.open) dialog.close();
  });

  function handleClose() {
    open = false;
    onclose?.();
  }
</script>

<dialog
  bind:this={dialog}
  aria-labelledby={titleId}
  onclose={handleClose}
  onclick={(e) => e.target === dialog && dialog?.close()}
  class="m-auto w-[min(32rem,calc(100vw-2rem))] rounded-card bg-surface p-0 text-ink shadow-xl backdrop:bg-black/40"
>
  <div class="flex items-center justify-between gap-4 border-b border-line px-5 py-4">
    <h2 id={titleId} class="text-lg font-semibold">{title}</h2>
    <button
      type="button"
      class="grid size-11 place-items-center rounded-full hover:bg-surface-muted"
      aria-label={t('common.close')}
      onclick={() => dialog?.close()}
    >
      <span aria-hidden="true">✕</span>
    </button>
  </div>
  <!-- Content exists only while open: no hidden duplicate fields in the DOM -->
  {#if open}
    <div class="px-5 py-4">
      {@render children()}
    </div>
  {/if}
  {#if open && footer}
    <div class="flex justify-end gap-2 border-t border-line px-5 py-4">
      {@render footer()}
    </div>
  {/if}
</dialog>
