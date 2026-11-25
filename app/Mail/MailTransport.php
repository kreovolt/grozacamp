<?php
declare(strict_types=1);

namespace Groza\Mail;

interface MailTransport
{
    /** @throws MailException письмо не отправлено */
    public function send(Message $m): void;
}
