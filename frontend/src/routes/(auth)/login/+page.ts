import { redirect } from '@sveltejs/kit';
import { resolve } from '$app/paths';
import { session } from '#lib/stores/session.svelte.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async () => {
  await session.ready();
  if (session.isAuthenticated) redirect(307, resolve('/(parent)/dashboard'));
};
