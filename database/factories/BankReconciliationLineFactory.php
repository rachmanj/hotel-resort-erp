<?php

namespace Database\Factories;

use App\Enums\BankStatementLineStatus;
use App\Models\BankReconciliationLine;
use App\Support\BankReconciliationSupport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliationLine>
 */
class BankReconciliationLineFactory extends Factory
{
    protected $model = BankReconciliationLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $postingDate = now()->startOfMonth()->addDays(fake()->numberBetween(0, 20))->toDateString();
        $amount = 1_000_000.0;
        $reference = fake()->uuid();
        $description = fake()->sentence();

        return [
            'statement_date' => $postingDate,
            'posting_date' => $postingDate,
            'statement_amount' => $amount,
            'statement_line_ref' => $reference,
            'reference' => $reference,
            'description' => $description,
            'debit' => 0,
            'credit' => $amount,
            'amount' => $amount,
            'direction' => 'credit',
            'match_status' => BankStatementLineStatus::Unmatched,
            'line_hash' => BankReconciliationSupport::lineHash(
                $postingDate,
                'credit',
                $amount,
                $reference,
                $description,
            ),
        ];
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_matched' => true,
            'match_status' => BankStatementLineStatus::Matched,
            'matched_at' => now(),
        ]);
    }

    public function excluded(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_status' => BankStatementLineStatus::Excluded,
            'exclude_reason' => 'Test exclusion',
        ]);
    }

    public function outstanding(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_status' => BankStatementLineStatus::Outstanding,
        ]);
    }
}
