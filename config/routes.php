<?php
declare(strict_types=1);

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Http\Router;

return static function (Router $router, App $app): void {
    $router->get('/', static fn (Request $r, array $params): Response => Response::html('ok'));
};
