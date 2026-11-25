<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Content\PageData;
use Groza\Forms\Fields;
use Groza\Forms\VacancyForm;
use Groza\Http\Request;
use Groza\Http\Response;

/**
 * «Вакансии» /vacancies/ (спека §7.5): открытые вакансии из админки, что даём, как проходит отбор и анкета.
 * Сюда же FormController возвращает ошибки проверки анкеты — с введёнными значениями и кодом 422.
 */
final class VacanciesPage
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        return $this->render($r);
    }

    /**
     * @param array<string,string> $values   введённое посетителем (повторный показ анкеты)
     * @param array<string,string> $errors
     * @param array{_ts:string,_sig:string}|null|false $guard  false — выдать свежие скрытые поля
     */
    public function render(Request $r, array $values = [], array $errors = [], int $status = 200, array|null|false $guard = false): Response
    {
        $store = $this->app->store();
        $texts = $store->read('texts');
        $vacancies = self::visible(PageData::items($store->read('vacancies')));
        $positions = self::positions($vacancies);

        if (!array_key_exists('position', $values)) {
            $wanted = $r->query('position');
            if (is_string($wanted) && in_array($wanted, $positions, true)) {
                $values['position'] = $wanted;
            }
        }

        return Response::html($this->app->view()->render('pages/vacancies', [
            'vacancies' => $vacancies,
            'intro' => PageData::text($texts, 'vacancies.intro'),
            'benefits' => PageData::text($texts, 'vacancies.benefits'),
            'selection' => PageData::text($texts, 'vacancies.selection'),
            'values' => $values,
            'errors' => $errors,
            'options' => ['positions' => $positions],
            'guard' => $guard === false ? $this->app->guardFields('vacancy') : $guard,
        ]), $status);
    }

    /** Вакансии без скрытых и без названия, по полю order. @param list<array<string,mixed>> $items @return list<array<string,mixed>> */
    public static function visible(array $items): array
    {
        return PageData::byOrder(array_values(array_filter(
            $items,
            static fn (array $v): bool => empty($v['hidden']) && Fields::line($v['title'] ?? '') !== ''
        )));
    }

    /**
     * Должности для списка анкеты: названия видимых вакансий и всегда «Другое». Те же значения принимает проверка формы.
     *
     * @param list<array<string,mixed>> $vacancies записи коллекции (скрытые отбрасываются)
     * @return list<string>
     */
    public static function positions(array $vacancies): array
    {
        $out = array_map(static fn (array $v): string => Fields::line($v['title'] ?? ''), self::visible($vacancies));
        $out[] = VacancyForm::OTHER;

        return array_values(array_unique($out));
    }
}
