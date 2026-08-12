<?php

namespace App\Services\Accounting;

use App\Enums\AccountingPeriodStatus;
use App\Enums\AccountType;
use App\Enums\NormalBalance;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedger;
use App\Models\Hotel;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class GlPostingService
{
    public function __construct(
        private AccountingPeriodService $accountingPeriodService,
    ) {}

    /**
     * @param  array<int, array{
     *     hotel_id: int,
     *     chart_of_account_id: int,
     *     transaction_date: string|CarbonInterface,
     *     debit: float|int|string,
     *     credit: float|int|string,
     *     description: string,
     *     reference_number?: string|null,
     *     source_type: string,
     *     source_id: int
     * }>  $lines
     * @return Collection<int, GeneralLedger>
     */
    public function post(array $lines): Collection
    {
        if ($lines === []) {
            throw new InvalidArgumentException('GL posting requires at least one line.');
        }

        $sourceType = $lines[0]['source_type'];
        $sourceId = $lines[0]['source_id'];

        foreach ($lines as $line) {
            if ($line['source_type'] !== $sourceType || $line['source_id'] !== $sourceId) {
                throw new InvalidArgumentException('All GL lines in a batch must share the same source.');
            }
        }

        if ($this->isAlreadyPosted($sourceType, $sourceId)) {
            return GeneralLedger::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->get();
        }

        $totalDebit = round(collect($lines)->sum(fn (array $line): float => (float) $line['debit']), 2);
        $totalCredit = round(collect($lines)->sum(fn (array $line): float => (float) $line['credit']), 2);

        if ($totalDebit !== $totalCredit) {
            throw new InvalidArgumentException("GL entry is unbalanced: debit {$totalDebit} != credit {$totalCredit}.");
        }

        if ($totalDebit <= 0) {
            throw new InvalidArgumentException('GL entry total must be greater than zero.');
        }

        return DB::transaction(function () use ($lines): Collection {
            $posted = collect();

            foreach ($lines as $line) {
                $transactionDate = Carbon::parse($line['transaction_date']);
                $period = $this->resolveOpenPeriod((int) $line['hotel_id'], $transactionDate);

                $account = ChartOfAccount::query()
                    ->withoutGlobalScope('hotel')
                    ->where('id', $line['chart_of_account_id'])
                    ->where('is_active', true)
                    ->firstOrFail();

                if (! $account->is_postable) {
                    throw new InvalidArgumentException("Account {$account->account_code} is not postable.");
                }

                $posted->push(GeneralLedger::query()->create([
                    'hotel_id' => $line['hotel_id'],
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'accounting_period_id' => $period->id,
                    'transaction_date' => $transactionDate->toDateString(),
                    'debit' => round((float) $line['debit'], 2),
                    'credit' => round((float) $line['credit'], 2),
                    'description' => $line['description'],
                    'reference_number' => $line['reference_number'] ?? null,
                    'source_type' => $line['source_type'],
                    'source_id' => $line['source_id'],
                ]));
            }

            return $posted;
        });
    }

    public function getBalance(ChartOfAccount $account, ?Carbon $asOfDate = null, ?int $hotelId = null): float
    {
        $hotelId ??= session('current_hotel_id');

        $query = GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('chart_of_account_id', $account->id);

        if ($hotelId !== null) {
            $query->where('hotel_id', $hotelId);
        }

        if ($asOfDate !== null) {
            $query->where('transaction_date', '<=', $asOfDate->toDateString());
        }

        $totals = $query
            ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
            ->first();

        $debit = (float) ($totals->total_debit ?? 0);
        $credit = (float) ($totals->total_credit ?? 0);

        return round($account->normal_balance === NormalBalance::Debit
            ? $debit - $credit
            : $credit - $debit, 2);
    }

    /**
     * @return Collection<int, array{
     *     account_id: int,
     *     account_code: string,
     *     account_name: string,
     *     account_type: string,
     *     debit: float,
     *     credit: float
     * }>
     */
    public function getTrialBalance(int $hotelId, Carbon $asOfDate): Collection
    {
        $accounts = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where(function ($query) use ($hotelId): void {
                $query->where('hotel_id', $hotelId)
                    ->orWhereNull('hotel_id');
            })
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return $accounts->map(function (ChartOfAccount $account) use ($hotelId, $asOfDate): array {
            $totals = GeneralLedger::query()
                ->withoutGlobalScope('hotel')
                ->where('hotel_id', $hotelId)
                ->where('chart_of_account_id', $account->id)
                ->where('transaction_date', '<=', $asOfDate->toDateString())
                ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                ->first();

            $debit = (float) ($totals->total_debit ?? 0);
            $credit = (float) ($totals->total_credit ?? 0);

            if ($account->normal_balance === NormalBalance::Debit) {
                $net = $debit - $credit;
                $trialDebit = $net >= 0 ? $net : 0.0;
                $trialCredit = $net < 0 ? abs($net) : 0.0;
            } else {
                $net = $credit - $debit;
                $trialCredit = $net >= 0 ? $net : 0.0;
                $trialDebit = $net < 0 ? abs($net) : 0.0;
            }

            return [
                'account_id' => $account->id,
                'account_code' => $account->account_code,
                'account_name' => $account->name,
                'account_type' => $account->account_type->value,
                'debit' => round($trialDebit, 2),
                'credit' => round($trialCredit, 2),
            ];
        })->filter(fn (array $row): bool => $row['debit'] > 0 || $row['credit'] > 0)->values();
    }

    /**
     * @return array{
     *     revenue: Collection<int, array{account_code: string, account_name: string, amount: float}>,
     *     cogs: Collection<int, array{account_code: string, account_name: string, amount: float}>,
     *     expenses: Collection<int, array{account_code: string, account_name: string, amount: float}>,
     *     total_revenue: float,
     *     total_cogs: float,
     *     total_expenses: float,
     *     gross_profit: float,
     *     net_income: float
     * }
     */
    public function getIncomeStatement(int $hotelId, Carbon $startDate, Carbon $endDate): array
    {
        $accounts = $this->getPeriodActivityByType($hotelId, $startDate, $endDate, [
            AccountType::Revenue,
            AccountType::Cogs,
            AccountType::Expense,
        ]);

        $revenue = $this->formatStatementLines($accounts->get(AccountType::Revenue->value, collect()));
        $cogs = $this->formatStatementLines($accounts->get(AccountType::Cogs->value, collect()));
        $expenses = $this->formatStatementLines($accounts->get(AccountType::Expense->value, collect()));

        $totalRevenue = round($revenue->sum('amount'), 2);
        $totalCogs = round($cogs->sum('amount'), 2);
        $totalExpenses = round($expenses->sum('amount'), 2);
        $grossProfit = round($totalRevenue - $totalCogs, 2);
        $netIncome = round($grossProfit - $totalExpenses, 2);

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'expenses' => $expenses,
            'total_revenue' => $totalRevenue,
            'total_cogs' => $totalCogs,
            'total_expenses' => $totalExpenses,
            'gross_profit' => $grossProfit,
            'net_income' => $netIncome,
        ];
    }

    /**
     * @return array{
     *     assets: Collection<int, array{account_code: string, account_name: string, amount: float}>,
     *     liabilities: Collection<int, array{account_code: string, account_name: string, amount: float}>,
     *     equity: Collection<int, array{account_code: string, account_name: string, amount: float}>,
     *     total_assets: float,
     *     total_liabilities: float,
     *     total_equity: float,
     *     total_liabilities_and_equity: float
     * }
     */
    public function getBalanceSheet(int $hotelId, Carbon $asOfDate): array
    {
        $accounts = $this->getBalancesByType($hotelId, $asOfDate, [
            AccountType::Asset,
            AccountType::Liability,
            AccountType::Equity,
        ]);

        $assets = $this->formatStatementLines($accounts->get(AccountType::Asset->value, collect()));
        $liabilities = $this->formatStatementLines($accounts->get(AccountType::Liability->value, collect()));
        $equity = $this->formatStatementLines($accounts->get(AccountType::Equity->value, collect()));

        $totalAssets = round($assets->sum('amount'), 2);
        $totalLiabilities = round($liabilities->sum('amount'), 2);
        $totalEquity = round($equity->sum('amount'), 2);

        return [
            'assets' => $assets,
            'liabilities' => $liabilities,
            'equity' => $equity,
            'total_assets' => $totalAssets,
            'total_liabilities' => $totalLiabilities,
            'total_equity' => $totalEquity,
            'total_liabilities_and_equity' => round($totalLiabilities + $totalEquity, 2),
        ];
    }

    public function findAccountByCode(int $hotelId, string $accountCode): ChartOfAccount
    {
        $account = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where('account_code', $accountCode)
            ->where(function ($query) use ($hotelId): void {
                $query->where('hotel_id', $hotelId)
                    ->orWhereNull('hotel_id');
            })
            ->where('is_active', true)
            ->orderByRaw('hotel_id IS NULL')
            ->first();

        if ($account === null) {
            throw new InvalidArgumentException("Chart of account {$accountCode} not found for hotel {$hotelId}.");
        }

        return $account;
    }

    private function isAlreadyPosted(string $sourceType, int $sourceId): bool
    {
        return GeneralLedger::query()
            ->withoutGlobalScope('hotel')
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->exists();
    }

    private function resolveOpenPeriod(int $hotelId, Carbon $transactionDate): AccountingPeriod
    {
        $period = AccountingPeriod::query()
            ->withoutGlobalScope('hotel')
            ->where('hotel_id', $hotelId)
            ->where('start_date', '<=', $transactionDate->toDateString())
            ->where('end_date', '>=', $transactionDate->toDateString())
            ->first();

        if ($period === null) {
            $hotel = Hotel::query()->withoutGlobalScope('hotel')->find($hotelId);

            if ($hotel !== null) {
                $period = $this->accountingPeriodService->ensurePeriodForDate($hotel, $transactionDate);
            } else {
                throw new InvalidArgumentException("No accounting period found for date {$transactionDate->toDateString()}.");
            }
        }

        if ($period->status === AccountingPeriodStatus::Closed) {
            throw new InvalidArgumentException("Accounting period {$period->name} is closed.");
        }

        return $period;
    }

    /**
     * @param  array<int, AccountType>  $types
     */
    private function getBalancesByType(int $hotelId, Carbon $asOfDate, array $types): Collection
    {
        $typeValues = array_map(fn (AccountType $type): string => $type->value, $types);

        $accounts = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where(function ($query) use ($hotelId): void {
                $query->where('hotel_id', $hotelId)
                    ->orWhereNull('hotel_id');
            })
            ->whereIn('account_type', $typeValues)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return $accounts->groupBy(fn (ChartOfAccount $account): string => $account->account_type->value)
            ->map(function (Collection $group) use ($hotelId, $asOfDate): Collection {
                return $group->map(function (ChartOfAccount $account) use ($hotelId, $asOfDate): array {
                    return [
                        'account_code' => $account->account_code,
                        'account_name' => $account->name,
                        'amount' => $this->getBalance($account, $asOfDate, $hotelId),
                    ];
                })->filter(fn (array $row): bool => abs($row['amount']) >= 0.01)->values();
            });
    }

    /**
     * @param  array<int, AccountType>  $types
     */
    private function getPeriodActivityByType(int $hotelId, Carbon $startDate, Carbon $endDate, array $types): Collection
    {
        $typeValues = array_map(fn (AccountType $type): string => $type->value, $types);

        $accounts = ChartOfAccount::query()
            ->withoutGlobalScope('hotel')
            ->where(function ($query) use ($hotelId): void {
                $query->where('hotel_id', $hotelId)
                    ->orWhereNull('hotel_id');
            })
            ->whereIn('account_type', $typeValues)
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get();

        return $accounts->groupBy(fn (ChartOfAccount $account): string => $account->account_type->value)
            ->map(function (Collection $group) use ($hotelId, $startDate, $endDate): Collection {
                return $group->map(function (ChartOfAccount $account) use ($hotelId, $startDate, $endDate): array {
                    $totals = GeneralLedger::query()
                        ->withoutGlobalScope('hotel')
                        ->where('hotel_id', $hotelId)
                        ->where('chart_of_account_id', $account->id)
                        ->whereBetween('transaction_date', [$startDate->toDateString(), $endDate->toDateString()])
                        ->selectRaw('COALESCE(SUM(debit), 0) as total_debit, COALESCE(SUM(credit), 0) as total_credit')
                        ->first();

                    $debit = (float) ($totals->total_debit ?? 0);
                    $credit = (float) ($totals->total_credit ?? 0);

                    $amount = $account->normal_balance === NormalBalance::Credit
                        ? $credit - $debit
                        : $debit - $credit;

                    return [
                        'account_code' => $account->account_code,
                        'account_name' => $account->name,
                        'amount' => round($amount, 2),
                    ];
                })->filter(fn (array $row): bool => abs($row['amount']) >= 0.01)->values();
            });
    }

    /**
     * @param  Collection<int, array{account_code: string, account_name: string, amount: float}>  $lines
     * @return Collection<int, array{account_code: string, account_name: string, amount: float}>
     */
    private function formatStatementLines(Collection $lines): Collection
    {
        return $lines->map(fn (array $line): array => [
            'account_code' => $line['account_code'],
            'account_name' => $line['account_name'],
            'amount' => round(abs($line['amount']), 2),
        ])->values();
    }
}
