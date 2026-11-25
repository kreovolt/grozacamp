<?php
declare(strict_types=1);

namespace Groza\Mail;

/** Для разработки: каждое письмо — файл .eml в каталоге (storage/mail). */
final class FileTransport implements MailTransport
{
    public function __construct(private string $dir)
    {
    }

    public function send(Message $m): void
    {
        if (!is_dir($this->dir) && !@mkdir($this->dir, 0775, true) && !is_dir($this->dir)) {
            throw new MailException('Не удалось создать каталог для писем.', 'write_failed');
        }
        $enc = static fn (string $s): string => mb_encode_mimeheader($s, 'UTF-8', 'B', "\r\n");
        $from = $m->from !== '' ? $m->from : 'noreply@localhost';
        $headers = [
            'Date: ' . date(DATE_RFC2822),
            'From: ' . ($m->fromName !== '' ? $enc($m->fromName) . ' <' . $from . '>' : $from),
            'To: ' . $m->to,
        ];
        if ($m->replyTo !== null) {
            $headers[] = 'Reply-To: ' . $m->replyTo;
        }
        $headers[] = 'Subject: ' . $enc($m->subject);
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/plain; charset=UTF-8';
        $headers[] = 'Content-Transfer-Encoding: 8bit';

        $file = $this->dir . '/' . date('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.eml';
        if (@file_put_contents($file, implode("\r\n", $headers) . "\r\n\r\n" . $m->text . "\r\n") === false) {
            throw new MailException('Не удалось записать письмо в файл.', 'write_failed');
        }
    }
}
