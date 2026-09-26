<?php

namespace Database\Factories;

use App\Enums\BankBookLineStatus;
use App\Models\BankReconciliationBookLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliationBookLine>
 */
class BankReconciliationBookLineFactory extends Factory
{
    protected $model = BankReconciliationBookLine::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $postingDate = now()->startOfMonth()->addDays(fake()->numberBetween(0, 20));

        return [
            'posting_date' => $postingDate,
            'doc_num' => fake()->numerify('JV-####'),
            'reference_number' => fake()->uuid(),
            'description' => fake()->sentence(),
            'debit' => 1_000_000,
            'credit' => 0,
            'match_status' => BankBookLineStatus::Unmatched,
        ];
    }

    public function matched(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_status' => BankBookLineStatus::Matched,
        ]);
    }

    public function excluded(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_status' => BankBookLineStatus::Excluded,
            'exclude_reason' => 'Test exclusion',
        ]);
    }

    public function outstanding(): static
    {
        return $this->state(fn (array $attributes) => [
            'match_status' => BankBookLineStatus::Outstanding,
        ]);
    }

    public function stale(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_stale' => true,
            'stale_reason' => 'GL row changed after snapshot',
        ]);
    }
}
