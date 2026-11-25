import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

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

  test('нет нарушений доступности (axe)', async ({ page }) => {
    await page.goto('/');
    await axe(page);
  });
});
