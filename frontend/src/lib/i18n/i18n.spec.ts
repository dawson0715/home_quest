import { describe, expect, it } from 'vitest';
import { errorMessage, has, t } from './index';
import messages from './it.json';

// Mirrors backend app\components\ErrorCode: every code must have a translation.
const BACKEND_ERROR_CODES = [
  'bad_request',
  'unauthorized',
  'forbidden',
  'not_found',
  'method_not_allowed',
  'conflict',
  'unsupported_media_type',
  'rate_limited',
  'server_error',
  'validation_failed',
  'no_family',
  'insufficient_points',
  'invalid_transition',
  'checklist_incomplete',
  'reward_limit_reached',
  'reward_unavailable',
  'penalties_disabled',
  'pairing_code_invalid',
  'pin_invalid',
  'image_invalid'
];

describe('i18n', () => {
  it('translates nested keys', () => {
    expect(t('common.cancel')).toBe('Annulla');
  });

  it('interpolates params and keeps unknown placeholders', () => {
    expect(t('common.hello', { name: 'Luca' })).toBe('Ciao Luca!');
    expect(t('common.hello')).toBe('Ciao {name}!');
    expect(t('missing.key')).toBe('missing.key');
  });

  it('has a translation for every backend error code', () => {
    const missing = BACKEND_ERROR_CODES.filter((c) => !has(`errors.${c}`));
    expect(missing).toEqual([]);
    expect(Object.keys(messages.errors)).toEqual(expect.arrayContaining(BACKEND_ERROR_CODES));
  });

  it('falls back to a generic message for unknown codes', () => {
    expect(errorMessage('something_new')).toBe(t('errors.unknown_error'));
    expect(errorMessage('not_found')).toBe('Elemento non trovato');
  });
});

describe('task templates', () => {
  it('translates every template seeded by the backend', async () => {
    const { readFileSync } = await import('node:fs');
    const seed = readFileSync(
      new URL(
        '../../../../backend/migrations/m261116_150100_seed_task_templates.php',
        import.meta.url
      ),
      'utf8'
    );
    const keys = [...seed.matchAll(/\['([a-z_]+)', '[^']+', \d+/g)].map((m) => m[1]);
    expect(keys.length).toBeGreaterThan(30);
    expect(keys.filter((k) => !has(`task_templates.${k}`))).toEqual([]);
  });
});

describe('badges', () => {
  it('names every badge seeded by the backend', async () => {
    const { readFileSync } = await import('node:fs');
    const seed = readFileSync(
      new URL(
        '../../../../backend/migrations/m261116_160000_create_reward_and_badge_tables.php',
        import.meta.url
      ),
      'utf8'
    );
    const codes = [...seed.matchAll(/\['([a-z0-9_]+)', '[^']+', \['type'/g)].map((m) => m[1]);
    expect(codes.length).toBe(16);
    expect(
      codes.filter((c) => !has(`badges.${c}.name`) || !has(`badges.${c}.description`))
    ).toEqual([]);
  });
});
