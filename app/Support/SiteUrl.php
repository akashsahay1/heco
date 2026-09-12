<?php

namespace App\Support;

/**
 * Absolute links to the two surfaces, built from configuration rather than
 * from whoever happened to make the request.
 *
 * url() answers with the host of the current request, which is right inside a
 * browser and wrong everywhere else: a mail sent from a console command, a
 * queued job, or a developer's machine carries that host into somebody's inbox,
 * and hecoportal.test is a dead link for every reader but its author. Mail is
 * read somewhere else, later — so it asks here instead.
 *
 * The portal base is APP_URL, not PORTAL_DOMAIN, on purpose: APP_URL is set on
 * every environment including the live one, whereas PORTAL_DOMAIN has a
 * .test default that would quietly reappear anywhere it was left unset.
 */
class SiteUrl
{
    public static function portal(string $path = '/'): string
    {
        $base = rtrim((string) config('app.url'), '/');

        return ($base ?: 'https://' . trim((string) config('app.portal_domain'), '/'))
            . '/' . ltrim($path, '/');
    }

    public static function admin(string $path = '/'): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https') ? 'https' : 'http';

        return $scheme . '://' . trim((string) config('app.admin_domain'), '/') . '/' . ltrim($path, '/');
    }

    /** Just the portal's hostname, for "you subscribed at …" lines. */
    public static function portalHost(): string
    {
        return (string) (parse_url(self::portal(), PHP_URL_HOST) ?: config('app.portal_domain'));
    }
}
