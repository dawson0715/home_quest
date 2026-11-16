<script lang="ts">
  import { ApiError } from '#lib/api/errors.ts';
  import type { Avatar, Child, ChildInput } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { AVATARS, CHILD_COLORS } from '#lib/utils/avatars.ts';

  interface Props {
    child?: Child | null;
    onsubmit: (input: ChildInput) => Promise<void>;
    oncancel?: () => void;
  }

  let { child = null, onsubmit, oncancel }: Props = $props();

  // Form state is a snapshot of the child at mount time
  // svelte-ignore state_referenced_locally
  const initial = child;
  let name = $state(initial?.name ?? '');
  let avatar = $state<Avatar>(initial?.avatar ?? 'fox');
  let color = $state(initial?.color ?? CHILD_COLORS[0]);
  let birth = $state(initial?.birth_year_month ?? '');
  let canCheckShopping = $state(initial?.can_check_shopping ?? false);
  let saving = $state(false);
  let fieldErrors = $state<Record<string, string[]>>({});
  let formError = $state<string | null>(null);

  async function submit(event: SubmitEvent) {
    event.preventDefault();
    saving = true;
    fieldErrors = {};
    formError = null;
    try {
      await onsubmit({
        name,
        avatar,
        color,
        birth_year_month: birth || null,
        can_check_shopping: canCheckShopping
      });
    } catch (e) {
      if (e instanceof ApiError && e.code === 'validation_failed') fieldErrors = e.fieldErrors;
      else formError = errorMessage(e instanceof ApiError ? e.code : 'unknown_error');
    } finally {
      saving = false;
    }
  }
</script>

<form class="flex flex-col gap-5" onsubmit={submit}>
  <div class="flex items-center gap-4">
    <ChildAvatar {avatar} {color} size="lg" />
    <Input
      id="child-name"
      class="flex-1"
      label={t('children.form.name')}
      hint={t('children.form.name_hint')}
      bind:value={name}
      maxlength={40}
      required
      errors={fieldErrors.name}
    />
  </div>

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

  <Input
    id="child-birth"
    type="month"
    label={t('children.form.birth')}
    bind:value={birth}
    errors={fieldErrors.birth_year_month}
  />

  <label class="flex min-h-11 items-center gap-3">
    <input type="checkbox" class="size-5 accent-brand" bind:checked={canCheckShopping} />
    <span>{t('children.form.can_check_shopping')}</span>
  </label>

  {#if formError}
    <p class="text-sm text-danger" role="alert">{formError}</p>
  {/if}

  <div class="flex justify-end gap-2">
    {#if oncancel}
      <Button variant="ghost" onclick={oncancel}>{t('common.cancel')}</Button>
    {/if}
    <Button type="submit" loading={saving}>{t('common.save')}</Button>
  </div>
</form>
