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

    public function handle(Request $r): Response
    {
        $this->currentPath = $r->path();
        $this->view = null; // глобальные переменные шаблонов — свежие на каждый запрос

        return $this->router->dispatch($r);
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
        if ($secret === '') {
            if (in_array($this->config['env'], ['staging', 'prod'], true)) {
                throw new RuntimeException('Не задан appSecret в config/local.php.');
            }
            $secret = 'dev-only-secret'; // локальная разработка без config/local.php
        }

        return new FormGuard(
            $secret,
            new RateLimiter($this->config['storageDir'] . '/ratelimit', $this->clock, $secret),
            $this->clock,
            (string) $this->config['baseUrl']
        );
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

    public function mailer(): Mailer
    {
        $mail = $this->config['mail'];
        $from = trim((string) ($mail['from'] ?? ''));
        if ($from === '') {
            $from = 'noreply@' . (string) parse_url((string) $this->config['baseUrl'], PHP_URL_HOST);
        }

        return new Mailer(
            $this->mailTransport(),
            trim((string) ($this->settings()['mailTo'] ?? '')),
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
            'year' => $this->clock->now()->format('Y'),
        ]);
    }
}
