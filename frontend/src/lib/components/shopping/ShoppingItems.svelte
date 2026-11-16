<script lang="ts">
  import type { ShoppingCategory, ShoppingItem } from '#lib/api/types.ts';
  import { t } from '#lib/i18n/index.ts';
  import { categoryName, formatQuantity } from '#lib/utils/categories.ts';

  interface Props {
    items: ShoppingItem[];
    categories: ShoppingCategory[];
    /** Kids get bigger rows and no edit actions. */
    kid?: boolean;
    ontoggle: (item: ShoppingItem) => void;
    onremove?: (item: ShoppingItem) => void;
    onhave?: (item: ShoppingItem) => void;
  }

  let { items, categories, kid = false, ontoggle, onremove, onhave }: Props = $props();

  const open = $derived(items.filter((i) => !i.checked));
  const done = $derived(items.filter((i) => i.checked));
  const groups = $derived(
    [...categories, undefined]
      .map((category) => ({
        category,
        items: open.filter((i) =>
          category ? i.category_id === category.id : !categories.some((c) => c.id === i.category_id)
        )
      }))
      .filter((g) => g.items.length > 0)
  );
</script>

{#snippet row(item: ShoppingItem)}
  <li class="flex items-center gap-2 {kid ? 'min-h-16 text-xl' : 'min-h-12'}">
    <button
      type="button"
      class="flex flex-1 items-center gap-3 rounded-lg px-2 py-2 text-left hover:bg-surface-muted"
      aria-pressed={item.checked}
      aria-label={item.checked
        ? t('shopping.uncheck', { label: item.label })
        : t('shopping.check', { label: item.label })}
      onclick={() => ontoggle(item)}
    >
      <span
        class="grid shrink-0 place-items-center rounded-full border-2 {kid
          ? 'size-10'
          : 'size-7'} {item.checked ? 'border-success bg-success text-white' : 'border-line'}"
        aria-hidden="true">{item.checked ? '✓' : ''}</span
      >
      <span class="flex flex-col {item.checked ? 'text-ink-muted line-through' : ''}">
        <span>{item.label}</span>
        <span class="text-sm text-ink-muted">
          {formatQuantity(item.quantity, item.unit)}{item.source === 'generated'
            ? ` · ${t('shopping.from_menu')}`
            : ''}
        </span>
      </span>
    </button>
    {#if onhave && item.source === 'generated' && item.ingredient_id && !item.checked}
      <button
        type="button"
        class="min-h-11 rounded-lg px-2 text-sm text-ink-muted hover:bg-surface-muted"
        onclick={() => onhave(item)}>{t('shopping.have_it')}</button
      >
    {/if}
    {#if onremove}
      <button
        type="button"
        class="size-11 rounded-lg text-danger hover:bg-surface-muted"
        aria-label={t('shopping.remove', { label: item.label })}
        onclick={() => onremove(item)}>✕</button
      >
    {/if}
  </li>
{/snippet}

<div class="flex flex-col gap-4">
  {#each groups as group (group.category?.id ?? 0)}
    <section>
      <h2 class="mb-1 text-sm font-semibold text-ink-muted">
        {group.category?.icon ?? '🛒'}
        {categoryName(group.category)}
      </h2>
      <ul class="divide-y divide-line rounded-card bg-surface px-2 shadow-sm">
        {#each group.items as item (item.client_uuid ?? item.id)}
          {@render row(item)}
        {/each}
      </ul>
    </section>
  {/each}
  {#if done.length > 0}
    <section>
      <h2 class="mb-1 text-sm font-semibold text-ink-muted">✓ {t('shopping.done_section')}</h2>
      <ul class="divide-y divide-line rounded-card bg-surface px-2 opacity-80 shadow-sm">
        {#each done as item (item.client_uuid ?? item.id)}
          {@render row(item)}
        {/each}
      </ul>
    </section>
  {/if}
</div>
