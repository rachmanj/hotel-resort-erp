<?php

namespace Database\Factories;

use App\Enums\BankReconciliationStatus;
use App\Models\BankReconciliation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliation>
 */
class BankReconciliationFactory extends Factory
{
    protected $model = BankReconciliation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $periodEnd = now()->endOfMonth();

        return [
            'period_end_date' => $periodEnd,
            'periode' => $periodEnd->toDateString(),
            'statement_balance' => 1_000_000,
            'book_balance' => 1_000_000,
            'status' => BankReconciliationStatus::InProgress,
            'source_mode' => 'manual',
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BankReconciliationStatus::Completed,
        ]);
    }
}
