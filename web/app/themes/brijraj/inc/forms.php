<?php
/**
 * Lead capture: the "Have a Project Workflow Challenge?" form.
 *
 * Submissions are customer research, not generic contact messages, so they are
 * stored as a private custom post type that can be read, searched and exported
 * from wp-admin — not fired into an inbox and lost.
 *
 * No form plugin: this is one form with a known shape, and a plugin would add a
 * dependency plus a settings surface we do not need. Spam handling is a
 * honeypot + a time-trap + a nonce, which is proportionate for a low-traffic
 * B2B form and costs the visitor nothing (no CAPTCHA, no third-party script).
 *
 * An ESP can be added later without touching the form: hook
 * `brijraj_lead_submitted` receives the sanitised payload after storage.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const BRIJRAJ_LEAD_CPT = 'brt_lead';

/**
 * Where notifications go. Kept as a filterable helper so it is defined once.
 */
function brijraj_notification_email(): string
{
    return (string) apply_filters('brijraj_notification_email', 'brij@brijraj.tech');
}

/**
 * Register the private lead store.
 */
add_action('init', static function (): void {
    register_post_type(BRIJRAJ_LEAD_CPT, [
        'labels' => [
            'name'          => __('Challenges', 'brijraj'),
            'singular_name' => __('Challenge', 'brijraj'),
            'menu_name'     => __('Challenges', 'brijraj'),
            'all_items'     => __('All Challenges', 'brijraj'),
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-testimonial',
        'menu_position'       => 26,
        'capability_type'     => 'post',
        'capabilities'        => ['create_posts' => 'do_not_allow'],
        'map_meta_cap'        => true,
        'supports'            => ['title'],
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
    ]);
});

/**
 * Prefix for the POSTed input names.
 *
 * Field keys must never be submitted under their bare names: WordPress treats
 * `name`, `s`, `author`, `cat`, `paged`, `order` and friends as public query
 * variables, so a field called `name` makes WP try to resolve a post with that
 * slug and hand back a 404 before any handler runs. Prefixing sidesteps the
 * whole class of collision.
 */
const BRIJRAJ_FIELD_PREFIX = 'brtf_';

/**
 * The POST key for a field.
 */
function brijraj_field_name(string $key): string
{
    return BRIJRAJ_FIELD_PREFIX . $key;
}

/**
 * The fields, defined once and reused by the renderer, the validator and the
 * admin display. Adding a field means editing this array only.
 *
 * @return array<string, array{label:string, type:string, required:bool, help?:string, autocomplete?:string}>
 */
function brijraj_challenge_fields(): array
{
    return [
        'name'     => ['label' => 'Your name',      'type' => 'text',     'required' => true,  'autocomplete' => 'name'],
        'email'    => ['label' => 'Email',          'type' => 'email',    'required' => true,  'autocomplete' => 'email'],
        'role'     => ['label' => 'Your role',      'type' => 'text',     'required' => true,  'help' => 'e.g. Project Manager, PMO Lead, Business Analyst', 'autocomplete' => 'organization-title'],
        'company'  => ['label' => 'Company',        'type' => 'text',     'required' => false, 'help' => 'Optional', 'autocomplete' => 'organization'],
        'mobile'   => ['label' => 'WhatsApp / mobile', 'type' => 'tel',   'required' => false, 'help' => 'Optional — only if you would rather I reply there', 'autocomplete' => 'tel'],
        'solving'  => ['label' => 'What are you trying to solve?', 'type' => 'textarea', 'required' => true],
        'current'  => ['label' => 'What does your current process look like?', 'type' => 'textarea', 'required' => false],
        'better'   => ['label' => 'What would a better outcome look like?',    'type' => 'textarea', 'required' => false],
    ];
}

/**
 * Render the form.
 */
function brijraj_challenge_form(): string
{
    $fields   = brijraj_challenge_fields();
    $errors   = [];
    $old      = [];
    $success  = false;

    // State handed over by the submission handler for this request.
    if (isset($GLOBALS['brijraj_form_state'])) {
        $state   = (array) $GLOBALS['brijraj_form_state'];
        $errors  = (array) ($state['errors'] ?? []);
        $old     = (array) ($state['values'] ?? []);
        $success = (bool) ($state['success'] ?? false);
    }

    ob_start();

    if ($success) {
        ?>
        <div class="brt-form__done" role="status">
            <h3>Thank you — that has reached me.</h3>
            <p>I read every one of these personally. If it is something I can help with, I will reply to the email address you gave, usually within a couple of working days.</p>
            <p class="brt-form__done-note">In the meantime, the free PM AI Starter Kit is the fastest way to see whether this approach fits how you work.</p>
            <?php echo brijraj_cta('starter', ['style' => 'primary']); ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
    ?>
    <form class="brt-form" method="post" action="#challenge-form" id="challenge-form" novalidate>
        <?php wp_nonce_field('brijraj_challenge', 'brijraj_challenge_nonce'); ?>
        <input type="hidden" name="brijraj_form" value="challenge">
        <input type="hidden" name="brijraj_t" value="<?php echo esc_attr((string) time()); ?>">

        <?php if ($errors !== []) : ?>
            <div class="brt-form__errors" role="alert">
                <p><strong>Please check the following:</strong></p>
                <ul>
                    <?php foreach ($errors as $e) : ?>
                        <li><?php echo esc_html($e); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php foreach ($fields as $key => $f) :
            $id  = 'brt_' . $key;
            $val = (string) ($old[$key] ?? ''); ?>
            <div class="brt-form__row">
                <label class="brt-form__label" for="<?php echo esc_attr($id); ?>">
                    <?php echo esc_html($f['label']); ?>
                    <?php if (! $f['required']) : ?><span class="brt-form__opt">optional</span><?php endif; ?>
                </label>
                <?php if (! empty($f['help'])) : ?>
                    <span class="brt-form__help" id="<?php echo esc_attr($id); ?>-help"><?php echo esc_html($f['help']); ?></span>
                <?php endif; ?>

                <?php if ($f['type'] === 'textarea') : ?>
                    <textarea class="brt-form__input" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr(brijraj_field_name($key)); ?>"
                              rows="4" <?php echo $f['required'] ? 'required aria-required="true"' : ''; ?>
                              <?php echo ! empty($f['help']) ? 'aria-describedby="' . esc_attr($id) . '-help"' : ''; ?>><?php echo esc_textarea($val); ?></textarea>
                <?php else : ?>
                    <input class="brt-form__input" id="<?php echo esc_attr($id); ?>" name="<?php echo esc_attr(brijraj_field_name($key)); ?>"
                           type="<?php echo esc_attr($f['type']); ?>" value="<?php echo esc_attr($val); ?>"
                           <?php echo ! empty($f['autocomplete']) ? 'autocomplete="' . esc_attr($f['autocomplete']) . '"' : ''; ?>
                           <?php echo $f['required'] ? 'required aria-required="true"' : ''; ?>
                           <?php echo ! empty($f['help']) ? 'aria-describedby="' . esc_attr($id) . '-help"' : ''; ?>>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <div class="brt-form__row brt-form__row--consent">
            <label class="brt-form__consent" for="brt_consent">
                <input type="checkbox" id="brt_consent" name="brtf_consent" value="1" required aria-required="true"
                       <?php checked(! empty($old['consent'])); ?>>
                <span>I am happy for Brij Raj Singh to reply to me about this, and to receive occasional relevant emails about AI workflows for project delivery. I can unsubscribe at any time.</span>
            </label>
        </div>

        <?php // Honeypot — hidden from people, tempting to bots. Never display:none alone. ?>
        <div class="brt-hp" aria-hidden="true">
            <label for="brt_website">Website</label>
            <input type="text" id="brt_website" name="brtf_website" tabindex="-1" autocomplete="off">
        </div>

        <button type="submit" class="brt-btn brt-btn--primary brt-form__submit" data-cta="challenge_submit">Share Your Challenge</button>

        <p class="brt-form__privacy">
            Your details are used only to reply to you and are never sold or shared.
            See the <a href="/privacy-policy/">Privacy Policy</a>.
        </p>
    </form>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('brt_challenge_form', static fn (): string => brijraj_challenge_form());

/**
 * Handle the submission.
 *
 * Runs on `template_redirect` so it happens before any output, which lets the
 * success path render inline without a redirect and without header warnings.
 */
add_action('template_redirect', static function (): void {
    if (($_POST['brijraj_form'] ?? '') !== 'challenge') {
        return;
    }

    $errors = [];
    $values = [];

    // Nonce.
    if (! isset($_POST['brijraj_challenge_nonce'])
        || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['brijraj_challenge_nonce'])), 'brijraj_challenge')) {
        $GLOBALS['brijraj_form_state'] = [
            'errors' => ['That form had expired. Please try again.'],
            'values' => [],
        ];
        return;
    }

    // Honeypot: a real person never fills this in.
    if (trim((string) ($_POST["brtf_website"] ?? "")) !== '') {
        // Pretend it worked. Bots get no signal to iterate against.
        $GLOBALS['brijraj_form_state'] = ['success' => true];
        return;
    }

    // Time trap: a human cannot complete this in under three seconds.
    $started = (int) ($_POST['brijraj_t'] ?? 0);
    if ($started > 0 && (time() - $started) < 3) {
        $GLOBALS['brijraj_form_state'] = ['success' => true];
        return;
    }

    foreach (brijraj_challenge_fields() as $key => $f) {
        $raw = wp_unslash((string) ($_POST[brijraj_field_name($key)] ?? ''));

        $clean = $f['type'] === 'textarea'
            ? sanitize_textarea_field($raw)
            : sanitize_text_field($raw);

        if ($f['type'] === 'email') {
            $clean = sanitize_email($raw);
        }

        $values[$key] = $clean;

        if ($f['required'] && $clean === '') {
            $errors[] = $f['label'] . ' is required.';
        }
    }

    if ($values['email'] !== '' && ! is_email($values['email'])) {
        $errors[] = 'That email address does not look right.';
    }

    $values['consent'] = ! empty($_POST["brtf_consent"]);
    if (! $values['consent']) {
        $errors[] = 'Please confirm you are happy for me to reply to you.';
    }

    if ($errors !== []) {
        $GLOBALS['brijraj_form_state'] = ['errors' => $errors, 'values' => $values];
        return;
    }

    // Store.
    $title = sprintf('%s — %s', $values['name'], $values['role'] ?: 'role not given');

    $post_id = wp_insert_post([
        'post_type'   => BRIJRAJ_LEAD_CPT,
        'post_title'  => $title,
        'post_status' => 'publish',
    ], true);

    if (! is_wp_error($post_id)) {
        foreach ($values as $k => $v) {
            update_post_meta($post_id, '_brt_' . $k, $v);
        }
        update_post_meta($post_id, '_brt_submitted_at', current_time('mysql'));
        update_post_meta($post_id, '_brt_source', esc_url_raw((string) wp_get_referer()));

        /**
         * Fires after a challenge submission is stored.
         *
         * Mirrors `brijraj_lead_captured` so campaign attribution and any future
         * integration can hook both funnels the same way.
         *
         * @param int $post_id Stored submission ID.
         */
        do_action('brijraj_challenge_stored', (int) $post_id);

        /**
         * Fires after a challenge submission is stored.
         *
         * This is the seam for an ESP later — subscribe the address here
         * without touching the form or the storage.
         *
         * @param array<string, mixed> $values  Sanitised submission.
         * @param int                  $post_id Stored lead ID.
         */
        do_action('brijraj_lead_submitted', $values, (int) $post_id);
    }

    // Notify.
    $lines = ["New challenge submitted on brijraj.tech", ''];
    foreach (brijraj_challenge_fields() as $key => $f) {
        if (($values[$key] ?? '') !== '') {
            $lines[] = $f['label'] . ': ' . $values[$key];
        }
    }
    $lines[] = '';
    $lines[] = 'Consent given: yes';
    $lines[] = 'Submitted: ' . current_time('mysql');
    if (! is_wp_error($post_id)) {
        $lines[] = 'Read in admin: ' . admin_url('post.php?post=' . $post_id . '&action=edit');
    }

    wp_mail(
        brijraj_notification_email(),
        sprintf('[BrijRaj.Tech] Challenge from %s', $values['name']),
        implode("\n", $lines),
        ['Reply-To: ' . $values['name'] . ' <' . $values['email'] . '>']
    );

    $GLOBALS['brijraj_form_state'] = ['success' => true];
});

/**
 * Show the submission content in the admin list and editor, since the CPT only
 * supports a title.
 */
add_action('add_meta_boxes', static function (): void {
    add_meta_box('brt_lead_detail', __('Submission', 'brijraj'), static function ($post): void {
        echo '<table class="widefat striped"><tbody>';
        foreach (brijraj_challenge_fields() as $key => $f) {
            $v = (string) get_post_meta($post->ID, '_brt_' . $key, true);
            if ($v === '') {
                continue;
            }
            printf(
                '<tr><th style="width:220px;text-align:left">%s</th><td>%s</td></tr>',
                esc_html($f['label']),
                nl2br(esc_html($v))
            );
        }
        printf(
            '<tr><th style="text-align:left">%s</th><td>%s</td></tr>',
            esc_html__('Submitted', 'brijraj'),
            esc_html((string) get_post_meta($post->ID, '_brt_submitted_at', true))
        );
        printf(
            '<tr><th style="text-align:left">%s</th><td>%s</td></tr>',
            esc_html__('From page', 'brijraj'),
            esc_html((string) get_post_meta($post->ID, '_brt_source', true))
        );
        echo '</tbody></table>';
    }, BRIJRAJ_LEAD_CPT, 'normal', 'high');
});

/**
 * Useful columns on the Challenges list screen.
 */
add_filter('manage_' . BRIJRAJ_LEAD_CPT . '_posts_columns', static function (array $cols): array {
    return [
        'cb'       => $cols['cb'] ?? '',
        'title'    => __('Name / Role', 'brijraj'),
        'brt_email'   => __('Email', 'brijraj'),
        'brt_company' => __('Company', 'brijraj'),
        'brt_solving' => __('Trying to solve', 'brijraj'),
        'date'     => __('Received', 'brijraj'),
    ];
});

add_action('manage_' . BRIJRAJ_LEAD_CPT . '_posts_custom_column', static function (string $col, int $post_id): void {
    $map = ['brt_email' => '_brt_email', 'brt_company' => '_brt_company', 'brt_solving' => '_brt_solving'];

    if (! isset($map[$col])) {
        return;
    }

    $v = (string) get_post_meta($post_id, $map[$col], true);

    if ($col === 'brt_email' && $v !== '') {
        printf('<a href="mailto:%s">%s</a>', esc_attr($v), esc_html($v));
        return;
    }

    echo esc_html($col === 'brt_solving' ? wp_trim_words($v, 14) : $v);
}, 10, 2);
