{{-- Expects: $financials (Collection<StockFinancial>, newest first), $ref --}}

@php
    // Growth is read against the column to the right, so the pairs are built
    // once here rather than re-derived inside every row.
    $years = $financials->values();
    $previousOf = fn (int $i) => $years[$i + 1] ?? null;

    // Rupiah figures run to the tens of trillions, so the table shows them
    // abbreviated and keeps the exact number in a tooltip.
    $money = fn (?float $v) => $v === null ? '-' : ($v < 0 ? '-' : '').'Rp '.\App\Support\Format::compactRupiah(abs($v));
    $pct = fn (?float $v) => $v === null ? '-' : number_format($v, 1).'%';
    $ratio = fn (?float $v) => $v === null ? '-' : number_format($v, 2).'x';
@endphp

<div class="bg-white border border-slate-200 rounded-2xl overflow-hidden">
    <div class="px-4 py-2.5 bg-slate-50 border-b border-slate-100 text-xs font-bold uppercase tracking-wide text-slate-500 flex items-center justify-between gap-3">
        <span>Laporan Keuangan Tahunan</span>
        @if ($financials->isNotEmpty())
            <span class="normal-case font-medium text-slate-400">{{ $financials->count() }} tahun terakhir</span>
        @endif
    </div>

    @if ($financials->isEmpty())
        <div class="p-6 text-center">
            <div class="text-sm text-slate-400">Belum ada data laporan keuangan untuk {{ $ref->cleanTicker() }}.</div>
            {{-- Named explicitly: an empty panel with no explanation reads as a
                 bug, when the usual cause is simply that the fetch has not run
                 for this emiten yet. --}}
            <div class="text-[11px] text-slate-400 mt-2">
                Jalankan <code class="bg-slate-100 px-1 py-0.5 rounded">php artisan idx:update-financials --ticker={{ $ref->cleanTicker() }}</code>
            </div>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm whitespace-nowrap">
                <thead class="bg-slate-50 text-xs uppercase text-slate-400">
                    <tr>
                        {{-- The label column stays put while the years scroll: six columns do not
                             fit a phone, and without this you lose track of which line you
                             are reading two columns in. --}}
                        <th class="text-left px-4 py-3 font-bold sticky left-0 bg-slate-50 z-10">Pos</th>
                        @foreach ($years as $y)
                            <th class="text-right px-4 py-3 font-bold">{{ $y->fiscal_year }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @php
                        $sections = [
                            'Laba Rugi' => [
                                ['Pendapatan', 'revenue', 'money'],
                                ['Laba Kotor', 'gross_profit', 'money'],
                                ['Laba Usaha', 'operating_income', 'money'],
                                ['Laba Bersih', 'net_income', 'money'],
                                ['EPS', 'eps', 'eps'],
                            ],
                            'Neraca' => [
                                ['Total Aset', 'total_assets', 'money'],
                                ['Total Liabilitas', 'total_liabilities', 'money'],
                                ['Total Ekuitas', 'total_equity', 'money'],
                                ['Total Utang', 'total_debt', 'money'],
                                ['Kas & Setara', 'cash_and_equivalents', 'money'],
                            ],
                            'Arus Kas' => [
                                ['Operasi', 'operating_cash_flow', 'money'],
                                ['Investasi', 'investing_cash_flow', 'money'],
                                ['Pendanaan', 'financing_cash_flow', 'money'],
                                ['Belanja Modal', 'capital_expenditure', 'money'],
                            ],
                        ];
                    @endphp

                    @foreach ($sections as $section => $rows)
                        <tr class="bg-slate-50">
                            <td colspan="{{ $years->count() + 1 }}" class="px-4 py-1.5"><div class="sticky left-4 w-fit text-[10px] font-bold uppercase tracking-wider text-slate-400">{{ $section }}</div></td>
                        </tr>
                        @foreach ($rows as [$label, $field, $format])
                            <tr class="group hover:bg-slate-50">
                                <td class="px-4 py-2.5 text-slate-500 sticky left-0 bg-white group-hover:bg-slate-50 z-10">{{ $label }}</td>
                                @foreach ($years as $y)
                                    @php $value = $y->{$field}; @endphp
                                    <td class="px-4 py-2.5 text-right font-num font-semibold {{ $value !== null && $value < 0 ? 'text-red-600' : '' }}"
                                        @if ($value !== null) title="{{ number_format($value, 2) }}" @endif>
                                        {{ $format === 'eps' ? ($value === null ? '-' : number_format($value, 2)) : $money($value) }}
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach

                    <tr class="bg-slate-50">
                        <td colspan="{{ $years->count() + 1 }}" class="px-4 py-1.5"><div class="sticky left-4 w-fit text-[10px] font-bold uppercase tracking-wider text-slate-400">Rasio &amp; Pertumbuhan</div></td>
                    </tr>

                    @foreach ([
                        ['Margin Kotor', 'grossMargin'],
                        ['Margin Usaha', 'operatingMargin'],
                        ['Margin Bersih', 'netMargin'],
                        ['ROE', 'roe'],
                        ['ROA', 'roa'],
                    ] as [$label, $method])
                        <tr class="group hover:bg-slate-50">
                            <td class="px-4 py-2.5 text-slate-500 sticky left-0 bg-white group-hover:bg-slate-50 z-10">{{ $label }}</td>
                            @foreach ($years as $y)
                                @php $v = $y->{$method}(); @endphp
                                <td class="px-4 py-2.5 text-right font-num font-semibold {{ $v !== null && $v < 0 ? 'text-red-600' : '' }}">{{ $pct($v) }}</td>
                            @endforeach
                        </tr>
                    @endforeach

                    <tr class="group hover:bg-slate-50">
                        <td class="px-4 py-2.5 text-slate-500 sticky left-0 bg-white group-hover:bg-slate-50 z-10">DER</td>
                        @foreach ($years as $y)
                            <td class="px-4 py-2.5 text-right font-num font-semibold">{{ $ratio($y->debtToEquity()) }}</td>
                        @endforeach
                    </tr>

                    <tr class="group hover:bg-slate-50">
                        <td class="px-4 py-2.5 text-slate-500 sticky left-0 bg-white group-hover:bg-slate-50 z-10" title="Arus kas operasi dibagi laba bersih. Jauh di bawah 100% berarti laba belum menjadi kas.">Konversi Kas</td>
                        @foreach ($years as $y)
                            @php $v = $y->cashConversion(); @endphp
                            <td class="px-4 py-2.5 text-right font-num font-semibold {{ $v !== null && $v < 50 ? 'text-amber-600' : '' }}">{{ $pct($v) }}</td>
                        @endforeach
                    </tr>

                    <tr class="group hover:bg-slate-50">
                        <td class="px-4 py-2.5 text-slate-500 sticky left-0 bg-white group-hover:bg-slate-50 z-10">Arus Kas Bebas</td>
                        @foreach ($years as $y)
                            @php $v = $y->freeCashFlow(); @endphp
                            <td class="px-4 py-2.5 text-right font-num font-semibold {{ $v !== null && $v < 0 ? 'text-red-600' : '' }}">{{ $money($v) }}</td>
                        @endforeach
                    </tr>

                    @foreach ([['Pertumbuhan Pendapatan', 'revenue'], ['Pertumbuhan Laba Bersih', 'net_income']] as [$label, $field])
                        <tr class="group hover:bg-slate-50">
                            <td class="px-4 py-2.5 text-slate-500 sticky left-0 bg-white group-hover:bg-slate-50 z-10">{{ $label }}</td>
                            @foreach ($years as $i => $y)
                                @php $g = $y->growthOver($previousOf($i), $field); @endphp
                                <td class="px-4 py-2.5 text-right font-num font-semibold {{ match (true) {
                                    $g === null => '',
                                    $g > 0 => 'text-emerald-600',
                                    default => 'text-red-600',
                                } }}">{{ $g === null ? '-' : ($g > 0 ? '+' : '').number_format($g, 1).'%' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-4 py-2.5 border-t border-slate-100 text-[11px] text-slate-400">
            Angka dalam Rupiah, disingkat (M = miliar, T = triliun) — arahkan kursor untuk nilai penuh.
            &ldquo;-&rdquo; berarti pos tersebut tidak dilaporkan, bukan nol.
        </div>
    @endif
</div>
