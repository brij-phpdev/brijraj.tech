<?php
/**
 * Reusable CTA system.
 *
 * Commerce is deliberately decoupled from the site: every paid destination is
 * an external URL (Gumroad today, something else later) stored as a WordPress
 * option. Pages and patterns reference a CTA by *slug*, never by URL, so
 * changing where a button points is a one-field edit in wp-admin rather than a
 * content migration across every page.
 *
 * Usage in post content:
 *   [brt_cta id="core"]
 *   [brt_cta id="starter" label="Get the Free Starter Kit" style="primary"]
 *   [brt_cta id="vault" style="secondary" size="small"]
 *
 * Usage in templates/patterns:
 *   echo brijraj_cta('core', ['label' => 'Get Instant Access']);
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The CTA registry.
 *
 * Each entry defines a stable slug, a human label for the settings screen, a
 * default button label, and a fallback URL used until a real destination is
 * configured. Add new funnel steps here — nothing else needs to change.
 *
 * @return array<string, array{label:string, help:string, default_text:string, fallback:string}>
 */
function brijraj_cta_registry(): array
{
    return [
        'core' => [
            'label'        => 'Core product — AI Workflow System ($39)',
            'help'         => 'Gumroad checkout URL for the main product.',
            'default_text' => 'Get Instant Access',
            'fallback'     => '',
        ],
        'vault' => [
            'label'        => 'Upsell — PM AI Workflow Template Vault ($9)',
            'help'         => 'Gumroad URL for the order bump / upsell.',
            'default_text' => 'Add the Template Vault',
            'fallback'     => '',
        ],
        'starter' => [
            'label'        => 'Lead magnet — Free PM AI Starter Kit',
            'help'         => 'Usually an internal page such as /starter-kit/.',
            'default_text' => 'Get the Free PM AI Starter Kit',
            'fallback'     => '/ai-project-delivery-starter-kit/',
        ],
        'downsell' => [
            'label'        => 'Downsell',
            'help'         => 'Optional lower-priced offer for visitors who decline the core product.',
            'default_text' => 'See the smaller option',
            'fallback'     => '',
        ],
        'challenge' => [
            'label'        => 'Share Your Challenge form',
            'help'         => 'Internal page for the challenge/enquiry form.',
            'default_text' => 'Share Your Challenge',
            'fallback'     => '/share-your-challenge/',
        ],
        'product' => [
            'label'        => 'Product detail page (internal)',
            'help'         => 'The on-site page describing the workflow system.',
            'default_text' => 'See the Workflow System',
            'fallback'     => '/ai-workflow-system/',
        ],
    ];
}

/**
 * Option name for a given CTA slug.
 */
function brijraj_cta_option_name(string $slug): string
{
    return 'brijraj_cta_' . sanitize_key($slug);
}

/**
 * Resolve the destination URL for a CTA slug.
 *
 * Returns an empty string when no destination is configured and there is no
 * fallback — callers use that to render a disabled state rather than a link
 * that goes nowhere.
 */
function brijraj_cta_url(string $slug): string
{
    $registry = brijraj_cta_registry();

    if (! isset($registry[$slug])) {
        return '';
    }

    $configured = trim((string) get_option(brijraj_cta_option_name($slug), ''));

    if ($configured !== '') {
        return $configured;
    }

    return $registry[$slug]['fallback'];
}

/**
 * Whether a CTA has a real, configured destination.
 */
function brijraj_cta_is_live(string $slug): bool
{
    return brijraj_cta_url($slug) !== '';
}

/**
 * Render a CTA button.
 *
 * @param string               $slug CTA slug from the registry.
 * @param array<string, mixed> $args label, style (primary|secondary|link), size (default|small), class, note.
 */
function brijraj_cta(string $slug, array $args = []): string
{
    $registry = brijraj_cta_registry();

    if (! isset($registry[$slug])) {
        return '';
    }

    $label = (string) ($args['label'] ?? $registry[$slug]['default_text']);
    $style = (string) ($args['style'] ?? 'primary');
    $size  = (string) ($args['size'] ?? 'default');
    $extra = (string) ($args['class'] ?? '');
    // Where on the page this button sits. Emitted as data-cta-location and read
    // by the analytics listener, because every purchase button shares the same
    // `core` slug and would otherwise be indistinguishable in reporting.
    $location = sanitize_key((string) ($args['location'] ?? ''));
    $url   = brijraj_cta_url($slug);

    $classes = ['brt-btn', 'brt-btn--' . sanitize_html_class($style)];

    if ($size === 'small') {
        $classes[] = 'brt-btn--sm';
    }

    if ($extra !== '') {
        $classes[] = sanitize_html_class($extra);
    }

    // No destination yet: render a clearly-marked disabled control so an
    // unconfigured funnel step is obvious in review rather than shipping a
    // button that silently goes nowhere.
    if ($url === '') {
        if (current_user_can('edit_posts')) {
            return sprintf(
                '<span class="%s brt-btn--unset" aria-disabled="true" title="%s">%s</span>',
                esc_attr(implode(' ', $classes)),
                esc_attr__('No destination configured yet — set it under Settings → CTA Destinations.', 'brijraj'),
                esc_html($label)
            );
        }

        return '';
    }

    $is_external = str_starts_with($url, 'http') && ! str_contains($url, (string) wp_parse_url(home_url(), PHP_URL_HOST));

    return sprintf(
        '<a class="%s" href="%s" data-cta="%s"%s%s>%s</a>',
        esc_attr(implode(' ', $classes)),
        esc_url($url),
        esc_attr($slug),
        $location !== '' ? ' data-cta-location="' . esc_attr($location) . '"' : '',
        $is_external ? ' target="_blank" rel="noopener noreferrer"' : '',
        esc_html($label)
    );
}

/**
 * The product name reported alongside purchase events.
 */
function brijraj_tracked_product_name(): string
{
    return (string) apply_filters('brijraj_tracked_product_name', 'AI Workflow System for Project Delivery');
}

/**
 * [brt_cta] shortcode.
 */
add_shortcode('brt_cta', static function ($atts): string {
    $atts = shortcode_atts(
        ['id' => 'core', 'label' => '', 'style' => 'primary', 'size' => 'default', 'class' => '', 'location' => ''],
        is_array($atts) ? $atts : [],
        'brt_cta'
    );

    $args = [
        'style'    => $atts['style'],
        'size'     => $atts['size'],
        'class'    => $atts['class'],
        'location' => $atts['location'],
    ];

    if ($atts['label'] !== '') {
        $args['label'] = $atts['label'];
    }

    return brijraj_cta((string) $atts['id'], $args);
});

/**
 * [brt_cta_pair] — primary + secondary side by side, the hero pattern.
 */
add_shortcode('brt_cta_pair', static function ($atts): string {
    $atts = shortcode_atts(
        [
            'primary' => 'starter', 'secondary' => 'product',
            'primary_label' => '', 'secondary_label' => '',
            'location' => 'hero',
        ],
        is_array($atts) ? $atts : [],
        'brt_cta_pair'
    );

    $a = brijraj_cta((string) $atts['primary'], array_filter([
        'style'    => 'primary',
        'label'    => $atts['primary_label'] ?: null,
        'location' => $atts['location'] ?: null,
    ]));

    $b = brijraj_cta((string) $atts['secondary'], array_filter([
        'style'    => 'secondary',
        'label'    => $atts['secondary_label'] ?: null,
        'location' => $atts['location'] ?: null,
    ]));

    if ($a === '' && $b === '') {
        return '';
    }

    return '<div class="brt-btns">' . $a . $b . '</div>';
});

/* -------------------------------------------------------------------------
 * Settings screen: Settings → CTA Destinations
 * ---------------------------------------------------------------------- */

add_action('admin_init', static function (): void {
    foreach (array_keys(brijraj_cta_registry()) as $slug) {
        register_setting('brijraj_cta', brijraj_cta_option_name($slug), [
            'type'              => 'string',
            'sanitize_callback' => static function ($value): string {
                $value = trim((string) $value);

                if ($value === '') {
                    return '';
                }

                // Allow site-relative paths as well as absolute URLs.
                if (str_starts_with($value, '/')) {
                    return esc_url_raw($value, ['http', 'https']);
                }

                return esc_url_raw($value, ['http', 'https']);
            },
            'default'           => '',
            'show_in_rest'      => false,
        ]);
    }
});

add_action('admin_menu', static function (): void {
    add_options_page(
        __('CTA Destinations', 'brijraj'),
        __('CTA Destinations', 'brijraj'),
        'manage_options',
        'brijraj-cta',
        static function (): void {
            if (! current_user_can('manage_options')) {
                return;
            }
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('CTA Destinations', 'brijraj'); ?></h1>
                <p style="max-width:52em">
                    <?php esc_html_e('Every call-to-action on the site points at one of these slugs rather than a hardcoded link. Change a URL here and it updates everywhere at once. Leave a field blank and that button is hidden from visitors (and shown to editors as an unset placeholder).', 'brijraj'); ?>
                </p>
                <form method="post" action="options.php">
                    <?php settings_fields('brijraj_cta'); ?>
                    <table class="form-table" role="presentation">
                        <?php foreach (brijraj_cta_registry() as $slug => $meta) :
                            $name = brijraj_cta_option_name($slug); ?>
                            <tr>
                                <th scope="row">
                                    <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($meta['label']); ?></label>
                                </th>
                                <td>
                                    <input type="text" class="regular-text code" id="<?php echo esc_attr($name); ?>"
                                           name="<?php echo esc_attr($name); ?>"
                                           value="<?php echo esc_attr((string) get_option($name, '')); ?>"
                                           placeholder="<?php echo esc_attr($meta['fallback'] ?: 'https://…'); ?>">
                                    <p class="description">
                                        <?php echo esc_html($meta['help']); ?>
                                        <br><code>[brt_cta id="<?php echo esc_html($slug); ?>"]</code>
                                    </p>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>

                    <?php if (function_exists('brijraj_hero_media_fields')) : ?>
                        <h2><?php esc_html_e('Homepage hero animation', 'brijraj'); ?></h2>
                        <p style="max-width:52em">
                            <?php esc_html_e('Upload the animation and its still frame to the Media Library, then paste the URLs here. Until both are blank the hero shows a plain placeholder rather than looking broken.', 'brijraj'); ?>
                        </p>
                        <table class="form-table" role="presentation">
                            <?php foreach (brijraj_hero_media_fields() as $key => $meta) :
                                $name = 'brijraj_' . $key; ?>
                                <tr>
                                    <th scope="row">
                                        <label for="<?php echo esc_attr($name); ?>"><?php echo esc_html($meta['label']); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" class="regular-text code" id="<?php echo esc_attr($name); ?>"
                                               name="<?php echo esc_attr($name); ?>"
                                               value="<?php echo esc_attr((string) get_option($name, '')); ?>">
                                        <p class="description"><?php echo esc_html($meta['help']); ?></p>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>

                    <h2>Lead magnet</h2>


                    <table class="form-table" role="presentation"><tr>


                      <th scope="row"><label for="brijraj_starter_kit_file">Starter Kit file URL</label></th>


                      <td><input type="text" class="regular-text code" id="brijraj_starter_kit_file" name="brijraj_starter_kit_file" value="<?php echo esc_attr((string) get_option('brijraj_starter_kit_file', '')); ?>">


                      <p class="description">Upload the Starter Kit PDF to the Media Library and paste its URL. Left blank, the success message still shows but no download link is offered.</p></td>


                    </tr></table>



                    <?php submit_button(); ?>
                </form>
            </div>
            <?php
        }
    );
});

/**
 * Track CTA clicks in GA4.
 *
 * One tiny delegated listener rather than inline handlers on every button.
 * Fires only when gtag is actually present, so it is inert if analytics is
 * removed or blocked.
 */
add_action('wp_footer', static function (): void {
    if (is_admin()) {
        return;
    }
    ?>
<script>
(function () {
  // One delegated listener, one event per click.
  //
  // Purchase and Starter Kit CTAs emit their own dedicated events and are
  // deliberately excluded from the generic cta_click, so a single click never
  // produces two GA4 events and inflates the counts.
  var PRODUCT = <?php echo wp_json_encode(brijraj_tracked_product_name()); ?>;
  var BUY = { core: 1, nav_core: 1 };

  function path() {
    return window.location.pathname + window.location.search;
  }

  document.addEventListener('click', function (e) {
    var el = e.target.closest('[data-cta]');
    if (!el || typeof window.gtag !== 'function') { return; }

    var id = el.getAttribute('data-cta') || '';
    var loc = el.getAttribute('data-cta-location') || 'unspecified';

    if (BUY[id]) {
      window.gtag('event', 'cta_buy_click', {
        cta_location: loc,
        page_path: path(),
        product_name: PRODUCT
      });
      return;
    }

    if (id === 'starter') {
      window.gtag('event', 'starter_kit_click', {
        cta_location: loc,
        page_path: path()
      });
      return;
    }

    // The audit page's CTAs are in-page anchors rather than registry entries,
    // and all of them mean the same thing: someone moved toward the form. One
    // event, distinguished by location, keeps them out of the generic bucket.
    if (id.indexOf('audit_') === 0) {
      window.gtag('event', 'audit_cta_click', {
        cta_location: loc,
        page_path: path()
      });
      return;
    }

    // These are already reported by the lead-capture script — starter_download
    // as lead_download, starter_kit_submit as lead_form_submit. Emitting
    // cta_click here as well would count one interaction twice.
    if (id === 'starter_download' || id === 'starter_kit_submit') { return; }

    window.gtag('event', 'cta_click', {
      cta_id: id,
      cta_text: (el.textContent || '').trim().slice(0, 80),
      link_url: el.getAttribute('href') || ''
    });
  }, { passive: true });
})();
</script>
    <?php
}, 20);

/**
 * [brt_purchase_cta] — the closing purchase block.
 *
 * When the Gumroad destination is configured it renders the buy button and the
 * delivery reassurances. When it is not, it does NOT render a dead button and
 * does NOT leave the page with no next step: it falls back to the free Starter
 * Kit, so a visitor who has just read the whole argument still has somewhere to
 * go. A landing page that ends in nothing wastes the traffic that got there.
 */
add_shortcode('brt_purchase_cta', static function ($atts): string {
    $atts = shortcode_atts(['label' => 'Purchase on Gumroad'], is_array($atts) ? $atts : [], 'brt_purchase_cta');

    if (brijraj_cta_is_live('core')) {
        return '<div class="brt-purchase">'
            . '<div class="brt-btns brt-btns--center">'
            . brijraj_cta('core', ['label' => (string) $atts['label'], 'location' => 'final'])
            . '</div>'
            . '<p class="brt-purchase__note">Instant digital delivery. No subscriptions. No software to install.</p>'
            . '</div>';
    }

    $notice = current_user_can('edit_posts')
        ? '<p class="brt-purchase__note"><span class="brt-todo">Gumroad URL not set — Settings &rarr; CTA Destinations</span></p>'
        : '';

    return '<div class="brt-purchase">'
        . '<p class="brt-purchase__soon">The AI Workflow System is being released shortly.</p>'
        . '<div class="brt-btns brt-btns--center">'
        . brijraj_cta('starter', ['label' => 'Get the Free Starter Kit meanwhile', 'location' => 'final'])
        . '</div>'
        . '<p class="brt-purchase__note">Take the free Starter Kit now and you will hear the moment the full system is available.</p>'
        . $notice
        . '</div>';
});

/* -------------------------------------------------------------------------
 * Sticky purchase bar
 * ---------------------------------------------------------------------- */

/**
 * A persistent buy bar on the product page.
 *
 * The landing page deliberately makes its argument before asking for money,
 * which means a visitor who is convinced at section 3 would otherwise have to
 * scroll to the bottom to act. This keeps the decision one tap away at every
 * point after the hero, without another full-width CTA slab interrupting the
 * reading.
 *
 * Appears only once the hero has scrolled out of view, so it never competes
 * with the hero's own CTA.
 */
add_action('wp_footer', static function (): void {
    if (! is_page('ai-workflow-system') || ! brijraj_cta_is_live('core')) {
        return;
    }
    ?>
<div class="brt-stickybuy" id="brt-stickybuy" hidden>
    <div class="brt-stickybuy__inner">
        <div class="brt-stickybuy__meta">
            <span class="brt-stickybuy__name">AI Workflow System</span>
            <span class="brt-stickybuy__price">
                <strong>$39</strong>
                <s>$59</s>
                <span class="brt-stickybuy__note">launch price</span>
            </span>
        </div>
        <?php echo brijraj_cta('core', ['label' => 'Get Instant Access', 'class' => 'brt-stickybuy__btn', 'location' => 'sticky']); ?>
    </div>
</div>
<script>
(function () {
  var bar = document.getElementById('brt-stickybuy');
  var hero = document.querySelector('.brt-hero');
  if (!bar || !hero || !('IntersectionObserver' in window)) { return; }

  var shown = false;
  new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      // Reveal once the hero (and its own CTA) has left the viewport.
      var show = !e.isIntersecting;
      bar.hidden = !show;
      bar.classList.toggle('is-visible', show);
      document.body.classList.toggle('has-stickybuy', show);
      if (show && !shown && typeof window.gtag === 'function') {
        shown = true;
        window.gtag('event', 'sticky_buy_view', { page_url: window.location.href });
      }
    });
  }, { threshold: 0 }).observe(hero);
})();
</script>
    <?php
}, 22);

/**
 * [brt_navcta] — the persistent header CTA.
 *
 * Navy rather than the accent blue: this button is present on every page, and a
 * loud accent repeated in the chrome would compete with the in-page CTAs that
 * are actually doing the selling. Hides itself entirely when no destination is
 * configured, so the header never carries a dead control.
 */
add_shortcode('brt_navcta', static function (): string {
    if (! brijraj_cta_is_live('core')) {
        return '';
    }

    return sprintf(
        '<a class="brt-navcta" href="%s" data-cta="nav_core" data-cta-location="nav" target="_blank" rel="noopener noreferrer">%s</a>',
        esc_url(brijraj_cta_url('core')),
        esc_html__('Get Instant Access', 'brijraj')
    );
});
