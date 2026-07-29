<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by PortfolioService::sell() when the position doesn't hold
 * enough lots to cover the order. See InsufficientFundsException for why
 * this exists alongside the FormRequest validation.
 */
class InsufficientLotsException extends RuntimeException
{
}
