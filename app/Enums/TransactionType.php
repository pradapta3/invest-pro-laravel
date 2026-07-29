<?php

namespace App\Enums;

enum TransactionType: string
{
    case Buy = 'BUY';
    case Sell = 'SELL';
}
