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

$field = fn (string $name, array $vars): string => $this->partial('forms/_field', $vars + [
    'formId' => 'booking', 'name' => $name, 'value' => $values[$name] ?? '', 'error' => $errors[$name] ?? '',
]);

$body = $field('shift', ['label' => 'Смена', 'type' => 'select', 'choices' => $choices, 'required' => true])
    . $field('parentName', ['label' => 'Ваше имя', 'autocomplete' => 'name', 'required' => true, 'maxlength' => 80])
    . $field('phone', ['label' => 'Телефон', 'type' => 'tel', 'autocomplete' => 'tel', 'inputmode' => 'tel', 'required' => true, 'hint' => 'Перезвоним в рабочее время'])
    . $field('childAge', ['label' => 'Возраст ребёнка', 'inputmode' => 'numeric', 'required' => true, 'maxlength' => 2, 'hint' => 'От 5 до 18 лет'])
    . $field('comment', ['label' => 'Комментарий', 'type' => 'textarea', 'maxlength' => 1000])
    . $field('consent', [
        'label' => 'Согласен на обработку персональных данных', 'type' => 'checkbox', 'required' => true,
        'labelHtml' => 'Согласен на обработку персональных данных — <a class="font-semibold text-rust-deep underline" href="/policy/" target="_blank" rel="noopener">политика обработки</a>',
    ]);

echo $this->partial('forms/_shell', [
    'formId' => 'booking',
    'action' => '/form/booking/',
    'success' => 'Спасибо! Мы перезвоним',
    'submitLabel' => 'Отправить заявку',
    'body' => $body,
    'guard' => $guard,
    'errors' => $errors,
]);
