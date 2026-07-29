{{-- Expects: $rows (Collection<StockPrice>), $type --}}
@if ($rows->isEmpty())
    <div class="text-center py-10 text-slate-400">Tidak ada saham yang memenuhi kriteria.</div>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                <tr>
                    <th class="text-left px-4 py-3">Ticker</th>
                    <th class="text-left py-3">Price</th>
                    <th class="text-left py-3">Metrics</th>
                    <th class="text-right px-4 py-3">Analysis</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach ($rows as $row)
                    @php $t = str_replace('.JK', '', $row->ticker); @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <a href="{{ route('stocks.show', $t) }}" class="font-bold hover:text-primary">{{ $t }}</a>
                            <div class="text-xs text-slate-400 font-normal">{{ $row->stockRef->nama_perusahaan ?? '' }}</div>
                        </td>
                        <td class="py-3">{{ number_format($row->close_price) }}</td>
                        <td class="py-3">
                            @if ($type === 'magic')
                                <span class="text-[11px] font-bold bg-indigo-50 text-primary px-2 py-0.5 rounded mr-1">ROE {{ round($row->stockRef->roe) }}%</span>
                                <span class="text-[11px] font-bold bg-sky-50 text-sky-600 px-2 py-0.5 rounded">PER {{ round($row->stockRef->pe_ratio, 1) }}x</span>
                            @elseif ($type === 'bandar')
                                <span class="text-[11px] font-bold bg-amber-100 text-amber-700 px-2 py-0.5 rounded">Vol {{ round($row->volumeSpikeRatio(), 1) }}x</span>
                            @elseif ($type === 'trend')
                                <span class="text-[11px] font-bold bg-emerald-500 text-white px-2 py-0.5 rounded">RSI {{ round($row->rsi_14) }}</span>
                            @elseif ($type === 'pullback')
                                <span class="text-[11px] font-bold bg-red-50 text-red-600 px-2 py-0.5 rounded mr-1">RSI {{ round($row->rsi_14) }}</span>
                                <span class="text-[11px] font-bold bg-slate-500 text-white px-2 py-0.5 rounded">Near MA20</span>
                            @elseif ($type === 'value')
                                <span class="text-[11px] font-bold bg-emerald-50 text-emerald-600 px-2 py-0.5 rounded mr-1">PBV {{ round($row->stockRef->pb_ratio, 2) }}x</span>
                                <span class="text-[11px] font-bold bg-sky-50 text-sky-600 px-2 py-0.5 rounded">DER {{ round($row->stockRef->der, 2) }}x</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <button onclick="analyzeStock('{{ $t }}')" class="rounded-full bg-slate-900 text-white text-xs font-bold px-3 py-1.5 hover:bg-slate-700 transition">
                                <i class="fa-solid fa-wand-magic-sparkles"></i> AI
                            </button>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
