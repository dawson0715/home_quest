import { AVATARS, type Avatar } from '../api/types';

/** Avatar keys (backend ChildProfile::AVATARS) → emoji. */
export const AVATAR_EMOJI: Record<Avatar, string> = {
  fox: '🦊',
  bear: '🐻',
  cat: '🐱',
  dog: '🐶',
  owl: '🦉',
  panda: '🐼',
  lion: '🦁',
  rabbit: '🐰',
  unicorn: '🦄',
  dragon: '🐲',
  robot: '🤖',
  astronaut: '🧑‍🚀',
  dino: '🦕',
  penguin: '🐧',
  koala: '🐨',
  frog: '🐸'
};

/** Accessible, light-on-dark-text friendly palette for child colors. */
export const CHILD_COLORS = [
  '#E07400',
  '#C0392B',
  '#B03A7A',
  '#7D3CC0',
  '#2E5BBA',
  '#1F7A8C',
  '#1F7A4D',
  '#6B6B1F'
] as const;

export function avatarEmoji(avatar: string): string {
  return (AVATAR_EMOJI as Record<string, string>)[avatar] ?? '🙂';
}

export { AVATARS };
