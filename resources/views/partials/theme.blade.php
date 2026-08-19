{{--
    Night mode.

    Included in the <head> of every layout, after the stylesheet block so it
    wins any tie on ordering.

    Why this remaps existing utilities instead of adding dark: variants:
    the views use the neutral palette about 600 times across 34 files
    (border-slate-200 alone appears 133 times). Adding a dark: counterpart to
    each is a change to every file in the view layer, and one missed class is
    a white card on a black page. Redefining what those utilities *render as*
    under html.dark covers all of them at once, and covers views written
    later without anyone having to remember.

    The rules are grouped by CSS property, not by colour token, because two
    tokens pull in opposite directions: text-slate-900 is a heading that has
    to become light, while bg-slate-900 is a deliberately dark surface (the
    admin sidebar, the modal header, the ticker bar) that has to stay dark.
    Likewise bg-amber-50 is a tinted panel that must darken, while
    text-amber-50 is light text sitting on a saturated background and must
    not. A per-token remap cannot express that; per-property rules can.

    Specificity: `html.dark .x` is (0,2,1) against Tailwind's (0,1,0), so
    these win without !important, including over hover: variants.
--}}
<script>
(function () {
    // Before first paint, so the page never flashes white on the way to dark.
    // Inline and un-deferred for the same reason — and with no dependency on
    // any CDN, several of which are unreliable from this server.
    var stored = null;

    try {
        stored = localStorage.getItem('theme');
    } catch (e) {
        // Private browsing can make localStorage throw on access rather than
        // return null. The system preference below still applies.
    }

    var dark = stored === 'dark'
        || (stored !== 'light' && window.matchMedia('(prefers-color-scheme: dark)').matches);

    document.documentElement.classList.toggle('dark', dark);
})();

// Called by the toggle button in every layout.
window.toggleTheme = function () {
    var dark = !document.documentElement.classList.contains('dark');

    document.documentElement.classList.toggle('dark', dark);

    try {
        localStorage.setItem('theme', dark ? 'dark' : 'light');
    } catch (e) {
        // Choice does not persist, but the page still switches.
    }

    syncThemeIcons(dark);

    // Charts paint their own labels and gridlines and cannot inherit CSS.
    window.dispatchEvent(new CustomEvent('theme-changed', { detail: { dark: dark } }));
};

function syncThemeIcons(dark) {
    // Each button holds both icons and hides one. The moon means "click for
    // dark", so it is the one shown while the page is light.
    document.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
        var forDark = icon.getAttribute('data-theme-icon') === 'dark';

        icon.classList.toggle('hidden', forDark !== dark);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // The markup renders showing the moon; correct it when we loaded dark.
    syncThemeIcons(document.documentElement.classList.contains('dark'));
});
</script>

<style>
html.dark {
    /* Surfaces, lightest-sitting-on-darkest. The page is the darkest layer,
       cards sit above it, and controls above those, so depth reads without
       needing shadows — which are black, and all but invisible here. */
    --ink-bg: #0b1120;
    --ink-surface: #131c2f;
    --ink-surface-2: #1b2740;
    --ink-surface-3: #24324f;

    /* Elements that were already dark in light mode. They stay dark, but are
       lifted off the page background so they remain distinguishable. */
    --ink-raised: #1b2740;
    --ink-raised-2: #24324f;
    --ink-raised-3: #2e3d5e;

    --ink-border: #26324f;
    --ink-border-soft: #1d2841;
    --ink-border-strong: #33415f;

    /* --ink-muted is the one to watch: it lands on text-slate-400, which the
       views use 117 times for labels and secondary figures. At #8794ae on
       --ink-surface it measures 5.6:1, clear of the 4.5:1 floor for body
       text. Do not darken it without re-checking that. */
    --ink-text: #e6ecf8;
    --ink-text-2: #cbd5e8;
    --ink-text-3: #a3b0c8;
    --ink-muted: #8794ae;

    /* Native scrollbars, form controls and the browser's own UI follow this;
       without it a select or a date picker renders as a white slab. */
    color-scheme: dark;
}

/* --- Backgrounds ---------------------------------------------------------- */

html.dark .bg-white { background-color: var(--ink-surface); }
html.dark .bg-slate-50 { background-color: var(--ink-bg); }
html.dark .bg-slate-100 { background-color: var(--ink-surface-2); }
html.dark .hover\:bg-slate-50:hover { background-color: var(--ink-surface-2); }
html.dark .hover\:bg-slate-100:hover { background-color: var(--ink-surface-3); }
html.dark .hover\:bg-slate-200:hover { background-color: var(--ink-surface-3); }

/* Deliberately dark in light mode: buttons, the admin sidebar, the modal
   header, the ticker bar. Kept dark, one step up from the page. */
html.dark .bg-slate-900 { background-color: var(--ink-raised); }
html.dark .bg-slate-800 { background-color: var(--ink-raised-2); }
html.dark .bg-slate-500 { background-color: var(--ink-raised-3); }
html.dark .hover\:bg-slate-900:hover { background-color: var(--ink-raised-2); }
html.dark .hover\:bg-slate-800:hover { background-color: var(--ink-raised-3); }
html.dark .hover\:bg-slate-700:hover { background-color: var(--ink-raised-3); }

/* --- Text ----------------------------------------------------------------- */

html.dark .text-slate-900 { color: var(--ink-text); }
html.dark .text-slate-700 { color: var(--ink-text-2); }
html.dark .text-slate-600 { color: var(--ink-text-2); }
html.dark .text-slate-500 { color: var(--ink-text-3); }
html.dark .text-slate-400 { color: var(--ink-muted); }
html.dark .hover\:text-slate-600:hover { color: var(--ink-text); }

/* text-slate-200/300 already sit on dark surfaces (the admin sidebar), so
   they are left alone — they are correct in both modes. text-white likewise:
   it labels saturated buttons that do not change colour. */

/* --- Borders -------------------------------------------------------------- */

html.dark .border-slate-200 { border-color: var(--ink-border); }
html.dark .border-slate-100 { border-color: var(--ink-border-soft); }
html.dark .border-slate-50 { border-color: var(--ink-border-soft); }
html.dark .border-slate-300 { border-color: var(--ink-border-strong); }
html.dark .hover\:border-slate-300:hover { border-color: var(--ink-border-strong); }
html.dark .border-slate-700,
html.dark .border-slate-800,
html.dark .border-slate-900 { border-color: var(--ink-border-strong); }
html.dark .divide-slate-100 > :not([hidden]) ~ :not([hidden]) { border-color: var(--ink-border-soft); }

/* --- Tinted panels -------------------------------------------------------- */
/* A -50 or -100 tint is a wash of white with a trace of hue. On a dark page
   that reads as a glowing block, which is the complaint this whole file
   answers. Rebuilt as a wash of the hue itself over the dark surface, with
   the paired text lightened to stay legible against it. */

html.dark .bg-emerald-50 { background-color: rgb(16 185 129 / 0.12); }
html.dark .bg-emerald-100 { background-color: rgb(16 185 129 / 0.18); }
html.dark .border-emerald-200 { border-color: rgb(16 185 129 / 0.35); }
html.dark .text-emerald-600 { color: #34d399; }
html.dark .text-emerald-700,
html.dark .text-emerald-900 { color: #6ee7b7; }

html.dark .bg-red-50 { background-color: rgb(239 68 68 / 0.12); }
html.dark .bg-red-100 { background-color: rgb(239 68 68 / 0.18); }
html.dark .border-red-200 { border-color: rgb(239 68 68 / 0.35); }
html.dark .hover\:bg-red-50:hover { background-color: rgb(239 68 68 / 0.15); }
html.dark .hover\:bg-red-100:hover { background-color: rgb(239 68 68 / 0.22); }
html.dark .text-red-600 { color: #f87171; }
html.dark .text-red-700,
html.dark .text-red-900 { color: #fca5a5; }
html.dark .hover\:text-red-600:hover { color: #f87171; }
html.dark .hover\:text-red-700:hover { color: #fca5a5; }

html.dark .bg-amber-50 { background-color: rgb(245 158 11 / 0.12); }
html.dark .bg-amber-100 { background-color: rgb(245 158 11 / 0.18); }
html.dark .border-amber-200 { border-color: rgb(245 158 11 / 0.35); }
html.dark .border-amber-50 { border-color: rgb(245 158 11 / 0.2); }
html.dark .text-amber-600 { color: #fbbf24; }
html.dark .text-amber-700,
html.dark .text-amber-800 { color: #fcd34d; }

html.dark .bg-sky-50 { background-color: rgb(14 165 233 / 0.12); }
html.dark .bg-sky-100 { background-color: rgb(14 165 233 / 0.18); }
html.dark .text-sky-600 { color: #38bdf8; }

html.dark .bg-indigo-50 { background-color: rgb(99 102 241 / 0.12); }
html.dark .hover\:bg-indigo-50:hover { background-color: rgb(99 102 241 / 0.18); }
html.dark .bg-blue-50 { background-color: rgb(59 130 246 / 0.12); }
html.dark .bg-purple-100 { background-color: rgb(168 85 247 / 0.18); }
html.dark .text-purple-600 { color: #c084fc; }
html.dark .bg-orange-50 { background-color: rgb(249 115 22 / 0.12); }
html.dark .text-orange-600 { color: #fb923c; }

/* --- Form controls -------------------------------------------------------- */
/* Most inputs here carry a border class but no background class, so they fall
   through to the browser default. color-scheme above handles that in current
   browsers; this states it outright so an older one does not leave a white
   field on a dark form. Checkboxes and radios keep the native rendering,
   which color-scheme already darkens correctly. */

html.dark input:not([type='checkbox']):not([type='radio']):not([type='range']),
html.dark select,
html.dark textarea {
    background-color: var(--ink-surface-2);
    color: var(--ink-text);
}

html.dark ::placeholder { color: var(--ink-muted); }

/* --- SweetAlert2 ---------------------------------------------------------- */
/* Ships its own stylesheet, so none of the above reaches it. */

html.dark .swal2-popup {
    background: var(--ink-surface);
    color: var(--ink-text);
}

html.dark .swal2-title,
html.dark .swal2-html-container { color: var(--ink-text); }
</style>
