<?php
/**
 * Inline SVG icons.
 *
 * Inline rather than a sprite or icon font: there are a dozen of them, they are
 * tiny, and inlining avoids an extra request and any flash of unstyled icon.
 * All are monochrome and inherit currentColor, so they pick up whatever the
 * surrounding text colour is and never fight the palette.
 *
 * The source icons deliberately avoid reproducing third-party brand logos.
 * Recognition comes from the label beside them ("Jira tickets", "Teams &
 * Slack"); the glyph only needs to say *what kind of thing* it is. That keeps
 * the set visually consistent and sidesteps trademark reproduction entirely.
 *
 * @package brijraj
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

/**
 * Return an inline SVG icon.
 *
 * @param string $name  Icon key.
 * @param int    $size  Pixel size (square).
 * @param string $class Extra class on the <svg>.
 */
function brijraj_icon(string $name, int $size = 18, string $class = ''): string
{
    $paths = [
        // Contact
        'linkedin' => '<path d="M4.98 3.5a2 2 0 1 1-4 0 2 2 0 0 1 4 0ZM.9 7.24h4.13V21H.9V7.24Zm6.7 0h3.96v1.88h.06c.55-1 1.9-2.06 3.9-2.06 4.17 0 4.94 2.6 4.94 6v7.94h-4.12v-7.04c0-1.68-.03-3.84-2.4-3.84-2.4 0-2.77 1.83-2.77 3.72V21H7.6V7.24Z" fill="currentColor"/>',
        'mail'     => '<path d="M3 5.5h18a1 1 0 0 1 1 1v11a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1v-11a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" fill="none"/><path d="m3 7 9 6 9-6" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'whatsapp' => '<path d="M12 2.5a9.4 9.4 0 0 0-8.1 14.1L2.6 21.5l5-1.3A9.4 9.4 0 1 0 12 2.5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" fill="none"/><path d="M8.9 8c.2-.5.4-.5.6-.5h.5c.2 0 .4 0 .6.5l.7 1.6c.1.3 0 .5-.1.7l-.4.5c-.1.2-.3.3-.1.6a7 7 0 0 0 3 2.6c.3.1.5.1.7-.1l.6-.7c.2-.2.4-.2.6-.1l1.6.8c.3.1.4.3.4.5a2 2 0 0 1-1.3 1.7c-.5.2-1.2.3-3.5-.7a10 10 0 0 1-4.2-4.3c-.8-1.6-.6-2.5-.4-3Z" fill="currentColor"/>',

        // Input sources — generic glyphs, identified by their labels
        'ticket'   => '<rect x="3" y="4.5" width="18" height="15" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M8 9.5h8M8 13.5h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>',
        'grid'     => '<rect x="3" y="4" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M3 9h18M3 14.5h18M9 4v16" stroke="currentColor" stroke-width="1.5"/>',
        'notes'    => '<path d="M6 3h9l4 4v14a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" fill="none"/><path d="M14.5 3v4.5H19M8.5 12.5h7M8.5 16.5h4.5" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" fill="none"/>',
        'chat'     => '<path d="M4 4.5h16a1 1 0 0 1 1 1v9a1 1 0 0 1-1 1H9l-4.4 3.6a.5.5 0 0 1-.8-.4V15.5H4a1 1 0 0 1-1-1v-9a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" fill="none"/>',
        'people'   => '<circle cx="9" cy="8.5" r="3.2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M2.8 19.5a6.2 6.2 0 0 1 12.4 0" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" fill="none"/><path d="M16.5 6.2a3.2 3.2 0 0 1 0 6M18 13.6a5.6 5.6 0 0 1 3.3 5.1" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" fill="none"/>',
        'alert'    => '<path d="M12 3.6 2.8 19.4a.9.9 0 0 0 .8 1.4h16.8a.9.9 0 0 0 .8-1.4L12 3.6Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round" fill="none"/><path d="M12 9.5v4.5M12 17.3h.01" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/>',
        'check'    => '<path d="m4 12.4 5.2 5.2L20 6.8" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
        'clipboard'=> '<rect x="5" y="4.5" width="14" height="16" rx="2" stroke="currentColor" stroke-width="1.7" fill="none"/><path d="M9 3h6a1 1 0 0 1 1 1v1.5a1 1 0 0 1-1 1H9a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z" stroke="currentColor" stroke-width="1.6" fill="none"/><path d="M9 11h6M9 15h4" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>',
        'arrow'    => '<path d="M5 12h13m0 0-5-5m5 5-5 5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" fill="none"/>',
    ];

    if (! isset($paths[$name])) {
        return '';
    }

    return sprintf(
        '<svg class="brt-ico%s" width="%1$d" height="%1$d" viewBox="0 0 24 24" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg">%s</svg>',
        $size,
        $class !== '' ? ' ' . esc_attr($class) : '',
        $paths[$name]
    );
}

/**
 * [brt_contact_pills] — LinkedIn / email / WhatsApp with icons.
 *
 * One definition used on every page, so the contact row can never drift out of
 * sync between About, the challenge page and the footer.
 *
 * @param array $atts style: pills|cards
 */
add_shortcode('brt_contact_pills', static function ($atts): string {
    $atts = shortcode_atts(['style' => 'pills'], is_array($atts) ? $atts : [], 'brt_contact_pills');
    $cards = $atts['style'] === 'cards';

    $items = [
        ['icon' => 'linkedin', 'label' => 'LinkedIn',          'sub' => 'Connect on LinkedIn',       'href' => 'https://www.linkedin.com/in/brijrajsinngh/', 'cta' => 'linkedin', 'ext' => true],
        ['icon' => 'mail',     'label' => 'brij@brijraj.tech',  'sub' => 'Email directly',            'href' => 'mailto:brij@brijraj.tech',                   'cta' => 'email',    'ext' => false],
        ['icon' => 'whatsapp', 'label' => 'WhatsApp',           'sub' => 'Quick conversation',        'href' => 'https://wa.me/917055565098',                 'cta' => 'whatsapp', 'ext' => true],
    ];

    $out = '<div class="' . ($cards ? 'brt-contactcards' : 'brt-contact') . '">';

    foreach ($items as $i) {
        $rel = $i['ext'] ? ' target="_blank" rel="noopener noreferrer"' : '';

        if ($cards) {
            $out .= sprintf(
                '<a class="brt-contactcard" href="%s" data-cta="%s"%s>%s<span class="brt-contactcard__text"><span class="brt-contactcard__label">%s</span><span class="brt-contactcard__sub">%s</span></span></a>',
                esc_url($i['href']),
                esc_attr($i['cta']),
                $rel,
                brijraj_icon($i['icon'], 20),
                esc_html($i['label']),
                esc_html($i['sub'])
            );
            continue;
        }

        $out .= sprintf(
            '<a href="%s" data-cta="%s"%s>%s<span>%s</span></a>',
            esc_url($i['href']),
            esc_attr($i['cta']),
            $rel,
            brijraj_icon($i['icon'], 17),
            esc_html($i['label'])
        );
    }

    return $out . '</div>';
});

/**
 * [brt_sources] — where project information actually comes from.
 */
add_shortcode('brt_sources', static function (): string {
    $sources = [
        ['ticket',    'Jira tickets'],
        ['grid',      'Excel trackers'],
        ['notes',     'Meeting notes'],
        ['mail',      'Email threads'],
        ['chat',      'Teams &amp; Slack'],
        ['alert',     'Risk logs'],
        ['people',    'Stakeholder inputs'],
        ['clipboard', 'Task updates'],
    ];

    $out = '<div class="brt-chips">';

    foreach ($sources as [$icon, $label]) {
        $out .= '<span class="brt-chip">' . brijraj_icon($icon, 16) . '<span>' . $label . '</span></span>';
    }

    return $out . '</div>';
});
