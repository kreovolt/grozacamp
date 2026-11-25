<?php
declare(strict_types=1);

namespace Groza\Pages;

use DateTimeImmutable;
use Groza\App;
use Groza\Content\PageData;
use Groza\Http\Request;
use Groza\Http\Response;
use Groza\Seo\Sitemap;

/** /sitemap.xml: страницы из config/seo.php и видимые смены; дата изменения — по файлам данных. */
final class SitemapController
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params = []): Response
    {
        $store = $this->app->store();
        $seo = require $this->app->config()['rootDir'] . '/config/seo.php';

        $pages = [];
        foreach ($seo['sitemap'] as $page) {
            $latest = null;
            foreach ($page['sources'] as $source) {
                $at = $store->updatedAt($source);
                if ($at !== null && ($latest === null || $at > $latest)) {
                    $latest = $at;
                }
            }
            $pages[] = ['path' => $page['path'], 'priority' => $page['priority'], 'lastmod' => $latest];
        }

        $xml = Sitemap::build([
            'pages' => $pages,
            'shifts' => PageData::items($store->read('shifts')),
            'shiftsLastmod' => $store->updatedAt('shifts'),
        ], (string) $this->app->config()['baseUrl'], $this->app->clock()->now());

        return Response::html($xml)->withHeader('Content-Type', 'application/xml; charset=utf-8');
    }
}
