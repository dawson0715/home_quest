<script lang="ts">
  import { t } from '#lib/i18n/index.ts';

  import type { EarnedBadge } from '#lib/api/types.ts';

  interface Props {
    points: number;
    /** New level reached, if any. */
    level?: number | null;
    badges?: EarnedBadge[];
    onclose: () => void;
  }

  let { points, level = null, badges = [], onclose }: Props = $props();

  $effect(() => {
    const timer = setTimeout(onclose, level || badges.length ? 4000 : 2200);
    return () => clearTimeout(timer);
  });

  const confetti = Array.from({ length: 24 }, (_, i) => ({
    left: (i * 37) % 100,
    delay: (i % 6) * 0.12,
    emoji: ['⭐', '🎉', '✨', '🪙'][i % 4]
  }));
</script>

<button
  type="button"
  class="fixed inset-0 z-50 grid place-items-center bg-black/40 p-6"
  onclick={onclose}
  aria-live="assertive"
>
  <span
    class="pointer-events-none absolute inset-0 overflow-hidden motion-reduce:hidden"
    aria-hidden="true"
  >
    {#each confetti as c, i (i)}
      <span
        class="confetti absolute -top-8 text-3xl"
        style:left="{c.left}%"
        style:animation-delay="{c.delay}s">{c.emoji}</span
      >
    {/each}
  </span>
  <span
    class="flex flex-col items-center gap-3 rounded-card bg-surface px-10 py-8 text-center shadow-xl"
  >
    <span class="text-6xl" aria-hidden="true">{level ? '🏆' : '🌟'}</span>
    <span class="text-3xl font-bold">{t('child.today.celebrate', { points })}</span>
    {#if level}
      <span class="text-2xl font-semibold text-brand">{t('child.today.level_up', { level })}</span>
    {/if}
    {#each badges as badge (badge.code)}
      <span class="text-2xl font-semibold">
        {badge.icon}
        {t('child.today.new_badge', { name: t(`badges.${badge.code}.name`) })}
      </span>
    {/each}
  </span>
</button>

<style>
  .confetti {
    animation: fall 1.8s ease-in forwards;
  }
  @keyframes fall {
    to {
      transform: translateY(110vh) rotate(540deg);
    }
  }
</style>
