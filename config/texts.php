<?php
declare(strict_types=1);

/**
 * Реестр текстовых блоков сайта (storage/data/texts.json — объект «ключ → значение»).
 *
 * type:     plain — строка; richtext — HTML (p, br, strong, em, a, ul, ol, li, h3, blockquote);
 *           list — список строк (для «значение|подпись» и «время|дело» — через вертикальную черту).
 * group:    раздел админки: home — «Главная», info — «Родителям», svedeniya — «Сведения»,
 *           vacancies — «Вакансии», legal — «Документы о данных».
 * required: пункт приказа Минпросвещения № 201, если блок по нему обязателен.
 *
 * @return array<string, array{label:string, type:'plain'|'richtext'|'list', group:'home'|'info'|'svedeniya'|'vacancies'|'legal', required?:string}>
 */
return [
    'home.heroKicker' => ['label' => 'Строка над заголовком (если пусто — по ближайшему сезону смен)', 'type' => 'plain', 'group' => 'home'],
    'home.heroTitle' => ['label' => 'Заголовок первого экрана', 'type' => 'plain', 'group' => 'home'],
    'home.heroLead' => ['label' => 'Подзаголовок первого экрана', 'type' => 'plain', 'group' => 'home'],
    'home.stats' => ['label' => 'Полоса цифр (строка: «значение|подпись»)', 'type' => 'list', 'group' => 'home'],
    'home.included' => ['label' => 'Что входит в стоимость', 'type' => 'list', 'group' => 'home'],
    'home.schedule' => ['label' => 'Распорядок дня (строка: «время|дело»)', 'type' => 'list', 'group' => 'home'],

    'info.prepare' => ['label' => 'Как подготовить ребёнка к первой поездке', 'type' => 'richtext', 'group' => 'info'],
    'info.contraindications' => ['label' => 'Противопоказания', 'type' => 'richtext', 'group' => 'info'],
    'info.medicines' => ['label' => 'Как передать лекарства', 'type' => 'richtext', 'group' => 'info'],
    'info.contact' => ['label' => 'Связь с ребёнком', 'type' => 'richtext', 'group' => 'info'],
    'info.transfer' => ['label' => 'Трансфер: где и когда сбор', 'type' => 'richtext', 'group' => 'info'],
    'info.visits' => ['label' => 'Посещения', 'type' => 'richtext', 'group' => 'info'],
    'lists.packing' => ['label' => 'Что взять с собой: одежда, обувь, средства гигиены', 'type' => 'list', 'group' => 'info', 'required' => '10.д'],
    'lists.documents' => ['label' => 'Документы для зачисления ребёнка', 'type' => 'list', 'group' => 'info', 'required' => '10.г'],
    'rules' => ['label' => 'Правила пребывания в лагере', 'type' => 'richtext', 'group' => 'info', 'required' => '4.б'],

    'policy' => ['label' => 'Политика обработки персональных данных', 'type' => 'richtext', 'group' => 'legal'],

    'sved.campType' => ['label' => 'Тип организации отдыха детей', 'type' => 'plain', 'group' => 'svedeniya', 'required' => '3.г'],
    'sved.legalForm' => ['label' => 'Организационно-правовая форма', 'type' => 'plain', 'group' => 'svedeniya', 'required' => '3.в'],
    'sved.fullName' => ['label' => 'Полное наименование', 'type' => 'plain', 'group' => 'svedeniya', 'required' => '3.а'],
    'sved.shortName' => ['label' => 'Краткое наименование', 'type' => 'plain', 'group' => 'svedeniya'],
    'sved.director' => ['label' => 'Руководитель (ФИО)', 'type' => 'plain', 'group' => 'svedeniya', 'required' => '3.б'],
    'sved.addresses' => ['label' => 'Адреса, в том числе мест проведения смен (строка — один адрес)', 'type' => 'list', 'group' => 'svedeniya', 'required' => '3.д'],
    'sved.mode' => ['label' => 'Режим и график работы', 'type' => 'richtext', 'group' => 'svedeniya', 'required' => '3.е'],
    'sved.ages' => ['label' => 'Возраст детей', 'type' => 'richtext', 'group' => 'svedeniya', 'required' => '8.а'],
    'sved.eduPrograms' => ['label' => 'Дополнительные общеобразовательные программы', 'type' => 'richtext', 'group' => 'svedeniya'],
    'sved.healthPrograms' => ['label' => 'Оздоровительные программы', 'type' => 'richtext', 'group' => 'svedeniya'],
    'sved.methods' => ['label' => 'Методические разработки', 'type' => 'richtext', 'group' => 'svedeniya'],
    'sved.paidOrder' => ['label' => 'Порядок оказания платных услуг', 'type' => 'richtext', 'group' => 'svedeniya', 'required' => '10.а'],
    'sved.compensation' => ['label' => 'Компенсация стоимости путёвки', 'type' => 'richtext', 'group' => 'svedeniya'],
    'sved.other' => ['label' => 'Иная информация', 'type' => 'richtext', 'group' => 'svedeniya'],

    'vacancies.intro' => ['label' => 'Вступление', 'type' => 'richtext', 'group' => 'vacancies'],
    'vacancies.benefits' => ['label' => 'Что даём', 'type' => 'richtext', 'group' => 'vacancies'],
    'vacancies.selection' => ['label' => 'Как проходит отбор', 'type' => 'richtext', 'group' => 'vacancies'],
];
