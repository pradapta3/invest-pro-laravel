@extends('layouts.app')

@section('title', 'Pengaturan Telegram — IDX Invest')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-extrabold"><x-icon name="telegram" class="text-sky-500 mr-2 w-4 h-4" :solid="true" />Pengaturan Telegram</h1>
    <a href="{{ route('dashboard') }}" class="text-sm font-bold border border-slate-200 rounded-lg px-3 py-2 hover:bg-slate-50 transition">&laquo; Dashboard</a>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden max-w-lg">
    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase tracking-wide text-slate-500">Status Koneksi</div>
    <div class="p-5">
        @if ($user->hasLinkedTelegram())
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center">
                    <x-icon name="check" class="w-4 h-4" :solid="true" />
                </div>
                <div>
                    <div class="font-bold text-sm">Terhubung</div>
                    <div class="text-xs text-slate-500">Tombol "Kirim Sinyal" akan masuk ke chat Telegram ini.</div>
                </div>
            </div>
            <form method="POST" action="{{ route('telegram.link.destroy') }}" onsubmit="return confirm('Putuskan koneksi Telegram?');">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm font-bold text-red-600 hover:text-red-700">
                    <x-icon name="link-slash" class="mr-1 w-4 h-4" :solid="true" />Putuskan Koneksi
                </button>
            </form>
        @else
            <div class="flex items-center gap-3 mb-4">
                <div class="w-10 h-10 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center">
                    <x-icon name="xmark" class="w-4 h-4" :solid="true" />
                </div>
                <div>
                    <div class="font-bold text-sm">Belum Terhubung</div>
                    <div class="text-xs text-slate-500">Hubungkan supaya sinyal yang Anda kirim masuk ke HP Anda sendiri.</div>
                </div>
            </div>

            @if (! $botToken || ! $webhookSecret)
                {{-- The code is redeemed by the bot receiving /LINK, so without a
                     token and a webhook secret it can never be redeemed. Say so
                     instead of handing out one that silently does nothing. --}}
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-4 text-sm text-amber-800">
                    <div class="font-bold mb-1">Telegram belum dikonfigurasi di server</div>
                    <p class="text-xs leading-relaxed">
                        Menghubungkan akun butuh
                        @if (! $botToken)<code class="bg-white px-1 rounded border border-amber-200">TELEGRAM_BOT_TOKEN</code>@endif
                        @if (! $botToken && ! $webhookSecret) dan @endif
                        @if (! $webhookSecret)<code class="bg-white px-1 rounded border border-amber-200">TELEGRAM_WEBHOOK_SECRET</code>@endif
                        terisi di <code class="bg-white px-1 rounded border border-amber-200">.env</code>, lalu webhook didaftarkan dengan
                        <code class="bg-white px-1 rounded border border-amber-200">php artisan idx:telegram-webhook</code>.
                        Hubungi administrator.
                    </p>
                </div>
            @else
                @if ($user->telegram_link_code && $user->telegram_link_code_expires_at?->isFuture())
                    <div class="bg-indigo-50 rounded-lg p-4 mb-4">
                        <div class="text-xs text-slate-500 mb-1">Kode Anda (berlaku {{ $user->telegram_link_code_expires_at->diffForHumans(null, true) }} lagi):</div>
                        <div class="text-2xl font-extrabold tracking-widest font-num text-center mb-3">{{ $user->telegram_link_code }}</div>

                        @if ($botUsername)
                            <a href="https://t.me/{{ $botUsername }}?start={{ $user->telegram_link_code }}"
                               target="_blank"
                               class="block w-full text-center rounded-lg bg-sky-500 text-white font-bold py-2 hover:bg-sky-600 transition mb-2">
                                <x-icon name="telegram" class="mr-1 w-4 h-4" :solid="true" />Buka Bot &amp; Hubungkan
                            </a>
                        @endif
                        <p class="text-xs text-slate-500 text-center">
                            {{ $botUsername ? 'Atau kirim' : 'Kirim' }} manual ke bot
                            @if ($botUsername)
                                <b>&#64;{{ $botUsername }}</b>:
                            @else
                                {{-- Without TELEGRAM_BOT_USERNAME there is no link and no name to
                                     show, so spell out that the bot has to be found by hand. --}}
                                Telegram Anda (tanyakan namanya ke administrator):
                            @endif
                            <code class="bg-white px-1.5 py-0.5 rounded border border-slate-200">/LINK {{ $user->telegram_link_code }}</code>
                        </p>
                    </div>
                @endif

                <form method="POST" action="{{ route('telegram.link.generate') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2 hover:bg-indigo-700 transition">
                        {{ $user->telegram_link_code && $user->telegram_link_code_expires_at?->isFuture() ? 'Buat Kode Baru' : 'Buat Kode Penghubung' }}
                    </button>
                </form>
            @endif
        @endif
    </div>
</div>
@endsection
