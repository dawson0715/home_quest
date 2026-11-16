<script lang="ts">
  import { searchIngredients } from '#lib/api/endpoints/meals.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import {
    RECIPE_TAGS,
    UNITS,
    type Ingredient,
    type Recipe,
    type RecipeInput,
    type RecipeTag,
    type Unit
  } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';

  interface Row {
    ingredient_id?: number;
    name: string;
    quantity: number | null;
    unit: Unit;
    note: string;
  }

  interface Props {
    recipe?: Recipe | null;
    onsubmit: (input: RecipeInput) => Promise<void>;
  }

  let { recipe = null, onsubmit }: Props = $props();

  // svelte-ignore state_referenced_locally
  const init = recipe;
  let title = $state(init?.title ?? '');
  let servings = $state<number>(init?.servings ?? 4);
  let prepMinutes = $state<number | null>(init?.prep_minutes ?? null);
  let instructions = $state(init?.instructions ?? '');
  let tags = $state<RecipeTag[]>(init?.tags ?? []);
  let favorite = $state(init?.is_favorite ?? false);
  let rows = $state<Row[]>(
    init?.ingredients.map((i) => ({
      ingredient_id: i.ingredient_id,
      name: i.name,
      quantity: i.quantity,
      unit: i.unit,
      note: i.note ?? ''
    })) ?? []
  );
  let suggestions = $state<Ingredient[]>([]);
  let saving = $state(false);
  let fieldErrors = $state<Record<string, string[]>>({});
  let formError = $state<string | null>(null);
  let searchTimer: ReturnType<typeof setTimeout> | undefined;

  function onNameInput(row: Row) {
    row.ingredient_id = undefined;
    clearTimeout(searchTimer);
    const q = row.name.trim();
    if (q.length < 2) return;
    searchTimer = setTimeout(async () => {
      suggestions = await searchIngredients(q).catch(() => []);
      const exact = suggestions.find((s) => s.name.toLowerCase() === q.toLowerCase());
      if (exact) {
        row.ingredient_id = exact.id;
        if (row.quantity === null) row.unit = exact.default_unit;
      }
    }, 200);
  }

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    saving = true;
    fieldErrors = {};
    formError = null;
    try {
      await onsubmit({
        title,
        servings: Number(servings),
        prep_minutes:
          prepMinutes === null || String(prepMinutes) === '' ? null : Number(prepMinutes),
        instructions: instructions || null,
        tags,
        is_favorite: favorite,
        ingredients: rows
          .filter((r) => r.name.trim() !== '')
          .map((r) => ({
            ...(r.ingredient_id ? { ingredient_id: r.ingredient_id } : { name: r.name.trim() }),
            quantity:
              r.quantity === null || String(r.quantity) === '' || r.unit === 'qb'
                ? null
                : Number(r.quantity),
            unit: r.unit,
            note: r.note || null
          }))
      });
    } catch (e) {
      if (e instanceof ApiError && e.code === 'validation_failed') fieldErrors = e.fieldErrors;
      else formError = errorMessage(e instanceof ApiError ? e.code : 'unknown_error');
    } finally {
      saving = false;
    }
  }
</script>

<datalist id="ingredient-suggestions">
  {#each suggestions as s (s.id)}
    <option value={s.name}></option>
  {/each}
</datalist>

<form class="flex flex-col gap-5" onsubmit={submit}>
  <Input
    id="recipe-title"
    label={t('recipes.form.title')}
    bind:value={title}
    maxlength={120}
    required
    errors={fieldErrors.title}
  />
  <div class="flex flex-wrap gap-3">
    <Input
      id="recipe-servings"
      type="number"
      min={1}
      max={50}
      class="w-32"
      label={t('recipes.form.servings')}
      bind:value={servings}
      errors={fieldErrors.servings}
    />
    <Input
      id="recipe-prep"
      type="number"
      min={1}
      max={1440}
      class="w-56"
      label={t('recipes.form.prep_minutes')}
      bind:value={prepMinutes}
      errors={fieldErrors.prep_minutes}
    />
  </div>

  <fieldset class="flex flex-wrap items-center gap-2">
    <legend class="mb-2 text-sm font-medium">{t('recipes.form.tags')}</legend>
    {#each RECIPE_TAGS as tag (tag)}
      <label
        class="flex min-h-11 cursor-pointer items-center rounded-full border border-line px-4 has-checked:border-brand has-checked:bg-brand has-checked:text-brand-contrast has-focus-visible:outline-2 has-focus-visible:outline-brand"
      >
        <input type="checkbox" class="sr-only" value={tag} bind:group={tags} />
        {t(`recipe_tags.${tag}`)}
      </label>
    {/each}
    <label class="ml-2 flex min-h-11 items-center gap-2">
      <input type="checkbox" class="size-5 accent-brand" bind:checked={favorite} />
      ⭐ {t('recipes.form.favorite')}
    </label>
  </fieldset>

  <fieldset class="flex flex-col gap-2">
    <legend class="mb-2 text-sm font-medium">{t('recipes.form.ingredients')}</legend>
    {#each rows as row, index (index)}
      <div class="flex flex-wrap items-center gap-2">
        <input
          class="min-h-11 min-w-44 flex-1 rounded-lg border border-line bg-surface px-3"
          list="ingredient-suggestions"
          aria-label={t('recipes.form.ingredient')}
          bind:value={row.name}
          oninput={() => onNameInput(row)}
          maxlength={80}
        />
        <input
          type="number"
          step="any"
          min="0"
          class="min-h-11 w-24 rounded-lg border border-line bg-surface px-3"
          aria-label={t('recipes.form.quantity')}
          bind:value={row.quantity}
          disabled={row.unit === 'qb'}
        />
        <select
          class="min-h-11 rounded-lg border border-line bg-surface px-2"
          aria-label={t('recipes.form.unit')}
          bind:value={row.unit}
        >
          {#each UNITS as unit (unit)}
            <option value={unit}>{t(`units.${unit}`)}</option>
          {/each}
        </select>
        <input
          class="min-h-11 w-32 rounded-lg border border-line bg-surface px-3"
          aria-label={t('recipes.form.note')}
          placeholder={t('recipes.form.note')}
          bind:value={row.note}
          maxlength={80}
        />
        <button
          type="button"
          class="size-11 rounded-lg text-danger hover:bg-surface-muted"
          aria-label={t('recipes.form.remove_ingredient', { name: row.name || String(index + 1) })}
          onclick={() => (rows = rows.filter((_, i) => i !== index))}>✕</button
        >
      </div>
    {/each}
    <div>
      <Button
        variant="secondary"
        onclick={() => (rows = [...rows, { name: '', quantity: null, unit: 'g', note: '' }])}
      >
        {t('recipes.form.add_ingredient')}
      </Button>
    </div>
    {#if fieldErrors.ingredients}
      <p class="text-sm text-danger" role="alert">{t('errors.validation_failed')}</p>
    {/if}
  </fieldset>

  <label class="flex flex-col gap-1">
    <span class="text-sm font-medium">{t('recipes.form.instructions')}</span>
    <textarea
      class="min-h-40 rounded-lg border border-line bg-surface p-3"
      bind:value={instructions}
      maxlength={10000}></textarea>
  </label>

  {#if formError}
    <p class="text-sm text-danger" role="alert">{formError}</p>
  {/if}
  <div class="flex justify-end">
    <Button type="submit" size="lg" loading={saving}>{t('common.save')}</Button>
  </div>
</form>
