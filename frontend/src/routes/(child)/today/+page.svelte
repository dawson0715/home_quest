<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { getToday, setChecklistItem, submitOccurrence } from '#lib/api/endpoints/occurrences.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import { TIMES_OF_DAY, type EarnedBadge, type Occurrence, type Today } from '#lib/api/types.ts';
  import Celebration from '#lib/components/child/Celebration.svelte';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import PointsCounter from '#lib/components/child/PointsCounter.svelte';
  import TaskCard from '#lib/components/child/TaskCard.svelte';
  import PushToggle from '#lib/components/ui/PushToggle.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  let today = $state<Today | null>(null);
  let busyId = $state<number | null>(null);
  let celebration = $state<{
    points: number;
    level: number | null;
    badges: EarnedBadge[];
  } | null>(null);

  const token = $derived(deviceSession.childToken ?? '');
  const total = $derived(
    today ? TIMES_OF_DAY.reduce((n, g) => n + (today?.groups[g].length ?? 0), 0) : 0
  );
  const levelProgress = $derived(
    today
      ? Math.min(
          100,
          Math.round(
            ((today.child.xp_total - today.child.xp_level_start) /
              Math.max(1, today.child.xp_next_level - today.child.xp_level_start)) *
              100
          )
        )
      : 0
  );

  async function handleError(e: unknown) {
    if (e instanceof ApiError && e.status === 401) {
      deviceSession.switchProfile();
      await goto(resolve('/(child)/profiles'));
      return;
    }
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function load() {
    try {
      today = await getToday(token);
    } catch (e) {
      await handleError(e);
    }
  }

  $effect(() => {
    load();
  });

  function replace(updated: Occurrence) {
    if (!today) return;
    const group = today.groups[updated.time_of_day];
    const index = group.findIndex((o) => o.id === updated.id);
    if (index >= 0) group[index] = updated;
  }

  async function toggle(o: Occurrence, itemId: number, done: boolean) {
    const item = o.checklist.find((i) => i.id === itemId);
    if (!item) return;
    item.done = done; // optimistic
    try {
      replace(await setChecklistItem(token, o.id, itemId, done));
    } catch (e) {
      item.done = !done;
      await handleError(e);
    }
  }

  async function submit(o: Occurrence) {
    busyId = o.id;
    try {
      const result = await submitOccurrence(token, o.id);
      replace(result.occurrence);
      if (today && result.balance !== null) {
        today.child.points_balance = result.balance;
        today.child.xp_total += result.occurrence.points_awarded ?? 0;
      }
      if (result.occurrence.status === 'approved') {
        celebration = {
          points: result.points_awarded,
          level: result.level_up && today ? today.child.level + 1 : null,
          badges: result.new_badges
        };
      }
      if (result.level_up) await load();
    } catch (e) {
      await handleError(e);
      await load();
    } finally {
      busyId = null;
    }
  }

  async function switchProfile() {
    deviceSession.switchProfile();
    await goto(resolve('/(child)/profiles'));
  }
</script>

<svelte:head>
  <title>{deviceSession.child?.name ?? ''} · {t('app.name')}</title>
</svelte:head>

<main class="mx-auto flex min-h-dvh max-w-3xl flex-col gap-6 p-4 sm:p-6">
  {#if today}
    <header class="flex items-center gap-4">
      <ChildAvatar avatar={today.child.avatar} color={today.child.color} size="lg" decorative />
      <div class="flex flex-1 flex-col gap-1">
        <h1 class="text-3xl font-bold">{t('child.today.hello', { name: today.child.name })}</h1>
        <p class="text-xl"><PointsCounter points={today.child.points_balance} /></p>
        <div class="flex items-center gap-2">
          <span class="text-sm font-semibold">
            {t('child.today.level', { level: today.child.level })}
          </span>
          <span
            class="h-3 flex-1 overflow-hidden rounded-full bg-line"
            role="progressbar"
            aria-valuemin={0}
            aria-valuemax={100}
            aria-valuenow={levelProgress}
          >
            <span class="block h-full bg-brand" style:width="{levelProgress}%"></span>
          </span>
        </div>
      </div>
    </header>

    {#if today.savings_goal}
      {@const goal = today.savings_goal}
      <a
        href={resolve('/(child)/shop')}
        class="flex flex-col gap-2 rounded-card bg-surface p-4 shadow-sm"
      >
        <span class="font-semibold">
          <span aria-hidden="true">{goal.icon}</span>
          {t('child.today.goal', { title: goal.title })}
        </span>
        <span
          class="h-3 overflow-hidden rounded-full bg-line"
          role="progressbar"
          aria-valuemin={0}
          aria-valuemax={100}
          aria-valuenow={Math.round(goal.progress * 100)}
        >
          <span class="block h-full bg-success" style:width="{goal.progress * 100}%"></span>
        </span>
      </a>
    {/if}

    {#if total === 0}
      <p class="rounded-card bg-surface p-6 text-center text-xl">{t('child.today.empty')}</p>
    {/if}

    {#each TIMES_OF_DAY as group (group)}
      {#if today.groups[group].length > 0}
        <section class="flex flex-col gap-3">
          <h2 class="text-xl font-semibold text-ink-muted">{t(`time_of_day.${group}`)}</h2>
          {#each today.groups[group] as occurrence (occurrence.id)}
            <TaskCard
              {occurrence}
              busy={busyId === occurrence.id}
              ontoggle={(itemId, done) => toggle(occurrence, itemId, done)}
              onsubmit={() => submit(occurrence)}
            />
          {/each}
        </section>
      {/if}
    {/each}
  {:else}
    <p class="text-center text-ink-muted">{t('common.loading')}</p>
  {/if}

  <div class="mt-auto flex flex-col items-center gap-4 pt-6">
    <PushToggle
      token={deviceSession.childToken ?? undefined}
      label={t('notifications.kid_enable')}
      size="lg"
    />
    <Button variant="ghost" size="xl" onclick={switchProfile}>{t('child.switch_profile')}</Button>
  </div>
</main>

{#if celebration}
  <Celebration
    points={celebration.points}
    level={celebration.level}
    badges={celebration.badges}
    onclose={() => (celebration = null)}
  />
{/if}
