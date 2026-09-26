<?php

namespace App\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\BankReconciliationStatus;
use App\Models\AccountingPeriod;
use App\Models\BankReconciliation;
use App\Models\Hotel;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class AccountingPeriodService
{
    public function openPeriod(
        Hotel $hotel,
        string $name,
        Carbon $startDate,
        Carbon $endDate,
    ): AccountingPeriod {
        if ($startDate->greaterThan($endDate)) {
            throw new InvalidArgumentException('Period start date must be on or before end date.');
        }

        $overlap = AccountingPeriod::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where(function ($query) use ($startDate, $endDate): void {
                $query->whereBetween('start_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhereBetween('end_date', [$startDate->toDateString(), $endDate->toDateString()])
                    ->orWhere(function ($query) use ($startDate, $endDate): void {
                        $query->where('start_date', '<=', $startDate->toDateString())
                            ->where('end_date', '>=', $endDate->toDateString());
                    });
            })
            ->exists();

        if ($overlap) {
            throw new InvalidArgumentException('An accounting period already exists for this date range.');
        }

        return AccountingPeriod::query()->create([
            'hotel_id' => $hotel->id,
            'name' => $name,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'status' => AccountingPeriodStatus::Open->value,
        ]);
    }

    public function closePeriod(AccountingPeriod $period, User $closedBy): AccountingPeriod
    {
        if ($period->status === AccountingPeriodStatus::Closed) {
            throw new InvalidArgumentException('Accounting period is already closed.');
        }

        $blocking = $this->unfinishedBankReconciliationsBlockingClose($period);

        if ($blocking->isNotEmpty()) {
            $labels = $blocking
                ->map(fn (BankReconciliation $reconciliation): string => $this->reconciliationDisplayLabel($reconciliation))
                ->implode(', ');

            throw new InvalidArgumentException(sprintf(
                'Cannot close %s: %d bank reconciliation(s) are not completed yet (%s).',
                $period->name,
                $blocking->count(),
                $labels,
            ));
        }

        $period->update([
            'status' => AccountingPeriodStatus::Closed->value,
            'closed_at' => now(),
            'closed_by' => $closedBy->id,
        ]);

        return $period->fresh();
    }

    public function ensureCurrentPeriod(Hotel $hotel): AccountingPeriod
    {
        return $this->ensurePeriodForDate($hotel, now());
    }

    public function ensurePeriodForDate(Hotel $hotel, Carbon $date): AccountingPeriod
    {
        $existing = AccountingPeriod::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('start_date', '<=', $date->toDateString())
            ->where('end_date', '>=', $date->toDateString())
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->openPeriod(
            $hotel,
            $date->format('Y-m'),
            $date->copy()->startOfMonth(),
            $date->copy()->endOfMonth(),
        );
    }

    /**
     * @return Collection<int, BankReconciliation>
     */
    private function unfinishedBankReconciliationsBlockingClose(AccountingPeriod $period): Collection
    {
        return BankReconciliation::query()
            ->with('bankAccount')
            ->whereHas('bankAccount', fn ($query) => $query->where('hotel_id', $period->hotel_id))
            ->whereNotIn('status', [
                BankReconciliationStatus::Completed->value,
                BankReconciliationStatus::Void->value,
            ])
            ->get()
            ->filter(fn (BankReconciliation $reconciliation): bool => $this->reconciliationOverlapsPeriod($reconciliation, $period))
            ->values();
    }

    private function reconciliationOverlapsPeriod(BankReconciliation $reconciliation, AccountingPeriod $period): bool
    {
        $periodEnd = $reconciliation->period_end_date ?? $reconciliation->periode;

        if ($periodEnd === null) {
            return false;
        }

        $periodStart = $reconciliation->periode ?? $periodEnd->copy()->startOfMonth();

        return $periodStart->toDateString() <= $period->end_date->toDateString()
            && $periodEnd->toDateString() >= $period->start_date->toDateString();
    }

    private function reconciliationDisplayLabel(BankReconciliation $reconciliation): string
    {
        $reconciliation->loadMissing('bankAccount');

        $bankName = $reconciliation->bankAccount->bank_name;
        $periodLabel = $reconciliation->period_end_date?->format('Y-m')
            ?? $reconciliation->periode?->format('Y-m')
            ?? 'unknown period';

        return trim("{$bankName} {$periodLabel}");
    }
}
