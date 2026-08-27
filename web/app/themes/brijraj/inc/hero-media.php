<?php
/**
 * Hero media: the "chaos to clarity" workflow animation.
 *
 * The asset is authored outside WordPress and dropped into the Media Library,
 * so this holds no video of its own — just the plumbing to display whatever is
 * configured, correctly, and to degrade sensibly when nothing is.
 *
 * Three states, in priority order:
 *   1. Video configured  → autoplaying muted loop with a poster frame
 *   2. Poster only       → static image
 *   3. Nothing           → CSS-drawn placeholder so the hero never looks broken
 *
 * Reduced motion is handled properly: users who ask for less motion get the
 * poster frame and a play button, never an autoplaying loop. CSS alone cannot
 * stop a video autoplaying, so there is a small inline script for it.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The hero media settings, registered alongside the CTA destinations.
 *
 * @return array<string, array{label:string, help:string}>
 */
function brijraj_hero_media_fields(): array
{
    return [
        'hero_video' => [
            'label' => 'Hero animation — MP4 URL',
            'help'  => 'Upload the MP4 to Media Library, then paste its URL. H.264, muted, seamless loop, ideally under 2 MB. Leave blank to show the poster image alone.',
        ],
        'hero_poster' => [
            'label' => 'Hero poster / still frame',
            'help'  => 'A still frame from the animation. Shown before the video loads, and instead of it for visitors who prefer reduced motion. Strongly recommended — this is what the Largest Contentful Paint measures.',
        ],
        'hero_webm' => [
            'label' => 'Hero animation — WebM URL (optional)',
            'help'  => 'Optional smaller alternative served to browsers that support it. MP4 alone is fine.',
        ],
        'hero_alt' => [
            'label' => 'Hero animation — description',
            'help'  => 'Plain-language description of what the animation shows, for screen readers and for anyone the video fails to load for.',
        ],
    ];
}

function brijraj_hero_option(string $key): string
{
    return trim((string) get_option('brijraj_' . sanitize_key($key), ''));
}

/**
 * The CSS-only workflow visualization.
 *
 * Scattered inputs arrive, the workflow engine works them, management-ready
 * outputs appear — on a 12-second loop driven entirely by CSS keyframes. No
 * JavaScript, no images, no gradients, and it holds a readable finished state
 * for anyone who has asked for reduced motion.
 *
 * Exposed as a whole to assistive tech via a single role="img" with a
 * description, because animating fragments would otherwise be announced as
 * meaningless text churn.
 */
function brijraj_hero_flowviz(string $alt): string
{
    $inputs = ['Jira updates', 'Meeting notes', 'Risk logs', 'Status reports'];
    $steps  = ['Extract', 'Structure', 'Prioritize', 'Draft'];
    $out    = ['Weekly report', 'Executive update', 'Action items', 'Risk intelligence'];

    ob_start();
    ?>
    <div class="brt-hero-media brt-hero-media--viz">
        <div class="brt-flowviz" role="img" aria-label="<?php echo esc_attr($alt); ?>">

            <div class="brt-flowviz__stage brt-flowviz__stage--in">
                <span class="brt-flowviz__label" aria-hidden="true">Input</span>
                <div class="brt-flowviz__cards" aria-hidden="true">
                    <?php foreach ($inputs as $i => $label) : ?>
                        <span class="brt-flowviz__card" style="--i:<?php echo (int) $i; ?>"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <span class="brt-flowviz__arrow" aria-hidden="true"></span>

            <div class="brt-flowviz__engine" aria-hidden="true">
                <span class="brt-flowviz__label">AI workflow system</span>
                <div class="brt-flowviz__steps">
                    <?php foreach ($steps as $i => $label) : ?>
                        <span class="brt-flowviz__step" style="--i:<?php echo (int) $i; ?>"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

            <span class="brt-flowviz__arrow brt-flowviz__arrow--two" aria-hidden="true"></span>

            <div class="brt-flowviz__stage brt-flowviz__stage--out">
                <span class="brt-flowviz__label" aria-hidden="true">Management-ready output</span>
                <div class="brt-flowviz__cards" aria-hidden="true">
                    <?php foreach ($out as $i => $label) : ?>
                        <span class="brt-flowviz__card brt-flowviz__card--out" style="--i:<?php echo (int) $i; ?>"><?php echo esc_html($label); ?></span>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
    </div>
    <?php

    return (string) ob_get_clean();
}

/**
 * Launch pricing badge for the hero.
 */
function brijraj_price_badge(): string
{
    return '<p class="brt-pricebadge">'
        . '<span class="brt-pricebadge__now">$39 launch price</span>'
        . '<span class="brt-pricebadge__was">$59</span>'
        . '<span class="brt-pricebadge__note">one payment</span>'
        . '</p>';
}

add_shortcode('brt_price_badge', static fn (): string => brijraj_price_badge());

/**
 * Render the hero media block.
 */
function brijraj_hero_media(): string
{
    $video  = brijraj_hero_option('hero_video');
    $webm   = brijraj_hero_option('hero_webm');
    $poster = brijraj_hero_option('hero_poster');
    $alt    = brijraj_hero_option('hero_alt');

    if ($alt === '') {
        $alt = 'Scattered project information — Jira updates, meeting notes, messages and risk logs — flowing through an AI workflow and a human review step, and coming out as a management-ready status report.';
    }

    // State 3: no video supplied — render the CSS workflow animation.
    // This is a real hero visual, not a placeholder: it tells the input →
    // workflow → output story with no JavaScript and no image payload.
    if ($video === '' && $poster === '') {
        return brijraj_hero_flowviz($alt);
    }

    // State 2: poster only.
    if ($video === '') {
        return sprintf(
            '<div class="brt-hero-media"><img class="brt-hero-media__el" src="%s" alt="%s" width="1200" height="900" fetchpriority="high" decoding="async"></div>',
            esc_url($poster),
            esc_attr($alt)
        );
    }

    // State 1: video, with poster and reduced-motion handling.
    $sources = '';

    if ($webm !== '') {
        $sources .= sprintf('<source src="%s" type="video/webm">', esc_url($webm));
    }

    $sources .= sprintf('<source src="%s" type="video/mp4">', esc_url($video));

    ob_start();
    ?>
    <div class="brt-hero-media" data-hero-media>
        <video class="brt-hero-media__el"
               autoplay muted loop playsinline
               preload="metadata"
               width="1200" height="900"
               <?php if ($poster !== '') : ?>poster="<?php echo esc_url($poster); ?>"<?php endif; ?>
               aria-label="<?php echo esc_attr($alt); ?>">
            <?php echo $sources; // phpcs:ignore WordPress.Security.EscapeOutput -- built from esc_url above. ?>
            <?php if ($poster !== '') : ?>
                <img src="<?php echo esc_url($poster); ?>" alt="<?php echo esc_attr($alt); ?>" width="1200" height="900">
            <?php endif; ?>
        </video>
        <button type="button" class="brt-hero-media__toggle" hidden aria-pressed="false">
            <span class="brt-hero-media__toggle-play">Play animation</span>
            <span class="brt-hero-media__toggle-pause">Pause animation</span>
        </button>
    </div>
    <script>
    (function () {
      var wrap = document.currentScript.closest('[data-hero-media]');
      if (!wrap) { return; }
      var vid = wrap.querySelector('video');
      var btn = wrap.querySelector('.brt-hero-media__toggle');
      if (!vid || !btn) { return; }

      var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)');

      function setPaused(paused) {
        if (paused) { vid.pause(); } else { vid.play().catch(function(){}); }
        btn.setAttribute('aria-pressed', paused ? 'false' : 'true');
        wrap.classList.toggle('is-paused', paused);
      }

      // Honour the OS-level preference: no autoplay, show the poster, offer a play control.
      if (reduce && reduce.matches) {
        vid.removeAttribute('autoplay');
        setPaused(true);
      }

      btn.hidden = false;
      btn.addEventListener('click', function () {
        setPaused(!vid.paused ? true : false);
      });

      // Do not burn CPU animating a hero nobody is looking at.
      if ('IntersectionObserver' in window) {
        new IntersectionObserver(function (entries) {
          entries.forEach(function (e) {
            if (reduce && reduce.matches) { return; }
            if (e.isIntersecting) { vid.play().catch(function(){}); }
            else { vid.pause(); }
          });
        }, { threshold: 0.1 }).observe(vid);
      }
    })();
    </script>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('brt_hero_media', static fn (): string => brijraj_hero_media());

/**
 * Register the settings next to the CTA destinations.
 */
add_action('admin_init', static function (): void {
    foreach (array_keys(brijraj_hero_media_fields()) as $key) {
        register_setting('brijraj_cta', 'brijraj_' . $key, [
            'type'              => 'string',
            'sanitize_callback' => static function ($value) use ($key): string {
                $value = trim((string) $value);

                if ($value === '') {
                    return '';
                }

                return $key === 'hero_alt'
                    ? sanitize_text_field($value)
                    : esc_url_raw($value, ['http', 'https']);
            },
            'default'           => '',
            'show_in_rest'      => false,
        ]);
    }
});

/**
 * [brt_flowviz] — the transformation diagram on its own.
 *
 * The homepage hero can be swapped for a video; the product page should always
 * show the diagram, because there the transformation *is* the argument.
 */
add_shortcode('brt_flowviz', static function (): string {
    return brijraj_hero_flowviz(
        'Scattered project information — Jira updates, meeting notes, risk logs and status reports — passing through the AI workflow system and coming out as status reports, executive updates, action items and risk intelligence.'
    );
});
