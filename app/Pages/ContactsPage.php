<?php
declare(strict_types=1);

namespace Groza\Pages;

use Groza\App;
use Groza\Http\Request;
use Groza\Http\Response;

/**
 * «Контакты» /contacts/ (спека §7.7): телефон, WhatsApp, почта, ВК, адрес и часы офиса из общих настроек.
 * Карты нет: ссылка на Яндекс.Карты вместо встроенного чужого скрипта. Форма вопроса не нужна — звонок, WhatsApp, почта.
 */
final class ContactsPage
{
    public function __construct(private App $app)
    {
    }

    /** @param array<string,string> $params */
    public function __invoke(Request $r, array $params): Response
    {
        $address = trim((string) ($this->app->settings()['officeAddress'] ?? ''));

        return Response::html($this->app->view()->render('pages/contacts', [
            'mapUrl' => $address !== '' ? self::mapUrl($address) : '',
        ]));
    }

    /** Поиск адреса на Яндекс.Картах (открывается в новой вкладке, на нашем сайте чужого кода нет). */
    public static function mapUrl(string $address): string
    {
        return 'https://yandex.ru/maps/?text=' . rawurlencode($address);
    }
}
