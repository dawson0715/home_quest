import { expect, test } from '@playwright/test';
import { ADULT_ID, createFakeApi, enterAsChild, loginAsParent } from './mock-api';

test('parent assigns tasks to a parent or leaves them open, and plays too', async ({ page }) => {
  const api = createFakeApi();
  api.addChild('Luca');
  api.rewards.push({
    id: 10,
    title: 'Partita a calcetto',
    icon: '⚽',
    cost: 20,
    limit_per_period: null,
    limit_period: null,
    stock: null,
    is_active: true,
    sort_order: 0
  });
  await api.install(page);
  await loginAsParent(page);

  await page.getByRole('link', { name: 'Compiti' }).click();
  await page.getByRole('button', { name: 'Nuovo compito' }).click();
  await page.getByLabel('Titolo').fill('Svuotare la lavastoviglie');
  await page.getByText('Libero: lo prende chi lo fa per primo').click();
  await expect(page.getByText('Anna (genitore)')).toBeVisible();
  await page.getByRole('button', { name: 'Salva' }).click();
  await expect(page.getByRole('link', { name: /Svuotare la lavastoviglie/ })).toContainText(
    '🏃 Libero'
  );
  expect(api.tasks[0]).toMatchObject({ is_open: true });

  // a task assigned to the parent shows up in "Il mio gioco"
  api.tasks.push({
    ...api.tasks[0],
    id: 90,
    title: 'Portare fuori il cane',
    icon: '🐕',
    points: 25,
    is_open: false,
    requires_approval: false,
    assignee_ids: [ADULT_ID]
  });
  api.occurrences.push({
    id: 91,
    task_id: 90,
    child_id: ADULT_ID,
    status: 'pending',
    done: [],
    review_note: null,
    points_awarded: null
  });
  await page.getByRole('link', { name: 'Il mio gioco' }).click();
  await page.getByRole('button', { name: /Fatto!/ }).click();
  await expect(page.getByText('Bravo! +25 punti')).toBeVisible();
  await page.getByText('Bravo! +25 punti').click();
  await expect(
    page
      .getByRole('listitem')
      .filter({ hasText: 'Partita a calcetto' })
      .getByRole('button', { name: 'Lo voglio!' })
  ).toBeEnabled();
});

test('the first kid who does an open task takes it', async ({ page }) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca');
  const sofia = api.addChild('Sofia');
  api.devices.push({ id: 1, name: 'Tablet', token: 'd_seed', revoked: false });
  api.tasks.push({
    id: 70,
    title: 'Annaffiare le piante',
    icon: '🪴',
    points: 10,
    requires_approval: false,
    is_open: true,
    recurrence_type: 'daily',
    recurrence_days: null,
    interval_days: null,
    time_of_day: 'any',
    due_date: null,
    start_date: '2026-11-16',
    end_date: null,
    is_active: true,
    assignee_ids: [],
    checklist: []
  });
  api.occurrences.push(
    {
      id: 71,
      task_id: 70,
      child_id: luca.id,
      status: 'pending',
      done: [],
      review_note: null,
      points_awarded: null
    },
    {
      id: 72,
      task_id: 70,
      child_id: sofia.id,
      status: 'pending',
      done: [],
      review_note: null,
      points_awarded: null
    }
  );
  await api.install(page);

  await enterAsChild(page, sofia.id);
  await page.goto('/today');
  await expect(page.getByText('🏃 Chi prima arriva!')).toBeVisible();
  await page.getByRole('button', { name: /Fatto!/ }).click();
  await expect(page.getByText('Bravo! +10 punti')).toBeVisible();

  await enterAsChild(page, luca.id);
  await page.goto('/today');
  await expect(page.getByText('Annaffiare le piante')).toHaveCount(0);
  await expect(page.getByText('Oggi niente compiti')).toBeVisible();
});
