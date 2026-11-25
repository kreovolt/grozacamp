<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Domain\Shifts;
use Groza\Forms\BookingForm;
use Groza\Forms\Fields;
use Groza\Forms\FormGuard;
use Groza\Forms\ValidationResult;
use Groza\Forms\VacancyForm;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Support\Dates;

/**
 * Приём публичных форм: POST /form/booking/ и /form/vacancy/, страница /spasibo/.
 * Порядок: защита → проверка полей → письмо. Без JS — редирект на /spasibo/ или страница с ошибками;
 * с JS (Accept: application/json) — JSON. В журнал пишутся только форма и код исхода.
 */
final class FormController
{
    private const FIELDS = [
        'booking' => ['shift', 'parentName', 'phone', 'childAge', 'comment', 'consent'],
        'vacancy' => ['fullName', 'phone', 'email', 'age', 'position', 'experience', 'shifts', 'vk', 'consent'],
    ];

    private const TITLES = [
        'booking' => 'Заявка на путёвку',
        'vacancy' => 'Анкета на вакансию',
    ];

    private const RETRY = [
        'too_fast' => 'Форма отправилась слишком быстро. Нажмите «Отправить» ещё раз.',
        'expired' => 'Страница устарела. Проверьте данные и нажмите «Отправить» ещё раз.',
    ];

    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function booking(Request $r, array $params): Response
    {
        return $this->submit($r, 'booking');
    }

    /** @param array<string,string> $params */
    public function vacancy(Request $r, array $params): Response
    {
        return $this->submit($r, 'vacancy');
    }

    /** @param array<string,string> $params */
    public function thanks(Request $r, array $params): Response
    {
        $form = $r->query('f') === 'vacancy' ? 'vacancy' : 'booking';

        return Response::html($this->app->view()->render('pages/thanks', ['form' => $form]));
    }

    /** Временная страница с формой (dev/test), пока нет /booking/ и /vacancies/ (Task 9, 10). */
    public function preview(string $formId): Response
    {
        return $this->page($formId, [], [], 200, $this->app->guardFields($formId));
    }

    private function submit(Request $r, string $formId): Response
    {
        try {
            $code = $this->app->guard()->check($r, $formId);
        } catch (\RuntimeException) {
            $this->log($formId, 'misconfigured');

            return $this->fallback($r, 503);
        }

        if ($code === 'honeypot') {
            $this->log($formId, 'honeypot');

            return $this->success($r, $formId); // бот не должен узнать, что пойман
        }
        if (isset(self::RETRY[$code ?? ''])) {
            $this->log($formId, $code);

            return $this->invalid($r, $formId, $this->values($r, $formId), ['_form' => self::RETRY[$code]], $code);
        }
        if ($code === 'rate_limited') {
            $this->log($formId, $code);

            return $this->fallback($r, 429)->withHeader('Retry-After', '600');
        }
        if ($code !== null) {
            $this->log($formId, $code);

            return $this->fallback($r, 403);
        }

        $result = $this->validate($formId, $r);
        if (!$result->ok()) {
            $this->log($formId, 'invalid');

            return $this->invalid($r, $formId, $this->values($r, $formId), $result->errors, null);
        }

        try {
            $mailer = $this->app->mailer();
            if ($formId === 'booking') {
                $mailer->bookingRequest($result->data, $this->shift((string) $result->data['shift']));
            } else {
                $mailer->vacancyApplication($result->data);
            }
        } catch (\Throwable) {
            // текст ошибки может содержать данные заявки — наружу и в журнал не идёт
            $this->log($formId, 'mail_failed');

            return $this->fallback($r, 503);
        }

        $this->log($formId, 'sent');

        return $this->success($r, $formId);
    }

    private function validate(string $formId, Request $r): ValidationResult
    {
        $post = $r->allPost();
        if ($formId === 'booking') {
            return BookingForm::validate($post, array_map(static fn (array $o): string => $o['slug'], $this->options('booking')['shifts']));
        }

        return VacancyForm::validate($post, $this->options('vacancy')['positions']);
    }

    /** @return array<string,mixed> */
    private function options(string $formId): array
    {
        if ($formId === 'vacancy') {
            $positions = [];
            foreach ($this->items('vacancies') as $v) {
                $title = Fields::line($v['title'] ?? '');
                if (empty($v['hidden']) && $title !== '') {
                    $positions[] = $title;
                }
            }
            $positions[] = VacancyForm::OTHER;

            return ['positions' => array_values(array_unique($positions))];
        }

        $shifts = [];
        foreach (Shifts::visible($this->items('shifts'), $this->app->clock()->now()) as $s) {
            if (in_array($s['status'] ?? '', ['open', 'few'], true) && !empty($s['slug'])) {
                $shifts[] = [
                    'slug' => (string) $s['slug'],
                    'label' => trim((string) ($s['title'] ?? '') . ', ' . Dates::range((string) ($s['dateStart'] ?? ''), (string) ($s['dateEnd'] ?? '')), ', '),
                ];
            }
        }

        return ['shifts' => $shifts];
    }

    /** @return array<string,mixed>|null */
    private function shift(string $slug): ?array
    {
        foreach ($this->items('shifts') as $s) {
            if (($s['slug'] ?? null) === $slug) {
                return $s;
            }
        }

        return null; // «any»
    }

    /** @return list<array<string,mixed>> */
    private function items(string $collection): array
    {
        $items = $this->app->store()->read($collection)['items'] ?? [];

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /** Введённое посетителем — для повторного показа формы (только известные поля, длина ограничена). @return array<string,string> */
    private function values(Request $r, string $formId): array
    {
        $out = [];
        foreach (self::FIELDS[$formId] as $name) {
            $out[$name] = mb_substr(Fields::text($r->post($name, '')), 0, 2100);
        }

        return $out;
    }

    private function success(Request $r, string $formId): Response
    {
        return $r->wantsJson()
            ? Response::json(['ok' => true])
            : Response::redirect('/spasibo/?f=' . $formId, 303);
    }

    /**
     * Принятая метка времени остаётся: иначе после каждой ошибки заново ждать 3 секунды.
     * Новая выдаётся только если прежняя устарела (или скоро устареет).
     *
     * @return array{_ts:string,_sig:string}|null
     */
    private function guardAfterError(Request $r, string $formId, ?string $code): ?array
    {
        $ts = $r->post('_ts');
        $sig = $r->post('_sig');
        if ($code !== 'expired' && is_string($ts) && is_string($sig) && ctype_digit($ts)
            && $this->app->clock()->now()->getTimestamp() - (int) $ts < FormGuard::MAX_SECONDS - 600) {
            return ['_ts' => $ts, '_sig' => $sig];
        }

        return $this->app->guardFields($formId);
    }

    /** @param array<string,string> $values @param array<string,string> $errors */
    private function invalid(Request $r, string $formId, array $values, array $errors, ?string $code): Response
    {
        $guard = $this->guardAfterError($r, $formId, $code);
        if ($r->wantsJson()) {
            return Response::json(['ok' => false, 'errors' => $errors, 'guard' => $guard], 422);
        }

        return $this->page($formId, $values, $errors, 422, $guard);
    }

    /** @param array<string,string> $values @param array<string,string> $errors */
    private function page(string $formId, array $values, array $errors, int $status, ?array $guard): Response
    {
        $html = $this->app->view()->render('pages/form-page', [
            'formId' => $formId,
            'title' => self::TITLES[$formId],
            'values' => $values,
            'errors' => $errors,
            'options' => $this->options($formId),
            'guard' => $guard,
        ]);

        return Response::html($html, $status);
    }

    /** Письмо не ушло или запрос отклонён: человеку — телефон и WhatsApp, а не «ошибка 500». */
    private function fallback(Request $r, int $status): Response
    {
        if ($r->wantsJson()) {
            return Response::json(['ok' => false, 'fallback' => true], $status);
        }

        return Response::html($this->app->view()->render('pages/form-error', ['status' => $status]), $status);
    }

    private function log(string $formId, string $outcome): void
    {
        $dir = $this->app->config()['storageDir'] . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = $this->app->clock()->now()->format(DATE_ATOM) . ' ' . $formId . ' ' . $outcome . "\n";
        @file_put_contents($dir . '/forms.log', $line, FILE_APPEND | LOCK_EX);
    }
}
