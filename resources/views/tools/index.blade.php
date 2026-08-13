@extends('layouts.app')

@section('title', 'Tools & Kalkulator — IDX Invest')

@section('content')
<div class="flex justify-between items-center mb-6">
    <h1 class="text-xl font-extrabold"><x-icon name="calculator" class="text-primary mr-2 w-4 h-4" :solid="true" />Tools &amp; Kalkulator</h1>
    <a href="{{ route('dashboard') }}" class="text-sm font-bold border border-slate-200 rounded-lg px-3 py-2 hover:bg-slate-50 transition">&laquo; Dashboard</a>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-sm"><x-icon name="layer-group" class="text-amber-500 mr-2 w-4 h-4" :solid="true" />Average Down Helper</div>
        <div class="p-4">
            <label class="block text-xs font-bold text-slate-500 mb-1">Posisi Sekarang — Harga Avg</label>
            <input type="number" id="avgPrice" placeholder="Contoh: 1000" class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-2">
            <label class="block text-xs font-bold text-slate-500 mb-1">Total Lot</label>
            <input type="number" id="avgLot" placeholder="Contoh: 10" class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-4">

            <hr class="my-3 border-slate-100">

            <label class="block text-xs font-bold text-slate-500 mb-1">Pembelian Baru — Harga Beli</label>
            <input type="number" id="newPrice" placeholder="Harga skrg" class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-2">
            <label class="block text-xs font-bold text-slate-500 mb-1">Beli Lot</label>
            <input type="number" id="newLot" placeholder="Mau beli brp lot?" class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-4">

            <div class="bg-indigo-50 text-center rounded-lg p-3 mb-3">
                <small class="text-slate-500">Harga Average Baru:</small>
                <h2 class="text-2xl font-extrabold" id="resultAvg">Rp 0</h2>
                <small id="resultModal" class="text-slate-500">Total Modal Baru: Rp 0</small>
            </div>
            <button onclick="calcAvg()" class="w-full rounded-lg bg-primary text-white font-bold py-2 hover:bg-indigo-700 transition">Hitung</button>
        </div>
    </div>

    <div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
        <div class="px-4 py-3 bg-slate-50 border-b border-slate-100 font-bold text-sm"><x-icon name="chart-line" class="text-emerald-500 mr-2 w-4 h-4" :solid="true" />Investment Forecasting</div>
        <div class="p-4">
            <label class="block text-xs font-bold text-slate-500 mb-1">Modal Awal (Rp)</label>
            <input type="number" id="fcModal" value="10000000" class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-2">
            <label class="block text-xs font-bold text-slate-500 mb-1">Top Up Bulanan (Rp)</label>
            <input type="number" id="fcTopup" value="1000000" class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-2">
            <label class="block text-xs font-bold text-slate-500 mb-1">Target Profit / Bulan (%)</label>
            <input type="number" id="fcReturn" value="5" class="w-full rounded-lg border border-slate-200 px-3 py-2 mb-4">

            <div class="grid grid-cols-2 gap-3 mb-3 text-center">
                <div class="border border-slate-200 rounded-lg p-2 bg-slate-50"><small class="text-slate-400">1 Tahun</small><h5 class="font-extrabold text-emerald-600" id="res1Yr">Rp 0</h5></div>
                <div class="border border-slate-200 rounded-lg p-2 bg-slate-50"><small class="text-slate-400">3 Tahun</small><h5 class="font-extrabold text-emerald-600" id="res3Yr">Rp 0</h5></div>
            </div>
            <button onclick="calcForecast()" class="w-full rounded-lg bg-emerald-600 text-white font-bold py-2 hover:bg-emerald-700 transition">Hitung Proyeksi</button>
        </div>
    </div>
</div>

@push('scripts')
<script>
function calcAvg() {
    const avgPrice = parseFloat(document.getElementById('avgPrice').value) || 0;
    const avgLot = parseFloat(document.getElementById('avgLot').value) || 0;
    const newPrice = parseFloat(document.getElementById('newPrice').value) || 0;
    const newLot = parseFloat(document.getElementById('newLot').value) || 0;

    const totalLot = avgLot + newLot;
    const totalModal = (avgPrice * avgLot * 100) + (newPrice * newLot * 100);
    const newAvg = totalLot > 0 ? totalModal / (totalLot * 100) : 0;

    document.getElementById('resultAvg').innerText = 'Rp ' + Math.round(newAvg).toLocaleString('id-ID');
    document.getElementById('resultModal').innerText = 'Total Modal Baru: Rp ' + Math.round(totalModal).toLocaleString('id-ID');
}

function calcForecast() {
    const modal = parseFloat(document.getElementById('fcModal').value) || 0;
    const topup = parseFloat(document.getElementById('fcTopup').value) || 0;
    const rate = (parseFloat(document.getElementById('fcReturn').value) || 0) / 100;

    function project(months) {
        let balance = modal;
        for (let i = 0; i < months; i++) {
            balance = (balance + topup) * (1 + rate);
        }
        return balance;
    }

    document.getElementById('res1Yr').innerText = 'Rp ' + Math.round(project(12)).toLocaleString('id-ID');
    document.getElementById('res3Yr').innerText = 'Rp ' + Math.round(project(36)).toLocaleString('id-ID');
}

calcForecast();
</script>
@endpush
@endsection
