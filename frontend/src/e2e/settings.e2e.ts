import { expect, test } from '@playwright/test';
import { createFakeApi, loginAsParent } from './mock-api';

test('parent updates family settings and sees statistics', async ({ page }) => {
  const api = createFakeApi();
  api.addChild('Luca');
  await api.install(page);
  await loginAsParent(page);

  await page.getByRole('link', { name: 'Impostazioni' }).click();
  await page.getByLabel('Nome della famiglia').fill('Rossi Bianchi');
  await page.getByText('Riepilogo settimanale via email').click();
  await page.getByText('Merenda').click();
  await page.getByRole('button', { name: 'Salva' }).click();
  await expect(page.getByText('Impostazioni salvate')).toBeVisible();
  expect(api.family).toMatchObject({
    name: 'Rossi Bianchi',
    settings: { weekly_email: true, meal_types: ['breakfast', 'lunch', 'dinner'] }
  });
  // e2e browser: no service worker / notifications denied; the page explains why
  await expect(
    page
      .getByRole('status')
      .filter({ hasText: /non supporta le notifiche|non sono configurate|bloccato le notifiche/ })
  ).toBeVisible();
  await expect(page.getByRole('listitem').filter({ hasText: 'Anna' })).toContainText(
    'Amministratore'
  );

  await page.getByRole('link', { name: 'Statistiche' }).click();
  const card = page.getByRole('region').or(page.locator('section')).filter({ hasText: 'Luca' });
  await expect(card).toContainText('75%');
  await expect(card).toContainText('+60');
  await page.getByRole('radio', { name: 'Ultimi 30 giorni' }).click();
  await expect(page.getByRole('radio', { name: 'Ultimi 30 giorni' })).toHaveAttribute(
    'aria-checked',
    'true'
  );
});

test('admin downloads the data and deletes the family', async ({ page }) => {
  const api = createFakeApi();
  await api.install(page);
  await loginAsParent(page);
  await page.goto('/settings/privacy');
  await expect(page.getByText('Nessun servizio di analisi')).toBeVisible();

  const download = page.waitForEvent('download');
  await page.getByRole('button', { name: /Scarica tutti i dati/ }).click();
  expect((await download).suggestedFilename()).toBe('homequest-export.json');

  await page.getByRole('button', { name: 'Elimina la famiglia' }).click();
  const dialog = page.getByRole('dialog');
  const confirm = dialog.getByRole('button', { name: 'Elimina la famiglia' });
  await expect(confirm).toBeDisabled();
  await dialog.getByLabel(/scrivi il nome della famiglia/).fill('Rossi');
  await confirm.click();
  await expect(page).toHaveURL(/\/login$/);
  expect(api.family.name).toBe('__deleted__');
});

test('parent edits their profile: name, email and avatar', async ({ page }) => {
  const api = createFakeApi();
  await api.install(page);
  await loginAsParent(page);

  await page.getByRole('link', { name: 'Il mio profilo' }).click();
  await expect(page.getByLabel('Nome', { exact: true })).toHaveValue('Anna');
  await page.getByLabel('Nome', { exact: true }).fill('Anna Maria');
  await page.getByLabel('Email (facoltativa)').fill('anna@example.com');
  await page.getByRole('img', { name: 'Leone' }).click();
  await page.getByRole('radio', { name: '#2E5BBA' }).check({ force: true });
  await page.getByRole('button', { name: 'Salva' }).click();
  await expect(page.getByText('Profilo salvato')).toBeVisible();
  expect(api.user).toMatchObject({ name: 'Anna Maria', email: 'anna@example.com' });
  expect(api.adult).toMatchObject({ name: 'Anna Maria', avatar: 'lion', color: '#2E5BBA' });
});
