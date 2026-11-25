<?php
declare(strict_types=1);

namespace Groza\Content;

use Groza\Forms\Fields;

/**
 * Разбор данных из storage/data для страниц: один источник вместо копий в каждой странице.
 * Все методы принимают то, что вернул JsonStore::read(), и не падают на неожиданной форме данных.
 */
final class PageData
{
    /**
     * Записи коллекции (`{"items": [...]}`) — только массивы.
     *
     * @param array<string,mixed> $collection результат JsonStore::read()
     * @return list<array<string,mixed>>
     */
    public static function items(array $collection): array
    {
        $items = $collection['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** Записи по полю order (стабильно: при равных — как в файле). @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    public static function byOrder(array $items): array
    {
        $indexed = array_map(null, array_keys($items), $items);
        usort($indexed, static fn (array $a, array $b): int => ((int) ($a[1]['order'] ?? 0) <=> (int) ($b[1]['order'] ?? 0)) ?: $a[0] <=> $b[0]);

        return array_map(static fn (array $pair): array => $pair[1], $indexed);
    }

    /**
     * Вопросы и ответы: по полю order, при $onHomeOnly — только отмеченные «на главную», не больше $limit.
     * Вопрос — одна строка, ответ — HTML из админки как есть.
     *
     * @param list<array<string,mixed>> $faqItems записи коллекции faq
     * @return list<array{question:string,answer:string}>
     */
    public static function faq(array $faqItems, ?int $limit = null, bool $onHomeOnly = false): array
    {
        $items = $onHomeOnly ? array_values(array_filter($faqItems, static fn (array $q): bool => !empty($q['onHome']))) : $faqItems;
        $items = self::byOrder($items);
        if ($limit !== null) {
            $items = array_slice($items, 0, $limit);
        }

        return array_map(
            static fn (array $q): array => ['question' => Fields::line($q['question'] ?? ''), 'answer' => is_string($q['answer'] ?? null) ? $q['answer'] : ''],
            $items
        );
    }

    /** Текстовый блок как строка без пробелов по краям; не строка → ''. @param array<string,mixed> $texts */
    public static function text(array $texts, string $key): string
    {
        return is_string($texts[$key] ?? null) ? trim($texts[$key]) : '';
    }

    /**
     * Блок-список (`type: list`) → непустые строки без пробелов по краям.
     *
     * @return list<string>
     */
    public static function lines(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $line): string => is_string($line) ? trim($line) : '',
            $value
        ), static fn (string $line): bool => $line !== ''));
    }

    /**
     * Строки «левое|правое» (распорядок «время|дело», цифры «значение|подпись») → пары; нет черты — правая часть пустая.
     *
     * @return list<array{0:string,1:string}>
     */
    public static function pairs(mixed $value): array
    {
        return array_map(static function (string $line): array {
            [$left, $right] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

            return [$left, $right];
        }, self::lines($value));
    }

    /** «Что входит в стоимость» (texts home.included). @param array<string,mixed> $texts @return list<string> */
    public static function included(array $texts): array
    {
        return self::lines($texts['home.included'] ?? []);
    }
}
