<?php
/**
 * Outbound notifications.
 *
 * Two audiences with opposite needs, kept deliberately separate:
 *
 * - The owner's copy is an internal record. It carries the qualifying data in
 *   the subject so it is readable on a phone lock screen, and links straight
 *   into wp-admin.
 * - The sender's copy is a reply from a person. It never mentions wp-admin,
 *   never says "this is an automated message", and tells them exactly what
 *   happens next and when. It is the first thing a prospect reads from him,
 *   and a generic autoresponder undoes the work the page just did.
 *
 * Plain text on purpose: it renders identically everywhere, cannot trip an
 * image-blocking client, and reads like something a person typed.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Hand the response to the browser, then keep working.
 *
 * A form submission sends two emails, and each is an authenticated SMTP round
 * trip to Google. Done before the redirect that is six or seven seconds of the
 * visitor watching a spinning button for work that has nothing to do with
 * them. LiteSpeed (and FPM) can close the response early and let PHP carry on,
 * so the redirect lands immediately and the mail goes out behind it.
 *
 * Where neither function exists this is a no-op and the old behaviour stands:
 * slower, but never lost.
 */
function brijraj_finish_request_early(): void
{
    if (function_exists('litespeed_finish_request')) {
        litespeed_finish_request();
        return;
    }

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
    }
}

/**
 * Redirect, release the visitor, then run the slow work.
 *
 * The lead is always stored before this is called, so a mail failure after the
 * response has gone can never cost a submission.
 *
 * @param string        $url   Where the visitor goes.
 * @param callable|null $after Work to do once they have gone.
 */
function brijraj_redirect_then(string $url, ?callable $after = null): void
{
    wp_safe_redirect($url);
    brijraj_finish_request_early();

    if ($after !== null) {
        try {
            $after();
        } catch (\Throwable $e) {
            error_log('[brijraj] post-response task failed: ' . $e->getMessage());
        }
    }

    exit;
}

/**
 * First name only, for the greeting.
 *
 * "Hi Brij" reads like a person wrote it; "Hi Brij Raj Singh" reads like a
 * mail merge. Falls back to a name-less greeting rather than guessing.
 */
function brijraj_first_name(string $full): string
{
    $first = trim((string) strtok(trim($full), ' '));

    return $first !== '' ? $first : '';
}

/**
 * Greeting line.
 */
function brijraj_greeting(string $full): string
{
    $first = brijraj_first_name($full);

    return $first !== '' ? 'Hi ' . $first . ',' : 'Hi,';
}

/**
 * Send the confirmation that goes to whoever filled the form.
 *
 * @param string               $type audit|challenge
 * @param string               $to   Recipient address.
 * @param array<string, mixed> $data Submission values, for personalisation.
 */
function brijraj_send_confirmation(string $type, string $to, array $data = []): bool
{
    if (! is_email($to)) {
        return false;
    }

    $name    = (string) ($data['name'] ?? '');
    $company = trim((string) ($data['company'] ?? ''));
    $hi      = brijraj_greeting($name);
    $from    = get_bloginfo('name') . ' <' . brijraj_notification_email() . '>';
    $headers = ['Reply-To: ' . brijraj_notification_email(), 'From: ' . $from];

    if ($type === 'audit') {
        $subject   = $company !== ''
            ? sprintf('Got your note about reporting at %s', $company)
            : 'Got your note about delivery reporting';
        $preheader = 'I reply within one working day - here is what happens next.';

        $html = brijraj_mail_h('Got it — thanks.')
            . brijraj_mail_p(esc_html($hi))
            . brijraj_mail_p('Thanks for sending that over. It has reached me, and I read these properly rather than skimming them.', true)
            . brijraj_mail_panel('What happens next', 'I reply within <strong>one working day</strong>, usually sooner. If it looks like a fit I will suggest a 30-minute call — I hold slots on Tuesday and Thursday late mornings. If it is not a fit I will tell you that too, and point you at whatever I think would actually help instead.')
            . brijraj_mail_p('Nothing needed from you in the meantime.')
            . brijraj_mail_p('One thing worth knowing before we talk: the engagement starts with a week of light time tracking across your PMs. About two minutes a day each — it is what makes the result a measured number rather than an opinion.')
            . brijraj_mail_button(home_url('/audit/'), 'Re-read what the audit covers');

        $text = implode("\n", [
            $hi,
            '',
            'Thanks for sending that over - it has reached me, and I read these',
            'properly rather than skimming them.',
            '',
            'WHAT HAPPENS NEXT',
            'I reply within one working day, usually sooner. If it looks like a fit',
            'I will suggest a 30-minute call - I hold slots on Tuesday and Thursday',
            'late mornings. If it is not a fit I will tell you that too, and point',
            'you at whatever I think would actually help instead.',
            '',
            'Nothing needed from you in the meantime.',
            '',
            'One thing worth knowing before we talk: the engagement starts with a',
            'week of light time tracking across your PMs. About two minutes a day',
            'each - it is what makes the result a measured number rather than an',
            'opinion.',
            '',
            home_url('/audit/'),
        ]);
    } elseif ($type === 'challenge') {
        $subject   = 'Got your delivery challenge';
        $preheader = 'I read every one of these personally. Give me a couple of working days.';

        $html = brijraj_mail_h('Got it — thanks.')
            . brijraj_mail_p(esc_html($hi))
            . brijraj_mail_p('Thanks for writing that up. It has reached me.', true)
            . brijraj_mail_panel('What happens next', 'I read every one of these personally, and I reply when it is something I can genuinely help with rather than sending everyone the same answer. Give me <strong>a couple of working days</strong>.')
            . brijraj_mail_p('If it is a coordination or reporting problem specifically, that is the work I spend most of my time on — so expect a longer reply.');

        $text = implode("\n", [
            $hi,
            '',
            'Thanks for writing that up - it has reached me.',
            '',
            'WHAT HAPPENS NEXT',
            'I read every one of these personally, and I reply when it is something',
            'I can genuinely help with rather than sending everyone the same answer.',
            'Give me a couple of working days.',
            '',
            'If it is a coordination or reporting problem specifically, that is the',
            'work I spend most of my time on, so expect a longer reply.',
        ]);
    } else {
        return false;
    }

    // The plain-text alternative carries the signature the HTML frame already
    // shows, so both halves of the message end the same way.
    $text .= "\n" . brijraj_mail_signature() . "\n";

    return brijraj_send_html_mail(
        $to,
        $subject,
        brijraj_mail_html($preheader, $html),
        $text,
        $headers
    );
}

/* -------------------------------------------------------------------------
 * Signature
 * ---------------------------------------------------------------------- */

/**
 * The sign-off appended to outgoing mail.
 *
 * Plain text on purpose. An HTML signature with a logo is the usual reflex,
 * but images are blocked by default in most clients, they add tracking-pixel
 * suspicion to a cold first contact, and a plain block renders identically
 * everywhere. This reads as a person with a real practice rather than a
 * template.
 *
 * Separated with the standard "-- " sigdash, which mail clients recognise and
 * collapse in replies, so it does not accumulate down a long thread.
 */
function brijraj_mail_signature(): string
{
    return (string) apply_filters('brijraj_mail_signature', implode("\n", [
        '',
        '-- ',
        'Brij Raj Singh',
        'Delivery systems for software teams',
        '',
        'brijraj.tech  |  brij@brijraj.tech',
        'LinkedIn   linkedin.com/in/brijrajsinngh',
        'WhatsApp   +91 70555 65098',
        'Instagram  instagram.com/brijraj.tech',
    ]));
}

/**
 * Append the signature to every outgoing message.
 *
 * Applied centrally rather than per template, so a new form or a future
 * WordPress-generated mail cannot go out unsigned.
 *
 * Two exclusions. Notifications addressed to the owner are an internal record
 * and do not need a business card, and any message already carrying a sigdash
 * is left alone so nothing is signed twice.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
add_filter('wp_mail', static function (array $args): array {
    $to = $args['to'] ?? '';
    $to = is_array($to) ? implode(',', $to) : (string) $to;

    if (str_contains(strtolower($to), strtolower(brijraj_notification_email()))) {
        return $args;
    }

    $body = (string) ($args['message'] ?? '');

    if ($body === '' || str_contains($body, "\n-- \n")) {
        return $args;
    }

    // Only sign plain text. An HTML message carries the signature inside its
    // own frame, and appending this block to it drops raw text below the
    // markup.
    //
    // Content type is checked three ways because it can be set three ways: as
    // a header, through the wp_mail_content_type filter (which is how the
    // HTML confirmations do it, so a header check alone misses them), or
    // implied by the body itself.
    $headers = $args['headers'] ?? [];
    $headers = is_array($headers) ? implode(' ', $headers) : (string) $headers;

    if (stripos($headers, 'text/html') !== false) {
        return $args;
    }

    if (isset($GLOBALS['brijraj_mail_alt'])) {
        return $args;
    }

    if (stripos((string) apply_filters('wp_mail_content_type', 'text/plain'), 'text/html') !== false) {
        return $args;
    }

    if (stripos(ltrim($body), '<!doctype html') === 0 || stripos(ltrim($body), '<html') === 0) {
        return $args;
    }

    $args['message'] = rtrim($body) . "\n" . brijraj_mail_signature() . "\n";

    return $args;
}, 20);
