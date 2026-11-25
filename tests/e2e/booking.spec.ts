import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';

for (const [name, width, height] of [['десктоп', 1440, 900], ['телефон', 375, 812]] as const) {
  test.describe(`${name} ${width}×${height}`, () => {
    test.use({ viewport: { width, height } });
    for (const url of ['/booking/', '/program/shturmovik-2027/', '/program/pogranichnik-2027/']) {
      test(`${url}: нет нарушений доступности (axe) и горизонтальной прокрутки`, async ({ page }) => {
        await page.goto(url);
        const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target).join(' | ')}`)).toEqual([]);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
      });
    }
  });
}
