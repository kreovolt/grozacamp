<?php
declare(strict_types=1);

namespace Groza\Mail;

use PHPMailer\PHPMailer\Exception as PhpMailerException;
use PHPMailer\PHPMailer\PHPMailer;

/** Отправка через SMTP почтового ящика домена (PHPMailer). Тексты ошибок сервера наружу не отдаются. */
final class SmtpTransport implements MailTransport
{
    /** @param array<string,mixed> $mailConfig host, port, user, pass, secure (ssl|tls|''), from, fromName */
    public function __construct(private array $mailConfig)
    {
    }

    public function send(Message $m): void
    {
        $c = $this->mailConfig;
        if (trim((string) ($c['host'] ?? '')) === '') {
            throw new MailException('SMTP не настроен.');
        }
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = (string) $c['host'];
            $mail->Port = (int) ($c['port'] ?? 465);
            $mail->SMTPAuth = (string) ($c['user'] ?? '') !== '';
            $mail->Username = (string) ($c['user'] ?? '');
            $mail->Password = (string) ($c['pass'] ?? '');
            $secure = (string) ($c['secure'] ?? 'ssl');
            $mail->SMTPSecure = $secure === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : ($secure === 'ssl' ? PHPMailer::ENCRYPTION_SMTPS : '');
            $mail->SMTPAutoTLS = $secure !== '';
            $mail->Timeout = 10;
            $mail->CharSet = PHPMailer::CHARSET_UTF8;
            $mail->Encoding = PHPMailer::ENCODING_BASE64;
            $mail->isHTML(false);

            $from = $m->from !== '' ? $m->from : (string) ($c['from'] ?? '');
            $mail->setFrom($from, $m->fromName !== '' ? $m->fromName : (string) ($c['fromName'] ?? ''));
            $mail->addAddress($m->to);
            if ($m->replyTo !== null) {
                $mail->addReplyTo($m->replyTo);
            }
            $mail->Subject = $m->subject;
            $mail->Body = $m->text;
            $mail->send();
        } catch (PhpMailerException $e) {
            throw new MailException('Не удалось отправить письмо: ' . $mail->ErrorInfo, 0, $e);
        }
    }
}
