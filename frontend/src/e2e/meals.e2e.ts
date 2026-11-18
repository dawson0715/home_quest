import { expect, test } from '@playwright/test';
import { createFakeApi, enterAsChild, loginAsParent } from './mock-api';

test.use({ viewport: { width: 1280, height: 900 } });

test('parent writes a recipe and plans it for dinner', async ({ page }) => {
  const api = createFakeApi();
  api.addChild('Luca');
  await api.install(page);
  await loginAsParent(page);

  await page.getByRole('link', { name: 'Ricette' }).click();
  await page.getByRole('button', { name: 'Nuova ricetta' }).click();
  await page.getByLabel('Nome della ricetta').fill('Pasta al pomodoro');
  await page.getByRole('button', { name: 'Aggiungi ingrediente' }).click();
  await page.getByLabel('Ingrediente', { exact: true }).fill('Spaghetti');
  await page.getByLabel('Quantità').fill('320');
  await page.getByText('Piace ai bimbi').click();
  await page.getByRole('button', { name: 'Salva' }).click();
  await expect(page.getByText('Ricetta salvata')).toBeVisible();
  expect(api.recipes[0]).toMatchObject({ title: 'Pasta al pomodoro', tags: ['preferito_bimbi'] });

  await page.goto('/menu/2026-11-16');
  await expect(page.getByText('Settimana dal lunedì 16 novembre')).toBeVisible();
  await page.getByRole('button', { name: 'Modifica Cena di mercoledì 18 novembre' }).click();
  const dialog = page.getByRole('dialog');
  await dialog.getByRole('button', { name: '+ Pasta al pomodoro' }).click();
  await dialog.getByLabel('Oppure scrivi un piatto').fill('Insalata');
  await dialog.getByRole('button', { name: 'Aggiungi', exact: true }).click();
  await dialog.getByRole('button', { name: 'Salva' }).click();
  await expect(page.getByText('Pasto aggiornato')).toBeVisible();
  await expect(page.getByRole('cell').filter({ hasText: 'Pasta al pomodoro' })).toContainText(
    'Insalata'
  );

  await page.getByRole('button', { name: 'Crea compito “aiuta a cucinare”' }).click();
  await expect(page.getByLabel('Titolo')).toHaveValue('Aiuta a cucinare: Pasta al pomodoro');
  await expect(page.getByText('Collegato al menù')).toBeVisible();
});

test('kid votes a dish and proposes one; parent accepts it', async ({ page }) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca');
  api.devices.push({ id: 1, name: 'Tablet', token: 'd_seed', revoked: false });
  api.dishes.push({
    id: 500,
    day: 0,
    meal: 'dinner',
    recipe_id: null,
    free_text: 'Minestrone',
    status: 'planned',
    proposed_by: null,
    votes: new Map()
  });
  await api.install(page);
  await page.clock.setFixedTime(new Date('2026-11-16T10:00:00+01:00'));
  await enterAsChild(page, luca.id);

  await page.goto('/kid-menu');
  const dinner = page.getByRole('article').filter({ hasText: 'Minestrone' });
  await dinner.getByRole('button', { name: 'Non mi piace' }).click();
  await expect(dinner.getByRole('button', { name: 'Non mi piace' })).toHaveAttribute(
    'aria-pressed',
    'true'
  );
  await dinner.getByRole('button', { name: /Proponi un piatto/ }).click();
  await page.getByLabel('Scrivi tu il piatto').fill('Pizza!');
  await page.getByRole('button', { name: 'Invia proposta' }).click();
  await expect(page.getByText('Proposta inviata!')).toBeVisible();
  await expect(dinner).toContainText('In attesa di mamma o papà');

  await page.evaluate(() => {
    localStorage.removeItem('hq.device');
    localStorage.removeItem('hq.child');
  });
  await loginAsParent(page);
  await page.goto('/menu/2026-11-16');
  const cell = page.getByRole('cell').filter({ hasText: 'Pizza!' });
  await expect(cell).toContainText('Proposta di Luca');
  await expect(page.getByRole('cell').filter({ hasText: 'Minestrone' })).toContainText(
    '0 👍 · 1 👎'
  );
  await cell.getByRole('button', { name: 'Accetta' }).click();
  await expect(cell).not.toContainText('Proposta di Luca');
  expect(api.dishes.find((d) => d.free_text === 'Pizza!')?.status).toBe('planned');
});

test('parent gets menu suggestions and adds the chosen ones', async ({ page }) => {
  const api = createFakeApi();
  api.recipes.push({
    id: 501,
    title: 'Risotto ai funghi',
    tags: [],
    is_favorite: true,
    servings: 4,
    ingredients: []
  });
  await api.install(page);
  await loginAsParent(page);

  await page.goto('/menu/2026-11-16');
  await page.getByRole('button', { name: '✨ Suggerisci menù' }).click();
  const dialog = page.getByRole('dialog');
  await expect(dialog.getByText('Non scrivere nomi o dati personali.')).toBeVisible();
  await dialog.getByLabel('Indicazioni (facoltative)').fill('Martedì poco tempo');
  await dialog.getByRole('button', { name: 'Genera proposte' }).click();
  await expect(dialog.getByTestId('suggest-source')).toContainText('intelligenza artificiale');
  expect(api.suggestNotes).toEqual(['Martedì poco tempo']);

  // 7 lunches (ideas) + 7 dinners (recipe): leave out Monday's lunch
  await dialog.getByLabel('Includi Minestrone per Pranzo di lun 16').uncheck();
  await dialog.getByRole('button', { name: 'Aggiungi al menù (13)' }).click();
  await expect(page.getByText('13 pasti aggiunti al menù')).toBeVisible();
  expect(api.dishes.filter((d) => d.status === 'planned')).toHaveLength(13);
  expect(api.dishes.some((d) => d.day === 0 && d.meal === 'lunch')).toBe(false);
  expect(api.dishes.find((d) => d.day === 0 && d.meal === 'dinner')).toMatchObject({
    recipe_id: 501
  });
});
