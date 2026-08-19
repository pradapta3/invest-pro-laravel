@extends('layouts.admin')

@section('title', 'Paket Langganan — Admin')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-extrabold">Paket Langganan</h1>
    <a href="{{ route('admin.plans.create') }}" class="rounded-lg bg-primary text-white font-bold px-4 py-2 text-sm hover:bg-indigo-700">
        <x-icon name="plus" class="mr-1 w-4 h-4" :solid="true" />Paket Baru
    </a>
</div>

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    {{-- Scrolls inside the card on a phone rather than widening the page. --}}
    <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[36rem]">
            <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                <tr>
                    <th class="text-left px-4 py-3">Nama</th>
                    <th class="text-left py-3">Harga/Bulan</th>
                    <th class="text-left py-3">Pelanggan</th>
                    <th class="text-left py-3">Status</th>
                    <th class="text-right px-4 py-3">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($plans as $plan)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-bold">{{ $plan->name }}</td>
                        <td class="py-3">Rp {{ number_format((float) $plan->price_per_month, 0, ',', '.') }}</td>
                        <td class="py-3">{{ $plan->subscriptions_count }}</td>
                        <td class="py-3">
                            <span class="text-xs font-bold px-2 py-0.5 rounded-full {{ $plan->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">
                                {{ $plan->is_active ? 'Aktif' : 'Nonaktif' }}
                            </span>
                        </td>
                        <td class="text-right px-4 py-3 space-x-2">
                            <a href="{{ route('admin.plans.edit', $plan) }}" class="text-xs font-bold text-primary hover:underline">Edit</a>
                            <form method="POST" action="{{ route('admin.plans.destroy', $plan) }}" class="inline" onsubmit="return confirm('Hapus paket {{ $plan->name }}?')">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-xs font-bold text-red-500 hover:underline">Hapus</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
