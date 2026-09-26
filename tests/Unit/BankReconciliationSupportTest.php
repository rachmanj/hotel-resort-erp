<?php

namespace Tests\Unit;

use App\Support\BankReconciliationSupport;
use PHPUnit\Framework\TestCase;

class BankReconciliationSupportTest extends TestCase
{
    public function test_normalize_reference_strips_separators_and_uppercases(): void
    {
        $this->assertSame(
            'TRF20260912',
            BankReconciliationSupport::normalizeReference('TRF/2026-09 #12'),
        );
    }

    public function test_normalize_reference_handles_null_as_empty_string(): void
    {
        $this->assertSame('', BankReconciliationSupport::normalizeReference(null));
    }

    public function test_suggest_counter_account_code_maps_bank_fee_descriptions(): void
    {
        $this->assertSame('6-8600', BankReconciliationSupport::suggestCounterAccountCode('Biaya Adm 14903'));
    }

    public function test_suggest_counter_account_code_maps_interest_descriptions(): void
    {
        $this->assertSame('4-9000', BankReconciliationSupport::suggestCounterAccountCode('Bunga 14903'));
    }

    public function test_suggest_counter_account_code_maps_tax_descriptions(): void
    {
        $this->assertSame('2-2200', BankReconciliationSupport::suggestCounterAccountCode('PPh 23 withholding'));
    }

    public function test_suggest_counter_account_code_returns_null_for_unrelated_description(): void
    {
        $this->assertNull(BankReconciliationSupport::suggestCounterAccountCode('Guest room payment'));
    }
}
