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

    public function test_room_inclusive_split_matches_the_shared_calculator(): void
    {
        $this->seed(BillingDemoSeeder::class);

        $appliesTo = FolioItemAppliesTo::forItemType(FolioItemType::Room->value);
        $taxCalculator = app(TaxCalculator::class);
        $rules = $taxCalculator->activeRulesPayload($appliesTo);

        $this->assertNotEmpty($rules);
        $this->assertSame('service_charge', $rules[0]['code']);
        $this->assertFalse($rules[0]['is_compounding']);
        $this->assertSame('pbjt', $rules[1]['code']);
        $this->assertTrue($rules[1]['is_compounding']);

        $this->assertEquals(1.21, round(TaxAmountCalculator::inclusiveFactor($rules), 4));

        $amount = 1_690_000.0;
        $fromCalculator = $taxCalculator->extractInclusive($amount, $appliesTo);
        $fromShared = TaxAmountCalculator::extractInclusive($amount, $rules);

        $this->assertEquals($fromCalculator, $fromShared);
        $this->assertEquals(1_396_694.21, $fromShared['dpp']);
        $this->assertEquals(139_669.42, $fromShared['service_charge']);
        $this->assertEquals(153_636.36, $fromShared['tax']);
        $this->assertEquals(1_690_000, $fromShared['total']);
    }

    public function test_extract_inclusive_is_a_no_op_when_no_rules_apply(): void
    {
        $split = TaxAmountCalculator::extractInclusive(1_200_000, []);

        $this->assertEquals(1_200_000, $split['dpp']);
        $this->assertEquals(0, $split['service_charge']);
        $this->assertEquals(0, $split['tax']);
        $this->assertEquals(1_200_000, $split['total']);
    }
}
