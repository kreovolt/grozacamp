<?php
declare(strict_types=1);

namespace Groza\Mail;

/** Письмо. Всё, что уходит в заголовки, очищается от переводов строк здесь же — вторая линия защиты после форм. */
final class Message
{
    public readonly string $to;
    public readonly string $subject;
    public readonly string $from;
    public readonly string $fromName;
    public readonly ?string $replyTo;

    public function __construct(
        string $to,
        string $subject,
        public readonly string $text,
        string $from = '',
        string $fromName = '',
        ?string $replyTo = null,
    ) {
        $to = self::header($to);
        if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
            throw new MailException('Некорректный адрес получателя.', 'bad_recipient');
        }
        $this->to = $to;
        $this->subject = self::header($subject);
        $this->from = self::address($from) ?? '';
        $this->fromName = self::header($fromName);
        $this->replyTo = self::address($replyTo);
    }

    /** Перевод строки в заголовке — признак подмены: адрес с ним считается недействительным. */
    private static function address(?string $v): ?string
    {
        if ($v === null || $v === '' || preg_match('/[\r\n\x00]/', $v) === 1) {
            return null;
        }

        return filter_var($v, FILTER_VALIDATE_EMAIL) === false ? null : $v;
    }

    public static function header(string $v): string
    {
        return trim(preg_replace('/[\p{Cc}\p{Zl}\p{Zp}\s]+/u', ' ', $v) ?? '');
    }
}
