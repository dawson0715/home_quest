import type { ApiErrorItem } from './types';

/** Error thrown by the API client for any non-2xx response or network failure. */
export class ApiError extends Error {
  constructor(
    readonly status: number,
    readonly errors: ApiErrorItem[]
  ) {
    super(errors[0]?.message ?? `HTTP ${status}`);
    this.name = 'ApiError';
  }

  /** Code of the first error (e.g. `not_found`, `validation_failed`). */
  get code(): string {
    return this.errors[0]?.code ?? 'unknown_error';
  }

  /** Validation errors grouped by field: `{ title: ['validation_failed'] }`. */
  get fieldErrors(): Record<string, string[]> {
    const out: Record<string, string[]> = {};
    for (const e of this.errors) {
      if (e.field) (out[e.field] ??= []).push(e.code);
    }
    return out;
  }
}

export const NETWORK_ERROR = 'network_error';
