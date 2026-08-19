@extends('layouts.admin')

@section('title', ($plan->exists ? 'Edit Paket' : 'Paket Baru').' — Admin')

@section('content')
<a href="{{ route('admin.plans.index') }}" class="text-sm text-slate-400 hover:text-slate-600 mb-4 inline-block"><x-icon name="arrow-left" class="mr-1 w-4 h-4" :solid="true" />Kembali</a>
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
            <label class="block text-xs font-bold text-slate-500 mb-2">Fitur yang termasuk paket ini</label>
            @php
                $selected = old('features', is_array($plan->features) ? $plan->features : []);
                $known = array_keys(config('subscription.features'));
                $legacy = array_diff(is_array($plan->features) ? $plan->features : [], $known);
            @endphp
            {{-- An unchecked box is a real restriction: the matching routes
                 answer 403 for subscribers on this plan (EnsurePlanFeature). --}}
            <div class="grid sm:grid-cols-2 gap-2">
                @foreach (config('subscription.features') as $key => $label)
                    <label class="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm cursor-pointer hover:bg-slate-50">
                        <input type="checkbox" name="features[]" value="{{ $key }}" class="rounded"
                               @checked(in_array($key, (array) $selected, true))>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
            @if ($legacy !== [])
                {{-- Plans predate the checkboxes and hold free-text bullets. Until
                     at least one box is ticked the plan is not restricted at all,
                     so nobody loses access just because this shipped. --}}
                <p class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 mt-2">
                    Paket ini masih memakai daftar fitur lama ({{ implode(', ', $legacy) }}) dan
                    <b>belum membatasi apa pun</b>. Centang di atas lalu simpan untuk mulai membatasi.
                </p>
            @endif
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
