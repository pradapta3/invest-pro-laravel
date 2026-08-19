<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Admin — IDX Invest')</title>


        @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.theme')
    {{-- Kept for any Alpine component added to an admin page later: without it
         x-cloak elements paint before Alpine binds. The drawer below no longer
         relies on either. --}}
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans">

<div class="flex min-h-screen">
    {{-- Mobile top bar. The sidebar below is 240px wide, which is most of a
         phone screen, so on small screens it becomes a drawer and this is what
         opens it. --}}
    <div class="md:hidden fixed top-0 inset-x-0 z-30 h-14 bg-slate-900 text-white flex items-center gap-3 px-4">
        <button type="button" id="side-open" aria-label="Buka menu" aria-controls="admin-side" aria-expanded="false"
                class="inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-slate-800 transition">
            <x-icon name="bars" class="w-4 h-4" :solid="true" />
        </button>
        <span class="text-base font-extrabold">DOMPET IJO</span>
        <span class="text-[10px] uppercase tracking-wider text-slate-500 font-bold">Admin</span>

        {{-- Reachable without opening the drawer, for the same reason as on the
             main header: the sidebar copy is behind a menu on a phone. --}}
        <x-theme-toggle class="ml-auto inline-flex items-center justify-center w-10 h-10 rounded-lg hover:bg-slate-800 transition" />
    </div>

    {{-- Backdrop, mobile only. --}}
    <div id="side-backdrop" hidden
         class="md:hidden fixed inset-0 bg-slate-900/60 z-40"></div>

    {{-- Off-canvas below md, an ordinary column from md up. The base classes are
         the closed state so the drawer is not briefly visible before Alpine
         initialises, and md:translate-x-0 pins it open on desktop regardless. --}}
    <aside id="admin-side" class="fixed md:static inset-y-0 left-0 z-50 w-60 bg-slate-900 text-slate-300 flex-shrink-0 flex flex-col
                  transform transition-transform duration-200 -translate-x-full md:translate-x-0">
        <div class="p-5 border-b border-slate-800 flex items-start justify-between gap-2">
            <div>
                <span class="text-lg font-extrabold text-white">DOMPET IJO</span>
                <div class="text-[10px] uppercase tracking-wider text-slate-500 font-bold mt-0.5">Admin Panel</div>
            </div>
            <button type="button" id="side-close" aria-label="Tutup menu"
                    class="md:hidden inline-flex items-center justify-center w-8 h-8 rounded-lg text-slate-400 hover:bg-slate-800 transition">
                <x-icon name="xmark" class="w-4 h-4" :solid="true" />
            </button>
        </div>
        <nav class="flex-1 p-3 space-y-1 text-sm font-semibold">
            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.dashboard') ? 'bg-primary text-white' : 'hover:bg-slate-800' }}">
                <x-icon name="chart-pie" class="w-4" :solid="true" /> Dashboard
            </a>
            <a href="{{ route('admin.users.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.users.*') ? 'bg-primary text-white' : 'hover:bg-slate-800' }}">
                <x-icon name="users" class="w-4" :solid="true" /> Pengguna
            </a>
            <a href="{{ route('admin.plans.index') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg {{ request()->routeIs('admin.plans.*') ? 'bg-primary text-white' : 'hover:bg-slate-800' }}">
                <x-icon name="tags" class="w-4" :solid="true" /> Paket Langganan
            </a>
        </nav>
        <div class="p-3 border-t border-slate-800 space-y-1">
            <x-theme-toggle
                labelled
                class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold hover:bg-slate-800 text-left" />
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold hover:bg-slate-800">
                <x-icon name="arrow-left" class="w-4" :solid="true" /> Ke Aplikasi
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="w-full flex items-center gap-3 px-3 py-2 rounded-lg text-sm font-semibold hover:bg-slate-800 text-left">
                    <x-icon name="right-from-bracket" class="w-4" :solid="true" /> Keluar
                </button>
            </form>
        </div>
    </aside>

    {{-- min-w-0 lets this column shrink below its content's intrinsic width, so
         a wide table scrolls inside its own wrapper instead of stretching the
         whole page.

         Padding is written as px/pb/pt rather than the p-* shorthand on purpose:
         a responsive `sm:p-6` sits in a later media query than a base `pt-20`
         and silently wins, which put the content back underneath the fixed
         mobile top bar between 640px and 767px — where that bar is still shown.
         Separate axes cannot collide, so pt-20 holds until md:pt-8 replaces it
         at exactly the width the bar disappears. --}}
    <main class="flex-1 min-w-0 max-w-6xl px-4 sm:px-6 md:px-8 pb-4 sm:pb-6 md:pb-8 pt-20 md:pt-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 font-semibold">
                {{ session('status') }}
            </div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm px-4 py-3 font-semibold">
                <ul class="list-disc pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </main>
</div>

{{-- Inline, un-deferred, no CDN. The main layout's nav toggle was moved off
     Alpine for this exact reason and this drawer was left behind: with
     cdn.jsdelivr.net blocked the aside stays translated off-screen below md,
     putting every admin page — including logout — out of reach behind a dead
     hamburger. --}}
<script>
(function () {
    var openBtn = document.getElementById('side-open');
    var closeBtn = document.getElementById('side-close');
    var side = document.getElementById('admin-side');
    var backdrop = document.getElementById('side-backdrop');
    if (!openBtn || !side) return;

    function set(open) {
        side.classList.toggle('!translate-x-0', open);
        if (backdrop) { backdrop.hidden = !open; }
        openBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    }

    openBtn.addEventListener('click', function () { set(true); });
    if (closeBtn) { closeBtn.addEventListener('click', function () { set(false); }); }
    if (backdrop) { backdrop.addEventListener('click', function () { set(false); }); }
})();
</script>

</body>
</html>
