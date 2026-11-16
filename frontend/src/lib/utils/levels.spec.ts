import { describe, expect, it } from 'vitest';
import { levelProgress, xpForLevel } from './levels';

describe('levels', () => {
  it('matches the backend curve', () => {
    expect([1, 2, 3, 4].map(xpForLevel)).toEqual([0, 100, 300, 600]);
  });

  it('computes progress within the level', () => {
    expect(levelProgress(0, 1)).toBe(0);
    expect(levelProgress(50, 1)).toBe(50);
    expect(levelProgress(200, 2)).toBe(50);
    expect(levelProgress(9999, 2)).toBe(100);
  });
});
