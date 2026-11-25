<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Forms;

use Groza\Forms\BookingForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BookingFormTest extends TestCase
{
    private const OPEN = ['spasatel-2027', 'shturmovik-2027'];

    private function valid(array $over = []): array
    {
        return $over + [
            'shift' => 'spasatel-2027', 'parentName' => 'Иванова Анна', 'phone' => '8 950 441-12-99',
            'childAge' => '11', 'comment' => '', 'consent' => 'yes',
        ];
    }

    public function test_valid(): void
    {
        $r = BookingForm::validate($this->valid(), self::OPEN);
        $this->assertTrue($r->ok());
        $this->assertSame([], $r->errors);
        $this->assertSame(
            ['shift' => 'spasatel-2027', 'parentName' => 'Иванова Анна', 'phone' => '+79504411299', 'childAge' => 11, 'comment' => ''],
            $r->data
        );
    }

    #[DataProvider('phones')]
    public function test_phone_formats_normalized(string $raw): void
    {
        $r = BookingForm::validate($this->valid(['phone' => $raw]), self::OPEN);
        $this->assertTrue($r->ok(), $raw);
        $this->assertSame('+79504411299', $r->data['phone']);
    }

    public static function phones(): array
    {
        return [['8 950 441-12-99'], ['+7(950)4411299'], ['9504411299'], ['+7 950 441 12 99'], ['7-950-441-12-99']];
    }

    public function test_bad_phone(): void
    {
        $r = BookingForm::validate($this->valid(['phone' => '12345']), self::OPEN);
        $this->assertArrayHasKey('phone', $r->errors);
    }

    public function test_unknown_shift_rejected(): void
    {
        $this->assertArrayHasKey('shift', BookingForm::validate($this->valid(['shift' => 'nope']), self::OPEN)->errors);
        $this->assertArrayHasKey('shift', BookingForm::validate($this->valid(['shift' => '']), self::OPEN)->errors);
    }

    public function test_any_shift_allowed(): void
    {
        $this->assertTrue(BookingForm::validate($this->valid(['shift' => 'any']), self::OPEN)->ok());
    }

    public function test_consent_required(): void
    {
        $r = BookingForm::validate($this->valid(['consent' => '']), self::OPEN);
        $this->assertSame('Нужно согласие на обработку данных', $r->errors['consent']);
        $this->assertArrayHasKey('consent', BookingForm::validate($this->valid(['consent' => 'on']), self::OPEN)->errors);
    }

    public function test_crlf_removed_from_name(): void
    {
        $r = BookingForm::validate($this->valid(['parentName' => "Анна\r\nBcc: x@y"]), self::OPEN);
        $this->assertSame('Анна Bcc: x@y', $r->data['parentName']);
    }

    public function test_age_bounds(): void
    {
        foreach (['4', '19', 'abc', '', '11.5'] as $age) {
            $this->assertArrayHasKey('childAge', BookingForm::validate($this->valid(['childAge' => $age]), self::OPEN)->errors, $age);
        }
        foreach (['5', '18'] as $age) {
            $this->assertTrue(BookingForm::validate($this->valid(['childAge' => $age]), self::OPEN)->ok(), $age);
        }
    }

    public function test_length_limits_and_array_input(): void
    {
        $this->assertArrayHasKey('parentName', BookingForm::validate($this->valid(['parentName' => 'А']), self::OPEN)->errors);
        $this->assertArrayHasKey('parentName', BookingForm::validate($this->valid(['parentName' => str_repeat('Я', 81)]), self::OPEN)->errors);
        $this->assertArrayHasKey('comment', BookingForm::validate($this->valid(['comment' => str_repeat('я', 1001)]), self::OPEN)->errors);
        $this->assertTrue(BookingForm::validate($this->valid(['comment' => str_repeat('я', 1000)]), self::OPEN)->ok());
        $r = BookingForm::validate($this->valid(['parentName' => ['x'], 'phone' => ['y']]), self::OPEN);
        $this->assertArrayHasKey('parentName', $r->errors);
        $this->assertArrayHasKey('phone', $r->errors);
    }

    public function test_comment_keeps_line_breaks_and_drops_control_chars(): void
    {
        $r = BookingForm::validate($this->valid(['comment' => "Первая\r\nвторая\x00\x07 строка"]), self::OPEN);
        $this->assertSame("Первая\nвторая строка", $r->data['comment']);
    }

    public function test_extra_fields_are_not_passed_on(): void
    {
        $r = BookingForm::validate($this->valid(['to' => 'evil@x.ru', 'Bcc' => 'evil@x.ru']), self::OPEN);
        $this->assertArrayNotHasKey('to', $r->data);
        $this->assertArrayNotHasKey('Bcc', $r->data);
    }
}
