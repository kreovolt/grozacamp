import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

// Порты серверов — в tests/e2e/ports.ts (их же берут спеки)
import { PORT, PORT_ADMIN, PORT_HISTORY, PORT_METRIKA, PORT_TEXTS } from './tests/e2e/ports';

// Сквозные тесты: встроенный сервер PHP в окружении test со своим каталогом данных storage/test;
// перед прогоном globalSetup кладёт туда примерные данные seed/demo.
export default defineConfig({
  testDir: 'tests/e2e',
  globalSetup: './tests/e2e/global-setup.ts',
  fullyParallel: true,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 1 : 0,
  reporter: [['list']],
  use: {
    baseURL: `http://127.0.0.1:${PORT}`,
    locale: 'ru-RU',
    trace: 'retain-on-failure',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: [
    {
    command: `node scripts/php.mjs -S 127.0.0.1:${PORT} -t public public/index.php`,
    url: `http://127.0.0.1:${PORT}/`,
    reuseExistingServer: !process.env.CI,
    timeout: 30_000,
    // журнал запросов встроенного сервера PHP идёт в stderr; показать: DEBUG_SERVER=1 npm run e2e
    stderr: process.env.DEBUG_SERVER ? 'pipe' : 'ignore',
    env: {
      GROZA_ENV: 'test',
      GROZA_STORAGE_DIR: resolve('storage/test'),
    },
    },
    // Второй сервер — с подключённой Метрикой (счётчик-образец): уведомление о cookie и CSP
    {
      command: `node scripts/php.mjs -S 127.0.0.1:${PORT_METRIKA} -t public public/index.php`,
      url: `http://127.0.0.1:${PORT_METRIKA}/`,
      reuseExistingServer: false, // у сервера своё окружение (Метрика): чужой запущенный нам не подходит
      timeout: 30_000,
      stderr: 'ignore',
      env: {
        GROZA_ENV: 'test',
        GROZA_STORAGE_DIR: resolve('storage/test'),
        GROZA_METRIKA_ID: '12345678',
      },
    },
    // Четвёртый сервер — для admin-texts.spec.ts (storage/test-texts)
    {
      command: `node scripts/php.mjs -S 127.0.0.1:${PORT_TEXTS} -t public public/index.php`,
      url: `http://127.0.0.1:${PORT_TEXTS}/`,
      reuseExistingServer: false,
      timeout: 30_000,
      stderr: 'ignore',
      env: {
        GROZA_ENV: 'test',
        GROZA_STORAGE_DIR: resolve('storage/test-texts'),
      },
    },
    // Пятый сервер — для admin-history.spec.ts (storage/test-history)
    {
      command: `node scripts/php.mjs -S 127.0.0.1:${PORT_HISTORY} -t public public/index.php`,
      url: `http://127.0.0.1:${PORT_HISTORY}/`,
      reuseExistingServer: false,
      timeout: 30_000,
      stderr: 'ignore',
      env: {
        GROZA_ENV: 'test',
        GROZA_STORAGE_DIR: resolve('storage/test-history'),
      },
    },
    // Третий сервер — со своими данными (storage/test-admin): спек админки меняет и создаёт смены
    {
      command: `node scripts/php.mjs -S 127.0.0.1:${PORT_ADMIN} -t public public/index.php`,
      url: `http://127.0.0.1:${PORT_ADMIN}/`,
      reuseExistingServer: false,
      timeout: 30_000,
      stderr: 'ignore',
      env: {
        GROZA_ENV: 'test',
        GROZA_STORAGE_DIR: resolve('storage/test-admin'),
      },
    },
  ],
});
