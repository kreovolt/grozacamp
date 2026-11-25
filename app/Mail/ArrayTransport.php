<?php
declare(strict_types=1);

namespace Groza\Mail;

/** Для тестов: письма копятся в памяти. */
final class ArrayTransport implements MailTransport
{
    /** @var list<Message> */
    public array $sent = [];

    public function send(Message $m): void
    {
        $this->sent[] = $m;
    }
}
