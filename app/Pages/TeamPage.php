<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Content\PageData;
use Groza\Http\Request;
use Groza\Http\Response;

/**
 * «Команда» /team/ (спека §7.7): карточки по группам — руководство, педагоги, вожатые, остальные.
 * Внутри группы по полю order, при равенстве по имени. Скрытые не показываются.
 */
final class TeamPage
{
    /** group → заголовок. Порядок групп на странице — порядок здесь; неизвестная группа попадает в «other». */
    public const GROUPS = [
        'leadership' => 'Руководство',
        'pedagogue' => 'Педагоги',
        'counselor' => 'Вожатые',
        'other' => 'Ещё в команде',
    ];

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        return Response::html($this->app->view()->render('pages/team', [
            'groups' => self::grouped(PageData::items($this->app->store()->read('team'))),
        ]));
    }

    /**
     * @param list<array<string,mixed>> $members
     * @return list<array{title:string,members:list<array<string,mixed>>}> только непустые группы
     */
    public static function grouped(array $members): array
    {
        $buckets = array_fill_keys(array_keys(self::GROUPS), []);
        foreach ($members as $m) {
            if (!empty($m['hidden']) || trim((string) ($m['name'] ?? '')) === '') {
                continue;
            }
            $group = (string) ($m['group'] ?? '');
            $buckets[isset(self::GROUPS[$group]) ? $group : 'other'][] = $m;
        }

        $out = [];
        foreach ($buckets as $group => $list) {
            if ($list === []) {
                continue;
            }
            usort($list, static fn (array $a, array $b): int => ((int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0))
                ?: strcmp(mb_strtolower((string) $a['name']), mb_strtolower((string) $b['name'])));
            $out[] = ['title' => self::GROUPS[$group], 'members' => $list];
        }

        return $out;
    }
}
