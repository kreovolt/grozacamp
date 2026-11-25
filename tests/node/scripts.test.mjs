// Тесты вспомогательных скриптов: без PHP они должны падать громко и сразу, а не молча.
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { dirname, join, resolve } from 'node:path';
import { test } from 'node:test';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', '..');
const run = (script, args, env) =>
  spawnSync(process.execPath, [join(root, 'scripts', script), ...args], {
    cwd: root,
    encoding: 'utf8',
    timeout: 30000,
    env: { ...process.env, ...env },
  });

test('php.mjs: нет бинарника PHP — ненулевой код и понятное сообщение', () => {
  const r = run('php.mjs', ['-v'], { PHP_BIN: join(root, 'no-such-dir', 'php-missing') });
  assert.equal(r.status, 127);
  assert.match(r.stderr, /PHP не запустился/);
  assert.match(r.stderr, /PHP_BIN/);
});

test('php.mjs: код выхода PHP передаётся как есть', () => {
  const r = run('php.mjs', ['-r', 'exit(3);'], {});
  assert.equal(r.status, 3);
});

test('dev.mjs: PHP не запустился — сообщение и выход с ошибкой, без зависания', () => {
  const r = run('dev.mjs', [], { PHP_BIN: join(root, 'no-such-dir', 'php-missing'), PORT: '18099' });
  assert.equal(r.status, 1);
  assert.match(r.stderr, /\[php\] не запустился/);
});
