@extends('layouts.app')

@section('title', 'Market Heatmap — IDX Invest')

@section('content')
<div class="flex justify-between items-center mb-4">
    <div class="flex items-center gap-3">
        <a href="{{ route('dashboard') }}" class="text-sm font-bold bg-slate-100 text-slate-500 rounded-lg px-3 py-2 hover:bg-slate-200 transition"><x-icon name="arrow-left" class="mr-2 w-4 h-4" :solid="true" />Dash</a>
        <h1 class="text-lg font-extrabold bg-gradient-to-r from-primary to-purple-500 bg-clip-text text-transparent">MARKET MAP</h1>
    </div>
    <div class="hidden md:block text-right">
        <div class="flex gap-0.5 h-2 w-36 rounded overflow-hidden">
            <div class="flex-1" style="background:#b91c1c" title="-3%"></div>
            <div class="flex-1" style="background:#ef4444" title="-1%"></div>
            <div class="flex-1" style="background:#94a3b8" title="0%"></div>
            <div class="flex-1" style="background:#22c55e" title="+1%"></div>
            <div class="flex-1" style="background:#15803d" title="+3%"></div>
        </div>
        <div class="flex justify-between text-[10px] text-slate-400 w-36"><span>Bearish</span><span>Bullish</span></div>
    </div>
</div>

@if ($stockCount < 5)
    <div class="text-center py-16 bg-white border border-slate-200 rounded-2xl">
        <x-icon name="database" class="text-slate-200 mb-4 w-4 h-4" :solid="true" />
        <h4 class="font-bold">Data Belum Siap</h4>
        <p class="text-slate-400 mb-4">Silakan jalankan update market terlebih dahulu.</p>
        <code class="text-xs bg-slate-100 rounded px-2 py-1">php artisan idx:update-market-data</code>
    </div>
@else
    <div id="heatmapChart" class="bg-white border border-slate-200 rounded-2xl" style="height: 75vh;"></div>
@endif

@push('scripts')
<script>
var rawData = @json($treemap);

// null is not zero. A tile whose daily change could not be established — no
// baseline, or one too old or on the wrong basis to trust — used to arrive
// here as 0.0 and be painted green at "+0%". Slate says "not known", which is
// the truth, and is distinct from the neutral used for a genuinely flat day.
function getColor(val) {
    if (val === null || val === undefined) return '#334155';
    if (val >= 3) return '#15803d';
    if (val >= 0.1) return '#22c55e';
    if (val <= -3) return '#b91c1c';
    if (val <= -0.1) return '#ef4444';
    return '#475569';
}

function changeLabel(val) {
    return val === null || val === undefined ? 'n/a' : val + '%';
}

// The treemap's own chrome — the gaps between tiles and the sector header
// strip — is drawn onto a canvas, so it is the one part of this page CSS
// cannot reach. Left at white it turns into a grid of bright lines on a dark
// page, which is exactly the glare night mode is meant to remove.
function chartChrome() {
    var dark = document.documentElement.classList.contains('dark');

    return {
        gap: dark ? '#0b1120' : '#fff',
        headerText: dark ? '#a3b0c8' : '#555',
        headerBg: dark ? '#1b2740' : '#e2e8f0',
    };
}

var el = document.getElementById('heatmapChart');

// ECharts is around a megabyte — several times the rest of the bundle — and
// this is the only page that draws with it, so app.js keeps it behind a
// dynamic import and every other page never downloads it. The promise also
// covers the module-deferral problem: `echarts` did not exist at parse time
// once it stopped arriving from a blocking CDN tag.
// Deferred until DOMContentLoaded because window.loadECharts is defined by
// the bundle, and the bundle is loaded as a module: modules are deferred, so
// this inline classic script used to run first and the treemap threw
// "loadECharts is not a function" without drawing anything. Deferred scripts
// execute before DOMContentLoaded fires, so by here the bundle has run.
//
// (Do not name the Blade directive that emits that tag anywhere in this file,
// even inside a JavaScript comment — Blade compiles directives wherever they
// appear, including in here, and calls it with no arguments.)
if (el) document.addEventListener('DOMContentLoaded', function () { window.loadECharts().then(function (echarts) {
    var chart = echarts.init(el);
    var formatted = rawData.map(sector => ({
        name: sector.name,
        children: sector.children.map(s => ({ name: s.name, value: s.value, itemStyle: { color: getColor(s.value[1]) } })),
    }));

    var chrome = chartChrome();

    chart.setOption({
        tooltip: {
            formatter: (info) => {
                const val = info.data.value;
                if (!val || val.length < 3) return '';
                const mcap = val[3] > 0 ? (val[3] / 1e12).toFixed(1) + ' T' : '-';
                const price = new Intl.NumberFormat('id-ID').format(val[2]);
                const unknown = val[1] === null || val[1] === undefined;
                const sign = unknown || val[1] < 0 ? '' : '+';
                const colour = unknown ? '#94a3b8' : (val[1] >= 0 ? '#059669' : '#dc2626');
                const change = unknown ? 'perubahan harian tidak tersedia' : `${sign}${val[1]}%`;
                return `<div style="font-family:sans-serif;padding:4px;"><div style="font-weight:bold;font-size:14px;">${info.name}</div><div style="font-size:12px;">Rp ${price}</div><div style="font-weight:800;font-size:14px;color:${colour}">${change}</div><div style="font-size:10px;color:#888;margin-top:4px;">MCap: ${mcap}</div></div>`;
            },
        },
        series: [{
            type: 'treemap', data: formatted, width: '100%', height: '100%',
            roam: 'move', nodeClick: false, breadcrumb: { show: false },
            label: { show: true, formatter: (p) => p.name + '\n' + changeLabel(p.data.value[1]), fontSize: 11, fontWeight: 'bold', color: '#fff' },
            itemStyle: { borderColor: chrome.gap, borderWidth: 1, gapWidth: 1 },
            levels: [
                { itemStyle: { borderWidth: 0, gapWidth: 1 } },
                { itemStyle: { borderColor: chrome.gap, borderWidth: 3, gapWidth: 3 }, upperLabel: { show: true, height: 20, fontSize: 10, color: chrome.headerText, backgroundColor: chrome.headerBg, fontWeight: 'bold' } },
                { itemStyle: { borderWidth: 1, gapWidth: 1 } },
            ],
        }],
    });
    window.addEventListener('resize', () => chart.resize());

    window.addEventListener('theme-changed', function () {
        var next = chartChrome();

        // Merged rather than re-set: setOption patches by default, so the data
        // and the tile colours computed above are left untouched.
        chart.setOption({
            series: [{
                itemStyle: { borderColor: next.gap },
                levels: [
                    { itemStyle: { borderWidth: 0, gapWidth: 1 } },
                    { itemStyle: { borderColor: next.gap, borderWidth: 3, gapWidth: 3 }, upperLabel: { show: true, height: 20, fontSize: 10, color: next.headerText, backgroundColor: next.headerBg, fontWeight: 'bold' } },
                    { itemStyle: { borderWidth: 1, gapWidth: 1 } },
                ],
            }],
        });
    });
    chart.on('click', (params) => { if (params.data?.name) window.location.href = `/stocks/${params.data.name}`; });
}); });
</script>
@endpush
@endsection
