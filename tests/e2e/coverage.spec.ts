import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

// Спека §11: проверка битых ссылок; доступность (axe) — и на страницах, которых нет в quality/svedeniya.
// Основной сервер (storage/test, примерные данные).

const PASSWORD = 'e2e-password-123';

test.beforeEach(() => {
  test.setTimeout(120_000);
});

/** Все внутренние ссылки страницы: путь со строкой запроса, без метки (#…). */
async function internalLinks(page: Page): Promise<string[]> {
  return page.$$eval('a[href]', (as) => {
    const out: string[] = [];
    for (const a of as as HTMLAnchorElement[]) {
      const u = new URL(a.href, location.href);
      if (u.origin === location.origin) out.push(u.pathname + u.search);
    }
    return out;
  });
}

/** Подключённые файлы страницы (картинки, стили, скрипты): тоже должны отдаваться. */
async function assetUrls(page: Page): Promise<string[]> {
  return page.evaluate(() => {
    const out = new Set<string>();
    const add = (v: string | null) => {
      if (!v) return;
      const u = new URL(v, location.href);
      if (u.origin === location.origin) out.add(u.pathname + u.search);
    };
    document.querySelectorAll('img[src], script[src]').forEach((el) => add(el.getAttribute('src')));
    document.querySelectorAll('link[rel="stylesheet"][href], link[rel="icon"][href], link[rel="preload"][href]').forEach((el) => add(el.getAttribute('href')));
    document.querySelectorAll('source[srcset], img[srcset]').forEach((el) => {
      for (const part of (el.getAttribute('srcset') || '').split(',')) add(part.trim().split(/\s+/)[0] || null);
    });
    return [...out];
  });
}

test('битые ссылки: всё, на что ведут страницы сайта с главной, отвечает 200 (без переадресаций)', async ({ page, request }) => {
  const queue = ['/'];
  const seen = new Set<string>(queue);
  const files = new Set<string>();
  const broken: string[] = [];
  while (queue.length > 0 && seen.size < 120) {
    const path = queue.shift()!;
    const res = await request.get(path, { maxRedirects: 0 });
    if (res.status() !== 200) {
      broken.push(`${path} → ${res.status()}`);
      continue;
    }
    if (!(res.headers()['content-type'] || '').startsWith('text/html')) continue;
    await page.goto(path);
    for (const link of await internalLinks(page)) {
      if (/^\/(uploads|assets)\//.test(link)) files.add(link);
      else if (!seen.has(link) && !link.startsWith('/admin')) {
        seen.add(link);
        queue.push(link);
      }
    }
    for (const f of await assetUrls(page)) files.add(f);
  }
  for (const f of files) {
    const res = await request.get(f, { maxRedirects: 0 });
    if (res.status() !== 200) broken.push(`${f} → ${res.status()}`);
  }
  expect(seen.size, 'обход нашёл страницы').toBeGreaterThan(20);
  expect([...seen]).toEqual(expect.arrayContaining(['/booking/', '/info/', '/vacancies/', '/team/', '/contacts/', '/pravila/', '/policy/', '/svedeniya/']));
  expect(broken).toEqual([]);
});

async function axe(page: Page, label: string) {
  // контраст axe считает по цвету «как сейчас»: ждём, пока появление блоков доиграет
  await expect
    .poll(() => page.$$eval('[data-reveal], [data-word]', (els) => els.every((el) => getComputedStyle(el).opacity === '1')), { timeout: 10_000 })
    .toBe(true);
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  expect(violations.map((v) => `${label} ${v.id} (${v.impact}): ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([]);
}

for (const [name, viewport] of [['десктоп', { width: 1440, height: 900 }], ['телефон', { width: 375, height: 812 }]] as const) {
  test.describe(`axe, ${name}`, () => {
    test.use({ viewport, reducedMotion: 'reduce' });

    test('публичные страницы и 404', async ({ page }) => {
      await page.goto('/booking/');
      const shift = await page.locator('a[href^="/program/"]').first().getAttribute('href');
      for (const path of ['/vacancies/', '/team/', '/contacts/', shift!, '/pravila/', '/policy/', '/net-takoy-stranicy/']) {
        await page.goto(path);
        await axe(page, path);
      }
    });

    test('формы с ошибками', async ({ page }) => {
      await page.goto('/booking/');
      await page.waitForTimeout(3300); // сервер требует ≥ 3 с между показом формы и отправкой
      await page.getByRole('button', { name: 'Отправить заявку' }).click();
      await expect(page.locator('#booking-shift-error')).toBeVisible();
      await axe(page, '/booking/ (ошибки)');

      await page.goto('/vacancies/');
      await page.waitForTimeout(3300);
      await page.getByRole('button', { name: 'Отправить анкету' }).click();
      await expect(page.locator('#vacancy-fullName-error')).toBeVisible();
      await axe(page, '/vacancies/ (ошибки)');
    });

    test('страницы правки в админке', async ({ page }) => {
      await page.goto('/admin/login/');
      await page.getByLabel('Логин').fill('e2e-main');
      await page.getByLabel('Пароль').fill(PASSWORD);
      await page.getByRole('button', { name: 'Войти' }).click();
      await expect(page).toHaveURL(/\/admin\/$/);
      await axe(page, '/admin/');

      await page.goto('/admin/shifts/');
      await axe(page, '/admin/shifts/');
      const edit = await page.locator('.row a[href^="/admin/shifts/"]').first().getAttribute('href');
      for (const path of [edit!, '/admin/shifts/new/', '/admin/venues/', '/admin/texts/home/', '/admin/texts/info/', '/admin/settings/', '/admin/history/']) {
        await page.goto(path);
        await page.locator('form[data-draft-ready], .card, .h1').first().waitFor();
        await axe(page, path);
      }
      await page.goto('/admin/venues/');
      await page.locator('.row').first().getByRole('link', { name: 'Изменить' }).click();
      await page.locator('form[data-draft-ready]').waitFor();
      await axe(page, 'правка базы (галерея)');
    });
  });
}
