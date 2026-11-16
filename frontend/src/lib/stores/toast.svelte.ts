export type ToastKind = 'success' | 'error' | 'info';

export interface Toast {
  id: number;
  kind: ToastKind;
  /** Already translated text. */
  message: string;
}

const DEFAULT_TIMEOUT_MS = 4000;

class ToastStore {
  items = $state<Toast[]>([]);
  #nextId = 1;

  show(kind: ToastKind, message: string, timeoutMs = DEFAULT_TIMEOUT_MS): number {
    const id = this.#nextId++;
    this.items.push({ id, kind, message });
    if (timeoutMs > 0) setTimeout(() => this.dismiss(id), timeoutMs);
    return id;
  }

  success(message: string): number {
    return this.show('success', message);
  }

  error(message: string): number {
    return this.show('error', message);
  }

  info(message: string): number {
    return this.show('info', message);
  }

  dismiss(id: number): void {
    this.items = this.items.filter((toast) => toast.id !== id);
  }
}

export const toasts = new ToastStore();
