<?php
declare(strict_types=1);

/**
 * Меню сайта. Подписи и порядок требует заказчик (спека §5.2, §5.3) — в админке не редактируются.
 * Любое изменение здесь должно сопровождаться правкой tests/php/Unit/View/NavTest.php.
 */
return [
    'main' => [
        ['label' => 'Купить путёвку', 'href' => '/booking/', 'kind' => 'button'],
        ['label' => 'Информация для родителей', 'href' => '/info/', 'kind' => 'link'],
        ['label' => 'Вакансии', 'href' => '/vacancies/', 'kind' => 'link'],
        [
            'label' => 'Сведения об организации отдыха детей и их оздоровления',
            'href' => '/svedeniya/',
            'kind' => 'dropdown',
            'children' => [
                [
                    'label' => 'Об организации отдыха детей и их оздоровления',
                    'href' => '/svedeniya/ob-organizacii/',
                    'children' => [
                        ['label' => 'Основные сведения', 'href' => '/svedeniya/ob-organizacii/#osnovnye-svedeniya'],
                        ['label' => 'Документы', 'href' => '/svedeniya/ob-organizacii/#dokumenty'],
                        ['label' => 'Руководство', 'href' => '/svedeniya/ob-organizacii/#rukovodstvo'],
                        ['label' => 'Педагогический и вожатский состав', 'href' => '/svedeniya/ob-organizacii/#sostav'],
                        ['label' => 'Контакты', 'href' => '/svedeniya/ob-organizacii/#kontakty'],
                    ],
                ],
                ['label' => 'Деятельность', 'href' => '/svedeniya/deyatelnost/'],
                ['label' => 'Материально-техническое обеспечение и оснащённость', 'href' => '/svedeniya/mto/'],
                ['label' => 'Услуги, в том числе платные', 'href' => '/svedeniya/uslugi/'],
                ['label' => 'Доступная среда', 'href' => '/svedeniya/dostupnaya-sreda/'],
                ['label' => 'Иная информация', 'href' => '/svedeniya/inaya-informaciya/'],
            ],
        ],
    ],

    // Колонка «Лагерю» в подвале
    'footer' => [
        ['label' => 'Купить путёвку', 'href' => '/booking/'],
        ['label' => 'Смены', 'href' => '/booking/#smeny'],
        ['label' => 'Команда', 'href' => '/team/'],
        ['label' => 'Информация для родителей', 'href' => '/info/'],
        ['label' => 'Вакансии', 'href' => '/vacancies/'],
    ],

    // Нижняя строка подвала
    'legal' => [
        ['label' => 'Политика обработки персональных данных', 'href' => '/policy/'],
        ['label' => 'Правила пребывания', 'href' => '/pravila/'],
    ],
];
