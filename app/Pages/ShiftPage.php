<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;

/**
 * Страница смены /program/{slug}/ (спека §7.3).
 * Неизвестный адрес: старые адреса карточек (config/redirects.php) → 301 на /booking/, остальные → 404.
 * Прошедшая смена (последний день раньше сегодняшнего по часам приложения) — «Смена завершена», без индексации.
 */
final class ShiftPage
{
    public const FAQ_LIMIT = 4;

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        $slug = (string) ($params['slug'] ?? '');
        $shift = null;
        foreach ($this->items('shifts') as $s) {
            if (($s['slug'] ?? null) === $slug && empty($s['hidden'])) {
                $shift = $s;
                break;
            }
        }

        if ($shift === null) {
            $redirects = require $this->app->config()['rootDir'] . '/config/redirects.php';
            if (in_array($slug, $redirects['legacyShiftSlugs'] ?? [], true)) {
                return Response::redirect('/booking/', 301);
            }

            return Response::html('Not Found', 404);
        }

        $venue = null;
        foreach ($this->items('venues') as $v) {
            if (($v['slug'] ?? null) === ($shift['venue'] ?? '')) {
                $venue = $v;
            }
        }
        $texts = $this->app->store()->read('texts');
        $included = $texts['home.included'] ?? [];
        $today = $this->app->clock()->now()->format('Y-m-d');

        return Response::html($this->app->view()->render('pages/shift', [
            'shift' => $shift,
            'venue' => $venue,
            'past' => $today > (string) ($shift['dateEnd'] ?? ''),
            'included' => is_array($included) ? array_values(array_filter($included, 'is_string')) : [],
            'faq' => $this->faq(),
        ]));
    }

    /** @return list<array{question:string,answer:string}> */
    private function faq(): array
    {
        $items = array_values(array_filter($this->items('faq'), static fn (array $q): bool => !empty($q['onHome'])));
        usort($items, static fn (array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));

        return array_map(
            static fn (array $q): array => ['question' => trim((string) ($q['question'] ?? '')), 'answer' => (string) ($q['answer'] ?? '')],
            array_slice($items, 0, self::FAQ_LIMIT)
        );
    }

    /** @return list<array<string,mixed>> */
    private function items(string $collection): array
    {
        $items = $this->app->store()->read($collection)['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }
}
