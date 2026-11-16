<script lang="ts">
  import { resolve } from '$app/paths';
  import { page } from '$app/state';
  import { t } from '#lib/i18n/index.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';
  import type { LayoutProps } from './$types';

  let { children }: LayoutProps = $props();

  const links = $derived([
    { href: resolve('/(child)/today'), icon: '✅', label: t('nav.child_today') },
    { href: resolve('/(child)/kid-menu'), icon: '🍽️', label: t('nav.child_menu') },
    ...(deviceSession.child?.can_check_shopping
      ? [{ href: resolve('/(child)/kid-shopping'), icon: '🛒', label: t('nav.child_shopping') }]
      : []),
    { href: resolve('/(child)/shop'), icon: '🎁', label: t('nav.child_rewards') },
    { href: resolve('/(child)/trophies'), icon: '🏆', label: t('nav.child_trophies') }
  ]);
  const showNav = $derived(
    deviceSession.isChildLoggedIn && !page.url.pathname.startsWith(resolve('/(child)/profiles'))
  );
</script>

<!-- Child UI: big targets, few words (docs/05-frontend.md) -->
<div class="min-h-dvh bg-surface-muted text-lg {showNav ? 'pb-24' : ''}">
  {@render children()}
</div>

{#if showNav}
  <nav
    class="fixed inset-x-0 bottom-0 z-40 border-t border-line bg-surface"
    aria-label={t('nav.child_nav')}
  >
    <ul class="mx-auto flex max-w-3xl justify-around">
      {#each links as link (link.href)}
        <li class="flex-1">
          <a
            href={link.href}
            class="flex min-h-20 flex-col items-center justify-center gap-1 text-base font-semibold aria-[current=page]:text-brand"
            aria-current={page.url.pathname === link.href ? 'page' : undefined}
          >
            <span class="text-3xl" aria-hidden="true">{link.icon}</span>
            {link.label}
          </a>
        </li>
      {/each}
    </ul>
  </nav>
{/if}
