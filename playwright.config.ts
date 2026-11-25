import { defineConfig, devices } from '@playwright/test';
import { resolve } from 'node:path';

const PORT = 8099;

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
  webServer: {
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
});
