<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SupplierInvoiceCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public int $hotelId,
        public int $supplierInvoiceId,
        public float $totalAmount,
        public string $description = 'Supplier invoice',
    ) {}
}
