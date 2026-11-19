/**
 * Whether the API is reachable, as seen by the last request (navigator.onLine lies
 * on captive portals and flaky mobile links). `cachedAt` is the age of the copy shown.
 */
class Connectivity {
  offline = $state(false);
  /** unix ms of the oldest cached response shown since going offline */
  cachedAt = $state<number | null>(null);

  constructor() {
    // the browser knows it lost the network: no need to wait for a failed request
    globalThis.addEventListener?.('offline', () => this.markOffline());
  }

  markOnline(): void {
    this.offline = false;
    this.cachedAt = null;
  }

  markOffline(cachedAt?: number): void {
    this.offline = true;
    if (cachedAt !== undefined) this.cachedAt = Math.min(this.cachedAt ?? cachedAt, cachedAt);
  }
}

export const connectivity = new Connectivity();
