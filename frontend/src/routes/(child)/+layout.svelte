<script lang="ts">
  import { resolve } from '$app/paths';
  import { page } from '$app/state';
  import { t } from '#lib/i18n/index.ts';
  import OfflineBanner from '#lib/components/ui/OfflineBanner.svelte';
  import { connectivity } from '#lib/stores/connectivity.svelte.ts';
  import { deviceSession } from '#lib/stores/device.svelte.ts';
  import { warmOfflineCache } from '#lib/utils/offline.ts';
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

  // Menu (and shopping, if allowed) readable offline on the kids' device
  $effect(() => {
    const token = deviceSession.childToken;
    const child = deviceSession.child;
    if (!token || !child) return;
    const timer = setTimeout(() => {
      warmOfflineCache({
        session: token,
        token,
        weekStartsOn: deviceSession.family?.settings.week_starts_on ?? 1,
        shopping: child.can_check_shopping,
        parent: false
      });
    }, 1500);
    return () => clearTimeout(timer);
  });

  const showOffline = $derived(
    showNav && !page.url.pathname.startsWith(resolve('/(child)/kid-shopping'))
  );
</script>

<!-- Child UI: big targets, few words (docs/05-frontend.md) -->
<div class="min-h-dvh bg-surface-muted text-lg {showNav ? 'pb-24' : ''}">
  {#if showOffline && connectivity.offline}
    <div class="mx-auto max-w-3xl px-4 pt-4 sm:px-6"><OfflineBanner /></div>
  {/if}
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
            class="group flex min-h-20 flex-col items-center justify-center gap-1 text-base font-semibold text-ink-muted aria-[current=page]:text-ink"
            aria-current={page.url.pathname === link.href ? 'page' : undefined}
          >
            <span
              class="grid h-9 w-16 place-items-center rounded-full text-3xl group-aria-[current=page]:bg-brand/20"
              aria-hidden="true">{link.icon}</span
            >
            {link.label}
          </a>
        </li>
      {/each}
    </ul>
  </nav>
{/if}
