<?php

namespace App\Enums;

enum SupplierInvoiceStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Paid = 'paid';
    case Disputed = 'disputed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::PendingApproval => 'Pending Approval',
            self::Approved => 'Approved',
            self::Paid => 'Paid',
            self::Disputed => 'Disputed',
        };
    }
}
