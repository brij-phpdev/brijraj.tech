<?php
/**
 * SEO output: meta description, Open Graph, Twitter cards, JSON-LD.
 *
 * WordPress core already handles <title> and rel=canonical, so this only adds
 * what core leaves out. Deliberately not an SEO plugin: the site has nine pages
 * and a blog, and a plugin would bring a settings surface, an admin UI and an
 * upgrade path we would then have to maintain through launch week.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Per-entry meta description, editable in the sidebar.
 */
add_action('add_meta_boxes', static function (): void {
    foreach (['page', 'post'] as $type) {
        add_meta_box(
            'brt_seo',
            __('Search appearance', 'brijraj'),
            static function ($post): void {
                wp_nonce_field('brt_seo_save', 'brt_seo_nonce');
                $desc = (string) get_post_meta($post->ID, '_brt_meta_description', true);
                ?>
                <p>
                    <label for="brt_meta_description"><strong><?php esc_html_e('Meta description', 'brijraj'); ?></strong></label>
                </p>
                <textarea id="brt_meta_description" name="brt_meta_description" rows="3"
                          style="width:100%" maxlength="200"
                          placeholder="<?php esc_attr_e('One sentence describing this page for search results.', 'brijraj'); ?>"><?php echo esc_textarea($desc); ?></textarea>
                <p class="description">
                    <?php esc_html_e('Aim for 140–160 characters. Left blank, one is generated from the content.', 'brijraj'); ?>
                </p>
                <?php
            },
            $type,
            'side',
            'default'
        );
    }
});

add_action('save_post', static function (int $post_id): void {
    if (! isset($_POST['brt_seo_nonce'])
        || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['brt_seo_nonce'])), 'brt_seo_save')) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (! current_user_can('edit_post', $post_id)) {
        return;
    }

    $desc = sanitize_text_field(wp_unslash((string) ($_POST['brt_meta_description'] ?? '')));

    if ($desc === '') {
        delete_post_meta($post_id, '_brt_meta_description');
        return;
    }

    update_post_meta($post_id, '_brt_meta_description', $desc);
});

/**
 * Work out the best description for the current view.
 */
function brijraj_meta_description(): string
{
    $fallback = (string) get_bloginfo('description');

    if (is_front_page()) {
        return 'Turn repetitive project work into five ready-to-run AI workflows — status reports, meeting actions, executive updates, incident comms and risk intelligence. Built for implementation, not coursework.';
    }

    if (is_singular()) {
        $id     = get_queried_object_id();
        $custom = (string) get_post_meta($id, '_brt_meta_description', true);

        if ($custom !== '') {
            return $custom;
        }

        $post = get_post($id);

        if ($post instanceof WP_Post) {
            $text = $post->post_excerpt !== ''
                ? $post->post_excerpt
                : wp_strip_all_tags(strip_shortcodes((string) $post->post_content));

            $text = trim(preg_replace('/\s+/', ' ', $text) ?? '');

            if ($text !== '') {
                return wp_html_excerpt($text, 155, '…');
            }
        }
    }

    if (is_category() || is_tag() || is_tax()) {
        $term = get_queried_object();

        if ($term instanceof WP_Term && $term->description !== '') {
            return wp_html_excerpt(wp_strip_all_tags($term->description), 155, '…');
        }

        if ($term instanceof WP_Term) {
            return sprintf('Articles on %s from BrijRaj.Tech.', $term->name);
        }
    }

    return $fallback;
}

/**
 * The social share image, if one has been set as the site icon.
 */
function brijraj_share_image(): string
{
    // A dedicated OG image can be filtered in once one exists.
    $custom = (string) apply_filters('brijraj_share_image', '');

    if ($custom !== '') {
        return $custom;
    }

    if (is_singular() && has_post_thumbnail()) {
        $src = wp_get_attachment_image_url((int) get_post_thumbnail_id(), 'full');

        if (is_string($src)) {
            return $src;
        }
    }

    $icon = get_site_icon_url(512);

    return is_string($icon) ? $icon : '';
}

/**
 * Emit meta description, Open Graph and Twitter card tags.
 */
add_action('wp_head', static function (): void {
    $desc  = brijraj_meta_description();
    $title = wp_get_document_title();
    $url   = is_singular() ? (string) get_permalink() : home_url(add_query_arg([], (string) ($_SERVER['REQUEST_URI'] ?? '/')));
    $image = brijraj_share_image();

    printf('<meta name="description" content="%s" />' . "\n", esc_attr($desc));

    printf('<meta property="og:site_name" content="%s" />' . "\n", esc_attr((string) get_bloginfo('name')));
    printf('<meta property="og:title" content="%s" />' . "\n", esc_attr($title));
    printf('<meta property="og:description" content="%s" />' . "\n", esc_attr($desc));
    printf('<meta property="og:type" content="%s" />' . "\n", is_singular('post') ? 'article' : 'website');
    printf('<meta property="og:url" content="%s" />' . "\n", esc_url($url));
    printf('<meta property="og:locale" content="%s" />' . "\n", esc_attr(str_replace('-', '_', (string) get_bloginfo('language'))));

    if ($image !== '') {
        printf('<meta property="og:image" content="%s" />' . "\n", esc_url($image));
        printf('<meta name="twitter:image" content="%s" />' . "\n", esc_url($image));
        echo '<meta name="twitter:card" content="summary_large_image" />' . "\n";
    } else {
        echo '<meta name="twitter:card" content="summary" />' . "\n";
    }

    printf('<meta name="twitter:title" content="%s" />' . "\n", esc_attr($title));
    printf('<meta name="twitter:description" content="%s" />' . "\n", esc_attr($desc));

    if (is_singular('post')) {
        printf('<meta property="article:published_time" content="%s" />' . "\n", esc_attr((string) get_the_date('c')));
        printf('<meta property="article:modified_time" content="%s" />' . "\n", esc_attr((string) get_the_modified_date('c')));
    }
}, 2);

/**
 * JSON-LD structured data.
 *
 * Only what is factually supportable: who publishes the site, what a given
 * article is, and the product's real price. No aggregate ratings, no review
 * counts, no fabricated organisation details.
 */
add_action('wp_head', static function (): void {
    $home = untrailingslashit(home_url());

    $person = [
        '@type' => 'Person',
        'name'  => 'Brij Raj Singh',
        'url'   => $home . '/about/',
        'jobTitle' => 'Founder',
        'email' => 'mailto:brij@brijraj.tech',
        'sameAs' => ['https://www.linkedin.com/in/brijrajsinngh/'],
    ];

    $graph = [];

    $graph[] = [
        '@type'       => 'WebSite',
        '@id'         => $home . '/#website',
        'url'         => $home . '/',
        'name'        => (string) get_bloginfo('name'),
        'description' => (string) get_bloginfo('description'),
        'publisher'   => ['@id' => $home . '/#person'],
        'inLanguage'  => (string) get_bloginfo('language'),
    ];

    $graph[] = array_merge($person, ['@id' => $home . '/#person']);

    if (is_singular('post')) {
        $graph[] = [
            '@type'            => 'BlogPosting',
            '@id'              => get_permalink() . '#article',
            'headline'         => wp_strip_all_tags((string) get_the_title()),
            'description'      => brijraj_meta_description(),
            'datePublished'    => (string) get_the_date('c'),
            'dateModified'     => (string) get_the_modified_date('c'),
            'author'           => ['@id' => $home . '/#person'],
            'publisher'        => ['@id' => $home . '/#person'],
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => (string) get_permalink()],
            'isPartOf'         => ['@id' => $home . '/#website'],
        ];
    }

    // Product markup only where a real, configured purchase destination exists.
    if (is_page('ai-workflow-system') && function_exists('brijraj_cta_is_live') && brijraj_cta_is_live('core')) {
        $graph[] = [
            '@type'       => 'Product',
            '@id'         => get_permalink() . '#product',
            'name'        => 'AI Workflow System for Project Delivery',
            'description' => 'Five ready-to-run AI workflows for recurring project delivery work: weekly status reporting, meeting actions, executive updates, incident communication and decision/risk intelligence.',
            'brand'       => ['@type' => 'Brand', 'name' => 'BrijRaj.Tech'],
            'offers'      => [
                '@type'         => 'Offer',
                'price'         => '39.00',
                'priceCurrency' => 'USD',
                'availability'  => 'https://schema.org/InStock',
                'url'           => brijraj_cta_url('core'),
            ],
        ];
    }

    // FAQPage, derived from the FAQ actually rendered on the page.
    //
    // Google requires the marked-up Q&A to match visible content. Parsing the
    // real markup instead of maintaining a parallel list means the two cannot
    // drift apart when the copy is edited.
    if (is_singular()) {
        $faqs = brijraj_extract_faqs((string) get_post_field('post_content', get_queried_object_id()));

        if (count($faqs) >= 2) {
            $graph[] = [
                '@type'      => 'FAQPage',
                '@id'        => get_permalink() . '#faq',
                'mainEntity' => array_map(static fn (array $f): array => [
                    '@type'          => 'Question',
                    'name'           => $f['q'],
                    'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
                ], $faqs),
            ];
        }
    }

    if ($graph === []) {
        return;
    }

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}, 3);

/**
 * Pull question/answer pairs out of rendered FAQ markup.
 *
 * Matches the <details><summary>…</summary><div class="brt-faq__a">…</div>
 * structure the FAQ component uses. Returns [] when the page has no FAQ, so
 * callers can simply skip emitting schema.
 *
 * @return list<array{q:string,a:string}>
 */
function brijraj_extract_faqs(string $content): array
{
    if (! str_contains($content, 'brt-faq__a')) {
        return [];
    }

    $pattern = '#<summary[^>]*>(.*?)</summary>\s*<div class="brt-faq__a"[^>]*>(.*?)</div>#is';

    if (! preg_match_all($pattern, $content, $m, PREG_SET_ORDER)) {
        return [];
    }

    $faqs = [];

    foreach ($m as $pair) {
        $q = trim(wp_strip_all_tags(html_entity_decode($pair[1], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $a = trim(wp_strip_all_tags(html_entity_decode($pair[2], ENT_QUOTES | ENT_HTML5, 'UTF-8')));
        $a = trim((string) preg_replace('/\s+/', ' ', $a));

        if ($q !== '' && $a !== '') {
            $faqs[] = ['q' => $q, 'a' => $a];
        }
    }

    return $faqs;
}
