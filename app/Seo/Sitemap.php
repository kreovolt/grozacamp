<?php
declare(strict_types=1);

namespace Groza\Seo;

use DateTimeImmutable;
use Groza\Domain\Shifts;

/** sitemap.xml из данных: статические страницы и страницы видимых смен. Закрытые от индексации адреса сюда не попадают. */
final class Sitemap
{
    /**
     * data: pages — список [path, priority?, lastmod?: DateTimeImmutable|null]; shifts — смены из данных;
     * shiftsLastmod — когда менялся файл смен (необязательно).
     *
     * @param array<string,mixed> $data
     */
    public static function build(array $data, string $baseUrl, DateTimeImmutable $now): string
    {
        $base = rtrim($baseUrl, '/');
        $rows = [];
        foreach ($data['pages'] ?? [] as $page) {
            $rows[] = [(string) $page['path'], $page['priority'] ?? null, $page['lastmod'] ?? null];
        }
        foreach (Shifts::visible($data['shifts'] ?? [], $now) as $shift) {
            $slug = (string) ($shift['slug'] ?? '');
            if (preg_match('/^[a-z0-9-]+$/', $slug) === 1) {
                $rows[] = ['/program/' . $slug . '/', '0.8', $data['shiftsLastmod'] ?? null];
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
        foreach ($rows as [$path, $priority, $lastmod]) {
            $xml .= '  <url><loc>' . htmlspecialchars($base . $path, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc>';
            if ($lastmod instanceof DateTimeImmutable) {
                $xml .= '<lastmod>' . $lastmod->format('Y-m-d') . '</lastmod>';
            }
            if ($priority !== null) {
                $xml .= '<priority>' . $priority . '</priority>';
            }
            $xml .= "</url>\n";
        }

        return $xml . "</urlset>\n";
    }
}
