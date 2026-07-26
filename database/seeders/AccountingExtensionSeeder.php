<?php

namespace Database\Seeders;

use App\Enums\ArInvoiceStatus;
use App\Enums\AssetType;
use App\Enums\BudgetDepartment;
use App\Enums\BudgetStatus;
use App\Enums\DepreciationMethod;
use App\Enums\SupplierInvoiceStatus;
use App\Enums\TaxTransactionStatus;
use App\Enums\TaxType;
use App\Models\Asset;
use App\Models\BankAccount;
use App\Models\Budget;
use App\Models\BudgetLine;
use App\Models\ChartOfAccount;
use App\Models\Company;
use App\Models\Hotel;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierInvoiceLine;
use App\Models\TaxTransaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccountingExtensionSeeder extends Seeder
{
    public function run(): void
    {
        Hotel::query()->each(function (Hotel $hotel): void {
            $this->seedBankAccounts($hotel);
            $this->seedFixedAssets($hotel);
            $this->seedBudgets($hotel);
            $this->seedArInvoices($hotel);
            $this->seedSupplierInvoices($hotel);
        });
    }

    private function seedBankAccounts(Hotel $hotel): void
    {
        if (BankAccount::query()->withoutGlobalScope('hotel')->where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        $glAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('account_code', '1-1200')
            ->first();

        if ($glAccount === null) {
            return;
        }

        BankAccount::query()->create([
            'hotel_id' => $hotel->id,
            'bank_name' => 'BCA',
            'account_no' => '1234567890',
            'account_name' => "{$hotel->name} Operasional",
            'chart_of_account_id' => $glAccount->id,
            'currency_code' => 'IDR',
            'is_active' => true,
        ]);
    }

    private function seedFixedAssets(Hotel $hotel): void
    {
        $expenseAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('account_code', '6-8400')
            ->first();

        $accumAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('account_code', '1-2920')
            ->first();

        if ($expenseAccount === null || $accumAccount === null) {
            return;
        }

        $asset = Asset::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->whereNull('acquisition_cost')
            ->first();

        if ($asset === null) {
            $asset = Asset::query()->create([
                'hotel_id' => $hotel->id,
                'name' => 'HVAC Unit - Lobby',
                'asset_type' => AssetType::Hvac->value,
                'location' => 'Lobby',
                'status' => 'operational',
            ]);
        }

        if ($asset->acquisition_cost === null) {
            $asset->update([
                'asset_code' => 'FA-'.str_pad((string) $asset->id, 4, '0', STR_PAD_LEFT),
                'acquisition_date' => now()->subYears(2)->startOfMonth(),
                'acquisition_cost' => 150000000,
                'residual_value' => 15000000,
                'useful_life_years' => 10,
                'depreciation_method' => DepreciationMethod::StraightLine->value,
                'accumulated_depreciation' => 0,
                'net_book_value' => 150000000,
                'chart_of_account_id' => $expenseAccount->id,
                'accumulated_depreciation_account_id' => $accumAccount->id,
            ]);
        }
    }

    private function seedBudgets(Hotel $hotel): void
    {
        $user = User::query()->first();
        if ($user === null) {
            return;
        }

        $year = (int) now()->year;

        if (Budget::query()->withoutGlobalScope('hotel')->where('hotel_id', $hotel->id)->where('fiscal_year', $year)->exists()) {
            return;
        }

        $expenseAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('account_code', '6-2100')
            ->first();

        if ($expenseAccount === null) {
            return;
        }

        $budget = Budget::query()->create([
            'hotel_id' => $hotel->id,
            'department' => BudgetDepartment::Rooms->value,
            'fiscal_year' => $year,
            'status' => BudgetStatus::Approved->value,
            'created_by' => $user->id,
        ]);

        for ($month = 1; $month <= 12; $month++) {
            BudgetLine::query()->create([
                'budget_id' => $budget->id,
                'chart_of_account_id' => $expenseAccount->id,
                'month' => $month,
                'budgeted_amount' => 5000000,
            ]);
        }
    }

    private function seedArInvoices(Hotel $hotel): void
    {
        if (ArInvoice::query()->withoutGlobalScope('hotel')->where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        $company = Company::query()->where('is_active', true)->first();
        if ($company === null) {
            return;
        }

        $invoiceNo = 'AR-INV-'.now()->format('Ymd').'-0001';

        ArInvoice::query()->create([
            'hotel_id' => $hotel->id,
            'invoice_no' => $invoiceNo,
            'company_id' => $company->id,
            'period_start' => now()->startOfMonth()->toDateString(),
            'period_end' => now()->endOfMonth()->toDateString(),
            'total_amount' => 25000000,
            'paid_amount' => 0,
            'status' => ArInvoiceStatus::Open->value,
            'due_date' => now()->addDays(30)->toDateString(),
            'issued_at' => now(),
        ]);
    }

    private function seedSupplierInvoices(Hotel $hotel): void
    {
        if (SupplierInvoice::query()->withoutGlobalScope('hotel')->where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        $supplier = Supplier::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('is_active', true)
            ->first();

        $expenseAccount = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->where('account_code', '6-4300')
            ->first();

        if ($supplier === null || $expenseAccount === null) {
            return;
        }

        DB::transaction(function () use ($hotel, $supplier, $expenseAccount): void {
            $subtotal = 5000000;
            $tax = round($subtotal * 0.11, 2);

            $invoice = SupplierInvoice::query()->create([
                'hotel_id' => $hotel->id,
                'invoice_no' => 'SUP-INV-'.now()->format('Ym').'-0001',
                'supplier_id' => $supplier->id,
                'invoice_date' => now()->toDateString(),
                'due_date' => now()->addDays(30)->toDateString(),
                'subtotal' => $subtotal,
                'tax_amount' => $tax,
                'withholding_tax_amount' => 0,
                'total_amount' => $subtotal + $tax,
                'status' => SupplierInvoiceStatus::Approved->value,
            ]);

            SupplierInvoiceLine::query()->create([
                'supplier_invoice_id' => $invoice->id,
                'chart_of_account_id' => $expenseAccount->id,
                'description' => 'Cleaning supplies',
                'quantity' => 1,
                'unit_cost' => $subtotal,
                'amount' => $subtotal,
            ]);

            TaxTransaction::query()->create([
                'hotel_id' => $hotel->id,
                'tax_type' => TaxType::PpnInput->value,
                'source_type' => 'supplier_invoice',
                'source_id' => $invoice->id,
                'transaction_date' => $invoice->invoice_date->toDateString(),
                'base_amount' => $subtotal,
                'tax_rate_percent' => 11,
                'tax_amount' => $tax,
                'tax_period' => now()->format('Y-m'),
                'status' => TaxTransactionStatus::Unreported->value,
            ]);
        });
    }
}
