<?php

namespace App\Exceptions;

use Exception;

class OutstandingBalanceException extends Exception
{
    public function __construct(public readonly float $balance)
    {
        $formatted = number_format($balance, 0, ',', '.');

        parent::__construct("Outstanding balance of Rp {$formatted} must be settled before checkout.");
    }
}
