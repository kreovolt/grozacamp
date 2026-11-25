<?php
declare(strict_types=1);

namespace Groza\Mail;

/**
 * Письмо не отправлено. $reason — короткий код причины для журнала (без текста ошибок сервера и данных заявки):
 * not_configured, bad_recipient, smtp_connect, smtp_auth, send_failed, write_failed.
 */
final class MailException extends \RuntimeException
{
    public function __construct(string $message = '', public readonly string $reason = 'failed', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
