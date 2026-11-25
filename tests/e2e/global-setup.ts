import { spawnSync } from 'node:child_process';

// Перед прогоном: примерные данные seed/demo в storage/test (с заменой — каждый прогон с чистого листа)
// и демо-PDF в public/uploads/docs.
export default function globalSetup(): void {
  const r = spawnSync(
    process.execPath,
    ['scripts/php.mjs', 'scripts/seed.php', '--demo', '--target=storage/test', '--force'],
    { stdio: 'inherit', env: { ...process.env, GROZA_ENV: 'test' } },
  );
  if (r.status !== 0) {
    throw new Error(`scripts/seed.php завершился с кодом ${r.status ?? r.signal}`);
  }
}
