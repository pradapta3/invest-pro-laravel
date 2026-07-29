<?php

return [

    // Shares per lot on the Indonesia Stock Exchange. Was a bare `* 100`
    // scattered through portfolio.php, index.php and detail.php.
    'lot_size' => (int) env('TRADING_LOT_SIZE', 100),

    'fees' => [
        'buy_rate' => (float) env('TRADING_BUY_FEE_RATE', 0.0015),
        'sell_rate' => (float) env('TRADING_SELL_FEE_RATE', 0.0025),
    ],

    // Seed balance for the single-row simulated wallet (id=1).
    'starting_cash_balance' => (float) env('TRADING_STARTING_CASH', 100_000_000),

];
