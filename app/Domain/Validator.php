<?php
declare(strict_types=1);

namespace Groza\Domain;

use DateTimeImmutable;
use Groza\Support\Sanitizer;
use Groza\Support\Slug;

final class Validator
{
    private const REQUIRED = 'Заполните поле';
    private const KEY = '/^[a-z0-9-]+$/D';
    private const INT_MAX = 999_999_999;
    /** Загруженные файлы и фото из манифеста: только внутри /uploads/ и /assets/, без «..» и схем. */
    private const LOCAL_PATH = '#^/(?:uploads|assets)/[A-Za-z0-9_.-]+(?:/[A-Za-z0-9_.-]+)*$#D';

    /**
     * Нормализует запись по схеме; поля вне схемы отбрасываются.
     *
     * @param array{fields:list<array<string,mixed>>} $collectionDef
     * @param array<string,list<string>> $refSlugs допустимые slug по коллекциям (если ключ есть — проверяем наличие)
     * @return array<string,mixed>
     * @throws ValidationException
     */
    public static function validate(array $collectionDef, array $input, array $refSlugs = []): array
    {
        $out = [];
        $errors = [];

        foreach ($collectionDef['fields'] as $field) {
            $name = $field['name'];
            if (!empty($field['computed'])) {
                $out[$name] = null;
                continue;
            }
            $raw = $input[$name] ?? null;
            if ($name === 'slug') {
                $raw = self::slugSource($raw, $input);
            }
            try {
                $value = self::normalize($field, $raw, $refSlugs);
            } catch (FieldError $e) {
                $errors[$name] = $e->getMessage();
                $out[$name] = null;
                continue;
            }
            if (!empty($field['required']) && self::isEmpty($value)) {
                $errors[$name] = self::REQUIRED;
            }
            $out[$name] = $value;
        }

        self::crossChecks($collectionDef, $out, $errors);

        if ($errors) {
            throw new ValidationException($errors);
        }
        return $out;
    }

    private static function slugSource(mixed $raw, array $input): string
    {
        $s = is_string($raw) ? Slug::fromRu($raw) : '';
        if ($s === '') {
            foreach (['title', 'name'] as $k) {
                if (isset($input[$k]) && is_string($input[$k])) {
                    return Slug::fromRu($input[$k]);
                }
            }
        }
        return $s;
    }

    private static function isEmpty(mixed $v): bool
    {
        return $v === null || $v === '' || $v === [];
    }

    private static function crossChecks(array $def, array &$out, array &$errors): void
    {
        $names = array_column($def['fields'], 'name');
        if (!in_array('dateStart', $names, true) || !in_array('days', $names, true)) {
            return;
        }
        $s = $out['dateStart'] ?? null;
        $e = $out['dateEnd'] ?? null;
        if ($s !== null && $e !== null) {
            if ($e < $s) {
                $errors['dateEnd'] = 'Дата окончания раньше даты начала';
            } else {
                $out['days'] = Shifts::days($s, $e);
            }
        }
        if (isset($out['ageMin'], $out['ageMax']) && $out['ageMax'] < $out['ageMin'] && !isset($errors['ageMax'])) {
            $errors['ageMax'] = 'Максимальный возраст меньше минимального';
        }
    }

    /** @throws FieldError */
    private static function normalize(array $field, mixed $raw, array $refSlugs): mixed
    {
        return match ($field['type']) {
            'text' => self::text($raw, $field, false),
            'textarea' => self::text($raw, $field, true),
            'richtext' => is_string($raw) ? Sanitizer::clean($raw) : '',
            'int' => self::int($raw),
            'date' => self::date($raw),
            'enum' => self::enum($raw, $field),
            'bool' => $raw === true || $raw === 1 || (is_string($raw) && in_array(strtolower($raw), ['1', 'on', 'true', 'yes'], true)),
            'image' => self::image($raw),
            'gallery' => self::gallery($raw),
            'file' => self::file($raw),
            'list' => self::list($raw),
            'ref' => self::ref($raw, $field, $refSlugs),
        };
    }

    private static function text(mixed $raw, array $field, bool $multiline): string
    {
        $s = is_scalar($raw) ? (string) $raw : '';
        $s = str_replace("\r\n", "\n", $s);
        $s = str_replace("\r", "\n", $s);
        $s = preg_replace($multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/' : '/[\x00-\x1F\x7F]/', $multiline ? '' : ' ', $s) ?? '';
        $s = trim($s);
        if (isset($field['max'])) {
            $s = trim(mb_substr($s, 0, (int) $field['max']));
        }
        if (($field['name'] ?? '') === 'slug') {
            $s = rtrim($s, '-'); // обрезка по max не оставляет дефис в конце адреса
        }
        return $s;
    }

    /** @throws FieldError */
    private static function int(mixed $raw): ?int
    {
        if (is_int($raw)) {
            return $raw >= 0 && $raw <= self::INT_MAX ? $raw : throw new FieldError('Введите целое число');
        }
        $s = is_string($raw) ? preg_replace('/[\s\x{00A0}]+/u', '', $raw) : '';
        if ($s === '' || $s === null) {
            return null;
        }
        if (!ctype_digit($s)) {
            throw new FieldError('Введите целое число');
        }
        $s = ltrim($s, '0');
        if (strlen($s) > 9) {
            throw new FieldError('Введите целое число');
        }
        return (int) $s;
    }

    /** @throws FieldError */
    private static function date(mixed $raw): ?string
    {
        $s = is_string($raw) ? trim($raw) : '';
        if ($s === '') {
            return null;
        }
        $d = DateTimeImmutable::createFromFormat('!Y-m-d', $s);
        if (!$d || $d->format('Y-m-d') !== $s) {
            throw new FieldError('Укажите дату в формате ГГГГ-ММ-ДД');
        }
        return $s;
    }

    /** @throws FieldError */
    private static function enum(mixed $raw, array $field): ?string
    {
        $s = is_string($raw) ? trim($raw) : '';
        if ($s === '') {
            return null;
        }
        if (!array_key_exists($s, $field['options'])) {
            throw new FieldError('Выберите значение из списка');
        }
        return $s;
    }

    /** @throws FieldError */
    private static function image(mixed $raw): string|array|null
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_string($raw) && preg_match(self::KEY, $raw)) {
            return $raw;
        }
        if (is_array($raw) && self::isDescriptor($raw)) {
            $out = [
                'src' => $raw['src'], 'widths' => array_values($raw['widths']),
                'formats' => array_values($raw['formats']), 'w' => $raw['w'], 'h' => $raw['h'],
            ];
            if (isset($raw['alt']) && is_string($raw['alt']) && $raw['alt'] !== '') {
                $out['alt'] = $raw['alt']; // подпись к фото: её задаёт сервер из поля формы, не клиент
            }

            return $out;
        }
        throw new FieldError('Некорректное изображение');
    }

    private static function isDescriptor(array $a): bool
    {
        return isset($a['src'], $a['widths'], $a['formats'], $a['w'], $a['h'])
            && is_string($a['src']) && self::isLocalPath($a['src'])
            && self::isPositiveInt($a['w']) && self::isPositiveInt($a['h'])
            && is_array($a['widths']) && $a['widths'] !== [] && array_is_list($a['widths']) && array_filter($a['widths'], self::isPositiveInt(...)) === $a['widths']
            && is_array($a['formats']) && $a['formats'] !== [] && array_is_list($a['formats'])
            && array_filter($a['formats'], static fn (mixed $f): bool => is_string($f) && preg_match('/^[a-z0-9]{2,5}$/D', $f) === 1) === $a['formats'];
    }

    private static function isLocalPath(string $path): bool
    {
        return preg_match(self::LOCAL_PATH, $path) === 1 && !str_contains($path, '..');
    }

    private static function isPositiveInt(mixed $v): bool
    {
        return is_int($v) && $v > 0 && $v <= self::INT_MAX;
    }

    /** @throws FieldError */
    private static function gallery(mixed $raw): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }
        if (!is_array($raw)) {
            throw new FieldError('Некорректный список изображений');
        }
        $out = [];
        foreach ($raw as $item) {
            $img = self::image($item);
            if ($img !== null) {
                $out[] = $img;
            }
        }
        return $out;
    }

    /** @throws FieldError */
    private static function file(mixed $raw): ?array
    {
        if ($raw === null || $raw === '') {
            return null;
        }
        if (is_array($raw) && isset($raw['url'], $raw['bytes'], $raw['originalName'])
            && is_string($raw['url']) && self::isLocalPath($raw['url']) && is_int($raw['bytes']) && $raw['bytes'] >= 0 && is_string($raw['originalName'])) {
            return ['url' => $raw['url'], 'bytes' => $raw['bytes'], 'originalName' => $raw['originalName']];
        }
        throw new FieldError('Некорректный файл');
    }

    private static function list(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $item) {
            if (is_scalar($item)) {
                $s = self::text($item, ['max' => 300], false);
                if ($s !== '') {
                    $out[] = $s;
                }
            }
        }
        return $out;
    }

    /** @throws FieldError */
    private static function ref(mixed $raw, array $field, array $refSlugs): ?string
    {
        $s = is_string($raw) ? trim($raw) : '';
        if ($s === '') {
            return null;
        }
        if (!preg_match(self::KEY, $s)) {
            throw new FieldError('Некорректная ссылка');
        }
        $known = $refSlugs[$field['ref'] ?? ''] ?? null;
        if ($known !== null && !in_array($s, $known, true)) {
            throw new FieldError('Такой записи нет');
        }
        return $s;
    }
}
