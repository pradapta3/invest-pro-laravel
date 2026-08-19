<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'IDX Invest')</title>


        @vite(['resources/css/app.css', 'resources/js/app.js'])

    @include('partials.theme')

    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans min-h-screen flex items-center justify-center p-4">

<div class="w-full {{ $wide ?? false ? 'max-w-2xl' : 'max-w-md' }}">
    <div class="text-center mb-6 relative">
        {{-- Reachable before signing in, so the login page itself is not the
             one glaring screen left. --}}
        <x-theme-toggle class="absolute right-0 top-0 bg-white border border-slate-200 text-slate-400 hover:bg-slate-50" />

        <a href="{{ route('login') }}" class="text-2xl font-extrabold tracking-tight bg-gradient-to-r from-primary to-purple-500 bg-clip-text text-transparent">
            DOMPET IJO
        </a>
        <p class="text-sm text-slate-400 mt-1">IDX Stock Analysis, Screener &amp; Portfolio</p>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl shadow-sm p-6 md:p-8">
        @if (session('status'))
            <div class="mb-4 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm px-4 py-3 font-semibold">
                {{ session('status') }}
            </div>
        @endif

        @yield('content')
    </div>
</div>

</body>
</html>
