@extends('layouts.admin')

@section('title', ($plan->exists ? 'Edit Paket' : 'Paket Baru').' — Admin')

@section('content')
<a href="{{ route('admin.plans.index') }}" class="text-sm text-slate-400 hover:text-slate-600 mb-4 inline-block"><i class="fa-solid fa-arrow-left mr-1"></i>Kembali</a>
<h1 class="text-xl font-extrabold mb-6">{{ $plan->exists ? 'Edit Paket' : 'Paket Baru' }}</h1>

<div class="bg-white border border-slate-200 rounded-2xl p-5 max-w-xl">
    <form method="POST" action="{{ $plan->exists ? route('admin.plans.update', $plan) : route('admin.plans.store') }}" class="space-y-4">
        @csrf
        @if ($plan->exists) @method('PUT') @endif

        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Nama Paket</label>
            <input type="text" name="name" value="{{ old('name', $plan->name) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Slug (URL-safe, unik)</label>
            <input type="text" name="slug" value="{{ old('slug', $plan->slug) }}" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Harga per Bulan (Rp)</label>
            <input type="number" name="price_per_month" value="{{ old('price_per_month', $plan->price_per_month) }}" step="1000" min="0" required class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Deskripsi</label>
            <textarea name="description" rows="2" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">{{ old('description', $plan->description) }}</textarea>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Fitur (satu per baris)</label>
            <textarea name="features" rows="4" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm" placeholder="Akses semua screener&#10;Backtest tanpa batas&#10;Broadcast Telegram">{{ old('features', is_array($plan->features) ? implode("\n", $plan->features) : '') }}</textarea>
        </div>
        <div>
            <label class="block text-xs font-bold text-slate-500 mb-1">Urutan Tampil</label>
            <input type="number" name="sort_order" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" min="0" class="w-full rounded-lg border border-slate-200 px-3 py-2 text-sm">
        </div>
        <label class="flex items-center gap-2 text-sm font-semibold text-slate-600">
            <input type="checkbox" name="is_active" value="1" @checked(old('is_active', $plan->is_active ?? true)) class="rounded">
            Tampilkan di halaman pendaftaran
        </label>

        <button type="submit" class="rounded-lg bg-primary text-white font-bold px-5 py-2 text-sm hover:bg-indigo-700">Simpan</button>
    </form>
</div>
@endsection
