// npm run dev: PHP-сервер + (если есть входные файлы) tailwind --watch и esbuild --watch.
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { phpBinary } from './php.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const bin = (name) => join(root, 'node_modules', '.bin', process.platform === 'win32' ? `${name}.cmd` : name);
const children = [];

function run(label, cmd, args, opts = {}) {
  const child = spawn(cmd, args, { cwd: root, stdio: 'inherit', ...opts });
  child.on('exit', (code) => {
    if (code) console.error(`[${label}] завершился с кодом ${code}`);
  });
  children.push(child);
  return child;
}

const host = process.env.HOST ?? '127.0.0.1';
const port = process.env.PORT ?? '8080';
run('php', phpBinary(), ['-S', `${host}:${port}`, '-t', 'public', 'public/index.php']);

// Tailwind и esbuild подключает Task 4; пока нет входных файлов — просто пропускаем.
const css = [['frontend/css/app.css', 'public/assets/css/app.css'], ['frontend/css/admin.css', 'public/assets/css/admin.css']];
for (const [input, output] of css) {
  if (existsSync(join(root, input)) && existsSync(join(root, 'tailwind.config.js'))) {
    run('tailwind', bin('tailwindcss'), ['-i', input, '-o', output, '--watch'], { shell: process.platform === 'win32' });
  }
}
const js = ['main', 'nav', 'forms', 'motion', 'admin', 'admin-editor'].map((n) => `frontend/js/${n}.js`).filter((f) => existsSync(join(root, f)));
if (js.length) {
  run('esbuild', bin('esbuild'), [...js, '--bundle', '--outdir=public/assets/js', '--watch'], { shell: process.platform === 'win32' });
}

const stop = () => {
  for (const c of children) c.kill();
  process.exit(0);
};
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
