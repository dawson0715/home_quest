import { expect, test } from '@playwright/test';
import { createFakeApi, loginAsParent } from './mock-api';

test.use({ viewport: { width: 390, height: 844 } });

test('phone: bottom navigation for the main sections and a side drawer for the rest', async ({
  page
}) => {
  await createFakeApi().install(page);
  await loginAsParent(page);

  const bottom = page.getByRole('navigation', { name: 'Sezioni principali' });
  await expect(bottom.getByRole('link')).toHaveText([/Compiti/, /Menù/, /Spesa/]);
  await bottom.getByRole('link', { name: 'Spesa' }).click();
  await expect(page).toHaveURL(/\/shopping/);
  await expect(bottom.getByRole('link', { name: 'Spesa' })).toHaveAttribute('aria-current', 'page');
  await expect(page.getByRole('banner')).toContainText('Spesa');
  if (process.env.HQ_SCREENSHOTS) await page.screenshot({ path: 'test-results/layout-bottom.png' });

  await page.getByRole('button', { name: 'Apri menu' }).click();
  const drawer = page.getByRole('dialog', { name: 'Menu' });
  await expect(drawer).toBeVisible();
  if (process.env.HQ_SCREENSHOTS) {
    await page.waitForTimeout(400); // drawer slide-in
    await page.screenshot({ path: 'test-results/layout-drawer.png' });
  }
  await drawer.getByRole('link', { name: 'Statistiche' }).click();
  await expect(page).toHaveURL(/\/stats$/);
  await expect(drawer).toBeHidden();

  await page.getByRole('button', { name: 'Apri menu' }).click();
  await page.keyboard.press('Escape');
  await expect(drawer).toBeHidden();
});
