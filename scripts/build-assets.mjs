// Сборка статики сайта (на хостинге Node нет — результат коммитится):
//   шрифты @fontsource → public/assets/fonts/ (cyrillic + latin; из latin-ext берётся только ₽)
//   иконки lucide-static + свои глифы → public/assets/icons.svg (спрайт)
//   frontend/css/app.css (Tailwind) → public/assets/css/app.css
//   frontend/js/main.js (esbuild) → public/assets/js/main.js
//   редактор текста: trix.css + frontend/css/admin-editor.css → css/admin-editor.css; frontend/js/admin-editor.js (Trix внутри, без <style>) → js/admin-editor.js
//   хеши содержимого → public/assets/manifest.json (asset() добавляет ?v=…)
// node scripts/build-assets.mjs            — разовая сборка (минифицированная)
// node scripts/build-assets.mjs --watch    — пересборка при изменениях (npm run dev)
import { spawn, spawnSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { copyFileSync, existsSync, mkdirSync, readFileSync, watch, writeFileSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const out = join(root, 'public', 'assets');
const watchMode = process.argv.includes('--watch');

// Пары «вход Tailwind → выход»; admin.css появится в задачах админки
const CSS = [['frontend/css/app.css', 'css/app.css'], ['frontend/css/admin.css', 'css/admin.css']]
  .filter(([input]) => existsSync(join(root, input)));
// Точки входа JS: main (все страницы), motion (GSAP — только страницы с [data-reveal]), metrika, admin; остальные модули подключаются через import
const JS = ['main', 'motion', 'metrika', 'admin', 'admin-editor'].map((n) => `frontend/js/${n}.js`).filter((f) => existsSync(join(root, f)));

// Trix при загрузке добавляет в страницу теги <style> — строгая CSP админки (style-src 'self') их заблокирует.
// Убираем эту функцию из Trix при сборке; те же правила лежат в frontend/css/admin-editor.css.
const TRIX_INLINE_CSS = 'const installDefaultCSSForTagName = function (tagName, defaultCSS) {';
const trixNoInlineStyle = {
  name: 'trix-no-inline-style',
  setup(build) {
    // Сборка должна брать только trix.esm.js: другой файл Trix (например, минифицированный) мог бы вернуть вставку <style>
    build.onResolve({ filter: /^trix(\/|$)/ }, (args) => {
      if (args.path !== 'trix/dist/trix.esm.js') throw new Error('Trix подключён как «' + args.path + '»: нужен только trix/dist/trix.esm.js (в нём убрана вставка <style>)');
      return undefined;
    });
    build.onLoad({ filter: /node_modules[\\/]trix[\\/].*\.js$/ }, (args) => {
      if (!/trix[\\/]dist[\\/]trix\.esm\.js$/.test(args.path)) throw new Error('В сборку попал файл Trix ' + args.path + ': нужен только trix.esm.js');
      return undefined;
    });
    build.onLoad({ filter: /trix[\\/]dist[\\/]trix\.esm\.js$/ }, (args) => {
      const source = readFileSync(args.path, 'utf8');
      if (!source.includes(TRIX_INLINE_CSS)) {
        throw new Error('Trix изменился: не найдено место, где он добавляет <style>. Обновите trixNoInlineStyle в scripts/build-assets.mjs');
      }
      return { contents: source.replace(TRIX_INLINE_CSS, TRIX_INLINE_CSS + ' return;'), loader: 'js' };
    });
  },
};

async function buildEditorCss() {
  const css = readFileSync(join(root, 'node_modules', 'trix', 'dist', 'trix.css'), 'utf8')
    + '\n' + readFileSync(join(root, 'frontend', 'css', 'admin-editor.css'), 'utf8');
  const out2 = await esbuild.transform(css, { loader: 'css', minify: !watchMode });
  mkdirSync(join(out, 'css'), { recursive: true });
  writeFileSync(join(out, 'css', 'admin-editor.css'), out2.code);
}

const FONTS = [
  ['oswald', [500, 600, 700]],
  ['manrope', [400, 600, 700]],
];

// Только используемые иконки (имя в спрайте = имя в lucide-static)
const LUCIDE = ['phone', 'message-circle', 'menu', 'x', 'chevron-down', 'chevron-right', 'map-pin', 'clock', 'mail', 'arrow-right', 'arrow-up', 'log-in', 'check', 'calendar'];
// Свои глифы в том же стиле (контур 2 px, 24×24): ВКонтакте и YouTube — без пакетов брендовых иконок
const CUSTOM = {
  vk: '<path d="M3 7.5 6.5 16.5 10 7.5"/><path d="M13.5 7.5v9"/><path d="M20.5 7.5 14 12.5l6.5 4"/>',
  youtube: '<rect x="2.5" y="5" width="19" height="14" rx="4"/><path d="m10 9 5 3-5 3z"/>',
};

function copyFonts() {
  mkdirSync(join(out, 'fonts'), { recursive: true });
  for (const [family, weights] of FONTS) {
    for (const weight of weights) {
      // latin-ext — только ради знака рубля (U+20BD): в app.css он подключён с unicode-range: U+20BD
      for (const subset of ['cyrillic', 'latin', 'latin-ext']) {
        const src = join(root, 'node_modules', '@fontsource', family, 'files', `${family}-${subset}-${weight}-normal.woff2`);
        copyFileSync(src, join(out, 'fonts', `${family}-${subset}-${weight}.woff2`));
      }
    }
  }
}

function buildSprite() {
  const symbols = LUCIDE.map((name) => {
    const svg = readFileSync(join(root, 'node_modules', 'lucide-static', 'icons', `${name}.svg`), 'utf8');
    const inner = svg.replace(/<!--[\s\S]*?-->/g, '').replace(/^[\s\S]*?<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '');
    return symbol(name, inner.replace(/\s*\n\s*/g, ''));
  });
  for (const [name, inner] of Object.entries(CUSTOM)) symbols.push(symbol(name, inner));
  const sprite = '<svg xmlns="http://www.w3.org/2000/svg">\n'
    + '<!-- Иконки: lucide-static (ISC License, https://lucide.dev), vk и youtube — свои -->\n'
    + symbols.join('\n') + '\n</svg>\n';
  writeFileSync(join(out, 'icons.svg'), sprite);
}

function symbol(name, inner) {
  return `<symbol id="i-${name}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${inner}</symbol>`;
}

function tailwind(input, output, extra = []) {
  const cli = join(root, 'node_modules', 'tailwindcss', 'lib', 'cli.js');
  const args = [cli, '-c', 'tailwind.config.js', '-i', input, '-o', join('public', 'assets', output), ...extra];
  if (extra.includes('--watch')) {
    // stdin — открытый канал от этого процесса: Tailwind в режиме --watch завершается, когда он закрыт,
    // поэтому не остаётся «сиротой», даже если этот процесс убит без сигнала (Windows)
    return spawn(process.execPath, args, { cwd: root, stdio: ['pipe', 'inherit', 'inherit'] });
  }
  const r = spawnSync(process.execPath, args, { cwd: root, stdio: ['ignore', 'ignore', 'pipe'], encoding: 'utf8' });
  if (r.status !== 0) {
    process.stderr.write(r.stderr);
    throw new Error(`Tailwind: ошибка сборки ${input}`);
  }
}

// Редактор — отдельной сборкой: Trix 2 написан для современных браузеров (классы, формы-элементы), а админкой пользуются сотрудники.
const EDITOR = 'frontend/js/admin-editor.js';
const esbuildOptions = {
  entryPoints: JS.filter((f) => f !== EDITOR).map((f) => join(root, f)),
  outdir: join(out, 'js'),
  bundle: true,
  format: 'iife',
  target: ['es2019', 'safari13'],
  minify: !watchMode,
  sourcemap: watchMode ? 'inline' : false,
  legalComments: 'none',
  logLevel: 'warning',
};
const editorOptions = {
  ...esbuildOptions,
  entryPoints: [join(root, EDITOR)],
  target: ['es2020', 'chrome90', 'firefox90', 'safari15'],
  plugins: [trixNoInlineStyle],
};

function writeManifest() {
  const file = join(out, 'manifest.json');
  let manifest = {};
  if (existsSync(file)) {
    try { manifest = JSON.parse(readFileSync(file, 'utf8')); } catch { manifest = {}; }
  }
  const files = ['icons.svg', 'css/admin-editor.css', ...CSS.map(([, o]) => o), ...JS.map((f) => `js/${f.split('/').pop()}`)];
  for (const rel of files) {
    const path = join(out, rel);
    if (existsSync(path)) {
      manifest[`/assets/${rel}`] = createHash('sha256').update(readFileSync(path)).digest('hex').slice(0, 10);
    }
  }
  const sorted = Object.fromEntries(Object.entries(manifest).sort(([a], [b]) => a.localeCompare(b)));
  writeFileSync(file, JSON.stringify(sorted, null, 2) + '\n');
}

copyFonts();
buildSprite();

if (!watchMode) {
  for (const [input, output] of CSS) tailwind(input, output, ['--minify']);
  if (JS.length) await esbuild.build(esbuildOptions);
  if (JS.includes(EDITOR)) await esbuild.build(editorOptions);
  await buildEditorCss();
  writeManifest();
  console.log('Статика собрана: public/assets (css, js, fonts, icons.svg, manifest.json)');
} else {
  // Хеши обновляются после каждой пересборки, чтобы браузер не держал старую версию
  let timer;
  const rehash = () => { clearTimeout(timer); timer = setTimeout(writeManifest, 150); };
  await buildEditorCss();
  const children = CSS.map(([input, output]) => tailwind(input, output, ['--watch']));
  const stop = () => { for (const c of children) c.kill(); process.exit(0); };
  process.on('SIGINT', stop);
  process.on('SIGTERM', stop);
  mkdirSync(join(out, 'css'), { recursive: true });
  watch(join(out, 'css'), rehash);
  if (JS.length) {
    const ctx = await esbuild.context({
      ...esbuildOptions,
      plugins: [{ name: 'manifest', setup: (b) => b.onEnd(rehash) }],
    });
    await ctx.watch();
    if (JS.includes(EDITOR)) {
      const editorCtx = await esbuild.context({ ...editorOptions, plugins: [...editorOptions.plugins, { name: 'manifest', setup: (b) => b.onEnd(rehash) }] });
      await editorCtx.watch();
    }
  }
}
