import { expect, test } from '@playwright/test';
import { createFakeApi, loginAsParent } from './mock-api';

test('parent adds a child, sets a PIN and archives it', async ({ page }) => {
  await createFakeApi().install(page);
  await loginAsParent(page);

  await page.getByRole('link', { name: 'Figli' }).click();
  await expect(page.getByText('Non hai ancora aggiunto nessun figlio.')).toBeVisible();

  await page.getByRole('button', { name: 'Aggiungi figlio' }).click();
  const dialog = page.getByRole('dialog', { name: 'Nuovo figlio' });
  await dialog.getByLabel('Nome o soprannome').fill('Luca');
  await dialog.getByRole('img', { name: 'Drago' }).click();
  await dialog.getByRole('button', { name: 'Salva' }).click();
  await expect(page.getByText('Profilo salvato')).toBeVisible();

  await page.getByRole('link', { name: /Luca/ }).click();
  // invalid PINs are blocked by the input pattern before reaching the API
  await page.getByLabel('Nuovo PIN (4 cifre)').fill('12a4');
  await page.getByRole('button', { name: 'Imposta PIN' }).click();
  await expect(page.getByText('PIN aggiornato')).toBeHidden();
  await page.getByLabel('Nuovo PIN (4 cifre)').fill('1234');
  await page.getByRole('button', { name: 'Imposta PIN' }).click();
  await expect(page.getByText('PIN aggiornato')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Rimuovi PIN' })).toBeVisible();

  await page.getByRole('button', { name: 'Archivia' }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Archivia' }).click();
  await expect(page).toHaveURL(/\/children$/);
  await expect(page.getByText('Non hai ancora aggiunto nessun figlio.')).toBeVisible();
});

test('parent generates a pairing code with QR and revokes a device', async ({ page }) => {
  const api = createFakeApi();
  api.devices.push({ id: 1, name: 'Tablet cucina', token: 'd_x', revoked: false });
  await api.install(page);
  await loginAsParent(page);

  await page.getByRole('link', { name: 'Impostazioni' }).click();
  await page.getByRole('link', { name: 'Gestisci i dispositivi dei bambini' }).click();
  await page.getByRole('button', { name: 'Genera codice di collegamento' }).click();
  await expect(page.getByTestId('pairing-code')).toHaveText('ABC234');
  await expect(page.getByRole('img', { name: /codice ABC234/ }).locator('svg')).toBeVisible();

  await page.getByRole('button', { name: 'Scollega' }).click();
  await page.getByRole('dialog').getByRole('button', { name: 'Scollega' }).click();
  await expect(page.getByText('Nessun dispositivo collegato.')).toBeVisible();
});
