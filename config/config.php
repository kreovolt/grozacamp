<?php
declare(strict_types=1);

/**
 * Итоговая конфигурация: умолчания → config/local.php → переменные окружения GROZA_*.
 *
 * Без настроек сайт считает себя боевым (env=prod): потерянный или забытый config/local.php не должен тихо
 * выключить пароль тестового адреса, Secure у cookie и SMTP. Разработка и тесты задают GROZA_ENV=dev|test явно
 * (npm run dev, playwright.config.ts, TestApp); незнакомое значение env тоже считается prod.
 */
return (static function (): array {
    $root = dirname(__DIR__);

    $config = [
        'env' => 'prod',                      // dev | test | staging | prod
        'baseUrl' => '',                      // https://grozacamp.ru; в dev и test по умолчанию http://127.0.0.1:8080
        'rootDir' => $root,
        'storageDir' => $root . '/storage',
        'timezone' => 'Asia/Yekaterinburg',
        'appSecret' => '',
        'mail' => [
            'host' => '',
            'port' => 465,
            'user' => '',
            'pass' => '',
            'secure' => 'ssl',
            'from' => '',
            'fromName' => 'Лагерь «Гроза»',
        ],
        'staging' => ['user' => '', 'pass' => ''],
        'metrikaId' => '',
        // Адреса обратных прокси (REMOTE_ADDR), которым верим в X-Forwarded-Proto (HTTPS снимается на прокси). Пусто — никому.
        'trustedProxies' => [],
        'fakeNow' => null,                    // ISO-момент «сейчас»; действует только при env=test
    ];

    $local = __DIR__ . '/local.php';
    if (is_file($local)) {
        $config = array_replace_recursive($config, (array) require $local);
    }

    $env = static function (string $name) {
        $v = getenv('GROZA_' . $name);
        return $v === false || $v === '' ? null : $v;
    };
    foreach (['env' => 'ENV', 'baseUrl' => 'BASE_URL', 'storageDir' => 'STORAGE_DIR', 'timezone' => 'TIMEZONE',
              'appSecret' => 'APP_SECRET', 'uploadsDir' => 'UPLOADS_DIR', 'metrikaId' => 'METRIKA_ID', 'fakeNow' => 'FAKE_NOW'] as $key => $name) {
        if (($v = $env($name)) !== null) {
            $config[$key] = $v;
        }
    }
    foreach (['host' => 'MAIL_HOST', 'port' => 'MAIL_PORT', 'user' => 'MAIL_USER', 'pass' => 'MAIL_PASS',
              'secure' => 'MAIL_SECURE', 'from' => 'MAIL_FROM', 'fromName' => 'MAIL_FROM_NAME'] as $key => $name) {
        if (($v = $env($name)) !== null) {
            $config['mail'][$key] = $key === 'port' ? (int) $v : $v;
        }
    }
    foreach (['user' => 'STAGING_USER', 'pass' => 'STAGING_PASS'] as $key => $name) {
        if (($v = $env($name)) !== null) {
            $config['staging'][$key] = $v;
        }
    }

    if (($v = $env('TRUSTED_PROXIES')) !== null) {
        $config['trustedProxies'] = array_values(array_filter(array_map('trim', explode(',', $v)), static fn (string $ip): bool => $ip !== ''));
    }

    if (!in_array($config['env'], ['dev', 'test', 'staging', 'prod'], true)) {
        $config['env'] = 'prod';
    }
    if ($config['baseUrl'] === '' && in_array($config['env'], ['dev', 'test'], true)) {
        $config['baseUrl'] = 'http://127.0.0.1:8080';
    }
    if ($config['env'] !== 'test') {
        $config['fakeNow'] = null;
    }

    return $config;
})();
