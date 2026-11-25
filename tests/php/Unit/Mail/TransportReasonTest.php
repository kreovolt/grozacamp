<?php
declare(strict_types=1);

namespace Groza\Tests\Unit\Mail;

use Groza\Mail\FileTransport;
use Groza\Mail\MailException;
use Groza\Mail\Message;
use Groza\Mail\SmtpTransport;
use PHPUnit\Framework\TestCase;

/** Причины сбоя почты — короткие коды без данных заявки и без текста ответа сервера. */
final class TransportReasonTest extends TestCase
{
    private function message(string $from = ''): Message
    {
        return new Message('2474347@bk.ru', 'Тема', 'Текст', $from);
    }

    private function reasonOf(callable $fn): string
    {
        try {
            $fn();
        } catch (MailException $e) {
            return $e->reason;
        }
        $this->fail('MailException expected');
    }

    public function test_smtp_without_host_is_not_configured(): void
    {
        $this->assertSame('not_configured', $this->reasonOf(fn () => (new SmtpTransport(['host' => '']))->send($this->message())));
    }

    public function test_bad_recipient(): void
    {
        $this->assertSame('bad_recipient', $this->reasonOf(fn () => new Message('не-адрес', 'Тема', 'Текст')));
    }

    public function test_unreachable_smtp_server_reports_connect_without_server_text(): void
    {
        $t = new SmtpTransport(['host' => '127.0.0.1', 'port' => 1, 'secure' => '', 'from' => 'site@example.ru']);
        try {
            $t->send($this->message());
            $this->fail('MailException expected');
        } catch (MailException $e) {
            $this->assertSame('smtp_connect', $e->reason);
            $this->assertStringNotContainsString('127.0.0.1', $e->getMessage());
            $this->assertStringNotContainsString('2474347', $e->getMessage());
        }
    }

    public function test_file_transport_failure_is_write_failed(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'groza-mail-');
        try {
            // каталог писем «внутри» обычного файла создать нельзя
            $this->assertSame('write_failed', $this->reasonOf(fn () => (new FileTransport($file . '/sub'))->send($this->message())));
        } finally {
            unlink($file);
        }
    }

    public function test_from_address_falls_back_from_message_to_config_to_login(): void
    {
        $cfg = ['host' => 'h', 'from' => 'cfg@example.ru', 'user' => 'login@example.ru'];
        $this->assertSame('msg@example.ru', (new SmtpTransport($cfg))->fromAddress($this->message('msg@example.ru')));
        $this->assertSame('cfg@example.ru', (new SmtpTransport($cfg))->fromAddress($this->message()));
        $this->assertSame('login@example.ru', (new SmtpTransport(['from' => '', 'user' => 'login@example.ru']))->fromAddress($this->message()));
        $this->assertSame('', (new SmtpTransport(['from' => '', 'user' => 'не-адрес']))->fromAddress($this->message()));
    }
}
