<script lang="ts">
  import { t } from '#lib/i18n/index.ts';
  import { connectivity } from '#lib/stores/connectivity.svelte.ts';
  import { formatDateTime } from '#lib/utils/dates.ts';

  let { class: className = '' }: { class?: string } = $props();
</script>

{#if connectivity.offline}
  <p class="rounded-lg bg-brand/10 p-3 text-sm {className}" role="status">
    📡 {connectivity.cachedAt
      ? t('offline.banner', { when: formatDateTime(new Date(connectivity.cachedAt).toISOString()) })
      : t('offline.banner_plain')}
  </p>
{/if}
