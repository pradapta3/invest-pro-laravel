<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'IDX Invest')</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Roboto+Mono:wght@500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    @if (file_exists(public_path('build/manifest.json')))
        {{-- Compiled by `npm run build` --}}
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        {{-- Node.js isn't installed on this machine yet, so the Vite build
             hasn't been run. This CDN fallback keeps the app usable in the
             meantime; run `npm install && npm run build` and this switches
             to the compiled assets automatically. --}}
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                darkMode: 'class',
                theme: {
                    extend: {
                        colors: { primary: '#4f46e5' },
                        fontFamily: {
                            sans: ['Plus Jakarta Sans', 'sans-serif'],
                            mono: ['Roboto Mono', 'monospace'],
                        },
                    },
                },
            };
        </script>
        <style>.font-num { font-family: 'Roboto Mono', monospace; }</style>
    @endif

    @include('partials.theme')

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>

    <script>
    // Chart.js paints its axis labels and gridlines onto a canvas, so no CSS
    // reaches them: left alone they stay near-black on a dark card. Set here,
    // before any page builds its charts, and re-applied on the theme-changed
    // event so a mid-session switch does not need a reload.
    //
    // Charts are found by walking the canvases rather than by each view
    // registering itself, so no chart page has to know about this.
    (function () {
        function applyChartTheme() {
            if (!window.Chart) return;

            var dark = document.documentElement.classList.contains('dark');

            Chart.defaults.color = dark ? '#a3b0c8' : '#64748b';
            Chart.defaults.borderColor = dark ? 'rgba(148, 163, 184, 0.16)' : 'rgba(100, 116, 139, 0.15)';
        }

        applyChartTheme();

        window.addEventListener('theme-changed', function () {
            applyChartTheme();

            document.querySelectorAll('canvas').forEach(function (canvas) {
                var chart = window.Chart && Chart.getChart(canvas);
                if (chart) chart.update();
            });
        });
    })();
    </script>

    @stack('head')
</head>
<body class="bg-slate-50 text-slate-900 font-sans pb-16">

<header class="sticky top-0 z-30 bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-x-4 gap-y-3">
        <div class="flex items-center gap-3 md:gap-4 min-w-0">
            <a href="{{ route('dashboard') }}" class="text-lg md:text-xl font-extrabold tracking-tight bg-gradient-to-r from-primary to-purple-500 bg-clip-text text-transparent whitespace-nowrap">
                DOMPET IJO
            </a>

            @isset($ihsg)
                @if($ihsg)
                    {{-- The index level is the one number worth keeping on a phone, so
                         it stays visible; the mood badge waits for a wider screen. --}}
                    <span class="inline-flex items-center text-xs md:text-sm font-bold gap-1 whitespace-nowrap">
                        <span class="text-slate-400">IHSG</span>
                        <span class="{{ $ihsg['change'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ number_format($ihsg['price']) }}
                            ({{ $ihsg['change'] >= 0 ? '+' : '' }}{{ round($ihsg['pct'], 2) }}%)
                        </span>
                    </span>
                @endif
            @endisset

            @isset($marketMood)
                <span class="hidden sm:inline-flex items-center text-sm font-bold gap-1 whitespace-nowrap" style="color: {{ $marketMood['color'] }}">
                    <i class="fa-solid {{ $marketMood['icon'] }}"></i>
                    {{ $marketMood['pct'] }}% {{ $marketMood['label'] }}
                </span>
            @endisset
        </div>

        {{-- Ten action buttons in one non-wrapping row were what forced the whole
             page wider than a phone, so the browser zoomed the entire layout out
             to fit. Below md they collapse behind this toggle instead.

             Driven by the inline script below rather than Alpine on purpose.
             Alpine arrives from a third-party CDN, and when that is blocked or
             slow this button is the only way to reach portfolio, alerts, admin
             and — worst of all — logout on a phone. The rest of the header's
             dropdowns still use Alpine; losing those only costs a menu. --}}
        {{-- Outside the collapsing nav below md, so the fix for a screen that is
             too bright does not itself require finding it behind a menu. From
             md up the nav is always open and the copy inside it is used. --}}
        <x-theme-toggle class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition" />

        <button type="button" id="nav-toggle" aria-controls="main-nav" aria-expanded="false" aria-label="Menu"
                class="md:hidden inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-600 hover:bg-slate-50 transition">
            <i id="nav-toggle-icon" class="fa-solid fa-bars"></i>
        </button>

        {{-- `hidden md:flex` is the no-JavaScript baseline: collapsed on a phone,
             always open from md up. The toggle only ever adds !flex to reveal it. --}}
        <nav id="main-nav" class="hidden md:flex w-full md:w-auto flex-wrap items-center gap-2 text-sm font-semibold">
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary text-white px-3 py-2 hover:bg-indigo-700 transition">
                    <i class="fa-solid fa-robot"></i> Analisa
                </button>
                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-48 rounded-xl bg-white shadow-lg border border-slate-200 py-2 z-40">
                    {{-- Each group is hidden unless the subscriber's plan covers it,
                         so the menu never offers a link that answers 403. The
                         separators live inside the groups for the same reason. --}}
                    @if (auth()->user()?->planAllows('scanner'))
                        <a href="{{ route('scanner.titan') }}" class="block px-4 py-2 hover:bg-slate-50"><i class="fa-solid fa-bolt text-amber-500 mr-2"></i>Titan Sniper</a>
                        <a href="{{ route('scanner.quant') }}" class="block px-4 py-2 hover:bg-slate-50"><i class="fa-solid fa-layer-group text-primary mr-2"></i>Quant Alpha</a>
                    @endif
                    @if (auth()->user()?->planAllows('pattern'))
                        @if (auth()->user()?->planAllows('scanner'))<hr class="my-1 border-slate-100">@endif
                        <a href="{{ route('seasonality.show') }}" class="block px-4 py-2 hover:bg-slate-50">Seasonality</a>
                        <a href="{{ route('similarity.show') }}" class="block px-4 py-2 hover:bg-slate-50">Ghost Pattern</a>
                    @endif
                    @if (auth()->user()?->planAllows('backtest'))
                        @if (auth()->user()?->planAllows('scanner') || auth()->user()?->planAllows('pattern'))<hr class="my-1 border-slate-100">@endif
                        <a href="{{ route('backtest.index') }}" class="block px-4 py-2 hover:bg-slate-50"><i class="fa-solid fa-flask mr-2 text-emerald-600"></i>Backtest</a>
                    @endif
                </div>
            </div>

            @if (auth()->user()?->is_admin)
                <button type="button" onclick="broadcastDigest(this)" title="Broadcast Top Picks"
                        class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-sky-500 text-white hover:bg-sky-600 transition">
                    <i class="fa-brands fa-telegram"></i>
                </button>
            @endif

            @if (auth()->user()?->planAllows('telegram'))
                <a href="{{ route('telegram.link') }}" title="{{ auth()->user()?->hasLinkedTelegram() ? 'Telegram Terhubung' : 'Hubungkan Telegram' }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg border transition {{ auth()->user()?->hasLinkedTelegram() ? 'bg-white border-slate-200 text-sky-500 hover:bg-slate-50' : 'bg-white border-amber-300 text-amber-500 hover:bg-slate-50' }}">
                <i class="fa-brands fa-telegram"></i>
            </a>
            @endif

            <a href="{{ route('alerts.index') }}" title="Price Alert" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-amber-500 hover:bg-slate-50 transition">
                <i class="fa-solid fa-bell"></i>
            </a>

            @if (auth()->user()?->planAllows('heatmap'))
            <a href="{{ route('heatmap.index') }}" title="Market Map" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-amber-500 hover:bg-slate-50 transition">
                <i class="fa-solid fa-map"></i>
            </a>
            @endif
            <a href="{{ route('news.index') }}" title="News" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-red-500 hover:bg-slate-50 transition">
                <i class="fa-solid fa-newspaper"></i>
            </a>
            @if (auth()->user()?->planAllows('tools'))
            <a href="{{ route('tools.index') }}" title="Tools" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-primary hover:bg-slate-50 transition">
                <i class="fa-solid fa-calculator"></i>
            </a>
            @endif
            <a href="{{ route('portfolio.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-white border border-slate-200 text-emerald-600 px-3 py-2 hover:bg-slate-50 transition">
                <i class="fa-solid fa-wallet"></i> Porto
            </a>

            @if (auth()->user()?->is_admin)
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" title="Data Updater"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 transition">
                        <i class="fa-solid fa-database"></i>
                    </button>
                    <div x-show="open" x-cloak class="absolute right-0 mt-2 w-56 rounded-xl bg-white shadow-lg border border-slate-200 py-2 z-40">
                        <div class="px-4 py-1 text-[10px] font-bold uppercase text-slate-400">Data Updater</div>
                        <button type="button" onclick="triggerUpdate('realtime', this)" class="w-full text-left px-4 py-2 text-sm font-bold text-red-600 hover:bg-slate-50">Update Realtime</button>
                        <button type="button" onclick="triggerUpdate('market', this)" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-50">Update Market (EOD)</button>
                        <button type="button" onclick="triggerUpdate('fundamentals', this)" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-50">Update Fundamental</button>
                        <button type="button" onclick="triggerUpdate('sentiment', this)" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-50">Update News Sentiment</button>
                        <button type="button" onclick="triggerUpdate('history', this)" class="w-full text-left px-4 py-2 text-sm hover:bg-slate-50">Backfill Riwayat (Backtest)</button>
                        <div class="border-t border-slate-100 mt-1 px-4 pt-2 text-[10px] text-slate-400">Berjalan di background — muat ulang halaman setelah beberapa menit.</div>
                    </div>
                </div>
                <a href="{{ route('admin.dashboard') }}" title="Admin" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-slate-800 text-white hover:bg-slate-900 transition">
                    <i class="fa-solid fa-user-shield"></i>
                </a>
            @endif

            {{-- md and up only: below that the copy beside the hamburger, which
                 is outside this collapsing nav, is the one on screen. --}}
            <x-theme-toggle class="hidden md:inline-flex items-center justify-center w-10 h-10 rounded-lg transition bg-white border border-slate-200 text-slate-500 hover:bg-slate-50" />

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Keluar" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-400 hover:bg-red-50 hover:text-red-500 transition">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </nav>
    </div>
</header>

{{-- Inline and un-deferred so the mobile menu works from first paint, with no
     dependency on any external script. --}}
<script>
(function () {
    var btn = document.getElementById('nav-toggle');
    var nav = document.getElementById('main-nav');
    var icon = document.getElementById('nav-toggle-icon');
    if (!btn || !nav) return;

    btn.addEventListener('click', function () {
        // Tailwind's !flex beats the `hidden` that keeps the row collapsed below
        // md; above md the md:flex in the class list already wins, so toggling
        // this is a no-op there.
        var open = nav.classList.toggle('!flex');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (icon) {
            icon.classList.toggle('fa-bars', !open);
            icon.classList.toggle('fa-xmark', open);
        }
    });
})();
</script>

<main class="max-w-7xl mx-auto px-4 py-6">
    @if (session('status'))
        <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 text-sm font-semibold">
            {{ session('status') }}
        </div>
    @endif

    @if ($errors->any())
        <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 px-4 py-3 text-sm font-semibold">
            <ul class="list-disc list-inside">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @yield('content')
</main>

@isset($tickerTape)
<div class="fixed bottom-0 left-0 w-full h-8 bg-slate-900 border-t border-slate-700 overflow-hidden flex items-center z-40">
    <div class="whitespace-nowrap animate-[ticker_45s_linear_infinite] pl-full">
        @foreach ($tickerTape as $t)
            @php
                $chg = (float) $t->close_price - (float) $t->open_price;
                $pct = (float) $t->open_price > 0 ? ($chg / (float) $t->open_price) * 100 : 0;
            @endphp
            <span class="inline-block px-4 text-xs font-mono font-semibold text-white">
                {{ str_replace('.JK', '', $t->ticker) }}
                <span class="{{ $chg >= 0 ? 'text-emerald-400' : 'text-red-400' }}">
                    {{ $chg >= 0 ? '▲' : '▼' }} {{ number_format($t->close_price) }} ({{ round($pct, 2) }}%)
                </span>
            </span>
        @endforeach
    </div>
</div>
<style>
@keyframes ticker { 0% { transform: translate3d(0,0,0); } 100% { transform: translate3d(-100%,0,0); } }
</style>
@endisset

<x-trading-plan-modal />

<script>
function toast(icon, title) {
    Swal.fire({ toast: true, position: 'top-end', icon, title, showConfirmButton: false, timer: 2500 });
}

function broadcastDigest(btn) {
    Swal.fire({
        title: 'Broadcast Top Picks?',
        text: 'Kirim rangkuman BSJP/Titan/Quant/Swing ke Telegram.',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Kirim',
    }).then((result) => {
        if (!result.isConfirmed) return;
        fetch('{{ route('admin.api.telegram.broadcast-digest') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        })
            .then(r => r.json())
            .then(data => toast(data.status === 'ok' ? 'success' : 'error', data.status === 'ok' ? 'Broadcast terkirim!' : 'Gagal broadcast.'))
            .catch(() => toast('error', 'Gagal broadcast.'));
    });
}

function analyzeStock(ticker) {
    Swal.fire({ title: 'Menganalisa ' + ticker + '...', didOpen: () => Swal.showLoading(), allowOutsideClick: false });

    const formData = new FormData();
    formData.append('ticker', ticker);

    fetch('{{ route('api.stocks.analyze') }}', {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
        body: formData,
    })
        .then(r => r.json())
        .then(data => {
            Swal.close();
            if (data.status !== 'success') {
                toast('error', data.msg || 'Gagal menganalisa.');
                return;
            }
            window.dispatchEvent(new CustomEvent('open-trading-plan-modal', { detail: data }));
        })
        .catch(() => { Swal.close(); toast('error', 'Gagal menganalisa.'); });
}

function sendSignal(ticker, format = 'standard') {
    Swal.fire({
        title: 'Kirim sinyal ' + ticker + '?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonText: 'Kirim',
    }).then((result) => {
        if (!result.isConfirmed) return;

        const formData = new FormData();
        formData.append('ticker', ticker);
        formData.append('format', format);

        const url = format === 'titan'
            ? '{{ route('api.telegram.broadcast-titan') }}'
            : '{{ route('api.telegram.broadcast-signal') }}';

        fetch(url, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
            body: formData,
        })
            .then(r => r.json())
            .then(data => toast(data.status === 'ok' ? 'success' : 'error', data.status === 'ok' ? 'Sinyal terkirim!' : (data.message || 'Gagal.')))
            .catch(() => toast('error', 'Gagal mengirim sinyal.'));
    });
}

function triggerUpdate(key, btn) {
    const original = btn.innerText;
    btn.disabled = true;
    btn.innerText = 'Memulai...';

    fetch(`{{ url('/admin/api/data-updates') }}/${key}`, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
    })
        .then(r => r.json())
        .then(data => {
            toast(data.status === 'ok' ? 'success' : 'error', data.message || 'Gagal memulai update.');
        })
        .catch(() => toast('error', 'Gagal memulai update.'))
        .finally(() => { btn.disabled = false; btn.innerText = original; });
}
</script>

@stack('scripts')
</body>
</html>
