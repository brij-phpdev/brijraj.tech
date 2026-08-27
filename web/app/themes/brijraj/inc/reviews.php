<?php
/**
 * Reviews.
 *
 * Storage and display for genuine customer reviews. The component renders
 * nothing at all when there are none, so it can sit in the page today and start
 * working the moment the first real review is collected — no content edit, no
 * placeholder to remember to remove.
 *
 * Deliberately NOT emitting Review or AggregateRating structured data.
 * Self-serving review markup on your own product is against Google's
 * guidelines and is a common cause of manual actions. When reviews live on a
 * third-party platform, link to them instead.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

const BRIJRAJ_REVIEW_CPT = 'brt_review';

add_action('init', static function (): void {
    register_post_type(BRIJRAJ_REVIEW_CPT, [
        'labels' => [
            'name'          => __('Reviews', 'brijraj'),
            'singular_name' => __('Review', 'brijraj'),
            'menu_name'     => __('Reviews', 'brijraj'),
            'add_new_item'  => __('Add Review', 'brijraj'),
            'edit_item'     => __('Edit Review', 'brijraj'),
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-format-quote',
        'menu_position'       => 28,
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'supports'            => ['title', 'editor', 'page-attributes'],
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
    ]);
});

/**
 * The attribution fields.
 *
 * @return array<string, array{label:string, help:string}>
 */
function brijraj_review_fields(): array
{
    return [
        'role'       => ['label' => 'Role',        'help' => 'e.g. Delivery Manager. Attribution without a role is far less persuasive.'],
        'company'    => ['label' => 'Company',     'help' => 'Optional. Leave blank if they cannot name their employer.'],
        'location'   => ['label' => 'Location',    'help' => 'Optional, e.g. London, UK.'],
        'linkedin'   => ['label' => 'LinkedIn URL','help' => 'Optional. A verifiable profile makes a review markedly more credible.'],
        'permission' => ['label' => 'Permission on file', 'help' => 'Type YES only if this person has agreed in writing to be quoted publicly.'],
    ];
}

add_action('add_meta_boxes', static function (): void {
    add_meta_box('brt_review_meta', __('Attribution', 'brijraj'), static function ($post): void {
        wp_nonce_field('brt_review_save', 'brt_review_nonce');
        echo '<table class="form-table" role="presentation">';

        foreach (brijraj_review_fields() as $key => $f) {
            printf(
                '<tr><th scope="row"><label for="brt_%1$s">%2$s</label></th>
                 <td><input type="text" class="regular-text" id="brt_%1$s" name="brt_%1$s" value="%3$s">
                 <p class="description">%4$s</p></td></tr>',
                esc_attr($key),
                esc_html($f['label']),
                esc_attr((string) get_post_meta($post->ID, '_brt_rev_' . $key, true)),
                esc_html($f['help'])
            );
        }

        echo '</table>';
        echo '<p style="margin-top:1em;padding:.75em 1em;background:#FFF4D6;border-left:4px solid #D9A441">'
            . esc_html__('A review is only published on the site when "Permission on file" is YES. Without it the review is stored but never shown.', 'brijraj')
            . '</p>';
    }, BRIJRAJ_REVIEW_CPT, 'normal', 'high');
});

add_action('save_post_' . BRIJRAJ_REVIEW_CPT, static function (int $post_id): void {
    if (! isset($_POST['brt_review_nonce'])
        || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['brt_review_nonce'])), 'brt_review_save')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    foreach (array_keys(brijraj_review_fields()) as $key) {
        $raw = sanitize_text_field(wp_unslash((string) ($_POST['brt_' . $key] ?? '')));

        if ($key === 'linkedin') {
            $raw = esc_url_raw($raw, ['http', 'https']);
        }

        update_post_meta($post_id, '_brt_rev_' . $key, $raw);
    }
});

/**
 * Published reviews that have explicit permission recorded.
 *
 * @return list<WP_Post>
 */
function brijraj_get_reviews(int $limit = 6): array
{
    $posts = get_posts([
        'post_type'      => BRIJRAJ_REVIEW_CPT,
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => ['menu_order' => 'ASC', 'date' => 'DESC'],
    ]);

    // Permission is a hard gate, not a suggestion: quoting someone publicly
    // without their agreement is a problem regardless of how good the quote is.
    return array_values(array_filter($posts, static function (WP_Post $p): bool {
        return strtoupper(trim((string) get_post_meta($p->ID, '_brt_rev_permission', true))) === 'YES';
    }));
}

/**
 * [brt_reviews] — renders nothing when there are no permissioned reviews.
 */
add_shortcode('brt_reviews', static function ($atts): string {
    $atts = shortcode_atts(
        ['limit' => '6', 'heading' => 'What people using it say'],
        is_array($atts) ? $atts : [],
        'brt_reviews'
    );

    $reviews = brijraj_get_reviews(max(1, (int) $atts['limit']));

    if ($reviews === []) {
        // Editors get a reminder; visitors get nothing at all.
        if (current_user_can('edit_posts')) {
            return '<p class="brt-todo">No permissioned reviews yet — add them under Reviews in wp-admin. '
                . 'This section stays invisible to visitors until then.</p>';
        }

        return '';
    }

    ob_start();
    ?>
    <div class="brt-reviews">
        <?php if ($atts['heading'] !== '') : ?>
            <h2 class="brt-reviews__heading"><?php echo esc_html((string) $atts['heading']); ?></h2>
        <?php endif; ?>
        <div class="brt-reviews__grid">
            <?php foreach ($reviews as $r) :
                $role     = (string) get_post_meta($r->ID, '_brt_rev_role', true);
                $company  = (string) get_post_meta($r->ID, '_brt_rev_company', true);
                $linkedin = (string) get_post_meta($r->ID, '_brt_rev_linkedin', true);
                $meta     = trim($role . ($company !== '' ? ', ' . $company : ''), ', ');
                ?>
                <figure class="brt-review">
                    <blockquote class="brt-review__quote">
                        <?php echo wp_kses_post(wpautop((string) $r->post_content)); ?>
                    </blockquote>
                    <figcaption class="brt-review__by">
                        <span class="brt-review__name">
                            <?php if ($linkedin !== '') : ?>
                                <a href="<?php echo esc_url($linkedin); ?>" target="_blank" rel="noopener noreferrer nofollow"><?php echo esc_html(get_the_title($r)); ?></a>
                            <?php else : ?>
                                <?php echo esc_html(get_the_title($r)); ?>
                            <?php endif; ?>
                        </span>
                        <?php if ($meta !== '') : ?>
                            <span class="brt-review__meta"><?php echo esc_html($meta); ?></span>
                        <?php endif; ?>
                    </figcaption>
                </figure>
            <?php endforeach; ?>
        </div>
    </div>
    <?php

    return (string) ob_get_clean();
});

/**
 * Inject the reviews section on the product page — but only when there is
 * something to show.
 *
 * Keeping it out of the page content entirely is the difference between "an
 * invisible section" and "no section": a wrapper group with vertical padding
 * still occupies ~200px of empty page even when its contents render nothing.
 * This appends the whole block, wrapper included, or nothing at all.
 */
add_filter('the_content', static function (string $content): string {
    if (! is_page('ai-workflow-system') || ! in_the_loop() || ! is_main_query()) {
        return $content;
    }

    if (brijraj_get_reviews(6) === []) {
        return $content;
    }

    return $content
        . '<div class="wp-block-group alignfull has-off-white-background-color has-background brt-reviews-band">'
        . do_shortcode('[brt_reviews limit="6" heading="What people using it say"]')
        . '</div>';
}, 22);
