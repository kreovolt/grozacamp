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

test.describe('карточки смен', () => {
  for (const path of ['/', '/booking/']) {
    test(`768 px: нечётная последняя карточка на весь ряд, фото слева (${path})`, async ({ page }) => {
      await page.setViewportSize({ width: 768, height: 1024 });
      await page.goto(path);
      const cards = page.locator('[data-block="shifts"] .shift-card');
      await expect(cards).toHaveCount(3);
      const first = await cards.nth(0).boundingBox();
      const second = await cards.nth(1).boundingBox();
      const last = await cards.nth(2).boundingBox();
      expect(Math.round(last!.x)).toBe(Math.round(first!.x));
      expect(Math.round(last!.x + last!.width)).toBe(Math.round(second!.x + second!.width));
      const photo = await cards.nth(2).locator('[data-card="photo"]').boundingBox();
      const info = await cards.nth(2).locator('[data-card="info"]').boundingBox();
      expect(photo!.x + photo!.width).toBeLessThanOrEqual(info!.x + 1);
    });
  }

  test('1024 px: линия цены и кнопки у карточек одного ряда на одном уровне', async ({ page }) => {
    await page.setViewportSize({ width: 1024, height: 900 });
    await page.goto('/booking/');
    const tops = async (part: string) => page.locator(`[data-block="shifts"] .shift-card [data-card="${part}"]`)
      .evaluateAll((els) => els.map((el) => Math.round(el.getBoundingClientRect().top)));
    for (const part of ['price', 'actions']) {
      const values = await tops(part);
      expect(values).toHaveLength(3);
      expect(new Set(values).size, part).toBe(1);
    }
  });
});

test.describe('подписи кнопок в одну строку', () => {
  for (const width of [320, 1024, 1112]) {
    for (const path of ['/', '/booking/']) {
      test(`${width} px: ${path}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 });
        await page.goto(path);
        await page.evaluate(() => document.fonts.ready);
        const wrapped = await page.locator('main a.rounded-btn > span, main button.rounded-btn > span').evaluateAll((spans) =>
          spans.filter((s) => {
            const r = s.getBoundingClientRect();
            return r.width > 0 && r.height > parseFloat(getComputedStyle(s).fontSize) * 1.6;
          }).map((s) => s.textContent?.trim()));
        expect(wrapped).toEqual([]);
        expect(await horizontalOverflow(page)).toBe(0);
      });
    }
  }
});
