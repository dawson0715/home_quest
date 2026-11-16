import { redirect } from '@sveltejs/kit';
import { resolve } from '$app/paths';
import { deviceSession } from '#lib/stores/device.svelte.ts';
import { todayInRome, weekStartOf } from '#lib/utils/dates.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ parent }) => {
  await parent();
  if (!deviceSession.isChildLoggedIn) redirect(307, resolve('/(child)/profiles'));
  const startsOn = deviceSession.family?.settings.week_starts_on ?? 1;
  return { week: weekStartOf(todayInRome(), startsOn), today: todayInRome() };
};
