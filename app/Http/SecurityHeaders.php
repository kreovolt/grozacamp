<?php
declare(strict_types=1);

namespace Groza\Http;

/** Заголовки безопасности на каждом ответе (страницы, ошибки, переадресации). */
final class SecurityHeaders
{
    /**
     * config: env (dev|test|staging|prod), metrikaId (цифры; пусто — Метрика не подключена).
     *
     * @param array<string,mixed> $config
     */
    public static function apply(Response $r, array $config): Response
    {
        $env = (string) ($config['env'] ?? 'prod');
        $metrika = (string) ($config['metrikaId'] ?? '') !== '';
        $yandex = ' https://mc.yandex.ru https://mc.yandex.com';

        $csp = [
            "default-src 'self'",
            "img-src 'self' data:" . ($metrika ? $yandex : ''),
            "style-src 'self'",
            "script-src 'self'" . ($metrika ? $yandex : ''),
            "font-src 'self'",
            "connect-src 'self'" . ($metrika ? $yandex : ''),
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "object-src 'none'",
        ];

        $r = $r->withHeader('Content-Security-Policy', implode('; ', $csp))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()')
            ->withHeader('Cross-Origin-Opener-Policy', 'same-origin');
        if ($env === 'prod') {
            $r = $r->withHeader('Strict-Transport-Security', 'max-age=31536000');
        }
        if ($env === 'staging') {
            $r = $r->withHeader('X-Robots-Tag', 'noindex, nofollow');
        }

        return $r;
    }
}
