<?php
declare(strict_types=1);

/**
 * Форма заявки на путёвку (спека §9.1).
 * @var array<string,string> $values
 * @var array<string,string> $errors
 * @var array{shifts:list<array{slug:string,label:string}>} $options
 * @var array<string,string> $guard
 */
$choices = [['', 'Выберите смену']];
foreach ($options['shifts'] ?? [] as $s) {
    $choices[] = [$s['slug'], $s['label']];
}
$choices[] = ['any', 'Пока не знаю / любая'];

$defs = [
    'shift' => ['label' => 'Смена', 'type' => 'select', 'choices' => $choices, 'required' => true],
    'parentName' => ['label' => 'Ваше имя', 'autocomplete' => 'name', 'required' => true, 'maxlength' => 80],
    'phone' => ['label' => 'Телефон', 'type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel', 'required' => true, 'hint' => 'Перезвоним в рабочее время'],
    'childAge' => ['label' => 'Возраст ребёнка', 'inputmode' => 'numeric', 'required' => true, 'maxlength' => 2, 'hint' => 'От 5 до 18 лет'],
    'comment' => ['label' => 'Комментарий', 'type' => 'textarea', 'maxlength' => 1000],
    'consent' => [
        'label' => 'Согласен на обработку персональных данных', 'type' => 'checkbox', 'required' => true,
        'labelHtml' => 'Согласен на обработку персональных данных — <a class="font-semibold text-rust-deep underline" href="/policy/" target="_blank" rel="noopener">политика обработки</a>',
    ],
];

$field = fn (string $name, array $vars): string => $this->partial('forms/_field', $vars + [
    'formId' => 'booking', 'name' => $name, 'value' => $values[$name] ?? '', 'error' => $errors[$name] ?? '',
]);
$body = implode('', array_map($field, array_keys($defs), $defs));
// подписи полей — для сводки ошибок в начале формы (без JS)
$labels = array_map(static fn (array $d): string => (string) $d['label'], $defs);

echo $this->partial('forms/_shell', [
    'formId' => 'booking',
    'action' => '/form/booking/#zayavka', // без JS ответ с ошибками откроется сразу у формы, а не в начале страницы
    'success' => 'Спасибо! Мы перезвоним',
    'submitLabel' => 'Отправить заявку',
    'body' => $body,
    'guard' => $guard,
    'errors' => $errors,
    'labels' => $labels,
]);
