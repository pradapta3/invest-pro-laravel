<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'IDX Invest')</title>


    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.theme')

    <style>[x-cloak] { display: none !important; }</style>


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
                {{-- Says what it measures. This is breadth — the share of the
                     exchange trading above its own 20-day average — not the
                     index's move today, and the two routinely disagree: IHSG
                     can close down while most stocks are still above their
                     20-day line. Labelled only "Greed" it read as "the market
                     is up", which is not what it says. --}}
                <span class="hidden sm:inline-flex items-center text-sm font-bold gap-1 whitespace-nowrap"
                      style="color: {{ $marketMood['color'] }}"
                      title="{{ $marketMood['pct'] }}% emiten ditutup di atas MA20-nya. Ini lebar pasar, bukan pergerakan IHSG hari ini.">
                    <x-icon :name="$marketMood['icon']" class="w-4 h-4" :solid="true" />
                    {{ $marketMood['pct'] }}% {{ $marketMood['label'] }}
                    <span class="font-normal text-slate-400 text-xs">di atas MA20</span>
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
            {{-- Both states in the markup, one hidden — the icons are SVG now,
                 so there is no class to swap the way the Font Awesome glyph
                 allowed. --}}
            <x-icon name="bars" class="w-4 h-4" data-nav-icon="closed" />
            <x-icon name="xmark" class="w-4 h-4 hidden" data-nav-icon="open" />
        </button>

        {{-- `hidden md:flex` is the no-JavaScript baseline: collapsed on a phone,
             always open from md up. The toggle only ever adds !flex to reveal it. --}}
        <nav id="main-nav" class="hidden md:flex w-full md:w-auto flex-wrap items-center gap-2 text-sm font-semibold">
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary text-white px-3 py-2 hover:bg-indigo-700 transition">
                    <x-icon name="robot" class="w-4 h-4" :solid="true" /> Analisa
                </button>
                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-48 rounded-xl bg-white shadow-lg border border-slate-200 py-2 z-40">
                    {{-- Each group is hidden unless the subscriber's plan covers it,
                         so the menu never offers a link that answers 403. The
                         separators live inside the groups for the same reason. --}}
                    @if (auth()->user()?->planAllows('scanner'))
                        <a href="{{ route('scanner.titan') }}" class="block px-4 py-2 hover:bg-slate-50"><x-icon name="bolt" class="text-amber-500 mr-2 w-4 h-4" :solid="true" />Titan Sniper</a>
                        <a href="{{ route('scanner.quant') }}" class="block px-4 py-2 hover:bg-slate-50"><x-icon name="layer-group" class="text-primary mr-2 w-4 h-4" :solid="true" />Quant Alpha</a>
                    @endif
                    @if (auth()->user()?->planAllows('pattern'))
                        @if (auth()->user()?->planAllows('scanner'))<hr class="my-1 border-slate-100">@endif
                        <a href="{{ route('seasonality.show') }}" class="block px-4 py-2 hover:bg-slate-50">Seasonality</a>
                        <a href="{{ route('similarity.show') }}" class="block px-4 py-2 hover:bg-slate-50">Ghost Pattern</a>
                    @endif
                    @if (auth()->user()?->planAllows('backtest'))
                        @if (auth()->user()?->planAllows('scanner') || auth()->user()?->planAllows('pattern'))<hr class="my-1 border-slate-100">@endif
                        <a href="{{ route('backtest.index') }}" class="block px-4 py-2 hover:bg-slate-50"><x-icon name="flask" class="mr-2 text-emerald-600 w-4 h-4" :solid="true" />Backtest</a>
                    @endif
                </div>
            </div>

            @if (auth()->user()?->is_admin)
                <button type="button" onclick="broadcastDigest(this)" title="Broadcast Top Picks"
                        class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-sky-500 text-white hover:bg-sky-600 transition">
                    <x-icon name="telegram" class="w-4 h-4" :solid="true" />
                </button>
            @endif

            @if (auth()->user()?->planAllows('telegram'))
                <a href="{{ route('telegram.link') }}" title="{{ auth()->user()?->hasLinkedTelegram() ? 'Telegram Terhubung' : 'Hubungkan Telegram' }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg border transition {{ auth()->user()?->hasLinkedTelegram() ? 'bg-white border-slate-200 text-sky-500 hover:bg-slate-50' : 'bg-white border-amber-300 text-amber-500 hover:bg-slate-50' }}">
                <x-icon name="telegram" class="w-4 h-4" :solid="true" />
            </a>
            @endif

            <a href="{{ route('alerts.index') }}" title="Price Alert" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-amber-500 hover:bg-slate-50 transition">
                <x-icon name="bell" class="w-4 h-4" :solid="true" />
            </a>

            @if (auth()->user()?->planAllows('heatmap'))
            <a href="{{ route('heatmap.index') }}" title="Market Map" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-amber-500 hover:bg-slate-50 transition">
                <x-icon name="map" class="w-4 h-4" :solid="true" />
            </a>
            @endif
            <a href="{{ route('news.index') }}" title="News" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-red-500 hover:bg-slate-50 transition">
                <x-icon name="newspaper" class="w-4 h-4" :solid="true" />
            </a>
            @if (auth()->user()?->planAllows('tools'))
            <a href="{{ route('tools.index') }}" title="Tools" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-primary hover:bg-slate-50 transition">
                <x-icon name="calculator" class="w-4 h-4" :solid="true" />
            </a>
            @endif
            <a href="{{ route('portfolio.index') }}" class="inline-flex items-center gap-2 rounded-lg bg-white border border-slate-200 text-emerald-600 px-3 py-2 hover:bg-slate-50 transition">
                <x-icon name="wallet" class="w-4 h-4" :solid="true" /> Porto
            </a>

            @if (auth()->user()?->is_admin)
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.outside="open = false" title="Data Updater"
                            class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-500 hover:bg-slate-50 transition">
                        <x-icon name="database" class="w-4 h-4" :solid="true" />
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
                    <x-icon name="user-shield" class="w-4 h-4" :solid="true" />
                </a>
            @endif

            {{-- md and up only: below that the copy beside the hamburger, which
                 is outside this collapsing nav, is the one on screen. --}}
            <x-theme-toggle class="hidden md:inline-flex items-center justify-center w-10 h-10 rounded-lg transition bg-white border border-slate-200 text-slate-500 hover:bg-slate-50" />

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Keluar" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-400 hover:bg-red-50 hover:text-red-500 transition">
                    <x-icon name="right-from-bracket" class="w-4 h-4" :solid="true" />
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
    var icons = btn.querySelectorAll('[data-nav-icon]');
    if (!btn || !nav) return;

    btn.addEventListener('click', function () {
        // Tailwind's !flex beats the `hidden` that keeps the row collapsed below
        // md; above md the md:flex in the class list already wins, so toggling
        // this is a no-op there.
        var open = nav.classList.toggle('!flex');
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        icons.forEach(function (icon) {
            icon.classList.toggle('hidden', (icon.dataset.navIcon === 'open') !== open);
        });
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
                // Against the previous close. This used to be close - open,
                // i.e. the intraday drift only, so a stock that gapped down and
                // then recovered a little showed an up arrow on a day it was
                // down.
                $chg = $t->dailyChange();
                $pct = $t->dailyChangePct();
            @endphp
            <span class="inline-block px-4 text-xs font-mono font-semibold text-white">
                {{ str_replace('.JK', '', $t->ticker) }}
                <span class="{{ match (true) {
                    $chg === null => 'text-slate-400',
                    $chg > 0 => 'text-emerald-400',
                    $chg < 0 => 'text-red-400',
                    default => 'text-slate-300',
                } }}">
                    {{ match (true) { $chg === null => '·', $chg > 0 => '▲', $chg < 0 => '▼', default => '=' } }}
                    {{ number_format($t->close_price) }}{{ $pct === null ? '' : ' ('.round($pct, 2).'%)' }}
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
