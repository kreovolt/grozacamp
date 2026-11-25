<?php
declare(strict_types=1);

/**
 * Переадресации со старого сайта (спека §5.4), все 301 за один переход; исполняет PHP (App::redirects()):
 *  map              — точные адреса → новый адрес (строка запроса сохраняется перед #якорем);
 *  legacyShiftSlugs — прежние карточки смен /program/{slug}/ (источник: «История карточек смен» в content/old-site-content.md);
 *                     если такой смены нет в данных, ведут на /booking/ (ShiftPage);
 *  gone             — служебные адреса WordPress, которых нет: 404 (запись заканчивается на «/» — закрыт и весь раздел).
 */
return [
    'map' => [
        '/program/' => '/booking/',
        '/faq/' => '/info/#faq',
        '/about/' => '/',
        '/o-lagere/' => '/',
        '/official_info/' => '/svedeniya/',
        '/glavnaya/dokumenty/' => '/svedeniya/ob-organizacii/#dokumenty',
        '/plan/' => '/svedeniya/deyatelnost/',
        '/pravila-prebyvaniya-v-lagere-groza/' => '/pravila/',
        '/programma-voenno-patrioticheskogo-lagerya-groza/' => '/svedeniya/ob-organizacii/#dokumenty',
        '/booking_form_borba/' => '/booking/',
        '/forma-bronirovaniya-sambo/' => '/booking/',
        '/let/' => '/booking/',
    ],
    'gone' => ['/wp-admin/', '/wp-login.php', '/xmlrpc.php'],
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
