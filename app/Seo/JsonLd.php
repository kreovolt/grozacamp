<?php
declare(strict_types=1);

namespace Groza\Seo;

use Groza\Support\Phone;

/** Структурированные данные schema.org (JSON-LD): организация и смены. */
final class JsonLd
{
    private const IMAGE = '/assets/og-default.jpg';

    /**
     * Лагерь как организация. У schema.org нет типа SummerCamp (спека просит его): рядом указан LocalBusiness,
     * который поисковики понимают.
     *
     * @param array<string,mixed> $settings
     * @return array<string,mixed>
     */
    public static function camp(array $settings, string $baseUrl): array
    {
        $base = rtrim($baseUrl, '/');
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => ['SummerCamp', 'LocalBusiness'],
            'name' => 'Детский лагерь «Гроза»',
            'url' => $base . '/',
            'logo' => $base . '/assets/logo-header.png',
            'image' => $base . self::IMAGE,
        ];
        $phone = trim((string) ($settings['phone'] ?? ''));
        if ($phone !== '') {
            $ld['telephone'] = Phone::pretty($phone);
        }
        $email = trim((string) ($settings['email'] ?? ''));
        if ($email !== '') {
            $ld['email'] = $email;
        }
        $office = trim((string) ($settings['officeAddress'] ?? ''));
        if ($office !== '') {
            $ld['address'] = [
                '@type' => 'PostalAddress',
                'streetAddress' => preg_replace('/^г\.\s*Пермь,\s*/u', '', $office),
                'addressLocality' => 'Пермь',
                'addressRegion' => 'Пермский край',
                'addressCountry' => 'RU',
            ];
        }
        $same = array_values(array_filter(
            [trim((string) ($settings['vk'] ?? '')), trim((string) ($settings['youtube'] ?? ''))],
            static fn (string $u): bool => $u !== ''
        ));
        if ($same !== []) {
            $ld['sameAs'] = $same;
        }

        return $ld;
    }

    /**
     * Смена как событие. Цена — только если она задана.
     *
     * @param array<string,mixed> $shift
     * @param array<string,mixed>|null $venue
     * @return array<string,mixed>
     */
    public static function event(array $shift, ?array $venue, string $baseUrl): array
    {
        $base = rtrim($baseUrl, '/');
        $url = $base . '/program/' . (string) ($shift['slug'] ?? '') . '/';
        $ld = [
            '@context' => 'https://schema.org',
            '@type' => 'Event',
            'name' => (string) ($shift['title'] ?? ''),
            'startDate' => (string) ($shift['dateStart'] ?? ''),
            'endDate' => (string) ($shift['dateEnd'] ?? ''),
            'eventStatus' => 'https://schema.org/EventScheduled',
            'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
            'url' => $url,
            'image' => [$base . self::IMAGE],
            'organizer' => ['@type' => 'Organization', 'name' => 'Детский лагерь «Гроза»', 'url' => $base . '/'],
        ];
        $lead = trim((string) ($shift['lead'] ?? ''));
        if ($lead !== '') {
            $ld['description'] = $lead;
        }
        $venueName = trim((string) ($venue['name'] ?? ''));
        $venueAddress = trim((string) ($venue['address'] ?? ''));
        $ld['location'] = [
            '@type' => 'Place',
            'name' => $venueName !== '' ? $venueName : 'Детский лагерь «Гроза»',
            'address' => $venueAddress !== ''
                ? ['@type' => 'PostalAddress', 'streetAddress' => $venueAddress, 'addressRegion' => 'Пермский край', 'addressCountry' => 'RU']
                : ['@type' => 'PostalAddress', 'addressRegion' => 'Пермский край', 'addressCountry' => 'RU'],
        ];
        $min = (int) ($shift['ageMin'] ?? 0);
        $max = (int) ($shift['ageMax'] ?? 0);
        if ($min > 0 && $max >= $min) {
            $ld['typicalAgeRange'] = $min . '-' . $max;
        }
        $price = (int) ($shift['price'] ?? 0);
        if ($price > 0) {
            $availability = match ((string) ($shift['status'] ?? 'open')) {
                'closed' => 'https://schema.org/SoldOut',
                'soon' => 'https://schema.org/PreOrder',
                default => 'https://schema.org/InStock',
            };
            $ld['offers'] = [
                '@type' => 'Offer',
                'price' => (string) $price,
                'priceCurrency' => 'RUB',
                'availability' => $availability,
                'url' => $url,
            ];
        }

        return $ld;
    }

    /** JSON для <script type="application/ld+json">: «</» и «<!--» не могут закрыть тег (JSON_HEX_TAG). @param array<string,mixed> $data */
    public static function encode(array $data): string
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_THROW_ON_ERROR);
    }
}
