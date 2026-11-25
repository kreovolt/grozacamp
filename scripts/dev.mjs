// npm run dev: PHP-сервер + пересборка статики при изменениях (scripts/build-assets.mjs --watch).
import { spawn } from 'node:child_process';
import { existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { phpBinary } from './php.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
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

// Статика: Tailwind и esbuild в режиме наблюдения, хеши в manifest.json (scripts/build-assets.mjs)
if (existsSync(join(root, 'frontend/css/app.css'))) {
  run('assets', process.execPath, ['scripts/build-assets.mjs', '--watch']);
}

const stop = () => {
  for (const c of children) c.kill();
  process.exit(0);
};
process.on('SIGINT', stop);
process.on('SIGTERM', stop);
