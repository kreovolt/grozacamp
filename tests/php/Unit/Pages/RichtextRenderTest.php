<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Pages;

use Groza\App;
use Groza\Tests\Support\TestApp;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/** Что бы ни лежало в JSON (правка файла вручную, старые данные), в публичный HTML оно попадает очищенным. */
final class RichtextRenderTest extends TestCase
{
    private const EVIL = '<p>безопасный</p><script>alert(1)</script><img src="x" onerror="alert(2)"><a href="javascript:alert(3)" onclick="alert(4)">ссылка</a><h3 onmouseover="alert(5)">Заголовок</h3>';

    private App $app;

    protected function setUp(): void
    {
        $this->app = TestApp::make();
    }

    private function assertClean(string $html, string $where): void
    {
        $this->assertStringContainsString('безопасный', $html, $where);
        $this->assertStringNotContainsString('<script>alert(1)', $html, $where);
        $this->assertStringNotContainsString('onerror=', $html, $where);
        $this->assertStringNotContainsString('javascript:alert', $html, $where);
        $this->assertStringNotContainsString('onclick=', $html, $where);
        $this->assertStringNotContainsString('onmouseover=', $html, $where);
    }

    private function setText(string $key, string $value): void
    {
        $texts = $this->app->store()->read('texts');
        $texts[$key] = $value;
        $this->app->store()->write('texts', $texts, 'test');
    }

    private function setItems(string $name, callable $edit): void
    {
        $items = $this->app->store()->read($name)['items'];
        foreach ($items as $i => $item) {
            $items[$i] = $edit($item);
        }
        $this->app->store()->write($name, ['items' => $items], 'test');
    }

    /** @return array<string,array{string,string}> */
    public static function textPages(): array
    {
        return [
            'info.prepare' => ['info.prepare', '/info/'],
            'rules on info' => ['rules', '/info/'],
            'rules' => ['rules', '/pravila/'],
            'policy' => ['policy', '/policy/'],
            'vacancies.intro' => ['vacancies.intro', '/vacancies/'],
            'vacancies.benefits' => ['vacancies.benefits', '/vacancies/'],
            'vacancies.selection' => ['vacancies.selection', '/vacancies/'],
            'sved.mode' => ['sved.mode', '/svedeniya/ob-organizacii/'],
            'sved.ages' => ['sved.ages', '/svedeniya/deyatelnost/'],
            'sved.paidOrder' => ['sved.paidOrder', '/svedeniya/uslugi/'],
            'sved.other' => ['sved.other', '/svedeniya/inaya-informaciya/'],
        ];
    }

    #[DataProvider('textPagesProvider')]
    public function test_text_blocks_are_sanitized_on_render(string $key, string $path): void
    {
        $this->setText($key, self::EVIL);
        $res = TestApp::get($this->app, $path);

        $this->assertSame(200, $res->status());
        $this->assertClean($res->body(), "$key → $path");
    }

    /** @return array<string,array{string,string}> */
    public static function textPagesProvider(): array
    {
        return self::textPages();
    }

    public function test_shift_body_is_sanitized_on_render(): void
    {
        $this->setItems('shifts', static fn (array $s): array => ['body' => self::EVIL] + $s);
        $shift = $this->app->store()->read('shifts')['items'][0];
        $res = TestApp::get($this->app, '/program/' . $shift['slug'] . '/');

        $this->assertSame(200, $res->status());
        $this->assertClean($res->body(), 'shift');
    }

    public function test_vacancy_conditions_are_sanitized_on_render(): void
    {
        $this->setItems('vacancies', static fn (array $v): array => ['conditions' => self::EVIL] + $v);
        $this->assertClean(TestApp::get($this->app, '/vacancies/')->body(), 'vacancies');
    }

    public function test_faq_answer_is_sanitized_on_render(): void
    {
        $this->setItems('faq', static fn (array $q): array => ['answer' => self::EVIL, 'onHome' => true] + $q);
        foreach (['/info/', '/booking/', '/'] as $path) {
            $this->assertClean(TestApp::get($this->app, $path)->body(), "faq on $path");
        }
    }

    public function test_h3_anchors_survive_sanitizing_on_text_pages(): void
    {
        $this->setText('policy', '<h3>Первый раздел</h3><p>текст</p>');
        $body = TestApp::get($this->app, '/policy/')->body();
        $this->assertMatchesRegularExpression('/<h3 id="[a-z0-9-]+">Первый раздел<\/h3>/', $body);
    }
}
