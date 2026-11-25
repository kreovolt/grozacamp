<?php
declare(strict_types=1);

/**
 * Переадресации со старого сайта. Сейчас — только прежние адреса карточек смен (/program/{slug}/):
 * их открывают из поиска и закладок, ведём на «Купить путёвку». Источник: таблица «История карточек смен»
 * в content/old-site-content.md. Остальные ключи (map, gone) добавляет Task 12.
 */
return [
    'legacyShiftSlugs' => [
        '5-shagov-k-uspehu',
        'groza-ty-kosmos',
        'kurs-molodogo-boytsa-2',
        'led-i-plamya-tur-vyhodnogo-dnya',
        'leto-groza-1-smena',
        'leto-groza-2-smena',
        'leto-v-groze-1-smena',
        'leto-v-groze-1-smena-2',
        'leto-v-groze-2-smena',
        'leto-v-groze-2-smena-2',
        'maslenitsa',
        'monstry-na-kanikulah',
        'ni-shagu-nazad',
        'ni-shagu-nazad-2',
        'ni-shagu-nazad-3',
        'poehali-2',
        'pogranichnik',
        'razvedchiki',
        'razvetchic',
        'shturmovic',
        'shturmovik',
        'smena-kak-priruchit-drakona',
        'smena-zaschitniki-otechestva',
        'spasatel',
        'vesenniy-grom',
        'vperyod-i-vverh',
        'vperyod-i-vverh-2',
        'vypusknoy',
        'zaschitniki-otechestva',
    ],
];
