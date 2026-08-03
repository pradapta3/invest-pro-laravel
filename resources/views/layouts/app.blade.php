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

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/echarts@5.4.3/dist/echarts.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/dompurify@3/dist/purify.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>

    @stack('head')
</head>
<body class="bg-slate-50 text-slate-900 font-sans pb-16">

<header class="sticky top-0 z-30 bg-white border-b border-slate-200">
    <div class="max-w-7xl mx-auto px-4 py-3 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-4">
            <a href="{{ route('dashboard') }}" class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-primary to-purple-500 bg-clip-text text-transparent">
                DOMPET IJO
            </a>

            @isset($ihsg)
                @if($ihsg)
                    <span class="hidden md:inline-flex items-center text-sm font-bold gap-1">
                        <span class="text-slate-400">IHSG</span>
                        <span class="{{ $ihsg['change'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                            {{ number_format($ihsg['price']) }}
                            ({{ $ihsg['change'] >= 0 ? '+' : '' }}{{ round($ihsg['pct'], 2) }}%)
                        </span>
                    </span>
                @endif
            @endisset

            @isset($marketMood)
                <span class="hidden md:inline-flex items-center text-sm font-bold gap-1" style="color: {{ $marketMood['color'] }}">
                    <i class="fa-solid {{ $marketMood['icon'] }}"></i>
                    {{ $marketMood['pct'] }}% {{ $marketMood['label'] }}
                </span>
            @endisset
        </div>

        <nav class="flex items-center gap-2 text-sm font-semibold">
            <div class="relative" x-data="{ open: false }">
                <button @click="open = !open" @click.outside="open = false"
                        class="inline-flex items-center gap-2 rounded-lg bg-primary text-white px-3 py-2 hover:bg-indigo-700 transition">
                    <i class="fa-solid fa-robot"></i> Analisa
                </button>
                <div x-show="open" x-cloak class="absolute right-0 mt-2 w-48 rounded-xl bg-white shadow-lg border border-slate-200 py-2 z-40">
                    <a href="{{ route('scanner.titan') }}" class="block px-4 py-2 hover:bg-slate-50"><i class="fa-solid fa-bolt text-amber-500 mr-2"></i>Titan Sniper</a>
                    <a href="{{ route('scanner.quant') }}" class="block px-4 py-2 hover:bg-slate-50"><i class="fa-solid fa-layer-group text-primary mr-2"></i>Quant Alpha</a>
                    <hr class="my-1 border-slate-100">
                    <a href="{{ route('seasonality.show') }}" class="block px-4 py-2 hover:bg-slate-50">Seasonality</a>
                    <a href="{{ route('similarity.show') }}" class="block px-4 py-2 hover:bg-slate-50">Ghost Pattern</a>
                    <hr class="my-1 border-slate-100">
                    <a href="{{ route('backtest.index') }}" class="block px-4 py-2 hover:bg-slate-50"><i class="fa-solid fa-flask mr-2 text-emerald-600"></i>Backtest</a>
                </div>
            </div>

            @if (auth()->user()?->is_admin)
                <button type="button" onclick="broadcastDigest(this)" title="Broadcast Top Picks"
                        class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-sky-500 text-white hover:bg-sky-600 transition">
                    <i class="fa-brands fa-telegram"></i>
                </button>
            @endif

            <a href="{{ route('telegram.link') }}" title="{{ auth()->user()?->hasLinkedTelegram() ? 'Telegram Terhubung' : 'Hubungkan Telegram' }}"
               class="inline-flex items-center justify-center w-10 h-10 rounded-lg border transition {{ auth()->user()?->hasLinkedTelegram() ? 'bg-white border-slate-200 text-sky-500 hover:bg-slate-50' : 'bg-white border-amber-300 text-amber-500 hover:bg-slate-50' }}">
                <i class="fa-brands fa-telegram"></i>
            </a>

            <a href="{{ route('alerts.index') }}" title="Price Alert" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-amber-500 hover:bg-slate-50 transition">
                <i class="fa-solid fa-bell"></i>
            </a>

            <a href="{{ route('heatmap.index') }}" title="Market Map" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-amber-500 hover:bg-slate-50 transition">
                <i class="fa-solid fa-map"></i>
            </a>
            <a href="{{ route('news.index') }}" title="News" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-red-500 hover:bg-slate-50 transition">
                <i class="fa-solid fa-newspaper"></i>
            </a>
            <a href="{{ route('tools.index') }}" title="Tools" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-primary hover:bg-slate-50 transition">
                <i class="fa-solid fa-calculator"></i>
            </a>
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

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" title="Keluar" class="inline-flex items-center justify-center w-10 h-10 rounded-lg bg-white border border-slate-200 text-slate-400 hover:bg-red-50 hover:text-red-500 transition">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </button>
            </form>
        </nav>
    </div>
</header>

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
