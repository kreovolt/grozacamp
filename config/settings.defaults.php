<?php
declare(strict_types=1);

/**
 * Контакты по умолчанию (Global Constraints). Настройки из storage/data/settings.json
 * перекрывают их ключ за ключом; пустое значение в настройках скрывает пункт на сайте.
 * Телефоны — в формате E.164, в шаблонах выводятся через Phone::pretty().
 */
return [
    'phone' => '+73422474347',
    'whatsapp' => '+79991239300',
    'email' => '2474347@bk.ru',
    'officeAddress' => 'г. Пермь, ул. Милиционера Власова, 37',
    'hoursWeekdays' => 'Пн–Пт 10:00–19:00',
    'hoursWeekend' => 'Сб–Вс 12:00–17:00',
    'vk' => 'https://vk.com/grozacamp',
    'youtube' => 'https://www.youtube.com/channel/UCfKD7JRX5wEmFvLhp5KWawQ',
];
