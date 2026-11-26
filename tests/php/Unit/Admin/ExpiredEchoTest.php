<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Admin;

use Groza\Admin\ExpiredEcho;
use Groza\Http\Request;
use PHPUnit\Framework\TestCase;

/** Показ набранного на странице «сессия устарела/истекла»: только своим формам; «Войти заново» — на ту же страницу. */
final class ExpiredEchoTest extends TestCase
{
    /** @param array<string,string> $headers */
    private static function echoFor(array $headers, string $path = '/admin/texts/info/'): array
    {
        return ExpiredEcho::from(Request::create('POST', $path, [], ['text' => ['info.prepare' => 'Мой текст']], $headers + ['Host' => 'grozacamp.ru']));
    }

    public function test_own_form_gets_its_text_back(): void
    {
        $this->assertCount(1, self::echoFor([])['items'], 'браузер источник не назвал');
        $this->assertCount(1, self::echoFor(['Origin' => 'https://grozacamp.ru'])['items']);
        $this->assertCount(1, self::echoFor(['Referer' => 'https://grozacamp.ru/admin/texts/info/'])['items']);
    }

    public function test_cross_site_post_is_not_reflected(): void
    {
        foreach ([
            ['Origin' => 'https://evil.example'],
            ['Origin' => 'https://grozacamp.ru.evil.example'],
            ['Origin' => 'null'],
            ['Referer' => 'https://evil.example/page'],
            ['Origin' => 'https://evil.example', 'Referer' => 'https://grozacamp.ru/admin/'],
        ] as $headers) {
            $echo = self::echoFor($headers);
            $this->assertSame([], $echo['items'], json_encode($headers));
            $this->assertSame('/admin/texts/info/', $echo['next'], 'ссылка «Войти заново» остаётся');
        }
    }

    public function test_next_points_to_the_page_the_form_came_from(): void
    {
        $id = 'abcdef012345';
        $cases = [
            '/admin/texts/home/' => '/admin/texts/home/',
            '/admin/settings/' => '/admin/settings/',
            '/admin/password/' => '/admin/password/',
            "/admin/shifts/$id/" => "/admin/shifts/$id/",
            '/admin/team/new/' => '/admin/team/new/',
            "/admin/documents/$id/delete/" => "/admin/documents/$id/delete/",
            "/admin/shifts/$id/status/" => '/admin/shifts/',
            "/admin/vacancies/$id/visibility/" => '/admin/vacancies/',
            "/admin/faq/$id/move/" => '/admin/faq/',
            '/admin/history/restore/' => '/admin/history/',
            '/admin/login/' => null,
            '/admin/logout/' => null,
            '/admin/../etc/' => null,
        ];
        foreach ($cases as $path => $next) {
            $this->assertSame($next, ExpiredEcho::next($path), $path);
        }
    }
}
