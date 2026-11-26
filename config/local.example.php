<?php
declare(strict_types=1);

// Скопируйте в config/local.php (файл не попадает в git и не выкладывается скриптом выкладки) и подставьте свои значения.
// Без config/local.php сайт считает себя боевым (env=prod): админка закрыта страницей «Сайт не настроен» (503),
// формы показывают телефон и WhatsApp — пока не заданы baseUrl (с https://) и appSecret.
return [
    'env' => 'prod',                          // prod — боевой сайт; staging — тестовый адрес (закрыт паролем ниже, noindex)
    'baseUrl' => 'https://grozacamp.ru',      // с https://, без пути; запросы по http переадресуются сюда
    'appSecret' => 'замените-на-длинную-случайную-строку', // от 32 знаков: php -r "echo bin2hex(random_bytes(32));"
    'mail' => [
        'host' => 'smtp.example.ru',
        'port' => 465,
        'user' => 'site@example.ru',
        'pass' => '',
        'secure' => 'ssl',
        'from' => 'site@example.ru',
        'fromName' => 'Лагерь «Гроза»',
    ],
    'staging' => ['user' => '', 'pass' => ''], // только для env=staging: логин и пароль Basic; пусто — закрыто для всех
    'metrikaId' => '',
    // Адреса обратного прокси хостинга (REMOTE_ADDR, который видит PHP), если HTTPS снимается на нём и в Apache приходит
    // X-Forwarded-Proto: https. Пусто — заголовку не верим (см. docs/staging-checklist.md).
    'trustedProxies' => [],
];
