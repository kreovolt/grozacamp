import { expect, test, type Page } from '@playwright/test';
import { PORT_METRIKA, url } from './ports';

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

test('375 px: фото «Как проходит смена» — лента с прокруткой вбок, снимки не меньше половины экрана', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/');
  const strip = page.locator('[data-block="steps"] .photo-strip');
  const widths = await strip.locator('figure').evaluateAll((els) => els.map((el) => el.getBoundingClientRect().width));
  expect(widths).toHaveLength(3);
  for (const w of widths) expect(w).toBeGreaterThanOrEqual(375 * 0.5);
  // есть что листать — и Tab до ленты доходит
  expect(await strip.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(true);
  await expect(strip).toHaveAttribute('tabindex', '0');
  expect(await horizontalOverflow(page)).toBe(0);
});

test('320 px: команда в одну колонку, фото слева, слова описаний не рвутся посередине', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 800 });
  await page.goto('/team/');
  const member = page.locator('.team-member').first();
  const photo = await member.locator('div').first().boundingBox();
  const name = await member.locator('p').first().boundingBox();
  expect(name!.x).toBeGreaterThan(photo!.x + photo!.width);
  expect(Math.round(name!.y)).toBe(Math.round(photo!.y));
});

test.describe('кнопка «Наверх» ничего не закрывает', () => {
  for (const viewport of [{ width: 320, height: 640 }, { width: 768, height: 1024 }, { width: 1024, height: 768 }, { width: 1366, height: 768 }]) {
    test(`${viewport.width} px: в конце страницы не лежит на правовых ссылках подвала`, async ({ page }) => {
      await page.setViewportSize(viewport);
      await page.goto('/');
      await page.evaluate(() => window.scrollTo(0, document.documentElement.scrollHeight));
      const up = page.getByRole('link', { name: 'Наверх' });
      await expect(up).toBeVisible();
      const button = (await up.boundingBox())!;
      for (const link of await page.locator('[data-footer-bottom] a, [data-footer-bottom] p').all()) {
        const b = (await link.boundingBox())!;
        const overlaps = b.x < button.x + button.width && b.x + b.width > button.x && b.y < button.y + button.height && b.y + b.height > button.y;
        expect(overlaps, await link.innerText()).toBe(false);
      }
    });
  }

  test('375 px: с уведомлением о cookie кнопка стоит над ним, уведомление компактное', async ({ page }) => {
    await page.setViewportSize({ width: 375, height: 812 });
    await page.goto(url(PORT_METRIKA) + '/');
    const notice = page.locator('[data-cookie-notice]');
    await expect(notice).toBeVisible();
    await page.evaluate(() => window.scrollTo(0, 3000));
    const up = page.getByRole('link', { name: 'Наверх' });
    await expect(up).toBeVisible();
    const box = (await notice.boundingBox())!;
    await expect.poll(async () => { const b = (await up.boundingBox())!; return b.y + b.height; }).toBeLessThanOrEqual(box.y);
    expect(box.height).toBeLessThanOrEqual(812 * 0.2);
  });
});

test('375 px: у ссылок подвала зона нажатия выше текста (44 px)', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/');
  const link = page.locator('footer a[href="/vacancies/"]').first();
  await link.scrollIntoViewIfNeeded();
  const hit = await link.evaluate((a) => {
    const r = a.getBoundingClientRect();
    const probe = (dy: number) => { const el = document.elementFromPoint(r.left + 10, r.top + r.height / 2 + dy); return el === a || a.contains(el); };
    return { above: probe(-(22 - r.height / 2) + 1), below: probe(22 - r.height / 2 - 1) };
  });
  expect(hit).toEqual({ above: true, below: true });
});

test.describe('широкие мониторы', () => {
  test('1920×1080: контейнер шире 1320 px, герой с полосой цифр занимают первый экран, заголовок в три строки', async ({ page }) => {
    await page.setViewportSize({ width: 1920, height: 1080 });
    await page.goto('/');
    await page.evaluate(() => document.fonts.ready);
    const wrap = await page.locator('[data-block="hero"] .wrap').boundingBox();
    expect(wrap!.width).toBeGreaterThan(1380);
    const stats = await page.locator('[data-block="stats"]').boundingBox();
    const bottom = stats!.y + stats!.height;
    expect(bottom).toBeGreaterThan(1080 - 60);
    expect(bottom).toBeLessThanOrEqual(1080 + 4);
    const title = page.getByRole('heading', { level: 1 });
    const lines = await title.evaluate((h) => Math.round(h.getBoundingClientRect().height / parseFloat(getComputedStyle(h).lineHeight)));
    expect(lines).toBe(3);
  });

  test('3440 px: топо-линии в широком варианте (2400 × 500), обычный спрятан', async ({ page }) => {
    await page.setViewportSize({ width: 3440, height: 1440 });
    await page.goto('/');
    const hero = page.locator('[data-block="hero"]');
    await expect(hero.locator('svg.topo[viewBox="0 0 2400 500"]')).toBeVisible();
    await expect(hero.locator('svg.topo[viewBox="0 0 1200 500"]')).toBeHidden();
  });
});

test('320 px: «Позвонить» и бургер в шапке не заходят на правое поле контейнера', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 640 });
  await page.goto('/');
  await page.evaluate(() => document.fonts.ready);
  expect(await gutterOverflow(page, '[data-header] .wrap')).toBeLessThanOrEqual(0);
});
