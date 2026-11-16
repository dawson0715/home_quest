import { error } from '@sveltejs/kit';
import { getChild } from '#lib/api/endpoints/children.ts';
import { ApiError } from '#lib/api/errors.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ params }) => {
  try {
    return { child: await getChild(Number(params.id)) };
  } catch (e) {
    if (e instanceof ApiError && e.status === 404) error(404, 'not_found');
    throw e;
  }
};
