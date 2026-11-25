<?php
declare(strict_types=1);

namespace Groza;

use Groza\Content\Nav;
use Groza\Data\AuditLog;
use Groza\Data\JsonStore;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Http\Router;
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

        return new App($config, $clock);
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
