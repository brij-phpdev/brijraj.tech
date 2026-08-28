<?php
/**
 * The Delivery Reporting Audit — offer page, qualifying form and lead store.
 *
 * This is the site's single conversion path, and it is deliberately built as
 * one self-contained module rather than as page content in the database:
 *
 * - The copy lives in PHP, so it is versioned and diffable rather than sitting
 *   in wp_posts where a stray editor session can silently rewrite the offer.
 * - The FAQ array is the single source for both the rendered <details> markup
 *   and the FAQPage schema, so the two cannot drift apart. (The site-wide
 *   parser in seo.php reads post_content, which a shortcode-rendered page does
 *   not populate — hence the schema is emitted here instead.)
 * - The field definitions are the single source for the renderer, the
 *   validator, the notification email and the admin columns.
 *
 * The page has no site navigation by design. Every nav link on a landing page
 * is an exit, and the visitor who wanders into /resources/ mid-consideration
 * does not come back.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/** Where qualified enquiries are stored. */
const BRIJRAJ_AUDIT_CPT = 'brt_audit_lead';

/** Slug of the offer page, used for template and schema targeting. */
const BRIJRAJ_AUDIT_SLUG = 'audit';

/** Slug of the post-submission confirmation page. */
const BRIJRAJ_AUDIT_DONE_SLUG = 'received';

/**
 * Minimum seconds between submissions from one address.
 *
 * Generous, because a genuine second submission within a minute is almost
 * always a double-click rather than a second enquiry.
 */
const BRIJRAJ_AUDIT_THROTTLE = 90;

/* -------------------------------------------------------------------------
 * Content
 * ---------------------------------------------------------------------- */

/**
 * The FAQ.
 *
 * Ordered deliberately. "Is this an AI thing?" is first because it is the
 * question already forming as they read, and answering it here stops it
 * becoming a data-safety detour on the call. Offering to build without AI
 * costs nothing and closes the objection completely.
 *
 * @return list<array{q:string,a:string}>
 */
function brijraj_audit_faqs(): array
{
    return [
        [
            'q' => 'Is this an AI thing?',
            'a' => 'Partly, but that is an implementation detail rather than the point. Some of the workflows use automation and AI to draft client updates from collected status. Others are just a better-designed process. What you are buying is fewer hours lost to reporting — whatever gets there. If you would rather no AI touched your client data, say so and I will build it without.',
        ],
        [
            'q' => 'What if my team will not track their time?',
            'a' => 'That is why a sponsor is a condition rather than a nice-to-have. It is one week, about two minutes a day per person, and the sheet takes seconds. Where it fails is when the team thinks it is a performance review — so I ask that you tell them plainly it is about the process, not the people. No individual is ever named in the report.',
        ],
        [
            'q' => 'What tools do you need access to?',
            'a' => 'Read access to whatever you run projects in — Jira, Asana, ClickUp, Trello, Monday, a spreadsheet, whatever it actually is. Plus your last four client status reports as they were sent. Nothing that touches your codebase or your client systems.',
        ],
        [
            'q' => 'You do this alongside another role — will you be available?',
            'a' => 'Yes, and it is worth being direct about. Three live calls across the three weeks, which I schedule in Tuesday or Thursday late-morning slots. Everything else runs async, and I answer questions within one working day. If your situation needs someone on daily standups, I am not the right fit and I will say so on the first call.',
        ],
        [
            'q' => 'What if it does not work?',
            'a' => 'You will still have the baseline report, which most teams find worth it on its own because nobody has ever measured this. If the workflows do not hold after two weeks, I will tell you that in the follow-up rather than let it slide, and we will look at why. I would rather have an honest result than a case study.',
        ],
        [
            'q' => 'What happens after the three weeks?',
            'a' => 'The system is yours. Documented, recorded, and owned by someone on your team who I have trained. Some teams keep me on a light monthly retainer for the first quarter to stop it drifting — that is a separate conversation at handover, and genuinely optional.',
        ],
        [
            'q' => 'Do you need to talk to our clients?',
            'a' => 'No. I do not contact your clients, I am not introduced to them, and I do not appear in any of your client communication. I read the reports you have already sent, and that is the extent of it.',
        ],
        [
            'q' => 'How is this different from hiring a delivery manager?',
            'a' => 'A delivery manager runs the process. This installs one and leaves. If your problem is that nobody is coordinating, hire someone. If it is that your existing PMs are drowning in reporting overhead, that is a system problem and this is cheaper and faster than a hire.',
        ],
    ];
}

/* -------------------------------------------------------------------------
 * Form definition
 * ---------------------------------------------------------------------- */

/**
 * The qualifying fields.
 *
 * Short on purpose. Every field costs completions, and these five are the ones
 * that decide whether a call is worth a Tuesday morning slot. `situation` is
 * the most valuable field on the site: it captures the prospect's own words
 * about their own problem, which is exactly what makes a proposal land.
 *
 * @return array<string, array{label:string, type:string, required:bool, help?:string, options?:array<string,string>, autocomplete?:string, rows?:int}>
 */
function brijraj_audit_fields(): array
{
    return [
        'fullname' => [
            'label'        => 'Your name',
            'type'         => 'text',
            'required'     => true,
            'autocomplete' => 'name',
        ],
        'email' => [
            'label'        => 'Work email',
            'type'         => 'email',
            'required'     => true,
            'autocomplete' => 'email',
        ],
        'company' => [
            'label'        => 'Company',
            'type'         => 'text',
            'required'     => true,
            'autocomplete' => 'organization',
        ],
        'headcount' => [
            'label'    => 'How many people?',
            'type'     => 'select',
            'required' => true,
            'options'  => [
                ''         => 'Choose one',
                'under-20' => 'Under 20',
                '20-50'    => '20–50',
                '51-100'   => '51–100',
                '101-150'  => '101–150',
                'over-150' => 'More than 150',
            ],
        ],
        'pms' => [
            'label'    => 'How many PMs or delivery coordinators?',
            'type'     => 'select',
            'required' => true,
            'options'  => [
                ''    => 'Choose one',
                '1'   => 'Just one',
                '2-3' => '2–3',
                '4-6' => '4–6',
                '7'   => '7 or more',
            ],
        ],
        'tool' => [
            'label'    => 'What do you run projects in?',
            'type'     => 'text',
            'required' => false,
            'help'     => 'Jira, Asana, ClickUp, a spreadsheet — whatever it actually is',
        ],
        'situation' => [
            'label'    => 'What does reporting look like right now?',
            'type'     => 'textarea',
            'required' => true,
            'rows'     => 5,
            'help'     => 'A few lines is plenty. What happens between a client asking "where are we?" and them getting an answer?',
        ],
    ];
}

/* -------------------------------------------------------------------------
 * Lead store
 * ---------------------------------------------------------------------- */

add_action('init', static function (): void {
    register_post_type(BRIJRAJ_AUDIT_CPT, [
        'labels' => [
            'name'          => __('Audit Enquiries', 'brijraj'),
            'singular_name' => __('Audit Enquiry', 'brijraj'),
            'menu_name'     => __('Audit Enquiries', 'brijraj'),
            'all_items'     => __('All Enquiries', 'brijraj'),
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-clipboard',
        'menu_position'       => 25,
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
 * Unread count beside the menu item.
 *
 * Outbound mail is not configured yet, so wp-admin is currently the only place
 * an enquiry surfaces. A bubble that cannot be missed is the difference
 * between a reply within a day and a lead going cold unnoticed.
 */
add_action('admin_menu', static function (): void {
    $unread = (int) (new WP_Query([
        'post_type'      => BRIJRAJ_AUDIT_CPT,
        'post_status'    => 'publish',
        'meta_query'     => [['key' => '_brt_read', 'compare' => 'NOT EXISTS']],
        'fields'         => 'ids',
        'posts_per_page' => 50,
        'no_found_rows'  => false,
    ]))->found_posts;

    if ($unread < 1) {
        return;
    }

    global $menu;

    // Not populated in every context that fires admin_menu (WP-CLI, for one).
    if (! is_array($menu)) {
        return;
    }

    foreach ($menu as $i => $item) {
        if (($item[2] ?? '') !== 'edit.php?post_type=' . BRIJRAJ_AUDIT_CPT) {
            continue;
        }

        $menu[$i][0] .= sprintf(
            ' <span class="awaiting-mod"><span class="pending-count">%d</span></span>',
            $unread
        );
        break;
    }
}, 100);

/** Opening an enquiry marks it read, which clears it from the bubble. */
add_action('load-post.php', static function (): void {
    $id = isset($_GET['post']) ? (int) $_GET['post'] : 0;

    if ($id > 0 && get_post_type($id) === BRIJRAJ_AUDIT_CPT) {
        update_post_meta($id, '_brt_read', current_time('mysql'));
    }
});

/* -------------------------------------------------------------------------
 * Submission
 * ---------------------------------------------------------------------- */

/**
 * Throttle key for the requesting address.
 *
 * The address is hashed rather than stored: rate limiting needs to recognise a
 * repeat caller, which a keyed hash does, and does not need to know who they
 * are. Mirrors the approach already used by the Starter Kit funnel.
 */
function brijraj_audit_throttle_key(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');

    return 'brt_audit_' . substr(wp_hash($ip, 'nonce'), 0, 24);
}

/**
 * Handle the enquiry.
 *
 * Runs on `template_redirect` and finishes with a redirect to the confirmation
 * page — POST-redirect-GET, so a refresh cannot resubmit and the back button
 * behaves. Errors are passed back through a transient keyed to the throttle
 * hash rather than a query string, because a validation message in the URL is
 * both ugly and shareable.
 */
add_action('template_redirect', static function (): void {
    if (($_POST['brijraj_form'] ?? '') !== 'audit') {
        return;
    }

    $back = home_url('/' . BRIJRAJ_AUDIT_SLUG . '/#audit-form');

    // Nonce.
    if (! isset($_POST['brijraj_audit_nonce'])
        || ! wp_verify_nonce(sanitize_text_field(wp_unslash((string) $_POST['brijraj_audit_nonce'])), 'brijraj_audit')) {
        brijraj_audit_fail(['That form had expired — please try again.'], []);
        wp_safe_redirect($back);
        exit;
    }

    // Honeypot. Bots get a success response so they have nothing to iterate on.
    if (trim((string) ($_POST['brtf_website'] ?? '')) !== '') {
        wp_safe_redirect(home_url('/' . BRIJRAJ_AUDIT_SLUG . '/' . BRIJRAJ_AUDIT_DONE_SLUG . '/'));
        exit;
    }

    // Time trap: nobody completes a seven-field form in three seconds.
    $started = (int) ($_POST['brijraj_t'] ?? 0);
    if ($started > 0 && (time() - $started) < 3) {
        wp_safe_redirect(home_url('/' . BRIJRAJ_AUDIT_SLUG . '/' . BRIJRAJ_AUDIT_DONE_SLUG . '/'));
        exit;
    }

    // Throttle.
    if (get_transient(brijraj_audit_throttle_key()) !== false) {
        brijraj_audit_fail(
            ['That looks like a duplicate — I have your first message. Email brij@brijraj.tech if something needs changing.'],
            []
        );
        wp_safe_redirect($back);
        exit;
    }

    $fields = brijraj_audit_fields();
    $errors = [];
    $values = [];

    foreach ($fields as $key => $f) {
        $raw = wp_unslash((string) ($_POST[brijraj_field_name($key)] ?? ''));

        if ($f['type'] === 'email') {
            $clean = sanitize_email($raw);
        } elseif ($f['type'] === 'textarea') {
            $clean = sanitize_textarea_field($raw);
        } else {
            $clean = sanitize_text_field($raw);
        }

        // A select may only hold one of its declared options.
        if ($f['type'] === 'select' && ! array_key_exists($clean, $f['options'] ?? [])) {
            $clean = '';
        }

        $values[$key] = $clean;

        if (! empty($f['required']) && $clean === '') {
            $errors[] = $f['label'] . ' is required.';
        }
    }

    if ($values['email'] !== '' && ! is_email($values['email'])) {
        $errors[] = 'That email address does not look right.';
    }

    if ($errors !== []) {
        brijraj_audit_fail($errors, $values);
        wp_safe_redirect($back);
        exit;
    }

    set_transient(brijraj_audit_throttle_key(), 1, BRIJRAJ_AUDIT_THROTTLE);

    // Campaign attribution, injected into the form by inc/utm.php.
    $utm = [];
    foreach (['source', 'medium', 'campaign', 'content', 'term'] as $p) {
        $v = sanitize_text_field(wp_unslash((string) ($_POST['brtf_utm_' . $p] ?? '')));
        if ($v !== '') {
            $utm[$p] = $v;
        }
    }

    $post_id = wp_insert_post([
        'post_type'   => BRIJRAJ_AUDIT_CPT,
        'post_title'  => sprintf('%s — %s', $values['company'], $values['fullname']),
        'post_status' => 'publish',
    ], true);

    if (! is_wp_error($post_id)) {
        foreach ($values as $k => $v) {
            update_post_meta($post_id, '_brt_' . $k, $v);
        }

        update_post_meta($post_id, '_brt_submitted_at', current_time('mysql'));
        update_post_meta($post_id, '_brt_qualified', brijraj_audit_is_qualified($values) ? '1' : '0');

        if ($utm !== []) {
            update_post_meta($post_id, '_brt_utm', $utm);
        }

        /**
         * Fires after an audit enquiry is stored.
         *
         * The ESP seam — shared with the other funnels so a future integration
         * hooks all of them the same way.
         *
         * @param array<string,mixed> $values  Sanitised submission.
         * @param int                 $post_id Stored enquiry ID.
         */
        do_action('brijraj_lead_captured', $values, (int) $post_id);
    }

    $mail_id = is_wp_error($post_id) ? 0 : (int) $post_id;

    // Redirect first, then mail. The enquiry is already stored, so the visitor
    // has no reason to wait on two SMTP round trips.
    brijraj_redirect_then(
        home_url('/' . BRIJRAJ_AUDIT_SLUG . '/' . BRIJRAJ_AUDIT_DONE_SLUG . '/'),
        static function () use ($values, $mail_id, $utm): void {
            // The sender hears back first. They have just handed over their
            // situation in their own words; silence is how you lose them.
            brijraj_send_confirmation('audit', (string) $values['email'], [
                'name'    => (string) $values['fullname'],
                'company' => (string) $values['company'],
            ]);

            $sent = brijraj_audit_notify($values, $mail_id, $utm);

            // Stamped on the record so it is possible to tell, later and from
            // wp-admin alone, whether the notification actually left the
            // server. Without it a mail failure after the response has gone is
            // completely invisible.
            if ($mail_id > 0) {
                update_post_meta($mail_id, '_brt_notified_at', current_time('mysql'));
                update_post_meta($mail_id, '_brt_notify_ok', $sent ? '1' : '0');
            }
        }
    );
});

/**
 * Stash errors and entered values for redisplay after the redirect.
 *
 * @param list<string>        $errors
 * @param array<string,mixed> $values
 */
function brijraj_audit_fail(array $errors, array $values): void
{
    set_transient(
        brijraj_audit_throttle_key() . '_err',
        ['errors' => $errors, 'values' => $values],
        300
    );
}

/**
 * Does this enquiry meet the published criteria?
 *
 * Recorded on the lead rather than used to reject anyone: a 15-person agency
 * with a real problem is still worth a conversation, and turning them away
 * automatically would be worse than reading it and deciding. This just sorts
 * the list so the ones worth a Tuesday slot are obvious.
 *
 * @param array<string,mixed> $v
 */
function brijraj_audit_is_qualified(array $v): bool
{
    $size_ok = in_array((string) ($v['headcount'] ?? ''), ['20-50', '51-100', '101-150'], true);
    $pms_ok  = in_array((string) ($v['pms'] ?? ''), ['2-3', '4-6', '7'], true);

    return $size_ok && $pms_ok;
}

/**
 * Notify the owner.
 *
 * SMTP is not configured yet (SMTP_PASS is empty), so this will not currently
 * leave the server — wp_mail fails quietly and the enquiry is still safe in
 * the CPT with an unread bubble on the menu. The call is written now so that
 * populating SMTP_PASS is the only step needed to switch notifications on.
 *
 * The subject carries the qualifying data because it has to be readable on a
 * phone lock screen without opening anything.
 *
 * @param array<string,mixed>  $values
 * @param array<string,string> $utm
 */
function brijraj_audit_notify(array $values, int $post_id, array $utm = []): bool
{
    $fields = brijraj_audit_fields();

    $subject = sprintf(
        '[Audit] %s — %s, %s PMs%s',
        $values['company'],
        $fields['headcount']['options'][$values['headcount']] ?? $values['headcount'],
        $fields['pms']['options'][$values['pms']] ?? $values['pms'],
        brijraj_audit_is_qualified($values) ? '' : ' (outside criteria)'
    );

    $lines = ['New audit enquiry from brijraj.tech', ''];

    foreach ($fields as $key => $f) {
        $val = (string) ($values[$key] ?? '');

        if ($val === '') {
            continue;
        }

        if ($f['type'] === 'select') {
            $val = $f['options'][$val] ?? $val;
        }

        $lines[] = $f['label'] . ': ' . $val;
    }

    $lines[] = '';
    $lines[] = brijraj_audit_is_qualified($values)
        ? 'Meets the published criteria.'
        : 'Outside the published criteria — worth reading before deciding.';

    if ($utm !== []) {
        $lines[] = '';
        foreach ($utm as $k => $v) {
            $lines[] = 'utm_' . $k . ': ' . $v;
        }
    }

    $lines[] = '';
    $lines[] = 'Received: ' . current_time('mysql');

    if ($post_id > 0) {
        $lines[] = 'Open in admin: ' . admin_url('post.php?post=' . $post_id . '&action=edit');
    }

    return (bool) wp_mail(
        brijraj_notification_email(),
        $subject,
        implode("\n", $lines),
        ['Reply-To: ' . $values['fullname'] . ' <' . $values['email'] . '>']
    );
}

/* -------------------------------------------------------------------------
 * Rendering
 * ---------------------------------------------------------------------- */

/**
 * A CTA button on this page.
 *
 * Not routed through brijraj_cta(): those entries describe external commerce
 * destinations configured in wp-admin, whereas these are in-page anchors to
 * the form. The data-cta attribute keeps them on the same analytics listener.
 */
function brijraj_audit_button(string $location, string $label = 'See if it fits'): string
{
    return sprintf(
        '<a class="brt-btn brt-btn--primary brt-audit__cta" href="#audit-form" data-cta="audit_%s" data-cta-location="%s">%s <span aria-hidden="true">&rarr;</span></a>',
        esc_attr(sanitize_key($location)),
        esc_attr(sanitize_key($location)),
        esc_html($label)
    );
}

/**
 * The offer page.
 */
function brijraj_audit_page(): string
{
    $state  = get_transient(brijraj_audit_throttle_key() . '_err');
    $errors = is_array($state) ? (array) ($state['errors'] ?? []) : [];
    $old    = is_array($state) ? (array) ($state['values'] ?? []) : [];

    if ($state !== false) {
        delete_transient(brijraj_audit_throttle_key() . '_err');
    }

    ob_start();
    ?>
<div class="brt-audit">

  <?php /* 01 — the problem, in their terms, before anything is sold. */ ?>
  <section class="brt-audit__hero">
    <h1 class="brt-audit__h1">Your project managers are losing most of a day a week to status reporting.</h1>
    <p class="brt-audit__lead">Not to the work. To collecting updates, reformatting them for each client, and chasing people who already replied somewhere else.</p>
    <p class="brt-audit__lead">It doesn't appear in any plan. It's spread thin enough across the week that nobody logs it. And it comes out of the capacity of the most experienced people you have.</p>
  </section>

  <?php /* 02 — the promise. */ ?>
  <section class="brt-audit__promise">
    <p class="brt-audit__eyebrow">The Delivery Reporting Audit &amp; Install</p>
    <p class="brt-audit__promise-text">In three weeks, you'll know exactly how many hours a week your delivery team loses to reporting — measured across a real baseline week, not estimated — and you'll have a system that reduces it, owned by your team.</p>
    <?php echo brijraj_audit_button('hero'); ?>
  </section>

  <?php /* 03 — qualification, in public. Reads as confidence, and saves the bad-fit calls. */ ?>
  <section class="brt-audit__section">
    <div class="brt-audit__cols">
      <div class="brt-audit__col">
        <h2>Who this is for</h2>
        <ul class="brt-audit__list brt-audit__list--yes">
          <li>Software agencies and services firms, roughly 20–150 people</li>
          <li>Five or more client projects running at once</li>
          <li>At least two PMs or delivery coordinators</li>
          <li>An owner, MD or delivery head who can sponsor it</li>
          <li>Reporting is already a felt problem — you've noticed the cost</li>
        </ul>
      </div>
      <div class="brt-audit__col">
        <h2>Who it isn't for</h2>
        <ul class="brt-audit__list brt-audit__list--no">
          <li>Teams under 20, where coordination isn't yet expensive</li>
          <li>Anyone wanting a tool migration — I work with what you already pay for</li>
          <li>Product teams without client reporting obligations</li>
          <li>Anyone who wants the system run for them rather than handed over</li>
        </ul>
      </div>
    </div>
    <p class="brt-audit__aside">If you're not sure which side you're on, say so on the form and I'll tell you straight.</p>
  </section>

  <?php /* 04 — the process. Specific and bounded, which is where scepticism dies. */ ?>
  <section class="brt-audit__section">
    <h2>Three weeks. Three calls. Everything else async.</h2>
    <ol class="brt-audit__steps">
      <li>
        <span class="brt-audit__step-k">Week 1</span>
        <div>
          <h3>Measure</h3>
          <p>Your PMs track their time for one week using a sheet I provide. I pull 90 days of activity from your project tool, read your last four client status reports, and spend 30 minutes with each PM. Your team's total effort: about an hour each.</p>
        </div>
      </li>
      <li>
        <span class="brt-audit__step-k">Day 7</span>
        <div>
          <h3>The number</h3>
          <p>I show you where the hours actually go. This is usually the uncomfortable one. We agree which three workflows to build.</p>
        </div>
      </li>
      <li>
        <span class="brt-audit__step-k">Week 2</span>
        <div>
          <h3>Build</h3>
          <p>I configure three workflows inside the tools you already use: status collection, client update generation, and an escalation trigger. No new software, nothing touching your codebase.</p>
        </div>
      </li>
      <li>
        <span class="brt-audit__step-k">Week 3</span>
        <div>
          <h3>Hand over</h3>
          <p>A recorded walkthrough, written documentation, and a named person on your side who owns it. Two weeks later I measure again, so you know whether it held.</p>
        </div>
      </li>
    </ol>
  </section>

  <?php /* 05 + 06 — deliverables and the client's side of the bargain. */ ?>
  <section class="brt-audit__section">
    <div class="brt-audit__cols">
      <div class="brt-audit__col">
        <h2>What you get</h2>
        <ul class="brt-audit__list">
          <li><strong>Baseline report</strong> — where the hours go, per role, with the number on page one</li>
          <li><strong>Reporting flow map</strong> — every place the same status gets entered, including the informal channels</li>
          <li><strong>Three working workflows</strong> — live in your existing tools</li>
          <li><strong>Operating document</strong> — who does what, when, and what happens when something slips</li>
          <li><strong>Recorded walkthrough</strong> — so it survives your next hire</li>
          <li><strong>30-day watch list</strong> — the three things most likely to break, and what to do about each</li>
        </ul>
      </div>
      <div class="brt-audit__col">
        <h2>What I need from you</h2>
        <ul class="brt-audit__list">
          <li>A sponsor who can ask people to cooperate — an owner, MD or delivery head</li>
          <li>A named person who'll own the system afterwards, identified at the start</li>
          <li>30 minutes from each PM, once</li>
          <li>One week of light time tracking from the delivery team</li>
          <li>Read access to your project tool, and your last four client status reports</li>
        </ul>
        <p class="brt-audit__aside">Engagements are 50% upfront. Not for cash flow — a baseline week that nobody commits to doesn't get done, and without it there's nothing to measure against.</p>
      </div>
    </div>
  </section>

  <?php /* 07 — pricing without a number. The rate moves several times over the
           next few months; a published figure becomes an anchor to argue past. */ ?>
  <section class="brt-audit__section brt-audit__section--quiet">
    <h2>What it costs</h2>
    <p>Fixed price, quoted after the first call, once I know the size of your delivery team and how many client formats you're maintaining. No hourly billing and no change requests — the scope on this page is the scope.</p>
    <p>I'm currently running a small number of these at introductory rates while I build up published case studies. If that's of interest, it's worth talking sooner rather than later.</p>
    <?php echo brijraj_audit_button('pricing'); ?>
  </section>

  <?php /* 08 — who, briefly. Practitioner framing: the day job is the credential. */ ?>
  <section class="brt-audit__section brt-audit__who">
    <h2>Who I am</h2>
    <p><strong>Brij Raj Singh.</strong> Seventeen years in software delivery, most of it between the client and the engineering team — requirements, scoping and bidding, running delivery, and being the one who explains it when a date moves.</p>
    <p>I still do that day to day inside a services organisation, which is why what I install is in current use rather than theoretical. I take a small number of these engagements alongside it, deliberately — I'd rather do a few well than many at volume.</p>
  </section>

  <?php /* 09 — proof. Shared component: same reviews on home and about. */ ?>
  <section class="brt-audit__section">
    <?php echo brijraj_reviews_html([
        'layout'  => 'grid',
        'heading' => "What people who've worked with me say",
    ]); ?>
  </section>

  <?php /* 10 — FAQ. Same array feeds the schema, so the two cannot drift. */ ?>
  <section class="brt-audit__section">
    <h2>Questions</h2>
    <div class="brt-faq brt-audit__faq">
      <?php foreach (brijraj_audit_faqs() as $i => $f) : ?>
        <details class="brt-faq__item" data-faq="<?php echo esc_attr((string) ($i + 1)); ?>">
          <summary><?php echo esc_html($f['q']); ?></summary>
          <div class="brt-faq__a"><?php echo esc_html($f['a']); ?></div>
        </details>
      <?php endforeach; ?>
    </div>
  </section>

  <?php /* 11 — the form. */ ?>
  <section class="brt-audit__section brt-audit__formwrap" id="audit-form">
    <h2>See if it fits</h2>
    <p>A few questions so I know whether I can help before we spend time on a call. If it's not a fit I'll say so and tell you what would help instead.</p>
    <p class="brt-audit__aside">I reply within one working day.</p>

    <?php echo brijraj_audit_form($errors, $old); ?>
  </section>

</div>
    <?php

    return (string) ob_get_clean();
}

add_shortcode('brt_audit', static fn (): string => brijraj_audit_page());

/**
 * The form itself.
 *
 * @param list<string>        $errors
 * @param array<string,mixed> $old
 */
function brijraj_audit_form(array $errors = [], array $old = []): string
{
    ob_start();
    ?>
<?php // No #audit-form fragment on the action: a browser carries the fragment
      // through a 302, so a successful submission would land on
      // /audit/received/#audit-form and read as though it had not navigated
      // properly. The error path adds the fragment back when it needs to. ?>
<form class="brt-form brt-audit__form" method="post" action="<?php echo esc_url(home_url('/' . BRIJRAJ_AUDIT_SLUG . '/')); ?>" novalidate>
  <?php wp_nonce_field('brijraj_audit', 'brijraj_audit_nonce'); ?>
  <input type="hidden" name="brijraj_form" value="audit">
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

  <?php foreach (brijraj_audit_fields() as $key => $f) :
      $id   = 'brt_audit_' . $key;
      $val  = (string) ($old[$key] ?? '');
      $req  = ! empty($f['required']);
      $desc = ! empty($f['help']) ? ' aria-describedby="' . esc_attr($id) . '-help"' : '';
      ?>
    <div class="brt-form__row">
      <label class="brt-form__label" for="<?php echo esc_attr($id); ?>">
        <?php echo esc_html($f['label']); ?>
        <?php if (! $req) : ?><span class="brt-form__opt">optional</span><?php endif; ?>
      </label>

      <?php if (! empty($f['help'])) : ?>
        <span class="brt-form__help" id="<?php echo esc_attr($id); ?>-help"><?php echo esc_html($f['help']); ?></span>
      <?php endif; ?>

      <?php if ($f['type'] === 'textarea') : ?>
        <textarea class="brt-form__input brt-form__input--tall" id="<?php echo esc_attr($id); ?>"
                  name="<?php echo esc_attr(brijraj_field_name($key)); ?>"
                  rows="<?php echo esc_attr((string) ($f['rows'] ?? 4)); ?>"
                  <?php echo $req ? 'required aria-required="true"' : ''; ?>
                  <?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped above. ?>><?php echo esc_textarea($val); ?></textarea>

      <?php elseif ($f['type'] === 'select') : ?>
        <select class="brt-form__input brt-form__select" id="<?php echo esc_attr($id); ?>"
                name="<?php echo esc_attr(brijraj_field_name($key)); ?>"
                <?php echo $req ? 'required aria-required="true"' : ''; ?>
                <?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped above. ?>>
          <?php foreach (($f['options'] ?? []) as $ov => $ol) : ?>
            <option value="<?php echo esc_attr($ov); ?>" <?php selected($val, $ov); ?>><?php echo esc_html($ol); ?></option>
          <?php endforeach; ?>
        </select>

      <?php else : ?>
        <input class="brt-form__input" id="<?php echo esc_attr($id); ?>"
               name="<?php echo esc_attr(brijraj_field_name($key)); ?>"
               type="<?php echo esc_attr($f['type']); ?>" value="<?php echo esc_attr($val); ?>"
               <?php echo ! empty($f['autocomplete']) ? 'autocomplete="' . esc_attr($f['autocomplete']) . '"' : ''; ?>
               <?php echo $req ? 'required aria-required="true"' : ''; ?>
               <?php echo $desc; // phpcs:ignore WordPress.Security.EscapeOutput -- pre-escaped above. ?>>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>

  <?php // Honeypot — hidden from people, tempting to bots. Never display:none alone. ?>
  <div class="brt-hp" aria-hidden="true">
    <label for="brt_audit_website">Website</label>
    <input type="text" id="brt_audit_website" name="brtf_website" tabindex="-1" autocomplete="off">
  </div>

  <button type="submit" class="brt-btn brt-btn--primary brt-form__submit" data-cta="audit_submit" data-cta-location="form">Send this to Brij</button>

  <p class="brt-form__privacy">
    Used only to reply to you about this enquiry. Never sold or shared.
    See the <a href="/privacy-policy/">Privacy Policy</a>.
  </p>
</form>
    <?php

    return (string) ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Admin
 * ---------------------------------------------------------------------- */

add_action('add_meta_boxes', static function (): void {
    add_meta_box('brt_audit_detail', __('Enquiry', 'brijraj'), static function ($post): void {
        $fields = brijraj_audit_fields();

        echo '<table class="widefat striped"><tbody>';

        foreach ($fields as $key => $f) {
            $v = (string) get_post_meta($post->ID, '_brt_' . $key, true);

            if ($v === '') {
                continue;
            }

            if ($f['type'] === 'select') {
                $v = $f['options'][$v] ?? $v;
            }

            printf(
                '<tr><th style="width:240px;text-align:left">%s</th><td>%s</td></tr>',
                esc_html($f['label']),
                nl2br(esc_html($v))
            );
        }

        $qualified = get_post_meta($post->ID, '_brt_qualified', true) === '1';

        printf(
            '<tr><th style="text-align:left">%s</th><td><strong style="color:%s">%s</strong></td></tr>',
            esc_html__('Against criteria', 'brijraj'),
            $qualified ? '#3E7A5E' : '#8A6A1E',
            esc_html($qualified ? 'Meets the published criteria' : 'Outside criteria — read before deciding')
        );

        printf(
            '<tr><th style="text-align:left">%s</th><td>%s</td></tr>',
            esc_html__('Received', 'brijraj'),
            esc_html((string) get_post_meta($post->ID, '_brt_submitted_at', true))
        );

        $utm = get_post_meta($post->ID, '_brt_utm', true);

        if (is_array($utm) && $utm !== []) {
            $bits = [];
            foreach ($utm as $k => $v) {
                $bits[] = esc_html($k . '=' . $v);
            }
            printf(
                '<tr><th style="text-align:left">%s</th><td>%s</td></tr>',
                esc_html__('Campaign', 'brijraj'),
                implode(' &middot; ', $bits) // phpcs:ignore WordPress.Security.EscapeOutput -- escaped above.
            );
        }

        echo '</tbody></table>';

        $email = (string) get_post_meta($post->ID, '_brt_email', true);

        if ($email !== '') {
            printf(
                '<p style="margin-top:12px"><a class="button button-primary" href="mailto:%s">%s</a></p>',
                esc_attr($email),
                esc_html__('Reply by email', 'brijraj')
            );
        }
    }, BRIJRAJ_AUDIT_CPT, 'normal', 'high');
});

add_filter('manage_' . BRIJRAJ_AUDIT_CPT . '_posts_columns', static function (array $cols): array {
    return [
        'cb'            => $cols['cb'] ?? '',
        'title'         => __('Company / Name', 'brijraj'),
        'brt_fit'       => __('Fit', 'brijraj'),
        'brt_headcount' => __('Size', 'brijraj'),
        'brt_pms'       => __('PMs', 'brijraj'),
        'brt_email'     => __('Email', 'brijraj'),
        'date'          => __('Received', 'brijraj'),
    ];
});

add_action('manage_' . BRIJRAJ_AUDIT_CPT . '_posts_custom_column', static function (string $col, int $post_id): void {
    $fields = brijraj_audit_fields();

    if ($col === 'brt_fit') {
        $ok = get_post_meta($post_id, '_brt_qualified', true) === '1';
        printf(
            '<span style="color:%s;font-weight:600">%s</span>',
            $ok ? '#3E7A5E' : '#8A6A1E',
            esc_html($ok ? 'In criteria' : 'Outside')
        );
        return;
    }

    $map = [
        'brt_headcount' => 'headcount',
        'brt_pms'       => 'pms',
        'brt_email'     => 'email',
    ];

    if (! isset($map[$col])) {
        return;
    }

    $key = $map[$col];
    $v   = (string) get_post_meta($post_id, '_brt_' . $key, true);

    if ($col === 'brt_email' && $v !== '') {
        printf('<a href="mailto:%s">%s</a>', esc_attr($v), esc_html($v));
        return;
    }

    if (isset($fields[$key]['options'])) {
        $v = $fields[$key]['options'][$v] ?? $v;
    }

    echo esc_html($v);
}, 10, 2);

/* -------------------------------------------------------------------------
 * Schema
 * ---------------------------------------------------------------------- */

/**
 * Service and FAQPage markup for the offer page.
 *
 * Emitted here rather than in inc/seo.php because that module derives FAQs by
 * parsing post_content, and this page's content is rendered by a shortcode.
 * Both structures are built from the same arrays the page renders, so the
 * schema still cannot drift from what a visitor sees.
 *
 * Service, not Product: this is not a purchasable good, and mismatched schema
 * is worse than none.
 */
add_action('wp_head', static function (): void {
    if (! is_page(BRIJRAJ_AUDIT_SLUG)) {
        return;
    }

    $url = (string) get_permalink();

    $graph = [
        [
            '@type'       => 'Service',
            '@id'         => $url . '#service',
            'name'        => 'Delivery Reporting Audit & Install',
            'description' => 'A three-week engagement that measures how many hours a week an agency\'s delivery team loses to status reporting, then installs a reporting and escalation system the team owns afterwards.',
            'serviceType' => 'Delivery operations consulting',
            'provider'    => [
                '@type' => 'Person',
                'name'  => 'Brij Raj Singh',
                'url'   => home_url('/'),
            ],
            'areaServed'  => ['@type' => 'Place', 'name' => 'Worldwide (remote)'],
            'audience'    => [
                '@type'                => 'BusinessAudience',
                'name'                 => 'Software agencies and services firms, 20–150 people',
                'numberOfEmployees'    => ['@type' => 'QuantitativeValue', 'minValue' => 20, 'maxValue' => 150],
            ],
            'url'         => $url,
        ],
    ];

    $faqs = brijraj_audit_faqs();

    if (count($faqs) >= 2) {
        $graph[] = [
            '@type'      => 'FAQPage',
            '@id'        => $url . '#faq',
            'mainEntity' => array_map(static fn (array $f): array => [
                '@type'          => 'Question',
                'name'           => $f['q'],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
            ], $faqs),
        ];
    }

    printf(
        '<script type="application/ld+json">%s</script>' . "\n",
        wp_json_encode(['@context' => 'https://schema.org', '@graph' => $graph], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    );
}, 4);

/**
 * Keep the confirmation page out of the index.
 *
 * It is a dead end for a search visitor, and an indexed thank-you page skews
 * every conversion number on the site.
 */
add_action('wp_head', static function (): void {
    if (is_page(BRIJRAJ_AUDIT_DONE_SLUG)) {
        echo '<meta name="robots" content="noindex,nofollow">' . "\n";
    }
}, 1);

/* -------------------------------------------------------------------------
 * Analytics
 * ---------------------------------------------------------------------- */

/**
 * Events specific to the offer page.
 *
 * CTA clicks are handled by the shared listener in inc/cta.php, which routes
 * anything prefixed `audit_` to a single audit_cta_click. This adds the two
 * things that listener cannot see: engagement with the form, and which
 * objection a visitor actually opened.
 *
 * faq_open earns its place because it tells you which objection is live. If
 * "will you be available?" opens on most sessions, that answer needs to move
 * up the page.
 */
add_action('wp_footer', static function (): void {
    if (! is_page(BRIJRAJ_AUDIT_SLUG)) {
        return;
    }
    ?>
<script>
(function () {
  var form = document.querySelector('.brt-audit__form');
  if (!form) { return; }

  function ga(name, params) {
    if (typeof window.gtag === 'function') { window.gtag('event', name, params || {}); }
  }

  // Fires once per session: the signal is "someone engaged with the form",
  // not how many times they tabbed through it.
  var started = false;
  form.addEventListener('focusin', function () {
    if (started) { return; }
    started = true;
    ga('audit_form_start', { page_path: window.location.pathname });
  }, { passive: true });

  form.addEventListener('submit', function () {
    var size = form.querySelector('#brt_audit_headcount');
    var pms  = form.querySelector('#brt_audit_pms');
    ga('audit_form_submit', {
      headcount: size ? size.value : '',
      pm_count: pms ? pms.value : '',
      page_path: window.location.pathname
    });
  });

  document.querySelectorAll('.brt-audit__faq details').forEach(function (d) {
    d.addEventListener('toggle', function () {
      if (!d.open) { return; }
      var q = d.querySelector('summary');
      ga('audit_faq_open', { faq_question: q ? q.textContent.trim().slice(0, 90) : '' });
    });
  });
})();
</script>
    <?php
}, 21);
