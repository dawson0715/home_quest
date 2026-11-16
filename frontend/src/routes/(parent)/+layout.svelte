<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { page } from '$app/state';
  import { t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';
  import type { LayoutProps } from './$types';

  let { children }: LayoutProps = $props();

  const links = [
    { href: resolve('/(parent)/dashboard'), label: t('nav.dashboard') },
    { href: resolve('/(parent)/tasks'), label: t('nav.tasks') },
    { href: resolve('/(parent)/my-game'), label: t('nav.my_game') },
    { href: resolve('/(parent)/approvals'), label: t('nav.approvals') },
    { href: resolve('/(parent)/menu/[[week]]', {}), label: t('nav.menu') },
    { href: resolve('/(parent)/recipes'), label: t('nav.recipes') },
    { href: resolve('/(parent)/shopping/[[id]]', {}), label: t('nav.shopping') },
    { href: resolve('/(parent)/rewards'), label: t('nav.rewards') },
    { href: resolve('/(parent)/children'), label: t('nav.children') },
    { href: resolve('/(parent)/stats'), label: t('nav.stats') },
    { href: resolve('/(parent)/settings/family'), label: t('nav.settings') }
  ];

  async function logout() {
    session.clear();
    await goto(resolve('/(auth)/login'));
  }
</script>

<div class="flex min-h-dvh flex-col">
  <header class="border-b border-line bg-surface">
    <nav class="mx-auto flex max-w-5xl items-center gap-2 px-4 py-2" aria-label={t('nav.main')}>
      <a href={resolve('/(parent)/dashboard')} class="mr-2 text-xl font-bold text-brand">
        {t('app.name')}
      </a>
      <ul class="flex flex-1 flex-wrap gap-1">
        {#each links as link (link.href)}
          <li>
            <a
              href={link.href}
              class="flex min-h-11 items-center rounded-card px-3 hover:bg-surface-muted aria-[current=page]:font-semibold aria-[current=page]:text-brand"
              aria-current={page.url.pathname.startsWith(link.href) ? 'page' : undefined}
            >
              {link.label}
            </a>
          </li>
        {/each}
      </ul>
      <button
        type="button"
        class="min-h-11 rounded-card px-3 text-ink-muted hover:bg-surface-muted"
        onclick={logout}
      >
        {t('nav.logout')}
      </button>
    </nav>
  </header>
  <main class="mx-auto w-full max-w-5xl flex-1 p-4">
    {@render children()}
  </main>
</div>
