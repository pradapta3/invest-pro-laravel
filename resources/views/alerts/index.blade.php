@extends('layouts.app')

@section('title', 'Price Alert — IDX Invest')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-extrabold"><i class="fa-solid fa-bell text-amber-500 mr-2"></i>Price Alert</h1>
    <a href="{{ route('dashboard') }}" class="text-sm font-bold border border-slate-200 rounded-lg px-3 py-2 hover:bg-slate-50 transition">&laquo; Dashboard</a>
</div>

@if (session('status'))
    <div class="bg-emerald-50 text-emerald-700 text-sm font-bold rounded-lg px-4 py-3 mb-4">{{ session('status') }}</div>
@endif

@unless (auth()->user()->hasLinkedTelegram())
    <div class="bg-amber-50 text-amber-700 text-sm font-bold rounded-lg px-4 py-3 mb-4 flex items-center justify-between">
        <span><i class="fa-solid fa-triangle-exclamation mr-1"></i>Telegram belum terhubung — alert tidak akan terkirim.</span>
        <a href="{{ route('telegram.link') }}" class="underline">Hubungkan sekarang</a>
    </div>
@endunless

<datalist id="alertStockOptions">
    @foreach ($tickerOptions as $opt)
        <option value="{{ $opt->cleanTicker() }}">{{ $opt->nama_perusahaan }}</option>
    @endforeach
</datalist>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden md:col-span-1 h-fit">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-sm">Buat Alert Baru</div>
        <form method="POST" action="{{ route('alerts.store') }}" class="p-4">
            @csrf
            <label class="block text-xs font-bold text-slate-500 mb-1">Ticker</label>
            <input type="text" name="ticker" list="alertStockOptions" required
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-3 font-bold uppercase"
                   value="{{ old('ticker') }}">

            <label class="block text-xs font-bold text-slate-500 mb-1">Kondisi</label>
            <select name="direction" required class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-3">
                <option value="above" {{ old('direction') === 'above' ? 'selected' : '' }}>Harga naik ke atas ini</option>
                <option value="below" {{ old('direction') === 'below' ? 'selected' : '' }}>Harga turun ke bawah ini</option>
            </select>

            <label class="block text-xs font-bold text-slate-500 mb-1">Target Harga (Rp)</label>
            <input type="number" name="target_price" step="0.01" required
                   class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-4 font-bold"
                   value="{{ old('target_price') }}">

            @error('ticker') <p class="text-xs text-red-600 mb-2">{{ $message }}</p> @enderror
            @error('target_price') <p class="text-xs text-red-600 mb-2">{{ $message }}</p> @enderror

            <button type="submit" class="w-full rounded-lg bg-primary text-white font-bold py-2 hover:bg-indigo-700 transition">
                Buat Alert
            </button>
        </form>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden md:col-span-2">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-sm">Alert Anda</div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs font-bold text-slate-400 uppercase border-b border-slate-100">
                        <th class="px-4 py-2">Ticker</th>
                        <th class="px-4 py-2">Kondisi</th>
                        <th class="px-4 py-2">Target</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($alerts as $alert)
                        <tr class="border-b border-slate-50">
                            <td class="px-4 py-3 font-bold">{{ $alert->stockRef?->cleanTicker() ?? str_replace('.JK', '', $alert->ticker) }}</td>
                            <td class="px-4 py-3 text-slate-500">{{ $alert->direction === 'above' ? 'Naik ke atas' : 'Turun ke bawah' }}</td>
                            <td class="px-4 py-3 font-num">Rp {{ number_format((float) $alert->target_price, 0, ',', '.') }}</td>
                            <td class="px-4 py-3">
                                @if ($alert->isActive())
                                    <span class="inline-flex items-center rounded-full bg-sky-50 text-sky-600 text-xs font-bold px-2 py-1">Aktif</span>
                                @else
                                    <span class="inline-flex items-center rounded-full bg-slate-100 text-slate-500 text-xs font-bold px-2 py-1">Terpicu {{ $alert->triggered_at->diffForHumans() }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('alerts.destroy', $alert) }}" onsubmit="return confirm('Hapus alert ini?');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-slate-400 hover:text-red-600" title="Hapus">
                                        <i class="fa-solid fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-4 py-8 text-center text-slate-400">Belum ada alert. Buat satu di sebelah kiri.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
