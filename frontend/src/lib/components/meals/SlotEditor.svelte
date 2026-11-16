<script lang="ts">
  import { listRecipes, putMealSlot } from '#lib/api/endpoints/meals.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { MealSlot, RecipeSummary } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';

  interface Props {
    weekStart: string;
    slot: MealSlot;
    onsaved: (slot: MealSlot) => void;
    oncancel: () => void;
  }

  let { weekStart, slot, onsaved, oncancel }: Props = $props();

  type Dish = { id?: number; recipe_id?: number; free_text?: string; title: string };

  // svelte-ignore state_referenced_locally
  const init = slot;
  let servings = $state(init.servings);
  let dishes = $state<Dish[]>(
    init.entries
      .filter((e) => e.status === 'planned')
      .map((e) => ({
        id: e.id,
        ...(e.recipe_id ? { recipe_id: e.recipe_id } : { free_text: e.free_text ?? '' }),
        title: e.title
      }))
  );
  let query = $state('');
  let freeText = $state('');
  let results = $state<RecipeSummary[]>([]);
  let saving = $state(false);
  let error = $state<string | null>(null);

  $effect(() => {
    const q = query;
    const timer = setTimeout(() => {
      listRecipes(q ? { q } : {})
        .then((list) => (results = list.slice(0, 8)))
        .catch(() => (results = []));
    }, 200);
    return () => clearTimeout(timer);
  });

  async function save() {
    saving = true;
    error = null;
    try {
      const saved = await putMealSlot(weekStart, slot.day_index, slot.meal_type, {
        servings: Number(servings),
        entries: dishes.map((d) => ({
          ...(d.id ? { id: d.id } : {}),
          ...(d.recipe_id ? { recipe_id: d.recipe_id } : { free_text: d.free_text })
        }))
      });
      onsaved(saved);
    } catch (e) {
      error = errorMessage(e instanceof ApiError ? e.code : 'unknown_error');
    } finally {
      saving = false;
    }
  }
</script>

<div class="flex flex-col gap-4">
  <Input
    id="slot-servings"
    type="number"
    min={1}
    max={30}
    class="w-40"
    label={t('menu.servings')}
    bind:value={servings}
  />

  <ul class="flex flex-col gap-2">
    {#each dishes as dish, index (index)}
      <li class="flex items-center justify-between gap-2 rounded-lg bg-surface-muted px-3 py-2">
        <span>{dish.recipe_id ? '📖 ' : ''}{dish.title}</span>
        <button
          type="button"
          class="size-11 rounded-lg text-danger hover:bg-surface"
          aria-label={t('menu.remove_dish', { title: dish.title })}
          onclick={() => (dishes = dishes.filter((_, i) => i !== index))}>✕</button
        >
      </li>
    {/each}
  </ul>

  <div class="flex flex-col gap-2">
    <input
      type="search"
      class="min-h-11 rounded-lg border border-line bg-surface px-3"
      placeholder={t('menu.add_recipe')}
      aria-label={t('menu.add_recipe')}
      bind:value={query}
    />
    <ul class="flex max-h-48 flex-col overflow-y-auto">
      {#each results as recipe (recipe.id)}
        <li>
          <button
            type="button"
            class="flex min-h-11 w-full items-center gap-2 rounded-lg px-2 text-left hover:bg-surface-muted"
            onclick={() => (dishes = [...dishes, { recipe_id: recipe.id, title: recipe.title }])}
          >
            + {recipe.title}
          </button>
        </li>
      {/each}
    </ul>
  </div>

  <form
    class="flex items-end gap-2"
    onsubmit={(e) => {
      e.preventDefault();
      if (!freeText.trim()) return;
      dishes = [...dishes, { free_text: freeText.trim(), title: freeText.trim() }];
      freeText = '';
    }}
  >
    <Input
      id="slot-free"
      class="flex-1"
      label={t('menu.add_free')}
      bind:value={freeText}
      maxlength={120}
    />
    <Button type="submit" variant="secondary">{t('menu.add')}</Button>
  </form>

  {#if error}
    <p class="text-sm text-danger" role="alert">{error}</p>
  {/if}
  <div class="flex justify-end gap-2">
    <Button variant="ghost" onclick={oncancel}>{t('common.cancel')}</Button>
    <Button onclick={save} loading={saving}>{t('common.save')}</Button>
  </div>
</div>
