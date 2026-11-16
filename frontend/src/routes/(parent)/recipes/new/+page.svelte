<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { createRecipe } from '#lib/api/endpoints/meals.ts';
  import type { RecipeInput } from '#lib/api/types.ts';
  import RecipeForm from '#lib/components/meals/RecipeForm.svelte';
  import { t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  async function save(input: RecipeInput) {
    const recipe = await createRecipe(input);
    toasts.success(t('recipes.saved'));
    await goto(resolve('/(parent)/recipes/[id]', { id: String(recipe.id) }));
  }
</script>

<svelte:head>
  <title>{t('recipes.new_title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-3xl flex-col gap-4">
  <a href={resolve('/(parent)/recipes')} class="text-sm text-ink-muted hover:underline"
    >← {t('common.back')}</a
  >
  <section class="rounded-card bg-surface p-5 shadow-sm">
    <h1 class="mb-4 text-2xl font-semibold">{t('recipes.new_title')}</h1>
    <p class="mb-4 text-sm text-ink-muted">{t('recipes.form.image_after_save')}</p>
    <RecipeForm onsubmit={save} />
  </section>
</div>
