@extends('layouts.admin')

@section('title', $user->name.' — Admin')

@section('content')
<a href="{{ route('admin.users.index') }}" class="text-sm text-slate-400 hover:text-slate-600 mb-4 inline-block"><x-icon name="arrow-left" class="mr-1 w-4 h-4" :solid="true" />Kembali</a>
<h1 class="text-xl font-extrabold mb-6">{{ $user->name }}</h1>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white border border-slate-200 rounded-2xl p-5">
        <h2 class="font-bold mb-4">Data Akun</h2>
        <form method="POST" action="{{ route('admin.users.update', $user) }}" class="space-y-4">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Nama</label>
                <input type="text" name="name" value="{{ old('name', $user->name) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Email</label>
                <input type="email" name="email" value="{{ old('email', $user->email) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Password Baru (opsional)</label>
                <input type="password" name="password" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Kosongkan jika tidak diubah">
            </div>
            <div>
                <label class="block text-xs font-bold text-slate-500 mb-1">Konfirmasi Password</label>
                <input type="password" name="password_confirmation" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
            </div>
            <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
                <input type="checkbox" name="is_admin" value="1" @checked($user->is_admin) class="rounded">
                Jadikan Administrator
            </label>

            <button type="submit" class="rounded-lg bg-primary text-white font-bold px-5 py-2 text-sm hover:bg-indigo-700">Simpan</button>
        </form>
    </div>

    <div class="space-y-6">
        <div class="bg-white border border-slate-200 rounded-2xl p-5">
            <h2 class="font-bold mb-4">Aktivasi Langganan</h2>
            <form method="POST" action="{{ route('admin.users.subscription.activate', $user) }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Paket</label>
                    <select name="subscription_plan_id" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        @foreach ($plans as $plan)
                            <option value="{{ $plan->id }}">{{ $plan->name }} — Rp {{ number_format((float) $plan->price_per_month, 0, ',', '.') }}/bln</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-slate-500 mb-1">Durasi (bulan)</label>
                    <input type="number" name="months" value="1" min="1" max="24" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
                </div>
                <button type="submit" class="w-full rounded-lg bg-emerald-600 text-white font-bold py-2 text-sm hover:bg-emerald-700">
                    <x-icon name="check" class="mr-1 w-4 h-4" :solid="true" />Aktivasi
                </button>
            </form>

            @if ($user->hasActiveSubscription())
                <form method="POST" action="{{ route('admin.users.subscription.cancel', $user) }}" class="mt-2" onsubmit="return confirm('Batalkan langganan aktif {{ $user->name }}?')">
                    @csrf
                    <button type="submit" class="w-full rounded-lg bg-red-50 text-red-600 font-bold py-2 text-sm hover:bg-red-100">Batalkan Langganan Aktif</button>
                </form>
            @endif
        </div>

        <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
            <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase tracking-wide text-slate-500">Riwayat Langganan</div>
            <div class="divide-y divide-slate-100">
                @forelse ($subscriptions as $sub)
                    <div class="px-4 py-3 flex justify-between items-center text-sm">
                        <div>
                            <div class="font-bold">{{ $sub->plan->name }}</div>
                            <div class="text-xs text-slate-400">
                                {{ $sub->starts_at?->format('d M Y') ?? '-' }} – {{ $sub->ends_at?->format('d M Y') ?? '-' }}
                            </div>
                        </div>
                        <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $sub->status->value === 'active' ? 'bg-emerald-100 text-emerald-700' : ($sub->status->value === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-slate-100 text-slate-500') }}">
                            {{ $sub->status->label() }}
                        </span>
                    </div>
                @empty
                    <div class="px-4 py-6 text-center text-sm text-slate-400">Belum ada riwayat.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
