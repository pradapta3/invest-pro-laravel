<?php

namespace App\Support;

use App\Models\StockPrice;
use Illuminate\Support\Carbon;

/**
 * How old the figures on a page are, said the same way everywhere.
 *
 * The header badge is rendered by Blade on first paint and then kept moving by
 * the live-quote poller, which means two places describe the same timestamp.
 * When each wrote its own wording they disagreed almost immediately: the
 * server printed "13 Agt 15:49" out of hours while the browser rebuilt the
 * string from parts and printed "13 Agt 15:4". Both the view and the endpoint
 * now read this, so there is one answer to format.
 *
 * The question it answers is "when did this app last hear from the exchange",
 * not "when did this one row change" — hence the max over the whole table.
 */
final class QuoteFreshness
{
    /**
     * @return array{label: string, title: string, stale: bool, open: bool, text_class: string, dot_class: string, at: ?string}
     */
    public static function current(): array
    {
        $lastWrite = StockPrice::query()->max('updated_at');

        return self::describe($lastWrite ? Carbon::parse($lastWrite, config('app.timezone')) : null);
    }

    /**
     * @return array{label: string, title: string, stale: bool, open: bool, text_class: string, dot_class: string, at: ?string}
     */
    public static function describe(?Carbon $lastWrite): array
    {
        $open = MarketClock::isOpen();
        $stale = MarketClock::isStale($lastWrite);

        if ($lastWrite === null) {
            return self::pack('belum ada data', 'Belum ada harga tersimpan — jalankan idx:update-realtime-quotes.', true, $open, null);
        }

        $time = $lastWrite->translatedFormat('H:i:s');

        return self::pack(
            // Outside a session the date matters more than the clock: "15:49"
            // on a Sunday reads as though it were minutes ago.
            $open ? $time : $lastWrite->translatedFormat('d M H:i'),
            match (true) {
                $stale => "Harga terakhir diperbarui {$time} — lebih lama dari yang diharapkan, periksa scheduler.",
                $open => "Harga diperbarui {$time} WIB.",
                default => 'Bursa tutup. Ini harga dari sesi terakhir, '.$lastWrite->translatedFormat('D d M Y H:i').'.',
            },
            $stale,
            $open,
            $lastWrite,
        );
    }

    /**
     * @return array{label: string, title: string, stale: bool, open: bool, text_class: string, dot_class: string, at: ?string}
     */
    private static function pack(string $label, string $title, bool $stale, bool $open, ?Carbon $at): array
    {
        return [
            'label' => $label,
            'title' => $title,
            'stale' => $stale,
            'open' => $open,
            // Amber is a complaint — the scheduler should have written by now.
            // Grey is not: outside the session there is nothing to write, and
            // the last close is the right thing to be showing.
            'text_class' => $stale ? 'text-amber-600' : 'text-slate-400',
            'dot_class' => $stale ? 'bg-amber-500' : ($open ? 'bg-emerald-500' : 'bg-slate-300'),
            'at' => $at?->toIso8601String(),
        ];
    }
}
