<script lang="ts">
  import { updateMe } from '#lib/api/endpoints/family.ts';
  import { ApiError } from '#lib/api/errors.ts';
  import type { Avatar, ParentMe } from '#lib/api/types.ts';
  import ChildAvatar from '#lib/components/child/ChildAvatar.svelte';
  import AvatarPicker from '#lib/components/ui/AvatarPicker.svelte';
  import Button from '#lib/components/ui/Button.svelte';
  import Input from '#lib/components/ui/Input.svelte';
  import { errorMessage, t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';
  import { toasts } from '#lib/stores/toast.svelte.ts';

  // Form state is a snapshot of the session at mount time
  const me = session.me as ParentMe | null;
  let name = $state(me?.player?.name ?? me?.user.name ?? me?.user.username ?? '');
  let email = $state(me?.user.email ?? '');
  let avatar = $state<Avatar>(me?.player?.avatar ?? 'owl');
  let color = $state(me?.player?.color ?? '#6B6B1F');
  let saving = $state(false);
  let fieldErrors = $state<Record<string, string[]>>({});

  async function save(event: SubmitEvent) {
    event.preventDefault();
    saving = true;
    fieldErrors = {};
    try {
      await updateMe({ name, email: email.trim() || null, avatar, color });
      await session.refresh();
      toasts.success(t('profile.saved'));
    } catch (e) {
      if (e instanceof ApiError && e.code === 'validation_failed') fieldErrors = e.fieldErrors;
      else toasts.error(errorMessage(e instanceof ApiError ? e.code : 'unknown_error'));
    } finally {
      saving = false;
    }
  }
</script>

<svelte:head>
  <title>{t('profile.title')} · {t('app.name')}</title>
</svelte:head>

<div class="mx-auto flex max-w-2xl flex-col gap-6">
  <h1 class="text-2xl font-semibold">{t('profile.title')}</h1>

  <form class="flex flex-col gap-5 rounded-card bg-surface p-5 shadow-sm" onsubmit={save}>
    <div class="flex items-center gap-4">
      <ChildAvatar {avatar} {color} size="lg" />
      <Input
        id="profile-name"
        class="flex-1"
        label={t('profile.name')}
        hint={t('profile.name_hint')}
        bind:value={name}
        maxlength={40}
        required
        errors={fieldErrors.name}
      />
    </div>

    <Input
      id="profile-email"
      type="email"
      autocomplete="email"
      label={t('profile.email')}
      hint={t('profile.email_hint')}
      bind:value={email}
      errors={fieldErrors.email}
    />

    {#if me?.player}
      <AvatarPicker bind:avatar bind:color />
    {/if}

    <p class="text-sm text-ink-muted">
      {t('profile.username', { username: me?.user.username ?? '' })}
    </p>

    <div class="flex justify-end">
      <Button type="submit" loading={saving}>{t('common.save')}</Button>
    </div>
  </form>
</div>
