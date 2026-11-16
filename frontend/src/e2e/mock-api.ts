import type { Page, Route } from '@playwright/test';

/**
 * Tiny in-memory fake of the HomeQuest API for e2e runs (no backend in CI).
 * Mirrors the contract in docs/04-api.md for the endpoints the UI uses.
 */
export interface FakeChild {
  id: number;
  name: string;
  avatar: string;
  color: string;
  birth_year_month: string | null;
  pin: string | null;
  can_check_shopping: boolean;
  archived: boolean;
}

export const PARENT_TOKEN = 'good-token';
export const TODAY = '2026-11-16';
/** game profile of the parent (Anna) */
export const ADULT_ID = 2000;

interface FakeTask {
  id: number;
  title: string;
  icon: string;
  points: number;
  requires_approval: boolean;
  is_open?: boolean;
  recurrence_type: string;
  recurrence_days: number | null;
  interval_days: number | null;
  time_of_day: string;
  due_date: string | null;
  start_date: string;
  end_date: string | null;
  is_active: boolean;
  assignee_ids: number[];
  checklist: { id: number; label: string; icon: string | null }[];
}

export interface FakeReward {
  id: number;
  title: string;
  icon: string;
  cost: number;
  limit_per_period: number | null;
  limit_period: string | null;
  stock: number | null;
  is_active: boolean;
  sort_order: number;
}

interface FakeRedemption {
  id: number;
  reward_id: number;
  child_id: number;
  status: string;
  note: string | null;
}

interface FakeTx {
  id: number;
  child_id: number;
  points: number;
  type: string;
  note: string | null;
}

export interface FakeDish {
  id: number;
  day: number;
  meal: string;
  recipe_id: number | null;
  free_text: string | null;
  status: string;
  proposed_by: number | null;
  votes: Map<string, number>;
}

interface FakeOccurrence {
  id: number;
  task_id: number;
  child_id: number;
  status: string;
  done: number[];
  review_note: string | null;
  points_awarded: number | null;
}

export function createFakeApi() {
  const family = {
    id: 1,
    name: 'Rossi',
    settings: {
      allow_penalties: false,
      weekly_email: false,
      meal_types: ['breakfast', 'lunch', 'snack', 'dinner'],
      week_starts_on: 1,
      shopping_poll_seconds: 5
    } as Record<string, unknown>
  };
  const children: FakeChild[] = [];
  const devices: { id: number; name: string; token: string; revoked: boolean }[] = [];
  const tasks: FakeTask[] = [];
  const occurrences: FakeOccurrence[] = [];
  const points = new Map<number, number>();
  const rewards: FakeReward[] = [];
  const redemptions: FakeRedemption[] = [];
  const ledger: FakeTx[] = [];
  const goals = new Map<number, number>();
  const shop = {
    version: 1,
    items: [] as {
      id: number;
      label: string;
      quantity: number | null;
      unit: string | null;
      category_id: number;
      source: string;
      checked: boolean;
      client_uuid: string | null;
      ingredient_id: number | null;
    }[]
  };
  const categories = [
    { id: 1, code: 'frutta_verdura', name: null, icon: '🥦', sort_order: 0 },
    { id: 3, code: 'latticini', name: null, icon: '🧀', sort_order: 2 },
    { id: 9, code: 'altro', name: null, icon: '🛒', sort_order: 8 }
  ];
  const listJson = () => ({
    id: 1,
    name: 'Spesa',
    is_default: true,
    version: shop.version,
    linked_week_id: null,
    pantry: [],
    items: shop.items.map((i) => ({ ...i, checked_at: i.checked ? '2026-11-16 10:00:00' : null }))
  });
  const shopAdd = (data: {
    label?: string;
    client_uuid?: string;
    category_id?: number;
    quantity?: number | null;
    unit?: string | null;
    ingredient_id?: number;
  }) => {
    if (data.client_uuid && shop.items.some((i) => i.client_uuid === data.client_uuid)) return;
    const label = data.label ?? '';
    shop.items.push({
      id: nextId++,
      label,
      quantity: data.quantity ?? null,
      unit: data.unit ?? null,
      category_id: data.category_id ?? (label === 'Latte' ? 3 : label === 'Mele' ? 1 : 9),
      source: 'manual',
      checked: false,
      client_uuid: data.client_uuid ?? null,
      ingredient_id: data.ingredient_id ?? null
    });
    shop.version++;
  };
  const shopOp = (op: { op: string; item_id?: number; client_uuid?: string; data?: object }) => {
    if (op.op === 'add') return shopAdd({ ...(op.data ?? {}), client_uuid: op.client_uuid });
    const item = shop.items.find(
      (i) =>
        (op.item_id && i.id === op.item_id) || (op.client_uuid && i.client_uuid === op.client_uuid)
    );
    if (!item) return;
    if (op.op === 'delete') shop.items.splice(shop.items.indexOf(item), 1);
    else item.checked = op.op === 'check';
    shop.version++;
  };
  const shopRoutes = (
    route: Route,
    path: string,
    method: string,
    body: Record<string, unknown>,
    ifNoneMatch: string | undefined,
    kid: boolean
  ) => {
    if (path === 'shopping-lists' && method === 'GET') {
      return ok(route, [
        {
          id: 1,
          name: 'Spesa',
          is_default: true,
          version: shop.version,
          items_count: shop.items.length,
          open_count: shop.items.filter((i) => !i.checked).length
        }
      ]);
    }
    if (path === 'shopping-lists/1' && method === 'GET') {
      if (ifNoneMatch === `"v${shop.version}"`) return route.fulfill({ status: 304 });
      return ok(route, listJson());
    }
    if (path === 'shopping-lists/1/sync' && method === 'POST') {
      for (const op of body.ops as { op: string }[]) {
        if (kid && !['check', 'uncheck'].includes(op.op)) continue;
        shopOp(op);
      }
      return ok(route, listJson());
    }
    if (kid) return null;
    if (path === 'shopping-lists/1/generate') {
      shop.items.push({
        id: nextId++,
        label: 'Uova',
        quantity: 6,
        unit: 'pz',
        category_id: 3,
        source: 'generated',
        checked: false,
        client_uuid: null,
        ingredient_id: 77
      });
      shop.version++;
      return route.fulfill({
        json: { data: listJson(), meta: { added: 1, updated: 0, removed: 0 } }
      });
    }
    if (path === 'shopping-lists/1/clear-checked') {
      shop.items = shop.items.filter((i) => !i.checked);
      shop.version++;
      return ok(route, listJson());
    }
    if (path === 'shopping-categories' && method === 'GET') return ok(route, categories);
    if (path === 'shopping-categories/order') return ok(route, { ids: body.ids });
    if (path === 'favorite-items')
      return ok(route, [
        { id: 1, label: 'Latte', ingredient_id: null, category_id: 3, use_count: 5 }
      ]);
    return undefined;
  };
  const recipes: {
    id: number;
    title: string;
    tags: string[];
    is_favorite: boolean;
    servings: number;
    ingredients: unknown[];
  }[] = [];
  const dishes: FakeDish[] = [];
  const recipeSummary = (r: (typeof recipes)[number]) => ({
    id: r.id,
    title: r.title,
    image_url: null,
    tags: r.tags,
    is_favorite: r.is_favorite,
    prep_minutes: null
  });
  const dishJson = (d: FakeDish, voter: string) => {
    const recipe = recipes.find((r) => r.id === d.recipe_id);
    const proposer = children.find((c) => c.id === d.proposed_by);
    const values = [...d.votes.values()];
    return {
      id: d.id,
      recipe_id: d.recipe_id,
      recipe: recipe ? recipeSummary(recipe) : null,
      free_text: d.free_text,
      title: recipe?.title ?? d.free_text,
      status: d.status,
      proposed_by: proposer
        ? {
            id: proposer.id,
            name: proposer.name,
            avatar: proposer.avatar,
            color: proposer.color,
            has_pin: false
          }
        : null,
      votes: {
        up: values.filter((v) => v > 0).length,
        down: values.filter((v) => v < 0).length,
        mine: d.votes.get(voter) ?? 0
      }
    };
  };
  const planJson = (week: string, voter: string) => {
    const meals = ['breakfast', 'lunch', 'snack', 'dinner'];
    const slots = [];
    for (let day = 0; day < 7; day++) {
      const date = new Date(`${week}T00:00:00Z`);
      date.setUTCDate(date.getUTCDate() + day);
      for (const meal of meals) {
        const entries = dishes.filter(
          (d) => d.day === day && d.meal === meal && d.status !== 'rejected'
        );
        slots.push({
          id: entries.length ? day * 10 + meals.indexOf(meal) + 1 : null,
          day_index: day,
          date: date.toISOString().slice(0, 10),
          meal_type: meal,
          servings: 4,
          entries: entries.map((d) => dishJson(d, voter))
        });
      }
    }
    return { id: 1, week_start: week, notes: null, meal_types: meals, slots };
  };
  const earned = new Map<number, string[]>();
  const credit = (childId: number, amount: number, type: string, note: string | null = null) => {
    points.set(childId, (points.get(childId) ?? 0) + amount);
    ledger.unshift({ id: nextId++, child_id: childId, points: amount, type, note });
  };
  const redemptionJson = (r: FakeRedemption) => {
    const reward = rewards.find((x) => x.id === r.reward_id) as FakeReward;
    const child = children.find((c) => c.id === r.child_id) as FakeChild;
    return {
      id: r.id,
      reward_id: r.reward_id,
      child_id: r.child_id,
      title: reward.title,
      icon: reward.icon,
      cost: reward.cost,
      status: r.status,
      requested_at: '2026-11-16 10:00:00',
      reviewed_at: null,
      note: r.note,
      child: {
        id: child.id,
        name: child.name,
        avatar: child.avatar,
        color: child.color,
        has_pin: false
      }
    };
  };
  let pairingCode: string | null = null;
  let nextId = 100;

  const childJson = (c: FakeChild) => ({
    id: c.id,
    name: c.name,
    avatar: c.avatar,
    color: c.color,
    birth_year_month: c.birth_year_month,
    has_pin: c.pin !== null,
    points_balance: points.get(c.id) ?? 0,
    xp_total: points.get(c.id) ?? 0,
    level: 1,
    current_streak: 0,
    best_streak: 0,
    can_check_shopping: c.can_check_shopping,
    sort_order: 0,
    archived: c.archived,
    is_adult: c.id === ADULT_ID
  });

  const taskJson = (task: FakeTask) => ({
    ...task,
    is_open: !!task.is_open,
    description: null,
    xp: task.points
  });
  const adult: FakeChild = {
    id: ADULT_ID,
    name: 'Anna',
    avatar: 'owl',
    color: '#6B6B1F',
    birth_year_month: null,
    pin: null,
    can_check_shopping: false,
    archived: false
  };
  const occJson = (o: FakeOccurrence) => {
    const task = tasks.find((x) => x.id === o.task_id) as FakeTask;
    const child = (
      o.child_id === ADULT_ID ? adult : children.find((c) => c.id === o.child_id)
    ) as FakeChild;
    return {
      id: o.id,
      task_id: o.task_id,
      child_id: o.child_id,
      due_date: TODAY,
      status: o.status,
      title: task.title,
      icon: task.icon,
      points: task.points,
      time_of_day: task.time_of_day,
      requires_approval: task.requires_approval,
      is_open: !!task.is_open,
      checklist: task.checklist.map((i) => ({ ...i, done: o.done.includes(i.id) })),
      submitted_at: null,
      reviewed_at: null,
      review_note: o.review_note,
      points_awarded: o.points_awarded,
      child: {
        id: child.id,
        name: child.name,
        avatar: child.avatar,
        color: child.color,
        has_pin: false
      }
    };
  };
  const generate = (task: FakeTask) => {
    for (const childId of task.assignee_ids) {
      if (!occurrences.some((o) => o.task_id === task.id && o.child_id === childId)) {
        occurrences.push({
          id: nextId++,
          task_id: task.id,
          child_id: childId,
          status: 'pending',
          done: [],
          review_note: null,
          points_awarded: null
        });
      }
    }
  };
  const approve = (o: FakeOccurrence) => {
    const task = tasks.find((x) => x.id === o.task_id) as FakeTask;
    o.status = 'approved';
    o.points_awarded = task.points;
    const before = points.get(o.child_id) ?? 0;
    credit(o.child_id, task.points, 'task');
    const firstBadge = !(earned.get(o.child_id) ?? []).includes('first_task');
    if (firstBadge) earned.set(o.child_id, [...(earned.get(o.child_id) ?? []), 'first_task']);
    return {
      occurrence: occJson(o),
      points_awarded: task.points,
      level_up: before < 100 && before + task.points >= 100,
      balance: before + task.points,
      new_badges: firstBadge ? [{ code: 'first_task', icon: '🌱' }] : []
    };
  };

  const ok = (route: Route, data: unknown, status = 200) =>
    route.fulfill({ status, json: { data, meta: {} } });
  const fail = (route: Route, status: number, code: string, field?: string) =>
    route.fulfill({
      status,
      json: { data: null, meta: { errors: [{ code, message: code, ...(field ? { field } : {}) }] } }
    });

  const net = { offline: false };

  async function handle(route: Route) {
    if (net.offline) return route.abort('internetdisconnected');
    const req = route.request();
    const url = new URL(req.url());
    const path = url.pathname.replace(/^\/api\/v1\//, '');
    const method = req.method();
    const token = (req.headers()['authorization'] ?? '').replace(/^Bearer /, '');
    const body = req.postData() ? JSON.parse(req.postData() as string) : {};
    const ifNoneMatch = req.headers()['if-none-match'];
    const device = devices.find((d) => d.token === token && !d.revoked);
    const childSession = token.startsWith('c_')
      ? children.find((c) => `c_${c.id}` === token && !c.archived)
      : undefined;
    const isParent = token === PARENT_TOKEN;

    if (path === 'push/vapid-key') return ok(route, { public_key: null });

    if (path === 'devices/pair' && method === 'POST') {
      const code = String(body.code ?? '')
        .replace(/[\s-]/g, '')
        .toUpperCase();
      if (!pairingCode || code !== pairingCode)
        return fail(route, 422, 'pairing_code_invalid', 'code');
      pairingCode = null;
      const d = { id: nextId++, name: body.device_name, token: `d_${nextId}`, revoked: false };
      devices.push(d);
      return ok(route, { device_token: d.token, device: { id: d.id, name: d.name } }, 201);
    }

    if (path === 'me' && method === 'GET') {
      if (isParent) {
        return ok(route, {
          user: { id: 2, username: 'anna', name: 'Anna' },
          family,
          role: 'admin',
          player: childJson(adult)
        });
      }
      if (device)
        return ok(route, { device: { id: device.id, name: device.name }, family, role: 'device' });
      if (childSession) return ok(route, { child: childJson(childSession), family, role: 'child' });
      return fail(route, 401, 'unauthorized');
    }

    if (childSession) {
      if (path === 'me/today') {
        const groups: Record<string, unknown[]> = {
          morning: [],
          afternoon: [],
          evening: [],
          any: []
        };
        for (const o of occurrences.filter(
          (x) => x.child_id === childSession.id && x.status !== 'taken'
        )) {
          const task = tasks.find((x) => x.id === o.task_id) as FakeTask;
          groups[task.time_of_day].push(occJson(o));
        }
        const xp = points.get(childSession.id) ?? 0;
        return ok(route, {
          date: TODAY,
          child: {
            ...childJson(childSession),
            level: xp >= 100 ? 2 : 1,
            xp_level_start: xp >= 100 ? 100 : 0,
            xp_next_level: xp >= 100 ? 300 : 100
          },
          groups,
          savings_goal: null
        });
      }
      const occMatch = path.match(/^occurrences\/(\d+)\/(checklist|submit)$/);
      const o = occMatch
        ? occurrences.find((x) => x.id === Number(occMatch[1]) && x.child_id === childSession.id)
        : undefined;
      if (occMatch && !o) return fail(route, 404, 'not_found');
      if (o && occMatch?.[2] === 'checklist') {
        o.done = body.done
          ? [...new Set([...o.done, body.item_id])]
          : o.done.filter((id) => id !== body.item_id);
        return ok(route, occJson(o));
      }
      if (o && occMatch?.[2] === 'submit') {
        const task = tasks.find((x) => x.id === o.task_id) as FakeTask;
        if (o.done.length < task.checklist.length) return fail(route, 422, 'checklist_incomplete');
        if (task.is_open) {
          for (const sibling of occurrences.filter(
            (x) => x.task_id === o.task_id && x !== o && x.status === 'pending'
          )) {
            sibling.status = 'taken';
          }
        }
        if (task.requires_approval) {
          o.status = 'submitted';
          return ok(route, {
            occurrence: occJson(o),
            points_awarded: 0,
            level_up: false,
            balance: null,
            new_badges: []
          });
        }
        return ok(route, approve(o));
      }
      if (path.startsWith('shopping')) {
        if (!childSession.can_check_shopping) return fail(route, 403, 'forbidden');
        const handled = shopRoutes(route, path, method, body, ifNoneMatch, true);
        if (handled) return handled;
        return fail(route, 403, 'forbidden');
      }
      const childPlan = path.match(/^meal-plans\/(\d{4}-\d{2}-\d{2})(\/proposals)?$/);
      if (childPlan && method === 'GET')
        return ok(route, planJson(childPlan[1], `child:${childSession.id}`));
      if (childPlan?.[2] && method === 'POST') {
        if (
          dishes.some(
            (d) =>
              d.day === body.day &&
              d.meal === body.meal_type &&
              d.proposed_by === childSession.id &&
              d.status === 'proposed'
          )
        ) {
          return fail(route, 409, 'conflict');
        }
        const d: FakeDish = {
          id: nextId++,
          day: body.day,
          meal: body.meal_type,
          recipe_id: body.recipe_id ?? null,
          free_text: body.free_text ?? null,
          status: 'proposed',
          proposed_by: childSession.id,
          votes: new Map()
        };
        dishes.push(d);
        return ok(route, dishJson(d, `child:${childSession.id}`), 201);
      }
      const childVote = path.match(/^meal-entries\/(\d+)\/vote$/);
      if (childVote) {
        const d = dishes.find((x) => x.id === Number(childVote[1]));
        if (!d) return fail(route, 404, 'not_found');
        if (body.value === 0) d.votes.delete(`child:${childSession.id}`);
        else d.votes.set(`child:${childSession.id}`, body.value);
        return ok(route, dishJson(d, `child:${childSession.id}`));
      }
      if (path === 'recipes' && method === 'GET') return ok(route, recipes.map(recipeSummary));
      if (path === 'rewards' && method === 'GET') {
        const balance = points.get(childSession.id) ?? 0;
        const reserved = redemptions
          .filter((r) => r.child_id === childSession.id && r.status === 'requested')
          .reduce((n, r) => n + (rewards.find((x) => x.id === r.reward_id)?.cost ?? 0), 0);
        return ok(
          route,
          rewards
            .filter((r) => r.is_active)
            .map((r) => ({
              ...r,
              affordable: balance - reserved >= r.cost,
              available: r.stock === null || r.stock > 0,
              is_goal: goals.get(childSession.id) === r.id
            }))
        );
      }
      const redeemMatch = path.match(/^rewards\/(\d+)\/redeem$/);
      if (redeemMatch && method === 'POST') {
        const reward = rewards.find((r) => r.id === Number(redeemMatch[1]));
        if (!reward) return fail(route, 404, 'not_found');
        if ((points.get(childSession.id) ?? 0) < reward.cost)
          return fail(route, 422, 'insufficient_points');
        const r = {
          id: nextId++,
          reward_id: reward.id,
          child_id: childSession.id,
          status: 'requested',
          note: null
        };
        redemptions.push(r);
        return ok(route, redemptionJson(r), 201);
      }
      if (path === 'redemptions' && method === 'GET') {
        return ok(
          route,
          redemptions.filter((r) => r.child_id === childSession.id).map(redemptionJson)
        );
      }
      if (path === 'me/savings-goal' && method === 'PUT') {
        if (body.reward_id === null) goals.delete(childSession.id);
        else goals.set(childSession.id, body.reward_id);
        return ok(route, { savings_goal: null });
      }
      if (path === 'badges' && method === 'GET') {
        const mine = earned.get(childSession.id) ?? [];
        return ok(route, [
          {
            code: 'first_task',
            icon: '🌱',
            earned_at: mine.includes('first_task') ? '2026-11-16 10:00:00' : null
          },
          { code: 'tasks_10', icon: '⭐', earned_at: null }
        ]);
      }
      return fail(route, 403, 'forbidden');
    }

    if (device) {
      if (path === 'devices/profiles') {
        return ok(
          route,
          children
            .filter((c) => !c.archived)
            .map((c) => ({
              id: c.id,
              name: c.name,
              avatar: c.avatar,
              color: c.color,
              has_pin: c.pin !== null
            }))
        );
      }
      if (path === 'devices/select-child' && method === 'POST') {
        const c = children.find((x) => x.id === body.child_id && !x.archived);
        if (!c) return fail(route, 404, 'not_found');
        if (c.pin !== null && body.pin !== c.pin) return fail(route, 422, 'pin_invalid', 'pin');
        return ok(route, { child_token: `c_${c.id}`, child: childJson(c) }, 201);
      }
      return fail(route, 403, 'forbidden');
    }

    if (!isParent) return fail(route, 401, 'unauthorized');

    if (path === 'family' && method === 'PATCH') {
      if (body.name !== undefined) family.name = body.name;
      if (body.settings) Object.assign(family.settings, body.settings);
      return ok(route, family);
    }
    if (path === 'family/export') return ok(route, { family, children });
    if (path === 'family' && method === 'DELETE') {
      if (body.confirm_name !== family.name)
        return fail(route, 422, 'validation_failed', 'confirm_name');
      family.name = '__deleted__';
      return route.fulfill({ status: 204 });
    }
    if (path === 'family/members')
      return ok(route, [{ id: 1, user_id: 2, username: 'anna', name: 'Anna', role: 'admin' }]);
    if (path === 'stats') {
      const from = url.searchParams.get('from') ?? TODAY;
      return ok(route, {
        from,
        to: TODAY,
        children: children
          .filter((c) => !c.archived)
          .map((c) => ({
            child: { id: c.id, name: c.name, avatar: c.avatar, color: c.color, has_pin: false },
            tasks: { approved: 6, missed: 2, rejected: 0, submitted: 0, pending: 1 },
            completion_rate: 0.75,
            points_earned: 60,
            points_spent: 20,
            current_streak: 3,
            best_streak: 5,
            per_day: [{ date: TODAY, approved: 2, missed: 0 }]
          }))
      });
    }
    const shopHandled = shopRoutes(route, path, method, body, ifNoneMatch, false);
    if (shopHandled) return shopHandled;
    if (path === 'recipes' && method === 'GET') {
      const q = (url.searchParams.get('q') ?? '').toLowerCase();
      return ok(route, recipes.filter((r) => r.title.toLowerCase().includes(q)).map(recipeSummary));
    }
    if (path === 'recipes' && method === 'POST') {
      if (!body.title) return fail(route, 422, 'validation_failed', 'title');
      const r = {
        id: nextId++,
        title: body.title,
        tags: body.tags ?? [],
        is_favorite: !!body.is_favorite,
        servings: body.servings ?? 4,
        ingredients: (body.ingredients ?? []).map(
          (
            i: { name?: string; quantity: number | null; unit: string; note: string | null },
            n: number
          ) => ({
            id: n + 1,
            ingredient_id: n + 1,
            name: i.name ?? 'Ingrediente',
            quantity: i.quantity,
            unit: i.unit,
            note: i.note
          })
        )
      };
      recipes.push(r);
      return ok(
        route,
        {
          ...recipeSummary(r),
          servings: r.servings,
          instructions: body.instructions ?? null,
          ingredients: r.ingredients
        },
        201
      );
    }
    const recipeMatch = path.match(/^recipes\/(\d+)$/);
    if (recipeMatch && method === 'GET') {
      const r = recipes.find((x) => x.id === Number(recipeMatch[1]));
      return r
        ? ok(route, {
            ...recipeSummary(r),
            servings: r.servings,
            instructions: null,
            ingredients: r.ingredients
          })
        : fail(route, 404, 'not_found');
    }
    if (path.startsWith('ingredients')) {
      return ok(route, [
        { id: 1, name: 'Pomodori', default_unit: 'g', shopping_category_id: 1, is_custom: false }
      ]);
    }
    const planMatch = path.match(/^meal-plans\/(\d{4}-\d{2}-\d{2})(?:\/slots\/(\d)\/([a-z]+))?$/);
    if (planMatch && method === 'GET') return ok(route, planJson(planMatch[1], 'user:2'));
    if (planMatch?.[2] && method === 'PUT') {
      const day = Number(planMatch[2]);
      const meal = planMatch[3];
      const keep = (body.entries as { id?: number; recipe_id?: number; free_text?: string }[])
        .map((e) => e.id)
        .filter(Boolean);
      for (let i = dishes.length - 1; i >= 0; i--) {
        const d = dishes[i];
        if (d.day === day && d.meal === meal && d.status === 'planned' && !keep.includes(d.id))
          dishes.splice(i, 1);
      }
      for (const e of body.entries as { id?: number; recipe_id?: number; free_text?: string }[]) {
        if (!e.id)
          dishes.push({
            id: nextId++,
            day,
            meal,
            recipe_id: e.recipe_id ?? null,
            free_text: e.free_text ?? null,
            status: 'planned',
            proposed_by: null,
            votes: new Map()
          });
      }
      const slot = planJson(planMatch[1], 'user:2').slots.find(
        (x) => x.day_index === day && x.meal_type === meal
      );
      return ok(route, slot);
    }
    const reviewDish = path.match(/^meal-entries\/(\d+)\/(accept|reject)$/);
    if (reviewDish) {
      const d = dishes.find((x) => x.id === Number(reviewDish[1]));
      if (!d) return fail(route, 404, 'not_found');
      d.status = reviewDish[2] === 'accept' ? 'planned' : 'rejected';
      return ok(route, dishJson(d, 'user:2'));
    }
    if (path === 'meal-templates') return ok(route, []);

    if (path === 'rewards' && method === 'GET' && url.searchParams.get('view') !== 'shop')
      return ok(route, rewards);
    if (path === 'rewards' && method === 'POST') {
      if (!body.title) return fail(route, 422, 'validation_failed', 'title');
      const r: FakeReward = {
        id: nextId++,
        title: body.title,
        icon: body.icon,
        cost: body.cost,
        limit_per_period: body.limit_per_period ?? null,
        limit_period: body.limit_period ?? null,
        stock: body.stock ?? null,
        is_active: true,
        sort_order: 0
      };
      rewards.push(r);
      return ok(route, r, 201);
    }
    const rewardMatch = path.match(/^rewards\/(\d+)$/);
    if (rewardMatch) {
      const r = rewards.find((x) => x.id === Number(rewardMatch[1]));
      if (!r) return fail(route, 404, 'not_found');
      if (method === 'PATCH') return ok(route, Object.assign(r, body));
      if (method === 'DELETE') {
        rewards.splice(rewards.indexOf(r), 1);
        return route.fulfill({ status: 204 });
      }
    }
    if (path === 'redemptions' && method === 'GET') {
      const statuses = (url.searchParams.get('status') ?? '').split(',').filter(Boolean);
      return ok(
        route,
        redemptions
          .filter((r) => !statuses.length || statuses.includes(r.status))
          .map(redemptionJson)
      );
    }
    const redemptionMatch = path.match(/^redemptions\/(\d+)\/(approve|reject|fulfill)$/);
    if (redemptionMatch) {
      const r = redemptions.find((x) => x.id === Number(redemptionMatch[1]));
      if (!r) return fail(route, 404, 'not_found');
      const action = redemptionMatch[2];
      if (action === 'approve') {
        const reward = rewards.find((x) => x.id === r.reward_id) as FakeReward;
        credit(r.child_id, -reward.cost, 'reward', reward.title);
        r.status = 'approved';
        return ok(route, { redemption: redemptionJson(r), new_badges: [] });
      }
      r.status = action === 'reject' ? 'rejected' : 'fulfilled';
      r.note = body.note ?? null;
      return ok(route, redemptionJson(r));
    }
    const ledgerMatch = path.match(/^children\/(\d+)\/(transactions|adjust)$/);
    if (ledgerMatch) {
      const c = children.find((x) => x.id === Number(ledgerMatch[1]));
      if (!c) return fail(route, 404, 'not_found');
      if (ledgerMatch[2] === 'transactions') {
        const items = ledger
          .filter((x) => x.child_id === c.id)
          .map((x) => ({
            ...x,
            xp: Math.max(0, x.points),
            source_type: null,
            source_id: null,
            created_at: '2026-11-16T10:00:00+01:00'
          }));
        return route.fulfill({
          json: {
            data: items,
            meta: {
              pagination: { totalCount: items.length, pageCount: 1, currentPage: 1, perPage: 20 }
            }
          }
        });
      }
      if (body.type === 'penalty') return fail(route, 422, 'penalties_disabled');
      if (!body.note) return fail(route, 422, 'validation_failed', 'note');
      credit(c.id, body.points, 'bonus', body.note);
      return ok(
        route,
        {
          child: childJson(c),
          transaction: {
            ...ledger[0],
            xp: body.points,
            source_type: null,
            source_id: null,
            created_at: ''
          },
          level_up: false
        },
        201
      );
    }

    if (path === 'children' && url.searchParams.get('players') === '1') {
      return ok(route, [...children.filter((c) => !c.archived), adult].map(childJson));
    }
    if (path === 'me/today') {
      const groups: Record<string, unknown[]> = {
        morning: [],
        afternoon: [],
        evening: [],
        any: []
      };
      for (const o of occurrences.filter((x) => x.child_id === ADULT_ID && x.status !== 'taken')) {
        groups[(tasks.find((x) => x.id === o.task_id) as FakeTask).time_of_day].push(occJson(o));
      }
      return ok(route, {
        date: TODAY,
        child: { ...childJson(adult), xp_level_start: 0, xp_next_level: 100 },
        groups,
        savings_goal: null
      });
    }
    const adultOcc = path.match(/^occurrences\/(\d+)\/submit$/);
    if (adultOcc) {
      const o = occurrences.find((x) => x.id === Number(adultOcc[1]) && x.child_id === ADULT_ID);
      if (!o) return fail(route, 404, 'not_found');
      return ok(route, approve(o));
    }
    if (path === 'rewards' && url.searchParams.get('view') === 'shop') {
      return ok(
        route,
        rewards.map((r) => ({
          ...r,
          affordable: (points.get(ADULT_ID) ?? 0) >= r.cost,
          available: true,
          is_goal: false
        }))
      );
    }
    if (path === 'children' && method === 'GET') {
      const archived = url.searchParams.get('archived') === '1';
      return ok(route, children.filter((c) => c.archived === archived).map(childJson));
    }
    if (path === 'children' && method === 'POST') {
      if (!body.name) return fail(route, 422, 'validation_failed', 'name');
      const c: FakeChild = {
        id: nextId++,
        name: body.name,
        avatar: body.avatar,
        color: body.color,
        birth_year_month: body.birth_year_month ?? null,
        pin: null,
        can_check_shopping: !!body.can_check_shopping,
        archived: false
      };
      children.push(c);
      return ok(route, childJson(c), 201);
    }
    const childMatch = path.match(/^children\/(\d+)(\/pin)?$/);
    if (childMatch) {
      const c = children.find((x) => x.id === Number(childMatch[1]));
      if (!c) return fail(route, 404, 'not_found');
      if (childMatch[2] && method === 'PUT') {
        if (body.pin !== null && !/^\d{4}$/.test(body.pin))
          return fail(route, 422, 'validation_failed', 'pin');
        c.pin = body.pin;
        return ok(route, childJson(c));
      }
      if (method === 'GET') return ok(route, childJson(c));
      if (method === 'PATCH') {
        Object.assign(c, body);
        return ok(route, childJson(c));
      }
      if (method === 'DELETE') {
        c.archived = true;
        return route.fulfill({ status: 204 });
      }
    }
    if (path === 'tasks' && method === 'GET') {
      return ok(route, tasks.map(taskJson));
    }
    if (path === 'tasks' && method === 'POST') {
      if (!body.title) return fail(route, 422, 'validation_failed', 'title');
      const task: FakeTask = {
        id: nextId++,
        title: body.title,
        icon: body.icon,
        points: body.points,
        requires_approval: body.requires_approval ?? true,
        is_open: !!body.is_open,
        recurrence_type: body.recurrence_type,
        recurrence_days: body.recurrence_days ?? null,
        interval_days: body.interval_days ?? null,
        time_of_day: body.time_of_day ?? 'any',
        due_date: body.due_date ?? null,
        start_date: body.start_date ?? TODAY,
        end_date: body.end_date ?? null,
        is_active: true,
        assignee_ids: body.assignee_ids ?? [],
        checklist: (body.checklist ?? []).map((i: { label: string; icon: string | null }) => ({
          id: nextId++,
          label: i.label,
          icon: i.icon ?? null
        }))
      };
      tasks.push(task);
      generate(task);
      return ok(route, taskJson(task), 201);
    }
    const taskMatch = path.match(/^tasks\/(\d+)$/);
    if (taskMatch) {
      const task = tasks.find((x) => x.id === Number(taskMatch[1]));
      if (!task) return fail(route, 404, 'not_found');
      if (method === 'GET') return ok(route, taskJson(task));
      if (method === 'PATCH') {
        Object.assign(
          task,
          body,
          body.checklist
            ? {
                checklist: body.checklist.map(
                  (i: { id?: number; label: string; icon: string | null }) => ({
                    id: i.id ?? nextId++,
                    label: i.label,
                    icon: i.icon ?? null
                  })
                )
              }
            : {}
        );
        return ok(route, taskJson(task));
      }
      if (method === 'DELETE') {
        tasks.splice(tasks.indexOf(task), 1);
        return route.fulfill({ status: 204 });
      }
    }
    if (path === 'task-templates') {
      return ok(route, [
        {
          id: 1,
          title_key: 'make_bed',
          icon: '🛏️',
          min_age: 4,
          max_age: 14,
          default_points: 5,
          category: 'home',
          time_of_day: 'morning',
          recurrence_type: 'daily'
        },
        {
          id: 2,
          title_key: 'tidy_toys',
          icon: '🧸',
          min_age: 3,
          max_age: 9,
          default_points: 5,
          category: 'home',
          time_of_day: 'evening',
          recurrence_type: 'daily'
        }
      ]);
    }
    if (path === 'occurrences' && method === 'GET') {
      const status = url.searchParams.get('status');
      return ok(route, occurrences.filter((o) => !status || o.status === status).map(occJson));
    }
    if (path === 'occurrences/bulk-approve' && method === 'POST') {
      const approved: number[] = [];
      const skipped: number[] = [];
      for (const id of body.ids as number[]) {
        const o = occurrences.find((x) => x.id === id && x.status === 'submitted');
        if (o) {
          approve(o);
          approved.push(id);
        } else skipped.push(id);
      }
      return ok(route, { approved, skipped });
    }
    const reviewMatch = path.match(/^occurrences\/(\d+)\/(approve|reject)$/);
    if (reviewMatch) {
      const o = occurrences.find((x) => x.id === Number(reviewMatch[1]));
      if (!o) return fail(route, 404, 'not_found');
      if (o.status !== 'submitted') return fail(route, 422, 'invalid_transition');
      if (reviewMatch[2] === 'approve') return ok(route, approve(o));
      o.status = 'rejected';
      o.review_note = body.note ?? null;
      return ok(route, occJson(o));
    }

    if (path === 'devices/pairing-code' && method === 'POST') {
      pairingCode = 'ABC234';
      return ok(route, { code: pairingCode, expires_at: '2026-11-16T15:00:00+01:00' }, 201);
    }
    if (path === 'devices' && method === 'GET') {
      return ok(
        route,
        devices
          .filter((d) => !d.revoked)
          .map((d) => ({
            id: d.id,
            name: d.name,
            last_seen_at: null,
            created_at: '',
            revoked: false
          }))
      );
    }
    const deviceMatch = path.match(/^devices\/(\d+)$/);
    if (deviceMatch && method === 'DELETE') {
      const d = devices.find((x) => x.id === Number(deviceMatch[1]));
      if (d) d.revoked = true;
      return route.fulfill({ status: 204 });
    }
    return fail(route, 404, 'not_found');
  }

  return {
    children,
    devices,
    tasks,
    occurrences,
    rewards,
    redemptions,
    recipes,
    dishes,
    shop,
    net,
    family,
    points,
    async install(page: Page) {
      await page.route('**/api/v1/**', handle);
    },
    addChild(name: string, extra: Partial<FakeChild> = {}): FakeChild {
      const c: FakeChild = {
        id: nextId++,
        name,
        avatar: 'fox',
        color: '#E07400',
        birth_year_month: null,
        pin: null,
        can_check_shopping: false,
        archived: false,
        ...extra
      };
      children.push(c);
      return c;
    }
  };
}

/** Opens a child session directly (the device flow is covered by device.e2e.ts). */
export async function enterAsChild(page: Page, childId: number) {
  await page.goto('/login');
  await page.evaluate((id) => {
    localStorage.setItem('hq.device', 'd_seed');
    localStorage.setItem('hq.child', `c_${id}`);
  }, childId);
}

export async function loginAsParent(page: Page) {
  await page.goto('/login');
  await page.getByLabel('Codice di accesso').fill(PARENT_TOKEN);
  await page.getByRole('button', { name: 'Entra' }).click();
  await page.waitForURL(/\/dashboard$/);
}
