import { expect, test } from '@playwright/test';
import { createFakeApi, loginAsParent } from './mock-api';

test('a new family is guided through the first steps', async ({ page }) => {
  const api = createFakeApi();
  await api.install(page);
  await loginAsParent(page);

  const card = page.getByRole('region', { name: /Benvenuti in HomeQuest/ });
  await expect(card).toContainText('0 di 4 fatti');
  await card.getByRole('link', { name: /Aggiungi il profilo di un figlio/ }).click();
  await expect(page).toHaveURL(/\/children$/);

  api.addChild('Luca');
  await page.goto('/dashboard');
  await expect(card).toContainText('1 di 4 fatti');
  await card.getByRole('button', { name: 'Nascondi' }).click();
  await expect(card).toHaveCount(0);
  await page.reload();
  await expect(page.getByRole('heading', { name: 'Ciao Anna!' })).toBeVisible();
  await expect(card).toHaveCount(0);
});
