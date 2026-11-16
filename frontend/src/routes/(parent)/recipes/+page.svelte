<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { listRecipes } from '#lib/api/endpoints/meals.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import { RECIPE_TAGS, type RecipeSummary } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  let q = $state('');
  let tag = $state('');
  let recipes = $state<RecipeSummary[] | null>(null);

  $effect(() => {
    const params = { q, tag };
    const timer = setTimeout(() => {
      listRecipes(params)
        .then((list) => (recipes = list))
        .catch((e) => toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error')));
    }, 200);
    return () => clearTimeout(timer);
  });
</script>

<svelte:head>
  <title>{t('recipes.title')} · {t('app.name')}</title>
</svelte:head>

<div class="flex flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-2xl font-semibold">{t('recipes.title')}</h1>
    <Button onclick={() => goto(resolve('/(parent)/recipes/new'))}>{t('recipes.new')}</Button>
  </div>
  <div class="flex flex-wrap gap-2">
    <input
      type="search"
      class="min-h-11 min-w-52 flex-1 rounded-lg border border-line bg-surface px-3"
      placeholder={t('recipes.search')}
      aria-label={t('recipes.search')}
      bind:value={q}
    />
    <select
      class="min-h-11 rounded-lg border border-line bg-surface px-2"
      aria-label={t('recipes.form.tags')}
      bind:value={tag}
    >
      <option value="">{t('recipes.all_tags')}</option>
      {#each RECIPE_TAGS as option (option)}
        <option value={option}>{t(`recipe_tags.${option}`)}</option>
      {/each}
    </select>
  </div>

  {#if recipes === null}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {:else if recipes.length === 0}
    <p class="rounded-card bg-surface p-6 text-center text-ink-muted">{t('recipes.empty')}</p>
  {:else}
    <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
      {#each recipes as recipe (recipe.id)}
        <li>
          <a
            href={resolve('/(parent)/recipes/[id]', { id: String(recipe.id) })}
            class="flex h-full flex-col overflow-hidden rounded-card bg-surface shadow-sm hover:shadow-md"
          >
            {#if recipe.image_url}
              <img src={recipe.image_url} alt="" class="h-36 w-full object-cover" loading="lazy" />
            {:else}
              <span
                class="grid h-36 place-items-center bg-surface-muted text-5xl"
                aria-hidden="true">🍽️</span
              >
            {/if}
            <span class="flex flex-col gap-1 p-3">
              <span class="font-semibold">{recipe.is_favorite ? '⭐ ' : ''}{recipe.title}</span>
              <span class="text-sm text-ink-muted">
                {[
                  recipe.prep_minutes ? t('recipes.minutes', { n: recipe.prep_minutes }) : null,
                  ...recipe.tags.map((x) => t(`recipe_tags.${x}`))
                ]
                  .filter(Boolean)
                  .join(' · ')}
              </span>
            </span>
          </a>
        </li>
      {/each}
    </ul>
  {/if}
</div>
