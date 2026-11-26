<?php
declare(strict_types=1);

namespace Groza\Http;

use Groza\Support\Phone;

/**
 * Страница 500 «что-то сломалось»: без шаблонов, хранилища и настроек из storage (сбой мог быть именно там) —
 * только контакты по умолчанию из config/settings.defaults.php, чтобы родитель мог позвонить или написать.
 * Никаких подробностей ошибки посетителю: причина — в журнале ошибок PHP (error_log).
 */
final class ErrorPage
{
    private const PHONE = '+73422474347';
    private const WHATSAPP = '+79991239300';

    /** Записать причину в журнал (тип, текст, место; без данных запроса). */
    public static function log(\Throwable $e): void
    {
        error_log(sprintf('Groza: %s: %s @ %s:%d', $e::class, $e->getMessage(), $e->getFile(), $e->getLine()));
    }

    public static function response(?string $settingsFile = null): Response
    {
        $contacts = ['phone' => self::PHONE, 'whatsapp' => self::WHATSAPP];
        try {
            $file = $settingsFile ?? dirname(__DIR__, 2) . '/config/settings.defaults.php';
            $defaults = is_file($file) ? (array) require $file : [];
            foreach (['phone', 'whatsapp'] as $k) {
                if (is_string($defaults[$k] ?? null) && $defaults[$k] !== '') {
                    $contacts[$k] = $defaults[$k];
                }
            }
        } catch (\Throwable) {
            // остаются контакты из Global Constraints
        }

        return Response::html(self::html($contacts['phone'], $contacts['whatsapp']), 500)
            ->withHeader('Cache-Control', 'no-store');
    }

    private static function html(string $phone, string $whatsapp): string
    {
        $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $pretty = Phone::pretty($phone);
        $tel = 'tel:' . preg_replace('/[^\d+]/', '', $phone);
        $wa = 'https://wa.me/' . preg_replace('/\D+/', '', $whatsapp);

        return '<!doctype html>' . "\n"
            . '<html lang="ru"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<meta name="robots" content="noindex"><title>Ошибка на сайте — лагерь «Гроза»</title></head>'
            . '<body><main><h1>Что-то пошло не так</h1>'
            . '<p>На сайте произошла ошибка, мы уже знаем о ней. Попробуйте обновить страницу через минуту.</p>'
            . '<p>Чтобы оформить заявку или задать вопрос, позвоните нам или напишите в WhatsApp:</p>'
            . '<p><a href="' . $e($tel) . '">' . $e($pretty) . '</a></p>'
            . '<p><a href="' . $e($wa) . '" rel="noopener">Написать в WhatsApp</a></p>'
            . '<p><a href="/">На главную</a></p></main></body></html>' . "\n";
    }
}
