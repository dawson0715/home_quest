<script lang="ts">
  import type { Snippet } from 'svelte';
  import type { HTMLButtonAttributes } from 'svelte/elements';
  import { t } from '#lib/i18n/index.ts';

  type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
  type Size = 'md' | 'lg' | 'xl';

  interface Props extends HTMLButtonAttributes {
    variant?: Variant;
    /** `xl` = child UI touch targets (≥ 64px). */
    size?: Size;
    loading?: boolean;
    children: Snippet;
  }

  let {
    variant = 'primary',
    size = 'md',
    loading = false,
    type = 'button',
    disabled,
    class: className = '',
    children,
    ...rest
  }: Props = $props();

  const variants: Record<Variant, string> = {
    primary: 'bg-brand text-brand-contrast hover:brightness-95',
    secondary: 'bg-surface-muted text-ink hover:brightness-95',
    ghost: 'bg-transparent text-ink hover:bg-surface-muted',
    danger: 'bg-danger text-white hover:brightness-95'
  };

  const sizes: Record<Size, string> = {
    md: 'min-h-11 px-4 text-base',
    lg: 'min-h-12 px-5 text-lg',
    xl: 'min-h-16 px-6 text-xl'
  };
</script>

<button
  {type}
  disabled={disabled || loading}
  aria-busy={loading}
  class="inline-flex items-center justify-center gap-2 rounded-card font-semibold transition focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand disabled:cursor-not-allowed disabled:opacity-50 {variants[
    variant
  ]} {sizes[size]} {className}"
  {...rest}
>
  {#if loading}
    <span
      class="size-4 animate-spin rounded-full border-2 border-current border-t-transparent motion-reduce:animate-none"
      aria-hidden="true"
    ></span>
    <span class="sr-only">{t('common.loading')}</span>
  {/if}
  {@render children()}
</button>
