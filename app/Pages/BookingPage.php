<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Domain\Shifts;
use Groza\Forms\Fields;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Dates;

/**
 * «Купить путёвку» (спека §7.2): смены, форма заявки, кнопки «Позвонить» и WhatsApp, что входит в стоимость.
 * Сюда же возвращаются ошибки проверки формы (FormController) — с введёнными значениями и кодом 422.
 */
final class BookingPage
{
    public const FAQ_LIMIT = 4;

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        return $this->render($r);
    }

    /**
     * @param array<string,string> $values   введённое посетителем (повторный показ формы)
     * @param array<string,string> $errors
     * @param array{_ts:string,_sig:string}|null|false $guard  false — выдать свежие скрытые поля
     */
    public function render(Request $r, array $values = [], array $errors = [], int $status = 200, array|null|false $guard = false): Response
    {
        $items = $this->items('shifts');
        $shifts = Shifts::visible($items, $this->app->clock()->now());
        $options = ['shifts' => self::shiftOptions($shifts)];

        if (!array_key_exists('shift', $values)) {
            $wanted = $r->query('smena');
            $slugs = array_column($options['shifts'], 'slug');
            if (is_string($wanted) && in_array($wanted, array_merge($slugs, ['any']), true)) {
                $values['shift'] = $wanted;
            }
        }

        $venues = [];
        foreach ($this->items('venues') as $venue) {
            $venues[(string) ($venue['slug'] ?? '')] = $venue;
        }
        $texts = $this->app->store()->read('texts');
        $included = $texts['home.included'] ?? [];

        return Response::html($this->app->view()->render('pages/booking', [
            'shifts' => $shifts,
            'venues' => $venues,
            'values' => $values,
            'errors' => $errors,
            'options' => $options,
            'guard' => $guard === false ? $this->app->guardFields('booking') : $guard,
            'included' => is_array($included) ? array_values(array_filter($included, 'is_string')) : [],
            'faq' => $this->faq(),
            'hasErrors' => $errors !== [],
        ]), $status);
    }

    /**
     * Смены для выпадающего списка: видимые и с открытой записью (open | few).
     *
     * @param list<array<string,mixed>> $visible результат Shifts::visible
     * @return list<array{slug:string,label:string}>
     */
    public static function shiftOptions(array $visible): array
    {
        $out = [];
        foreach ($visible as $s) {
            if (in_array($s['status'] ?? '', ['open', 'few'], true) && !empty($s['slug'])) {
                $out[] = [
                    'slug' => (string) $s['slug'],
                    'label' => trim((string) ($s['title'] ?? '') . ', ' . Dates::range((string) ($s['dateStart'] ?? ''), (string) ($s['dateEnd'] ?? '')), ', '),
                ];
            }
        }

        return $out;
    }

    /** @return list<array{question:string,answer:string}> */
    private function faq(): array
    {
        $items = array_values(array_filter($this->items('faq'), static fn (array $q): bool => !empty($q['onHome'])));
        usort($items, static fn (array $a, array $b): int => (int) ($a['order'] ?? 0) <=> (int) ($b['order'] ?? 0));

        return array_map(
            static fn (array $q): array => ['question' => Fields::line($q['question'] ?? ''), 'answer' => (string) ($q['answer'] ?? '')],
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
