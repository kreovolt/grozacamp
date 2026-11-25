<?php
declare(strict_types=1);

namespace Groza;

use Groza\Content\Nav;
use Groza\Data\AuditLog;
use Groza\Data\JsonStore;
use Groza\Data\Seeder;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Forms\FormGuard;
use Groza\Http\Router;
use Groza\Http\SecurityHeaders;
use Groza\Mail\ArrayTransport;
use Groza\Mail\FileTransport;
use Groza\Mail\Mailer;
use Groza\Mail\MailTransport;
use Groza\Mail\SmtpTransport;
use Groza\Support\RateLimiter;
use Groza\Support\Clock;
use Groza\Support\FixedClock;
use Groza\Support\SystemClock;
use Groza\View\View;

final class App
{
    private const MIN_SECRET_BYTES = 32;
    private const EXAMPLE_SECRET = 'замените-на-длинную-случайную-строку';
    public const DEFAULT_MAIL_TO = '2474347@bk.ru';

    private Router $router;
    private ?JsonStore $store = null;
    private ?AuditLog $audit = null;
    private ?View $view = null;
    private ?MailTransport $mailTransport = null;
    private string $currentPath = '/';

    /** @param array<string,mixed> $config */
    private function __construct(private array $config, private Clock $clock)
    {
        $this->router = new Router();
        $redirects = $this->redirects();
        $this->router->redirects($redirects['map']);
        $this->router->gone($redirects['gone']);
        $register = require $config['rootDir'] . '/config/routes.php';
        $register($this->router, $this);
    }

    /** @param array<string,mixed> $config */
    public static function boot(array $config): App
    {
        date_default_timezone_set($config['timezone']);
        $clock = ($config['env'] === 'test' && !empty($config['fakeNow']))
            ? new FixedClock($config['fakeNow'], $config['timezone'])
            : new SystemClock($config['timezone']);

        $app = new App($config, $clock);
        $app->seedMissing();

        return $app;
    }

    /** На хостинге без SSH стартовые данные появляются при первом запросе (seed/data, без перезаписи). */
    private function seedMissing(): void
    {
        $seedDir = $this->config['rootDir'] . '/seed/data';
        if (!is_dir($seedDir)) {
            return;
        }
        try {
            Seeder::seedMissing($this->store(), $seedDir);
        } catch (\Exception $e) {
            // сайт продолжит работать на умолчаниях; причину видно в журнале ошибок PHP
            error_log('Groza seed: ' . $e->getMessage());
        }
    }

    /** Каждый ответ — страница, ошибка или переадресация — получает заголовки безопасности. */
    public function handle(Request $r): Response
    {
        $this->currentPath = $r->path();
        $this->view = null; // глобальные переменные шаблонов — свежие на каждый запрос

        $response = $this->canonicalHost($r) ?? $this->stagingGate($r) ?? $this->router->dispatch($r);
        if (str_starts_with($r->path(), '/admin/')) {
            $response = $response->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        return SecurityHeaders::apply($response, ['env' => $this->config['env'], 'metrikaId' => $this->metrikaId()]);
    }

    /** Адрес с www. ведёт на основной (baseUrl) с тем же путём и строкой запроса. */
    private function canonicalHost(Request $r): ?Response
    {
        $base = (string) $this->config['baseUrl'];
        $parts = parse_url($base);
        $host = strtolower((string) ($parts['host'] ?? ''));
        if ($host === '' || $r->host() === '') {
            return null;
        }
        $canonical = $host . (isset($parts['port']) ? ':' . $parts['port'] : '');
        if ($r->host() !== 'www.' . $canonical) {
            return null;
        }
        $path = implode('/', array_map('rawurlencode', explode('/', $r->path())));
        $query = $r->queryString();

        return Response::redirect(rtrim($base, '/') . $path . ($query !== '' ? '?' . $query : ''), 301);
    }

    /** Тестовый адрес закрыт паролем. Логин и пароль не заданы — закрыт для всех. */
    private function stagingGate(Request $r): ?Response
    {
        if ($this->config['env'] !== 'staging') {
            return null;
        }
        $user = (string) ($this->config['staging']['user'] ?? '');
        $pass = (string) ($this->config['staging']['pass'] ?? '');
        $header = (string) $r->header('Authorization');
        if ($user !== '' && $pass !== '' && stripos($header, 'Basic ') === 0) {
            $given = explode(':', (string) base64_decode(substr($header, 6), true), 2);
            if (count($given) === 2 && hash_equals($user, $given[0]) && hash_equals($pass, $given[1])) {
                return null;
            }
        }

        return Response::html('Требуется авторизация', 401)->withHeader('WWW-Authenticate', 'Basic realm="Groza staging", charset="UTF-8"');
    }

    /**
     * Переадресации и «несуществующие» адреса старого сайта (config/redirects.php) — единый загрузчик.
     *
     * @return array{map:array<string,string>, legacyShiftSlugs:list<string>, gone:list<string>}
     */
    public function redirects(): array
    {
        $cfg = (array) require $this->config['rootDir'] . '/config/redirects.php';

        return [
            'map' => (array) ($cfg['map'] ?? []),
            'legacyShiftSlugs' => array_values((array) ($cfg['legacyShiftSlugs'] ?? [])),
            'gone' => array_values((array) ($cfg['gone'] ?? [])),
        ];
    }

    /** Номер счётчика Метрики: настройка из админки, иначе config; только цифры (идёт в разметку и CSP). Пусто — Метрика выключена. */
    public function metrikaId(): string
    {
        $id = trim((string) ($this->settings()['metrikaId'] ?? '')) ?: trim((string) $this->config['metrikaId']);

        return preg_match('/^[0-9]{1,12}$/', $id) === 1 ? $id : '';
    }

    /** @return array<string,mixed> */
    public function config(): array
    {
        return $this->config;
    }

    public function clock(): Clock
    {
        return $this->clock;
    }

    public function store(): JsonStore
    {
        $dir = $this->config['storageDir'];

        return $this->store ??= new JsonStore($dir . '/data', $dir . '/versions');
    }

    public function audit(): AuditLog
    {
        return $this->audit ??= new AuditLog($this->config['storageDir'] . '/logs/audit.jsonl');
    }

    /** Контакты и общие настройки: умолчания из config/settings.defaults.php, поверх — storage/data/settings.json. @return array<string,mixed> */
    public function settings(): array
    {
        $defaults = require $this->config['rootDir'] . '/config/settings.defaults.php';

        return array_replace($defaults, $this->store()->read('settings'));
    }

    /** Защита публичных форм. Без секрета приложения форма не работает вовсе (на проде это ошибка настройки). */
    public function guard(): FormGuard
    {
        $secret = (string) $this->config['appSecret'];
        if (in_array($this->config['env'], ['staging', 'prod'], true)) {
            // пустой, взятый из config/local.example.php или короткий секрет позволяет подделать подпись формы
            if (strlen($secret) < self::MIN_SECRET_BYTES || $secret === self::EXAMPLE_SECRET) {
                throw new \RuntimeException('appSecret в config/local.php не задан или слишком слаб (нужно от 32 байт, не из примера).');
            }
        } elseif ($secret === '') {
            $secret = 'dev-only-secret'; // локальная разработка без config/local.php
        }

        return new FormGuard(
            $secret,
            new RateLimiter($this->config['storageDir'] . '/ratelimit', $this->clock, $secret),
            $this->clock,
            (string) $this->config['baseUrl']
        );
    }

    /** Скрытые поля формы для шаблона; null — защита не настроена (форму заменяет блок «позвоните нам»). @return array{_ts:string,_sig:string}|null */
    public function guardFields(string $formId): ?array
    {
        try {
            return $this->guard()->fields($formId);
        } catch (\RuntimeException) {
            return null;
        }
    }

    /** Транспорт писем: test — в памяти, dev — файлы .eml в storage/mail, иначе SMTP. */
    public function mailTransport(): MailTransport
    {
        return $this->mailTransport ??= match ($this->config['env']) {
            'test' => new ArrayTransport(),
            'dev' => new FileTransport($this->config['storageDir'] . '/mail'),
            default => new SmtpTransport($this->config['mail']),
        };
    }

    /** Для тестов: подменить транспорт (например, на падающий). */
    public function setMailTransport(MailTransport $transport): void
    {
        $this->mailTransport = $transport;
    }

    /** Получатель заявок: настройка mailTo, при пустой или неверной — адрес из Global Constraints. */
    private function mailTo(): string
    {
        $to = trim((string) ($this->settings()['mailTo'] ?? ''));

        return filter_var($to, FILTER_VALIDATE_EMAIL) !== false ? $to : self::DEFAULT_MAIL_TO;
    }

    public function mailer(): Mailer
    {
        $mail = $this->config['mail'];
        $from = trim((string) ($mail['from'] ?? ''));
        if ($from === '') {
            $from = 'noreply@' . (string) parse_url((string) $this->config['baseUrl'], PHP_URL_HOST);
        }

        return new Mailer(
            $this->mailTransport(),
            $this->mailTo(),
            $from,
            (string) ($mail['fromName'] ?? ''),
            $this->clock
        );
    }

    public function view(): View
    {
        return $this->view ??= new View($this->config['rootDir'] . '/templates', [
            'settings' => $this->settings(),
            'nav' => Nav::main(),
            'currentPath' => $this->currentPath,
            'env' => $this->config['env'],
            'baseUrl' => rtrim((string) $this->config['baseUrl'], '/'),
            'metrikaId' => $this->metrikaId(),
            'year' => $this->clock->now()->format('Y'),
        ]);
    }
}
