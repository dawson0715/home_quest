import { version } from '$app/env';
import { assets, immutable } from '$app/manifest';
import { self as sw } from '$app/service-worker';
import { t } from '#lib/i18n/index.ts';

const CACHE = `hq-${version}`;
// dotfiles are never served (Apache denies them): one failed request would abort the install
const ASSETS = [...immutable, ...assets].map((f) => f.path).filter((p) => !/\/\./.test(p));

sw.addEventListener('install', (event) => {
  event.waitUntil(caches.open(CACHE).then((cache) => cache.addAll(ASSETS)));
});

sw.addEventListener('activate', (event) => {
  event.waitUntil(
    caches
      .keys()
      .then((keys) => Promise.all(keys.filter((k) => k !== CACHE).map((k) => caches.delete(k))))
  );
});

sw.addEventListener('fetch', (event) => {
  const { request } = event;
  if (request.method !== 'GET') return;
  const url = new URL(request.url);
  if (url.origin !== sw.location.origin) return;

  // Shopping lists: network first, last copy when offline (the app's own queue handles writes)
  if (url.pathname.startsWith('/api/v1/shopping-lists')) {
    event.respondWith(
      fetch(request)
        .then(async (response) => {
          if (response.ok) (await caches.open(CACHE)).put(request, response.clone());
          return response;
        })
        .catch(async () => (await caches.match(request)) ?? Response.error())
    );
    return;
  }
  if (url.pathname.startsWith('/api/') || url.pathname.startsWith('/uploads/')) return; // network only

  // App shell: cached assets; navigations fall back to the SPA index when offline
  event.respondWith(
    (async () => {
      const cached = await caches.match(request);
      if (cached && ASSETS.includes(url.pathname)) return cached;
      try {
        return await fetch(request);
      } catch {
        if (request.mode === 'navigate') {
          return (
            (await caches.match('/index.html')) ?? (await caches.match('/')) ?? Response.error()
          );
        }
        return cached ?? Response.error();
      }
    })()
  );
});

interface PushPayload {
  type: string;
  params: Record<string, string | number>;
  url: string;
}

sw.addEventListener('push', (event) => {
  const payload = (event.data?.json() ?? null) as PushPayload | null;
  if (!payload) return;
  event.waitUntil(
    sw.registration.showNotification(t(`notifications.${payload.type}.title`, payload.params), {
      body: t(`notifications.${payload.type}.body`, payload.params),
      icon: '/app-icons/icon-any-192.png',
      badge: '/app-icons/icon-maskable-192.png',
      tag: payload.type,
      data: { url: payload.url }
    })
  );
});

sw.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const url = (event.notification.data as { url?: string } | null)?.url ?? '/';
  event.waitUntil(
    (async () => {
      const windows = await sw.clients.matchAll({ type: 'window', includeUncontrolled: true });
      const open = windows.find((w) => new URL(w.url).origin === sw.location.origin);
      if (open) {
        await open.focus();
        return open.navigate(url);
      }
      return sw.clients.openWindow(url);
    })()
  );
});
