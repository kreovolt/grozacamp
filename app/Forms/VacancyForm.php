<?php
declare(strict_types=1);

namespace Groza\Forms;

use Groza\Support\Phone;

final class VacancyForm
{
    public const OTHER = 'Другое';
    private const VK = '#^https://(www\.|m\.)?vk\.(com|ru)/[A-Za-z0-9_.\-/?=&%\#]{1,150}$#';

    /**
     * @param array<string,mixed> $post
     * @param list<string> $positions
     */
    public static function validate(array $post, array $positions): ValidationResult
    {
        $errors = [];

        $name = Fields::line($post['fullName'] ?? '');
        $len = mb_strlen($name);
        if ($len < 2 || $len > 120) {
            $errors['fullName'] = 'Укажите ФИО — от 2 до 120 символов';
        }

        $phone = Phone::normalize(Fields::line($post['phone'] ?? ''));
        if ($phone === null) {
            $errors['phone'] = 'Проверьте номер телефона — например, +7 950 441-12-99';
        }

        $email = Fields::line($post['email'] ?? '');
        if ($email !== '' && (mb_strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false)) {
            $errors['email'] = 'Проверьте адрес почты';
        }

        $age = Fields::int($post['age'] ?? '');
        if ($age === null || $age < 16 || $age > 80) {
            $errors['age'] = 'Возраст — от 16 до 80 лет';
        }

        $position = Fields::line($post['position'] ?? '');
        if ($position !== self::OTHER && !in_array($position, $positions, true)) {
            $errors['position'] = 'Выберите должность из списка';
        }

        $experience = Fields::text($post['experience'] ?? '');
        if (mb_strlen($experience) > 2000) {
            $errors['experience'] = 'Не больше 2000 символов';
        }

        $shifts = Fields::line($post['shifts'] ?? '');
        if (mb_strlen($shifts) > 300) {
            $errors['shifts'] = 'Не больше 300 символов';
        }

        $vk = Fields::line($post['vk'] ?? '');
        if ($vk !== '' && preg_match(self::VK, $vk) !== 1) {
            $errors['vk'] = 'Ссылка должна начинаться с https://vk.com/';
        }

        if (($post['consent'] ?? '') !== 'yes') {
            $errors['consent'] = BookingForm::CONSENT_ERROR;
        }

        return new ValidationResult([
            'fullName' => $name,
            'phone' => $phone ?? '',
            'email' => $email,
            'age' => $age ?? 0,
            'position' => $position,
            'experience' => $experience,
            'shifts' => $shifts,
            'vk' => $vk,
        ], $errors);
    }
}
