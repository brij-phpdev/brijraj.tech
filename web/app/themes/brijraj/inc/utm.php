<?php
/**
 * Campaign attribution.
 *
 * GA4 already reads utm_* parameters on arrival, so nothing here is needed for
 * basic channel reporting. What GA4 cannot do is carry the campaign across the
 * two boundaries that matter for this business:
 *
 *   1. The checkout hop. A visitor arrives from LinkedIn, clicks Buy, and lands
 *      on Gumroad — where the campaign is gone. Gumroad then reports every sale
 *      as coming from brijraj.tech, so per-channel revenue is unknowable.
 *   2. The lead record. A challenge submission or Starter Kit signup is far more
 *      useful when it says which campaign produced it.
 *
 * Storage is sessionStorage, not a cookie: attribution lasts for the visit,
 * which covers the realistic path (click ad -> read -> buy), and it avoids
 * planting a persistent identifier and the consent questions that come with one.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * The parameters tracked, in the order they are stored and displayed.
 *
 * @return list<string>
 */
function brijraj_utm_keys(): array
{
    return ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
}

/**
 * Client-side capture, propagation and form injection.
 */
add_action('wp_footer', static function (): void {
    if (is_admin()) {
        return;
    }

    $keys = wp_json_encode(brijraj_utm_keys());
    ?>
<script>
(function () {
  var KEYS = <?php echo $keys; // phpcs:ignore WordPress.Security.EscapeOutput -- wp_json_encode output. ?>;
  var STORE = 'brt_campaign';

  function fromUrl() {
    var q = new URLSearchParams(window.location.search), found = {}, any = false;
    KEYS.forEach(function (k) {
      var v = q.get(k);
      if (v) { found[k] = v.slice(0, 120); any = true; }
    });
    // Ad platform click IDs are worth keeping for the same reason.
    ['gclid', 'fbclid', 'li_fat_id'].forEach(function (k) {
      var v = q.get(k);
      if (v) { found[k] = v.slice(0, 200); any = true; }
    });
    return any ? found : null;
  }

  function read() {
    try { return JSON.parse(sessionStorage.getItem(STORE) || 'null'); }
    catch (e) { return null; }
  }

  // First touch of the session wins. A visitor who arrives from LinkedIn and
  // later clicks an internal link should still be attributed to LinkedIn.
  var current = read();
  var incoming = fromUrl();
  if (incoming && !current) {
    try { sessionStorage.setItem(STORE, JSON.stringify(incoming)); } catch (e) {}
    current = incoming;
  }
  if (!current) { return; }

  // --- 1. Carry the campaign through to Gumroad -------------------------
  document.addEventListener('click', function (e) {
    var a = e.target.closest && e.target.closest('a[href]');
    if (!a) { return; }

    var href = a.getAttribute('href') || '';
    if (href.indexOf('gumroad.com') === -1) { return; }

    try {
      var u = new URL(href, window.location.origin);
      Object.keys(current).forEach(function (k) {
        if (!u.searchParams.has(k)) { u.searchParams.set(k, current[k]); }
      });
      a.setAttribute('href', u.toString());
    } catch (err) { /* leave the original href alone */ }
  }, true);

  // --- 2. Attach the campaign to form submissions -----------------------
  function inject() {
    document.querySelectorAll('form.brt-form').forEach(function (form) {
      Object.keys(current).forEach(function (k) {
        var name = 'brtf_' + k;
        if (form.querySelector('[name="' + name + '"]')) { return; }
        var i = document.createElement('input');
        i.type = 'hidden';
        i.name = name;
        i.value = current[k];
        form.appendChild(i);
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', inject);
  } else {
    inject();
  }
})();
</script>
    <?php
}, 23);

/**
 * Persist campaign data onto a stored lead or challenge submission.
 *
 * @param int $post_id Lead/subscriber post to annotate.
 */
function brijraj_store_utm(int $post_id): void
{
    $extra = ['gclid', 'fbclid', 'li_fat_id'];

    foreach (array_merge(brijraj_utm_keys(), $extra) as $key) {
        $raw = sanitize_text_field(wp_unslash((string) ($_POST['brtf_' . $key] ?? '')));

        if ($raw === '') {
            continue;
        }

        update_post_meta($post_id, '_brt_' . $key, mb_substr($raw, 0, 200));
    }
}

/**
 * Record campaign data against both funnels.
 *
 * Hooked rather than edited into each form so the two capture paths stay in
 * sync and a third form would only need the same one-line hook.
 */
add_action('brijraj_lead_captured', static function (array $lead, int $post_id): void {
    brijraj_store_utm($post_id);
}, 10, 2);

add_action('brijraj_challenge_stored', static function (int $post_id): void {
    brijraj_store_utm($post_id);
});

/**
 * Show the campaign on the submission screens, so a lead's origin is visible
 * without cross-referencing GA4.
 */
add_action('add_meta_boxes', static function (): void {
    foreach ([BRIJRAJ_LEAD_CPT, BRIJRAJ_SUBSCRIBER_CPT] as $type) {
        add_meta_box('brt_utm', __('Campaign', 'brijraj'), static function ($post): void {
            $rows = '';

            foreach (array_merge(brijraj_utm_keys(), ['gclid', 'fbclid', 'li_fat_id']) as $key) {
                $v = (string) get_post_meta($post->ID, '_brt_' . $key, true);

                if ($v === '') {
                    continue;
                }

                $rows .= sprintf(
                    '<tr><th style="width:150px;text-align:left">%s</th><td><code>%s</code></td></tr>',
                    esc_html($key),
                    esc_html($v)
                );
            }

            if ($rows === '') {
                echo '<p>' . esc_html__('No campaign parameters — this visitor arrived directly, from an untagged link, or from organic search.', 'brijraj') . '</p>';
                return;
            }

            echo '<table class="widefat striped"><tbody>' . $rows . '</tbody></table>'; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
        }, $type, 'side', 'default');
    }
}, 20);

/**
 * Add the campaign columns to the subscriber CSV export.
 */
add_filter('brijraj_subscriber_export_columns', static function (array $cols): array {
    return array_merge($cols, brijraj_utm_keys());
});

add_filter('brijraj_subscriber_export_row', static function (array $row, int $post_id): array {
    foreach (brijraj_utm_keys() as $key) {
        $row[] = (string) get_post_meta($post_id, '_brt_' . $key, true);
    }

    return $row;
}, 10, 2);
