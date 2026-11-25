<?php
declare(strict_types=1);

// Скопируйте в config/local.php (файл не попадает в git) и подставьте свои значения.
return [
    'env' => 'prod',
    'baseUrl' => 'https://grozacamp.ru',
    'appSecret' => 'замените-на-длинную-случайную-строку',
    'mail' => [
        'host' => 'smtp.example.ru',
        'port' => 465,
        'user' => 'site@example.ru',
        'pass' => '',
        'secure' => 'ssl',
        'from' => 'site@example.ru',
        'fromName' => 'Лагерь «Гроза»',
    ],
    'staging' => ['user' => '', 'pass' => ''],
    'metrikaId' => '',
];
