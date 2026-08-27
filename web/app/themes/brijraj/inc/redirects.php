<?php
/**
 * Permanent redirects for URLs that have moved.
 *
 * Kept in the theme rather than .htaccess so the map deploys with the code,
 * lives in version control, and stays in one readable place. The volume here
 * is a handful of paths on a low-traffic site — nothing that warrants a
 * plugin or a database table.
 *
 * These are permanent and stay permanent. The old URLs are in the GA4
 * history, may be in a published LinkedIn post, and there is no expiry on a
 * link somebody has already shared.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Old path => new path. Both with leading and trailing slashes.
 *
 * @return array<string, string>
 */
function brijraj_redirect_map(): array
{
    return (array) apply_filters('brijraj_redirect_map', [
        // Products moved under /resources/ so they stop competing with the
        // audit for attention in the navigation.
        '/ai-workflow-system/'              => '/resources/ai-workflow-system/',
        '/starter-kit/'                     => '/resources/starter-kit/',
        '/ai-project-delivery-starter-kit/' => '/resources/starter-kit/',
        '/share-your-challenge/'            => '/resources/share-your-challenge/',

        // The blog became /writing/.
        '/blog/'                            => '/writing/',
    ]);
}

/**
 * Issue the redirect.
 *
 * Runs on `template_redirect` at default priority — early enough that nothing
 * has been sent, late enough that WordPress has finished deciding what the
 * request resolves to. Only fires on a 404, so a real page at one of these
 * paths would always win; the map cannot accidentally shadow live content.
 */
add_action('template_redirect', static function (): void {
    if (! is_404()) {
        return;
    }

    $path = (string) wp_parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    $path = '/' . trim($path, '/') . '/';

    if ($path === '//') {
        return;
    }

    $map = brijraj_redirect_map();

    // Exact match first.
    if (isset($map[$path])) {
        brijraj_redirect_to($map[$path]);
    }

    // Then anything nested beneath a mapped path, so a child URL that was
    // shared before the move still lands somewhere sensible.
    foreach ($map as $from => $to) {
        if (str_starts_with($path, $from)) {
            brijraj_redirect_to($to . substr($path, strlen($from)));
        }
    }
});

/**
 * Send a 301, preserving any query string.
 */
function brijraj_redirect_to(string $path): void
{
    $query = (string) ($_SERVER['QUERY_STRING'] ?? '');
    $url   = home_url($path) . ($query !== '' ? '?' . $query : '');

    wp_safe_redirect($url, 301);
    exit;
}
