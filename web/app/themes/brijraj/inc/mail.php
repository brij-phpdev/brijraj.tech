<?php
/**
 * Outbound mail via Google Workspace SMTP.
 *
 * WordPress sends through the Hostinger server by default, which cannot
 * DKIM-sign for brijraj.tech — the domain's only DKIM key belongs to Google.
 * Mail therefore arrived claiming to be from brijraj.tech, signed by nobody,
 * from a host SPF did not list. Gmail tolerated it for self-addressed mail;
 * Outlook would not, and the Starter Kit delivery would have gone to spam.
 *
 * Routing through Google fixes all three at once: SPF passes (Google is in the
 * record), DKIM is applied by Google using the existing key, and the two align
 * so DMARC can be tightened later.
 *
 * The App Password lives in .env, never in code or version control. With it
 * absent, mail silently falls back to the old path rather than failing — a
 * missing credential should not take the contact forms down.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Read a value from the environment.
 *
 * Bedrock loads .env through phpdotenv, so values land in $_ENV; getenv() is
 * checked as a fallback for other server configurations.
 */
function brijraj_env(string $key, string $default = ''): string
{
    if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
        return (string) $_ENV[$key];
    }

    $v = getenv($key);

    return ($v !== false && $v !== '') ? (string) $v : $default;
}

/**
 * Whether SMTP is fully configured.
 */
function brijraj_smtp_ready(): bool
{
    return brijraj_env('SMTP_HOST') !== ''
        && brijraj_env('SMTP_USER') !== ''
        && brijraj_env('SMTP_PASS') !== '';
}

/**
 * Send through Google rather than the local sendmail binary.
 */
add_action('phpmailer_init', static function ($phpmailer): void {
    if (! brijraj_smtp_ready()) {
        return;
    }

    $phpmailer->isSMTP();
    $phpmailer->Host       = brijraj_env('SMTP_HOST', 'smtp.gmail.com');
    $phpmailer->Port       = (int) brijraj_env('SMTP_PORT', '587');
    $phpmailer->SMTPAuth   = true;
    $phpmailer->Username   = brijraj_env('SMTP_USER');
    $phpmailer->Password   = brijraj_env('SMTP_PASS');
    $phpmailer->SMTPSecure = brijraj_env('SMTP_SECURE', 'tls');
    $phpmailer->CharSet    = 'UTF-8';

    // The envelope sender must match the authenticated mailbox, or Google
    // rejects the message and DKIM alignment is lost.
    $from = brijraj_env('SMTP_FROM', brijraj_env('SMTP_USER'));
    $phpmailer->setFrom($from, brijraj_env('SMTP_FROM_NAME', 'BrijRaj.Tech'), false);
    $phpmailer->Sender = $from;
});

/**
 * Default From address and name.
 *
 * Without these WordPress sends as wordpress@<server hostname>, which defeats
 * the alignment the SMTP routing exists to achieve.
 */
add_filter('wp_mail_from', static function (string $email): string {
    $from = brijraj_env('SMTP_FROM', brijraj_env('SMTP_USER'));
    return $from !== '' ? $from : $email;
}, 20);

add_filter('wp_mail_from_name', static function (string $name): string {
    $n = brijraj_env('SMTP_FROM_NAME', 'BrijRaj.Tech');
    return $n !== '' ? $n : $name;
}, 20);

/**
 * Surface delivery status in wp-admin.
 *
 * Silent mail failure is the worst kind: the form says "check your inbox" and
 * nothing ever arrives, with no signal to anyone. This puts the state where it
 * will actually be noticed.
 */
add_action('admin_notices', static function (): void {
    if (! current_user_can('manage_options')) {
        return;
    }

    $screen = function_exists('get_current_screen') ? get_current_screen() : null;

    if (! $screen || ! in_array($screen->id, ['dashboard', 'settings_page_brijraj-cta'], true)) {
        return;
    }

    if (brijraj_smtp_ready()) {
        return;
    }

    echo '<div class="notice notice-warning"><p><strong>'
        . esc_html__('Email is not authenticated.', 'brijraj')
        . '</strong> '
        . esc_html__('WordPress is sending through the hosting server, which cannot DKIM-sign for brijraj.tech, so Starter Kit deliveries are likely to land in spam. Add SMTP_USER and SMTP_PASS to the server .env file to route mail through Google Workspace.', 'brijraj')
        . '</p></div>';
});

/**
 * Log failures rather than losing them.
 */
add_action('wp_mail_failed', static function ($error): void {
    if (is_wp_error($error)) {
        error_log('[BrijRaj.Tech] Mail failed: ' . $error->get_error_message());
    }
});
