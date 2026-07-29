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
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @else
        <script src="https://cdn.tailwindcss.com"></script>
        <script>
            tailwind.config = {
                theme: {
                    extend: {
                        colors: { primary: '#4f46e5' },
                        fontFamily: { sans: ['Plus Jakarta Sans', 'sans-serif'] },
                    },
                },
            };
        </script>
    @endif

    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>[x-cloak] { display: none !important; }</style>
</head>
<body class="bg-slate-50 text-slate-900 font-sans min-h-screen flex items-center justify-center p-4">

<div class="w-full {{ $wide ?? false ? 'max-w-2xl' : 'max-w-md' }}">
    <div class="text-center mb-6">
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
