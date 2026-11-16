<script lang="ts">
  import '../app.css';
  import favicon from '#lib/assets/favicon.svg';
  import Toaster from '#lib/components/ui/Toaster.svelte';
  import { t } from '#lib/i18n/index.ts';
  import type { LayoutProps } from './$types';

  let { children }: LayoutProps = $props();
</script>

<svelte:head>
  <link rel="icon" href={favicon} />
  <title>{t('app.name')}</title>
</svelte:head>

<div class="min-h-dvh">
  {@render children()}
</div>

<Toaster />
