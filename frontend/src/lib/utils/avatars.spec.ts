import { describe, expect, it } from 'vitest';
import { AVATARS, AVATAR_EMOJI, avatarEmoji } from './avatars';
import messages from '../i18n/it.json';

describe('avatars', () => {
  it('has an emoji and an Italian name for every backend avatar', () => {
    for (const avatar of AVATARS) {
      expect(AVATAR_EMOJI[avatar]).toBeTruthy();
      expect((messages.avatar as Record<string, string>)[avatar]).toBeTruthy();
    }
  });

  it('falls back for unknown avatars', () => {
    expect(avatarEmoji('nope')).toBe('🙂');
  });
});
