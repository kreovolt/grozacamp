<?php
declare(strict_types=1);

/**
 * Анкета на вакансию (спека §9.2). Без загрузки файлов.
 * @var array<string,string> $values
 * @var array<string,string> $errors
 * @var array{positions:list<string>} $options
 * @var array<string,string> $guard
 */
$choices = [['', 'Выберите должность']];
foreach ($options['positions'] ?? [] as $p) {
    $choices[] = [$p, $p];
}

$defs = [
    'position' => ['label' => 'Должность', 'type' => 'select', 'choices' => $choices, 'required' => true],
    'fullName' => ['label' => 'ФИО', 'autocomplete' => 'name', 'required' => true, 'maxlength' => 120],
    'phone' => ['label' => 'Телефон', 'type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel', 'required' => true],
    'email' => ['label' => 'Почта', 'type' => 'email', 'autocomplete' => 'email', 'maxlength' => 120],
    'age' => ['label' => 'Возраст', 'inputmode' => 'numeric', 'required' => true, 'maxlength' => 2],
    'experience' => ['label' => 'Опыт работы с детьми', 'type' => 'textarea', 'maxlength' => 2000],
    'shifts' => ['label' => 'Удобные смены', 'maxlength' => 300],
    'vk' => ['label' => 'Страница ВКонтакте', 'type' => 'url', 'inputmode' => 'url', 'maxlength' => 150, 'hint' => 'Например, https://vk.com/ivanov'],
    'consent' => [
        'label' => 'Согласен на обработку персональных данных', 'type' => 'checkbox', 'required' => true,
        'labelHtml' => 'Согласен на обработку персональных данных — <a class="relative z-10 font-semibold text-rust-deep underline" href="/policy/" target="_blank" rel="noopener">политика обработки</a>',
    ],
];

$field = fn (string $name, array $vars): string => $this->partial('forms/_field', $vars + [
    'formId' => 'vacancy', 'name' => $name, 'value' => $values[$name] ?? '', 'error' => $errors[$name] ?? '',
]);
$body = implode('', array_map($field, array_keys($defs), $defs));
// подписи полей — для сводки ошибок в начале формы (без JS)
$labels = array_map(static fn (array $d): string => (string) $d['label'], $defs);

echo $this->partial('forms/_shell', [
    'formId' => 'vacancy',
    'action' => '/form/vacancy/#anketa', // без JS ответ с ошибками откроется сразу у анкеты
    'success' => 'Спасибо! Мы свяжемся с вами',
    'submitLabel' => 'Отправить анкету',
    'body' => $body,
    'guard' => $guard,
    'errors' => $errors,
    'labels' => $labels,
]);
