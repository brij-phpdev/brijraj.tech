<?php
/**
 * BrijRaj.Tech theme functions.
 *
 * Deliberately minimal: the design system lives in theme.json and style.css,
 * and the page structure lives in block patterns. No page builder, no
 * framework, nothing that needs maintaining beyond WordPress itself.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Reusable CTA system — commerce destinations live in options, not in content.
 */
/**
 * Outbound mail via Google Workspace SMTP - loaded first so all later
 * modules inherit authenticated, DKIM-signed delivery.
 */
require_once __DIR__ . '/inc/mail.php';

require_once __DIR__ . '/inc/cta.php';

/**
 * Challenge form — lead capture stored as customer research.
 */
require_once __DIR__ . '/inc/forms.php';

/**
 * SEO output — meta description, Open Graph, JSON-LD. No plugin.
 */
require_once __DIR__ . '/inc/seo.php';

/**
 * Hero animation plumbing — asset is configured in wp-admin, not hardcoded.
 */
require_once __DIR__ . '/inc/hero-media.php';

/**
 * Starter Kit lead capture — the free funnel into the paid product.
 */
require_once __DIR__ . '/inc/leads.php';

/**
 * The Delivery Reporting Audit — offer page, qualifying form, lead store.
 */
require_once __DIR__ . '/inc/audit.php';

/**
 * Reviews — genuine ones only; the component hides itself until they exist.
 */
require_once __DIR__ . '/inc/reviews.php';

/**
 * Inline SVG icons + the contact and source components that use them.
 */
require_once __DIR__ . '/inc/icons.php';

/**
 * Campaign attribution — carries utm_* through to Gumroad and onto lead records.
 */
require_once __DIR__ . '/inc/utm.php';

/**
 * Cookie consent — Google Consent Mode v2, denied by default.
 */
require_once __DIR__ . '/inc/consent.php';

/**
 * Load the parent and child stylesheets.
 *
 * Block themes enqueue style.css on their own, but being explicit keeps the
 * load order predictable: parent first, then our overrides.
 */
add_action('wp_enqueue_scripts', static function (): void {
    $child = get_stylesheet_directory();

    wp_enqueue_style(
        'twentytwentyfive-style',
        get_template_directory_uri() . '/style.css',
        [],
        (string) filemtime(get_template_directory() . '/style.css')
    );

    wp_enqueue_style(
        'brijraj-style',
        get_stylesheet_uri(),
        ['twentytwentyfive-style'],
        (string) filemtime($child . '/style.css')
    );
}, 20);

/**
 * Make the component CSS available inside the block editor too, so the
 * patterns look the same when editing as they do on the front end.
 */
add_action('after_setup_theme', static function (): void {
    add_editor_style('style.css');
});

/**
 * Register a pattern category so the BrijRaj sections group together in the
 * inserter rather than being scattered through the default categories.
 */
add_action('init', static function (): void {
    if (! function_exists('register_block_pattern_category')) {
        return;
    }

    register_block_pattern_category('brijraj', [
        'label'       => __('BrijRaj.Tech', 'brijraj'),
        'description' => __('Sections built for the BrijRaj.Tech launch site.', 'brijraj'),
    ]);
}, 9);

/**
 * Trim WordPress head output we do not need.
 *
 * Removes the generator tag (version disclosure) and legacy discovery links
 * for services this site does not use. Keeps the markup lean and gives away
 * less about the stack.
 */
add_action('init', static function (): void {
    remove_action('wp_head', 'wp_generator');
    remove_action('wp_head', 'wlwmanifest_link');
    remove_action('wp_head', 'rsd_link');
});

/**
 * Disable XML-RPC.
 *
 * Nothing on this site uses it, and it is a standing brute-force and
 * pingback-amplification target.
 */
add_filter('xmlrpc_enabled', '__return_false');

/**
 * Remove the X-Pingback header that advertises XML-RPC.
 */
add_filter('wp_headers', static function (array $headers): array {
    unset($headers['X-Pingback']);
    return $headers;
});

/**
 * Do not leak which of username/password was wrong on a failed login.
 */
add_filter('login_errors', static fn (): string => __('Login failed. Please check your details and try again.', 'brijraj'));

/**
 * Excerpt length tuned for the blog index cards.
 */
add_filter('excerpt_length', static fn (): int => 28, 20);
add_filter('excerpt_more', static fn (): string => '&nbsp;&hellip;');

/**
 * Google Analytics 4 measurement ID.
 *
 * A GA4 measurement ID is a public identifier — it ships in the page source on
 * every site that uses it and grants no access to the property. It is not a
 * secret and is safe to keep in version control.
 */
const BRIJRAJ_GA4_ID = 'G-2QT9WK6L15';

/**
 * Emit the GA4 tag in the document head on the front end.
 *
 * Logged-in administrators and editors are excluded so that our own work on
 * the site does not pollute launch metrics. Remove the current_user_can()
 * check if you want every visit counted, including your own.
 */
add_action('wp_head', static function (): void {
    if (is_admin() || wp_doing_ajax()) {
        return;
    }

    // Keep staging/dev traffic out of the production property.
    if (defined('WP_ENV') && WP_ENV !== 'production') {
        return;
    }

    // Editors are normally excluded so our own work does not pollute the
    // launch metrics. `?ga_debug=1` overrides that — without it there is no way
    // for a logged-in admin to verify their own tracking in GA4 DebugView,
    // because the tag simply is not on the page for them.
    $debug = isset($_GET['ga_debug']) && $_GET['ga_debug'] === '1';

    if (! $debug && is_user_logged_in() && current_user_can('edit_posts')) {
        return;
    }

    $id = BRIJRAJ_GA4_ID;
    ?>
<!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($id); ?>"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
<?php echo brijraj_consent_default_script(); // phpcs:ignore WordPress.Security.EscapeOutput -- static script, no user input. ?>
  gtag('config', '<?php echo esc_js($id); ?>'<?php echo $debug ? ", { debug_mode: true }" : ''; ?>);
</script>
<?php if ($debug) : ?>
<script>console.info('[BrijRaj.Tech] GA4 debug mode on — events stream to GA4 DebugView. Remove ?ga_debug=1 to return to normal (editor-excluded) behaviour.');</script>
<?php endif; ?>
    <?php
}, 1);

/**
 * Social share image.
 *
 * Set from Media Library via the `brijraj_og_image` option so the OG card is a
 * proper 1200x630 landscape rather than the square site icon.
 */
add_filter('brijraj_share_image', static function (string $url): string {
    $set = trim((string) get_option('brijraj_og_image', ''));
    return $set !== '' ? $set : $url;
});

/**
 * [brt_founder_photo] — the founder portrait, or a neutral placeholder if the
 * image has not been set yet. Kept dynamic so replacing the photo is a Media
 * Library change, not a content edit across several pages.
 */
add_shortcode('brt_founder_photo', static function (): string {
    $url = trim((string) get_option('brijraj_founder_image', ''));

    if ($url === '') {
        return '<div class="brt-founder__photo" role="img" aria-label="' . esc_attr__('Brij Raj Singh', 'brijraj') . '"></div>';
    }

    // Read the real dimensions from the attachment so the reserved space (and
    // therefore the aspect ratio) follows whatever image is configured, rather
    // than being pinned to whatever was uploaded first.
    $w = 1400;
    $h = 933;
    $id = attachment_url_to_postid($url);

    if ($id > 0) {
        $meta = wp_get_attachment_metadata($id);

        if (is_array($meta) && ! empty($meta['width']) && ! empty($meta['height'])) {
            $w = (int) $meta['width'];
            $h = (int) $meta['height'];
        }
    }

    return sprintf(
        '<img class="brt-founder__photo" src="%s" alt="%s" width="%d" height="%d" style="--brt-founder-ratio:%s" loading="lazy" decoding="async">',
        esc_url($url),
        esc_attr__('Brij Raj Singh at his desk, beside a wall reading Ideas. Systems. Outcomes.', 'brijraj'),
        $w,
        $h,
        esc_attr($w . ' / ' . $h)
    );
});
