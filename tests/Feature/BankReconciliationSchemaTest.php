<?php

namespace Tests\Feature;

use App\Enums\BankAccountType;
use App\Models\BankAccount;
use App\Models\BankReconciliation;
use App\Models\BankReconciliationBookLine;
use App\Models\BankReconciliationLine;
use App\Models\BankReconciliationMatch;
use App\Models\BankReconciliationMatchLine;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankReconciliationSchemaTest extends TestCase
{
    use RefreshDatabase;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $hotel = Hotel::query()->create([
            'name' => 'Recon Test Hotel',
            'code' => 'RTH',
            'currency' => 'IDR',
            'timezone' => 'Asia/Makassar',
            'is_active' => true,
        ]);

        session(['current_hotel_id' => $hotel->id]);

        $bankCoa = ChartOfAccount::query()->create([
            'hotel_id' => $hotel->id,
            'account_code' => '1-1299',
            'name' => 'Test Bank',
            'account_type' => 'asset',
            'normal_balance' => 'debit',
            'is_postable' => true,
            'is_active' => true,
        ]);

        $this->bankAccount = BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '9990001111',
            'account_name' => 'Recon Account',
            'type' => BankAccountType::Bank->value,
            'chart_of_account_id' => $bankCoa->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);
    }

    public function test_reconciliation_relations_load(): void
    {
        $reconciliation = BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create();

        $line = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
        ]);

        $bookLine = BankReconciliationBookLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
        ]);

        $match = BankReconciliationMatch::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
        ]);

        $reconciliation->refresh();

        $this->assertTrue($reconciliation->lines->contains($line));
        $this->assertTrue($reconciliation->bookLines->contains($bookLine));
        $this->assertTrue($reconciliation->matches->contains($match));
        $this->assertSame($reconciliation->id, $line->bankReconciliation->id);
        $this->assertSame($reconciliation->id, $bookLine->reconciliation->id);
    }

    public function test_duplicate_match_line_for_same_statement_line_fails(): void
    {
        $reconciliation = BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create();

        $line = BankReconciliationLine::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
        ]);

        $matchOne = BankReconciliationMatch::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
        ]);

        $matchTwo = BankReconciliationMatch::factory()->create([
            'bank_reconciliation_id' => $reconciliation->id,
        ]);

        BankReconciliationMatchLine::query()->create([
            'bank_reconciliation_match_id' => $matchOne->id,
            'bank_reconciliation_line_id' => $line->id,
        ]);

        $this->expectException(QueryException::class);

        BankReconciliationMatchLine::query()->create([
            'bank_reconciliation_match_id' => $matchTwo->id,
            'bank_reconciliation_line_id' => $line->id,
        ]);
    }

    public function test_preparer_helpers_and_scope(): void
    {
        $preparer = User::factory()->create();
        $validator = User::factory()->create();
        $other = User::factory()->create();

        $reconciliation = BankReconciliation::factory()
            ->for($this->bankAccount)
            ->create([
                'created_by' => $preparer->id,
                'submitted_by' => $preparer->id,
                'validated_by' => $validator->id,
            ]);

        $this->assertTrue($reconciliation->isPreparer($preparer->id));
        $this->assertTrue($reconciliation->isPreparer($validator->id));
        $this->assertFalse($reconciliation->isPreparer($other->id));

        $visibleToOther = BankReconciliation::query()
            ->excludingPreparer($other->id)
            ->whereKey($reconciliation->id)
            ->exists();

        $this->assertTrue($visibleToOther);

        $visibleToPreparer = BankReconciliation::query()
            ->excludingPreparer($preparer->id)
            ->whereKey($reconciliation->id)
            ->exists();

        $this->assertFalse($visibleToPreparer);

        $visibleToValidator = BankReconciliation::query()
            ->excludingPreparer($validator->id)
            ->whereKey($reconciliation->id)
            ->exists();

        $this->assertFalse($visibleToValidator);
    }
}
