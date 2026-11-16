import { redirect } from '@sveltejs/kit';
import { resolve } from '$app/paths';
import { deviceSession } from '#lib/stores/device.svelte.ts';
import type { LayoutLoad } from './$types';

/** Guard: only paired devices (child pages additionally require a child session). */
export const load: LayoutLoad = async () => {
  await deviceSession.ready();
  if (!deviceSession.isPaired) redirect(307, resolve('/(auth)/device'));
};
