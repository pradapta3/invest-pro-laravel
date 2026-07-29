{{-- Shared by stocks/detail.blade.php and seasonality/index.blade.php --}}
@php
    $months = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Jul',8=>'Agu',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $years = range(now()->year - 4, now()->year);
    $stats = $seasonality['stats'];
    $maxAbs = max(1, collect($stats)->max(fn ($s) => abs($s['avg'])));

    $heatClass = function (float $val) {
        if ($val >= 10) return 'bg-emerald-800 text-white';
        if ($val >= 5) return 'bg-emerald-500 text-white';
        if ($val > 0) return 'bg-emerald-100 text-emerald-900';
        if ($val <= -10) return 'bg-red-800 text-white';
        if ($val <= -5) return 'bg-red-500 text-white';
        if ($val < 0) return 'bg-red-100 text-red-900';
        return 'bg-slate-50 text-slate-400';
    };
@endphp

@if (empty($monthlyByYear))
    <div class="text-center py-8 text-sm text-slate-400">Data historis tidak cukup.</div>
@else
    <div class="flex items-end gap-1 h-24 mb-4 border-b border-slate-200 pb-0">
        @foreach ($months as $num => $name)
            @php
                $val = $stats[$num]['avg'];
                $h = (abs($val) / $maxAbs) * 100;
            @endphp
            <div class="flex-1 flex flex-col items-center justify-end h-full">
                <span class="text-[10px] font-bold {{ $val >= 0 ? 'text-emerald-600' : 'text-red-600' }}">{{ round($val, 1) }}%</span>
                <div class="w-full rounded-t {{ $val >= 0 ? 'bg-emerald-500' : 'bg-red-500' }}" style="height: {{ max(2, $h) }}%"></div>
                <span class="text-[10px] text-slate-400 mt-1">{{ substr($name, 0, 1) }}</span>
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto">
        <table class="w-full text-center text-xs">
            <thead class="text-slate-400 border-b border-slate-100">
                <tr>
                    <th class="text-left py-1">Bulan</th>
                    <th class="py-1">Win%</th>
                    @foreach ($years as $y)
                        <th class="py-1">{{ $y }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach ($months as $num => $name)
                    @php $wr = $stats[$num]['win_rate']; @endphp
                    <tr>
                        <td class="text-left font-bold py-1">{{ $name }}</td>
                        <td class="py-1 font-bold {{ $wr >= 80 ? 'text-emerald-600' : ($wr <= 20 ? 'text-red-600' : 'text-slate-400') }}">{{ round($wr) }}%</td>
                        @foreach ($years as $y)
                            @php $val = $monthlyByYear[$num][$y] ?? 0; @endphp
                            <td class="py-1.5 font-bold border border-white {{ $heatClass($val) }}">{{ $val != 0 ? round($val, 1).'%' : '-' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
