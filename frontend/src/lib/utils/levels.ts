/** Mirrors backend PointsService: level L starts at 50·L·(L−1) XP. */
export function xpForLevel(level: number): number {
  return 50 * level * (level - 1);
}

/** 0–100: progress towards the next level. */
export function levelProgress(xpTotal: number, level: number): number {
  const start = xpForLevel(level);
  const next = xpForLevel(level + 1);
  return Math.max(0, Math.min(100, Math.round(((xpTotal - start) / (next - start)) * 100)));
}
