import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

// Подписи и порядок — спека §5.2 (те же, что в tests/php/Unit/View/NavTest.php)
const MAIN = ['Купить путёвку', 'Информация для родителей', 'Вакансии', 'Сведения об организации отдыха детей и их оздоровления'];
const SVEDENIYA = [
  'Об организации отдыха детей и их оздоровления',
  'Основные сведения',
  'Документы',
  'Руководство',
  'Педагогический и вожатский состав',
  'Контакты',
  'Деятельность',
  'Материально-техническое обеспечение и оснащённость',
  'Услуги, в том числе платные',
  'Доступная среда',
  'Иная информация',
];
const SVEDENIYA_BUTTON = { name: 'Сведения об организации отдыха детей и их оздоровления' };

async function axe(page: Page) {
  const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
  expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([]);
}

test.describe('десктоп 1440×900', () => {
  test.use({ viewport: { width: 1440, height: 900 } });

  test.beforeEach(async ({ page }) => {
    await page.goto('/vacancies/');
  });

  test('в шапке четыре пункта в порядке спеки, бургера нет', async ({ page }) => {
    const nav = page.getByRole('navigation', { name: 'Основное меню' });
    await expect(nav.locator(':scope > ul > li > :is(a, button)')).toHaveText(MAIN);
    await expect(nav.getByRole('link', { name: 'Купить путёвку' })).toHaveAttribute('href', '/booking/');
    await expect(nav.getByRole('link', { name: 'Вакансии' })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('button', { name: 'Меню', exact: true })).toBeHidden();
  });

  test('верхняя полоса: телефон, WhatsApp, ВКонтакте, часы офиса', async ({ page }) => {
    const bar = page.locator('[data-topbar]');
    await expect(bar.getByRole('link', { name: '+7 (342) 247-43-47' })).toHaveAttribute('href', 'tel:+73422474347');
    await expect(bar.getByRole('link', { name: 'WhatsApp' })).toHaveAttribute('href', 'https://wa.me/79991239300');
    await expect(bar.getByRole('link', { name: 'ВКонтакте' })).toHaveAttribute('href', 'https://vk.com/grozacamp');
    await expect(bar).toContainText('Пн–Пт 10:00–19:00');
  });

  test('верхняя полоса: «Вход для сотрудников» ведёт в админку, в меню его нет', async ({ page }) => {
    const bar = page.locator('[data-topbar]');
    await expect(bar.getByRole('link', { name: 'Вход для сотрудников' })).toHaveAttribute('href', '/admin/');
    await expect(page.getByRole('navigation', { name: 'Основное меню' }).getByRole('link', { name: 'Вход для сотрудников' })).toHaveCount(0);
  });

  test('шапка липкая: после прокрутки меню остаётся сверху, верхняя полоса уезжает', async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => window.scrollTo(0, 1500));
    await expect.poll(async () => (await page.locator('header').boundingBox())?.y).toBe(0);
    await expect(page.getByRole('navigation', { name: 'Основное меню' })).toBeInViewport();
    await expect(page.locator('[data-topbar]')).not.toBeInViewport();
  });

  test('якорь не прячется под шапкой', async ({ page }) => {
    await page.goto('/booking/#zayavka');
    const header = await page.locator('header').boundingBox();
    await expect.poll(async () => (await page.locator('#zayavka').boundingBox())?.y ?? 0).toBeGreaterThanOrEqual(header!.height);
  });

  test('клик по «Сведения…» открывает список разделов, клик мимо закрывает', async ({ page }) => {
    const button = page.getByRole('button', SVEDENIYA_BUTTON);
    const panel = page.locator(`#${await button.getAttribute('aria-controls')}`);
    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await expect(panel).toBeHidden();

    await button.click();
    await expect(button).toHaveAttribute('aria-expanded', 'true');
    await expect(panel).toBeVisible();
    await expect(panel.locator(':scope > ul a')).toHaveText(SVEDENIYA);
    await expect(panel.getByRole('link', { name: 'Все сведения' })).toHaveAttribute('href', '/svedeniya/');

    await page.getByRole('heading', { level: 1 }).click();
    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await expect(panel).toBeHidden();
  });

  test('Enter открывает, Tab идёт по пунктам, Esc закрывает и возвращает фокус на кнопку', async ({ page }) => {
    const button = page.getByRole('button', SVEDENIYA_BUTTON);
    const panel = page.locator(`#${await button.getAttribute('aria-controls')}`);

    await button.focus();
    await page.keyboard.press('Enter');
    await expect(button).toHaveAttribute('aria-expanded', 'true');

    await page.keyboard.press('Tab');
    await expect(panel.getByRole('link', { name: SVEDENIYA[0] })).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(panel.getByRole('link', { name: SVEDENIYA[1] })).toBeFocused();

    await page.keyboard.press('Escape');
    await expect(button).toHaveAttribute('aria-expanded', 'false');
    await expect(panel).toBeHidden();
    await expect(button).toBeFocused();
  });

  test('пробел открывает, уход фокуса из списка закрывает', async ({ page }) => {
    const button = page.getByRole('button', SVEDENIYA_BUTTON);
    await button.focus();
    await page.keyboard.press(' ');
    await expect(button).toHaveAttribute('aria-expanded', 'true');

    await page.keyboard.press('Shift+Tab'); // назад, на «Вакансии»
    await expect(button).toHaveAttribute('aria-expanded', 'false');
  });

  test('наведение открывает список', async ({ page }) => {
    const button = page.getByRole('button', SVEDENIYA_BUTTON);
    await button.hover();
    await expect(button).toHaveAttribute('aria-expanded', 'true');
  });

  test('нет нарушений доступности (axe), в том числе с открытым списком', async ({ page }) => {
    await axe(page);
    await page.getByRole('button', SVEDENIYA_BUTTON).click();
    await axe(page);
  });
});

test.describe('граница бургера', () => {
  test('1200 px: меню целиком в одну строку шапки', async ({ page }) => {
    await page.setViewportSize({ width: 1200, height: 800 });
    await page.goto('/');
    const nav = page.getByRole('navigation', { name: 'Основное меню' });
    await expect(nav).toBeVisible();
    const header = await page.locator('header').boundingBox();
    expect(header?.height).toBeLessThanOrEqual(81);
    const boxes = await nav.locator(':scope > ul > li').evaluateAll((els) => els.map((el) => el.getBoundingClientRect().top));
    expect(Math.max(...boxes) - Math.min(...boxes)).toBeLessThan(20);
    // «Купить путёвку», «Информация для родителей», «Вакансии» — каждая подпись в одну строку
    const lines = await nav.locator(':scope > ul > li > a').evaluateAll((els) =>
      els.map((el) => (el.querySelector('span') ?? el).getClientRects().length));
    expect(lines).toEqual([1, 1, 1]);
  });

  test('1199 px: бургер вместо меню', async ({ page }) => {
    await page.setViewportSize({ width: 1199, height: 800 });
    await page.goto('/');
    await expect(page.getByRole('button', { name: 'Меню', exact: true })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Основное меню' })).toBeHidden();
  });
});

test.describe('телефон 375×812', () => {
  test.use({ viewport: { width: 375, height: 812 }, hasTouch: true });

  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('ссылка «Позвонить» в шапке звонит в офис', async ({ page }) => {
    await expect(page.getByRole('link', { name: 'Позвонить', exact: true })).toHaveAttribute('href', 'tel:+73422474347');
  });

  test('бургер открывает меню, «Сведения…» раскрываются аккордеоном с 11 пунктами', async ({ page }) => {
    const burger = page.getByRole('button', { name: 'Меню', exact: true });
    await expect(burger).toHaveAttribute('aria-expanded', 'false');
    await burger.click();
    await expect(burger).toHaveAttribute('aria-expanded', 'true');

    const menu = page.getByRole('dialog', { name: 'Меню' });
    await expect(menu).toBeVisible();
    const nav = menu.getByRole('navigation', { name: 'Основное меню' });
    await expect(nav.locator(':scope > ul > li > :is(a, button)')).toHaveText(MAIN);

    const accordion = menu.getByRole('button', SVEDENIYA_BUTTON);
    await expect(accordion).toHaveAttribute('aria-expanded', 'false');
    await accordion.click();
    await expect(accordion).toHaveAttribute('aria-expanded', 'true');
    const items = menu.locator('[data-menu-list] a');
    await expect(items).toHaveCount(11);
    await expect(items).toHaveText(SVEDENIYA);
    await expect(items.first()).toBeVisible();
  });

  test('в меню есть «Вход для сотрудников» — ссылка на админку', async ({ page }) => {
    await page.getByRole('button', { name: 'Меню', exact: true }).click();
    const link = page.getByRole('dialog', { name: 'Меню' }).getByRole('link', { name: 'Вход для сотрудников' });
    await expect(link).toBeVisible();
    await expect(link).toHaveAttribute('href', '/admin/');
  });

  test('шапка с «Позвонить» и бургером остаётся сверху при прокрутке', async ({ page }) => {
    await page.evaluate(() => window.scrollTo(0, 1500));
    await expect.poll(async () => (await page.locator('header').boundingBox())?.y).toBe(0);
    await expect(page.getByRole('button', { name: 'Меню', exact: true })).toBeInViewport();
  });

  test('Esc закрывает меню и возвращает фокус на бургер', async ({ page }) => {
    const burger = page.getByRole('button', { name: 'Меню', exact: true });
    await burger.click();
    await expect(page.getByRole('dialog', { name: 'Меню' })).toBeVisible();
    await page.keyboard.press('Escape');
    await expect(page.getByRole('dialog', { name: 'Меню' })).toBeHidden();
    await expect(burger).toBeFocused();
  });

  test('нет нарушений доступности (axe), в том числе с открытым меню', async ({ page }) => {
    await axe(page);
    await page.getByRole('button', { name: 'Меню', exact: true }).click();
    await page.getByRole('dialog', { name: 'Меню' }).getByRole('button', SVEDENIYA_BUTTON).click();
    await axe(page);
  });
});

test.describe('кнопка «Наверх»', () => {
  for (const viewport of [{ width: 1440, height: 900 }, { width: 375, height: 812 }]) {
    test(`${viewport.width} px: спрятана вверху, появляется после прокрутки и возвращает в начало`, async ({ page }) => {
      await page.setViewportSize(viewport);
      await page.goto('/');
      const up = page.getByRole('link', { name: 'Наверх' });
      await expect(up).toBeHidden();

      await page.evaluate(() => window.scrollTo(0, 3000));
      await expect(up).toBeVisible();
      await expect(up).toBeInViewport();
      await up.click();
      await expect.poll(() => page.evaluate(() => window.scrollY)).toBe(0);
      await expect(up).toBeHidden();
      expect(new URL(page.url()).hash).toBe('');
    });
  }
});
