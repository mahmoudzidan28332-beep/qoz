<?php
/**
 * frontend/partials/store_sections/icons.php
 * Central SVG icon library — replaces all emoji across store sections
 *
 * Usage: <?= icon('star', 18, 'var(--pub-primary)') ?>
 * Size defaults to 16. Color defaults to currentColor.
 */

if (!function_exists('icon')) {
    function icon(string $name, int $size = 16, string $color = 'currentColor'): string
    {
        $s = $size;
        $c = htmlspecialchars($color, ENT_QUOTES);

        $paths = [

            /* ── Stars & Rating ───────────────────────────────── */
            'star' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='$c' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 1.5l2.32 4.7 5.18.75-3.75 3.66.89 5.16L10 13.27l-4.64 2.5.89-5.16L2.5 6.95l5.18-.75z'/>"
                . "</svg>",

            'star-outline' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 1.5l2.32 4.7 5.18.75-3.75 3.66.89 5.16L10 13.27l-4.64 2.5.89-5.16L2.5 6.95l5.18-.75z'/>"
                . "</svg>",

            'star-half' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'>"
                . "<defs><clipPath id='h'><rect x='0' y='0' width='10' height='20'/></clipPath></defs>"
                . "<path d='M10 1.5l2.32 4.7 5.18.75-3.75 3.66.89 5.16L10 13.27l-4.64 2.5.89-5.16L2.5 6.95l5.18-.75z' fill='none' stroke='$c' stroke-width='1.4'/>"
                . "<path d='M10 1.5l2.32 4.7 5.18.75-3.75 3.66.89 5.16L10 13.27l-4.64 2.5.89-5.16L2.5 6.95l5.18-.75z' fill='$c' clip-path='url(#h)'/>"
                . "</svg>",

            /* ── User / People ─────────────────────────────────── */
            'user' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='7' r='3.5'/>"
                . "<path d='M3 18c0-3.866 3.134-7 7-7s7 3.134 7 7'/>"
                . "</svg>",

            'user-circle' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='8.5'/>"
                . "<circle cx='10' cy='8' r='2.8'/>"
                . "<path d='M4.5 17c.8-2.6 3-4.5 5.5-4.5s4.7 1.9 5.5 4.5'/>"
                . "</svg>",

            /* ── Calendar / Time ───────────────────────────────── */
            'calendar' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='2' y='3.5' width='16' height='14' rx='2'/>"
                . "<path d='M2 8h16M6.5 2v3M13.5 2v3'/>"
                . "</svg>",

            'clock' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='8'/>"
                . "<path d='M10 5.5V10l3 2.5'/>"
                . "</svg>",

            /* ── Communication ─────────────────────────────────── */
            'send' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M17.5 2.5L1 8.5l6 2.5 2.5 6.5 8-15z'/>"
                . "<path d='M7 11l4.5-4.5'/>"
                . "</svg>",

            'message' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M17 1.5H3a1.5 1.5 0 00-1.5 1.5v11A1.5 1.5 0 003 15.5h3.5L10 19l3.5-3.5H17a1.5 1.5 0 001.5-1.5V3A1.5 1.5 0 0017 1.5z'/>"
                . "</svg>",

            /* ── Edit / Action ─────────────────────────────────── */
            'edit' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M14.5 2.5l3 3L6 17H3v-3L14.5 2.5z'/>"
                . "</svg>",

            'pen' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M3 17l1-4L13.5 3.5a2.12 2.12 0 013 3L7 17H3z'/>"
                . "<path d='M11 5.5l3 3'/>"
                . "</svg>",

            /* ── Copy / Clipboard ──────────────────────────────── */
            'copy' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='7' y='7' width='10' height='11' rx='1.5'/>"
                . "<path d='M4.5 13.5H3a1.5 1.5 0 01-1.5-1.5V3A1.5 1.5 0 013 1.5h9A1.5 1.5 0 0113.5 3v1.5'/>"
                . "</svg>",

            'check' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='2' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M3 10l5 5L17 5'/>"
                . "</svg>",

            'check-circle' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='8'/>"
                . "<path d='M6.5 10l2.5 2.5 4.5-5'/>"
                . "</svg>",

            'x-circle' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='8'/>"
                . "<path d='M7 7l6 6M13 7l-6 6'/>"
                . "</svg>",

            /* ── Location / Map ────────────────────────────────── */
            'pin' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 1.5C7.015 1.5 4.5 4.015 4.5 7c0 4.5 5.5 11 5.5 11s5.5-6.5 5.5-11c0-2.985-2.515-5.5-5.5-5.5z'/>"
                . "<circle cx='10' cy='7' r='2'/>"
                . "</svg>",

            'map' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M1.5 4.5l5.5 2 6-3 5.5 2v12l-5.5-2-6 3-5.5-2V4.5z'/>"
                . "<path d='M7 6.5v12M13 3.5v12'/>"
                . "</svg>",

            'navigation' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M18 2L1 9l6.5 3L11 18.5 18 2z'/>"
                . "<path d='M7.5 12L18 2'/>"
                . "</svg>",

            /* ── Tags / Offers ─────────────────────────────────── */
            'tag' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10.5 1.5H17.5V8.5L9.207 16.793a1 1 0 01-1.414 0L2.207 11.207a1 1 0 010-1.414L10.5 1.5z'/>"
                . "<circle cx='14' cy='5' r='1' fill='$c' stroke='none'/>"
                . "</svg>",

            'gift' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='1.5' y='7' width='17' height='11' rx='1.5'/>"
                . "<rect x='4' y='3.5' width='12' height='4' rx='1'/>"
                . "<path d='M10 3.5C10 3.5 8 1.5 6.5 2.5S6 6 10 4.5'/>"
                . "<path d='M10 3.5c0 0 2-2 3.5-1s.5 3.5-3.5 2'/>"
                . "<path d='M10 7v11'/>"
                . "</svg>",

            'percent' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M4 16L16 4'/>"
                . "<circle cx='5.5' cy='5.5' r='2.5'/>"
                . "<circle cx='14.5' cy='14.5' r='2.5'/>"
                . "</svg>",

            /* ── Info / Status ─────────────────────────────────── */
            'info' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='8'/>"
                . "<path d='M10 9v5.5'/>"
                . "<circle cx='10' cy='6.5' r='.75' fill='$c' stroke='none'/>"
                . "</svg>",

            'alert' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 2L18.5 17H1.5L10 2z'/>"
                . "<path d='M10 9v4'/>"
                . "<circle cx='10' cy='14.5' r='.75' fill='$c' stroke='none'/>"
                . "</svg>",

            'shield' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 1.5l7.5 3v5c0 4.5-3.5 7.5-7.5 8.5C6.5 17 3 14 3 9.5v-5L10 1.5z'/>"
                . "<path d='M7 10l2 2 4-4'/>"
                . "</svg>",

            'lock' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='3.5' y='9' width='13' height='9.5' rx='1.5'/>"
                . "<path d='M6.5 9V6.5a3.5 3.5 0 017 0V9'/>"
                . "</svg>",

            /* ── Commerce ──────────────────────────────────────── */
            'cart' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M1.5 1.5h2.5l2 9.5h9.5l2-7H5'/>"
                . "<circle cx='8' cy='16.5' r='1.5'/>"
                . "<circle cx='14.5' cy='16.5' r='1.5'/>"
                . "</svg>",

            'bag' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M3 5.5h14l-1.5 11H4.5L3 5.5z'/>"
                . "<path d='M7.5 5.5V4a2.5 2.5 0 015 0v1.5'/>"
                . "</svg>",

            'card' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='1.5' y='4' width='17' height='12' rx='2'/>"
                . "<path d='M1.5 8h17'/>"
                . "<path d='M5 13h4'/>"
                . "</svg>",

            'cash' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='1.5' y='4.5' width='17' height='11' rx='1.5'/>"
                . "<circle cx='10' cy='10' r='2.5'/>"
                . "<path d='M1.5 7.5h3M15.5 7.5h3M1.5 12.5h3M15.5 12.5h3'/>"
                . "</svg>",

            /* ── Building / Store ──────────────────────────────── */
            'store' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M1.5 7.5h17v10.5H1.5z'/>"
                . "<path d='M1.5 7.5L4 2h12l2.5 5.5'/>"
                . "<path d='M7.5 18V13h5v5'/>"
                . "<path d='M1.5 7.5C1.5 9.157 2.843 10.5 4.5 10.5S7.5 9.157 7.5 7.5'/>"
                . "<path d='M7.5 7.5C7.5 9.157 8.843 10.5 10.5 10.5S13.5 9.157 13.5 7.5'/>"
                . "<path d='M13.5 7.5c0 1.657 1.343 3 3 3s3-1.343 3-3'/>"
                . "</svg>",

            'building' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='2' y='2' width='16' height='17' rx='1'/>"
                . "<path d='M6 6h2M12 6h2M6 10h2M12 10h2M8 19v-5h4v5'/>"
                . "</svg>",

            'truck' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M1.5 4h11v10.5H1.5z'/>"
                . "<path d='M12.5 7h4l2 4v3.5h-6V7z'/>"
                . "<circle cx='5' cy='14.5' r='1.5'/>"
                . "<circle cx='15.5' cy='14.5' r='1.5'/>"
                . "</svg>",

            /* ── Misc UI ───────────────────────────────────────── */
            'chevron-down' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M4.5 7.5L10 13l5.5-5.5'/>"
                . "</svg>",

            'chevron-right' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M7.5 4.5L13 10l-5.5 5.5'/>"
                . "</svg>",

            'share' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='15.5' cy='4' r='2'/>"
                . "<circle cx='4' cy='10' r='2'/>"
                . "<circle cx='15.5' cy='16' r='2'/>"
                . "<path d='M6 10.9l7.5 3.6M13.5 5.4L6 8.9'/>"
                . "</svg>",

            'tools' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M14.5 2a3 3 0 00-3 3c0 .4.08.78.2 1.14L3.5 14.5a1 1 0 000 1.41l.6.6a1 1 0 001.4 0l8.36-8.2c.36.12.74.19 1.14.19a3 3 0 000-6z'/>"
                . "</svg>",

            'settings' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='2.5'/>"
                . "<path d='M10 1.5v2M10 16.5v2M1.5 10h2M16.5 10h2M4.1 4.1l1.4 1.4M14.5 14.5l1.4 1.4M4.1 15.9l1.4-1.4M14.5 5.5l1.4-1.4'/>"
                . "</svg>",

            'list' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M3 5h14M3 10h14M3 15h14'/>"
                . "</svg>",

            'sparkle' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.3' stroke-linecap='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 1.5v4M10 14.5v4M1.5 10h4M14.5 10h4'/>"
                . "<path d='M3.9 3.9l2.8 2.8M13.3 13.3l2.8 2.8M13.3 6.7l2.8-2.8M3.9 16.1l2.8-2.8'/>"
                . "<circle cx='10' cy='10' r='3'/>"
                . "</svg>",

            'phone' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M5.5 1.5h3l1.5 4-2 1.5a10 10 0 004 4L13.5 9l4 1.5v3a2 2 0 01-2 2C6.716 15.5 1.5 6.284 1.5 3.5a2 2 0 012-2z'/>"
                . "</svg>",

            'mail' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='1.5' y='4' width='17' height='12' rx='2'/>"
                . "<path d='M1.5 6l8.5 6 8.5-6'/>"
                . "</svg>",

            'globe' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='8.5'/>"
                . "<path d='M10 1.5c-2.5 2-4 5-4 8.5s1.5 6.5 4 8.5'/>"
                . "<path d='M10 1.5c2.5 2 4 5 4 8.5s-1.5 6.5-4 8.5'/>"
                . "<path d='M1.5 10h17'/>"
                . "</svg>",

            'link' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M8 11.5a3.5 3.5 0 005 0l3-3a3.535 3.535 0 00-5-5l-1.5 1.5'/>"
                . "<path d='M12 8.5a3.5 3.5 0 00-5 0l-3 3a3.535 3.535 0 005 5l1.5-1.5'/>"
                . "</svg>",

            'hourglass' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M4 1.5h12M4 18.5h12M5 1.5C5 7 9 10 9 10s-4 3-4 8.5'/>"
                . "<path d='M15 1.5C15 7 11 10 11 10s4 3 4 8.5'/>"
                . "</svg>",

            'orders' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='3' y='1.5' width='14' height='17' rx='1.5'/>"
                . "<path d='M6.5 6.5h7M6.5 10h7M6.5 13.5h4'/>"
                . "</svg>",

            'verified' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='$c' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 1l2.5 2.2L15 2l1 2.9 3 .4-1.5 2.8 1.5 2.8-3 .4-1 2.9-2.5-1.2L10 19l-2.5-2-2.5 1.2-1-2.9-3-.4 1.5-2.8L1 9.5l3-.4 1-2.9 2.5 1.2L10 1z'/>"
                . "<path d='M7 10l2 2 4-4' fill='none' stroke='white' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/>"
                . "</svg>",

            'dot-open' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='5' fill='#22c55e'/>"
                . "<circle cx='10' cy='10' r='3' fill='white'/>"
                . "</svg>",

            'dot-closed' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='5' fill='#ef4444'/>"
                . "<circle cx='10' cy='10' r='3' fill='white'/>"
                . "</svg>",

            'dot-pulse' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='7' fill='#22c55e' opacity='0.25'/>"
                . "<circle cx='10' cy='10' r='4' fill='#22c55e'/>"
                . "</svg>",

            'search' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='8.5' cy='8.5' r='6'/>"
                . "<path d='M13 13l4.5 4.5'/>"
                . "</svg>",

            'image' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='1.5' y='3' width='17' height='14' rx='2'/>"
                . "<circle cx='7' cy='8' r='2'/>"
                . "<path d='M1.5 15l4.5-4.5 3 3 3-3 5 5'/>"
                . "</svg>",

            'house' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M3 9.5L10 4l7 5.5V17a1 1 0 01-1 1h-3v-6H7v6H4a1 1 0 01-1-1V9.5z'/>"
                . "</svg>",

            'grid' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<rect x='3' y='3' width='6' height='6' rx='1'/>"
                . "<rect x='11' y='3' width='6' height='6' rx='1'/>"
                . "<rect x='3' y='11' width='6' height='6' rx='1'/>"
                . "<rect x='11' y='11' width='6' height='6' rx='1'/>"
                . "</svg>",

            'x' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.6' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M15 5L5 15M5 5l10 10'/>"
                . "</svg>",

            'cart-x' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M1.5 1.5h2.5l2 9.5h9.5l2-7H5'/>"
                . "<circle cx='8' cy='16.5' r='1.5'/>"
                . "<circle cx='14.5' cy='16.5' r='1.5'/>"
                . "<path d='M14 1.5l-3 3M11 1.5l3 3'/>"
                . "</svg>",

            'check-lg' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M2 10l5 5 11-11'/>"
                . "</svg>",

            'rocket' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M17.5 2.5s-4 0-7 3c-2 2-3 5-3 5l-4 4v3h3l4-4s3-1 5-3c3-3 3-7 3-7zM7.5 12.5L3 17'/>"
                . "<circle cx='13.5' cy='6.5' r='1.5'/>"
                . "</svg>",

            'box' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M2.5 5.5l7.5-3 7.5 3v9l-7.5 3-7.5-3v-9zM10 2.5V17m-7.5-11.5l15 6M2.5 11l7.5 3 7.5-3'/>"
                . "</svg>",

            'inbox' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M3 3h14l1.5 6H13a3 3 0 01-6 0H1.5L3 3z'/>"
                . "<path d='M2 10v6a2 2 0 002 2h12a2 2 0 002-2v-6'/>"
                . "</svg>",

            'funnel' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M2.5 3h15l-6 7.5v6.5l-3-3V10.5L2.5 3z'/>"
                . "</svg>",

            'headset' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M3.5 10v2a6.5 6.5 0 0013 0v-2'/>"
                . "<path d='M2 10a8 8 0 0116 0M2.5 10c0-1 1-2 2-2h1c1 0 1.5.5 1.5 1.5v3c0 1-.5 1.5-1.5 1.5h-1c-1 0-2-1-2-2v-2zm15 0c0-1-1-2-2-2h-1c-1 0-1.5.5-1.5 1.5v3c0 1 .5 1.5 1.5 1.5h1c1 0 2-1 2-2v-2z'/>"
                . "</svg>",

            'wallet' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M16 6V4a2 2 0 00-2-2H3a2 2 0 00-2 2v12a2 2 0 002 2h11.5a2 2 0 002-2v-2'/>"
                . "<path d='M14 6h3.5a1.5 1.5 0 011.5 1.5v5a1.5 1.5 0 01-1.5 1.5H14a2 2 0 01-2-2v-4a2 2 0 012-2z'/>"
                . "<circle cx='15' cy='10' r='1' fill='$c' stroke='none'/>"
                . "</svg>",

            'award' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='7' r='5.5'/>"
                . "<path d='M7 12l-1.5 6.5 4.5-2.5 4.5 2.5-1.5-6.5'/>"
                . "</svg>",

            'gear' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<circle cx='10' cy='10' r='2.5'/>"
                . "<path d='M10 2l1 2M15.5 4.5l-1.5 1.5M18 10l-2 1M15.5 15.5l-1.5-1.5M10 18l-1-2M4.5 15.5l1.5-1.5M2 10l2-1M4.5 4.5l1.5 1.5'/>"
                . "</svg>",

            'heart' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 17.5l-1.35-1.24C4.1 12.1 1.5 9.5 1.5 6.4c0-2.4 1.9-4.3 4.3-4.3 1.4 0 2.7.7 3.6 1.7.9-1.1 2.2-1.7 3.6-1.7 2.4 0 4.3 1.9 4.3 4.3 0 3.1-2.6 5.7-7.15 9.86L10 17.5z'/>"
                . "</svg>",

            'heart-fill' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='$c' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M10 17.5l-1.35-1.24C4.1 12.1 1.5 9.5 1.5 6.4c0-2.4 1.9-4.3 4.3-4.3 1.4 0 2.7.7 3.6 1.7.9-1.1 2.2-1.7 3.6-1.7 2.4 0 4.3 1.9 4.3 4.3 0 3.1-2.6 5.7-7.15 9.86L10 17.5z'/>"
                . "</svg>",

            'eye' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M1.5 10s3.5-7 8.5-7 8.5 7 8.5 7-3.5 7-8.5 7-8.5-7-8.5-7z'/>"
                . "<circle cx='10' cy='10' r='3'/>"
                . "</svg>",

            'arrow-left-right' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M13.5 14.5L18 10l-4.5-4.5M6.5 5.5L2 10l4.5 4.5M2 10h16'/>"
                . "</svg>",

            'cart-plus' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M1.5 1.5h2.5l2 9.5h9.5l2-7H5'/>"
                . "<circle cx='8' cy='16.5' r='1.5'/>"
                . "<circle cx='14.5' cy='16.5' r='1.5'/>"
                . "<path d='M13 1.5v5M10.5 4h5'/>"
                . "</svg>",

            'file-text' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M13 1.5H4.5a1.5 1.5 0 00-1.5 1.5v14a1.5 1.5 0 001.5 1.5h11a1.5 1.5 0 001.5-1.5V6.5L13 1.5z'/>"
                . "<path d='M13 1.5v5h5M6.5 11h7M6.5 14h7'/>"
                . "</svg>",

            'arrow-return' =>
                "<svg width='$s' height='$s' viewBox='0 0 20 20' fill='none' stroke='$c' stroke-width='1.4' stroke-linecap='round' stroke-linejoin='round' xmlns='http://www.w3.org/2000/svg'>"
                . "<path d='M7.5 4.5L3 9l4.5 4.5'/>"
                . "<path d='M3 9h10a4 4 0 010 8h-2'/>"
                . "</svg>",

        ];

        return $paths[$name] ?? '';
    }
}