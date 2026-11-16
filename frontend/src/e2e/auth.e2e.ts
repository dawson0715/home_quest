import { expect, test } from '@playwright/test';
import { createFakeApi, loginAsParent } from './mock-api';

test('unauthenticated users land on the login page in Italian', async ({ page }) => {
  await createFakeApi().install(page);
  await page.goto('/');
  await expect(page).toHaveURL(/\/login$/);
  await expect(page.locator('html')).toHaveAttribute('lang', 'it');
  await expect(page.getByRole('heading', { name: 'Accedi' })).toBeVisible();
});

test('a wrong token shows an error', async ({ page }) => {
  await createFakeApi().install(page);
  await page.goto('/login');
  await page.getByLabel('Codice di accesso').fill('nope');
  await page.getByRole('button', { name: 'Entra' }).click();
  await expect(page.getByRole('alert')).toHaveText('Codice non valido o disattivato');
});

test('login, reload keeps the session, logout', async ({ page }) => {
  await createFakeApi().install(page);
  await loginAsParent(page);
  await expect(page.getByRole('heading', { name: 'Ciao Anna!' })).toBeVisible();
  await expect(page.getByText('Famiglia Rossi · Amministratore')).toBeVisible();

  await page.reload();
  await expect(page.getByRole('heading', { name: 'Ciao Anna!' })).toBeVisible();

  await page.getByRole('button', { name: 'Esci' }).click();
  await expect(page).toHaveURL(/\/login$/);
  await page.goto('/dashboard');
  await expect(page).toHaveURL(/\/login$/);
});
