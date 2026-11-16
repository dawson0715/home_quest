<script lang="ts">
  import { resolve } from '$app/paths';
  import { listOccurrences } from '#lib/api/endpoints/occurrences.ts';
  import { listRedemptions } from '#lib/api/endpoints/rewards.ts';
  import OnboardingCard from '#lib/components/parent/OnboardingCard.svelte';
  import { t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';

  const me = $derived(session.me);
  let pending = $state<number | null>(null);
  let rewardRequests = $state(0);

  $effect(() => {
    listOccurrences({ status: 'submitted' })
      .then((list) => (pending = list.length))
      .catch(() => (pending = null));
    listRedemptions('requested')
      .then((list) => (rewardRequests = list.length))
      .catch(() => (rewardRequests = 0));
  });
</script>

<svelte:head>
  <title>{t('nav.dashboard')} · {t('app.name')}</title>
</svelte:head>

{#if me?.family}
  <section class="flex flex-col gap-4">
    <div class="flex flex-col gap-1">
      <h1 class="text-2xl font-semibold">
        {t('dashboard.hello', { name: me.user.name ?? me.user.username })}
      </h1>
      <p class="text-ink-muted">
        {t('dashboard.family', { name: me.family.name })} · {t(`dashboard.role.${me.role}`)}
      </p>
    </div>
    <OnboardingCard />
    {#if pending !== null}
      <a
        href={resolve('/(parent)/approvals')}
        class="rounded-card bg-surface p-5 shadow-sm hover:shadow-md {pending > 0
          ? 'border-l-4 border-brand'
          : ''}"
      >
        {pending > 0
          ? t('dashboard.approvals.some', { count: pending })
          : t('dashboard.approvals.none')}{rewardRequests > 0
          ? ` · ${t('dashboard.rewards.some', { count: rewardRequests })}`
          : ''}
      </a>
    {/if}
  </section>
{/if}
