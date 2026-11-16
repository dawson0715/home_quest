import type { RecurrenceType } from '../api/types';
import { t } from '../i18n';

/** Mon=1 … Sun=64 (backend RecurrenceRule). Index 0 = Monday. */
export const WEEKDAY_BITS = [1, 2, 4, 8, 16, 32, 64] as const;
export const WEEKDAYS_MON_FRI = 31;
export const WEEKEND = 96;
export const EVERY_DAY = 127;

export function hasDay(mask: number, index: number): boolean {
  return (mask & WEEKDAY_BITS[index]) !== 0;
}

export function toggleDay(mask: number, index: number): number {
  return mask ^ WEEKDAY_BITS[index];
}

interface RecurrenceLike {
  recurrence_type: RecurrenceType;
  recurrence_days: number | null;
  interval_days: number | null;
  due_date: string | null;
}

/** "Ogni giorno", "Lun, Mer, Ven", "Ogni 3 giorni", "Una volta (entro 20/11)". */
export function describeRecurrence(task: RecurrenceLike): string {
  switch (task.recurrence_type) {
    case 'daily':
      return t('recurrence.summary.daily');
    case 'weekly': {
      const mask = task.recurrence_days ?? 0;
      if (mask === EVERY_DAY) return t('recurrence.summary.daily');
      if (mask === WEEKDAYS_MON_FRI) return t('recurrence.summary.weekdays');
      if (mask === WEEKEND) return t('recurrence.summary.weekend');
      return WEEKDAY_BITS.map((_, i) => i)
        .filter((i) => hasDay(mask, i))
        .map((i) => t(`recurrence.weekday_short.${i}`))
        .join(', ');
    }
    case 'interval':
      return t('recurrence.summary.interval', { n: task.interval_days ?? 1 });
    case 'none':
      return task.due_date
        ? t('recurrence.summary.once_until', {
            date: task.due_date.split('-').reverse().slice(0, 2).join('/')
          })
        : t('recurrence.summary.once');
  }
}
