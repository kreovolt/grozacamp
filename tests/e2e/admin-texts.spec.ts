import { expect, test, type Page } from '@playwright/test';

// Свой сервер и данные (порт 8096, storage/test-texts): тексты и настройки меняются, редактор грузит много статики.
// Всё, что тест меняет, он возвращает на место.
const PASSWORD = 'e2e-password-123';
const ADMIN = 'http://127.0.0.1:8096';

test.describe.configure({ mode: 'serial' });
test.use({ baseURL: ADMIN, viewport: { width: 1100, height: 900 } });
test.beforeEach(() => {
  test.setTimeout(60_000);
});

async function login(page: Page) {
  await page.goto('/admin/login/');
  await page.getByLabel('Логин').fill('e2e-texts');
  await page.getByLabel('Пароль').fill(PASSWORD);
  await page.getByRole('button', { name: 'Войти' }).click();
  await expect(page).toHaveURL(/\/admin\/$/);
}

/** Нарушения CSP страницы: редактор не должен добавлять <style> и inline-атрибуты. */
async function watchCsp(page: Page) {
  await page.addInitScript(() => {
    (window as unknown as { __csp: string[] }).__csp = [];
    document.addEventListener('securitypolicyviolation', (e) => {
      (window as unknown as { __csp: string[] }).__csp.push(`${e.violatedDirective} ${e.blockedURI} ${e.sample ?? ''}`);
    });
  });
}

async function cspViolations(page: Page): Promise<string[]> {
  return page.evaluate(() => (window as unknown as { __csp: string[] }).__csp);
}

test('Trix: жирный текст и список в «Родителям» сохраняются и видны на /info/', async ({ page }) => {
  const consoleCsp: string[] = [];
  page.on('console', (m) => {
    if (/content security policy|refused to/i.test(m.text())) consoleCsp.push(m.text());
  });
  await watchCsp(page);
  await login(page);
  await page.goto('/admin/texts/info/');

  const field = page.locator('#info\\.visits');
  const editor = field.locator('trix-editor');
  await expect(editor).toBeVisible();
  await expect(field.locator('trix-toolbar')).toBeVisible();
  // значок кнопки Trix — картинка data: из CSS, он применился (стили не заблокированы)
  const bg = await field.locator('.trix-button--icon-bold').evaluate((el) => getComputedStyle(el, '::before').backgroundImage);
  expect(bg).toContain('data:image/svg+xml');
  const original = await field.locator('textarea').inputValue();
  expect(original).toContain('<');

  // одно значение из текстов — заголовок h3 — должно пережить загрузку в редактор
  await page.goto('/admin/texts/legal/');
  const policy = await page.locator('#t-policy').inputValue();
  expect(policy).toContain('<h3>1. Общие положения</h3>');
  await page.goto('/admin/texts/info/');

  await editor.click();
  await page.keyboard.press('Control+A');
  await page.keyboard.type('Проверка редактора, ');
  await field.getByRole('button', { name: 'Жирный' }).click();
  await page.keyboard.type('жирное слово');
  await field.getByRole('button', { name: 'Жирный' }).click();
  await page.keyboard.press('Enter');
  await field.getByRole('button', { name: 'Маркированный список' }).click();
  await page.keyboard.type('первый пункт');
  await page.keyboard.press('Enter');
  await page.keyboard.type('второй пункт');

  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');

  await page.goto('/info/');
  const section = page.locator('#visits');
  await expect(section.locator('strong')).toHaveText('жирное слово');
  await expect(section.locator('ul li')).toHaveText(['первый пункт', 'второй пункт']);
  expect(await section.locator('script, [onclick], [style]').count()).toBe(0);

  // вернуть как было (в редактор — через значение поля, как это делает восстановление черновика)
  await page.goto('/admin/texts/info/');
  await page.locator('#info\\.visits textarea').evaluate((t, html) => {
    (t as HTMLTextAreaElement).value = html as string;
    t.dispatchEvent(new Event('input', { bubbles: true }));
  }, original);
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  await page.goto('/info/');
  await expect(page.locator('#visits')).toContainText('Навестить ребёнка можно');
  await expect(page.locator('#visits strong')).toHaveCount(0);

  expect(consoleCsp).toEqual([]);
  expect(await cspViolations(page)).toEqual([]);
});

test('страницы админки с редактором работают без нарушений CSP: тексты, сведения, смена', async ({ page }) => {
  const consoleCsp: string[] = [];
  page.on('console', (m) => {
    if (/content security policy|refused to/i.test(m.text())) consoleCsp.push(m.text());
  });
  const pageErrors: string[] = [];
  page.on('pageerror', (e) => pageErrors.push(e.message));
  await watchCsp(page);
  await login(page);

  for (const path of ['/admin/texts/home/', '/admin/texts/svedeniya/', '/admin/texts/vacancies/', '/admin/texts/legal/', '/admin/settings/', '/admin/vacancies/', '/admin/faq/']) {
    await page.goto(path);
    await expect(page.locator('h1')).toBeVisible();
  }
  // редактор в списке «Смены» (поле «Описание»)
  await page.goto('/admin/shifts/');
  await page.locator('.row__title a').first().click();
  await expect(page.locator('trix-editor').first()).toBeVisible();
  expect(await cspViolations(page)).toEqual([]);
  expect(consoleCsp).toEqual([]);
  expect(pageErrors).toEqual([]);
});

test('«Сведения»: пустой обязательный пункт без подтверждения не сохраняется', async ({ page }) => {
  await login(page);
  await page.goto('/admin/texts/svedeniya/');
  const field = page.locator('#sved\\.fullName');
  const original = await page.locator('#t-sved\\.fullName').inputValue();
  expect(original).not.toBe('');
  await expect(field).toContainText('п. 3.а приказа № 201');
  await field.locator('details summary').click();
  await expect(field.locator('details')).toContainText('полное и сокращенное (при наличии) наименование Организации отдыха');

  await page.locator('#t-sved\\.fullName').fill('');
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('#error-summary')).toBeVisible();
  await expect(page.locator('#sved\\.fullName')).toContainText('Это обязательный пункт для проверки (п. 3.а приказа № 201). Сохранить пустым?');
  await expect(page.locator('#t-sved\\.fullName')).toHaveValue('');

  // подтверждения нет — на сайте имя на месте
  await page.goto('/svedeniya/ob-organizacii/');
  await expect(page.locator('main')).toContainText(original.slice(0, 20));
});

test('настройки: телефон приводится к одному виду, изменение видно в подвале сайта', async ({ page }) => {
  await login(page);
  await page.goto('/admin/settings/');
  const weekend = page.locator('#s-hoursWeekend');
  const phone = page.locator('#s-phone');
  const oldWeekend = await weekend.inputValue();
  const oldPhone = await phone.inputValue();

  await weekend.fill('Сб–Вс 11:00–16:00 (e2e)');
  await phone.fill('8 950 441-12-99');
  await page.locator('#s-mailTo').fill('not-an-email');
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('#error-summary')).toBeVisible();
  await expect(page.locator('#mailTo .field-error')).toBeVisible();
  await expect(weekend).toHaveValue('Сб–Вс 11:00–16:00 (e2e)');

  await page.locator('#s-mailTo').fill('2474347@bk.ru');
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  await expect(phone).toHaveValue('+7 (950) 441-12-99');

  await page.goto('/');
  const footer = page.locator('footer');
  await expect(footer).toContainText('Сб–Вс 11:00–16:00 (e2e)');
  await expect(footer.locator('a[href="tel:+79504411299"]')).toHaveCount(1);

  await page.goto('/admin/settings/');
  await weekend.fill(oldWeekend);
  await phone.fill(oldPhone);
  await page.getByRole('button', { name: 'Сохранить' }).click();
  await expect(page.locator('.alert-ok')).toContainText('Сохранено');
  await page.goto('/');
  await expect(page.locator('footer')).toContainText(oldWeekend);
});

test('черновик: нетронутая форма его не создаёт, набранное в редакторе возвращается после перезагрузки', async ({ page }) => {
  await login(page);
  await page.goto('/admin/texts/info/');
  await expect(page.locator('#info\\.prepare trix-editor')).toBeVisible();
  expect(await page.locator('style').count()).toBe(0);
  await page.goto('/admin/texts/home/');
  await page.goto('/admin/texts/info/');
  await expect(page.locator('[data-draft-notice]')).toBeHidden();
  expect(await page.evaluate(() => Object.keys(localStorage).filter((k) => k.startsWith('draft:')))).toEqual([]);

  await page.locator('form[data-draft-ready]').waitFor();
  const editor = page.locator('#info\\.visits trix-editor');
  await editor.click();
  await page.keyboard.press('Control+End');
  await page.keyboard.type(' ЧЕРНОВИК-e2e');
  await page.waitForTimeout(1300);
  await page.reload();
  await expect(page.locator('[data-draft-notice]')).toBeVisible();
  await expect(page.locator('#info\\.visits trix-editor')).toContainText('ЧЕРНОВИК-e2e');

  await page.locator('[data-draft-cancel]').click();
  await expect(page.locator('#info\\.visits trix-editor')).not.toContainText('ЧЕРНОВИК-e2e');
});
