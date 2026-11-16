import { redirect } from '@sveltejs/kit';
import { resolve } from '$app/paths';
import { deviceSession } from '#lib/stores/device.svelte.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ url }) => {
  await deviceSession.ready();
  if (deviceSession.isPaired) redirect(307, resolve('/(child)/profiles'));
  return { code: url.searchParams.get('code') ?? '' };
};
