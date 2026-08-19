{{--
    Inline SVG icons, replacing Font Awesome.

    Font Awesome came from cdnjs as a 100KB stylesheet plus a webfont, for the
    forty-odd glyphs this app actually uses — and when cdnjs was unreachable
    every icon in the interface rendered as an empty square. The header became
    a row of blank buttons with no way to tell logout from the admin link.
    These ship in the HTML, so there is nothing left to fail.

    One 24x24 stroked grid throughout, drawn with currentColor, so an icon
    takes the colour and size of whatever it sits in exactly as the font did.
    Unknown names render nothing rather than throwing: a typo should cost an
    icon, not the page.

    `solid` fills the shape instead of stroking it, and only a couple of icons
    are drawn as closed outlines that survive being filled. Font Awesome's
    fa-solid was on nearly every icon in this app, so honouring it literally
    turned brains, pie charts and clocks into unreadable blobs — the outline
    paths below describe an object's *edges*, and filling those edges floods
    the whole shape. It is honoured only where the filled form carries meaning
    and the path is closed: a watchlisted star against an unwatched one.
--}}
@props(['name', 'class' => 'w-4 h-4', 'solid' => false])

@php
    // Paths are the drawing only; the svg element below supplies the shared
    // 24x24 viewBox, stroke width and line joins.
    $paths = [
        'arrow-left' => '<path d="M19 12H5M12 19l-7-7 7-7"/>',
        'arrow-right' => '<path d="M5 12h14M12 5l7 7-7 7"/>',
        'arrow-trend-up' => '<path d="M23 6l-9.5 9.5-5-5L1 18"/><path d="M17 6h6v6"/>',
        'bars' => '<path d="M3 12h18M3 6h18M3 18h18"/>',
        'bell' => '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/>',
        'bolt' => '<path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/>',
        'brain' => '<path d="M12 5a3 3 0 10-5.9.8A3 3 0 004 9.5a3 3 0 001.5 2.6A3 3 0 007 17a3 3 0 005 1.8V5z"/><path d="M12 5a3 3 0 115.9.8A3 3 0 0120 9.5a3 3 0 01-1.5 2.6A3 3 0 0117 17a3 3 0 01-5 1.8V5z"/>',
        'calculator' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 10h.01M12 10h.01M16 10h.01M8 14h.01M12 14h.01M16 14h.01M8 18h8"/>',
        'calendar-check' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M9 16l2 2 4-4"/>',
        'chart-line' => '<path d="M3 3v18h18"/><path d="M7 14l4-4 3 3 5-6"/>',
        'chart-pie' => '<path d="M21.2 15.9A10 10 0 118.1 2.8"/><path d="M22 12A10 10 0 0012 2v10z"/>',
        'chart-simple' => '<path d="M6 20V10M12 20V4M18 20v-6"/>',
        'check' => '<path d="M20 6L9 17l-5-5"/>',
        'circle' => '<circle cx="12" cy="12" r="9"/>',
        'clock-rotate-left' => '<path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l3 2"/>',
        'cloud-rain' => '<path d="M20 16.6A5 5 0 0018 7h-1.3A8 8 0 104 15.2"/><path d="M8 19v2M12 20v2M16 19v2"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
        'fire' => '<path d="M12 2s4 4 4 8a4 4 0 01-8 0c0-1.5.8-2.8.8-2.8S6 10 6 14a6 6 0 0012 0c0-6-6-12-6-12z"/>',
        'flask' => '<path d="M9 2h6M10 2v6.5L4.5 18A2 2 0 006.2 21h11.6a2 2 0 001.7-3L14 8.5V2"/><path d="M7 15h10"/>',
        'ghost' => '<path d="M12 2a8 8 0 00-8 8v12l3-2 3 2 2-2 2 2 3-2 3 2V10a8 8 0 00-8-8z"/><path d="M9 10h.01M15 10h.01"/>',
        'hourglass-half' => '<path d="M6 2h12M6 22h12"/><path d="M6 2v4a6 6 0 006 6 6 6 0 006-6V2"/><path d="M6 22v-4a6 6 0 016-6 6 6 0 016 6v4"/>',
        'layer-group' => '<path d="M12 2L2 7l10 5 10-5-10-5z"/><path d="M2 17l10 5 10-5"/><path d="M2 12l10 5 10-5"/>',
        'link-slash' => '<path d="M9 17H7A5 5 0 017 7h2"/><path d="M15 7h2a5 5 0 013.5 8.5"/><path d="M8 12h4"/><path d="M2 2l20 20"/>',
        'map' => '<path d="M9 3L3 6v15l6-3 6 3 6-3V3l-6 3-6-3z"/><path d="M9 3v15M15 6v15"/>',
        'moon' => '<path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/>',
        // The five sentiment faces share a head and eyes; only the mouth moves.
        'face-laugh-beam' => '<circle cx="12" cy="12" r="9"/><path d="M8 14s1.5 2.5 4 2.5 4-2.5 4-2.5"/><path d="M8.5 9.5s.5-1 1.5-1 1.5 1 1.5 1M12.5 9.5s.5-1 1.5-1 1.5 1 1.5 1"/>',
        'face-smile' => '<circle cx="12" cy="12" r="9"/><path d="M8 14.5s1.3 2 4 2 4-2 4-2"/><path d="M9 9.5h.01M15 9.5h.01"/>',
        'face-meh' => '<circle cx="12" cy="12" r="9"/><path d="M8.5 15h7"/><path d="M9 9.5h.01M15 9.5h.01"/>',
        'face-frown' => '<circle cx="12" cy="12" r="9"/><path d="M8 16.5s1.3-2 4-2 4 2 4 2"/><path d="M9 9.5h.01M15 9.5h.01"/>',
        'face-dizzy' => '<circle cx="12" cy="12" r="9"/><path d="M8 16.5s1.3-2 4-2 4 2 4 2"/><path d="M8 8.5l2 2M10 8.5l-2 2M14 8.5l2 2M16 8.5l-2 2"/>',
        'newspaper' => '<path d="M4 4h13a2 2 0 012 2v13a2 2 0 002 2H5a2 2 0 01-2-2V4z"/><path d="M8 8h6M8 12h6M8 16h4"/>',
        'paper-plane' => '<path d="M22 2L11 13"/><path d="M22 2l-7 20-4-9-9-4 20-7z"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'right-from-bracket' => '<path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5"/><path d="M21 12H9"/>',
        'robot' => '<rect x="4" y="8" width="16" height="12" rx="2"/><path d="M12 8V4M9 4h6"/><path d="M9 13h.01M15 13h.01M9 17h6"/>',
        'satellite-dish' => '<path d="M4 10a10 10 0 0110 10"/><path d="M4 15a5 5 0 015 5"/><circle cx="5" cy="19" r="1.5"/><path d="M14 5l5 5-4 4-5-5 4-4z"/>',
        'scale-balanced' => '<path d="M12 3v18M7 21h10"/><path d="M12 6l7 2M12 6L5 8"/><path d="M5 8l-3 6a3 3 0 006 0L5 8z"/><path d="M19 8l-3 6a3 3 0 006 0l-3-6z"/>',
        'search' => '<circle cx="11" cy="11" r="7"/><path d="M21 21l-4.3-4.3"/>',
        'snowflake' => '<path d="M12 2v20M4.9 6.5l14.2 11M19.1 6.5L4.9 17.5"/><path d="M12 6l3-3M12 6L9 3M12 18l3 3M12 18l-3 3"/>',
        'star' => '<path d="M12 2.5l2.9 6 6.6.9-4.8 4.6 1.2 6.5-5.9-3.1-5.9 3.1 1.2-6.5L2.5 9.4l6.6-.9 2.9-6z"/>',
        'tags' => '<path d="M9 3H3v6l10 10 6-6L9 3z"/><path d="M6.5 6.5h.01"/>',
        'telegram' => '<path d="M22 3L2 10.5l6 2.2L20 6l-9.5 8.6.4 5.4 3-3.6 5 3.6L22 3z"/>',
        'trash' => '<path d="M3 6h18"/><path d="M8 6V4a1 1 0 011-1h6a1 1 0 011 1v2"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/>',
        'trophy' => '<path d="M8 21h8M12 17v4"/><path d="M7 4h10v5a5 5 0 01-10 0V4z"/><path d="M7 6H4v2a3 3 0 003 3M17 6h3v2a3 3 0 01-3 3"/>',
        'triangle-exclamation' => '<path d="M10.3 3.9L1.8 18a2 2 0 001.7 3h17a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/><path d="M12 9v4M12 17h.01"/>',
        'user-shield' => '<path d="M9 11a4 4 0 100-8 4 4 0 000 8z"/><path d="M2 21v-1a6 6 0 016-6h2"/><path d="M18 12l4 1.5V17a5 5 0 01-4 4.5A5 5 0 0114 17v-3.5L18 12z"/>',
        'users' => '<path d="M16 21v-2a4 4 0 00-4-4H6a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 00-3-3.9"/><path d="M16 3.1a4 4 0 010 7.8"/>',
        'wallet' => '<path d="M3 7a2 2 0 012-2h13v4"/><path d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2v-7a2 2 0 00-2-2H5"/><path d="M17 13h.01"/>',
        'wand-magic-sparkles' => '<path d="M15 4V2M15 10V8M12.5 6h-2M19.5 6h-2"/><path d="M3 21L14 10l-3-3L0 18z" transform="translate(3 1)"/><path d="M19 15v-2M19 19v-2M17 17h-1M22 17h-1"/>',
        'xmark' => '<path d="M18 6L6 18M6 6l12 12"/>',
    ];

    $path = $paths[$name] ?? null;

    // Closed single-outline shapes, where filling reads as "on" rather than as
    // a smudge. Everything else stays stroked whatever the caller asks for.
    $fillable = ['star', 'circle', 'moon'];
    $filled = $solid && in_array($name, $fillable, true);
@endphp

@if ($path)
    <svg {{ $attributes->merge(['class' => $class]) }}
         viewBox="0 0 24 24"
         fill="{{ $filled ? 'currentColor' : 'none' }}"
         stroke="currentColor"
         stroke-width="1.8"
         stroke-linecap="round"
         stroke-linejoin="round"
         aria-hidden="true">{!! $path !!}</svg>
@endif
