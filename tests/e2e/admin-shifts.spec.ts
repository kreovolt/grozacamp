import { expect, test, type Page } from '@playwright/test';

// Свой сервер и свои данные (storage/test-admin, порт 8097): смены здесь меняются свободно, другие спеки не страдают.
// Пользователь e2e-shifts создаётся в globalSetup. Всё — в размере телефона: офис правит смены с телефона.
const PASSWORD = 'e2e-password-123';
const ADMIN = 'http://127.0.0.1:8097';

// Все тесты файла делят одни данные смен (общий файл shifts.json): идут по очереди, иначе настоящий «кто-то сохранил» ломает соседей
test.describe.configure({ mode: 'serial' });
test.use({ baseURL: ADMIN, viewport: { width: 375, height: 812 }, hasTouch: true });
test.beforeEach(() => {
  test.setTimeout(60_000);
});

async function login(page: Page) {
  await page.goto('/admin/login/');
  await page.getByLabel('Логин').fill('e2e-shifts');
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(/\/admin\/$/);
}

async function openShift(page: Page, title: string) {
  await page.goto('/admin/shifts/');
  await page.getByRole('link', { name: title, exact: true }).first().click();
  await expect(page.getByRole('heading', { level: 1, name: title })).toBeVisible();
}

const draftKeys = (page: Page) =>
  page.evaluate(() => Object.keys(window.localStorage).filter((k) => k.startsWith('draft:')));

test('«Мест нет» одной кнопкой в списке смен — и на /booking/ у карточки «Мест нет»', async ({ page }) => {
  await login(page);
  await page.getByRole('link', { name: /Смены/ }).click();
  await expect(page).toHaveURL(/\/admin\/shifts\/$/);

  const row = page.locator('.row', { hasText: 'Спасатель' });
  const closed = row.getByRole('button', { name: 'Мест нет' });
  await expect(row.getByRole('button', { name: 'Идёт набор' })).toHaveAttribute('aria-pressed', 'true');
  const box = await closed.boundingBox();
  expect(box!.height).toBeGreaterThanOrEqual(44); // кнопки крупные: попасть пальцем

  await closed.click();
  await expect(page.getByRole('status')).toContainText('Статус изменён');
  await expect(row.getByRole('button', { name: 'Мест нет' })).toHaveAttribute('aria-pressed', 'true');
  await expect(row.getByRole('button', { name: 'Идёт набор' })).toHaveAttribute('aria-pressed', 'false');

  await page.goto('/booking/');
  const card = page.locator('.shift-card', { hasText: 'Спасатель' });
  await expect(card).toContainText('Мест нет');
  await expect(card.getByRole('link', { name: 'Забронировать' })).toHaveCount(0);

  // вернуть как было
  await page.goto('/admin/shifts/');
  await page.locator('.row', { hasText: 'Спасатель' }).getByRole('button', { name: 'Идёт набор' }).click();
  await expect(page.locator('.row', { hasText: 'Спасатель' }).getByRole('button', { name: 'Идёт набор' })).toHaveAttribute('aria-pressed', 'true');
});

test('черновик: набранное в «Описание» возвращается после перезагрузки, после сохранения черновика нет', async ({ page }) => {
  await login(page);
  await openShift(page, 'Спасатель');
  const body = page.getByLabel('Описание');
  const original = await body.inputValue();
  const typed = original + '\n<p>Черновик: длинный текст, который нельзя потерять</p>';

  await body.fill(typed);
  // уход со страницы сам сохраняет черновик (без ожидания таймера)
  await page.reload();
  await expect(page.getByLabel('Описание')).toHaveValue(typed);
  await expect(page.getByText('Восстановлен черновик')).toBeVisible();

  // «Отменить» возвращает то, что на сервере
  await page.getByRole('button', { name: 'Отменить' }).click();
  await expect(page.getByLabel('Описание')).toHaveValue(original);
  await expect(page.getByText('Восстановлен черновик')).toBeHidden();
  expect(await draftKeys(page)).toEqual([]);

  // снова набрать, дождаться таймера (5 с), уйти и вернуться
  await page.getByLabel('Описание').fill(typed);
  await expect.poll(() => draftKeys(page), { timeout: 8000 }).toHaveLength(1);
  await openShift(page, 'Спасатель');
  await expect(page.getByLabel('Описание')).toHaveValue(typed);
  await expect(page.getByText('Восстановлен черновик')).toBeVisible();

  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page).toHaveURL(/\?saved=1$/);
  await expect(page.getByRole('status').first()).toContainText('Сохранено');
  await expect(page.getByRole('link', { name: 'Посмотреть на сайте' }).first()).toBeVisible();
  await expect(page.getByLabel('Описание')).toHaveValue(typed);
  expect(await draftKeys(page)).toEqual([]);
  await page.reload();
  await expect(page.getByText('Восстановлен черновик')).toBeHidden();

  // вернуть описание
  await page.getByLabel('Описание').fill(original);
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page).toHaveURL(/\?saved=1$/);
});

test('ошибка проверки: сводка со ссылками, фокус на первом неверном поле, введённое не пропадает', async ({ page }) => {
  await login(page);
  await page.goto('/admin/shifts/new/');
  await page.getByLabel('Описание').fill('Текст, который не должен пропасть');
  await page.getByRole('button', { name: 'Сохранить' }).click();

  const summary = page.locator('#error-summary');
  await expect(summary).toBeVisible();
  await expect(summary.getByRole('link', { name: 'Название' })).toBeVisible();
  await expect(page.getByLabel('Название')).toBeFocused();
  await expect(page.getByLabel('Описание')).toHaveValue('Текст, который не должен пропасть');
  await expect(page.locator('#f-title-error')).toHaveText('Заполните поле');
});

test('две вкладки: второе сохранение видит «кто-то сохранил» и свой ввод; повторное нажатие проходит', async ({ page, browser }) => {
  await login(page);
  await openShift(page, 'Пограничник');
  const other = await browser.newContext({ baseURL: ADMIN, viewport: { width: 375, height: 812 } });
  const page2 = await other.newPage();
  await login(page2);
  await openShift(page2, 'Пограничник');

  await page2.getByLabel('Коротко о смене').fill('Правка офиса');
  await page2.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page2).toHaveURL(/\?saved=1$/);

  await page.getByLabel('Коротко о смене').fill('Правка руководителя');
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.getByRole('alert').first()).toContainText('Пока вы редактировали, кто-то сохранил изменения');
  await expect(page.getByLabel('Коротко о смене')).toHaveValue('Правка руководителя');

  await page.getByRole('button', { name: 'Сохранить' }).click(); // осознанно, со свежим _hash
  await expect(page).toHaveURL(/\?saved=1$/);
  await expect(page.getByLabel('Коротко о смене')).toHaveValue('Правка руководителя');
  await other.close();
});

test('новая смена: сохранение ведёт на страницу правки, удаление — через подтверждение', async ({ page }) => {
  await login(page);
  await page.goto('/admin/shifts/');
  await page.getByRole('link', { name: 'Добавить' }).click();
  await page.getByLabel('Название').fill('Проверочная смена');
  await page.getByLabel('Дата начала').fill('2030-07-01');
  await page.getByLabel('Дата окончания').fill('2030-07-10');
  await page.getByLabel('Возраст от').fill('8');
  await page.getByLabel('Возраст до').fill('14');
  await page.getByLabel('Идёт набор').check();
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page).toHaveURL(/\/admin\/shifts\/[a-f0-9]{12}\/\?saved=1$/);
  await expect(page.getByRole('heading', { level: 1, name: 'Проверочная смена' })).toBeVisible();
  expect(await draftKeys(page)).toEqual([]); // и черновик «новой записи» убран

  await page.getByRole('link', { name: 'Удалить' }).click();
  await expect(page.getByRole('heading', { name: 'Удалить запись?' })).toBeVisible();
  await page.getByRole('button', { name: 'Да, удалить' }).click();
  await expect(page).toHaveURL(/\/admin\/shifts\/\?ok=delete$/);
  await expect(page.getByText('Проверочная смена')).toHaveCount(0);
});
