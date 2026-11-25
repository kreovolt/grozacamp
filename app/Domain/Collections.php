<?php
declare(strict_types=1);

namespace Groza\Domain;

use InvalidArgumentException;

/** Единая схема коллекций: проверка данных, посев и формы админки. */
final class Collections
{
    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string,array{label:string, publicUrl:?string, fields:list<array<string,mixed>>}> */
    public static function all(): array
    {
        return self::$cache ??= self::build();
    }

    /** @return array{label:string, publicUrl:?string, fields:list<array<string,mixed>>} */
    public static function get(string $name): array
    {
        return self::all()[$name] ?? throw new InvalidArgumentException("Неизвестная коллекция: $name");
    }

    /** @return array<string,mixed> */
    private static function f(string $name, string $type, string $label, array $extra = []): array
    {
        return ['name' => $name, 'type' => $type, 'label' => $label] + $extra;
    }

    private static function build(): array
    {
        $f = self::f(...);
        $req = ['required' => true];

        $mto = [
            'mto_a' => 'Учебные кабинеты и объекты для практических занятий (п. 9 г)',
            'mto_b' => 'Библиотека и объекты спорта (п. 9 г)',
            'mto_v' => 'Условия проживания и питания (п. 9 б, в)',
            'mto_g' => 'Обеспечение оздоровительной деятельности, если ведётся (п. 9 д)',
            'mto_d' => 'Даты ввода в эксплуатацию и капитального ремонта (п. 9 а)',
        ];
        $access = [
            'access_a' => 'а', 'access_b' => 'б', 'access_v' => 'в', 'access_g' => 'г', 'access_d' => 'д',
            'access_e' => 'е', 'access_zh' => 'ж', 'access_z' => 'з', 'access_i' => 'и', 'access_k' => 'к',
            'access_l' => 'л', 'access_m' => 'м', 'access_n' => 'н',
        ];
        $venueFields = [
            $f('slug', 'text', 'Адрес страницы (латиницей)', $req + ['max' => 80]),
            $f('name', 'text', 'Название', $req + ['max' => 200]),
            $f('address', 'text', 'Адрес', ['max' => 300]),
            $f('photos', 'gallery', 'Фотографии'),
        ];
        foreach ($mto as $k => $label) {
            $venueFields[] = $f($k, 'textarea', $label, ['max' => 4000]);
        }
        foreach ($access as $k => $letter) {
            $venueFields[] = $f($k, 'textarea', "Доступная среда, п. 11 $letter", ['max' => 4000]);
        }

        return [
            'shifts' => [
                'label' => 'Смены',
                'publicUrl' => '/program/{slug}/',
                'fields' => [
                    $f('title', 'text', 'Название', $req + ['max' => 120]),
                    $f('slug', 'text', 'Адрес страницы (латиницей)', ['max' => 80, 'help' => 'Если оставить пустым, возьмётся из названия']),
                    $f('season', 'text', 'Сезон', ['max' => 80, 'help' => 'Например: Лето 2027']),
                    $f('dateStart', 'date', 'Дата начала', $req),
                    $f('dateEnd', 'date', 'Дата окончания', $req),
                    $f('days', 'int', 'Дней', ['computed' => true, 'help' => 'Считается по датам']),
                    $f('ageMin', 'int', 'Возраст от', $req),
                    $f('ageMax', 'int', 'Возраст до', $req),
                    $f('price', 'int', 'Цена, ₽', ['help' => 'Можно оставить пустой']),
                    $f('priceNote', 'text', 'Пояснение к цене', ['max' => 200]),
                    $f('status', 'enum', 'Статус', $req + ['options' => Shifts::STATUS_LABELS]),
                    $f('venue', 'ref', 'База', ['ref' => 'venues']),
                    $f('photo', 'image', 'Фото'),
                    $f('lead', 'textarea', 'Коротко о смене', ['max' => 400]),
                    $f('activities', 'list', 'Чем занимаемся'),
                    $f('body', 'richtext', 'Описание'),
                    $f('hidden', 'bool', 'Скрыть с сайта'),
                ],
            ],
            'venues' => [
                'label' => 'Базы',
                'publicUrl' => '/svedeniya/mto/',
                'fields' => $venueFields,
            ],
            'team' => [
                'label' => 'Команда',
                'publicUrl' => '/team/',
                'fields' => [
                    $f('name', 'text', 'Имя', $req + ['max' => 120]),
                    $f('role', 'text', 'Должность', $req + ['max' => 160]),
                    $f('group', 'enum', 'Группа', $req + ['options' => [
                        'leadership' => 'Руководство', 'pedagogue' => 'Педагог', 'counselor' => 'Вожатый', 'other' => 'Другое',
                    ]]),
                    $f('education', 'text', 'Образование', ['max' => 300]),
                    $f('specialty', 'text', 'Специальность', ['max' => 200]),
                    $f('qualification', 'text', 'Квалификация', ['max' => 200]),
                    $f('ovzResponsible', 'bool', 'Отвечает за работу с детьми с ОВЗ'),
                    $f('photo', 'image', 'Фото'),
                    $f('about', 'textarea', 'О себе', ['max' => 1500]),
                    $f('onHome', 'bool', 'Показывать на главной'),
                    $f('order', 'int', 'Порядок'),
                    $f('hidden', 'bool', 'Скрыть с сайта'),
                ],
            ],
            'documents' => [
                'label' => 'Документы',
                'publicUrl' => '/svedeniya/ob-organizacii/#dokumenty',
                'fields' => [
                    $f('title', 'text', 'Название', $req + ['max' => 200]),
                    $f('section', 'enum', 'Раздел', $req + ['options' => [
                        'uchreditelnye' => 'Учредительные документы',
                        'pravila' => 'Правила нахождения на территории',
                        'programma-vospitaniya' => 'Программа воспитательной работы',
                        'kalendarnyj-plan' => 'Календарный план воспитательной работы',
                        'programma-razvitiya' => 'Программа развития',
                        'inoe' => 'Иное',
                    ]]),
                    $f('file', 'file', 'Файл (PDF)', $req),
                    $f('date', 'date', 'Дата документа'),
                    $f('order', 'int', 'Порядок'),
                ],
            ],
            'vacancies' => [
                'label' => 'Вакансии',
                'publicUrl' => '/vacancies/',
                'fields' => [
                    $f('title', 'text', 'Должность', $req + ['max' => 160]),
                    $f('summary', 'textarea', 'Коротко', ['max' => 600]),
                    $f('conditions', 'richtext', 'Условия и требования'),
                    $f('hidden', 'bool', 'Скрыть с сайта'),
                ],
            ],
            'faq' => [
                'label' => 'Вопросы и ответы',
                'publicUrl' => '/info/#faq',
                'fields' => [
                    $f('question', 'text', 'Вопрос', $req + ['max' => 300]),
                    $f('answer', 'richtext', 'Ответ', $req),
                    $f('onHome', 'bool', 'Показывать на главной'),
                    $f('order', 'int', 'Порядок'),
                ],
            ],
            'reviews' => [
                'label' => 'Отзывы',
                'publicUrl' => '/',
                'fields' => [
                    $f('text', 'textarea', 'Текст отзыва', $req + ['max' => 1500]),
                    $f('author', 'text', 'Подпись', ['max' => 120]),
                    $f('visible', 'bool', 'Показывать на сайте'),
                ],
            ],
        ];
    }
}
