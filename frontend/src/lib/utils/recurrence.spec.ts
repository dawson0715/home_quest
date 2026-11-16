import { describe, expect, it } from 'vitest';
import { describeRecurrence, hasDay, toggleDay, WEEKDAYS_MON_FRI } from './recurrence';

const base = { recurrence_days: null, interval_days: null, due_date: null };

describe('recurrence', () => {
  it('toggles weekday bits', () => {
    let mask = 0;
    mask = toggleDay(mask, 0); // Mon
    mask = toggleDay(mask, 6); // Sun
    expect(mask).toBe(65);
    expect(hasDay(mask, 6)).toBe(true);
    expect(hasDay(mask, 1)).toBe(false);
    expect(toggleDay(mask, 0)).toBe(64);
  });

  it('describes recurrences in Italian', () => {
    expect(describeRecurrence({ ...base, recurrence_type: 'daily' })).toBe('Ogni giorno');
    expect(
      describeRecurrence({ ...base, recurrence_type: 'weekly', recurrence_days: WEEKDAYS_MON_FRI })
    ).toBe('Dal lunedì al venerdì');
    expect(
      describeRecurrence({ ...base, recurrence_type: 'weekly', recurrence_days: 1 | 4 | 16 })
    ).toBe('Lun, Mer, Ven');
    expect(describeRecurrence({ ...base, recurrence_type: 'interval', interval_days: 3 })).toBe(
      'Ogni 3 giorni'
    );
    expect(describeRecurrence({ ...base, recurrence_type: 'none', due_date: '2026-11-20' })).toBe(
      'Una volta, entro il 20/11'
    );
  });
});
