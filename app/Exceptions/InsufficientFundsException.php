<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by PortfolioService::buy() when the wallet's cash balance can't
 * cover the order. StoreTradeRequest validates this up front for a
 * friendly form error; this is the defense-in-depth check taken inside
 * the locked DB transaction in case wallet state changed between form
 * validation and execution.
 */
class InsufficientFundsException extends RuntimeException
{
}
