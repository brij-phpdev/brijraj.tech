<?php
/**
 * Client reviews — the site's only third-party proof.
 *
 * These are the verified reviews from the LinkedIn services page. They are
 * facts only — name, role, rating, date — and the block links back to LinkedIn
 * so a visitor can check them independently. Review *text* is deliberately
 * empty rather than paraphrased: putting words in a named person's mouth is
 * the one mistake here that cannot be walked back.
 *
 * To add the real quotes, filter `brijraj_client_reviews` and set `quote` on
 * each entry. Both layouts pick it up with no other change.
 *
 * Defined once here and rendered in three places (home, about, audit) rather
 * than duplicated, so correcting a role or adding a quote is a single edit.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * @return list<array{name:string,role:string,rating:string,date:string,quote:string}>
 */
function brijraj_client_reviews(): array
{
    return (array) apply_filters('brijraj_client_reviews', [
        [
            'name'   => 'Akanksha Dhyani',
            'role'   => 'Product Manager',
            'rating' => '5/5',
            'date'   => 'June 2023',
            'quote'  => '',
        ],
        [
            'name'   => 'Vivek Sharma',
            'role'   => 'Technical Architect',
            'rating' => '4.8/5',
            'date'   => 'May 2023',
            'quote'  => '',
        ],
        [
            'name'   => 'Aditya Verma',
            'role'   => 'Technical Lead',
            'rating' => '5/5',
            'date'   => 'May 2023',
            'quote'  => '',
        ],
        [
            'name'   => 'Akhilesh Singh',
            'role'   => 'Digital Marketing Manager',
            'rating' => '5/5',
            'date'   => 'May 2023',
            'quote'  => '',
        ],
    ]);
}

/**
 * Where the reviews can be verified.
 */
function brijraj_client_reviews_url(): string
{
    return (string) apply_filters(
        'brijraj_client_reviews_url',
        'https://www.linkedin.com/in/brijrajsinngh/'
    );
}

/**
 * Render the reviews.
 *
 * Two layouts. `grid` gives each review a card and room for a quote — used
 * where the reader has already committed to the page. `strip` is a single
 * dense row for the homepage, where the job is to establish that named people
 * vouch for him within the first screen, not to be read closely.
 *
 * @param array{layout?:string, heading?:string, note?:bool} $args
 */
function brijraj_reviews_html(array $args = []): string
{
    $reviews = brijraj_client_reviews();

    if ($reviews === []) {
        return '';
    }

    $layout  = ($args['layout'] ?? 'grid') === 'strip' ? 'strip' : 'grid';
    $heading = (string) ($args['heading'] ?? '');
    $note    = ($args['note'] ?? true) !== false;

    ob_start();
    ?>
<div class="brt-proof brt-proof--<?php echo esc_attr($layout); ?>">
  <?php if ($heading !== '') : ?>
    <h2 class="brt-proof__h"><?php echo esc_html($heading); ?></h2>
  <?php endif; ?>

  <ul class="brt-proof__list">
    <?php foreach ($reviews as $r) : ?>
      <li class="brt-proof__item">
        <?php if (! empty($r['quote'])) : ?>
          <p class="brt-proof__quote"><?php echo esc_html($r['quote']); ?></p>
        <?php endif; ?>
        <p class="brt-proof__meta">
          <span class="brt-proof__name"><?php echo esc_html($r['name']); ?></span>
          <span class="brt-proof__role"><?php echo esc_html($r['role']); ?></span>
          <span class="brt-proof__rating"><?php echo esc_html($r['rating']); ?><span class="brt-proof__dot"> &middot; </span><?php echo esc_html($r['date']); ?></span>
        </p>
      </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($note) : ?>
    <p class="brt-proof__note">
      Client reviews from my
      <a href="<?php echo esc_url(brijraj_client_reviews_url()); ?>" target="_blank" rel="noopener noreferrer">LinkedIn services page</a>,
      where they can be read in full.
    </p>
  <?php endif; ?>
</div>
    <?php

    return (string) ob_get_clean();
}

/**
 * [brt_client_reviews layout="strip|grid" heading="..." note="yes|no"]
 *
 * Named for the client reviews specifically, because inc/reviews.php already
 * owns [brt_reviews] for the CPT-backed review-gathering system. That module
 * loads later, so sharing the tag would silently overwrite this one — and,
 * since no CPT reviews exist yet, render nothing at all.
 */
add_shortcode('brt_client_reviews', static function ($atts): string {
    $atts = shortcode_atts(
        ['layout' => 'grid', 'heading' => '', 'note' => 'yes'],
        is_array($atts) ? $atts : [],
        'brt_client_reviews'
    );

    return brijraj_reviews_html([
        'layout'  => (string) $atts['layout'],
        'heading' => (string) $atts['heading'],
        'note'    => $atts['note'] !== 'no',
    ]);
});
