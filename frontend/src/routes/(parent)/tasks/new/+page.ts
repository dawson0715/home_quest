import { listChildren } from '#lib/api/endpoints/children.ts';
import { listTaskTemplates } from '#lib/api/endpoints/tasks.ts';
import type { PageLoad } from './$types';

export const load: PageLoad = async ({ url }) => {
  const templateId = Number(url.searchParams.get('template'));
  const [children, templates] = await Promise.all([
    listChildren(),
    templateId ? listTaskTemplates() : Promise.resolve([])
  ]);
  const mealSlot = Number(url.searchParams.get('meal_slot')) || null;
  return {
    children,
    template: templates.find((tpl) => tpl.id === templateId) ?? null,
    mealSlot: mealSlot
      ? {
          id: mealSlot,
          date: url.searchParams.get('date') ?? '',
          title: url.searchParams.get('title') ?? ''
        }
      : null
  };
};
