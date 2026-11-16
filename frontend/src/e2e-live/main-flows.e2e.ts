import { expect, test } from '@playwright/test';

const token = process.env.HQ_PARENT_TOKEN ?? '';

test.skip(!token, 'set HQ_PARENT_TOKEN (demo-anna token from `php yii seed/demo`)');

test('parent pairs the tablet, a kid enters, parent uses the shopping list', async ({
  browser
}) => {
  const parent = await browser.newPage();
  await parent.goto('/login');
  await parent.getByLabel('Codice di accesso').fill(token);
  await parent.getByRole('button', { name: 'Entra' }).click();
  await expect(parent.getByRole('heading', { name: 'Ciao Anna!' })).toBeVisible();

  await parent.goto('/settings/devices');
  await parent.getByRole('button', { name: 'Genera codice di collegamento' }).click();
  const code = (await parent.getByTestId('pairing-code').textContent())?.trim() ?? '';
  expect(code).toMatch(/^[A-Z2-9]{6}$/);

  const kid = await (await browser.newContext({ serviceWorkers: 'block' })).newPage();
  await kid.goto(`/device?code=${code}`);
  await kid.getByRole('button', { name: 'Collega' }).click();
  await expect(kid.getByRole('heading', { name: 'Chi sei?' })).toBeVisible();
  await kid.getByRole('button', { name: 'Sofia' }).click();
  await expect(kid.getByRole('heading', { name: 'Ciao Sofia!' })).toBeVisible();
  await kid.getByRole('link', { name: 'Trofei' }).click();
  await expect(kid.getByRole('heading', { name: '🏆 Trofei' })).toBeVisible();

  await parent.goto('/shopping');
  await expect(parent.getByRole('button', { name: /Spunta Spaghetti/ })).toBeVisible();
  const label = `Prova live ${Date.now()}`;
  await parent.getByLabel('Cosa serve?').fill(label);
  await parent.getByRole('button', { name: 'Aggiungi', exact: true }).click();
  await parent.getByRole('button', { name: `Spunta ${label}` }).click();
  await expect(parent.getByRole('button', { name: `Togli la spunta a ${label}` })).toBeVisible();
  await parent.reload();
  await expect(parent.getByRole('button', { name: `Togli la spunta a ${label}` })).toBeVisible();

  await parent.goto('/settings/devices');
  await parent.getByRole('button', { name: 'Scollega' }).first().click();
  await parent.getByRole('dialog').getByRole('button', { name: 'Scollega' }).click();
});
