<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InsufficientLotsException;
use App\Models\StockRef;
use App\Models\UserHistory;
use App\Models\UserPortfolio;
use App\Models\UserWallet;
use App\Services\MarketData\MarketDataService;
use App\ValueObjects\PortfolioHolding;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Buy/sell/deposit orchestration and portfolio valuation, replacing
 * portfolio.php's inline handlers. Every mutation that touches both the
 * wallet and a position runs inside a row-locked DB transaction — the
 * legacy code had none, so a mid-request failure could desync cash
 * balance from open positions.
 *
 * Every method is scoped to a $userId — the app used to be single-user
 * (one wallet, hardcoded id=1); it now serves multiple subscribers, each
 * with their own wallet/positions/history, so nothing here may touch
 * another user's row. Callers must pass the *authenticated* user's id
 * (never one taken from request input) — see PortfolioController.
 */
class PortfolioService
{
    public function __construct(private readonly MarketDataService $marketData)
    {
    }

    public function wallet(int $userId): UserWallet
    {
        return UserWallet::query()->firstOrCreate(
            ['user_id' => $userId],
            ['cash_balance' => config('trading.starting_cash_balance'), 'realized_pl' => 0]
        );
    }

    public function deposit(int $userId, float $amount): UserWallet
    {
        return DB::transaction(function () use ($userId, $amount) {
            $wallet = UserWallet::query()->lockForUpdate()->where('user_id', $userId)->first() ?? $this->wallet($userId);
            $wallet->increment('cash_balance', $amount);

            return $wallet->fresh();
        });
    }

    /**
     * @throws InsufficientFundsException
     */
    public function buy(int $userId, string $ticker, float $price, int $lots): UserPortfolio
    {
        $ticker = $this->normalizeTicker($ticker);
        $lotSize = config('trading.lot_size');
        $value = $price * $lots * $lotSize;
        $fee = $value * config('trading.fees.buy_rate');

        return DB::transaction(function () use ($userId, $ticker, $price, $lots, $value, $fee, $lotSize) {
            $wallet = UserWallet::query()->lockForUpdate()->where('user_id', $userId)->first() ?? $this->wallet($userId);

            if ((float) $wallet->cash_balance < ($value + $fee)) {
                throw new InsufficientFundsException("Insufficient cash balance to buy {$lots} lot(s) of {$ticker}.");
            }

            $position = UserPortfolio::query()->lockForUpdate()->where('user_id', $userId)->where('ticker', $ticker)->first();

            // avg_price includes the buy fee, not just the raw price — otherwise
            // a round-trip buy-then-sell at an unchanged price would show as
            // break-even when it actually cost the fee twice. sell() already
            // subtracts the sell fee on top, so this is the only place the buy
            // fee needs to be folded in.
            if ($position) {
                $newLots = $position->lots + $lots;
                $newAvgPrice = ((($position->lots * $lotSize) * (float) $position->avg_price) + $value + $fee) / ($newLots * $lotSize);
                $position->update(['avg_price' => $newAvgPrice, 'lots' => $newLots]);
            } else {
                $position = UserPortfolio::create([
                    'user_id' => $userId,
                    'ticker' => $ticker,
                    'avg_price' => ($value + $fee) / ($lots * $lotSize),
                    'lots' => $lots,
                ]);
            }

            $wallet->decrement('cash_balance', $value + $fee);

            UserHistory::create([
                'user_id' => $userId,
                'ticker' => $ticker,
                'type' => TransactionType::Buy,
                'price' => $price,
                'lots' => $lots,
                'value' => $value,
                'profit_loss' => 0,
                'date' => now(),
            ]);

            return $position->fresh();
        });
    }

    /**
     * @throws InsufficientLotsException
     */
    public function sell(int $userId, string $ticker, float $price, int $lots): ?UserPortfolio
    {
        $ticker = $this->normalizeTicker($ticker);
        $lotSize = config('trading.lot_size');
        $value = $price * $lots * $lotSize;
        $fee = $value * config('trading.fees.sell_rate');

        return DB::transaction(function () use ($userId, $ticker, $price, $lots, $value, $fee, $lotSize) {
            $wallet = UserWallet::query()->lockForUpdate()->where('user_id', $userId)->first() ?? $this->wallet($userId);
            $position = UserPortfolio::query()->lockForUpdate()->where('user_id', $userId)->where('ticker', $ticker)->first();

            if (! $position || $position->lots < $lots) {
                throw new InsufficientLotsException("Not enough lots of {$ticker} to sell {$lots} lot(s).");
            }

            $realizedPl = ($price - (float) $position->avg_price) * $lots * $lotSize;
            $netPl = $realizedPl - $fee;

            $remaining = null;
            if ($lots === $position->lots) {
                $position->delete();
            } else {
                $position->decrement('lots', $lots);
                $remaining = $position->fresh();
            }

            $wallet->increment('cash_balance', $value - $fee);
            $wallet->increment('realized_pl', $netPl);

            UserHistory::create([
                'user_id' => $userId,
                'ticker' => $ticker,
                'type' => TransactionType::Sell,
                'price' => $price,
                'lots' => $lots,
                'value' => $value,
                'profit_loss' => $netPl,
                'date' => now(),
            ]);

            return $remaining;
        });
    }

    /**
     * @return Collection<int, PortfolioHolding>
     */
    public function holdings(int $userId): Collection
    {
        $lotSize = config('trading.lot_size');

        return UserPortfolio::query()
            ->where('user_id', $userId)
            ->with(['stockRef.price'])
            ->get()
            ->sortByDesc(fn (UserPortfolio $p) => $p->lots * (float) $p->avg_price)
            ->map(function (UserPortfolio $position) use ($lotSize) {
                $price = $position->stockRef?->price;
                $closePrice = (float) ($price?->close_price ?? 0);
                $isLive = false;
                $current = $closePrice;

                if ($current <= 0) {
                    $live = $this->marketData->livePrice($position->ticker);
                    if ($live > 0) {
                        $current = $live;
                        $isLive = true;
                    }
                }
                if ($current <= 0) {
                    $current = (float) $position->avg_price;
                }

                $marketValue = $current * $position->lots * $lotSize;
                $costBasis = (float) $position->avg_price * $position->lots * $lotSize;
                $pl = $marketValue - $costBasis;
                $plPct = $costBasis > 0 ? ($pl / $costBasis) * 100 : 0.0;

                return new PortfolioHolding(
                    position: $position,
                    companyName: $position->stockRef?->nama_perusahaan,
                    currentPrice: $current,
                    marketValue: $marketValue,
                    profitLoss: $pl,
                    profitLossPct: $plPct,
                    isLivePrice: $isLive,
                );
            })
            ->values();
    }

    /**
     * @return array{wallet: UserWallet, holdings: Collection<int, PortfolioHolding>, total_equity: float, stock_value: float, unrealized_pl: float, win_rate: float}
     */
    public function summary(int $userId): array
    {
        $wallet = $this->wallet($userId);
        $holdings = $this->holdings($userId);

        $stockValue = (float) $holdings->sum(fn (PortfolioHolding $h) => $h->marketValue);
        $unrealizedPl = (float) $holdings->sum(fn (PortfolioHolding $h) => $h->profitLoss);

        $sellTrades = UserHistory::query()->where('user_id', $userId)->where('type', TransactionType::Sell)->get();
        $winRate = $sellTrades->count() > 0
            ? ($sellTrades->where('profit_loss', '>', 0)->count() / $sellTrades->count()) * 100
            : 0.0;

        return [
            'wallet' => $wallet,
            'holdings' => $holdings,
            'total_equity' => (float) $wallet->cash_balance + $stockValue,
            'stock_value' => $stockValue,
            'unrealized_pl' => $unrealizedPl,
            'win_rate' => $winRate,
        ];
    }

    public function recentHistory(int $userId, int $limit = 10): Collection
    {
        return UserHistory::query()->where('user_id', $userId)->orderByDesc('id')->limit($limit)->get();
    }

    /**
     * @return Collection<int, StockRef>
     */
    public function tickerOptions(): Collection
    {
        return StockRef::query()->orderBy('ticker')->get(['ticker', 'nama_perusahaan']);
    }

    /**
     * Normalized portfolio-vs-IHSG performance series for the dashboard
     * chart, replacing portfolio.php's `get_chart_data` AJAX handler.
     *
     * @return array{labels: string[], ihsg: float[], portfolio: float[], last_ihsg: float, last_port: float}|array{error: string}
     */
    public function performanceSeries(int $userId, string $uiRange): array
    {
        [$yahooRange, $interval] = match ($uiRange) {
            '1d' => ['1d', '15m'],
            '1wk' => ['5d', '60m'],
            'ytd' => ['ytd', '1d'],
            '1y' => ['1y', '1d'],
            default => ['1mo', '1d'],
        };

        $ihsgChart = $this->marketData->dailyChart('^JKSE', $yahooRange, $interval);
        if (empty($ihsgChart['timestamps'])) {
            return ['error' => 'Gagal ambil data'];
        }

        $lotSize = config('trading.lot_size');
        $holdings = UserPortfolio::query()->where('user_id', $userId)->pluck('lots', 'ticker');

        $stocksHistory = [];
        foreach ($holdings as $ticker => $lots) {
            $chart = $this->marketData->dailyChart($ticker, $yahooRange, $interval);
            $map = [];
            foreach ($chart['timestamps'] as $i => $ts) {
                $close = $chart['close'][$i] ?? null;
                if ($close !== null && $close > 0) {
                    $map[$ts] = $close;
                }
            }
            $stocksHistory[$ticker] = $map;
        }

        $labels = [];
        $dataIhsg = [];
        $dataPort = [];
        $baseIhsg = 0.0;
        $basePort = 0.0;

        foreach ($ihsgChart['timestamps'] as $i => $ts) {
            $valIhsg = $ihsgChart['close'][$i] ?? 0;
            if ($valIhsg == 0) {
                continue;
            }

            $valPort = 0.0;
            $validItems = 0;
            foreach ($holdings as $ticker => $lots) {
                $price = $this->findClosestPrice($stocksHistory[$ticker] ?? [], $ts);
                if ($price > 0) {
                    $valPort += $price * $lots * $lotSize;
                    $validItems++;
                }
            }

            if ($baseIhsg == 0.0) {
                $baseIhsg = $valIhsg;
            }
            if ($basePort == 0.0 && $valPort > 0) {
                $basePort = $valPort;
            }

            if ($baseIhsg > 0 && $basePort > 0 && $validItems > 0) {
                $labels[] = in_array($uiRange, ['1d', '1wk']) ? date('H:i', $ts) : date('d M', $ts);
                $dataIhsg[] = round((($valIhsg - $baseIhsg) / $baseIhsg) * 100, 2);
                $dataPort[] = round((($valPort - $basePort) / $basePort) * 100, 2);
            }
        }

        return [
            'labels' => $labels,
            'ihsg' => $dataIhsg,
            'portfolio' => $dataPort,
            'last_ihsg' => (float) (end($dataIhsg) ?: 0),
            'last_port' => (float) (end($dataPort) ?: 0),
        ];
    }

    private function findClosestPrice(array $history, int $targetTimestamp): float
    {
        if (isset($history[$targetTimestamp])) {
            return $history[$targetTimestamp];
        }

        $closest = 0.0;
        $minDiff = 86400 * 5;

        foreach ($history as $ts => $price) {
            $diff = abs($ts - $targetTimestamp);
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $price;
            }
        }

        return $closest;
    }

    private function normalizeTicker(string $ticker): string
    {
        $ticker = strtoupper(trim($ticker));

        return str_contains($ticker, '.JK') ? $ticker : $ticker.'.JK';
    }
}
