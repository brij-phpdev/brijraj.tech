<?php
/**
 * Lead capture: the free AI Project Delivery Starter Kit.
 *
 * A second, deliberately lighter funnel than the challenge form in forms.php.
 * That one is long-form customer research; this one asks for a first name and
 * an email and gets out of the way, because it sits inline on pages whose main
 * job is something else.
 *
 * Reuses the existing plumbing rather than duplicating it:
 *   - brijraj_field_name()      the brtf_ prefix that avoids WP query vars
 *   - brijraj_notification_email()
 *   - the .brt-form CSS already in style.css
 *
 * Integration seam: `brijraj_lead_captured` fires after a lead is stored, so
 * Brevo / MailerLite / ConvertKit / n8n can be wired in later without touching
 * the form, the storage, or the markup.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const BRIJRAJ_SUBSCRIBER_CPT = 'brt_subscriber';

/** Max submissions per IP per hour before we start refusing. */
const BRIJRAJ_LEAD_RATE_LIMIT = 5;

/**
 * Where the lead magnet lives. Deliberately an option, never hardcoded — the
 * file does not exist yet and a broken download is worse than an honest
 * "on its way" message.
 */
function brijraj_starter_kit_url(): string
{
    return trim((string) get_option('brijraj_starter_kit_file', ''));
}

/* -------------------------------------------------------------------------
 * Storage
 * ---------------------------------------------------------------------- */

add_action('init', static function (): void {
    register_post_type(BRIJRAJ_SUBSCRIBER_CPT, [
        'labels' => [
            'name'          => __('Subscribers', 'brijraj'),
            'singular_name' => __('Subscriber', 'brijraj'),
            'menu_name'     => __('Subscribers', 'brijraj'),
            'all_items'     => __('All Subscribers', 'brijraj'),
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-email-alt',
        'menu_position'       => 27,
        'capability_type'     => 'post',
        'capabilities'        => ['create_posts' => 'do_not_allow'],
        'map_meta_cap'        => true,
        'supports'            => ['title'],
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
    ]);
});

/* -------------------------------------------------------------------------
 * The form
 * ---------------------------------------------------------------------- */

/**
 * Human-readable names for the placements, used in the admin list and in the
 * GA4 payload so we can tell which surface actually converts.
 *
 * @return array<string, string>
 */
function brijraj_lead_locations(): array
{
    return [
        'hero'     => 'Homepage hero',
        'blog'     => 'Blog article footer',
        'product'  => 'Product page',
        'landing'  => 'Starter Kit landing page',
        'exit'     => 'Exit intent',
        'inline'   => 'Inline',
    ];
}

/**
 * Render the lead capture component.
 *
 * @param array<string, string> $args location, style (panel|inline), headline, sub
 */
function brijraj_lead_form(array $args = []): string
{
    static $instance = 0;
    $instance++;

    $locations = brijraj_lead_locations();
    $location  = (string) ($args['location'] ?? 'inline');

    if (! isset($locations[$location])) {
        $location = 'inline';
    }

    $style    = ($args['style'] ?? 'panel') === 'inline' ? 'inline' : 'panel';
    $headline = (string) ($args['headline'] ?? 'Turn repetitive project work into repeatable AI workflows.');
    $sub      = (string) ($args['sub'] ?? 'Download the free AI Project Delivery Starter Kit — practical templates to improve reporting, meetings, and project communication.');

    $id      = 'brt-lead-' . $instance;
    $state   = $GLOBALS['brijraj_lead_state'] ?? null;
    $mine    = is_array($state) && ($state['location'] ?? '') === $location;
    $success = $mine && ! empty($state['success']);
    $errors  = $mine ? (array) ($state['errors'] ?? []) : [];
    $old     = $mine ? (array) ($state['values'] ?? []) : [];

    ob_start();
    ?>
    <div class="brt-lead brt-lead--<?php echo esc_attr($style); ?>"
         id="<?php echo esc_attr($id); ?>"
         data-lead-form
         data-lead-location="<?php echo esc_attr($location); ?>">

        <?php if ($success) : ?>
            <div class="brt-lead__done" role="status" data-lead-success>
                <h3><?php echo brijraj_starter_kit_url() !== '' ? 'Your AI Project Delivery Starter Kit is on its way. Check your inbox.' : 'Thank you — your Starter Kit is on its way.'; ?></h3>
                <?php if (brijraj_starter_kit_url() !== '') : ?>
                    <p>You can also download it straight away:</p>
                    <p>
                        <a class="brt-btn brt-btn--primary" data-cta="starter_download"
                           href="<?php echo esc_url(brijraj_starter_kit_url()); ?>" download>
                            Download the Starter Kit
                        </a>
                    </p>
                <?php else : ?>
                    <p>If it has not arrived within a few minutes, check your spam folder or email
                       <a href="mailto:<?php echo esc_attr(brijraj_notification_email()); ?>"><?php echo esc_html(brijraj_notification_email()); ?></a>.</p>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <div class="brt-lead__copy">
                <h3 class="brt-lead__headline"><?php echo esc_html($headline); ?></h3>
                <p class="brt-lead__sub"><?php echo esc_html($sub); ?></p>
            </div>

            <form class="brt-form brt-lead__form" method="post" action="#<?php echo esc_attr($id); ?>" novalidate>
                <?php wp_nonce_field('brijraj_lead', 'brijraj_lead_nonce'); ?>
                <input type="hidden" name="brijraj_form" value="lead">
                <input type="hidden" name="brijraj_t" value="<?php echo esc_attr((string) time()); ?>">
                <input type="hidden" name="<?php echo esc_attr(brijraj_field_name('location')); ?>" value="<?php echo esc_attr($location); ?>">
                <input type="hidden" name="<?php echo esc_attr(brijraj_field_name('source_url')); ?>" value="<?php echo esc_attr(home_url(add_query_arg([], (string) ($_SERVER['REQUEST_URI'] ?? '/')))); ?>">

                <?php if ($errors !== []) : ?>
                    <div class="brt-form__errors" role="alert">
                        <ul style="margin:0;padding-left:1.1rem">
                            <?php foreach ($errors as $e) : ?>
                                <li><?php echo esc_html($e); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <div class="brt-lead__fields">
                    <div class="brt-form__row">
                        <label class="brt-form__label" for="<?php echo esc_attr($id); ?>-first">First name</label>
                        <input class="brt-form__input" type="text" autocomplete="given-name" required aria-required="true"
                               id="<?php echo esc_attr($id); ?>-first"
                               name="<?php echo esc_attr(brijraj_field_name('first_name')); ?>"
                               value="<?php echo esc_attr((string) ($old['first_name'] ?? '')); ?>">
                    </div>
                    <div class="brt-form__row">
                        <label class="brt-form__label" for="<?php echo esc_attr($id); ?>-email">Email address</label>
                        <input class="brt-form__input" type="email" autocomplete="email" required aria-required="true"
                               id="<?php echo esc_attr($id); ?>-email"
                               name="<?php echo esc_attr(brijraj_field_name('email')); ?>"
                               value="<?php echo esc_attr((string) ($old['email'] ?? '')); ?>">
                    </div>
                </div>

                <div class="brt-form__row brt-form__row--consent">
                    <label class="brt-form__consent" for="<?php echo esc_attr($id); ?>-consent">
                        <input type="checkbox" required aria-required="true"
                               id="<?php echo esc_attr($id); ?>-consent"
                               name="<?php echo esc_attr(brijraj_field_name('consent')); ?>" value="1"
                               <?php checked(! empty($old['consent'])); ?>>
                        <span>I agree to receive practical AI workflow resources and product updates from BrijRaj.Tech. I can unsubscribe anytime.</span>
                    </label>
                </div>

                <div class="brt-hp" aria-hidden="true">
                    <label for="<?php echo esc_attr($id); ?>-site">Website</label>
                    <input type="text" tabindex="-1" autocomplete="off"
                           id="<?php echo esc_attr($id); ?>-site"
                           name="<?php echo esc_attr(brijraj_field_name('website')); ?>">
                </div>

                <button type="submit" class="brt-btn brt-btn--primary brt-lead__submit" data-cta="starter_kit_submit">
                    Get the Free Starter Kit
                </button>

                <p class="brt-form__privacy">
                    No spam. Unsubscribe in one click. See the <a href="/privacy-policy/">Privacy Policy</a>.
                </p>
            </form>
        <?php endif; ?>
    </div>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('brt_lead_form', static function ($atts): string {
    $atts = shortcode_atts(
        ['location' => 'inline', 'style' => 'panel', 'headline' => '', 'sub' => ''],
        is_array($atts) ? $atts : [],
        'brt_lead_form'
    );

    $args = ['location' => $atts['location'], 'style' => $atts['style']];

    if ($atts['headline'] !== '') {
        $args['headline'] = $atts['headline'];
    }

    if ($atts['sub'] !== '') {
        $args['sub'] = $atts['sub'];
    }

    return brijraj_lead_form($args);
});

/**
 * [brt_starter_delivered] — the body of the Starter Kit confirmation page.
 *
 * The download link and the delivery event used to live in the inline success
 * panel. That panel no longer renders now the form redirects, so both moved
 * here; without this the lead_download event would simply have stopped firing
 * and the funnel would look broken in GA4 rather than in the page.
 */
add_shortcode('brt_starter_delivered', static function (): string {
    $kit  = brijraj_starter_kit_url();
    $from = isset($_GET['from']) ? sanitize_key((string) $_GET['from']) : '';

    ob_start();
    ?>
<div class="brt-lead__done" role="status" data-lead-delivered data-from="<?php echo esc_attr($from); ?>">
    <?php if ($kit !== '') : ?>
        <p>You can download it straight away:</p>
        <p>
            <a class="brt-btn brt-btn--primary" data-cta="starter_download"
               href="<?php echo esc_url($kit); ?>" download>Download the Starter Kit</a>
        </p>
        <p class="brt-audit__aside">A copy is in your inbox too, so you can come back to it later.</p>
    <?php else : ?>
        <p class="brt-audit__aside">I send this one personally rather than automatically, so it will land
        shortly. If it has not arrived within a few hours, reply to that email and I will chase it.</p>
    <?php endif; ?>
</div>

<script>
(function () {
  var el = document.querySelector('[data-lead-delivered]');
  if (!el || typeof window.gtag !== 'function') { return; }
  window.gtag('event', 'lead_download', {
    stage: 'delivered',
    lead_location: el.getAttribute('data-from') || 'unknown'
  });
})();
</script>
    <?php

    return (string) ob_get_clean();
});

/* -------------------------------------------------------------------------
 * Submission
 * ---------------------------------------------------------------------- */

/**
 * Rate limit key. The IP is hashed with a site-specific salt and never stored
 * on the lead record — it exists only to throttle, so there is no reason to
 * keep it in readable form.
 */
function brijraj_lead_rate_key(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return 'brt_lead_rl_' . substr(hash_hmac('sha256', $ip, (string) wp_salt('nonce')), 0, 24);
}

add_action('template_redirect', static function (): void {
    if (($_POST['brijraj_form'] ?? '') !== 'lead') {
        return;
    }

    $location = sanitize_key(wp_unslash((string) ($_POST[brijraj_field_name('location')] ?? 'inline')));
    $fail = static function (array $errors, array $values = []) use ($location): void {
        $GLOBALS['brijraj_lead_state'] = ['location' => $location, 'errors' => $errors, 'values' => $values];
    };
    // Post-redirect-get, so a refresh cannot resubmit and the visitor ends on
    // a page that is unambiguously a "done" state. The location is carried
    // through so the confirmation can still report where the request came
    // from and the analytics keep their attribution.
    $pass = static function (?callable $after = null) use ($location): void {
        brijraj_redirect_then(
            add_query_arg('from', $location, home_url('/resources/starter-kit/received/')),
            $after
        );
    };

    // Nonce.
    if (! isset($_POST['brijraj_lead_nonce'])
        || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['brijraj_lead_nonce'])), 'brijraj_lead')) {
        $fail(['That form had expired. Please try again.']);
        return;
    }

    // Honeypot and time trap — silent success so bots get no feedback loop.
    if (trim((string) ($_POST[brijraj_field_name('website')] ?? '')) !== '') {
        $pass();
        return;
    }

    $started = (int) ($_POST['brijraj_t'] ?? 0);
    if ($started > 0 && (time() - $started) < 2) {
        $pass();
        return;
    }

    // Rate limit.
    $key   = brijraj_lead_rate_key();
    $count = (int) get_transient($key);

    if ($count >= BRIJRAJ_LEAD_RATE_LIMIT) {
        $fail(['Too many submissions from this connection. Please try again later, or email ' . brijraj_notification_email() . '.']);
        return;
    }

    $first  = sanitize_text_field(wp_unslash((string) ($_POST[brijraj_field_name('first_name')] ?? '')));
    $email  = sanitize_email(wp_unslash((string) ($_POST[brijraj_field_name('email')] ?? '')));
    $consent = ! empty($_POST[brijraj_field_name('consent')]);
    $source  = esc_url_raw(wp_unslash((string) ($_POST[brijraj_field_name('source_url')] ?? '')));

    $errors = [];

    if ($first === '') {
        $errors[] = 'Please tell me your first name.';
    }

    if ($email === '' || ! is_email($email)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if (! $consent) {
        $errors[] = 'Please tick the consent box so I can send you the Starter Kit.';
    }

    if ($errors !== []) {
        $fail($errors, ['first_name' => $first, 'email' => $email, 'consent' => $consent]);
        return;
    }

    set_transient($key, $count + 1, HOUR_IN_SECONDS);

    // Deduplicate: one record per address, updated rather than repeated.
    $existing = get_posts([
        'post_type'      => BRIJRAJ_SUBSCRIBER_CPT,
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'meta_key'       => '_brt_email',
        'meta_value'     => $email,
        'post_status'    => 'any',
    ]);

    $locations = brijraj_lead_locations();
    $title     = sprintf('%s <%s>', $first !== '' ? $first : 'Subscriber', $email);

    if ($existing !== []) {
        $post_id = (int) $existing[0];
        wp_update_post(['ID' => $post_id, 'post_title' => $title]);
        update_post_meta($post_id, '_brt_repeat_count', (int) get_post_meta($post_id, '_brt_repeat_count', true) + 1);
    } else {
        $post_id = (int) wp_insert_post([
            'post_type'   => BRIJRAJ_SUBSCRIBER_CPT,
            'post_title'  => $title,
            'post_status' => 'publish',
        ]);
    }

    if ($post_id > 0) {
        update_post_meta($post_id, '_brt_first_name', $first);
        update_post_meta($post_id, '_brt_email', $email);
        update_post_meta($post_id, '_brt_consent', $consent ? '1' : '0');
        update_post_meta($post_id, '_brt_location', $location);
        update_post_meta($post_id, '_brt_location_label', $locations[$location] ?? $location);
        update_post_meta($post_id, '_brt_source_url', $source);
        update_post_meta($post_id, '_brt_captured_at', current_time('mysql'));

        /**
         * Fires after a Starter Kit lead is captured.
         *
         * The integration seam for Brevo / MailerLite / ConvertKit / n8n.
         * Subscribe the address here; do not modify the form.
         *
         * @param array<string, mixed> $lead    first_name, email, consent, location, source_url.
         * @param int                  $post_id Stored subscriber ID.
         */
        do_action('brijraj_lead_captured', [
            'first_name' => $first,
            'email'      => $email,
            'consent'    => $consent,
            'location'   => $location,
            'source_url' => $source,
        ], $post_id);
    }

    // Deliver to the subscriber FIRST. They were told to check their inbox, so
    // something has to actually arrive; a notification to the site owner is not
    // delivery. When no file is configured this says so honestly rather than
    // leaving the promise hanging.
    $kit  = brijraj_starter_kit_url();
    $site = get_bloginfo('name');

    if ($kit !== '') {
        $body = [
            'Hi ' . $first . ',',
            '',
            'Here is your AI Project Delivery Starter Kit:',
            $kit,
            '',
            'It contains one complete workflow - Meeting to Action - plus a',
            '10-minute audit to find the repetitive work in your week. Run it on a',
            'real meeting and you will know quickly whether the approach suits you.',
            '',
            'If anything does not open, just reply to this email.',
        ];
        $subject = 'Your AI Project Delivery Starter Kit';
    } else {
        $body = [
            'Hi ' . $first . ',',
            '',
            'Thanks for requesting the AI Project Delivery Starter Kit.',
            '',
            'I am sending it to you personally rather than automatically, so it',
            'will land in your inbox shortly. If it has not arrived within a few',
            'hours, reply here and I will chase it.',
        ];
        $subject = 'Your Starter Kit is on its way';
    }

    $from_label = $locations[$location] ?? $location;

    // Redirect first; both messages go out once the visitor has already landed
    // on the confirmation page. The subscriber record is stored above, so
    // nothing here can cost a lead.
    $pass(static function () use ($email, $subject, $body, $site, $first, $from_label, $source): void {
        wp_mail(
            $email,
            $subject,
            implode("\n", $body),
            ['Reply-To: ' . brijraj_notification_email(), 'From: ' . $site . ' <' . brijraj_notification_email() . '>']
        );

        wp_mail(
            brijraj_notification_email(),
            sprintf('[Starter Kit] %s', $first),
            implode("\n", [
                'New Starter Kit request.',
                '',
                'Name:     ' . $first,
                'Email:    ' . $email,
                'From:     ' . $from_label,
                'Page:     ' . $source,
                'Consent:  yes',
                'Received: ' . current_time('mysql'),
            ]),
            ['Reply-To: ' . $first . ' <' . $email . '>']
        );
    });
});

/* -------------------------------------------------------------------------
 * Admin: columns and CSV export
 * ---------------------------------------------------------------------- */

add_filter('manage_' . BRIJRAJ_SUBSCRIBER_CPT . '_posts_columns', static function (array $cols): array {
    return [
        'cb'           => $cols['cb'] ?? '',
        'title'        => __('Subscriber', 'brijraj'),
        'brt_email'    => __('Email', 'brijraj'),
        'brt_location' => __('Captured from', 'brijraj'),
        'brt_source'   => __('Page', 'brijraj'),
        'date'         => __('Date', 'brijraj'),
    ];
});

add_action('manage_' . BRIJRAJ_SUBSCRIBER_CPT . '_posts_custom_column', static function (string $col, int $post_id): void {
    if ($col === 'brt_email') {
        $v = (string) get_post_meta($post_id, '_brt_email', true);
        printf('<a href="mailto:%s">%s</a>', esc_attr($v), esc_html($v));
        return;
    }

    if ($col === 'brt_location') {
        echo esc_html((string) get_post_meta($post_id, '_brt_location_label', true));
        return;
    }

    if ($col === 'brt_source') {
        $v = (string) get_post_meta($post_id, '_brt_source_url', true);
        if ($v !== '') {
            printf('<a href="%s" target="_blank" rel="noopener">%s</a>', esc_url($v), esc_html(wp_parse_url($v, PHP_URL_PATH) ?: $v));
        }
    }
}, 10, 2);

/** Export button above the list table. */
add_action('restrict_manage_posts', static function (string $post_type): void {
    if ($post_type !== BRIJRAJ_SUBSCRIBER_CPT || ! current_user_can('manage_options')) {
        return;
    }

    $url = wp_nonce_url(admin_url('admin-post.php?action=brijraj_export_subscribers'), 'brijraj_export', 'brt_export_nonce');
    printf('<a href="%s" class="button">%s</a>', esc_url($url), esc_html__('Export CSV', 'brijraj'));
});

add_action('admin_post_brijraj_export_subscribers', static function (): void {
    if (! current_user_can('manage_options')) {
        wp_die(esc_html__('You do not have permission to export subscribers.', 'brijraj'), '', ['response' => 403]);
    }

    if (! isset($_GET['brt_export_nonce'])
        || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_GET['brt_export_nonce'])), 'brijraj_export')) {
        wp_die(esc_html__('That export link has expired.', 'brijraj'), '', ['response' => 403]);
    }

    $rows = get_posts([
        'post_type'      => BRIJRAJ_SUBSCRIBER_CPT,
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'orderby'        => 'date',
        'order'          => 'DESC',
    ]);

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=brijraj-subscribers-' . gmdate('Y-m-d') . '.csv');

    $out = fopen('php://output', 'w');
    $columns = apply_filters(
        'brijraj_subscriber_export_columns',
        ['First name', 'Email', 'Consent', 'Captured from', 'Page', 'Captured at', 'Repeat submissions']
    );
    fputcsv($out, $columns);

    foreach ($rows as $p) {
        $row = [
            (string) get_post_meta($p->ID, '_brt_first_name', true),
            (string) get_post_meta($p->ID, '_brt_email', true),
            get_post_meta($p->ID, '_brt_consent', true) === '1' ? 'yes' : 'no',
            (string) get_post_meta($p->ID, '_brt_location_label', true),
            (string) get_post_meta($p->ID, '_brt_source_url', true),
            (string) get_post_meta($p->ID, '_brt_captured_at', true),
            (string) (get_post_meta($p->ID, '_brt_repeat_count', true) ?: '0'),
        ];

        fputcsv($out, apply_filters('brijraj_subscriber_export_row', $row, (int) $p->ID));
    }

    fclose($out);
    exit;
});

/* -------------------------------------------------------------------------
 * Settings: the Starter Kit file
 * ---------------------------------------------------------------------- */

add_action('admin_init', static function (): void {
    register_setting('brijraj_cta', 'brijraj_starter_kit_file', [
        'type'              => 'string',
        'sanitize_callback' => static fn ($v): string => esc_url_raw(trim((string) $v), ['http', 'https']),
        'default'           => '',
        'show_in_rest'      => false,
    ]);
});

/* -------------------------------------------------------------------------
 * GA4 events
 * ---------------------------------------------------------------------- */

add_action('wp_footer', static function (): void {
    if (is_admin()) {
        return;
    }
    ?>
<script>
(function () {
  if (!document.querySelector('[data-lead-form]')) { return; }

  function send(name, form, extra) {
    if (typeof window.gtag !== 'function') { return; }
    window.gtag('event', name, Object.assign({
      form_location: form ? form.getAttribute('data-lead-location') : 'unknown',
      page_url: window.location.href,
      event_timestamp: new Date().toISOString()
    }, extra || {}));
  }

  // lead_form_view — fire once per form when it first enters the viewport.
  var seen = new WeakSet();
  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function (entries) {
      entries.forEach(function (e) {
        if (e.isIntersecting && !seen.has(e.target)) {
          seen.add(e.target);
          send('lead_form_view', e.target);
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.4 });
    document.querySelectorAll('[data-lead-form]').forEach(function (f) { io.observe(f); });
  }

  // lead_form_start — first interaction with any field, once per form.
  var started = new WeakSet();
  document.addEventListener('focusin', function (e) {
    var form = e.target.closest && e.target.closest('[data-lead-form]');
    if (!form || started.has(form)) { return; }
    if (!e.target.matches('input, textarea')) { return; }
    started.add(form);
    send('lead_form_start', form);
  }, { passive: true });

  // lead_form_submit
  document.addEventListener('submit', function (e) {
    var form = e.target.closest && e.target.closest('[data-lead-form]');
    if (form) { send('lead_form_submit', form); }
  }, { passive: true });

  // lead_download — the success panel rendered, or the download link clicked.
  document.querySelectorAll('[data-lead-success]').forEach(function (el) {
    send('lead_download', el.closest('[data-lead-form]'), { stage: 'delivered' });
  });
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('[data-cta="starter_download"]');
    if (a) { send('lead_download', a.closest('[data-lead-form]'), { stage: 'clicked' }); }
  }, { passive: true });
})();
</script>
    <?php
}, 21);

/* -------------------------------------------------------------------------
 * Legacy URL consolidation
 * ---------------------------------------------------------------------- */

/*
 * The /starter-kit/ redirect that used to live here has moved to
 * inc/redirects.php, which now owns every moved URL on the site. Leaving it
 * here would have produced a two-hop chain: /starter-kit/ to the old landing
 * page, and only then to its home under /resources/.
 */

/**
 * Append the capture panel to the foot of every blog article.
 *
 * Done with a filter rather than by editing each post so new articles get it
 * automatically and the copy stays in one place. Uses the quieter inline style:
 * a navy slab at the end of a long read is too loud.
 */
add_filter('the_content', static function (string $content): string {
    if (! is_singular('post') || ! in_the_loop() || ! is_main_query()) {
        return $content;
    }

    if (post_password_required()) {
        return $content;
    }

    return $content . brijraj_lead_form([
        'location' => 'blog',
        'style'    => 'inline',
        'headline' => 'Turn repetitive project work into repeatable AI workflows.',
        'sub'      => 'Download the free AI Project Delivery Starter Kit — practical templates to improve reporting, meetings, and project communication.',
    ]);
}, 20);
