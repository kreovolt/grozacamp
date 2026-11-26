import { expect, test, type Page } from '@playwright/test';

// Вёрстка на краевых ширинах (аудит адаптивности, .superpowers/…/layout-audit.md): без горизонтальной прокрутки,
// ничего не обрезано и не залезает на поля контейнера .wrap.

// PHP-сервер однопоточный: страницы под параллельной нагрузкой отвечают небыстро
test.beforeEach(() => {
  test.setTimeout(90_000);
});

/** Ширина документа минус ширина окна: > 0 — страница прокручивается вбок. */
async function horizontalOverflow(page: Page): Promise<number> {
  return page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
}

/** Насколько содержимое блока вылезает за поля его .wrap (0 — всё внутри). */
async function gutterOverflow(page: Page, selector: string): Promise<number> {
  return page.locator(selector).first().evaluate((root) => {
    const wrap = root.closest('.wrap') ?? root.querySelector('.wrap') ?? root;
    const cs = getComputedStyle(wrap);
    const r = wrap.getBoundingClientRect();
    const left = r.left + parseFloat(cs.paddingLeft);
    const right = r.right - parseFloat(cs.paddingRight);
    let worst = 0;
    for (const el of wrap.querySelectorAll('h1, h2, h3, p, a, button, table')) {
      const b = el.getBoundingClientRect();
      if (!b.width) continue;
      worst = Math.max(worst, b.right - right, left - b.left);
    }
    return Math.round(worst);
  });
}

test.describe('без горизонтальной прокрутки', () => {
  for (const width of [320, 1024]) {
    for (const path of ['/', '/booking/', '/svedeniya/ob-organizacii/', '/team/', '/info/']) {
      test(`${width} px: ${path}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(path);
        await page.evaluate(() => document.fonts.ready);
        expect(await horizontalOverflow(page)).toBe(0);
      });
    }
  }
});

test('320 px: финальный призыв на главной целиком в контейнере (заголовок, текст, телефон не обрезаны)', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 700 });
  await page.goto('/');
  await page.evaluate(() => document.fonts.ready);
  expect(await gutterOverflow(page, '[data-block="cta"] .wrap')).toBeLessThanOrEqual(0);
});

test('1024 px: таблица состава в Сведениях помещается в колонку рядом с оглавлением', async ({ page }) => {
  await page.setViewportSize({ width: 1024, height: 900 });
  await page.goto('/svedeniya/ob-organizacii/');
  await page.evaluate(() => document.fonts.ready);
  const fits = await page.locator('#sostav .table-scroll').evaluate((el) => el.scrollWidth <= el.clientWidth);
  expect(fits).toBe(true);
});

test('телефон боком 844×390: фото героя — полоса, заголовок виден на первом экране', async ({ page }) => {
  await page.setViewportSize({ width: 844, height: 390 });
  await page.goto('/');
  const photo = await page.locator('[data-block="hero"] .hero-photo').boundingBox();
  const title = await page.getByRole('heading', { level: 1 }).boundingBox();
  expect(photo!.height).toBeLessThanOrEqual(390 * 0.45);
  expect(title!.y).toBeLessThan(390);
});
