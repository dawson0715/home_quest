<script lang="ts">
  import { putMealSlot, suggestMealPlan } from '#lib/api/endpoints/meals.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { MealSuggestion, MealSuggestions } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { formatShortDay } from '#lib/utils/dates.ts';

  interface Props {
    weekStart: string;
    /** Called after the accepted dishes are saved. */
    onapplied: (count: number) => void;
  }

  let { weekStart, onapplied }: Props = $props();

  const NOTE_MAX = 200;

  let note = $state('');
  let loading = $state(false);
  let applying = $state(false);
  let result = $state<MealSuggestions | null>(null);
  let selected = $state<Record<string, boolean>>({});

  const key = (s: MealSuggestion) => `${s.day_index}:${s.meal_type}`;
  const count = $derived(result ? result.suggestions.filter((s) => selected[key(s)]).length : 0);
  const days = $derived(
    result
      ? [...new Set(result.suggestions.map((s) => s.date))].map((date) => ({
          date,
          items: result!.suggestions.filter((s) => s.date === date)
        }))
      : []
  );

  function fail(e: unknown) {
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function generate(event?: SubmitEvent) {
    event?.preventDefault();
    loading = true;
    try {
      result = await suggestMealPlan(weekStart, note);
      selected = Object.fromEntries(result.suggestions.map((s) => [key(s), true]));
    } catch (e) {
      fail(e);
    } finally {
      loading = false;
    }
  }

  async function apply() {
    if (!result) return;
    applying = true;
    let saved = 0;
    try {
      for (const s of result.suggestions) {
        if (!selected[key(s)]) continue;
        const dish = s.recipe_id !== null ? { recipe_id: s.recipe_id } : { free_text: s.title };
        await putMealSlot(weekStart, s.day_index, s.meal_type, { entries: [dish] });
        saved++;
      }
      onapplied(saved);
    } catch (e) {
      fail(e);
      if (saved > 0) onapplied(saved);
    } finally {
      applying = false;
    }
  }

  function why(s: MealSuggestion): string | null {
    if (s.reason) return s.reason;
    return s.reason_code ? t(`menu.suggest.reason.${s.reason_code}`) : null;
  }
</script>

<div class="flex flex-col gap-4">
  <form class="flex flex-col gap-2" onsubmit={generate}>
    <p class="text-sm text-ink-muted">{t('menu.suggest.intro')}</p>
    <label class="flex flex-col gap-1">
      <span class="text-sm font-medium">{t('menu.suggest.note')}</span>
      <textarea
        class="min-h-16 rounded-lg border border-line p-2"
        bind:value={note}
        maxlength={NOTE_MAX}
        placeholder={t('menu.suggest.note_placeholder')}
        aria-describedby="suggest-note-hint"></textarea>
      <span id="suggest-note-hint" class="text-xs text-ink-muted"
        >{t('menu.suggest.note_hint')}</span
      >
    </label>
    <div>
      <Button type="submit" variant={result ? 'secondary' : 'primary'} {loading}>
        {result ? t('menu.suggest.regenerate') : t('menu.suggest.generate')}
      </Button>
    </div>
  </form>

  {#if loading}
    <p class="text-sm text-ink-muted" role="status">{t('menu.suggest.generating')}</p>
  {:else if result}
    {#if result.suggestions.length === 0}
      <p class="text-ink-muted">{t('menu.suggest.nothing')}</p>
    {:else}
      <p class="text-xs text-ink-muted" data-testid="suggest-source">
        {result.source === 'ai' ? t('menu.suggest.source_ai') : t('menu.suggest.source_local')}
        {#if result.fallback_reason && result.fallback_reason !== 'not_configured'}
          {t(`menu.suggest.fallback.${result.fallback_reason}`)}
        {/if}
      </p>
      <div class="flex max-h-[50vh] flex-col gap-3 overflow-y-auto">
        {#each days as day (day.date)}
          <section>
            <h3 class="text-sm font-semibold">{formatShortDay(day.date)}</h3>
            <ul class="flex flex-col gap-1">
              {#each day.items as s (key(s))}
                <li>
                  <label class="flex items-start gap-3 rounded-lg p-1 hover:bg-surface-muted">
                    <input
                      type="checkbox"
                      class="mt-1 size-5 accent-brand"
                      bind:checked={selected[key(s)]}
                      aria-label={t('menu.suggest.select', {
                        title: s.title,
                        meal: t(`meal_type.${s.meal_type}`),
                        day: formatShortDay(s.date)
                      })}
                    />
                    <span class="flex flex-col">
                      <span>
                        <span class="text-xs text-ink-muted">{t(`meal_type.${s.meal_type}`)}</span>
                        <span class="font-medium">{s.title}</span>
                        {#if s.recipe_id === null}
                          <span class="rounded bg-brand/10 px-1 text-xs"
                            >{t('menu.suggest.idea')}</span
                          >
                        {/if}
                      </span>
                      {#if why(s)}
                        <span class="text-xs text-ink-muted">{why(s)}</span>
                      {/if}
                    </span>
                  </label>
                </li>
              {/each}
            </ul>
          </section>
        {/each}
      </div>
      <div class="flex justify-end">
        <Button onclick={apply} loading={applying} disabled={count === 0}>
          {t('menu.suggest.apply', { n: count })}
        </Button>
      </div>
    {/if}
  {/if}
</div>
