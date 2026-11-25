import { expect, test, type Page } from '@playwright/test';
import { PORT_METRIKA, url } from './ports';

const PAGES = ['/', '/booking/', '/info/', '/vacancies/', '/team/', '/contacts/', '/pravila/', '/policy/', '/svedeniya/', '/svedeniya/mto/', '/program/spasatel-2027/', '/net-takoy-stranicy/'];
const METRIKA = url(PORT_METRIKA);

/** Собирает нарушения CSP и ошибки консоли за время загрузки страницы. */
async function watch(page: Page): Promise<string[]> {
  const problems: string[] = [];
  page.on('console', (m) => {
    // «Failed to load resource: 404» — браузер сообщает об ответе самой страницы 404; нарушения CSP приходят отдельно
    if (m.type() === 'error' && !/status of 404/.test(m.text())) problems.push(`console: ${m.text()}`);
  });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  await page.addInitScript(() => {
    document.addEventListener('securitypolicyviolation', (e) => {
      console.error(`CSP: ${e.violatedDirective} ${e.blockedURI}`);
    });
  });
  return problems;
}

test.describe('CSP не ломает страницы', () => {
  for (const path of PAGES) {
    test(`без нарушений: ${path}`, async ({ page }) => {
      const problems = await watch(page);
      const res = await page.goto(path);
      await page.waitForLoadState('networkidle');
      expect(res?.headers()['content-security-policy']).toContain("default-src 'self'");
      expect(problems).toEqual([]);
    });
  }

  test('меню и скрипты работают под CSP (бургер открывается)', async ({ page }) => {
    const problems = await watch(page);
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/');
    await page.getByRole('button', { name: /меню/i }).first().click();
    await expect(page.getByRole('navigation').first()).toBeVisible();
    expect(problems).toEqual([]);
  });
});

test.describe('Метрика подключена', () => {
  test.beforeEach(async ({ page }) => {
    // сеть к Яндексу не нужна: проверяем, что CSP и разметка её допускают
    await page.route('https://mc.yandex.ru/**', (route) => route.fulfill({ status: 200, contentType: 'application/javascript', body: '' }));
  });

  test('уведомление о cookie: «Понятно» запоминается', async ({ page }) => {
    await page.goto(`${METRIKA}/`);
    const notice = page.locator('[data-cookie-notice]');
    await expect(notice).toBeVisible();
    await expect(notice.getByRole('link', { name: /политик/i })).toHaveAttribute('href', '/policy/');
    await notice.getByRole('button', { name: 'Понятно' }).click();
    await expect(notice).toBeHidden();
    await page.reload();
    await expect(notice).toBeHidden();
  });

  test('CSP допускает Метрику, нарушений нет', async ({ page }) => {
    const problems = await watch(page);
    const res = await page.goto(`${METRIKA}/booking/`);
    await page.waitForLoadState('networkidle');
    expect(res?.headers()['content-security-policy']).toContain('https://mc.yandex.ru');
    expect(problems).toEqual([]);
  });
});

test('без Метрики уведомления о cookie нет', async ({ page }) => {
  await page.goto('/');
  await expect(page.locator('[data-cookie-notice]')).toHaveCount(0);
});
