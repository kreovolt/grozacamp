<?php
declare(strict_types=1);

namespace Groza\Forms;

use Groza\Http\Request;
use Groza\Support\Clock;
use Groza\Support\RateLimiter;

/**
 * Защита публичных форм без сессий и капчи: поле-ловушка, подписанная метка времени показа формы
 * (_ts + _sig = HMAC-SHA256(секрет, formId|_ts)), проверка источника запроса и лимит частоты по IP.
 */
final class FormGuard
{
    public const HONEYPOT = 'website';
    public const MIN_SECONDS = 3;
    public const MAX_SECONDS = 7200;
    public const RATE_MAX = 5;
    public const RATE_WINDOW = 600;

    private string $baseHost;

    public function __construct(
        private string $secret,
        private RateLimiter $limiter,
        private Clock $clock,
        string $baseUrl,
    ) {
        if ($secret === '') {
            throw new \InvalidArgumentException('Пустой секрет приложения: защита форм не может работать.');
        }
        $this->baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
    }

    /** Скрытые поля формы: время показа и подпись. @return array{_ts:string,_sig:string} */
    public function fields(string $formId): array
    {
        $ts = (string) $this->clock->now()->getTimestamp();

        return ['_ts' => $ts, '_sig' => $this->sign($formId, $ts)];
    }

    /** null — запрос принят, иначе код отказа. Порядок проверок важен: лимит считает только прошедших остальное. */
    public function check(Request $r, string $formId): ?string
    {
        $trap = $r->post(self::HONEYPOT);
        if (is_array($trap) || trim((string) $trap) !== '') {
            return 'honeypot';
        }

        $ts = $r->post('_ts');
        $sig = $r->post('_sig');
        if (!is_string($ts) || !is_string($sig) || !preg_match('/^\d{1,12}$/', $ts)
            || !hash_equals($this->sign($formId, $ts), $sig)) {
            return 'bad_signature';
        }

        $age = $this->clock->now()->getTimestamp() - (int) $ts;
        if ($age < self::MIN_SECONDS) {
            return 'too_fast';
        }
        if ($age > self::MAX_SECONDS) {
            return 'expired';
        }

        if (!$this->originAllowed($r)) {
            return 'bad_origin';
        }

        if (!$this->limiter->hit('form:' . $formId, $r->ip(), self::RATE_MAX, self::RATE_WINDOW)) {
            return 'rate_limited';
        }

        return null;
    }

    private function sign(string $formId, string $ts): string
    {
        return hash_hmac('sha256', $formId . '|' . $ts, $this->secret);
    }

    /** Есть Origin — сверяем его; иначе Referer; нет обоих — пропускаем (часть клиентов их не шлёт). Сравнивается только хост. */
    private function originAllowed(Request $r): bool
    {
        foreach (['Origin', 'Referer'] as $name) {
            $value = $r->header($name);
            if ($value !== null && $value !== '') {
                $host = parse_url($value, PHP_URL_HOST);

                return is_string($host) && strtolower($host) === $this->baseHost;
            }
        }

        return true;
    }
}
