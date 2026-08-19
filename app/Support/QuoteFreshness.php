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
    /** Memoised newest write, and when that was worked out. */
    private static ?Carbon $newestWrite = null;

    private static ?float $newestWriteReadAt = null;

    /**
     * @return array{label: string, title: string, stale: bool, open: bool, text_class: string, dot_class: string, at: ?string}
     */
    public static function current(): array
    {
        return self::describe(self::newestWrite());
    }

    /**
     * When any emiten was last written.
     *
     * Also the yardstick for whether one row has stopped being written while
     * the rest of the board moves — see StockPrice::rowIsFrozen() — which is a
     * per-row question asked of every row on the page, so it cannot be a query
     * each. Memoised, with a short expiry so a queue worker or a long-lived
     * command does not answer from a snapshot taken an hour ago.
     */
    public static function newestWrite(): ?Carbon
    {
        if (self::$newestWriteReadAt !== null && microtime(true) - self::$newestWriteReadAt < 30) {
            return self::$newestWrite;
        }

        $max = StockPrice::query()->max('updated_at');

        self::$newestWrite = $max ? Carbon::parse($max, config('app.timezone')) : null;
        self::$newestWriteReadAt = microtime(true);

        return self::$newestWrite;
    }

    /** Drops the memo, for tests and for commands that have just written. */
    public static function forget(): void
    {
        self::$newestWrite = null;
        self::$newestWriteReadAt = null;
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
