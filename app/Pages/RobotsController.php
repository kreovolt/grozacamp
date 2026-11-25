<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;

/** /robots.txt: на боевом сайте открыт всё, кроме служебного; на тестовом и локальном — закрыт целиком. */
final class RobotsController
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params = []): Response
    {
        $config = $this->app->config();
        if ($config['env'] !== 'prod') {
            $body = "User-agent: *\nDisallow: /\n";
        } else {
            $body = "User-agent: *\n"
                . "Disallow: /admin/\n"
                . "Disallow: /form/\n"
                . "Disallow: /spasibo/\n"
                . "Clean-param: utm_source&utm_medium&utm_campaign&utm_content&utm_term&yclid&fbclid&from /\n"
                . "\nSitemap: " . rtrim((string) $config['baseUrl'], '/') . "/sitemap.xml\n";
        }

        return Response::html($body)->withHeader('Content-Type', 'text/plain; charset=utf-8');
    }
}
