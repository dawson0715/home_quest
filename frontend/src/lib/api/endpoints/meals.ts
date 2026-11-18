import { api } from '../client';
import type {
  Ingredient,
  MealEntry,
  MealPlan,
  MealSlot,
  MealSuggestions,
  MealTemplate,
  MealType,
  Recipe,
  RecipeInput,
  RecipeSummary,
  ShoppingCategory
} from '../types';

/** `token` is passed by the kids' device (child session); parents use the session. */
type Opts = { token?: string };

export async function listRecipes(
  params: { q?: string; tag?: string; favorite?: boolean } = {},
  opts: Opts = {}
): Promise<RecipeSummary[]> {
  const q = new URLSearchParams();
  if (params.q) q.set('q', params.q);
  if (params.tag) q.set('tag', params.tag);
  if (params.favorite) q.set('favorite', '1');
  const qs = q.toString();
  return (await api.get<RecipeSummary[]>(qs ? `recipes?${qs}` : 'recipes', opts)).data;
}

export async function getRecipe(id: number): Promise<Recipe> {
  return (await api.get<Recipe>(`recipes/${id}`)).data;
}

export async function createRecipe(input: RecipeInput): Promise<Recipe> {
  return (await api.post<Recipe>('recipes', input)).data;
}

export async function updateRecipe(id: number, input: RecipeInput): Promise<Recipe> {
  return (await api.patch<Recipe>(`recipes/${id}`, input)).data;
}

export async function deleteRecipe(id: number): Promise<void> {
  await api.delete(`recipes/${id}`);
}

export async function uploadRecipeImage(id: number, file: File): Promise<Recipe> {
  const form = new FormData();
  form.append('image', file);
  return (await api.post<Recipe>(`recipes/${id}/image`, form)).data;
}

export async function removeRecipeImage(id: number): Promise<Recipe> {
  return (await api.delete<Recipe>(`recipes/${id}/image`)).data;
}

export async function searchIngredients(q: string): Promise<Ingredient[]> {
  return (await api.get<Ingredient[]>(`ingredients?q=${encodeURIComponent(q)}`)).data;
}

export async function listShoppingCategories(): Promise<ShoppingCategory[]> {
  return (await api.get<ShoppingCategory[]>('shopping-categories')).data;
}

export async function getMealPlan(weekStart: string, opts: Opts = {}): Promise<MealPlan> {
  return (await api.get<MealPlan>(`meal-plans/${weekStart}`, opts)).data;
}

export async function updateMealPlanNotes(weekStart: string, notes: string): Promise<MealPlan> {
  return (await api.patch<MealPlan>(`meal-plans/${weekStart}`, { notes })).data;
}

export async function putMealSlot(
  weekStart: string,
  day: number,
  mealType: MealType,
  input: { servings?: number; entries: { id?: number; recipe_id?: number; free_text?: string }[] }
): Promise<MealSlot> {
  return (await api.put<MealSlot>(`meal-plans/${weekStart}/slots/${day}/${mealType}`, input)).data;
}

export async function copyMealPlan(
  weekStart: string,
  source: { source_week_start: string } | { template_id: number }
): Promise<MealPlan> {
  return (await api.post<MealPlan>(`meal-plans/${weekStart}/copy-from`, source)).data;
}

/** Preview of dishes for the empty slots (nothing is saved). */
export async function suggestMealPlan(weekStart: string, note = ''): Promise<MealSuggestions> {
  return (await api.post<MealSuggestions>(`meal-plans/${weekStart}/suggest`, { note })).data;
}

export async function saveMealTemplate(weekStart: string, name: string): Promise<{ id: number }> {
  return (await api.post<{ id: number }>(`meal-plans/${weekStart}/save-as-template`, { name }))
    .data;
}

export async function listMealTemplates(): Promise<MealTemplate[]> {
  return (await api.get<MealTemplate[]>('meal-templates')).data;
}

export async function deleteMealTemplate(id: number): Promise<void> {
  await api.delete(`meal-templates/${id}`);
}

export async function voteMealEntry(
  id: number,
  value: -1 | 0 | 1,
  opts: Opts = {}
): Promise<MealEntry> {
  return (await api.post<MealEntry>(`meal-entries/${id}/vote`, { value }, opts)).data;
}

export async function reviewMealEntry(
  id: number,
  decision: 'accept' | 'reject'
): Promise<MealEntry> {
  return (await api.post<MealEntry>(`meal-entries/${id}/${decision}`)).data;
}

export async function proposeDish(
  childToken: string,
  weekStart: string,
  input: { day: number; meal_type: MealType; recipe_id?: number; free_text?: string }
): Promise<MealEntry> {
  return (
    await api.post<MealEntry>(`meal-plans/${weekStart}/proposals`, input, { token: childToken })
  ).data;
}
