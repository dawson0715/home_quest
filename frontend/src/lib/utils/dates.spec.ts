import { describe, expect, it } from 'vitest';
import { formatDateTime, formatTime, parseAppDate } from './dates';

describe('dates', () => {
  it('parses backend DATETIME as Rome time (winter and summer)', () => {
    expect(parseAppDate('2026-11-16 14:05:00').toISOString()).toBe('2026-11-16T13:05:00.000Z');
    expect(parseAppDate('2026-07-01 14:05:00').toISOString()).toBe('2026-07-01T12:05:00.000Z');
  });

  it('formats in Rome time', () => {
    expect(formatTime('2026-11-16T13:05:00Z')).toBe('14:05');
    expect(formatDateTime('2026-11-16 14:05:00')).toBe('16 nov, 14:05');
  });
});

describe('calendar days', () => {
  it('computes today in Rome around midnight UTC', async () => {
    const { todayInRome, formatDay } = await import('./dates');
    expect(todayInRome(new Date('2026-11-15T23:30:00Z'))).toBe('2026-11-16');
    expect(todayInRome(new Date('2026-11-16T22:59:00Z'))).toBe('2026-11-16');
    expect(formatDay('2026-11-16')).toBe('lunedì 16 novembre');
  });
});

describe('weeks', () => {
  it('finds the week start for Monday and Sunday weeks', async () => {
    const { weekStartOf, addDays, formatShortDay } = await import('./dates');
    expect(weekStartOf('2026-11-16')).toBe('2026-11-16'); // Monday
    expect(weekStartOf('2026-11-22')).toBe('2026-11-16'); // Sunday
    expect(weekStartOf('2026-11-22', 7)).toBe('2026-11-22');
    expect(weekStartOf('2026-11-18', 7)).toBe('2026-11-15');
    expect(addDays('2027-03-27', 2)).toBe('2027-03-29'); // across DST
    expect(formatShortDay('2026-11-16')).toBe('lun 16');
  });
});
