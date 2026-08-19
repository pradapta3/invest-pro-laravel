{{--
    Night-mode switch. Sits in the header of every layout.

    The icon shows what clicking will give you: a moon while the page is
    light, a sun while it is dark. Both are rendered and one is hidden, and
    which one is corrected on DOMContentLoaded by partials/theme.blade.php —
    the server cannot know which theme this visitor chose, because the choice
    lives in localStorage.

    Inline SVG rather than a Font Awesome class, and a vanilla onclick rather
    than Alpine, because both of those arrive from CDNs that have already
    proved unreachable from this server. A theme switch that renders as an
    invisible empty square, or that silently does nothing when clicked, is
    worse than no switch at all. Same reasoning as the password toggle on the
    login form.

    `class` replaces the default styling outright rather than adding to it,
    so the admin sidebar can render this as a full-width row alongside its
    other links instead of a floating square.
--}}
@props([
    'class' => 'inline-flex items-center justify-center w-10 h-10 rounded-lg transition bg-white border border-slate-200 text-slate-500 hover:bg-slate-50',
    'labelled' => false,
])

<button type="button"
        onclick="window.toggleTheme()"
        title="Mode gelap / terang"
        aria-label="Ganti mode gelap atau terang"
        {{ $attributes->merge(['class' => $class]) }}>
    {{-- Moon: shown while the page is light, i.e. "click for dark". --}}
    <svg data-theme-icon="light" class="w-5 h-5 shrink-0" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M21.75 15a9.75 9.75 0 01-11.7-12.7A9.75 9.75 0 1021.75 15z"/>
    </svg>

    {{-- Sun: shown while the page is dark, i.e. "click for light". --}}
    <svg data-theme-icon="dark" class="w-5 h-5 shrink-0 hidden" viewBox="0 0 24 24" fill="none"
         stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <circle cx="12" cy="12" r="4"/>
        <path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/>
    </svg>

    @if ($labelled)
        {{-- Swapped by the same rule as the icons, so the row never reads
             "Mode Gelap" on a page that is already dark. --}}
        <span data-theme-icon="light">Mode Gelap</span>
        <span data-theme-icon="dark" class="hidden">Mode Terang</span>
    @endif
</button>
