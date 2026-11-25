// Оптимизация фото: _source/photos → public/assets/img (avif/webp/jpg × ширины), manifest.json,
// а также фавиконы, логотип шапки и OG-картинка из шеврона. Результат коммитится (на хостинге нет Node).
// Запуск: npm run photos [-- --force | --only=ключ,ключ]. Без них пропускает файлы, которые новее исходника;
// --only пересобирает только эти ключи. После смены crop/position/качества в config/photos.json исходник не меняется,
// поэтому нужен --force или --only. Манифест всегда пишется целиком.
// crop в photos.json — доли исходника {top, left, width, height}; вырезается ДО уменьшения (так убираем водяной знак
// фотографа с шевроном в правом верхнем углу кадра — спека §6), w/h в манифесте — уже после кропа.
import { readFileSync, writeFileSync, mkdirSync, readdirSync, statSync, existsSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const only = process.argv.find((a) => a.startsWith('--only='))?.slice('--only='.length).split(',').filter(Boolean) ?? null;
const force = process.argv.includes('--force') && only === null;
const forced = (key) => force || (only?.includes(key) ?? false);
const srcDir = join(root, '_source/photos');
const outDir = join(root, 'public/assets/img');
const assets = join(root, 'public/assets');
const chevron = join(root, '_source/logo/shevron-original.webp');

const DEFAULT_WIDTHS = [480, 960, 1440];
const FORMATS = ['avif', 'webp', 'jpg'];
const HERO_KEY = 'hero-river';
const HERO_LIMIT = 200 * 1024;

const encode = {
  avif: (s, cfg = {}) => s.avif({ quality: cfg.avifQuality ?? 50 }), // avifQuality в photos.json — для тяжёлых кадров (герой)
  webp: (s) => s.webp({ quality: 72 }),
  jpg: (s) => s.jpeg({ quality: 78, mozjpeg: true }),
};

const photos = JSON.parse(readFileSync(join(root, 'config/photos.json'), 'utf8'));
const files = readdirSync(srcDir);
mkdirSync(outDir, { recursive: true });

const newer = (out, src) => existsSync(out) && statSync(out).mtimeMs >= statSync(src).mtimeMs;
const rows = [];
const manifest = {};
const missing = [];

for (const [key, cfg] of Object.entries(photos)) {
  const name = files.find((f) => f.startsWith(`${cfg.file}__`));
  if (!name) {
    missing.push(`${key} (${cfg.file})`);
    continue;
  }
  const src = join(srcDir, name);
  const meta = await sharp(src).rotate().metadata(); // rotate() — учесть ориентацию EXIF
  const oriented = (meta.orientation ?? 1) >= 5; // 90°/270°: ширина и высота меняются местами
  const fullW = oriented ? meta.height : meta.width;
  const fullH = oriented ? meta.width : meta.height;
  const region = cropRegion(key, cfg.crop, fullW, fullH);
  const srcW = region ? region.width : fullW;
  const srcH = region ? region.height : fullH;
  const widths = (cfg.widths ?? DEFAULT_WIDTHS).filter((w) => w <= srcW);
  const top = widths[widths.length - 1];

  for (const width of widths) {
    for (const format of FORMATS) {
      const out = join(outDir, `${key}-${width}.${format}`);
      if (forced(key) || !newer(out, src)) {
        // withMetadata не вызываем: EXIF/GPS в результат не попадает
        await encode[format](load(src, region).resize({ width, withoutEnlargement: true }), cfg).toFile(out);
      }
      rows.push([key, width, format, statSync(out).size]);
    }
  }
  manifest[key] = {
    src: `/assets/img/${key}`,
    widths,
    formats: FORMATS,
    w: top,
    h: Math.round((top * srcH) / srcW),
    ...(cfg.position ? { position: cfg.position } : {}),
  };
}

writeFileSync(join(outDir, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');

/** Исходник, повёрнутый по EXIF, и (если задан crop) вырезанная область — до любого resize. */
function load(file, region) {
  const s = sharp(file).rotate();
  return region ? s.extract(region) : s;
}

/** crop {top, left, width, height} в долях исходника → пиксели. Ошибка в конфиге — сразу и громко. */
function cropRegion(key, crop, fullW, fullH) {
  if (!crop) return null;
  const { top = 0, left = 0 } = crop;
  const { width = 1 - left, height = 1 - top } = crop;
  const fractions = [top, left, width, height].every((v) => typeof v === 'number' && v >= 0 && v <= 1);
  if (!fractions || width <= 0 || height <= 0 || left + width > 1.0001 || top + height > 1.0001) {
    throw new Error(`config/photos.json: неверный crop у «${key}»: ${JSON.stringify(crop)}`);
  }
  const l = Math.round(left * fullW);
  const t = Math.round(top * fullH);
  return {
    left: l,
    top: t,
    width: Math.min(fullW - l, Math.round(width * fullW)),
    height: Math.min(fullH - t, Math.round(height * fullH)),
  };
}

// --- шеврон: фавиконы, логотип шапки, OG ---
const chevronOut = (name) => join(assets, name);
const chevronFresh = (names) => !force && names.every((n) => newer(chevronOut(n), chevron)); // --only шеврон не трогает
const icoNames = ['favicon.ico', 'favicon.svg', 'apple-touch-icon.png', 'logo-header.png', 'logo-header.webp'];
if (!chevronFresh(icoNames)) {
  const sand = { r: 241, g: 235, b: 221, alpha: 1 };
  const fit = (size, bg) => sharp(chevron).resize({ height: size, width: size, fit: 'contain', background: bg ?? { r: 0, g: 0, b: 0, alpha: 0 } });
  const png32 = await fit(32).png().toBuffer();
  const png64 = await fit(64).png().toBuffer();
  // ICO с PNG внутри (поддерживается всеми современными браузерами)
  const head = Buffer.alloc(22);
  head.writeUInt16LE(1, 2); // тип: иконка
  head.writeUInt16LE(1, 4); // одно изображение
  head[6] = 32; head[7] = 32;
  head.writeUInt16LE(1, 10); // плоскости
  head.writeUInt16LE(32, 12); // бит на пиксель
  head.writeUInt32LE(png32.length, 14);
  head.writeUInt32LE(22, 18);
  writeFileSync(chevronOut('favicon.ico'), Buffer.concat([head, png32]));
  writeFileSync(
    chevronOut('favicon.svg'),
    `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" width="64" height="64"><image width="64" height="64" href="data:image/png;base64,${png64.toString('base64')}"/></svg>\n`,
  );
  await fit(180, sand).flatten({ background: sand }).png().toFile(chevronOut('apple-touch-icon.png'));
  const logo = () => sharp(chevron).resize({ height: 104 }); // 2× от высоты 52 px
  await logo().png({ compressionLevel: 9 }).toFile(chevronOut('logo-header.png'));
  await logo().webp({ quality: 80 }).toFile(chevronOut('logo-header.webp'));
}

// OG 1200×630 — кроп героя
const heroName = files.find((f) => f.startsWith(`${photos[HERO_KEY]?.file}__`));
const og = chevronOut('og-default.jpg');
if (heroName && (forced(HERO_KEY) || !newer(og, join(srcDir, heroName)))) {
  const heroSrc = join(srcDir, heroName);
  const m = await sharp(heroSrc).rotate().metadata();
  const turned = (m.orientation ?? 1) >= 5;
  const region = cropRegion(HERO_KEY, photos[HERO_KEY].crop, turned ? m.height : m.width, turned ? m.width : m.height);
  await load(heroSrc, region).resize(1200, 630, { fit: 'cover', position: 'attention' }).jpeg({ quality: 80, mozjpeg: true }).toFile(og);
}

// --- отчёт ---
const kb = (n) => (n / 1024).toFixed(1).padStart(8);
console.log('key'.padEnd(18), 'width'.padStart(6), 'fmt'.padStart(5), '      KB');
for (const [key, width, format, size] of rows) console.log(key.padEnd(18), String(width).padStart(6), format.padStart(5), kb(size));
const total = rows.reduce((s, r) => s + r[3], 0);
console.log(`\nфото: ${Object.keys(manifest).length}, файлов: ${rows.length}, всего ${(total / 1048576).toFixed(1)} МБ`);

let fail = false;
if (missing.length) {
  console.error(`Нет исходников: ${missing.join(', ')}`);
  fail = true;
}
const hero = rows.find((r) => r[0] === HERO_KEY && r[1] === 1920 && r[2] === 'avif');
if (!hero) {
  console.error('Не найден hero-river-1920.avif');
  fail = true;
} else if (hero[3] > HERO_LIMIT) {
  console.error(`hero-river-1920.avif ${(hero[3] / 1024).toFixed(0)} КБ > 200 КБ — снизьте качество для героя`);
  fail = true;
}
process.exit(fail ? 1 : 0);
