import { expect, test, type Page } from '@playwright/test';

// Свой сервер и данные (порт 8095, storage/test-history): здесь скрываются записи и возвращаются версии.
const PASSWORD = 'e2e-password-123';
const HOME = 'http://127.0.0.1:8095';

test.use({ baseURL: HOME });
test.describe.configure({ mode: 'serial' });
test.beforeEach(() => {
  test.setTimeout(60_000);
});

async function login(page: Page) {
  await page.goto('/admin/login/');
  await page.getByLabel('Логин').fill('e2e-history');
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(/\/admin\/$/);
}

test('панель: на примерных данных есть красные пункты, «Заполнить» открывает нужный редактор', async ({ page }) => {
  await login(page);
  await expect(page.getByRole('heading', { level: 1, name: 'Готовность к проверке' })).toBeVisible();
  await expect(page.locator('.readiness__summary--bad')).toContainText(/Не заполнено: \d+ обязательн/);

  // «Учредительные документы» — нет в примерных данных; ссылка ведёт в форму нового документа с этим разделом
  const item = page.locator('.readiness__item', { hasText: 'Учредительные документы' });
  await expect(item).toBeVisible();
  await item.getByRole('link', { name: 'Заполнить' }).click();
  await expect(page).toHaveURL(/\/admin\/documents\/new\/\?section=uchreditelnye$/);
  await expect(page.getByLabel('Раздел')).toHaveValue('uchreditelnye');
});

test('история: скрыли вакансию, вернули прежнюю версию — вакансия снова на сайте', async ({ page }) => {
  await login(page);
  await page.goto('/vacancies/');
  const title = (await page.locator('h2, h3').filter({ hasText: /Вожатый/ }).first().innerText()).trim();

  await page.goto('/admin/vacancies/');
  await page.locator('.row', { hasText: 'Вожатый' }).first().getByRole('button', { name: /Скрыть/ }).click();
  await expect(page.getByRole('status')).toContainText('Видимость на сайте изменена');
  await page.goto('/vacancies/');
  await expect(page.getByText(title)).toHaveCount(0);

  // на панели это видно в «Последних изменениях», по-русски
  await page.goto('/admin/');
  await expect(page.locator('.changes__item').first()).toContainText('Тест История изменил(а) видимость на сайте');

  await page.goto('/admin/history/');
  const block = page.locator('details.history', { hasText: 'Вакансии' });
  await block.locator('summary').click();
  const first = block.locator('.history__item').first();
  await expect(first).toContainText('Как было до правки: Тест История');
  await expect(first).toContainText('до последнего изменения');
  await expect(first).toContainText('Тест История изменил(а) видимость на сайте');

  // без отметки «Да, вернуть» браузер форму не отправит; сервер, если обойти, тоже откажет
  await first.getByLabel('Да, вернуть').check();
  await first.getByRole('button', { name: 'Вернуть эту версию' }).click();
  await expect(page.getByRole('status')).toContainText('Версия возвращена');

  await page.goto('/vacancies/');
  await expect(page.getByText(title).first()).toBeVisible();
});

test('резервная копия: zip с данными', async ({ page }) => {
  await login(page);
  const res = await page.request.get('/admin/backup/');
  expect(res.status()).toBe(200);
  expect(res.headers()['content-type']).toBe('application/zip');
  expect(res.headers()['content-disposition']).toMatch(/^attachment; filename="groza-backup-\d{4}-\d{2}-\d{2}\.zip"$/);
  expect(res.headers()['cache-control']).toContain('no-store');
  const body = await res.body();
  expect(body.subarray(0, 2).toString('latin1')).toBe('PK');
  const text = body.toString('latin1');
  expect(text).toContain('data/shifts.json');
  expect(text).not.toContain('data/users.json');
  expect(text).not.toContain('auth-meta');
});
