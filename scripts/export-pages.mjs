// npm run export:pages -- --out=DIR [--base=https://kreovolt.github.io/grozacamp] [--port=8094]
//
// Статическая копия публичной части сайта для GitHub Pages (или любого хостинга без PHP) — посмотреть вёрстку.
// PHP там не работает: формы заявок не отправляются (скрипт форм покажет телефон и WhatsApp), админки нет,
// данные — демо (seed/demo). Копия закрыта от поисковиков (noindex): это не боевой сайт.
//
// Как работает: свой php -S с демо-данными во временной папке хранилища, обход сайта от «/» по ссылкам,
// страницы — <путь>/index.html, файлы (css, js, шрифты, фото, PDF) — как есть, страница 404 — 404.html.
// Корневые ссылки «/booking/» переписываются под подпапку проекта на Pages: «/grozacamp/booking/».
import { spawn, spawnSync } from 'node:child_process';
import { mkdirSync, mkdtempSync, rmSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { phpBinary } from './php.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const args = Object.fromEntries(process.argv.slice(2).map((a) => a.replace(/^--/, '').split('=')).map(([k, ...v]) => [k, v.join('=')]));
if (!args.out) {
  console.error('Usage: node scripts/export-pages.mjs --out=DIR [--base=https://kreovolt.github.io/grozacamp] [--port=8094]');
  process.exit(2);
}
const out = resolve(args.out);
const base = (args.base || 'https://kreovolt.github.io/grozacamp').replace(/\/+$/, '');
const prefix = new URL(base).pathname.replace(/\/+$/, ''); // «/grozacamp» (пусто, если сайт в корне домена)
const port = Number(args.port || 8094);
const origin = `http://127.0.0.1:${port}`;

// Не страницы сайта: админка, приёмники форм (только POST), служебное
const SKIP = [/^\/admin(\/|$)/, /^\/form\//, /^\/styleguide\//];

const storage = mkdtempSync(join(tmpdir(), 'groza-pages-'));
const env = { ...process.env, GROZA_ENV: 'dev', GROZA_BASE_URL: base, GROZA_STORAGE_DIR: storage };

function seed() {
  const r = spawnSync(phpBinary(), ['scripts/seed.php', '--demo', `--target=${storage}`], { cwd: root, env, encoding: 'utf8' });
  if (r.status !== 0) throw new Error(`seed.php: ${r.stderr || r.stdout}`);
}

async function startServer() {
  const server = spawn(phpBinary(), ['-S', `127.0.0.1:${port}`, '-t', 'public', 'public/index.php'], { cwd: root, env, stdio: 'ignore' });
  for (let i = 0; i < 100; i++) {
    try {
      await fetch(`${origin}/`);
      return server;
    } catch {
      await new Promise((r) => setTimeout(r, 100));
    }
  }
  server.kill();
  throw new Error(`php -S не ответил на ${origin}`);
}

/** Корневые ссылки из HTML и CSS: «/путь» (не «//хост»), без якоря. */
function refs(text, isHtml) {
  const found = [];
  const add = (u) => {
    const v = u.trim().replace(/&amp;/g, '&');
    if (v.startsWith('/') && !v.startsWith('//')) found.push(v.split('#')[0]);
  };
  if (isHtml) {
    for (const m of text.matchAll(/\s(?:href|src|action|poster)="([^"]*)"/g)) add(m[1]);
    for (const m of text.matchAll(/\ssrcset="([^"]*)"/g)) m[1].split(',').forEach((c) => add(c.trim().split(/\s+/)[0]));
  }
  for (const m of text.matchAll(/url\(\s*['"]?([^'")]+)['"]?\s*\)/g)) add(m[1]);
  return found;
}

/** Корневые ссылки → под подпапку проекта (в атрибутах, srcset и url() в CSS и style). */
function rewrite(text, isHtml) {
  if (prefix === '') return text;
  const fix = (u) => (u.startsWith('/') && !u.startsWith('//') && !u.startsWith(prefix + '/') ? prefix + u : u);
  let s = text.replace(/url\(\s*(['"]?)(\/[^'")]*)\1\s*\)/g, (_, q, u) => `url(${q}${fix(u)}${q})`);
  if (isHtml) {
    s = s.replace(/(\s(?:href|src|action|poster)=")([^"]*)"/g, (_, a, u) => `${a}${fix(u)}"`);
    s = s.replace(/(\ssrcset=")([^"]*)"/g, (_, a, list) => `${a}${list.split(',').map((c) => {
      const [u, ...rest] = c.trim().split(/\s+/);
      return [fix(u), ...rest].join(' ');
    }).join(', ')}"`);
  }
  return s;
}

/** Копия не для поисковиков: noindex в каждую страницу, без canonical (вместе с noindex он противоречив). */
function noindex(html) {
  const s = html.replace(/<link rel="canonical" href="[^"]*">\n?/, '');
  if (/<meta name="robots"/.test(s)) return s.replace(/<meta name="robots" content="[^"]*">/, '<meta name="robots" content="noindex, nofollow">');
  return s.replace('<meta charset="utf-8">', '<meta charset="utf-8">\n<meta name="robots" content="noindex, nofollow">');
}

function fileFor(path, isHtml) {
  const clean = decodeURIComponent(path.split('?')[0]);
  if (isHtml) return join(out, clean.endsWith('/') ? clean + 'index.html' : clean + '/index.html');
  return join(out, clean);
}

function save(file, body) {
  mkdirSync(dirname(file), { recursive: true });
  writeFileSync(file, body);
}

async function crawl() {
  const queue = ['/'];
  const seen = new Set();
  let pages = 0;
  let files = 0;
  let bytes = 0;
  while (queue.length) {
    const path = queue.shift();
    const key = path.split('?')[0];
    if (seen.has(key) || SKIP.some((re) => re.test(key))) continue;
    seen.add(key);
    const res = await fetch(origin + key, { redirect: 'manual' });
    if (res.status >= 300 && res.status < 400) {
      const to = new URL(res.headers.get('location') || '/', origin + key);
      if (to.origin === origin) queue.push(to.pathname);
      continue;
    }
    if (res.status !== 200) {
      console.warn(`  ${res.status} ${key}`);
      continue;
    }
    const type = res.headers.get('content-type') || '';
    const isHtml = type.includes('text/html');
    const isCss = type.includes('text/css') || key.endsWith('.css');
    if (isHtml || isCss) {
      const text = await res.text();
      queue.push(...refs(text, isHtml));
      const body = isHtml ? noindex(rewrite(text, true)) : rewrite(text, false);
      save(fileFor(key, isHtml), body);
      bytes += Buffer.byteLength(body);
      isHtml ? pages++ : files++;
    } else {
      const buf = Buffer.from(await res.arrayBuffer());
      save(fileFor(key, false), buf);
      bytes += buf.length;
      files++;
    }
  }
  return { pages, files, bytes };
}

async function notFoundPage() {
  const res = await fetch(`${origin}/net-takoy-stranicy-${Date.now()}/`);
  const html = noindex(rewrite(await res.text(), true));
  save(join(out, '404.html'), html);
}

rmSync(out, { recursive: true, force: true });
mkdirSync(out, { recursive: true });
seed();
const server = await startServer();
try {
  const { pages, files, bytes } = await crawl();
  await notFoundPage();
  writeFileSync(join(out, '.nojekyll'), ''); // Pages без Jekyll: файлы отдаются как есть
  console.log(`Готово: ${pages} страниц, ${files} файлов, ${(bytes / 1024 / 1024).toFixed(1)} МБ → ${out} (адрес ${base}/)`);
} finally {
  server.kill();
  rmSync(storage, { recursive: true, force: true });
}
