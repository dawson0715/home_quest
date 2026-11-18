<script lang="ts">
  import type { Avatar } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import { t } from '#lib/i18n/index.ts';
  import { AVATARS, CHILD_COLORS } from '#lib/utils/avatars.ts';

  interface Props {
    avatar: Avatar;
    color: string;
  }

  let { avatar = $bindable(), color = $bindable() }: Props = $props();
</script>

<fieldset class="flex flex-col gap-2">
  <legend class="mb-2 text-sm font-medium">{t('children.form.avatar')}</legend>
  <div class="grid grid-cols-4 gap-2 sm:grid-cols-8">
    {#each AVATARS as key (key)}
      <label
        class="grid min-h-14 cursor-pointer place-items-center rounded-card border-2 text-3xl has-checked:border-brand has-checked:bg-brand/10 has-focus-visible:outline-2 has-focus-visible:outline-brand {key ===
        avatar
          ? 'border-brand'
          : 'border-transparent bg-surface-muted'}"
      >
        <input type="radio" class="sr-only" name="avatar" value={key} bind:group={avatar} />
        <ChildAvatar avatar={key} {color} size="sm" />
      </label>
    {/each}
  </div>
</fieldset>

<fieldset class="flex flex-col gap-2">
  <legend class="mb-2 text-sm font-medium">{t('children.form.color')}</legend>
  <div class="flex flex-wrap gap-2">
    {#each CHILD_COLORS as swatch (swatch)}
      <label
        class="grid size-11 cursor-pointer place-items-center rounded-full ring-offset-2 has-checked:ring-2 has-checked:ring-ink has-focus-visible:outline-2 has-focus-visible:outline-brand"
        style:background-color={swatch}
      >
        <input
          type="radio"
          class="sr-only"
          name="color"
          value={swatch}
          bind:group={color}
          aria-label={swatch}
        />
      </label>
    {/each}
  </div>
</fieldset>
