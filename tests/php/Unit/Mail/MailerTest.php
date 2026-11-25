<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Mail;

use Groza\Mail\ArrayTransport;
use Groza\Mail\FileTransport;
use Groza\Mail\Mailer;
use Groza\Mail\MailException;
use Groza\Mail\Message;
use Groza\Support\FixedClock;
use PHPUnit\Framework\TestCase;

final class MailerTest extends TestCase
{
    private function mailer(ArrayTransport $t): Mailer
    {
        return new Mailer($t, '2474347@bk.ru', 'noreply@grozacamp.ru', 'Лагерь «Гроза»', new FixedClock('2027-01-15T12:00:00+05:00'));
    }

    private function booking(array $over = []): array
    {
        return $over + ['shift' => 'spasatel-2027', 'parentName' => 'Иванова Анна', 'phone' => '+79504411299', 'childAge' => 11, 'comment' => 'Позвоните после 18:00'];
    }

    public function test_booking_mail(): void
    {
        $t = new ArrayTransport();
        $this->mailer($t)->bookingRequest($this->booking(), ['title' => 'Спасатель', 'dateStart' => '2027-07-02', 'dateEnd' => '2027-07-22']);
        $this->assertCount(1, $t->sent);
        $m = $t->sent[0];
        $this->assertSame('2474347@bk.ru', $m->to);
        $this->assertSame('Заявка на путёвку: Спасатель — Иванова Анна', $m->subject);
        $this->assertSame('noreply@grozacamp.ru', $m->from);
        $this->assertNull($m->replyTo);
        $this->assertStringContainsString('Смена: Спасатель (2–22 июля 2027)', $m->text);
        $this->assertStringContainsString('Родитель: Иванова Анна', $m->text);
        $this->assertStringContainsString('Телефон: +7 (950) 441-12-99', $m->text);
        $this->assertStringContainsString('Возраст ребёнка: 11', $m->text);
        $this->assertStringContainsString('Комментарий: Позвоните после 18:00', $m->text);
        $this->assertStringContainsString('Отправлено с сайта grozacamp.ru, 15.01.2027 12:00', $m->text);
    }

    public function test_booking_shift_without_valid_dates_has_no_brackets(): void
    {
        $t = new ArrayTransport();
        $this->mailer($t)->bookingRequest($this->booking(), ['title' => 'Спасатель']);
        $this->assertStringContainsString("Смена: Спасатель
", $t->sent[0]->text);
    }

    public function test_booking_without_shift(): void
    {
        $t = new ArrayTransport();
        $this->mailer($t)->bookingRequest($this->booking(['shift' => 'any', 'comment' => '']), null);
        $this->assertSame('Заявка на путёвку: смена не выбрана — Иванова Анна', $t->sent[0]->subject);
        $this->assertStringContainsString('Смена: не выбрана', $t->sent[0]->text);
    }

    public function test_subject_has_no_newlines(): void
    {
        $t = new ArrayTransport();
        $this->mailer($t)->bookingRequest($this->booking(['parentName' => "Анна\r\nBcc: x@y.ru"]), ['title' => "Спас
атель"]);
        $this->assertDoesNotMatchRegularExpression('/[\r\n]/', $t->sent[0]->subject);
        $this->assertSame('Заявка на путёвку: Спас атель — Анна Bcc: x@y.ru', $t->sent[0]->subject);
    }

    public function test_vacancy_mail_with_reply_to(): void
    {
        $t = new ArrayTransport();
        $this->mailer($t)->vacancyApplication([
            'fullName' => 'Петров Пётр', 'phone' => '+79504411299', 'email' => 'p@example.ru', 'age' => 22, 'position' => 'Вожатый',
            'experience' => 'Два сезона', 'shifts' => 'июль', 'vk' => 'https://vk.com/petrov',
        ]);
        $m = $t->sent[0];
        $this->assertSame('Анкета на вакансию: Вожатый — Петров Пётр', $m->subject);
        $this->assertSame('p@example.ru', $m->replyTo);
        $this->assertStringContainsString('ФИО: Петров Пётр', $m->text);
        $this->assertStringContainsString('Телефон: +7 (950) 441-12-99', $m->text);
        $this->assertStringContainsString('ВКонтакте: https://vk.com/petrov', $m->text);
    }

    public function test_vacancy_without_email_has_no_reply_to(): void
    {
        $t = new ArrayTransport();
        $this->mailer($t)->vacancyApplication(['fullName' => 'П П', 'phone' => '+79504411299', 'email' => '', 'age' => 22, 'position' => 'Повар', 'experience' => '', 'shifts' => '', 'vk' => '']);
        $this->assertNull($t->sent[0]->replyTo);
    }

    public function test_message_strips_crlf_from_header_fields_and_rejects_bad_recipient(): void
    {
        $m = new Message('a@b.ru', "Тема\r\nBcc: evil@x.ru", 'text', 'from@b.ru', 'Имя', "x@y.ru\r\nBcc: z@z.ru");
        $this->assertStringNotContainsString("\n", $m->subject);
        $this->assertNull($m->replyTo); // с переводом строки адрес недействителен
        $this->expectException(MailException::class);
        new Message("a@b.ru\r\nBcc: evil@x.ru", 's', 't');
    }

    public function test_invalid_recipient_is_mail_failure(): void
    {
        $this->expectException(MailException::class);
        (new Mailer(new ArrayTransport(), '', 'noreply@x.ru'))->bookingRequest($this->booking(), null);
    }

    public function test_file_transport_writes_eml(): void
    {
        $dir = sys_get_temp_dir() . '/groza-eml-' . bin2hex(random_bytes(4));
        (new FileTransport($dir))->send(new Message('a@b.ru', 'Тема письма', "Строка 1\nСтрока 2", 'noreply@x.ru', 'Гроза', 'r@x.ru'));
        $files = glob($dir . '/*.eml') ?: [];
        $this->assertCount(1, $files);
        $eml = (string) file_get_contents($files[0]);
        $this->assertStringContainsString('To: a@b.ru', $eml);
        $this->assertStringContainsString('Reply-To: r@x.ru', $eml);
        $this->assertStringContainsString('Строка 2', $eml);
        unlink($files[0]);
        rmdir($dir);
    }
}
