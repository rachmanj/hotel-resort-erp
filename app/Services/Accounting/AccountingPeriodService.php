<?php

namespace App\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Models\AccountingPeriod;
use App\Models\Hotel;
use App\Models\User;
use Carbon\Carbon;
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
}
