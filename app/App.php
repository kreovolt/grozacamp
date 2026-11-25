<?php
declare(strict_types=1);

namespace Groza;

use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Http\Router;
use Groza\Support\Clock;
use Groza\Support\FixedClock;
use Groza\Support\SystemClock;

final class App
{
    private Router $router;

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
}
