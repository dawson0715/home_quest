<script lang="ts">
  import { resolve } from '$app/paths';
  import { listChildren } from '#lib/api/endpoints/children.ts';
  import { listDevices } from '#lib/api/endpoints/devices.ts';
  import { listRewards } from '#lib/api/endpoints/rewards.ts';
  import { listTasks } from '#lib/api/endpoints/tasks.ts';
  import { t } from '#lib/i18n/index.ts';

  const KEY = 'hq.onboarding.dismissed';
  const ROUTES = {
    child: '/(parent)/children',
    task: '/(parent)/tasks/new',
    reward: '/(parent)/rewards',
    device: '/(parent)/settings/devices'
  } as const;
  type Step = keyof typeof ROUTES;

  let dismissed = $state(readDismissed());
  let steps = $state<{ key: Step; done: boolean }[] | null>(null);

  function readDismissed(): boolean {
    try {
      return globalThis.localStorage?.getItem(KEY) === '1';
    } catch {
      return false;
    }
  }

  function dismiss() {
    dismissed = true;
    try {
      globalThis.localStorage?.setItem(KEY, '1');
    } catch {
      /* ignore */
    }
  }

  $effect(() => {
    if (dismissed) return;
    Promise.all([listChildren(), listTasks(), listRewards(), listDevices()])
      .then(([children, tasks, rewards, devices]) => {
        steps = [
          { key: 'child', done: children.length > 0 },
          { key: 'task', done: tasks.length > 0 },
          { key: 'reward', done: rewards.length > 0 },
          { key: 'device', done: devices.length > 0 }
        ];
      })
      .catch(() => (steps = null));
  });

  const doneCount = $derived(steps?.filter((s) => s.done).length ?? 0);
  const next = $derived(steps?.find((s) => !s.done)?.key);
</script>

{#if !dismissed && steps && doneCount < steps.length}
  <section
    class="flex flex-col gap-3 rounded-card border-2 border-brand/40 bg-surface p-5 shadow-sm"
    aria-labelledby="onboarding-title"
  >
    <div class="flex items-start justify-between gap-2">
      <div>
        <h2 id="onboarding-title" class="text-lg font-semibold">👋 {t('onboarding.title')}</h2>
        <p class="text-sm text-ink-muted">
          {t('onboarding.intro')}
          {t('onboarding.progress', { done: doneCount, total: steps.length })}
        </p>
      </div>
      <button
        type="button"
        class="min-h-11 rounded-lg px-3 text-sm text-ink-muted hover:bg-surface-muted"
        onclick={dismiss}
      >
        {t('onboarding.dismiss')}
      </button>
    </div>
    <ol class="flex flex-col gap-1">
      {#each steps as step, i (step.key)}
        <li>
          {#if step.done}
            <span class="flex min-h-11 items-center gap-3 px-2 text-ink-muted line-through">
              <span
                class="grid size-7 place-items-center rounded-full bg-success text-white"
                aria-hidden="true">✓</span
              >
              {t(`onboarding.steps.${step.key}`)}
              <span class="sr-only">{t('onboarding.done')}</span>
            </span>
          {:else}
            <a
              href={resolve(ROUTES[step.key])}
              class="flex min-h-11 items-center gap-3 rounded-lg px-2 hover:bg-surface-muted {step.key ===
              next
                ? 'font-semibold text-brand'
                : ''}"
            >
              <span
                class="grid size-7 place-items-center rounded-full border-2 border-current"
                aria-hidden="true">{i + 1}</span
              >
              {t(`onboarding.steps.${step.key}`)} →
            </a>
          {/if}
        </li>
      {/each}
    </ol>
  </section>
{/if}
