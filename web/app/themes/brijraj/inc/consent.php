<?php
/**
 * Cookie consent — Google Consent Mode v2.
 *
 * Implemented as real consent, not a cosmetic banner. Analytics storage is
 * DENIED by default, so no analytics cookie is written until a visitor opts in.
 * Until then GA4 sends cookieless pings, which is what Consent Mode is for:
 * Google still models aggregate traffic, and no identifier is stored on the
 * visitor's device.
 *
 * Why Consent Mode rather than simply withholding the tag: withholding means a
 * rejecting visitor is invisible, and a consenting visitor's first pageview is
 * lost to the race between the banner and the tag. Consent Mode loads the tag
 * immediately in a storage-less state and upgrades it in place.
 *
 * Design decisions worth keeping:
 * - Reject is exactly as prominent as Accept. Making refusal harder than
 *   agreement is a dark pattern and is specifically called out under GDPR.
 * - No pre-ticked boxes, and silence is not consent — dismissing without
 *   choosing leaves everything denied.
 * - Shown to every visitor, not geo-targeted. Simpler, and it reads as
 *   trustworthy to the enterprise audience this site is aimed at.
 * - The choice lives in localStorage, not a cookie: a record of a consent
 *   decision is strictly necessary, and keeping it out of the cookie jar avoids
 *   the awkwardness of setting a cookie to remember that cookies were refused.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** How long a stored decision stands before we ask again (days). */
const BRIJRAJ_CONSENT_TTL_DAYS = 180;

/**
 * Consent Mode defaults.
 *
 * MUST be printed before gtag('config', …) — the defaults have to be in the
 * dataLayer before the tag configures itself, or the first hit escapes with
 * storage enabled.
 */
function brijraj_consent_default_script(): string
{
    ob_start();
    ?>
  // Consent Mode v2 — everything that touches storage starts denied.
  gtag('consent', 'default', {
    'ad_storage': 'denied',
    'ad_user_data': 'denied',
    'ad_personalization': 'denied',
    'analytics_storage': 'denied',
    'functionality_storage': 'granted',
    'security_storage': 'granted',
    'wait_for_update': 500
  });

  // Re-apply a previously granted decision before the first hit, so returning
  // visitors are not asked again and their pageview is measured properly.
  (function () {
    try {
      var raw = window.localStorage.getItem('brt_consent');
      if (!raw) { return; }
      var saved = JSON.parse(raw);
      var age = (Date.now() - (saved.t || 0)) / 86400000;
      if (age > <?php echo (int) BRIJRAJ_CONSENT_TTL_DAYS; ?>) { return; }
      if (saved.analytics === 'granted') {
        gtag('consent', 'update', { 'analytics_storage': 'granted' });
      }
      window.__brtConsent = saved;
    } catch (e) {}
  })();
    <?php
    return (string) ob_get_clean();
}

/**
 * The banner, plus the logic that records a decision.
 */
add_action('wp_footer', static function (): void {
    if (is_admin()) {
        return;
    }

    $privacy = get_privacy_policy_url() ?: home_url('/privacy-policy/');
    ?>
<div class="brt-consent" id="brt-consent" role="dialog" aria-modal="false"
     aria-labelledby="brt-consent-title" aria-describedby="brt-consent-desc" hidden>
  <div class="brt-consent__inner">
    <div class="brt-consent__copy">
      <p class="brt-consent__title" id="brt-consent-title">Cookies on this site</p>
      <p class="brt-consent__desc" id="brt-consent-desc">
        We use Google Analytics to understand which pages are useful and which are not.
        Nothing is stored on your device unless you agree, and nothing is ever sold or
        shared. Decline and the site works exactly the same.
        <a href="<?php echo esc_url($privacy); ?>">Privacy Policy</a>
      </p>
    </div>
    <div class="brt-consent__actions">
      <button type="button" class="brt-btn brt-btn--secondary brt-consent__btn" data-consent="deny">Decline</button>
      <button type="button" class="brt-btn brt-btn--primary brt-consent__btn" data-consent="allow">Accept analytics</button>
    </div>
  </div>
</div>

<script>
(function () {
  var KEY = 'brt_consent';
  var TTL_DAYS = <?php echo (int) BRIJRAJ_CONSENT_TTL_DAYS; ?>;
  var bar = document.getElementById('brt-consent');
  if (!bar) { return; }

  function read() {
    try {
      var raw = window.localStorage.getItem(KEY);
      if (!raw) { return null; }
      var v = JSON.parse(raw);
      if ((Date.now() - (v.t || 0)) / 86400000 > TTL_DAYS) { return null; }
      return v;
    } catch (e) { return null; }
  }

  function write(decision) {
    try {
      window.localStorage.setItem(KEY, JSON.stringify({ analytics: decision, t: Date.now() }));
    } catch (e) {}
  }

  // Consent Mode stops gtag *using* analytics cookies, but it does not remove
  // ones already written. Withdrawing consent has to actually clear them —
  // otherwise the identifier survives the refusal, which is precisely what the
  // visitor just declined.
  function clearAnalyticsCookies() {
    var host = window.location.hostname;
    var domains = ['', host, '.' + host];
    var bare = host.replace(/^www\./, '');
    if (bare !== host) { domains.push(bare, '.' + bare); }

    document.cookie.split(';').forEach(function (chunk) {
      var name = chunk.split('=')[0].trim();
      if (!/^_ga/.test(name) && name !== '_gid' && name !== '_gat') { return; }

      domains.forEach(function (d) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:01 GMT; path=/'
          + (d ? '; domain=' + d : '');
      });
    });
  }

  function apply(decision) {
    if (typeof window.gtag === 'function') {
      window.gtag('consent', 'update', { 'analytics_storage': decision });
      if (decision === 'granted') {
        // The pageview already went out cookieless; send one that counts.
        window.gtag('event', 'page_view');
      }
    }

    if (decision === 'denied') {
      clearAnalyticsCookies();
    }
  }

  function hide() {
    bar.hidden = true;
    bar.classList.remove('is-open');
    document.body.classList.remove('has-consent-bar');
    document.documentElement.style.removeProperty('--brt-consent-h');
  }

  // Publish the banner's real height so the page reserves exactly that much.
  // A hard-coded offset breaks as soon as the copy wraps differently at another
  // width - which is what caused the sticky bar to overlap it.
  function measure() {
    document.documentElement.style.setProperty(
      '--brt-consent-h', Math.ceil(bar.getBoundingClientRect().height) + 'px'
    );
  }

  function show() {
    bar.hidden = false;
    // Force a reflow so the transition has a start state, then reveal.
    //
    // This deliberately does NOT use requestAnimationFrame: rAF is throttled in
    // background tabs, and a visitor who middle-clicks a link from LinkedIn
    // would otherwise land on a page where the banner is un-hidden but still
    // translated off-screen - invisible, and impossible to consent through.
    void bar.offsetHeight;
    bar.classList.add('is-open');
    document.body.classList.add('has-consent-bar');
    measure();
  }

  window.addEventListener('resize', function () {
    if (!bar.hidden) { measure(); }
  }, { passive: true });

  bar.addEventListener('click', function (e) {
    var btn = e.target.closest('[data-consent]');
    if (!btn) { return; }
    var decision = btn.getAttribute('data-consent') === 'allow' ? 'granted' : 'denied';
    write(decision);
    apply(decision);
    hide();
  });

  // Reopen from the footer link, so a decision is never final.
  document.addEventListener('click', function (e) {
    var link = e.target.closest('[data-consent-reopen]');
    if (!link) { return; }
    e.preventDefault();
    show();
    var first = bar.querySelector('[data-consent]');
    if (first) { first.focus(); }
  });

  if (!read()) { show(); }
})();
</script>
    <?php
}, 5);

/**
 * Footer link to reopen the banner.
 *
 * A consent decision that cannot be revisited is not really consent, and the
 * right to withdraw has to be as easy as giving it in the first place.
 */
add_shortcode('brt_consent_link', static function (): string {
    return '<a href="#" data-consent-reopen>' . esc_html__('Cookie settings', 'brijraj') . '</a>';
});
