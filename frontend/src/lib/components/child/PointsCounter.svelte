<script lang="ts">
  import { t } from '#lib/i18n/index.ts';

  interface Props {
    points: number;
  }

  let { points }: Props = $props();

  // svelte-ignore state_referenced_locally
  let shown = $state(points);

  $effect(() => {
    const target = points;
    const reduced = globalThis.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
    if (reduced || shown === target) {
      shown = target;
      return;
    }
    const from = shown;
    const start = performance.now();
    let frame = 0;
    const step = (now: number) => {
      const k = Math.min(1, (now - start) / 800);
      shown = Math.round(from + (target - from) * (1 - (1 - k) ** 3));
      if (k < 1) frame = requestAnimationFrame(step);
    };
    frame = requestAnimationFrame(step);
    return () => cancelAnimationFrame(frame);
  });
</script>

<span class="tabular-nums" data-testid="points" aria-live="polite">
  ⭐ {t('child.today.points', { points: shown })}
</span>
