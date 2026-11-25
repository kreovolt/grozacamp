<?php
declare(strict_types=1);

namespace Groza\Content;

/** Сотрудники из коллекции team: группировка для /team/ и выборки для раздела сведений (п. 5 и п. 6 приказа № 201). */
final class Staff
{
    /** group → заголовок. Порядок групп на странице — порядок здесь; неизвестная группа попадает в «other». */
    public const GROUPS = [
        'leadership' => 'Руководство',
        'pedagogue' => 'Педагоги',
        'counselor' => 'Вожатые',
        'other' => 'Ещё в команде',
    ];

    /** Группы педагогического и вожатского состава (п. 6 а). Руководство — п. 5, «другое» (фотографы, ди-джеи) — не педагогический состав. */
    public const PEDAGOGICAL = ['pedagogue', 'counselor'];

    /**
     * Нескрытые сотрудники с именем по группам; внутри группы по полю order, при равенстве по имени. Пустые группы не возвращаются.
     *
     * @param list<array<string,mixed>> $members
     * @return list<array{group:string,title:string,members:list<array<string,mixed>>}>
     */
    public static function grouped(array $members): array
    {
        $buckets = array_fill_keys(array_keys(self::GROUPS), []);
        foreach ($members as $m) {
            if (!empty($m['hidden']) || !is_string($m['name'] ?? null) || trim($m['name']) === '') {
                continue;
            }
            $group = is_string($m['group'] ?? null) ? $m['group'] : '';
            $buckets[isset(self::GROUPS[$group]) ? $group : 'other'][] = $m;
        }

        $out = [];
        foreach ($buckets as $group => $list) {
            if ($list === []) {
                continue;
            }
            usort($list, static fn (array $a, array $b): int => ((int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0))
                ?: strcmp(mb_strtolower((string) $a['name']), mb_strtolower((string) $b['name'])));
            $out[] = ['group' => $group, 'title' => self::GROUPS[$group], 'members' => $list];
        }

        return $out;
    }

    /** Все видимые сотрудники в порядке групп. @param list<array<string,mixed>> $members @return list<array<string,mixed>> */
    public static function all(array $members): array
    {
        return self::pick($members, array_keys(self::GROUPS));
    }

    /** Сотрудники указанных групп в порядке групп и order. @param list<array<string,mixed>> $members @param list<string> $groups @return list<array<string,mixed>> */
    public static function pick(array $members, array $groups): array
    {
        $out = [];
        foreach (self::grouped($members) as $g) {
            if (in_array($g['group'], $groups, true)) {
                array_push($out, ...$g['members']);
            }
        }

        return $out;
    }
}
