<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Domain;

use Groza\Domain\Collections;
use Groza\Domain\ValidationException;
use Groza\Domain\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private function shift(array $over = []): array
    {
        return $over + [
            'title' => 'Спасатель', 'dateStart' => '2027-07-02', 'dateEnd' => '2027-07-22',
            'ageMin' => '8', 'ageMax' => '17', 'status' => 'open',
        ];
    }

    private function errors(string $coll, array $input, array $refs = []): array
    {
        try {
            Validator::validate(Collections::get($coll), $input, $refs);
        } catch (ValidationException $e) {
            return $e->errors();
        }
        return [];
    }

    private function ok(array $input, string $coll = 'shifts', array $refs = []): array
    {
        return Validator::validate(Collections::get($coll), $input, $refs);
    }

    public function test_title_required(): void
    {
        $this->assertSame('Заполните поле', $this->errors('shifts', $this->shift(['title' => '  ']))['title']);
    }

    public function test_end_before_start_rejected(): void
    {
        $this->assertArrayHasKey('dateEnd', $this->errors('shifts', $this->shift(['dateEnd' => '2027-07-01'])));
    }

    public function test_status_enum(): void
    {
        $this->assertArrayHasKey('status', $this->errors('shifts', $this->shift(['status' => 'maybe'])));
        $this->assertSame('few', $this->ok($this->shift(['status' => 'few']))['status']);
    }

    public function test_price_normalized(): void
    {
        $this->assertSame(60000, $this->ok($this->shift(['price' => '60 000']))['price']);
        $this->assertNull($this->ok($this->shift(['price' => '']))['price']);
        $this->assertArrayHasKey('price', $this->errors('shifts', $this->shift(['price' => '6o'])));
    }

    public function test_slug_from_title_when_empty(): void
    {
        $this->assertSame('spasatel', $this->ok($this->shift(['slug' => '']))['slug']);
        $this->assertSame('moya-smena', $this->ok($this->shift(['slug' => 'Моя смена']))['slug']);
    }

    public function test_days_computed(): void
    {
        $this->assertSame(21, $this->ok($this->shift())['days']);
        $this->assertSame(21, $this->ok($this->shift(['days' => 999]))['days']);
    }

    public function test_age_max_below_min_rejected(): void
    {
        $this->assertArrayHasKey('ageMax', $this->errors('shifts', $this->shift(['ageMin' => '12', 'ageMax' => '8'])));
    }

    public function test_unknown_keys_dropped_and_defaults(): void
    {
        $r = $this->ok($this->shift(['id' => 'x', 'evil' => '1']));
        $this->assertArrayNotHasKey('evil', $r);
        $this->assertArrayNotHasKey('id', $r);
        $this->assertFalse($r['hidden']);
        $this->assertSame([], $r['activities']);
        $this->assertNull($r['photo']);
    }

    public function test_required_fields_per_collection(): void
    {
        $this->assertSame(['title', 'dateStart', 'dateEnd', 'ageMin', 'ageMax', 'status'], array_keys($this->errors('shifts', [])));
        $this->assertSame(['name', 'role', 'group'], array_keys($this->errors('team', [])));
        $this->assertSame(['title', 'section', 'file'], array_keys($this->errors('documents', [])));
        $this->assertSame(['title'], array_keys($this->errors('vacancies', [])));
        $this->assertSame(['question', 'answer'], array_keys($this->errors('faq', [])));
        $this->assertSame(['text'], array_keys($this->errors('reviews', [])));
        $this->assertSame(['slug', 'name'], array_keys($this->errors('venues', [])));
    }

    public function test_text_trim_max_and_control_chars(): void
    {
        $r = $this->ok(['text' => "  hi\x00\x07 there  ", 'author' => str_repeat('я', 500)], 'reviews');
        $this->assertSame('hi there', $r['text']);
        $this->assertLessThanOrEqual(200, mb_strlen($r['author']));
        $r = $this->ok(['text' => "a\r\nb"], 'reviews');
        $this->assertSame("a\nb", $r['text']);
    }

    public function test_bool_and_int_order(): void
    {
        $r = $this->ok(['name' => 'Анна', 'role' => 'Вожатый', 'group' => 'counselor', 'onHome' => '1', 'order' => ' 3 '], 'team');
        $this->assertTrue($r['onHome']);
        $this->assertFalse($r['hidden']);
        $this->assertSame(3, $r['order']);
        $this->assertSame('counselor', $r['group']);
    }

    public function test_invalid_date(): void
    {
        $this->assertArrayHasKey('dateStart', $this->errors('shifts', $this->shift(['dateStart' => '2027-02-30'])));
        $this->assertArrayHasKey('dateStart', $this->errors('shifts', $this->shift(['dateStart' => '02.07.2027'])));
    }

    public function test_list_keeps_non_empty_strings(): void
    {
        $r = $this->ok($this->shift(['activities' => [' Лазертаг ', '', 'Квесты']]));
        $this->assertSame(['Лазертаг', 'Квесты'], $r['activities']);
    }

    public function test_image_manifest_key_or_descriptor_or_null(): void
    {
        $this->assertSame('base-cabins', $this->ok($this->shift(['photo' => 'base-cabins']))['photo']);
        $d = ['src' => '/uploads/img/a.jpg', 'widths' => [480, 960], 'formats' => ['webp', 'jpg'], 'w' => 1600, 'h' => 900];
        $this->assertSame($d, $this->ok($this->shift(['photo' => $d]))['photo']);
        $this->assertNull($this->ok($this->shift(['photo' => '']))['photo']);
        $this->assertArrayHasKey('photo', $this->errors('shifts', $this->shift(['photo' => 'Bad Key!'])));
        $this->assertArrayHasKey('photo', $this->errors('shifts', $this->shift(['photo' => ['src' => 1]])));
    }

    public function test_gallery_is_list_of_images(): void
    {
        $d = ['src' => '/uploads/img/a.jpg', 'widths' => [480], 'formats' => ['jpg'], 'w' => 10, 'h' => 10];
        $r = $this->ok(['slug' => 'sol', 'name' => 'Солнышко', 'photos' => ['base-cabins', '', $d]], 'venues');
        $this->assertSame(['base-cabins', $d], $r['photos']);
        $this->assertArrayHasKey('photos', $this->errors('venues', ['slug' => 'sol', 'name' => 'x', 'photos' => ['Bad Key']]));
    }

    public function test_file_descriptor(): void
    {
        $doc = ['title' => 'Устав', 'section' => 'uchreditelnye'];
        $f = ['url' => '/uploads/docs/ustav.pdf', 'bytes' => 1024, 'originalName' => 'Устав.pdf'];
        $this->assertSame($f, $this->ok($doc + ['file' => $f], 'documents')['file']);
        $this->assertArrayHasKey('file', $this->errors('documents', $doc + ['file' => ['url' => 5]]));
    }

    public function test_ref_checks_format_and_existence(): void
    {
        $this->assertSame('solnyshko', $this->ok($this->shift(['venue' => 'solnyshko']))['venue']);
        $this->assertArrayHasKey('venue', $this->errors('shifts', $this->shift(['venue' => 'Bad Slug'])));
        $this->assertSame([], $this->errors('shifts', $this->shift(['venue' => 'solnyshko']), ['venues' => ['solnyshko']]));
        $this->assertArrayHasKey('venue', $this->errors('shifts', $this->shift(['venue' => 'vostok']), ['venues' => ['solnyshko']]));
    }

    public function test_collections_definitions(): void
    {
        $this->assertSame(['shifts', 'venues', 'team', 'documents', 'vacancies', 'faq', 'reviews'], array_keys(Collections::all()));
        $this->assertSame('/program/{slug}/', Collections::get('shifts')['publicUrl']);
        $names = array_column(Collections::get('venues')['fields'], 'name');
        $this->assertCount(4 + 5 + 13, $names);
        $this->assertContains('access_zh', $names);
        $this->assertSame('Идёт набор', Collections::get('shifts')['fields'][array_search('status', array_column(Collections::get('shifts')['fields'], 'name'), true)]['options']['open']);
        $this->expectException(\InvalidArgumentException::class);
        Collections::get('nope');
    }

    public function test_slug_cut_at_max_does_not_end_with_dash(): void
    {
        $title = str_repeat('а', 79) . ' ' . 'бвг'; // 79 букв, дефис на 80-й позиции
        $slug = $this->ok($this->shift(['title' => $title, 'slug' => '']))['slug'];
        $this->assertLessThanOrEqual(80, strlen($slug));
        $this->assertStringEndsNotWith('-', $slug);
    }

    public function test_slug_exact_max_length_is_kept(): void
    {
        $slug = str_repeat('a', 80);
        $this->assertSame($slug, $this->ok($this->shift(['slug' => $slug]))['slug']);
        $this->assertSame(80, strlen($this->ok($this->shift(['slug' => $slug . 'b']))['slug']));
    }

    public function test_int_edge_cases(): void
    {
        $this->assertSame(1, $this->ok($this->shift(['price' => '0000000001']))['price']);
        $this->assertSame(0, $this->ok($this->shift(['price' => '0']))['price']);
        $this->assertSame(999999999, $this->ok($this->shift(['price' => '999999999']))['price']);
        foreach (['1000000000', '-5', '1.5', '1e3', '12a'] as $bad) {
            $this->assertArrayHasKey('price', $this->errors('shifts', $this->shift(['price' => $bad])), $bad);
        }
        $this->assertArrayHasKey('price', $this->errors('shifts', $this->shift(['price' => PHP_INT_MAX])));
        $this->assertArrayHasKey('price', $this->errors('shifts', $this->shift(['price' => -1])));
    }

    public function test_ref_key_regex_rejects_trailing_newline(): void
    {
        $this->assertArrayHasKey('photo', $this->errors('shifts', $this->shift(['photo' => "base-cabins\n"])));
    }

    public function test_image_descriptor_is_constrained_defence_in_depth(): void
    {
        $good = ['src' => '/uploads/img/a1b2', 'widths' => [480], 'formats' => ['jpg'], 'w' => 10, 'h' => 10];
        $this->assertSame($good, $this->ok($this->shift(['photo' => $good]))['photo']);
        $assets = ['src' => '/assets/img/friends-selfie'] + $good;
        $this->assertSame($assets, $this->ok($this->shift(['photo' => $assets]))['photo']);
        $bad = [
            ['src' => 'https://evil.example/x.jpg'], ['src' => '//evil.example/x'], ['src' => '/etc/passwd'],
            ['src' => '/uploads/img/../../x'], ['src' => "/uploads/img/a\n"], ['src' => '/uploads/'],
            ['w' => 0], ['h' => -3], ['widths' => [0]], ['widths' => [-480]], ['widths' => []], ['formats' => []],
            ['formats' => ['../x']],
        ];
        foreach ($bad as $i => $patch) {
            $this->assertArrayHasKey('photo', $this->errors('shifts', $this->shift(['photo' => $patch + $good])), 'case ' . $i);
        }
    }

    public function test_file_descriptor_is_constrained(): void
    {
        $doc = ['title' => 'Устав', 'section' => 'uchreditelnye'];
        $f = ['url' => '/uploads/docs/ustav.pdf', 'bytes' => 1024, 'originalName' => 'Устав.pdf'];
        foreach ([['url' => 'https://evil.example/a.pdf'], ['url' => '/uploads/docs/../../x'], ['url' => '/etc/passwd'], ['bytes' => -1]] as $i => $patch) {
            $this->assertArrayHasKey('file', $this->errors('documents', $doc + ['file' => $patch + $f]), 'case ' . $i);
        }
    }
}
