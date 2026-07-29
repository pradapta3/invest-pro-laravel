@extends('layouts.admin')

@section('title', 'Admin Dashboard — IDX Invest')

@section('content')
<h1 class="text-xl font-extrabold mb-6">Dashboard Admin</h1>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs font-bold text-slate-400 uppercase">Total Pengguna</div>
        <div class="text-2xl font-extrabold">{{ $totalUsers }}</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs font-bold text-slate-400 uppercase">Langganan Aktif</div>
        <div class="text-2xl font-extrabold text-emerald-600">{{ $activeCount }}</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs font-bold text-slate-400 uppercase">Menunggu Aktivasi</div>
        <div class="text-2xl font-extrabold text-amber-500">{{ $pendingCount }}</div>
    </div>
    <div class="bg-white border border-slate-200 rounded-2xl p-4">
        <div class="text-xs font-bold text-slate-400 uppercase">Estimasi MRR</div>
        <div class="text-xl font-extrabold">Rp {{ number_format($estimatedMrr, 0, ',', '.') }}</div>
    </div>
</div>

<div class="grid md:grid-cols-2 gap-6">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 flex justify-between items-center">
            <h2 class="font-bold text-sm">Menunggu Aktivasi</h2>
            <a href="{{ route('admin.users.index') }}" class="text-xs font-bold text-primary hover:underline">Lihat semua</a>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse ($pendingSubscriptions as $sub)
                <div class="px-4 py-3 flex justify-between items-center">
                    <div>
                        <div class="font-bold text-sm">{{ $sub->user->name }}</div>
                        <div class="text-xs text-slate-400">{{ $sub->plan->name }} — Rp {{ number_format((float) $sub->plan->price_per_month, 0, ',', '.') }}/bln</div>
                    </div>
                    <a href="{{ route('admin.users.edit', $sub->user) }}" class="text-xs font-bold bg-primary text-white rounded-lg px-3 py-1.5 hover:bg-indigo-700">Aktivasi</a>
                </div>
            @empty
                <div class="px-4 py-6 text-center text-sm text-slate-400">Tidak ada yang menunggu.</div>
            @endforelse
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100">
            <h2 class="font-bold text-sm">Pendaftar Terbaru</h2>
        </div>
        <div class="divide-y divide-slate-100">
            @foreach ($recentUsers as $u)
                <div class="px-4 py-3 flex justify-between items-center">
                    <div>
                        <div class="font-bold text-sm">{{ $u->name }}</div>
                        <div class="text-xs text-slate-400">{{ $u->email }}</div>
                    </div>
                    <span class="text-xs text-slate-400">{{ $u->created_at->diffForHumans() }}</span>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection
