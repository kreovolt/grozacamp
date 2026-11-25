<?php
declare(strict_types=1);

namespace Groza\Domain;

use InvalidArgumentException;

/** Единая схема коллекций: проверка данных, посев и формы админки. */
final class Collections
{
    /** Сколько вопросов показывается на главной: счётчик в админке, подсказка и HomePage берут число отсюда. */
    public const FAQ_HOME_MAX = 5;

    /** @var array<string,array<string,mixed>>|null */
    private static ?array $cache = null;

    /** @return array<string,array{label:string, publicUrl:?string, fields:list<array<string,mixed>>, orderable?:bool, counter?:array{field:string,label:string,max:int}}> */
    public static function all(): array
    {
        return self::$cache ??= self::build();
    }

    /** @return array{label:string, publicUrl:?string, fields:list<array<string,mixed>>} */
    public static function get(string $name): array
    {
        return self::all()[$name] ?? throw new InvalidArgumentException("Неизвестная коллекция: $name");
    }

    /**
     * Пункты приказа № 201 по id («mto.b»): номер, короткое название, полная формулировка, обязательность (config/order201.php).
     *
     * @return array<string,array{paragraph:string,title:string,full:string,level:string}>
     */
    private static function legal(): array
    {
        $out = [];
        foreach ((array) require dirname(__DIR__, 2) . '/config/order201.php' as $p) {
            $out[(string) $p['id']] = $p;
        }

        return $out;
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

        $legal = self::legal();
        $mto = ['mto_a' => 'mto.a', 'mto_b' => 'mto.b', 'mto_v' => 'mto.v', 'mto_g' => 'mto.g', 'mto_d' => 'mto.d'];
        $access = [
            'access_a' => 'dostupnaya.a', 'access_b' => 'dostupnaya.b', 'access_v' => 'dostupnaya.v', 'access_g' => 'dostupnaya.g',
            'access_d' => 'dostupnaya.d', 'access_e' => 'dostupnaya.e', 'access_zh' => 'dostupnaya.zh', 'access_z' => 'dostupnaya.z',
            'access_i' => 'dostupnaya.i', 'access_k' => 'dostupnaya.k', 'access_l' => 'dostupnaya.l', 'access_m' => 'dostupnaya.m',
            'access_n' => 'dostupnaya.n',
        ];
        $venueFields = [
            $f('slug', 'text', 'Адрес страницы (латиницей)', $req + ['max' => 80]),
            $f('name', 'text', 'Название', $req + ['max' => 200]),
            $f('address', 'text', 'Адрес', ['max' => 300]),
            $f('photos', 'gallery', 'Фотографии'),
        ];
        // Подпись — «9 б) Условия проживания»: буква и короткое название пункта из приказа № 201; полная формулировка — в «Подсказке приказа»
        $point = static function (string $key, string $id, ?string $heading) use ($f, $legal): array {
            $p = $legal[$id];
            $extra = ['max' => 4000, 'legal' => $p['full']];
            if ($p['level'] === 'optional') {
                $extra['optional'] = true;
            }
            if ($heading !== null) {
                $extra['heading'] = ['title' => $heading];
            }

            return $f($key, 'textarea', $p['paragraph'] . ') ' . $p['title'], $extra);
        };
        $first = true;
        foreach ($mto as $k => $id) {
            $venueFields[] = $point($k, $id, $first ? 'Материально-техническое обеспечение (п. 9)' : null);
            $first = false;
        }
        $first = true;
        foreach ($access as $k => $id) {
            $venueFields[] = $point($k, $id, $first ? 'Доступная среда (п. 11)' : null);
            $first = false;
        }

        return [
            'shifts' => [
                'label' => 'Смены',
                'publicUrl' => '/program/{slug}/',
                'orderable' => false, // на сайте смены идут по дате начала, ручной порядок ни на что не влияет
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
                    $f('education', 'text', 'Образование', ['max' => 300, 'heading' => ['title' => 'Для раздела «Сведения» (п. 6)', 'help' => 'Обязательно для педагогов и вожатых: должность и уровень образования']]),
                    $f('specialty', 'text', 'Специальность', ['max' => 200]),
                    $f('qualification', 'text', 'Квалификация', ['max' => 200]),
                    $f('ovzResponsible', 'bool', 'Отвечает за работу с детьми с ОВЗ', ['help' => 'Человек попадёт в раздел «Сведения», п. 6 б: ответственные за доступность лагеря для детей с ОВЗ и детей-инвалидов']),
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
                'counter' => ['field' => 'onHome', 'label' => 'На главной', 'max' => self::FAQ_HOME_MAX],
                'fields' => [
                    $f('question', 'text', 'Вопрос', $req + ['max' => 300]),
                    $f('answer', 'richtext', 'Ответ', $req),
                    $f('onHome', 'bool', 'Показывать на главной', ['help' => 'На главной показываются до ' . self::FAQ_HOME_MAX . ' вопросов']),
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
