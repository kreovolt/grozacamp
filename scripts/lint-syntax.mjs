// php -l по всем PHP-файлам проекта (app, config, scripts, templates, public, tests).
// Синтаксис PHP 8.1 отдельно проверяет `npm run lint:php` (PHPCompatibility, testVersion 8.1).
import { spawnSync } from 'node:child_process';
import { readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { phpBinary } from './php.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const dirs = ['app', 'config', 'scripts', 'templates', 'public', 'tests/php'];

function* walk(dir) {
  for (const e of readdirSync(dir, { withFileTypes: true })) {
    const p = join(dir, e.name);
    if (e.isDirectory()) {
      if (e.name === 'uploads' || e.name === 'node_modules') continue;
      yield* walk(p);
    } else if (e.name.endsWith('.php')) yield p;
  }
}

let failed = 0;
let count = 0;
for (const d of dirs) {
  for (const file of walk(join(root, d))) {
    count++;
    const r = spawnSync(phpBinary(), ['-n', '-l', file], { encoding: 'utf8' });
    if (r.error || r.status !== 0) {
      failed++;
      console.error(r.error ? String(r.error) : (r.stdout + r.stderr).trim());
    }
  }
}
if (failed) {
  console.error(`php -l: ошибок в ${failed} из ${count} файлов`);
  process.exit(1);
}
console.log(`php -l: ${count} файлов, синтаксис в порядке`);
