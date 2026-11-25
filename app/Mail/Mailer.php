<?php
declare(strict_types=1);

namespace Groza\Mail;

use Groza\Support\Clock;
use Groza\Support\Phone;

/** Письма офису: текст простой, по полю на строке. Адресат — настройка mailTo. */
final class Mailer
{
    public function __construct(
        private MailTransport $transport,
        private string $to,
        private string $from = '',
        private string $fromName = '',
        private ?Clock $clock = null,
    ) {
    }

    /**
     * @param array<string,mixed> $data  результат BookingForm (phone уже +7XXXXXXXXXX)
     * @param array<string,mixed>|null $shift выбранная смена или null («любая»)
     * @throws MailException
     */
    public function bookingRequest(array $data, ?array $shift): void
    {
        $title = $shift !== null ? Message::header((string) ($shift['title'] ?? '')) : '';
        $title = $title !== '' ? $title : 'смена не выбрана';
        $name = (string) ($data['parentName'] ?? '');

        $lines = [
            'Смена: ' . ($shift !== null ? $title : 'не выбрана'),
            'Родитель: ' . $name,
            'Телефон: ' . Phone::pretty((string) ($data['phone'] ?? '')),
            'Возраст ребёнка: ' . (string) ($data['childAge'] ?? ''),
        ];
        if (trim((string) ($data['comment'] ?? '')) !== '') {
            $lines[] = 'Комментарий: ' . $data['comment'];
        }

        $this->deliver('Заявка на путёвку: ' . $title . ' — ' . $name, $lines, null);
    }

    /**
     * @param array<string,mixed> $data результат VacancyForm
     * @throws MailException
     */
    public function vacancyApplication(array $data): void
    {
        $position = (string) ($data['position'] ?? '');
        $name = (string) ($data['fullName'] ?? '');

        $lines = [
            'Должность: ' . $position,
            'ФИО: ' . $name,
            'Телефон: ' . Phone::pretty((string) ($data['phone'] ?? '')),
            'Возраст: ' . (string) ($data['age'] ?? ''),
        ];
        foreach (['email' => 'Почта', 'vk' => 'ВКонтакте', 'shifts' => 'Удобные смены', 'experience' => 'Опыт'] as $key => $label) {
            if (trim((string) ($data[$key] ?? '')) !== '') {
                $lines[] = $label . ': ' . $data[$key];
            }
        }
        $email = (string) ($data['email'] ?? '');

        $this->deliver('Анкета на вакансию: ' . $position . ' — ' . $name, $lines, $email !== '' ? $email : null);
    }

    /** @param list<string> $lines */
    private function deliver(string $subject, array $lines, ?string $replyTo): void
    {
        $when = $this->clock?->now()->format('d.m.Y H:i');
        $lines[] = '';
        $lines[] = 'Отправлено с сайта grozacamp.ru' . ($when !== null ? ', ' . $when . ' (Пермь)' : '');

        $this->transport->send(new Message($this->to, $subject, implode("\n", $lines) . "\n", $this->from, $this->fromName, $replyTo));
    }
}
