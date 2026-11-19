import { getMealPlan, listShoppingCategories } from '../api/endpoints/meals';
import { getShoppingList, listShoppingLists } from '../api/endpoints/shopping';
import { todayInRome, weekStartOf } from './dates';

const warmed = new Set<string>();

/**
 * Fetches this week's menu and the shopping lists once per app start, so they are
 * readable offline even if the user never opened them (the client keeps the copies).
 * Best effort: failures are ignored.
 */
export async function warmOfflineCache(opts: {
  /** session the copies belong to (once per session and app start) */
  session: string;
  /** explicit token (kids' device); parents use the session token */
  token?: string;
  weekStartsOn: 1 | 7;
  shopping: boolean;
  parent: boolean;
}): Promise<void> {
  if (warmed.has(opts.session)) return;
  warmed.add(opts.session);
  const auth = opts.token ? { token: opts.token } : {};
  const week = weekStartOf(todayInRome(), opts.weekStartsOn);
  await Promise.allSettled([
    getMealPlan(week, auth),
    opts.parent ? listShoppingCategories() : null,
    opts.shopping
      ? listShoppingLists(auth).then((lists) =>
          Promise.allSettled(lists.map((l) => getShoppingList(l.id, undefined, auth)))
        )
      : null
  ]);
}
