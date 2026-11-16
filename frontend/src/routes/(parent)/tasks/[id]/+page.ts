import { error } from '@sveltejs/kit';
import { listPlayers } from '#lib/api/endpoints/children.ts';
import { getTask } from '#lib/api/endpoints/tasks.ts';
import { ApiError } from '#lib/api/errors.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params }) => {
  try {
    const [task, children] = await Promise.all([getTask(Number(params.id)), listPlayers()]);
    return { task, children };
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) error(404, 'not_found');
    throw e;
  }
};
