import { expect, test, type Page } from '@playwright/test';

// Пользователи создаются в globalSetup (storage/test): e2e-main — основной, e2e-pass — для смены пароля.
const PASSWORD = 'e2e-password-123';

async function login(page: Page, login: string, password = PASSWORD) {
  await page.goto('/admin/login/');
  await page.getByLabel('Логин').fill(login);
  await page.getByLabel('Пароль').fill(password);
  await page.getByRole('button', { name: 'Войти' }).click();
}

test.beforeEach(() => {
  test.setTimeout(60_000);
});

test('без входа админка отправляет на форму входа и возвращает назад', async ({ page }) => {
  await page.goto('/admin/password/');
  await expect(page).toHaveURL(/\/admin\/login\/\?next=%2Fadmin%2Fpassword%2F$/);
  await expect(page.getByRole('heading', { name: 'Вход в админку' })).toBeVisible();

  await page.getByLabel('Логин').fill('E2E-Main');
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(/\/admin\/password\/$/);
});

test('вход открывает панель, выход возвращает на форму входа', async ({ page }) => {
  await login(page, 'e2e-main');
  await expect(page).toHaveURL(/\/admin\/$/);
  await expect(page.getByRole('heading', { name: 'Админка', exact: true })).toBeVisible();
  await expect(page.getByText('Здравствуйте, Тест Основной')).toBeVisible();

  await page.getByRole('button', { name: 'Выйти' }).click();
  await expect(page).toHaveURL(/\/admin\/login\/$/);

  await page.goto('/admin/');
  await expect(page).toHaveURL(/\/admin\/login\/\?next=/);
});

test('неверный пароль и неизвестный логин — одно и то же сообщение', async ({ page }) => {
  await login(page, 'e2e-main', 'definitely-wrong-1');
  await expect(page.getByRole('alert')).toHaveText('Неверный логин или пароль');
  await expect(page.getByLabel('Пароль')).toHaveValue('');
  await expect(page).toHaveURL(/\/admin\/login\/$/);
});

test('смена пароля: старый перестаёт работать, новый работает', async ({ page, browser }) => {
  await login(page, 'e2e-pass');
  await page.goto('/admin/password/');
  await page.getByLabel('Текущий пароль').fill(PASSWORD);
  await page.getByLabel('Новый пароль', { exact: true }).fill('another-password-456');
  await page.getByLabel('Новый пароль ещё раз').fill('another-password-456');
  await page.getByRole('button', { name: 'Сменить пароль' }).click();
  await expect(page.getByRole('status')).toHaveText('Пароль изменён.');

  const other = await browser.newContext();
  const page2 = await other.newPage();
  await login(page2, 'e2e-pass'); // старый пароль
  await expect(page2.getByRole('alert')).toHaveText('Неверный логин или пароль');
  await other.close();

  await page.getByRole('button', { name: 'Выйти' }).click();
  await login(page, 'e2e-pass', 'another-password-456');
  await expect(page).toHaveURL(/\/admin\/$/);
});

test('cookie сессии: HttpOnly, SameSite=Strict, только /admin/; публичные страницы без cookie', async ({ page, context }) => {
  await page.goto('/');
  await page.goto('/booking/');
  expect(await context.cookies()).toEqual([]);

  await login(page, 'e2e-main');
  const cookie = (await context.cookies()).find((c) => c.name === 'groza_admin');
  expect(cookie).toBeTruthy();
  expect(cookie?.httpOnly).toBe(true);
  expect(cookie?.sameSite).toBe('Strict');
  expect(cookie?.path).toBe('/admin/');
});

test('ответы админки не кэшируются и закрыты от поисковиков; POST без токена — 419', async ({ page, request }) => {
  const res = await page.goto('/admin/login/');
  expect(res?.headers()['cache-control']).toContain('no-store');
  expect(res?.headers()['x-robots-tag']).toBe('noindex, nofollow');
  await expect(page.locator('meta[name=robots]')).toHaveAttribute('content', 'noindex, nofollow');

  const forged = await request.post('/admin/logout/', { form: {} });
  expect(forged.status()).toBe(419);
  expect(await forged.text()).toContain('Сессия устарела. Обновите страницу и повторите.');
});

test('строгий режим сессии: подброшенный идентификатор не принимается', async ({ page, context, baseURL }) => {
  const planted = 'attackerchosen0123456789abcdef';
  await context.addCookies([{ name: 'groza_admin', value: planted, url: `${baseURL}/admin/`, httpOnly: true, sameSite: 'Strict' }]);
  await page.goto('/admin/login/');
  const cookie = (await context.cookies()).find((c) => c.name === 'groza_admin');
  expect(cookie).toBeTruthy();
  expect(cookie?.value).not.toBe(planted);
});
