<?php
declare(strict_types=1);

/**
 * SEO: заголовки и описания страниц, картинка для соцсетей, состав sitemap.xml.
 * Заголовок страницы — «{название} — детский лагерь «Гроза», Пермь»; главная — свой заголовок.
 * Для страниц с переменным содержимым (смены, правила, сведения) название и описание приходят из страницы.
 * Описание — до 160 знаков (Meta обрезает по слову).
 */
return [
    'titleSuffix' => ' — детский лагерь «Гроза», Пермь',
    'homeTitle' => 'Детский лагерь «Гроза» — лагерь приключений под Пермью',
    'defaultTitle' => 'Детский лагерь «Гроза», Пермь',
    'defaultDescription' => 'Детский лагерь «Гроза» в Пермском крае: смены для детей 8–17 лет, трансфер из Перми, питание и вожатые рядом. Запишитесь на смену.',
    'image' => '/assets/og-default.jpg',
    'descriptionMax' => 160,
    'pages' => [
        'home' => [
            'description' => 'Детский лагерь «Гроза» в Пермском крае: походы, спорт, командные игры, вечера у костра. Трансфер из Перми и питание входят в стоимость.',
        ],
        'booking' => [
            'title' => 'Купить путёвку',
            'description' => 'Выберите смену детского лагеря «Гроза» и оставьте заявку: офис перезвонит в рабочее время. Проживание, питание и трансфер из Перми включены.',
        ],
        'info' => [
            'title' => 'Информация для родителей',
            'description' => 'Что взять с собой, какие документы нужны, как передать лекарства, как связаться с ребёнком и где сбор на трансфер в детском лагере «Гроза».',
        ],
        'vacancies' => [
            'title' => 'Вакансии',
            'description' => 'Детский лагерь «Гроза» набирает вожатых, педагогов и инструкторов. Расскажите о себе в анкете, офис перезвонит.',
        ],
        'team' => [
            'title' => 'Команда',
            'description' => 'Руководители, педагоги и вожатые детского лагеря «Гроза»: кто будет рядом с вашим ребёнком на смене.',
        ],
        'contacts' => [
            'title' => 'Контакты',
            'description' => 'Телефон, WhatsApp, почта и адрес офиса детского лагеря «Гроза» в Перми. Часы работы: Пн–Пт 10:00–19:00, Сб–Вс 12:00–17:00.',
        ],
        'thanks' => ['title' => 'Спасибо'],
        'form-error' => ['title' => 'Не удалось отправить'],
        'not-found' => ['title' => 'Страница не найдена'],
    ],
    // Публичные страницы в sitemap.xml: путь, приоритет, из каких файлов данных берётся дата изменения.
    // Страницы смен (/program/{slug}/) добавляются из данных. /admin/, /spasibo/, /form/ — не входят.
    'sitemap' => [
        ['path' => '/', 'priority' => '1.0', 'sources' => ['shifts', 'texts', 'venues', 'faq', 'team', 'reviews']],
        ['path' => '/booking/', 'priority' => '0.9', 'sources' => ['shifts', 'venues']],
        ['path' => '/info/', 'priority' => '0.7', 'sources' => ['texts', 'faq']],
        ['path' => '/vacancies/', 'priority' => '0.6', 'sources' => ['vacancies']],
        ['path' => '/team/', 'priority' => '0.5', 'sources' => ['team']],
        ['path' => '/contacts/', 'priority' => '0.6', 'sources' => ['settings']],
        ['path' => '/pravila/', 'priority' => '0.4', 'sources' => ['texts']],
        ['path' => '/policy/', 'priority' => '0.3', 'sources' => ['texts']],
        ['path' => '/svedeniya/', 'priority' => '0.6', 'sources' => ['texts', 'documents', 'shifts', 'venues', 'team']],
        ['path' => '/svedeniya/ob-organizacii/', 'priority' => '0.5', 'sources' => ['texts', 'documents', 'team', 'settings']],
        ['path' => '/svedeniya/deyatelnost/', 'priority' => '0.5', 'sources' => ['texts', 'shifts', 'venues']],
        ['path' => '/svedeniya/mto/', 'priority' => '0.5', 'sources' => ['venues', 'shifts']],
        ['path' => '/svedeniya/uslugi/', 'priority' => '0.5', 'sources' => ['texts', 'shifts']],
        ['path' => '/svedeniya/dostupnaya-sreda/', 'priority' => '0.5', 'sources' => ['venues', 'shifts']],
        ['path' => '/svedeniya/inaya-informaciya/', 'priority' => '0.4', 'sources' => ['texts', 'documents']],
    ],
];
