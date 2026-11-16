import { api, request } from '../api/client';

export type PushState = 'unsupported' | 'unavailable' | 'denied' | 'off' | 'on';

function base64UrlToBytes(value: string): Uint8Array<ArrayBuffer> {
  const padded = (value + '='.repeat((4 - (value.length % 4)) % 4))
    .replace(/-/g, '+')
    .replace(/_/g, '/');
  const raw = atob(padded);
  const bytes = new Uint8Array(new ArrayBuffer(raw.length));
  for (let i = 0; i < raw.length; i++) bytes[i] = raw.charCodeAt(i);
  return bytes;
}

function supported(): boolean {
  return (
    typeof navigator !== 'undefined' && 'serviceWorker' in navigator && 'PushManager' in window
  );
}

async function registration(): Promise<ServiceWorkerRegistration | null> {
  return supported() ? ((await navigator.serviceWorker.getRegistration()) ?? null) : null;
}

export async function pushState(): Promise<PushState> {
  if (!supported()) return 'unsupported';
  if (Notification.permission === 'denied') return 'denied';
  const reg = await registration();
  return (await reg?.pushManager.getSubscription()) ? 'on' : 'off';
}

/**
 * Asks permission (only call from a user gesture) and registers this browser.
 * `token`: the child session token on kids' devices.
 */
export async function enablePush(token?: string): Promise<PushState> {
  if (!supported()) return 'unsupported';
  const key = (await api.get<{ public_key: string | null }>('push/vapid-key', { token: null })).data
    .public_key;
  if (!key) return 'unavailable';
  if ((await Notification.requestPermission()) !== 'granted') return 'denied';
  const reg = (await registration()) ?? (await navigator.serviceWorker.ready);
  const sub =
    (await reg.pushManager.getSubscription()) ??
    (await reg.pushManager.subscribe({
      userVisibleOnly: true,
      applicationServerKey: base64UrlToBytes(key)
    }));
  const json = sub.toJSON();
  await api.post(
    'push/subscribe',
    { endpoint: json.endpoint, keys: json.keys },
    token ? { token } : {}
  );
  return 'on';
}

export async function disablePush(token?: string): Promise<PushState> {
  const reg = await registration();
  const sub = await reg?.pushManager.getSubscription();
  if (sub) {
    await request(
      'DELETE',
      'push/subscribe',
      { endpoint: sub.endpoint },
      token ? { token } : {}
    ).catch(() => undefined);
    await sub.unsubscribe();
  }
  return 'off';
}
