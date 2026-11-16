import { redirect } from '@sveltejs/kit';
import { resolve } from '$app/paths';
import { session } from '#lib/stores/session.svelte.ts';
import type { LayoutLoad } from './$types';

/** Guard: parents only (admin|coparent). */
export const load: LayoutLoad = async () => {
  await session.ready();
  if (!session.isAuthenticated) redirect(307, resolve('/(auth)/login'));
};
