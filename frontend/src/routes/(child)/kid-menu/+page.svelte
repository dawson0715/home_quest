<script lang="ts">
  import {
    getMealPlan,
    listRecipes,
    proposeDish,
    voteMealEntry
  } from '#lib/api/endpoints/meals.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { MealEntry, MealPlan, MealSlot, RecipeSummary } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { formatDay } from '#lib/utils/dates.ts';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();

  let plan = $state<MealPlan | null>(null);
  let proposing = $state<MealSlot | null>(null);
  let recipes = $state<RecipeSummary[]>([]);
  let freeText = $state('');

  const token = $derived(deviceSession.childToken ?? '');
  const days = $derived(
    plan ? [...new Set(plan.slots.map((s) => s.date))].filter((date) => date >= data.today) : []
  );

  function fail(e: unknown) {
    const code = e instanceof ApiError ? e.code : 'unknown_error';
    toasts.error(code === 'conflict' ? t('kid_menu.already_proposed') : errorMessage(code));
  }

  async function load() {
    try {
      plan = await getMealPlan(data.week, { token });
    } catch (e) {
      fail(e);
    }
  }

  $effect(() => {
    load();
  });

  async function vote(entry: MealEntry, value: -1 | 1) {
    try {
      const updated = await voteMealEntry(entry.id, entry.votes.mine === value ? 0 : value, {
        token
      });
      entry.votes = updated.votes;
    } catch (e) {
      fail(e);
    }
  }

  async function openProposal(slot: MealSlot) {
    proposing = slot;
    freeText = '';
    recipes = await listRecipes({}, { token }).catch(() => []);
  }

  async function propose(input: { recipe_id?: number; free_text?: string }) {
    const slot = proposing;
    if (!slot) return;
    proposing = null;
    try {
      await proposeDish(token, data.week, {
        day: slot.day_index,
        meal_type: slot.meal_type,
        ...input
      });
      toasts.success(t('kid_menu.proposed'));
      await load();
    } catch (e) {
      fail(e);
    }
  }
</script>

<svelte:head>
  <title>{t('kid_menu.title')} · {t('app.name')}</title>
</svelte:head>

<main class="mx-auto flex max-w-3xl flex-col gap-6 p-4 sm:p-6">
  <h1 class="text-3xl font-bold">🍽️ {t('kid_menu.title')}</h1>
  {#if plan}
    {#each days as date (date)}
      <section class="flex flex-col gap-3">
        <h2 class="text-xl font-semibold capitalize">{formatDay(date)}</h2>
        {#each plan.slots.filter((s) => s.date === date) as slot (slot.meal_type)}
          <article class="flex flex-col gap-2 rounded-card bg-surface p-4 shadow-sm">
            <h3 class="font-semibold text-ink-muted">{t(`meal_type.${slot.meal_type}`)}</h3>
            {#each slot.entries as entry (entry.id)}
              <div class="flex flex-wrap items-center gap-3">
                <span class="flex-1 text-xl font-semibold">{entry.title}</span>
                {#if entry.status === 'proposed'}
                  <span class="text-sm">⏳ {t('kid_menu.waiting')}</span>
                {/if}
                <button
                  type="button"
                  class="grid size-14 place-items-center rounded-full text-3xl {entry.votes.mine ===
                  1
                    ? 'bg-success/20'
                    : 'bg-surface-muted'}"
                  aria-pressed={entry.votes.mine === 1}
                  aria-label={t('kid_menu.like')}
                  onclick={() => vote(entry, 1)}>👍</button
                >
                <button
                  type="button"
                  class="grid size-14 place-items-center rounded-full text-3xl {entry.votes.mine ===
                  -1
                    ? 'bg-danger/20'
                    : 'bg-surface-muted'}"
                  aria-pressed={entry.votes.mine === -1}
                  aria-label={t('kid_menu.dislike')}
                  onclick={() => vote(entry, -1)}>👎</button
                >
              </div>
            {:else}
              <p class="text-ink-muted">{t('kid_menu.empty_day')}</p>
            {/each}
            <div>
              <Button variant="ghost" onclick={() => openProposal(slot)}>
                💡 {t('kid_menu.propose')}
              </Button>
            </div>
          </article>
        {/each}
      </section>
    {/each}
  {:else}
    <p class="text-center text-ink-muted">{t('common.loading')}</p>
  {/if}
</main>

<Modal
  open={proposing !== null}
  title={proposing
    ? t('kid_menu.propose_title', {
        meal: t(`meal_type.${proposing.meal_type}`).toLowerCase(),
        day: formatDay(proposing.date)
      })
    : ''}
  onclose={() => (proposing = null)}
>
  <ul class="flex max-h-64 flex-col gap-1 overflow-y-auto">
    {#each recipes as recipe (recipe.id)}
      <li>
        <button
          type="button"
          class="flex min-h-14 w-full items-center gap-3 rounded-lg px-3 text-left text-lg hover:bg-surface-muted"
          onclick={() => propose({ recipe_id: recipe.id })}
        >
          {recipe.title}
        </button>
      </li>
    {/each}
  </ul>
  <form
    class="mt-3 flex items-end gap-2"
    onsubmit={(e) => {
      e.preventDefault();
      if (freeText.trim()) propose({ free_text: freeText.trim() });
    }}
  >
    <Input
      id="propose-free"
      class="flex-1"
      label={t('kid_menu.propose_free')}
      bind:value={freeText}
      maxlength={120}
    />
    <Button type="submit">{t('kid_menu.send')}</Button>
  </form>
</Modal>
