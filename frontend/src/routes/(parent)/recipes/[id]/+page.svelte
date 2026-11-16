<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import {
    deleteRecipe,
    removeRecipeImage,
    updateRecipe,
    uploadRecipeImage
  } from '#lib/api/endpoints/meals.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Recipe, RecipeInput } from '#lib/api/types.ts';
  import RecipeForm from '#lib/components/meals/RecipeForm.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();

  // svelte-ignore state_referenced_locally
  let recipe = $state<Recipe>(data.recipe);
  let uploading = $state(false);
  let confirmDelete = $state(false);

  function fail(e: unknown) {
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function save(input: RecipeInput) {
    recipe = await updateRecipe(recipe.id, input);
    toasts.success(t('recipes.saved'));
  }

  async function upload(event: Event) {
    const input = event.currentTarget as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    uploading = true;
    try {
      recipe = await uploadRecipeImage(recipe.id, file);
      toasts.success(t('recipes.form.image_saved'));
    } catch (e) {
      fail(e);
    } finally {
      uploading = false;
      input.value = '';
    }
  }

  async function removeImage() {
    try {
      recipe = await removeRecipeImage(recipe.id);
    } catch (e) {
      fail(e);
    }
  }

  async function remove() {
    confirmDelete = false;
    try {
      await deleteRecipe(recipe.id);
      toasts.success(t('recipes.deleted'));
      await goto(resolve('/(parent)/recipes'));
    } catch (e) {
      fail(e);
    }
  }
</script>

<svelte:head>
  <title>{recipe.title} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-3xl flex-col gap-4">
  <a href={resolve('/(parent)/recipes')} class="text-sm text-ink-muted hover:underline"
    >← {t('common.back')}</a
  >
  <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-sm">
    <h2 class="text-lg font-semibold">{t('recipes.form.image')}</h2>
    {#if recipe.image_url}
      <img src={recipe.image_url} alt={recipe.title} class="max-h-72 rounded-lg object-cover" />
    {/if}
    <div class="flex flex-wrap gap-2">
      <label
        class="inline-flex min-h-11 cursor-pointer items-center rounded-card bg-surface-muted px-4 font-semibold focus-within:outline-2 focus-within:outline-brand"
      >
        <input
          type="file"
          accept="image/jpeg,image/png,image/webp"
          class="sr-only"
          onchange={upload}
          disabled={uploading}
        />
        📷 {t('recipes.form.image_upload')}
      </label>
      {#if recipe.image_url}
        <Button variant="ghost" onclick={removeImage}>{t('recipes.form.image_remove')}</Button>
      {/if}
    </div>
  </section>
  <section class="rounded-card bg-surface p-5 shadow-sm">
    <h1 class="mb-4 text-2xl font-semibold">{t('recipes.edit_title')}</h1>
    {#key recipe.id}
      <RecipeForm {recipe} onsubmit={save} />
    {/key}
  </section>
  <div class="flex justify-end">
    <Button variant="danger" onclick={() => (confirmDelete = true)}>{t('recipes.delete')}</Button>
  </div>
</div>

<Modal bind:open={confirmDelete} title={t('recipes.delete_confirm_title', { title: recipe.title })}>
  <p>{t('recipes.delete_confirm_text')}</p>
  {#snippet footer()}
    <Button variant="ghost" onclick={() => (confirmDelete = false)}>{t('common.cancel')}</Button>
    <Button variant="danger" onclick={remove}>{t('recipes.delete')}</Button>
  {/snippet}
</Modal>
