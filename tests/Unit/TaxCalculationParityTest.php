<?php

namespace Tests\Unit;

use App\Enums\FolioItemType;
use App\Services\TaxCalculator;
use App\Support\FolioItemAppliesTo;
use App\Support\TaxAmountCalculator;
use Database\Seeders\BillingDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TaxCalculationParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_misc_charge_tax_payload_matches_calculator_for_known_amount(): void
    {
        $this->seed(BillingDemoSeeder::class);

        $appliesTo = FolioItemAppliesTo::forItemType(FolioItemType::Misc->value);
        $taxCalculator = app(TaxCalculator::class);
        $rules = $taxCalculator->activeRulesPayload($appliesTo);

        $this->assertNotEmpty($rules);
        $this->assertSame('service_charge', $rules[0]['code']);
        $this->assertFalse($rules[0]['is_compounding']);

        $amount = 500_000.0;
        $fromCalculator = $taxCalculator->calculate($amount, $appliesTo);
        $fromShared = TaxAmountCalculator::calculate($amount, $rules);

        $this->assertEquals($fromCalculator, $fromShared);
        $this->assertEquals(500_000, $fromShared['subtotal']);
        $this->assertEquals(50_000, $fromShared['service_charge']);
        $this->assertEquals(60_500, $fromShared['tax']);
        $this->assertEquals(610_500, $fromShared['total']);
    }
}
