<script lang="ts">
  import type { HTMLInputAttributes } from 'svelte/elements';
  import { errorMessage } from '#lib/i18n/index.ts';

  interface Props extends Omit<HTMLInputAttributes, 'value'> {
    id: string;
    /** Already translated label. */
    label: string;
    value?: string | number | null;
    /** Error codes for this field (ApiError.fieldErrors[field]). */
    errors?: string[];
    /** Already translated hint shown under the field. */
    hint?: string;
  }

  let {
    id,
    label,
    value = $bindable(''),
    errors = [],
    hint,
    class: className = '',
    ...rest
  }: Props = $props();

  const describedBy = $derived(
    [errors.length ? `${id}-error` : null, hint ? `${id}-hint` : null].filter(Boolean).join(' ') ||
      undefined
  );
</script>

<div class="flex flex-col gap-1 {className}">
  <label for={id} class="text-sm font-medium text-ink">{label}</label>
  <input
    {id}
    bind:value
    aria-invalid={errors.length > 0}
    aria-describedby={describedBy}
    class="min-h-11 rounded-lg border border-line bg-surface px-3 text-base text-ink outline-none focus:border-brand focus:ring-2 focus:ring-brand/30 aria-invalid:border-danger"
    {...rest}
  />
  {#if hint}
    <p id="{id}-hint" class="text-sm text-ink-muted">{hint}</p>
  {/if}
  {#if errors.length}
    <p id="{id}-error" class="text-sm text-danger" role="alert">{errorMessage(errors[0])}</p>
  {/if}
</div>
