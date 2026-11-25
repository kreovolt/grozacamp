<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Content\PageData;
use Groza\Domain\Collections;
use Groza\Domain\Shifts;
use Groza\Http\Request;
use Groza\Http\Response;

use function Groza\View\safe_url;

/**
 * Главная (спека §7.1): собирает данные двенадцати блоков из storage/data и отдаёт templates/pages/home.php.
 * Тексты героя, полоса цифр, «что входит» и распорядок — из texts (правятся в админке);
 * смены, базы, команда, вопросы и отзывы — из своих коллекций.
 */
final class HomePage
{
    public const TEAM_LIMIT = 6;
    public const FAQ_LIMIT = Collections::FAQ_HOME_MAX;
    public const REVIEWS_LIMIT = 5;

    private const DEFAULT_TITLE = 'Лагерь настоящих приключений';
    private const REGION = 'Пермский край';
    /** Фото героя: селфи вожатого с ребятами из папки заказчика (решение пользователя, photo-selection.md). */
    private const HERO_PHOTO = 'friends-selfie';

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
                'title' => PageData::text($texts, 'home.heroTitle') ?: self::DEFAULT_TITLE,
                'lead' => PageData::text($texts, 'home.heroLead'),
                'photo' => self::HERO_PHOTO,
            ],
            'stats' => PageData::lines($texts['home.stats'] ?? []),
            'shifts' => $shifts,
            'venues' => $venues,
            'bases' => $this->bases($shifts, $venues),
            'included' => PageData::included($texts),
            'schedule' => PageData::pairs($texts['home.schedule'] ?? []),
            'team' => $this->team(),
            'faq' => PageData::faq($this->items('faq'), self::FAQ_LIMIT, true),
            'reviews' => array_slice(array_values(array_filter(
                $this->items('reviews'),
                static fn (array $item): bool => !empty($item['visible']) && trim((string) ($item['text'] ?? '')) !== ''
            )), 0, self::REVIEWS_LIMIT),
            'reviewsIsThread' => safe_url((string) ($settings['vkReviewsUrl'] ?? '')) !== '',
            'reviewsUrl' => safe_url((string) (($settings['vkReviewsUrl'] ?? '') ?: ($settings['vk'] ?? ''))) ?: safe_url((string) ($settings['vk'] ?? '')),
        ]));
    }

    /**
     * Ссылка с карточки и страницы смены на форму заявки. У открытой смены форма сразу выбирает её;
     * у смены «скоро» её нет в списке формы, поэтому без выбора.
     *
     * @param array<string,mixed> $shift
     */
    public static function bookingHref(array $shift): string
    {
        if (($shift['status'] ?? '') === 'soon') {
            return '/booking/#zayavka';
        }

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
        $own = PageData::text($texts, 'home.heroKicker');
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
            $out[$slug]['shifts'][] = (string) ($shift['title'] ?? '');
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

        return array_slice(PageData::byOrder($items), 0, self::TEAM_LIMIT);
    }

    /** @return list<array<string,mixed>> */
    private function items(string $collection): array
    {
        return PageData::items($this->app->store()->read($collection));
    }
}
