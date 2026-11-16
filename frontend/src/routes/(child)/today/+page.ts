import { redirect } from '@sveltejs/kit';
import { resolve } from '$app/paths';
import { deviceSession } from '#lib/stores/device.svelte.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ parent }) => {
  await parent();
  if (!deviceSession.isChildLoggedIn) redirect(307, resolve('/(child)/profiles'));
};
