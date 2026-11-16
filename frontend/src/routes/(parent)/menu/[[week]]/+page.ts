import { redirect } from '@sveltejs/kit';
import { resolve } from '$app/paths';
import { session } from '#lib/stores/session.svelte.ts';
import { todayInRome, weekStartOf } from '#lib/utils/dates.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params, parent }) => {
  await parent();
  const startsOn = session.me?.family?.settings.week_starts_on ?? 1;
  const week = params.week ?? weekStartOf(todayInRome(), startsOn);
  const normalized = /^\d{4}-\d{2}-\d{2}$/.test(week) ? weekStartOf(week, startsOn) : null;
  if (normalized === null || normalized !== week) {
    redirect(
      307,
      resolve('/(parent)/menu/[[week]]', {
        week: normalized ?? weekStartOf(todayInRome(), startsOn)
      })
    );
  }
  return { week, startsOn };
};
