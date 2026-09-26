<?php

namespace App\Services\Accounting;

use App\Models\JournalEntry;
use Illuminate\Support\Facades\DB;

class JournalEntryNumberService
{
    public function nextNumber(): string
    {
        return DB::transaction(function (): string {
            $prefix = 'JV-'.now()->format('Ym').'-';

            $lastNo = JournalEntry::query()
                ->withoutGlobalScope('hotel')
                ->where('journal_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('journal_no')
                ->value('journal_no');

            $sequence = 1;
            if ($lastNo !== null) {
                $sequence = (int) substr($lastNo, -4) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
