<?php

namespace App\Events;

use App\Models\StockMovement;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockMovementRecorded
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public StockMovement $stockMovement,
    ) {}
}
