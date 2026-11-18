import { expect, test } from '@playwright/test';
import { createFakeApi, enterAsChild, loginAsParent } from './mock-api';

test('parent adds items, works offline, generates from the menu', async ({ page }) => {
  const api = createFakeApi();
  await api.install(page);
  await loginAsParent(page);

  await page.getByRole('link', { name: 'Spesa' }).click();
  await expect(page.getByText('La lista è vuota.')).toBeVisible();
  await page.getByRole('button', { name: '+ Latte' }).click();
  await page.getByLabel('Cosa serve?').fill('Mele');
  await page.getByRole('button', { name: 'Aggiungi', exact: true }).click();
  await expect(page.getByRole('heading', { name: '🧀 Latticini e uova' })).toBeVisible();
  await expect(page.getByRole('heading', { name: '🥦 Frutta e verdura' })).toBeVisible();

  // offline: changes are queued and shown, then synced when back online
  api.net.offline = true;
  await page.getByRole('button', { name: 'Spunta Latte' }).click();
  await page.getByLabel('Cosa serve?').fill('Pane');
  await page.getByRole('button', { name: 'Aggiungi', exact: true }).click();
  await expect(page.getByRole('status')).toContainText('Sei offline');
  await expect(page.getByRole('button', { name: 'Togli la spunta a Latte' })).toBeVisible();
  expect(api.shop.items.find((i) => i.label === 'Latte')?.checked).toBe(false);

  // back online: the browser fires `online` (the mock only aborts requests, so dispatch it);
  // without it the test would race the 5 s polling interval against expect.poll's 5 s timeout
  api.net.offline = false;
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await expect
    .poll(() => api.shop.items.map((i) => [i.label, i.checked]))
    .toEqual([
      ['Latte', true],
      ['Mele', false],
      ['Pane', false]
    ]);
  await expect(page.getByRole('status')).toHaveCount(0);

  await page.getByRole('button', { name: '🍽️ Genera dal menù' }).click();
  await expect(page.getByText('Lista aggiornata dal menù: 1 nuovi, 0 aggiornati')).toBeVisible();
  await expect(page.getByRole('button', { name: /Spunta Uova/ })).toContainText('6 pz · dal menù');

  await page.getByRole('button', { name: 'Togli gli spuntati' }).click();
  await expect(page.getByRole('button', { name: 'Togli la spunta a Latte' })).toHaveCount(0);
});

test('a kid with permission checks items; without it sees a message', async ({ page }) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca', { can_check_shopping: true });
  const anna = api.addChild('Anna');
  api.devices.push({ id: 1, name: 'Tablet', token: 'd_seed', revoked: false });
  api.shop.items.push({
    id: 900,
    label: 'Banane',
    quantity: 6,
    unit: 'pz',
    category_id: 1,
    source: 'manual',
    checked: false,
    client_uuid: null,
    ingredient_id: null
  });
  await api.install(page);

  await enterAsChild(page, luca.id);
  await page.goto('/today');
  await page.getByRole('link', { name: 'Spesa' }).click();
  await page.getByRole('button', { name: 'Spunta Banane' }).click();
  await expect.poll(() => api.shop.items[0].checked).toBe(true);

  await enterAsChild(page, anna.id);
  await page.goto('/today');
  await expect(page.getByRole('link', { name: 'Spesa' })).toHaveCount(0);
  await page.goto('/kid-shopping');
  await expect(
    page.getByText('Chiedi a mamma o papà di poter usare la lista della spesa.')
  ).toBeVisible();
});
