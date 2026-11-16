import { expect, test } from '@playwright/test';
import { createFakeApi } from './mock-api';

test('a kid pairs the tablet, enters with PIN and switches profile', async ({ page }) => {
  const api = createFakeApi();
  api.addChild('Luca', { avatar: 'dragon', pin: '1357' });
  api.addChild('Anna', { avatar: 'owl' });
  await api.install(page);

  await page.goto('/login');
  await page.getByRole('link', { name: 'Sei un bambino? Collega questo dispositivo' }).click();
  await expect(page).toHaveURL(/\/device$/);

  await page.evaluate(() =>
    fetch('/api/v1/devices/pairing-code', {
      method: 'POST',
      headers: { Authorization: 'Bearer good-token' }
    })
  );
  await page.getByLabel('Codice di collegamento').fill('abc-234');
  await page.getByRole('button', { name: 'Collega' }).click();

  await expect(page.getByRole('heading', { name: 'Chi sei?' })).toBeVisible();
  await page.getByRole('button', { name: 'Luca' }).click();
  await expect(page.getByRole('heading', { name: 'Ciao Luca! Inserisci il PIN' })).toBeVisible();
  for (const d of ['1', '1', '1', '1'])
    await page.getByRole('button', { name: `Cifra ${d}` }).click();
  await expect(page.getByRole('alert')).toHaveText('PIN sbagliato, riprova');
  for (const d of ['1', '3', '5', '7'])
    await page.getByRole('button', { name: `Cifra ${d}` }).click();

  await expect(page).toHaveURL(/\/today$/);
  await expect(page.getByRole('heading', { name: 'Ciao Luca!' })).toBeVisible();

  await page.reload();
  await expect(page.getByRole('heading', { name: 'Ciao Luca!' })).toBeVisible();

  await page.getByRole('button', { name: 'Cambia profilo' }).click();
  await page.getByRole('button', { name: 'Anna' }).click();
  await expect(page.getByRole('heading', { name: 'Ciao Anna!' })).toBeVisible();

  await page.goto('/');
  await expect(page).toHaveURL(/\/today$/);
});

test('a wrong pairing code is rejected', async ({ page }) => {
  await createFakeApi().install(page);
  await page.goto('/device?code=ZZZZZZ');
  await expect(page.getByLabel('Codice di collegamento')).toHaveValue('ZZZZZZ');
  await page.getByRole('button', { name: 'Collega' }).click();
  await expect(page.getByRole('alert')).toHaveText('Codice non valido o scaduto');
});
