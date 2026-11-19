import { expect, test } from '@playwright/test';
import { createFakeApi, enterAsChild, loginAsParent } from './mock-api';

test.use({ viewport: { width: 1280, height: 900 } });

test('parent reopens menu and shopping without connection (read-only menu)', async ({ page }) => {
  const api = createFakeApi();
  await api.install(page);
  await loginAsParent(page);

  await page.goto('/menu/2026-11-16');
  await page.getByRole('button', { name: 'Modifica Cena di mercoledì 18 novembre' }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByLabel('Oppure scrivi un piatto').fill('Minestrone');
  await dialog.getByRole('button', { name: 'Aggiungi', exact: true }).click();
  await dialog.getByRole('button', { name: 'Salva' }).click();
  await expect(page.getByText('Pasto aggiornato')).toBeVisible();
  await page.goto('/menu/2026-11-16'); // the saved copy is the server's response
  await expect(page.getByRole('cell').filter({ hasText: 'Minestrone' })).toBeVisible();

  await page.goto('/shopping');
  await page.getByLabel('Cosa serve?').fill('Mele');
  await page.getByRole('button', { name: 'Aggiungi', exact: true }).click();
  await expect(page.getByRole('button', { name: 'Spunta Mele' })).toBeVisible();

  // no network: the app still starts (session from the saved /me) and shows the copies
  api.net.offline = true;
  await page.goto('/menu/2026-11-16');
  await expect(page.getByRole('status')).toContainText('Sei offline');
  await expect(page.getByRole('cell').filter({ hasText: 'Minestrone' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Copia settimana scorsa' })).toBeDisabled();
  await expect(
    page.getByRole('button', { name: 'Modifica Cena di mercoledì 18 novembre' })
  ).toBeDisabled();

  // a week never opened on this device
  await page.goto('/menu/2026-12-07');
  await expect(page.getByText('Questa pagina non è salvata sul dispositivo')).toBeVisible();

  // shopping keeps working offline: changes are queued
  await page.goto('/shopping');
  await page.getByRole('button', { name: 'Spunta Mele' }).click();
  await expect(page.getByRole('status')).toContainText('Sei offline');
  await expect(page.getByRole('button', { name: 'Togli la spunta a Mele' })).toBeVisible();

  api.net.offline = false;
  await page.evaluate(() => window.dispatchEvent(new Event('online')));
  await expect.poll(() => api.shop.items.find((i) => i.label === 'Mele')?.checked).toBe(true);

  // logout drops the saved copies
  await page.evaluate(() => {
    const keys = Object.keys(localStorage).filter((k) => k.startsWith('hq.cache.'));
    if (keys.length === 0) throw new Error('no offline copies saved');
  });
  await page.getByRole('button', { name: 'Esci' }).click();
  await page.waitForURL(/\/login$/);
  expect(
    await page.evaluate(() => Object.keys(localStorage).filter((k) => k.startsWith('hq.cache.')))
  ).toEqual([]);
});

test("kid's menu stays readable offline, votes wait for the connection", async ({ page }) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca');
  api.devices.push({ id: 1, name: 'Tablet', token: 'd_seed', revoked: false });
  await api.install(page);

  await enterAsChild(page, luca.id);
  await page.goto('/kid-menu');
  await expect(page.getByRole('heading', { name: '🍽️ Menù' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Proponi un piatto' }).first()).toBeEnabled();

  api.net.offline = true;
  await page.reload();
  await expect(page.getByRole('status')).toContainText('Sei offline');
  await expect(page.getByRole('button', { name: 'Proponi un piatto' }).first()).toBeDisabled();
});
