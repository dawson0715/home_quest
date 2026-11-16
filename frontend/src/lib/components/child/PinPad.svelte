<script lang="ts">
  import { t } from '#lib/i18n/index.ts';

  interface Props {
    /** Called with the 4 digits once complete. */
    oncomplete: (pin: string) => void;
    disabled?: boolean;
  }

  let { oncomplete, disabled = false }: Props = $props();

  let digits = $state('');

  export function clear() {
    digits = '';
  }

  function press(d: string) {
    if (disabled || digits.length >= 4) return;
    digits += d;
    if (digits.length === 4) oncomplete(digits);
  }

  function backspace() {
    if (!disabled) digits = digits.slice(0, -1);
  }

  const keys = ['1', '2', '3', '4', '5', '6', '7', '8', '9'];
</script>

<div class="flex flex-col items-center gap-6">
  <div class="flex gap-4" role="status" aria-label={t('pinpad.entered', { count: digits.length })}>
    {#each [0, 1, 2, 3] as i (i)}
      <span
        class="size-5 rounded-full border-2 border-ink {i < digits.length ? 'bg-ink' : ''}"
        aria-hidden="true"
      ></span>
    {/each}
  </div>
  <div class="grid grid-cols-3 gap-3">
    {#each keys as key (key)}
      <button
        type="button"
        class="size-20 rounded-full bg-surface text-3xl font-semibold shadow-sm active:scale-95 disabled:opacity-50 motion-reduce:active:scale-100"
        aria-label={t('pinpad.digit', { digit: key })}
        {disabled}
        onclick={() => press(key)}>{key}</button
      >
    {/each}
    <span></span>
    <button
      type="button"
      class="size-20 rounded-full bg-surface text-3xl font-semibold shadow-sm active:scale-95 disabled:opacity-50 motion-reduce:active:scale-100"
      aria-label={t('pinpad.digit', { digit: '0' })}
      {disabled}
      onclick={() => press('0')}>0</button
    >
    <button
      type="button"
      class="size-20 rounded-full text-3xl active:scale-95 disabled:opacity-50 motion-reduce:active:scale-100"
      aria-label={t('pinpad.delete')}
      {disabled}
      onclick={backspace}>⌫</button
    >
  </div>
</div>
