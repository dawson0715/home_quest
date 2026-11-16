import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { toasts } from './toast.svelte';

describe('toasts', () => {
  beforeEach(() => vi.useFakeTimers());
  afterEach(() => {
    vi.useRealTimers();
    toasts.items = [];
  });

  it('adds and auto-dismisses a toast', () => {
    toasts.success('Salvato');
    expect(toasts.items).toHaveLength(1);
    expect(toasts.items[0]).toMatchObject({ kind: 'success', message: 'Salvato' });

    vi.advanceTimersByTime(4000);
    expect(toasts.items).toHaveLength(0);
  });

  it('keeps sticky toasts until dismissed', () => {
    const id = toasts.show('error', 'Errore', 0);
    vi.advanceTimersByTime(60_000);
    expect(toasts.items).toHaveLength(1);

    toasts.dismiss(id);
    expect(toasts.items).toHaveLength(0);
  });
});
