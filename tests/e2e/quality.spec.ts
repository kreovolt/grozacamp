import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';
import { createHash } from 'node:crypto';
import { existsSync, readFileSync } from 'node:fs';
import { gzipSync } from 'node:zlib';

const REVEAL_PAGES = ['/', '/booking/', '/info/', '/vacancies/', '/team/'];

async function scrollThrough(page: Page) {
  await page.evaluate(async () => {
    const height = document.documentElement.scrollHeight;
    for (let y = 0; y <= height; y += 600) {
      window.scrollTo(0, y);
      await new Promise((r) => setTimeout(r, 100));
    }
    await new Promise((r) => setTimeout(r, 1000)); // дать срабатывать триггерам внизу страницы
    window.scrollTo(0, 0);
  });
}

// PHP-сервер однопоточный: страницы с фото под параллельной нагрузкой отвечают небыстро
test.beforeEach(() => {
  test.setTimeout(90_000);
});

async function revealOpacities(page: Page): Promise<string[]> {
  return page.$$eval('[data-reveal]', (els) => els.map((el) => getComputedStyle(el).opacity));
}

test.describe('анимации: всё видно без движения', () => {
  test.describe('prefers-reduced-motion', () => {
    test.use({ reducedMotion: 'reduce' });
    for (const path of REVEAL_PAGES) {
      test(`reduced motion shows everything: ${path}`, async ({ page }) => {
        await page.goto(path);
        await page.waitForLoadState('networkidle');
        const opacities = await revealOpacities(page);
        expect(opacities.length).toBeGreaterThan(0);
        expect(opacities.every((o) => o === '1')).toBe(true);
        expect(await page.locator('h1').first().evaluate((h) => h.querySelectorAll('[data-word]').length)).toBe(0);
      });
    }
  });

  test.describe('без JavaScript', () => {
    test.use({ javaScriptEnabled: false });
    for (const path of REVEAL_PAGES) {
      test(`no js shows everything: ${path}`, async ({ page }) => {
        await page.goto(path);
        const opacities = await revealOpacities(page);
        expect(opacities.length).toBeGreaterThan(0);
        expect(opacities.every((o) => o === '1')).toBe(true);
      });
    }
  });

  test('с движением: блоки ниже первого экрана появляются при прокрутке', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    const before = await revealOpacities(page);
    expect(before.some((o) => o === '0')).toBe(true);
    await page.evaluate(async () => {
      for (let y = 0; y <= document.documentElement.scrollHeight; y += 600) {
        window.scrollTo(0, y);
        await new Promise((r) => setTimeout(r, 100));
      }
    });
    // остаёмся внизу и ждём: время срабатывания зависит от нагрузки на машину
    await expect.poll(async () => (await revealOpacities(page)).every((o) => o === '1'), { timeout: 10_000 }).toBe(true);
  });

  test('заголовок героя виден сразу и анимация укладывается в 0,8 с', async ({ page }) => {
    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/', { waitUntil: 'domcontentloaded' });
    const h1 = page.locator('h1#hero-title');
    // виден в исходной разметке: ни одно слово не прозрачно до конца
    expect(await h1.evaluate((el) => getComputedStyle(el).opacity)).toBe('1');
    await page.waitForFunction(() => document.querySelectorAll('#hero-title [data-word]').length > 0);
    const minStart = await h1.evaluate((el) => Math.min(...Array.from(el.querySelectorAll<HTMLElement>('[data-word]')).map((w) => Number(getComputedStyle(w).opacity))));
    expect(minStart).toBeGreaterThanOrEqual(0.3);
    await page.waitForTimeout(1000);
    expect(await h1.evaluate((el) => Array.from(el.querySelectorAll<HTMLElement>('[data-word]')).every((w) => getComputedStyle(w).opacity === '1'))).toBe(true);
  });

  test('GSAP грузится только там, где есть [data-reveal]', async ({ page, request }) => {
    for (const path of ['/svedeniya/', '/svedeniya/ob-organizacii/', '/contacts/']) {
      await page.goto(path);
      expect(await page.locator('[data-reveal]').count()).toBe(0);
      expect(await page.locator('script[src*="motion.js"]').count()).toBe(0);
    }
    await page.goto('/');
    expect(await page.locator('script[src*="motion.js"]').count()).toBe(1);
    const manifest = JSON.parse(readFileSync('public/assets/manifest.json', 'utf8'));
    expect(manifest['/assets/js/motion.js']).toBeTruthy();
    const main = await request.get(`/assets/js/main.js`);
    expect((await main.text()).includes('ScrollTrigger')).toBe(false);
  });
});

test.describe('бюджеты скорости', () => {
  test.use({ viewport: { width: 375, height: 812 } });

  test('home weight budget', async ({ page }) => {
    const sizes: { url: string; size: number }[] = [];
    page.on('response', async (res) => {
      const body = await res.body().catch(() => null);
      const len = Number(res.headers()['content-length'] ?? body?.length ?? 0);
      sizes.push({ url: res.url(), size: len || (body?.length ?? 0) });
    });
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    const total = sizes.reduce((s, r) => s + r.size, 0);
    const hero = sizes.filter((r) => r.url.includes('friends-selfie'));
    expect(hero.length).toBeGreaterThan(0);
    expect(Math.max(...hero.map((r) => r.size))).toBeLessThanOrEqual(200_000);
    expect(total).toBeLessThanOrEqual(1_500_000);
  });

  test('размер бандлов: main.js и motion.js', async () => {
    const gz = (f: string) => gzipSync(readFileSync(f)).length;
    expect(gz('public/assets/js/main.js')).toBeLessThanOrEqual(12_000);
    expect(gz('public/assets/js/motion.js')).toBeLessThanOrEqual(48_000);
  });
});

test('only own hosts', async ({ page }) => {
  const foreign: string[] = [];
  page.on('request', (req) => {
    const u = new URL(req.url());
    if (!/^(127\.0\.0\.1|localhost)$/.test(u.hostname) && u.protocol.startsWith('http')) foreign.push(req.url());
  });
  for (const path of ['/', '/booking/', '/svedeniya/ob-organizacii/']) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await scrollThrough(page);
  }
  expect(foreign).toEqual([]);
});

test('axe has no serious issues', async ({ page }) => {
  for (const path of ['/', '/booking/', '/info/', '/svedeniya/ob-organizacii/']) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await scrollThrough(page);
    await page.waitForTimeout(700);
    const { violations } = await new AxeBuilder({ page }).analyze();
    const bad = violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    expect(bad.map((v) => `${path} ${v.id}: ${v.nodes.map((n) => n.target.join(' ')).join(', ')}`)).toEqual([]);
  }
});

test('no console errors', async ({ page }) => {
  const problems: string[] = [];
  page.on('console', (m) => {
    if (m.type() === 'error' || m.type() === 'warning') problems.push(`console: ${m.text()}`);
  });
  page.on('pageerror', (e) => problems.push(`pageerror: ${e.message}`));
  for (const path of REVEAL_PAGES) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await scrollThrough(page);
  }
  expect(problems).toEqual([]);
});

test('manifest: каждый файл есть и хеш совпадает (не забыт npm run build)', () => {
  const manifest: Record<string, string> = JSON.parse(readFileSync('public/assets/manifest.json', 'utf8'));
  expect(Object.keys(manifest).length).toBeGreaterThan(3);
  for (const [path, hash] of Object.entries(manifest)) {
    const file = `public${path}`;
    expect(existsSync(file), `${file} существует`).toBe(true);
    const actual = createHash('sha256').update(readFileSync(file)).digest('hex').slice(0, 10);
    expect(actual, `${path} хеш`).toBe(hash);
  }
});

test.describe('главная: нет горизонтальной прокрутки и наложений героя', () => {
  for (const [width, height] of [[1024, 768], [1280, 800], [1920, 1080]]) {
    test(`ширина ${width}`, async ({ page }) => {
      await page.setViewportSize({ width, height });
      await page.goto('/');
      await page.waitForLoadState('networkidle');
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      expect(overflow).toBeLessThanOrEqual(0);
      const photo = await page.locator('.hero-photo').boundingBox();
      const h1 = await page.locator('#hero-title').boundingBox();
      expect(photo && h1).toBeTruthy();
      const overlapX = Math.min(photo!.x + photo!.width, h1!.x + h1!.width) - Math.max(photo!.x, h1!.x);
      const overlapY = Math.min(photo!.y + photo!.height, h1!.y + h1!.height) - Math.max(photo!.y, h1!.y);
      expect(overlapX > 1 && overlapY > 1).toBe(false);
    });
  }
});

test.describe('мобильное меню без JavaScript', () => {
  test.use({ javaScriptEnabled: false, viewport: { width: 375, height: 800 } });
  test('открывается и показывает четыре пункта', async ({ page }) => {
    await page.goto('/');
    const panel = page.locator('#mobile-menu');
    await expect(panel).toBeHidden();
    await page.getByRole('link', { name: 'Меню' }).click();
    await expect(panel).toBeVisible();
    for (const label of ['Купить путёвку', 'Информация для родителей', 'Вакансии', 'Сведения об организации отдыха детей и их оздоровления']) {
      await expect(panel.getByText(label, { exact: true }).first()).toBeVisible();
    }
  });
});

test.describe('оглавление /info/', () => {
  test('на телефоне при >8 пунктах свёрнуто, на десктопе открыто, без JS открыто', async ({ page, browser }) => {
    await page.setViewportSize({ width: 375, height: 800 });
    await page.goto('/info/');
    const toc = page.locator('nav details').first();
    expect(await toc.locator('li').count()).toBeGreaterThan(8);
    await expect(toc).not.toHaveAttribute('open', /.*/);
    await toc.locator('summary').click();
    await expect(toc).toHaveAttribute('open', '');

    await page.setViewportSize({ width: 1280, height: 800 });
    await page.goto('/info/');
    await expect(page.locator('nav details').first()).toHaveAttribute('open', '');

    const ctx = await browser.newContext({ javaScriptEnabled: false, viewport: { width: 375, height: 800 } });
    const nojs = await ctx.newPage();
    await nojs.goto('http://127.0.0.1:8099/info/');
    await expect(nojs.locator('nav details').first()).toHaveAttribute('open', '');
    await ctx.close();
  });
});
