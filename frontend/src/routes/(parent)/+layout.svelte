<script lang="ts">
  import { goto } from '$app/navigation';
  import { resolve } from '$app/paths';
  import { page } from '$app/state';
  import { t } from '#lib/i18n/index.ts';
  import { session } from '#lib/stores/session.svelte.ts';
  import type { LayoutProps } from './$types';

  let { children }: LayoutProps = $props();

  interface NavItem {
    href: string;
    label: string;
    icon: string;
  }

  /** Bottom navigation bar (Material 3): the three main destinations. */
  const primary: NavItem[] = [
    { href: resolve('/(parent)/tasks'), label: t('nav.tasks'), icon: '✅' },
    { href: resolve('/(parent)/menu/[[week]]', {}), label: t('nav.menu'), icon: '🍽️' },
    { href: resolve('/(parent)/shopping/[[id]]', {}), label: t('nav.shopping'), icon: '🛒' }
  ];

  /** Navigation drawer: everything, grouped. */
  const drawer: NavItem[][] = [
    [
      { href: resolve('/(parent)/dashboard'), label: t('nav.dashboard'), icon: '🏠' },
      ...primary,
      { href: resolve('/(parent)/approvals'), label: t('nav.approvals'), icon: '📋' },
      { href: resolve('/(parent)/my-game'), label: t('nav.my_game'), icon: '🎮' }
    ],
    [
      { href: resolve('/(parent)/recipes'), label: t('nav.recipes'), icon: '📖' },
      { href: resolve('/(parent)/rewards'), label: t('nav.rewards'), icon: '🎁' },
      { href: resolve('/(parent)/children'), label: t('nav.children'), icon: '👧' },
      { href: resolve('/(parent)/stats'), label: t('nav.stats'), icon: '📊' },
      { href: resolve('/(parent)/settings/family'), label: t('nav.settings'), icon: '⚙️' }
    ]
  ];

  let dialog = $state<HTMLDialogElement>();

  const isActive = (href: string) => page.url.pathname.startsWith(href);
  const title = $derived(drawer.flat().find((item) => isActive(item.href))?.label ?? t('app.name'));

  function openDrawer() {
    dialog?.showModal();
  }

  function closeDrawer() {
    dialog?.close();
  }

  async function logout() {
    closeDrawer();
    session.clear();
    await goto(resolve('/(auth)/login'));
  }
</script>

{#snippet drawerContent(modal: boolean)}
  <div class="flex h-full flex-col gap-1 overflow-y-auto px-3 py-4">
    <div class="flex items-center justify-between px-4 pb-3">
      <span class="text-xl font-bold text-brand">{t('app.name')}</span>
      {#if modal}
        <button
          type="button"
          class="grid size-11 place-items-center rounded-full hover:bg-surface-muted"
          aria-label={t('nav.close_menu')}
          onclick={closeDrawer}>✕</button
        >
      {/if}
    </div>
    {#if session.me?.family}
      <p class="px-4 pb-2 text-sm text-ink-muted">{session.me.family.name}</p>
    {/if}
    {#each drawer as group, i (i)}
      {#if i > 0}<hr class="mx-4 my-2 border-line" />{/if}
      <ul class="flex flex-col gap-0.5">
        {#each group as item (item.href)}
          <li>
            <!-- hrefs are built with resolve() above -->
            <!-- eslint-disable svelte/no-navigation-without-resolve -->
            <a
              href={item.href}
              onclick={closeDrawer}
              aria-current={isActive(item.href) ? 'page' : undefined}
              class="flex min-h-14 items-center gap-3 rounded-full px-4 font-medium hover:bg-surface-muted aria-[current=page]:bg-brand/15 aria-[current=page]:text-brand"
            >
              <span class="w-6 text-center text-xl" aria-hidden="true">{item.icon}</span>
              {item.label}
            </a>
            <!-- eslint-enable svelte/no-navigation-without-resolve -->
          </li>
        {/each}
      </ul>
    {/each}
    <hr class="mx-4 my-2 border-line" />
    <button
      type="button"
      class="flex min-h-14 items-center gap-3 rounded-full px-4 text-left font-medium text-ink-muted hover:bg-surface-muted"
      onclick={logout}
    >
      <span class="w-6 text-center text-xl" aria-hidden="true">🚪</span>
      {t('nav.logout')}
    </button>
  </div>
{/snippet}

<div class="min-h-dvh lg:pl-80">
  <!-- Expanded screens: permanent navigation drawer -->
  <nav
    class="fixed inset-y-0 left-0 z-30 hidden w-80 border-r border-line bg-surface lg:block"
    aria-label={t('nav.drawer')}
  >
    {@render drawerContent(false)}
  </nav>

  <!-- Compact screens: top app bar -->
  <header
    class="sticky top-0 z-20 flex h-16 items-center gap-2 border-b border-line bg-surface px-2 lg:hidden"
  >
    <button
      type="button"
      class="grid size-12 place-items-center rounded-full text-2xl hover:bg-surface-muted"
      aria-label={t('nav.open_menu')}
      onclick={openDrawer}>☰</button
    >
    <span class="truncate text-xl font-medium">{title}</span>
  </header>

  <main class="mx-auto w-full max-w-5xl p-4 pb-28 lg:pb-8">
    {@render children()}
  </main>

  <!-- Compact screens: bottom navigation bar with the main destinations -->
  <nav
    class="fixed inset-x-0 bottom-0 z-20 border-t border-line bg-surface pb-[env(safe-area-inset-bottom)] lg:hidden"
    aria-label={t('nav.bottom')}
  >
    <ul class="mx-auto flex max-w-xl">
      {#each primary as item (item.href)}
        <li class="flex-1">
          <a
            href={item.href}
            aria-current={isActive(item.href) ? 'page' : undefined}
            class="group flex min-h-20 flex-col items-center justify-center gap-1 text-sm font-medium text-ink-muted aria-[current=page]:text-ink"
          >
            <span
              class="grid h-8 w-16 place-items-center rounded-full text-xl transition-colors group-aria-[current=page]:bg-brand/20 motion-reduce:transition-none"
              aria-hidden="true">{item.icon}</span
            >
            {item.label}
          </a>
        </li>
      {/each}
    </ul>
  </nav>
</div>

<!-- Compact screens: modal navigation drawer (focus trap + Esc from <dialog>) -->
<dialog
  bind:this={dialog}
  class="m-0 h-dvh max-h-dvh w-80 max-w-[85vw] bg-surface p-0 shadow-xl backdrop:bg-black/40 open:animate-[drawer-in_200ms_ease-out] motion-reduce:open:animate-none"
  aria-label={t('nav.drawer')}
  onclick={(e) => e.target === dialog && closeDrawer()}
>
  {@render drawerContent(true)}
</dialog>

<style>
  @keyframes -global-drawer-in {
    from {
      transform: translateX(-100%);
    }
    to {
      transform: translateX(0);
    }
  }
</style>
