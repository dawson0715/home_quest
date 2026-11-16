/** All dates are shown in the app time zone (docs/02-architettura.md §8). */
export const APP_TIME_ZONE = 'Europe/Rome';

const timeFmt = new Intl.DateTimeFormat('it-IT', {
  timeZone: APP_TIME_ZONE,
  hour: '2-digit',
  minute: '2-digit'
});

const dateTimeFmt = new Intl.DateTimeFormat('it-IT', {
  timeZone: APP_TIME_ZONE,
  day: 'numeric',
  month: 'short',
  hour: '2-digit',
  minute: '2-digit'
});

/** "14:05" */
export function formatTime(iso: string): string {
  return timeFmt.format(new Date(iso));
}

/** "16 nov, 14:05". Accepts ISO strings or backend DATETIME ("2026-11-16 14:05:00", Rome time). */
export function formatDateTime(value: string): string {
  return dateTimeFmt.format(parseAppDate(value));
}

/** Parses ISO-8601 or a backend DATETIME in Europe/Rome. */
export function parseAppDate(value: string): Date {
  if (/[zZ]|[+-]\d{2}:\d{2}$/.test(value)) return new Date(value);
  const [d, t = '00:00:00'] = value.split(/[ T]/);
  const naive = new Date(`${d}T${t}Z`);
  // offset of Rome at that instant (CET/CEST)
  const local = new Date(naive.toLocaleString('en-US', { timeZone: APP_TIME_ZONE }));
  const utc = new Date(naive.toLocaleString('en-US', { timeZone: 'UTC' }));
  return new Date(naive.getTime() - (local.getTime() - utc.getTime()));
}

const isoDateFmt = new Intl.DateTimeFormat('en-CA', {
  timeZone: APP_TIME_ZONE,
  year: 'numeric',
  month: '2-digit',
  day: '2-digit'
});

/** Today's calendar date in Rome: "2026-11-16". */
export function todayInRome(now: Date = new Date()): string {
  return isoDateFmt.format(now);
}

const dayFmt = new Intl.DateTimeFormat('it-IT', {
  timeZone: 'UTC',
  weekday: 'long',
  day: 'numeric',
  month: 'long'
});

/** "lunedì 16 novembre" for a calendar date (Y-m-d). */
export function formatDay(date: string): string {
  return dayFmt.format(new Date(`${date}T00:00:00Z`));
}

function utcDate(date: string): Date {
  return new Date(`${date}T00:00:00Z`);
}

/** Calendar arithmetic on "Y-m-d" strings (UTC, DST-safe). */
export function addDays(date: string, days: number): string {
  const d = utcDate(date);
  d.setUTCDate(d.getUTCDate() + days);
  return d.toISOString().slice(0, 10);
}

/** First day of the week containing `date`: Monday (1) or Sunday (7). */
export function weekStartOf(date: string, startsOn: 1 | 7 = 1): string {
  const isoDay = ((utcDate(date).getUTCDay() + 6) % 7) + 1; // Mon=1 … Sun=7
  const offset = startsOn === 1 ? isoDay - 1 : isoDay % 7;
  return addDays(date, -offset);
}

const shortDayFmt = new Intl.DateTimeFormat('it-IT', {
  timeZone: 'UTC',
  weekday: 'short',
  day: 'numeric'
});

/** "lun 16" */
export function formatShortDay(date: string): string {
  return shortDayFmt.format(utcDate(date));
}
