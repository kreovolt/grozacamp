<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Domain\Shifts;
use Groza\Http\Request;
use Groza\Http\Response;

/**
 * Главная (спека §7.1): собирает данные двенадцати блоков из storage/data и отдаёт templates/pages/home.php.
 * Тексты героя, полоса цифр, «что входит» и распорядок — из texts (правятся в админке);
 * смены, базы, команда, вопросы и отзывы — из своих коллекций.
 */
final class HomePage
{
    public const TEAM_LIMIT = 6;
    public const FAQ_LIMIT = 5;
    public const REVIEWS_LIMIT = 5;

    private const DEFAULT_TITLE = 'Лагерь настоящих приключений';
    private const REGION = 'Пермский край';
    /** Фото героя (Global Constraints: shoot-group-splash → ключ hero-river в манифесте). */
    private const HERO_PHOTO = 'hero-river';

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        $store = $this->app->store();
        $texts = $store->read('texts');
        $settings = $this->app->settings();

        $allShifts = $this->items('shifts');
        $shifts = Shifts::visible($allShifts, $this->app->clock()->now());
        $venues = [];
        foreach ($this->items('venues') as $venue) {
            $venues[(string) ($venue['slug'] ?? '')] = $venue;
        }

        return Response::html($this->app->view()->render('pages/home', [
            'hero' => [
                'kicker' => $this->kicker($texts, $allShifts),
                'title' => $this->text($texts, 'home.heroTitle') ?: self::DEFAULT_TITLE,
                'lead' => $this->text($texts, 'home.heroLead'),
                'photo' => self::HERO_PHOTO,
            ],
            'stats' => $this->list($texts, 'home.stats'),
            'shifts' => $shifts,
            'venues' => $venues,
            'bases' => $this->bases($shifts, $venues),
            'included' => $this->list($texts, 'home.included'),
            'schedule' => $this->pairs($this->list($texts, 'home.schedule')),
            'team' => $this->team(),
            'faq' => $this->faq(),
            'reviews' => array_slice(array_values(array_filter(
                $this->items('reviews'),
                static fn (array $item): bool => !empty($item['visible']) && trim((string) ($item['text'] ?? '')) !== ''
            )), 0, self::REVIEWS_LIMIT),
            'reviewsUrl' => (string) (($settings['vkReviewsUrl'] ?? '') ?: ($settings['vk'] ?? '')),
        ]));
    }

    /** Ссылка «Забронировать» с карточки: форма заявки с уже выбранной сменой (Task 9). */
    public static function bookingHref(array $shift): string
    {
        return '/booking/?smena=' . rawurlencode((string) ($shift['slug'] ?? '')) . '#zayavka';
    }

    /**
     * Строка над заголовком: своя из админки, иначе сезон ближайшей видимой смены,
     * иначе нейтральная — сезонные слова не вписаны в шаблон (спека §8.1).
     *
     * @param array<string,mixed> $texts
     * @param list<array<string,mixed>> $shifts
     */
    private function kicker(array $texts, array $shifts): string
    {
        $own = $this->text($texts, 'home.heroKicker');
        if ($own !== '') {
            return $own;
        }
        $season = Shifts::nearestSeason($shifts, $this->app->clock()->now());

        return $season !== null ? 'Смены: ' . $season . ' · ' . self::REGION : 'Детский лагерь · ' . self::REGION;
    }

    /**
     * Базы, на которых идут видимые смены (в порядке смен), с названиями этих смен.
     *
     * @param list<array<string,mixed>> $shifts
     * @param array<string,array<string,mixed>> $venues
     * @return list<array{venue:array<string,mixed>, shifts:list<string>}>
     */
    private function bases(array $shifts, array $venues): array
    {
        $out = [];
        foreach ($shifts as $shift) {
            $slug = (string) ($shift['venue'] ?? '');
            if (!isset($venues[$slug])) {
                continue;
            }
            $out[$slug] ??= ['venue' => $venues[$slug], 'shifts' => []];
            $out[$slug]['shifts'][] = (string) $shift['title'];
        }

        return array_values($out);
    }

    /** @return list<array<string,mixed>> отмеченные «на главную», не скрытые, по полю order, не больше шести */
    private function team(): array
    {
        $items = array_values(array_filter(
            $this->items('team'),
            static fn (array $m): bool => !empty($m['onHome']) && empty($m['hidden'])
        ));

        return array_slice($this->byOrder($items), 0, self::TEAM_LIMIT);
    }

    /** @return list<array<string,mixed>> отмеченные «на главную», по полю order, не больше пяти */
    private function faq(): array
    {
        $items = array_values(array_filter($this->items('faq'), static fn (array $q): bool => !empty($q['onHome'])));

        return array_slice($this->byOrder($items), 0, self::FAQ_LIMIT);
    }

    /**
     * @param list<array<string,mixed>> $items
     * @return list<array<string,mixed>>
     */
    private function byOrder(array $items): array
    {
        usort($items, static fn (array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));

        return $items;
    }

    /** @return list<array<string,mixed>> */
    private function items(string $collection): array
    {
        $items = $this->app->store()->read($collection)['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** @param array<string,mixed> $texts */
    private function text(array $texts, string $key): string
    {
        return is_string($texts[$key] ?? null) ? trim($texts[$key]) : '';
    }

    /**
     * @param array<string,mixed> $texts
     * @return list<string> непустые строки списка
     */
    private function list(array $texts, string $key): array
    {
        $value = $texts[$key] ?? [];
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $line): string => is_string($line) ? trim($line) : '',
            $value
        ), static fn (string $line): bool => $line !== ''));
    }

    /**
     * Строки «левое|правое» (распорядок: «время|дело»).
     *
     * @param list<string> $lines
     * @return list<array{0:string,1:string}>
     */
    private function pairs(array $lines): array
    {
        return array_map(static function (string $line): array {
            [$left, $right] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');

            return [$left, $right];
        }, $lines);
    }
}
