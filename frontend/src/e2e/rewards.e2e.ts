import { expect, test } from '@playwright/test';
import { createFakeApi, enterAsChild, loginAsParent, type FakeReward } from './mock-api';

const reward = (id: number, title: string, cost: number, icon = '🎁'): FakeReward => ({
  id,
  title,
  icon,
  cost,
  limit_per_period: null,
  limit_period: null,
  stock: null,
  is_active: true,
  sort_order: 0
});

test('kid picks a goal and asks for an affordable reward', async ({ page }) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca');
  api.devices.push({ id: 1, name: 'Tablet', token: 'd_seed', revoked: false });
  api.rewards.push(
    reward(10, '30 min di tablet', 50, '📱'),
    reward(11, 'Scegli la cena', 200, '🍕')
  );
  api.points.set(luca.id, 80);
  await api.install(page);
  await enterAsChild(page, luca.id);

  await page.goto('/today');
  await page.getByRole('link', { name: 'Premi' }).click();
  await expect(page).toHaveURL(/\/shop$/);
  await expect(page.getByTestId('points')).toHaveText('⭐ Hai 80 punti');

  const dinner = page.getByRole('listitem').filter({ hasText: 'Scegli la cena' });
  await expect(dinner.getByText('Ti mancano 120 punti')).toBeVisible();
  await dinner.getByRole('button', { name: 'Scegli come obiettivo' }).click();
  await expect(page.getByTestId('goal')).toContainText('Obiettivo: Scegli la cena');

  await page
    .getByRole('listitem')
    .filter({ hasText: '30 min di tablet' })
    .getByRole('button', { name: 'Lo voglio!' })
    .click();
  await expect(page.getByText('Richiesta inviata! Aspetta mamma o papà')).toBeVisible();
  await expect(page.getByRole('listitem').filter({ hasText: 'In attesa' })).toContainText(
    '30 min di tablet'
  );
});

test('trophies show level, streak and earned badges', async ({ page }) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca');
  api.devices.push({ id: 1, name: 'Tablet', token: 'd_seed', revoked: false });
  await api.install(page);
  await enterAsChild(page, luca.id);

  await page.goto('/trophies');
  await expect(page.getByRole('heading', { name: '🏆 Trofei' })).toBeVisible();
  await expect(page.getByText('Livello 1')).toBeVisible();
  await expect(page.getByTestId('streak')).toHaveText('0 giorni');
  await expect(page.getByText('Primo passo')).toBeVisible();
  await expect(page.getByRole('listitem').filter({ hasText: 'Primo passo' })).toContainText(
    'Da sbloccare'
  );
});

test('parent creates a reward, approves and delivers a request, gives a bonus', async ({
  page
}) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca');
  api.rewards.push(reward(10, 'Gelato', 30, '🍦'));
  api.points.set(luca.id, 50);
  api.redemptions.push({
    id: 20,
    reward_id: 10,
    child_id: luca.id,
    status: 'requested',
    note: null
  });
  await api.install(page);
  await loginAsParent(page);

  await expect(page.getByText('1 premi richiesti')).toBeVisible();
  await page.getByRole('link', { name: 'Da approvare', exact: true }).click();
  const request = page.getByRole('listitem').filter({ hasText: 'Gelato' });
  await request.getByRole('button', { name: 'Approva' }).click();
  await expect(page.getByText('Premio approvato: -30 punti a Luca')).toBeVisible();
  await page
    .getByRole('listitem')
    .filter({ hasText: 'Da consegnare' })
    .getByRole('button', { name: 'Consegnato' })
    .click();
  await expect(page.getByText('Segnato come consegnato')).toBeVisible();

  await page.getByRole('link', { name: 'Premi' }).click();
  await page.getByRole('button', { name: 'Nuovo premio' }).click();
  const dialog = page.getByRole('dialog', { name: 'Nuovo premio' });
  await dialog.getByLabel('Nome del premio').fill('Cinema');
  await dialog.getByLabel('Costo in punti').fill('300');
  await dialog.getByLabel('Limita quante volte si può chiedere').check();
  await dialog.getByRole('button', { name: 'Salva' }).click();
  await expect(page.getByText('Premio salvato')).toBeVisible();
  await expect(page.getByRole('button', { name: /Cinema/ })).toContainText('Max 1 per settimana');

  await page.getByRole('link', { name: 'Figli' }).click();
  await page.getByRole('link', { name: /Luca/ }).click();
  await expect(page.getByText('Le penalità sono disattivate')).toBeVisible();
  await page.getByLabel('Punti', { exact: true }).fill('15');
  await page.getByLabel('Motivo').fill('Ha aiutato la nonna');
  await page.getByRole('button', { name: 'Assegna' }).click();
  await expect(page.getByText('Fatto: 15 punti a Luca')).toBeVisible();
  await expect(page.getByTestId('ledger').getByRole('listitem').first()).toContainText(
    'Bonus · Ha aiutato la nonna'
  );
  await expect(page.getByTestId('ledger')).toContainText('-30');
});
