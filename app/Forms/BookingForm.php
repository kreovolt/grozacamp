<?php
declare(strict_types=1);

namespace Groza\Forms;

use Groza\Support\Phone;

final class BookingForm
{
    public const CONSENT_ERROR = 'Нужно согласие на обработку данных';

    /**
     * @param array<string,mixed> $post
     * @param list<string> $openShiftSlugs
     */
    public static function validate(array $post, array $openShiftSlugs): ValidationResult
    {
        $errors = [];

        $shift = Fields::line($post['shift'] ?? '');
        if ($shift !== 'any' && !in_array($shift, $openShiftSlugs, true)) {
            $errors['shift'] = 'Выберите смену из списка';
        }

        $name = Fields::line($post['parentName'] ?? '');
        $len = mb_strlen($name);
        if ($len < 2 || $len > 80) {
            $errors['parentName'] = 'Укажите имя — от 2 до 80 символов';
        }

        $phone = Phone::normalize(Fields::line($post['phone'] ?? ''));
        if ($phone === null) {
            $errors['phone'] = 'Проверьте номер телефона — например, +7 950 441-12-99';
        }

        $age = Fields::int($post['childAge'] ?? '');
        if ($age === null || $age < 5 || $age > 18) {
            $errors['childAge'] = 'Возраст ребёнка — от 5 до 18 лет';
        }

        $comment = Fields::text($post['comment'] ?? '');
        if (mb_strlen($comment) > 1000) {
            $errors['comment'] = 'Не больше 1000 символов';
        }

        if (($post['consent'] ?? '') !== 'yes') {
            $errors['consent'] = self::CONSENT_ERROR;
        }

        return new ValidationResult([
            'shift' => $shift,
            'parentName' => $name,
            'phone' => $phone ?? '',
            'childAge' => $age ?? 0,
            'comment' => $comment,
        ], $errors);
    }
}
