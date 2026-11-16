import { redirect } from '@sveltejs/kit';
import { resolve } from '$app/paths';
import { deviceSession } from '#lib/stores/device.svelte.ts';
import { session } from '#lib/stores/session.svelte.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async () => {
  await Promise.all([session.ready(), deviceSession.ready()]);
  if (session.isAuthenticated) redirect(307, resolve('/(parent)/dashboard'));
  if (deviceSession.isChildLoggedIn) redirect(307, resolve('/(child)/today'));
  if (deviceSession.isPaired) redirect(307, resolve('/(child)/profiles'));
  redirect(307, resolve('/(auth)/login'));
};
