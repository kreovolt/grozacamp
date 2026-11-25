// Запускает PHP с переданными аргументами, подбирая бинарник:
// env PHP_BIN -> tools/php/php.exe -> установка winget -> php из PATH.
import { spawnSync } from 'node:child_process';
import { existsSync, readdirSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');

export function phpBinary() {
  if (process.env.PHP_BIN) return process.env.PHP_BIN;
  const local = join(root, 'tools', 'php', 'php.exe');
  if (existsSync(local)) return local;
  const base = process.env.LOCALAPPDATA && join(process.env.LOCALAPPDATA, 'Microsoft', 'WinGet', 'Packages');
  if (base && existsSync(base)) {
    const dir = readdirSync(base).find((n) => n.startsWith('PHP.PHP.8.2') && existsSync(join(base, n, 'php.exe')));
    if (dir) return join(base, dir, 'php.exe');
  }
  return 'php';
}

if (process.argv[1] && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
  const r = spawnSync(phpBinary(), process.argv.slice(2), { stdio: 'inherit', cwd: root });
  process.exit(r.status ?? 1);
}
