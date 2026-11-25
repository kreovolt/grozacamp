<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Forms;

use Groza\Forms\VacancyForm;
use PHPUnit\Framework\TestCase;

final class VacancyFormTest extends TestCase
{
    private const POSITIONS = ['Вожатый', 'Повар'];

    private function valid(array $over = []): array
    {
        return $over + [
            'fullName' => 'Петров Пётр', 'phone' => '+7(950)4411299', 'email' => 'p@example.ru', 'age' => '22',
            'position' => 'Вожатый', 'experience' => 'Два сезона в лагере', 'shifts' => 'июль', 'vk' => 'https://vk.com/petrov', 'consent' => 'yes',
        ];
    }

    public function test_valid(): void
    {
        $r = VacancyForm::validate($this->valid(), self::POSITIONS);
        $this->assertTrue($r->ok());
        $this->assertSame('+79504411299', $r->data['phone']);
        $this->assertSame(22, $r->data['age']);
        $this->assertSame('Вожатый', $r->data['position']);
    }

    public function test_optional_fields_may_be_empty(): void
    {
        $r = VacancyForm::validate($this->valid(['email' => '', 'vk' => '', 'shifts' => '', 'experience' => '']), self::POSITIONS);
        $this->assertTrue($r->ok());
    }

    public function test_other_position_allowed_unknown_rejected(): void
    {
        $this->assertTrue(VacancyForm::validate($this->valid(['position' => 'Другое']), self::POSITIONS)->ok());
        $this->assertArrayHasKey('position', VacancyForm::validate($this->valid(['position' => 'Директор']), self::POSITIONS)->errors);
    }

    public function test_email_validation(): void
    {
        $this->assertArrayHasKey('email', VacancyForm::validate($this->valid(['email' => 'not-an-email']), self::POSITIONS)->errors);
        $r = VacancyForm::validate($this->valid(['email' => "a@b.ru\r\nBcc: x@y.ru"]), self::POSITIONS);
        $this->assertArrayHasKey('email', $r->errors);
    }

    public function test_age_bounds(): void
    {
        foreach (['15', '81', 'x'] as $age) {
            $this->assertArrayHasKey('age', VacancyForm::validate($this->valid(['age' => $age]), self::POSITIONS)->errors, $age);
        }
        $this->assertTrue(VacancyForm::validate($this->valid(['age' => '16']), self::POSITIONS)->ok());
    }

    public function test_vk_must_be_vk_https_link(): void
    {
        foreach (['http://vk.com/x', 'https://evil.example/vk.com/x', 'vk.com/x', 'javascript:alert(1)', 'https://vk.com.evil.example/x'] as $vk) {
            $this->assertArrayHasKey('vk', VacancyForm::validate($this->valid(['vk' => $vk]), self::POSITIONS)->errors, $vk);
        }
    }

    public function test_limits_and_consent_and_crlf(): void
    {
        $this->assertArrayHasKey('experience', VacancyForm::validate($this->valid(['experience' => str_repeat('я', 2001)]), self::POSITIONS)->errors);
        $this->assertArrayHasKey('shifts', VacancyForm::validate($this->valid(['shifts' => str_repeat('я', 301)]), self::POSITIONS)->errors);
        $this->assertSame('Нужно согласие на обработку данных', VacancyForm::validate($this->valid(['consent' => '']), self::POSITIONS)->errors['consent']);
        $r = VacancyForm::validate($this->valid(['fullName' => "Пётр\r\nBcc: x@y"]), self::POSITIONS);
        $this->assertSame('Пётр Bcc: x@y', $r->data['fullName']);
    }
}
