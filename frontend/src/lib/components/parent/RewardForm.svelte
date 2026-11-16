<script lang="ts">
  import { ApiError } from '#lib/api/errors.ts';
  import type { Reward, RewardInput, RewardPeriod } from '#lib/api/types.ts';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';

  interface Props {
    reward?: Reward | null;
    onsubmit: (input: RewardInput) => Promise<void>;
    oncancel?: () => void;
  }

  let { reward = null, onsubmit, oncancel }: Props = $props();

  // svelte-ignore state_referenced_locally
  const init = reward;
  let title = $state(init?.title ?? '');
  let icon = $state(init?.icon ?? '🎁');
  let cost = $state<number>(init?.cost ?? 50);
  let limited = $state(init?.limit_period != null);
  let limitPerPeriod = $state<number>(init?.limit_per_period ?? 1);
  let limitPeriod = $state<RewardPeriod>(init?.limit_period ?? 'week');
  let stock = $state<number | null>(init?.stock ?? null);
  let isActive = $state(init?.is_active ?? true);
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
        title,
        icon,
        cost: Number(cost),
        limit_per_period: limited ? Number(limitPerPeriod) : null,
        limit_period: limited ? limitPeriod : null,
        stock: stock === null || String(stock) === '' ? null : Number(stock),
        is_active: isActive
      });
    } catch (e) {
      if (e instanceof ApiError && e.code === 'validation_failed') fieldErrors = e.fieldErrors;
      else formError = errorMessage(e instanceof ApiError ? e.code : 'unknown_error');
    } finally {
      saving = false;
    }
  }
</script>

<form class="flex flex-col gap-4" onsubmit={submit}>
  <div class="flex gap-3">
    <Input
      id="reward-icon"
      class="w-24 [&_input]:text-center [&_input]:text-2xl"
      label={t('rewards.form.icon')}
      bind:value={icon}
      maxlength={8}
      required
      errors={fieldErrors.icon}
    />
    <Input
      id="reward-title"
      class="flex-1"
      label={t('rewards.form.title')}
      bind:value={title}
      maxlength={120}
      required
      errors={fieldErrors.title}
    />
  </div>
  <Input
    id="reward-cost"
    type="number"
    min={1}
    max={10000}
    class="w-40"
    label={t('rewards.form.cost')}
    bind:value={cost}
    required
    errors={fieldErrors.cost}
  />
  <label class="flex min-h-11 items-center gap-3">
    <input type="checkbox" class="size-5 accent-brand" bind:checked={limited} />
    <span>{t('rewards.form.limited')}</span>
  </label>
  {#if limited}
    <div class="flex flex-wrap items-end gap-3">
      <Input
        id="reward-limit"
        type="number"
        min={1}
        max={100}
        class="w-32"
        label={t('rewards.form.limit_per_period')}
        bind:value={limitPerPeriod}
        errors={fieldErrors.limit_per_period}
      />
      <label class="flex flex-col gap-1">
        <span class="text-sm font-medium">{t('rewards.form.limit_period')}</span>
        <select
          class="min-h-11 rounded-lg border border-line bg-surface px-3"
          bind:value={limitPeriod}
        >
          {#each ['day', 'week', 'month'] as const as period (period)}
            <option value={period}>{t(`rewards.period.${period}`)}</option>
          {/each}
        </select>
      </label>
    </div>
  {/if}
  <Input
    id="reward-stock"
    type="number"
    min={0}
    max={10000}
    class="w-56"
    label={t('rewards.form.stock')}
    bind:value={stock}
    errors={fieldErrors.stock}
  />
  {#if reward}
    <label class="flex min-h-11 items-center gap-3">
      <input type="checkbox" class="size-5 accent-brand" bind:checked={isActive} />
      <span>{t('rewards.form.active')}</span>
    </label>
  {/if}
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
