import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Locator, type Page } from '@playwright/test';

// Блоки главной по спеке §7.1, в порядке 1–12
const BLOCKS = ['hero', 'stats', 'shifts', 'kids', 'care', 'steps', 'included', 'base', 'team', 'reviews', 'faq', 'cta'];

async function axe(page: Page) {
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([]);
}

test.describe('главная, десктоп 1440×900', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('герой: заголовок, «Выбрать смену» ведёт на /booking/, фото грузится сразу', async ({ page }) => {
    const hero = page.locator('[data-block="hero"]');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Лагерь настоящих приключений');
    await expect(hero.getByRole('link', { name: 'Выбрать смену' })).toHaveAttribute('href', '/booking/');
    await expect(hero.getByRole('link', { name: 'Информация для родителей' })).toHaveAttribute('href', '/info/');
    await expect(hero.locator('img')).toHaveAttribute('loading', 'eager');
    await expect(hero.locator('img')).toHaveAttribute('fetchpriority', 'high');
  });

  test('герой на две колонки по сетке шапки: текст слева, селфи-карточка справа с полями, не пересекаются', async ({ page }) => {
    const hero = page.locator('[data-block="hero"]');
    const photo = await box(hero.locator('.hero-photo'));
    const title = await box(page.getByRole('heading', { level: 1 }));
    const block = await box(hero);
    const width = page.viewportSize()!.width;
    // правый край содержимого .wrap — тот же, что у меню в шапке
    const gridRight = await hero.locator('.hero-grid').evaluate((el) => {
      const r = el.getBoundingClientRect();
      return r.right - parseFloat(getComputedStyle(el).paddingRight);
    });

    expect(await hero.locator('img').getAttribute('src')).toMatch(/\/friends-selfie-\d+\.jpg$/);
    expect(photo.x + photo.width / 2).toBeGreaterThan(width / 2);
    expect(photo.x).toBeGreaterThanOrEqual(width * 0.4);
    expect(Math.abs(photo.x + photo.width - gridRight)).toBeLessThanOrEqual(1);
    expect(photo.x + photo.width).toBeLessThan(width - 16);
    // карточка целиком внутри блока, с полями сверху и снизу, пропорции фото 4 : 3
    expect(photo.y).toBeGreaterThan(block.y + 16);
    expect(photo.y + photo.height).toBeLessThan(block.y + block.height - 16);
    expect(photo.width / photo.height).toBeCloseTo(4 / 3, 1);
    expect(title.x + title.width).toBeLessThanOrEqual(width / 2);
    expect(title.x + title.width).toBeLessThanOrEqual(photo.x);
  });

  test('полоса цифр: четыре пункта, без «24/7»', async ({ page }) => {
    const stats = page.locator('[data-block="stats"]');
    await expect(stats.locator('li')).toHaveCount(4);
    await expect(stats).not.toContainText('24/7');
  });

  test('двенадцать блоков в порядке спеки', async ({ page }) => {
    const names = await page.locator('[data-block]').evaluateAll((els) => els.map((el) => el.getAttribute('data-block')));
    expect(names).toEqual(BLOCKS);
  });

  test('карточки смен: у закрытой нет «Забронировать», у открытой — ссылка на заявку', async ({ page }) => {
    const cards = page.locator('[data-block="shifts"] .shift-card');
    await expect(cards).toHaveCount(3);
    const open = cards.filter({ hasText: 'Спасатель' });
    await expect(open.getByRole('link', { name: 'Забронировать' })).toHaveAttribute('href', '/booking/?smena=spasatel-2027#zayavka');
    const closed = cards.filter({ hasText: 'Пограничник' });
    await expect(closed).toContainText('Мест нет');
    await expect(closed.getByRole('link', { name: 'Забронировать' })).toHaveCount(0);
  });

  test('распорядок дня и вопросы раскрываются', async ({ page }) => {
    const schedule = page.locator('[data-block="steps"] details');
    await expect(schedule.getByText('«Свечка», вечерний сбор отряда')).toBeHidden();
    await schedule.locator('summary').click();
    await expect(schedule.getByText('«Свечка», вечерний сбор отряда')).toBeVisible();

    const faq = page.locator('[data-block="faq"] details');
    await expect(faq).toHaveCount(5);
    await faq.first().locator('summary').click();
    await expect(faq.first()).toHaveAttribute('open', '');
  });

  test('нет нарушений доступности (axe)', async ({ page }) => {
    await axe(page);
  });
});

test.describe('главная, телефон 375×812', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('нет горизонтальной прокрутки, первый экран с заголовком и кнопкой', async ({ page }) => {
    await page.goto('/');
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
    expect(overflow).toBe(0);
    await expect(page.getByRole('heading', { level: 1 })).toBeInViewport();
    await expect(page.locator('[data-block="hero"]').getByRole('link', { name: 'Выбрать смену' })).toBeInViewport();
  });

  test('герой: сначала селфи, под ним заголовок', async ({ page }) => {
    await page.goto('/');
    const photo = await box(page.locator('[data-block="hero"] img'));
    const title = await box(page.getByRole('heading', { level: 1 }));

    expect(photo.y + photo.height).toBeLessThanOrEqual(title.y);
    expect(photo.width).toBeGreaterThanOrEqual(375 - 1);
  });

  test('вес первой загрузки ≤ 1,5 МБ, фото героя ≤ 200 КБ (спека: бюджеты)', async ({ page }) => {
    const sizes = new Map<string, number>();
    const pending: Promise<void>[] = [];
    page.on('response', (res) => {
      pending.push(res.body().then((b) => void sizes.set(res.url(), b.length), () => undefined));
    });
    await page.goto('/', { waitUntil: 'networkidle' });
    await Promise.all(pending);

    const total = [...sizes.values()].reduce((sum, n) => sum + n, 0);
    const hero = [...sizes].filter(([url]) => /\/friends-selfie-\d+\.(avif|webp|jpg)$/.test(url));
    expect(total).toBeLessThanOrEqual(1.5 * 1024 * 1024);
    expect(hero).toHaveLength(1);
    expect(hero[0][1]).toBeLessThanOrEqual(200 * 1024);
  });

  test('нет нарушений доступности (axe)', async ({ page }) => {
    await page.goto('/');
    await axe(page);
  });
});

async function box(locator: Locator) {
  const b = await locator.boundingBox();
  expect(b, 'элемент виден').not.toBeNull();
  return b!;
}
