<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Content\PageData;
use Groza\Http\Request;
use Groza\Http\Response;

/**
 * Страница смены /program/{slug}/ (спека §7.3).
 * Неизвестный адрес: прежний адрес смены (её адрес поменяли в админке — formerSlugs) → 301 на нынешний;
 * старые адреса карточек (config/redirects.php) → 301 на /booking/, остальные → 404.
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
            foreach ($this->items('shifts') as $s) {
                if (empty($s['hidden']) && is_string($s['slug'] ?? null) && $s['slug'] !== ''
                    && in_array($slug, (array) ($s['formerSlugs'] ?? []), true)) {
                    return Response::redirect('/program/' . rawurlencode($s['slug']) . '/', 301);
                }
            }
            if (in_array($slug, $this->app->redirects()['legacyShiftSlugs'], true)) {
                return Response::redirect('/booking/', 301);
            }

            return (new NotFoundPage($this->app))($r, []);
        }

        $venue = null;
        foreach ($this->items('venues') as $v) {
            if (($v['slug'] ?? null) === ($shift['venue'] ?? '')) {
                $venue = $v;
            }
        }
        $texts = $this->app->store()->read('texts');
        $today = $this->app->clock()->now()->format('Y-m-d');

        return Response::html($this->app->view()->render('pages/shift', [
            'shift' => $shift,
            'venue' => $venue,
            'past' => $today > (string) ($shift['dateEnd'] ?? ''),
            'included' => PageData::included($texts),
            'faq' => PageData::faq($this->items('faq'), self::FAQ_LIMIT, true),
        ]));
    }

    /** @return list<array<string,mixed>> */
    private function items(string $collection): array
    {
        return PageData::items($this->app->store()->read($collection));
    }
}
