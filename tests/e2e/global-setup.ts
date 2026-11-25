import { spawnSync } from 'node:child_process';
import { rmSync } from 'node:fs';

const E2E_PASSWORD = 'e2e-password-123';

// Перед прогоном: примерные данные seed/demo в storage/test (с заменой — каждый прогон с чистого листа)
// и демо-PDF в public/uploads/docs.
export default function globalSetup(): void {
  // счётчики лимита частоты форм (5 заявок за 10 минут с одного адреса) — тоже с чистого листа
  rmSync('storage/test/ratelimit', { recursive: true, force: true });
  const r = spawnSync(
    process.execPath,
    ['scripts/php.mjs', 'scripts/seed.php', '--demo', '--target=storage/test', '--force'],
    { stdio: 'inherit', env: { ...process.env, GROZA_ENV: 'test' } },
  );
  if (r.status !== 0) {
    throw new Error(`scripts/seed.php завершился с кодом ${r.status ?? r.signal}`);
  }

  // Пользователи админки для admin-auth.spec.ts (пароль подаётся через stdin, не аргументом)
  for (const file of ['users.json', 'auth-meta.json']) rmSync(`storage/test/data/${file}`, { force: true });
  rmSync('storage/test/sessions', { recursive: true, force: true });
  for (const [login, name] of [['e2e-main', 'Тест Основной'], ['e2e-pass', 'Тест Пароль']]) {
    const u = spawnSync(process.execPath, ['scripts/php.mjs', 'scripts/user.php', 'add', login, name, '--password-stdin'], {
      input: E2E_PASSWORD,
      stdio: ['pipe', 'inherit', 'inherit'],
      env: { ...process.env, GROZA_ENV: 'test', GROZA_STORAGE_DIR: 'storage/test' },
    });
    if (u.status !== 0) {
      throw new Error(`scripts/user.php add ${login} завершился с кодом ${u.status ?? u.signal}`);
    }
  }

  // Отдельные данные для admin-shifts.spec.ts (сервер на порту 8097): смены там меняются и создаются свободно
  rmSync('storage/test-admin', { recursive: true, force: true });
  const seed = spawnSync(
    process.execPath,
    ['scripts/php.mjs', 'scripts/seed.php', '--demo', '--target=storage/test-admin', '--force'],
    { stdio: 'inherit', env: { ...process.env, GROZA_ENV: 'test' } },
  );
  if (seed.status !== 0) {
    throw new Error(`scripts/seed.php (storage/test-admin) завершился с кодом ${seed.status ?? seed.signal}`);
  }
  // Пользователи спеков админки (свои сессии: спеки идут параллельно)
  for (const [login, name] of [['e2e-shifts', 'Тест Смены'], ['e2e-docs', 'Тест Документы']]) {
    const u = spawnSync(process.execPath, ['scripts/php.mjs', 'scripts/user.php', 'add', login, name, '--password-stdin'], {
      input: E2E_PASSWORD,
      stdio: ['pipe', 'inherit', 'inherit'],
      env: { ...process.env, GROZA_ENV: 'test', GROZA_STORAGE_DIR: 'storage/test-admin' },
    });
    if (u.status !== 0) {
      throw new Error(`scripts/user.php add ${login} завершился с кодом ${u.status ?? u.signal}`);
    }
  }
}
