<?php

namespace Database\Seeders;

use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\ChartOfAccount;
use App\Models\Hotel;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedGroupAccounts();

        Hotel::query()->each(fn (Hotel $hotel) => $this->forHotel($hotel));

        Hotel::query()->each(fn (Hotel $hotel) => $this->syncAdditionalRevenueAccounts($hotel));
    }

    public function forHotel(Hotel $hotel): void
    {
        if (ChartOfAccount::query()->withoutGlobalScope('hotel')->where('hotel_id', $hotel->id)->exists()) {
            return;
        }

        $accounts = $this->hotelAccounts();
        $codeToId = [];

        foreach ($accounts as $account) {
            $parentCode = $account['parent_code'] ?? null;
            unset($account['parent_code']);

            $created = ChartOfAccount::query()->create([
                'hotel_id' => $hotel->id,
                'parent_id' => $parentCode !== null ? ($codeToId[$parentCode] ?? null) : null,
                ...$account,
            ]);

            $codeToId[$account['account_code']] = $created->id;
        }
    }

    private function seedGroupAccounts(): void
    {
        if (ChartOfAccount::query()->withoutGlobalScope('hotel')->whereNull('hotel_id')->exists()) {
            return;
        }

        $groupAccounts = [
            ['account_code' => '3-1400', 'name' => 'Intercompany Clearing', 'account_type' => AccountType::Equity, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true],
            ['account_code' => '4-9200', 'name' => 'Selisih Kurs Laba', 'account_type' => AccountType::Revenue, 'normal_balance' => NormalBalance::Credit, 'is_postable' => true],
            ['account_code' => '6-9100', 'name' => 'Selisih Kurs Rugi', 'account_type' => AccountType::Expense, 'normal_balance' => NormalBalance::Debit, 'is_postable' => true],
        ];

        foreach ($groupAccounts as $account) {
            ChartOfAccount::query()->create([
                'hotel_id' => null,
                'parent_id' => null,
                'is_active' => true,
                ...$account,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function hotelAccounts(): array
    {
        return [
            $this->header('1-0000', 'ASET', AccountType::Asset, NormalBalance::Debit),
            $this->header('1-1000', 'Kas & Setara Kas', AccountType::Asset, NormalBalance::Debit, '1-0000'),
            $this->postable('1-1100', 'Kas', AccountType::Asset, NormalBalance::Debit, '1-1000'),
            $this->postable('1-1200', 'Bank Operasional', AccountType::Asset, NormalBalance::Debit, '1-1000'),
            $this->postable('1-1210', 'Bank Payroll', AccountType::Asset, NormalBalance::Debit, '1-1000'),
            $this->postable('1-1300', 'Piutang Tamu (Guest Ledger)', AccountType::Asset, NormalBalance::Debit, '1-0000'),
            $this->postable('1-1400', 'Piutang City Ledger', AccountType::Asset, NormalBalance::Debit, '1-0000'),
            $this->postable('1-1500', 'Persediaan', AccountType::Asset, NormalBalance::Debit, '1-0000'),
            $this->postable('1-1600', 'Biaya Dibayar Dimuka', AccountType::Asset, NormalBalance::Debit, '1-0000'),
            $this->postable('1-1700', 'PPN Masukan', AccountType::Asset, NormalBalance::Debit, '1-0000'),
            $this->postable('1-1800', 'Uang Muka Pembelian', AccountType::Asset, NormalBalance::Debit, '1-0000'),

            $this->header('1-2000', 'Aset Tetap', AccountType::Asset, NormalBalance::Debit, '1-0000'),
            $this->postable('1-2100', 'Tanah', AccountType::Asset, NormalBalance::Debit, '1-2000'),
            $this->postable('1-2200', 'Bangunan', AccountType::Asset, NormalBalance::Debit, '1-2000'),
            $this->postable('1-2300', 'Peralatan & Furnitur', AccountType::Asset, NormalBalance::Debit, '1-2000'),
            $this->postable('1-2400', 'Kendaraan', AccountType::Asset, NormalBalance::Debit, '1-2000'),
            $this->postable('1-2500', 'Peralatan IT', AccountType::Asset, NormalBalance::Debit, '1-2000'),
            $this->postable('1-2900', 'Akumulasi Penyusutan', AccountType::Asset, NormalBalance::Credit, '1-2000'),
            $this->postable('1-2910', 'Akum Penyusutan Bangunan', AccountType::Asset, NormalBalance::Credit, '1-2000'),
            $this->postable('1-2920', 'Akum Penyusutan Peralatan', AccountType::Asset, NormalBalance::Credit, '1-2000'),

            $this->header('2-0000', 'KEWAJIBAN', AccountType::Liability, NormalBalance::Credit),
            $this->postable('2-1100', 'Hutang Usaha', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-1400', 'Utang Komisi Agen', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-1410', 'Utang Fee OTA', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-1500', 'Hutang Pajak Lainnya', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-1600', 'Beban Terutang', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-2100', 'PPN Keluaran', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-2200', 'PPh 23 Terutang', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-2300', 'Gaji Terutang', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-2400', 'Pendapatan Diterima Dimuka', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('2-2500', 'Deposit Tamu', AccountType::Liability, NormalBalance::Credit, '2-0000'),

            $this->header('3-0000', 'EKUITAS', AccountType::Equity, NormalBalance::Credit),
            $this->postable('3-1100', 'Modal Disetor', AccountType::Equity, NormalBalance::Credit, '3-0000'),
            $this->postable('3-1200', 'Laba Ditahan', AccountType::Equity, NormalBalance::Credit, '3-0000'),
            $this->postable('3-1300', 'Laba Tahun Berjalan', AccountType::Equity, NormalBalance::Credit, '3-0000'),

            $this->header('4-0000', 'PENDAPATAN', AccountType::Revenue, NormalBalance::Credit),
            $this->header('4-1000', 'Pendapatan Kamar', AccountType::Revenue, NormalBalance::Credit, '4-0000'),
            $this->postable('4-1100', 'Room Revenue - Standard', AccountType::Revenue, NormalBalance::Credit, '4-1000'),
            $this->postable('4-1200', 'Room Revenue - Deluxe', AccountType::Revenue, NormalBalance::Credit, '4-1000'),
            $this->postable('4-1300', 'Room Revenue - Suite', AccountType::Revenue, NormalBalance::Credit, '4-1000'),
            $this->postable('4-1400', 'Room Revenue - Grand Deluxe', AccountType::Revenue, NormalBalance::Credit, '4-1000'),
            $this->postable('4-1500', 'Service Charge Revenue', AccountType::Revenue, NormalBalance::Credit, '4-0000'),
            $this->postable('4-1600', 'Laundry Revenue', AccountType::Revenue, NormalBalance::Credit, '4-0000'),
            $this->postable('4-1700', 'Telephone Revenue', AccountType::Revenue, NormalBalance::Credit, '4-0000'),

            $this->header('4-2000', 'Pendapatan F&B', AccountType::Revenue, NormalBalance::Credit, '4-0000'),
            $this->postable('4-2100', 'Restaurant Revenue', AccountType::Revenue, NormalBalance::Credit, '4-2000'),
            $this->postable('4-2200', 'Banquet Revenue', AccountType::Revenue, NormalBalance::Credit, '4-2000'),
            $this->postable('4-2300', 'Room Service Revenue', AccountType::Revenue, NormalBalance::Credit, '4-2000'),
            $this->postable('4-2400', 'Bar Revenue', AccountType::Revenue, NormalBalance::Credit, '4-2000'),
            $this->postable('4-2500', 'Prata Coffee Revenue', AccountType::Revenue, NormalBalance::Credit, '4-2000'),

            $this->header('4-3000', 'Pendapatan Spa', AccountType::Revenue, NormalBalance::Credit, '4-0000'),
            $this->postable('4-3100', 'Spa Treatment Revenue', AccountType::Revenue, NormalBalance::Credit, '4-3000'),
            $this->postable('4-3200', 'Spa Product Sales', AccountType::Revenue, NormalBalance::Credit, '4-3000'),

            $this->header('4-4000', 'Departemen Lain', AccountType::Revenue, NormalBalance::Credit, '4-0000'),
            $this->postable('4-4100', 'Golf Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4200', 'Banquet Service Charge', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4300', 'Dive Center Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4310', 'Boat Charter Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4400', 'Merchandise Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4500', 'Showcase Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4600', 'Tiket Pantai Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4700', 'Transport Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4800', 'Meeting Package Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-9000', 'Pendapatan Lain-lain', AccountType::Revenue, NormalBalance::Credit, '4-0000'),

            $this->header('5-0000', 'HARGA POKOK PENJUALAN', AccountType::Cogs, NormalBalance::Debit),
            $this->postable('5-1100', 'HPP F&B', AccountType::Cogs, NormalBalance::Debit, '5-0000'),
            $this->postable('5-1200', 'HPP Spa Products', AccountType::Cogs, NormalBalance::Debit, '5-0000'),
            $this->postable('5-1300', 'Cost of Amenities', AccountType::Cogs, NormalBalance::Debit, '5-0000'),
            $this->postable('5-1400', 'Linen & Laundry COGS', AccountType::Cogs, NormalBalance::Debit, '5-0000'),

            $this->header('6-0000', 'BEBAN OPERASIONAL', AccountType::Expense, NormalBalance::Debit),
            $this->header('6-1000', 'Gaji & Tunjangan', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-1100', 'Gaji Karyawan', AccountType::Expense, NormalBalance::Debit, '6-1000'),
            $this->postable('6-1200', 'Tunjangan Karyawan', AccountType::Expense, NormalBalance::Debit, '6-1000'),
            $this->postable('6-1300', 'BPJS Kesehatan & Ketenagakerjaan', AccountType::Expense, NormalBalance::Debit, '6-1000'),
            $this->postable('6-1400', 'Bonus & Insentif', AccountType::Expense, NormalBalance::Debit, '6-1000'),

            $this->header('6-2000', 'Utilitas', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-2100', 'Listrik', AccountType::Expense, NormalBalance::Debit, '6-2000'),
            $this->postable('6-2200', 'Air', AccountType::Expense, NormalBalance::Debit, '6-2000'),
            $this->postable('6-2300', 'Gas', AccountType::Expense, NormalBalance::Debit, '6-2000'),
            $this->postable('6-2400', 'Internet & Telekomunikasi', AccountType::Expense, NormalBalance::Debit, '6-2000'),

            $this->header('6-3000', 'Pemasaran', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-3100', 'Advertising', AccountType::Expense, NormalBalance::Debit, '6-3000'),
            $this->postable('6-3200', 'Sales Commission', AccountType::Expense, NormalBalance::Debit, '6-3000'),
            $this->postable('6-3300', 'Travel Agent Commission', AccountType::Expense, NormalBalance::Debit, '6-3000'),
            $this->postable('6-3400', 'OTA Booking Fee', AccountType::Expense, NormalBalance::Debit, '6-3000'),

            $this->postable('6-4000', 'Housekeeping Expense', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-4100', 'Guest Supplies', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-4200', 'Linen & Laundry Expense', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-4300', 'Cleaning Supplies', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-5000', 'F&B Operating Expense', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-6000', 'Spa Operating Expense', AccountType::Expense, NormalBalance::Debit, '6-0000'),

            $this->header('6-7000', 'Pemeliharaan', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-7100', 'Contract Services', AccountType::Expense, NormalBalance::Debit, '6-7000'),
            $this->postable('6-7200', 'Equipment Rental', AccountType::Expense, NormalBalance::Debit, '6-7000'),
            $this->postable('6-7300', 'Repairs & Maintenance', AccountType::Expense, NormalBalance::Debit, '6-7000'),

            $this->header('6-8000', 'Administrasi & Umum', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-8100', 'Office Supplies', AccountType::Expense, NormalBalance::Debit, '6-8000'),
            $this->postable('6-8200', 'Professional Fees', AccountType::Expense, NormalBalance::Debit, '6-8000'),
            $this->postable('6-8300', 'Asuransi', AccountType::Expense, NormalBalance::Debit, '6-8000'),
            $this->postable('6-8400', 'Penyusutan', AccountType::Expense, NormalBalance::Debit, '6-8000'),
            $this->postable('6-8500', 'Penyesuaian Persediaan', AccountType::Expense, NormalBalance::Debit, '6-8000'),
            $this->postable('6-8600', 'Bank Charges', AccountType::Expense, NormalBalance::Debit, '6-8000'),

            $this->postable('6-6100', 'Training & Development', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-6200', 'Employee Meals', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-9000', 'Pajak & Perizinan', AccountType::Expense, NormalBalance::Debit, '6-0000'),
            $this->postable('6-9100', 'PBB & Retribusi', AccountType::Expense, NormalBalance::Debit, '6-0000'),
        ];
    }

    private function syncAdditionalRevenueAccounts(Hotel $hotel): void
    {
        $additionalAccounts = [
            $this->postable('2-1410', 'Utang Fee OTA', AccountType::Liability, NormalBalance::Credit, '2-0000'),
            $this->postable('6-3400', 'OTA Booking Fee', AccountType::Expense, NormalBalance::Debit, '6-3000'),
            $this->postable('4-1400', 'Room Revenue - Grand Deluxe', AccountType::Revenue, NormalBalance::Credit, '4-1000'),
            $this->postable('4-2500', 'Prata Coffee Revenue', AccountType::Revenue, NormalBalance::Credit, '4-2000'),
            $this->postable('4-4300', 'Dive Center Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4310', 'Boat Charter Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4400', 'Merchandise Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4500', 'Showcase Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4600', 'Tiket Pantai Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4700', 'Transport Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
            $this->postable('4-4800', 'Meeting Package Revenue', AccountType::Revenue, NormalBalance::Credit, '4-4000'),
        ];

        $codeToId = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotel->id)
            ->pluck('id', 'account_code')
            ->all();

        foreach ($additionalAccounts as $account) {
            $parentCode = $account['parent_code'] ?? null;
            unset($account['parent_code']);

            $existing = ChartOfAccount::query()
                ->withoutGlobalScope('hotel')
                ->where('hotel_id', $hotel->id)
                ->where('account_code', $account['account_code'])
                ->first();

            if ($existing !== null) {
                continue;
            }

            $created = ChartOfAccount::query()->create([
                'hotel_id' => $hotel->id,
                'parent_id' => $parentCode !== null ? ($codeToId[$parentCode] ?? null) : null,
                ...$account,
            ]);

            $codeToId[$account['account_code']] = $created->id;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function header(
        string $code,
        string $name,
        AccountType $type,
        NormalBalance $balance,
        ?string $parentCode = null,
    ): array {
        return [
            'parent_id' => null,
            'parent_code' => $parentCode,
            'account_code' => $code,
            'name' => $name,
            'account_type' => $type,
            'normal_balance' => $balance,
            'is_postable' => false,
            'is_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function postable(
        string $code,
        string $name,
        AccountType $type,
        NormalBalance $balance,
        ?string $parentCode = null,
    ): array {
        return [
            'parent_id' => null,
            'parent_code' => $parentCode,
            'account_code' => $code,
            'name' => $name,
            'account_type' => $type,
            'normal_balance' => $balance,
            'is_postable' => true,
            'is_active' => true,
        ];
    }
}
