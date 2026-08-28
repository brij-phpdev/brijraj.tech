<?php
/**
 * HTML email template.
 *
 * Built for the audience rather than for the medium. The reference for this
 * was a consumer product announcement — big illustrations, screenshots, a
 * five-star pull quote. That style works for a B2C app launch and works
 * against a first reply to an agency owner who has just asked a serious
 * question: it reads as a broadcast, and broadcasts get archived.
 *
 * So this keeps the branded frame and drops the marketing furniture. Type,
 * colour and space do the work; there are no images beyond the wordmark, which
 * matters because most clients block images by default and a layout that
 * depends on them arrives broken.
 *
 * Compatibility notes, since email is not the web:
 * - Tables for layout. Flexbox and grid are unreliable in Outlook.
 * - Every style inline. Gmail strips much of a <style> block.
 * - 600px, the width every client handles without horizontal scroll.
 * - System font stack; no webfonts.
 * - Explicit background colours on every cell, so a dark-mode client cannot
 *   invert half the message and leave dark text on a dark ground.
 * - Every message is sent multipart with a real plain-text alternative.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Palette, matching the site.
 *
 * @return array<string,string>
 */
function brijraj_mail_palette(): array
{
    return [
        'navy'    => '#111D32',
        'ink'     => '#1B2430',
        'slate'   => '#4F5D70',
        'steel'   => '#3E5875',
        'accent'  => '#2F6FED',
        'border'  => '#DCDAD0',
        'ground'  => '#F4F3EC',
        'panel'   => '#FBFAF5',
        'white'   => '#FFFFFF',
    ];
}

/**
 * Wrap message content in the branded frame.
 *
 * @param string $preheader Shown in the inbox preview line, hidden in the body.
 * @param string $content   Ready-made HTML rows for the content area.
 */
function brijraj_mail_html(string $preheader, string $content): string
{
    $c    = brijraj_mail_palette();
    $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif";

    $links = [];
    foreach (brijraj_contact_links() as $l) {
        // mailto and tel do not belong in an email footer; the reply button
        // covers the first and the signature covers the second.
        if ($l['icon'] === 'mail') {
            continue;
        }
        $links[] = sprintf(
            '<a href="%s" style="color:%s;text-decoration:none;font-weight:600;">%s</a>',
            esc_url($l['href']),
            $c['steel'],
            esc_html($l['short'])
        );
    }

    $footer_links = implode(
        '<span style="color:' . $c['border'] . ';"> &nbsp;·&nbsp; </span>',
        $links
    );

    return '<!DOCTYPE html>
<html lang="en"><head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<meta name="color-scheme" content="light">
<title>BrijRaj.Tech</title>
</head>
<body style="margin:0;padding:0;background-color:' . $c['ground'] . ';">

<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent;height:0;width:0;">' . esc_html($preheader) . '</div>

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:' . $c['ground'] . ';">
<tr><td align="center" style="padding:28px 12px;">

  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="width:600px;max-width:100%;">

    <!-- brand bar -->
    <tr><td style="background-color:' . $c['navy'] . ';border-radius:12px 12px 0 0;padding:20px 32px;">
      <span style="font-family:' . $font . ';font-size:17px;font-weight:700;letter-spacing:-0.02em;color:#FFFFFF;">BrijRaj.Tech</span>
      <span style="font-family:' . $font . ';font-size:13px;color:rgba(255,255,255,0.55);"> &nbsp;·&nbsp; Delivery systems for software teams</span>
    </td></tr>

    <!-- content -->
    <tr><td style="background-color:' . $c['white'] . ';padding:32px;font-family:' . $font . ';">
      ' . $content . '
    </td></tr>

    <!-- signature -->
    <tr><td style="background-color:' . $c['white'] . ';border-top:1px solid ' . $c['border'] . ';padding:24px 32px;font-family:' . $font . ';">
      <p style="margin:0 0 2px;font-size:15px;font-weight:700;color:' . $c['navy'] . ';">Brij Raj Singh</p>
      <p style="margin:0 0 14px;font-size:13px;color:' . $c['slate'] . ';">Software delivery, client communication &amp; operations</p>
      <p style="margin:0;font-size:13px;color:' . $c['steel'] . ';">
        <a href="mailto:brij@brijraj.tech" style="color:' . $c['accent'] . ';text-decoration:none;font-weight:600;">brij@brijraj.tech</a>
        <span style="color:' . $c['border'] . ';"> &nbsp;·&nbsp; </span>
        <a href="https://brijraj.tech" style="color:' . $c['accent'] . ';text-decoration:none;font-weight:600;">brijraj.tech</a>
        <span style="color:' . $c['border'] . ';"> &nbsp;·&nbsp; </span>
        +91 70555 65098
      </p>
    </td></tr>

    <!-- footer -->
    <tr><td style="background-color:' . $c['panel'] . ';border-top:1px solid ' . $c['border'] . ';border-radius:0 0 12px 12px;padding:18px 32px;font-family:' . $font . ';">
      <p style="margin:0 0 6px;font-size:13px;">' . $footer_links . '</p>
      <p style="margin:0;font-size:12px;color:' . $c['slate'] . ';">You are receiving this because you contacted me through brijraj.tech. It is not a marketing list.</p>
    </td></tr>

  </table>

</td></tr>
</table>
</body></html>';
}

/**
 * A paragraph in the content area.
 */
function brijraj_mail_p(string $html, bool $lead = false): string
{
    $c = brijraj_mail_palette();

    return sprintf(
        '<p style="margin:0 0 14px;font-size:%s;line-height:1.6;color:%s;">%s</p>',
        $lead ? '17px' : '15px',
        $lead ? $c['ink'] : $c['slate'],
        $html
    );
}

/**
 * The heading at the top of the content area.
 */
function brijraj_mail_h(string $text): string
{
    $c = brijraj_mail_palette();

    return sprintf(
        '<h1 style="margin:0 0 18px;font-size:23px;line-height:1.25;font-weight:800;letter-spacing:-0.02em;color:%s;">%s</h1>',
        $c['navy'],
        esc_html($text)
    );
}

/**
 * A quiet panel for the "what happens next" detail.
 *
 * Set apart rather than shouted: it carries the practical information someone
 * actually needs, and a coloured alert box would overstate it.
 */
function brijraj_mail_panel(string $label, string $html): string
{
    $c = brijraj_mail_palette();

    return sprintf(
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%%" style="margin:4px 0 20px;">
          <tr><td style="background-color:%s;border-left:3px solid %s;border-radius:0 8px 8px 0;padding:16px 18px;">
            <p style="margin:0 0 6px;font-size:11px;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:%s;">%s</p>
            <p style="margin:0;font-size:14px;line-height:1.6;color:%s;">%s</p>
          </td></tr>
        </table>',
        $c['panel'],
        $c['accent'],
        $c['steel'],
        esc_html($label),
        $c['slate'],
        $html
    );
}

/**
 * A button.
 */
function brijraj_mail_button(string $url, string $label): string
{
    $c = brijraj_mail_palette();

    return sprintf(
        '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:6px 0 18px;">
          <tr><td style="background-color:%s;border-radius:8px;">
            <a href="%s" style="display:inline-block;padding:13px 26px;font-size:15px;font-weight:700;color:#FFFFFF;text-decoration:none;">%s</a>
          </td></tr>
        </table>',
        $c['accent'],
        esc_url($url),
        esc_html($label)
    );
}

/**
 * Send one message as multipart HTML + plain text.
 *
 * The plain-text part is not a courtesy. Some clients prefer it, some
 * corporate gateways strip HTML entirely, and a message with no text
 * alternative scores worse with spam filters — which matters more than usual
 * here, because these land as a first contact from a domain with almost no
 * sending history.
 */
function brijraj_send_html_mail(string $to, string $subject, string $html, string $text, array $headers = []): bool
{
    if (! is_email($to)) {
        return false;
    }

    $GLOBALS['brijraj_mail_alt'] = $text;

    $type = static fn (): string => 'text/html';
    add_filter('wp_mail_content_type', $type);

    $sent = (bool) wp_mail($to, $subject, $html, $headers);

    remove_filter('wp_mail_content_type', $type);
    unset($GLOBALS['brijraj_mail_alt']);

    return $sent;
}

/**
 * Attach the plain-text alternative to the outgoing message.
 */
add_action('phpmailer_init', static function ($phpmailer): void {
    $alt = (string) ($GLOBALS['brijraj_mail_alt'] ?? '');

    if ($alt !== '') {
        $phpmailer->AltBody = $alt;
    }
}, 20);
