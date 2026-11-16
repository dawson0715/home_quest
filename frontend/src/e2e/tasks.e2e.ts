import { expect, test } from '@playwright/test';
import { createFakeApi, enterAsChild, loginAsParent } from './mock-api';

test('parent creates a routine from the library with a checklist', async ({ page }) => {
  const api = createFakeApi();
  api.addChild('Luca');
  await api.install(page);
  await loginAsParent(page);

  await page.getByRole('link', { name: 'Compiti' }).click();
  await expect(page.getByText('Nessun compito')).toBeVisible();
  await page.getByRole('button', { name: 'Dalla libreria' }).click();
  await page
    .getByRole('dialog')
    .getByRole('listitem')
    .filter({ hasText: 'Rifare il letto' })
    .getByRole('button', { name: 'Usa' })
    .click();

  await expect(page.getByLabel('Titolo')).toHaveValue('Rifare il letto');
  await page.getByRole('radio', { name: 'Giorni della settimana' }).click();
  await page.getByRole('button', { name: 'Sabato' }).click();
  await page.getByRole('button', { name: 'Aggiungi passaggio' }).click();
  await page.getByLabel('Passaggio 1', { exact: true }).fill('Tirare il piumone');
  await page.getByRole('button', { name: 'Salva' }).click();

  await expect(page.getByText('Compito salvato')).toBeVisible();
  await expect(page.getByRole('link', { name: /Rifare il letto/ })).toContainText(
    'Lun, Mar, Mer, Gio, Ven, Sab'
  );
  expect(api.tasks[0]).toMatchObject({
    recurrence_type: 'weekly',
    recurrence_days: 63,
    checklist: [{ label: 'Tirare il piumone' }]
  });
});

test('kid completes the checklist, submits and gets celebrated', async ({ page }) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca');
  api.devices.push({ id: 1, name: 'Tablet', token: 'd_seed', revoked: false });
  api.tasks.push({
    id: 50,
    title: 'Routine del mattino',
    icon: '🌅',
    points: 120,
    requires_approval: false,
    recurrence_type: 'daily',
    recurrence_days: null,
    interval_days: null,
    time_of_day: 'morning',
    due_date: null,
    start_date: '2026-11-16',
    end_date: null,
    is_active: true,
    assignee_ids: [luca.id],
    checklist: [
      { id: 51, label: 'Denti', icon: '🪥' },
      { id: 52, label: 'Vestiti', icon: '👕' }
    ]
  });
  api.occurrences.push({
    id: 60,
    task_id: 50,
    child_id: luca.id,
    status: 'pending',
    done: [],
    review_note: null,
    points_awarded: null
  });
  await api.install(page);
  await enterAsChild(page, luca.id);
  await page.goto('/today');

  await expect(page.getByRole('heading', { name: 'Mattina' })).toBeVisible();
  const done = page.getByRole('button', { name: /Fatto!/ });
  await expect(done).toBeDisabled();
  await page.getByRole('button', { name: /Denti/ }).click();
  await page.getByRole('button', { name: /Vestiti/ }).click();
  await expect(page.getByRole('button', { name: /Denti/ })).toHaveAttribute('aria-pressed', 'true');
  await done.click();

  await expect(page.getByText('Bravo! +120 punti')).toBeVisible();
  await expect(page.getByText('Sei salito al livello 2!')).toBeVisible();
  await page.getByText('Bravo! +120 punti').click();
  await expect(page.getByTestId('points')).toHaveText('⭐ Hai 120 punti');
  await expect(page.getByText('🎉 Fatto! +120')).toBeVisible();
});

test('parent approves and rejects submitted tasks', async ({ page }) => {
  const api = createFakeApi();
  const luca = api.addChild('Luca');
  const anna = api.addChild('Anna', { avatar: 'owl' });
  const base = {
    icon: '🧹',
    points: 10,
    requires_approval: true,
    recurrence_type: 'daily',
    recurrence_days: null,
    interval_days: null,
    time_of_day: 'any',
    due_date: null,
    start_date: '2026-11-16',
    end_date: null,
    is_active: true,
    checklist: []
  };
  api.tasks.push({ ...base, id: 70, title: 'Camera', assignee_ids: [luca.id, anna.id] });
  api.occurrences.push(
    {
      id: 71,
      task_id: 70,
      child_id: luca.id,
      status: 'submitted',
      done: [],
      review_note: null,
      points_awarded: null
    },
    {
      id: 72,
      task_id: 70,
      child_id: anna.id,
      status: 'submitted',
      done: [],
      review_note: null,
      points_awarded: null
    }
  );
  await api.install(page);
  await loginAsParent(page);

  await expect(page.getByText('2 compiti da approvare')).toBeVisible();
  await page.getByText('2 compiti da approvare').click();
  const lucaRow = page.getByRole('listitem').filter({ hasText: 'Luca' });
  await lucaRow.getByRole('button', { name: 'Approva' }).click();
  await expect(page.getByText('Approvato: +10 punti a Luca')).toBeVisible();

  await page
    .getByRole('listitem')
    .filter({ hasText: 'Anna' })
    .getByRole('button', { name: 'Rifiuta' })
    .click();
  await page.getByLabel('Spiega cosa manca (facoltativo)').fill('Il letto non è fatto');
  await page.getByRole('dialog').getByRole('button', { name: 'Rifiuta' }).click();
  await expect(page.getByText('Niente da approvare')).toBeVisible();
  expect(api.occurrences.find((o) => o.id === 72)).toMatchObject({
    status: 'rejected',
    review_note: 'Il letto non è fatto'
  });
});
