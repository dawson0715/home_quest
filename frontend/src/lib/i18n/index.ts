import it from './it.json';

/**
 * Minimal i18n: the app is Italian-only (docs/05-frontend.md). Keys are
 * dot-separated paths into it.json; `{name}` placeholders are interpolated.
 */
type Messages = { [key: string]: string | Messages };

const messages: Messages = it;

export type Params = Record<string, string | number>;

function lookup(key: string): string | undefined {
  let node: string | Messages | undefined = messages;
  for (const part of key.split('.')) {
    if (typeof node !== 'object' || node === null) return undefined;
    node = node[part];
  }
  return typeof node === 'string' ? node : undefined;
}

export function has(key: string): boolean {
  return lookup(key) !== undefined;
}

/** Translates `key`; returns the key itself when missing (visible in dev, never crashes). */
export function t(key: string, params?: Params): string {
  const msg = lookup(key);
  if (msg === undefined) {
    if (import.meta.env.DEV) console.warn(`[i18n] missing key: ${key}`);
    return key;
  }
  return params ? msg.replace(/\{(\w+)\}/g, (m, p: string) => String(params[p] ?? m)) : msg;
}

/** User-facing message for an API error code (meta.errors[].code). */
export function errorMessage(code: string): string {
  return t(has(`errors.${code}`) ? `errors.${code}` : 'errors.unknown_error');
}
