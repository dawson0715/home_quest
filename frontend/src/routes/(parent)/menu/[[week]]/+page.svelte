<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import {
    copyMealPlan,
    deleteMealTemplate,
    getMealPlan,
    listMealTemplates,
    reviewMealEntry,
    saveMealTemplate,
    updateMealPlanNotes
  } from '#lib/api/endpoints/meals.ts';
  import { generateShoppingList, listShoppingLists } from '#lib/api/endpoints/shopping.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { MealEntry, MealPlan, MealSlot, MealTemplate } from '#lib/api/types.ts';
  import MenuSuggest from '#lib/components/meals/MenuSuggest.svelte';
  import SlotEditor from '#lib/components/meals/SlotEditor.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import Modal from '#lib/components/ui/Modal.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';
  import { addDays, formatDay, formatShortDay, todayInRome } from '#lib/utils/dates.ts';
  import type { PageProps } from './$types';

  let { data }: PageProps = $props();

  let plan = $state<MealPlan | null>(null);
  let notes = $state('');
  let editing = $state<MealSlot | null>(null);
  let templatesOpen = $state(false);
  let templates = $state<MealTemplate[]>([]);
  let saveTemplateOpen = $state(false);
  let templateName = $state('');
  let mobileDay = $state(0);
  let suggestOpen = $state(false);

  const week = $derived(data.week);
  const days = $derived([0, 1, 2, 3, 4, 5, 6].map((i) => addDays(week, i)));

  function fail(e: unknown) {
    toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
  }

  async function load() {
    try {
      plan = await getMealPlan(week);
      notes = plan.notes ?? '';
      const todayIndex = days.indexOf(todayInRome());
      mobileDay = todayIndex >= 0 ? todayIndex : 0;
    } catch (e) {
      fail(e);
    }
  }

  $effect(() => {
    void week;
    load();
  });

  function slotOf(day: number, meal: string): MealSlot | undefined {
    return plan?.slots.find((s) => s.day_index === day && s.meal_type === meal);
  }

  function replaceSlot(saved: MealSlot) {
    if (!plan) return;
    const i = plan.slots.findIndex(
      (s) => s.day_index === saved.day_index && s.meal_type === saved.meal_type
    );
    if (i >= 0) plan.slots[i] = saved;
  }

  async function review(entry: MealEntry, decision: 'accept' | 'reject') {
    try {
      await reviewMealEntry(entry.id, decision);
      await load();
    } catch (e) {
      fail(e);
    }
  }

  async function saveNotes() {
    try {
      await updateMealPlanNotes(week, notes);
      toasts.success(t('menu.notes_saved'));
    } catch (e) {
      fail(e);
    }
  }

  async function copyLastWeek() {
    try {
      plan = await copyMealPlan(week, { source_week_start: addDays(week, -7) });
      toasts.success(t('menu.copied'));
    } catch (e) {
      fail(e);
    }
  }

  async function openTemplates() {
    templatesOpen = true;
    templates = await listMealTemplates().catch(() => []);
  }

  async function useTemplate(tpl: MealTemplate) {
    templatesOpen = false;
    try {
      plan = await copyMealPlan(week, { template_id: tpl.id });
      toasts.success(t('menu.copied'));
    } catch (e) {
      fail(e);
    }
  }

  async function removeTemplate(tpl: MealTemplate) {
    await deleteMealTemplate(tpl.id).catch(fail);
    templates = templates.filter((x) => x.id !== tpl.id);
  }

  async function saveTemplate(event: SubmitEvent) {
    event.preventDefault();
    try {
      await saveMealTemplate(week, templateName);
      saveTemplateOpen = false;
      templateName = '';
      toasts.success(t('menu.template_saved'));
    } catch (e) {
      fail(e);
    }
  }

  async function generateShopping() {
    try {
      const lists = await listShoppingLists();
      const target = lists.find((l) => l.is_default) ?? lists[0];
      const res = await generateShoppingList(target.id, week);
      toasts.success(
        t('shopping.generated', {
          added: Number(res.meta.added ?? 0),
          updated: Number(res.meta.updated ?? 0)
        })
      );
      await goto(resolve('/(parent)/shopping/[[id]]', { id: String(target.id) }));
    } catch (e) {
      fail(e);
    }
  }

  async function helpCook(slot: MealSlot) {
    const title =
      slot.entries.find((e) => e.status === 'planned')?.title ?? t(`meal_type.${slot.meal_type}`);
    await goto(
      resolve(
        `/(parent)/tasks/new?meal_slot=${slot.id}&date=${slot.date}&title=${encodeURIComponent(
          t('menu.help_cook_title', { title })
        )}`
      )
    );
  }
</script>

<svelte:head>
  <title>{t('menu.title')} · {t('app.name')}</title>
</svelte:head>

{#snippet cell(slot: MealSlot | undefined)}
  {#if slot}
    <div class="flex h-full flex-col gap-1 rounded-lg bg-surface p-2 text-sm shadow-sm">
      {#each slot.entries as entry (entry.id)}
        <div class="rounded-md {entry.status === 'proposed' ? 'bg-brand/10 p-1' : ''}">
          <p class="font-medium">{entry.title}</p>
          {#if entry.votes.up || entry.votes.down}
            <p class="text-xs text-ink-muted">
              {t('menu.votes', { up: entry.votes.up, down: entry.votes.down })}
            </p>
          {/if}
          {#if entry.status === 'proposed' && entry.proposed_by}
            <p class="text-xs">{t('menu.proposed_by', { name: entry.proposed_by.name })}</p>
            <div class="flex gap-1">
              <button
                type="button"
                class="min-h-9 rounded px-2 text-xs font-semibold text-success hover:bg-surface"
                onclick={() => review(entry, 'accept')}>{t('menu.accept')}</button
              >
              <button
                type="button"
                class="min-h-9 rounded px-2 text-xs text-danger hover:bg-surface"
                onclick={() => review(entry, 'reject')}>{t('menu.reject')}</button
              >
            </div>
          {/if}
        </div>
      {:else}
        <p class="text-ink-muted">{t('menu.empty_slot')}</p>
      {/each}
      <div class="mt-auto flex flex-wrap gap-1 pt-1">
        <button
          type="button"
          class="min-h-9 rounded px-2 text-xs font-semibold text-brand hover:bg-surface-muted"
          aria-label={t('menu.edit_slot', {
            meal: t(`meal_type.${slot.meal_type}`),
            day: formatDay(slot.date)
          })}
          onclick={() => (editing = slot)}>✏️ {t('common.edit')}</button
        >
        {#if slot.id && slot.entries.some((e) => e.status === 'planned')}
          <button
            type="button"
            class="min-h-9 rounded px-2 text-xs hover:bg-surface-muted"
            title={t('menu.help_cook')}
            aria-label={t('menu.help_cook')}
            onclick={() => helpCook(slot)}>👩‍🍳</button
          >
        {/if}
      </div>
    </div>
  {/if}
{/snippet}

<div class="flex flex-col gap-4">
  <div class="flex flex-wrap items-center justify-between gap-2">
    <h1 class="text-2xl font-semibold">{t('menu.title')}</h1>
    <div class="flex items-center gap-1">
      <a
        class="grid size-11 place-items-center rounded-full hover:bg-surface"
        aria-label={t('menu.prev_week')}
        href={resolve('/(parent)/menu/[[week]]', { week: addDays(week, -7) })}>←</a
      >
      <span class="font-medium">{t('menu.week_of', { date: formatDay(week) })}</span>
      <a
        class="grid size-11 place-items-center rounded-full hover:bg-surface"
        aria-label={t('menu.next_week')}
        href={resolve('/(parent)/menu/[[week]]', { week: addDays(week, 7) })}>→</a
      >
    </div>
  </div>
  <div class="flex flex-wrap gap-2">
    <Button onclick={() => (suggestOpen = true)}>{t('menu.suggest.button')}</Button>
    <Button variant="secondary" onclick={copyLastWeek}>{t('menu.copy_last_week')}</Button>
    <Button variant="secondary" onclick={openTemplates}>{t('menu.use_template')}</Button>
    <Button variant="ghost" onclick={() => (saveTemplateOpen = true)}
      >{t('menu.save_template')}</Button
    >
    <Button onclick={generateShopping}>🛒 {t('shopping.generate_from_planner')}</Button>
  </div>

  {#if plan}
    <!-- Mobile: one day at a time -->
    <div class="flex flex-col gap-3 md:hidden">
      <div class="flex gap-1 overflow-x-auto" role="tablist" aria-label={t('menu.day_tabs')}>
        {#each days as date, i (date)}
          <button
            type="button"
            role="tab"
            aria-selected={mobileDay === i}
            class="min-h-11 shrink-0 rounded-full px-3 {mobileDay === i
              ? 'bg-brand text-brand-contrast'
              : 'bg-surface'}"
            onclick={() => (mobileDay = i)}>{formatShortDay(date)}</button
          >
        {/each}
      </div>
      {#each plan.meal_types as meal (meal)}
        <section>
          <h2 class="mb-1 text-sm font-semibold text-ink-muted">{t(`meal_type.${meal}`)}</h2>
          {@render cell(slotOf(mobileDay, meal))}
        </section>
      {/each}
    </div>

    <!-- Desktop: 7 columns × meals -->
    <div class="hidden overflow-x-auto md:block">
      <table class="w-full table-fixed border-separate border-spacing-1">
        <thead>
          <tr>
            <th class="w-24"></th>
            {#each days as date (date)}
              <th
                scope="col"
                class="text-sm font-semibold {date === todayInRome() ? 'text-brand' : ''}"
                >{formatShortDay(date)}</th
              >
            {/each}
          </tr>
        </thead>
        <tbody>
          {#each plan.meal_types as meal (meal)}
            <tr>
              <th scope="row" class="text-left align-top text-sm font-semibold text-ink-muted"
                >{t(`meal_type.${meal}`)}</th
              >
              {#each days as date, i (date)}
                <td class="h-28 align-top">{@render cell(slotOf(i, meal))}</td>
              {/each}
            </tr>
          {/each}
        </tbody>
      </table>
    </div>

    <form
      class="flex flex-col gap-2 rounded-card bg-surface p-4 shadow-sm"
      onsubmit={(e) => {
        e.preventDefault();
        saveNotes();
      }}
    >
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">{t('menu.notes')}</span>
        <textarea
          class="min-h-20 rounded-lg border border-line p-2"
          bind:value={notes}
          maxlength={2000}></textarea>
      </label>
      <div><Button type="submit" variant="secondary">{t('common.save')}</Button></div>
    </form>
  {:else}
    <p class="text-ink-muted">{t('common.loading')}</p>
  {/if}
</div>

<Modal
  open={editing !== null}
  title={editing
    ? t('menu.edit_slot', {
        meal: t(`meal_type.${editing.meal_type}`),
        day: formatDay(editing.date)
      })
    : ''}
  onclose={() => (editing = null)}
>
  {#if editing}
    <SlotEditor
      weekStart={week}
      slot={editing}
      onsaved={(saved) => {
        replaceSlot(saved);
        editing = null;
        toasts.success(t('menu.slot_saved'));
      }}
      oncancel={() => (editing = null)}
    />
  {/if}
</Modal>

<Modal bind:open={suggestOpen} title={t('menu.suggest.title')}>
  {#if suggestOpen}
    <MenuSuggest
      weekStart={week}
      onapplied={async (n) => {
        suggestOpen = false;
        toasts.success(t('menu.suggest.applied', { n }));
        await load();
      }}
    />
  {/if}
</Modal>

<Modal bind:open={templatesOpen} title={t('menu.use_template')}>
  {#if templates.length === 0}
    <p class="text-ink-muted">{t('menu.templates_empty')}</p>
  {/if}
  <ul class="flex flex-col gap-1">
    {#each templates as tpl (tpl.id)}
      <li class="flex items-center gap-2">
        <button
          type="button"
          class="flex min-h-11 flex-1 items-center justify-between rounded-lg px-3 hover:bg-surface-muted"
          onclick={() => useTemplate(tpl)}
        >
          <span>{tpl.name}</span>
          <span class="text-sm text-ink-muted">{t('menu.template_dishes', { n: tpl.dishes })}</span>
        </button>
        <button
          type="button"
          class="size-11 rounded-lg text-danger hover:bg-surface-muted"
          aria-label={t('menu.delete_template', { name: tpl.name })}
          onclick={() => removeTemplate(tpl)}>✕</button
        >
      </li>
    {/each}
  </ul>
</Modal>

<Modal bind:open={saveTemplateOpen} title={t('menu.save_template')}>
  <form class="flex items-end gap-2" onsubmit={saveTemplate}>
    <Input
      id="template-name"
      class="flex-1"
      label={t('menu.template_name')}
      bind:value={templateName}
      maxlength={60}
      required
    />
    <Button type="submit">{t('common.save')}</Button>
  </form>
</Modal>
