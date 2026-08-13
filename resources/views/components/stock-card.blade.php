@props(['ticker', 'name' => null, 'price', 'score' => null, 'badges' => [], 'planEntry' => null, 'planTp' => null, 'planSl' => null, 'detailUrl' => null])

<div class="bg-white border border-slate-200 rounded-2xl p-4 hover:shadow-lg hover:-translate-y-0.5 transition">
    <div class="flex items-start justify-between mb-3">
        <div>
            <h4 class="font-extrabold text-slate-900">{{ $ticker }}</h4>
            @if ($name)
                <div class="text-xs text-slate-500 truncate max-w-[10rem]">{{ $name }}</div>
            @endif
        </div>
        @if ($score !== null)
            <x-score-badge :score="$score" />
        @endif
    </div>

    <div class="text-lg font-extrabold text-emerald-600 mb-3">Rp {{ number_format($price) }}</div>

    @if (count($badges))
        <div class="flex flex-wrap gap-1 mb-3">
            @foreach ($badges as $badge)
                <span class="text-[11px] font-bold px-2 py-0.5 rounded-md {{ $badge['class'] ?? 'bg-slate-100 text-slate-600' }}">{{ $badge['label'] }}</span>
            @endforeach
        </div>
    @endif

    @if ($planEntry || $planTp || $planSl)
        <div class="bg-slate-50 border border-dashed border-slate-300 rounded-lg p-2.5 text-xs mb-3 space-y-1">
            @if ($planEntry)
                <div class="flex justify-between"><span class="text-slate-500">Entry</span><span class="font-bold text-primary">{{ $planEntry }}</span></div>
            @endif
            @if ($planTp)
                <div class="flex justify-between"><span class="text-slate-500">Target</span><span class="font-bold text-emerald-600">{{ $planTp }}</span></div>
            @endif
            @if ($planSl)
                <div class="flex justify-between"><span class="text-slate-500">Stop</span><span class="font-bold text-red-600">{{ $planSl }}</span></div>
            @endif
        </div>
    @endif

    <div class="flex gap-2">
        <button type="button" onclick="sendSignal('{{ $ticker }}', '{{ $attributes->get('signal-format', 'standard') }}')"
                class="w-9 h-9 shrink-0 rounded-lg bg-sky-100 text-sky-600 hover:bg-sky-600 hover:text-white transition flex items-center justify-center">
            <x-icon name="paper-plane" class="w-4 h-4" :solid="true" />
        </button>
        @if ($detailUrl)
            <a href="{{ $detailUrl }}" class="flex-1 rounded-lg bg-slate-100 text-slate-600 hover:bg-slate-700 hover:text-white transition text-sm font-bold flex items-center justify-center gap-1">
                Detail <x-icon name="arrow-right" class="text-xs w-4 h-4" :solid="true" />
            </a>
        @endif
    </div>
</div>
