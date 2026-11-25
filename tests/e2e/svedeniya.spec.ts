import AxeBuilder from '@axe-core/playwright';
import { expect, test } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const PAGES = [
  '/svedeniya/',
  '/svedeniya/ob-organizacii/',
  '/svedeniya/deyatelnost/',
  '/svedeniya/mto/',
  '/svedeniya/uslugi/',
  '/svedeniya/dostupnaya-sreda/',
  '/svedeniya/inaya-informaciya/',
];

for (const [name, viewport] of [['десктоп 1440×900', { width: 1440, height: 900 }], ['телефон 375×812', { width: 375, height: 812 }]] as const) {
  test.describe(`сведения, ${name}`, () => {
    test.use({ viewport });

    for (const path of PAGES) {
      test(`${path}: без горизонтальной прокрутки и нарушений доступности (axe)`, async ({ page }) => {
        await page.goto(path);
        const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
        expect(overflow, 'нет горизонтальной прокрутки').toBeLessThanOrEqual(0);
        const { violations } = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag21aa']).analyze();
        expect(violations.map((v) => `${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([]);

        // снимки для проверки глазами: SHOTS=путь_к_папке npm run e2e -- svedeniya
        const dir = process.env.SHOTS;
        if (dir) {
          mkdirSync(dir, { recursive: true });
          await page.screenshot({ path: `${dir}/${path.replace(/^\/svedeniya\/?|\/$/g, '') || 'hub'}-${viewport.width}.png`, fullPage: true });
        }
      });
    }
  });
}

test('пункт «Доступная среда» не прячет формулировку приказа на телефоне', async ({ page }) => {
  await page.setViewportSize({ width: 375, height: 812 });
  await page.goto('/svedeniya/dostupnaya-sreda/');
  await expect(page.getByText('о допуске и условиях перемещения').first()).toBeVisible();
});
