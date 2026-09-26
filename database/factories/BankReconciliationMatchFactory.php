<?php

namespace Database\Factories;

use App\Enums\BankMatchType;
use App\Models\BankReconciliationMatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankReconciliationMatch>
 */
class BankReconciliationMatchFactory extends Factory
{
    protected $model = BankReconciliationMatch::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'match_type' => BankMatchType::Manual,
            'bank_total' => 1_000_000,
            'book_total' => 1_000_000,
            'difference' => 0,
        ];
    }
}
