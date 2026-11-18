<script lang="ts">
  import { resolve } from '$app/paths';
  import { getFamilyMembers, updateFamily } from '#lib/api/endpoints/family.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { FamilyMember, FamilySettings, MealType } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import PushToggle from '#lib/components/ui/PushToggle.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  const ALL_MEALS: MealType[] = ['breakfast', 'lunch', 'snack', 'dinner'];

  const family = session.me?.family;
  let name = $state(family?.name ?? '');
  let settings = $state<FamilySettings>(
    family
      ? { ...family.settings, meal_types: [...family.settings.meal_types] }
      : {
          allow_penalties: false,
          weekly_email: false,
          meal_types: ALL_MEALS,
          week_starts_on: 1,
          shopping_poll_seconds: 5,
          ai_suggestions: true
        }
  );
  let members = $state<FamilyMember[]>([]);
  let saving = $state(false);
  let fieldErrors = $state<Record<string, string[]>>({});

  $effect(() => {
    getFamilyMembers()
      .then((list) => (members = list))
      .catch(() => undefined);
  });

  async function save(event: SubmitEvent) {
    event.preventDefault();
    saving = true;
    fieldErrors = {};
    try {
      await updateFamily({
        name,
        settings: {
          ...settings,
          week_starts_on: Number(settings.week_starts_on) as 1 | 7,
          shopping_poll_seconds: Number(settings.shopping_poll_seconds)
        }
      });
      await session.refresh();
      toasts.success(t('settings.saved'));
    } catch (e) {
      if (e instanceof ApiError && e.code === 'validation_failed') fieldErrors = e.fieldErrors;
      else toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
    } finally {
      saving = false;
    }
  }
</script>

<svelte:head>
  <title>{t('settings.title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-2xl flex-col gap-6">
  <h1 class="text-2xl font-semibold">{t('settings.title')}</h1>

  <form class="flex flex-col gap-4 rounded-card bg-surface p-5 shadow-sm" onsubmit={save}>
    <h2 class="text-lg font-semibold">{t('settings.family')}</h2>
    <Input
      id="family-name"
      label={t('settings.family_name')}
      bind:value={name}
      maxlength={80}
      required
      errors={fieldErrors.name}
    />
    <label class="flex items-start gap-3">
      <input
        type="checkbox"
        class="mt-1 size-5 accent-brand"
        bind:checked={settings.allow_penalties}
      />
      <span>
        {t('settings.allow_penalties')}
        <span class="block text-sm text-ink-muted">{t('settings.allow_penalties_hint')}</span>
      </span>
    </label>
    <label class="flex items-start gap-3">
      <input
        type="checkbox"
        class="mt-1 size-5 accent-brand"
        bind:checked={settings.weekly_email}
      />
      <span>
        {t('settings.weekly_email')}
        <span class="block text-sm text-ink-muted">{t('settings.weekly_email_hint')}</span>
      </span>
    </label>
    <label class="flex items-start gap-3">
      <input
        type="checkbox"
        class="mt-1 size-5 accent-brand"
        bind:checked={settings.ai_suggestions}
      />
      <span>
        {t('settings.ai_suggestions')}
        <span class="block text-sm text-ink-muted">{t('settings.ai_suggestions_hint')}</span>
      </span>
    </label>
    <fieldset class="flex flex-wrap gap-2">
      <legend class="mb-2 text-sm font-medium">{t('settings.meal_types')}</legend>
      {#each ALL_MEALS as meal (meal)}
        <label
          class="flex min-h-11 cursor-pointer items-center rounded-full border border-line px-4 has-checked:border-brand has-checked:bg-brand has-checked:text-brand-contrast has-focus-visible:outline-2 has-focus-visible:outline-brand"
        >
          <input type="checkbox" class="sr-only" value={meal} bind:group={settings.meal_types} />
          {t(`meal_type.${meal}`)}
        </label>
      {/each}
    </fieldset>
    {#if fieldErrors.settings}
      <p class="text-sm text-danger" role="alert">{t('errors.validation_failed')}</p>
    {/if}
    <div class="flex flex-wrap gap-4">
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">{t('settings.week_starts_on')}</span>
        <select
          class="min-h-11 rounded-lg border border-line bg-surface px-3"
          bind:value={settings.week_starts_on}
        >
          <option value={1}>{t('settings.monday')}</option>
          <option value={7}>{t('settings.sunday')}</option>
        </select>
      </label>
      <Input
        id="poll-seconds"
        type="number"
        min={2}
        max={60}
        class="w-56"
        label={t('settings.poll')}
        bind:value={settings.shopping_poll_seconds}
      />
    </div>
    <div class="flex justify-end">
      <Button type="submit" loading={saving}>{t('common.save')}</Button>
    </div>
  </form>

  <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-sm">
    <h2 class="text-lg font-semibold">{t('settings.notifications')}</h2>
    <PushToggle />
  </section>

  <section class="flex flex-col gap-3 rounded-card bg-surface p-5 shadow-sm">
    <h2 class="text-lg font-semibold">{t('settings.members')}</h2>
    <ul class="flex flex-col gap-1">
      {#each members as m (m.id)}
        <li class="flex justify-between">
          <span>{m.name ?? m.username}</span>
          <span class="text-ink-muted">{t(`dashboard.role.${m.role}`)}</span>
        </li>
      {/each}
    </ul>
    <a href={resolve('/(parent)/settings/devices')} class="text-brand underline"
      >{t('settings.devices_link')}</a
    >
    <a href={resolve('/(parent)/settings/privacy')} class="text-brand underline"
      >{t('privacy.link')}</a
    >
  </section>
</div>
