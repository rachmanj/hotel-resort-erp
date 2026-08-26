<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\BankAccountType;
use App\Enums\PettyCashDirection;
use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\GeneralLedger;
use App\Models\PettyCashTransaction;
use App\Services\Accounting\FundTransferService;
use App\Services\Accounting\GlPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class PettyCashController extends Controller
{
    public function __construct(
        private GlPostingService $glPostingService,
        private FundTransferService $fundTransferService,
    ) {}

    public function index(Request $request): Response
    {
        $hotelId = (int) session('current_hotel_id');

        $pettyCashAccounts = BankAccount::query()
            ->with('chartOfAccount:id,account_code,name')
            ->where('type', BankAccountType::PettyCash->value)
            ->where('is_active', true)
            ->orderBy('account_name')
            ->get()
            ->map(function (BankAccount $account) use ($hotelId): array {
                $coa = $account->chartOfAccount;
                $balance = $coa !== null
                    ? $this->glPostingService->getBalance($coa, null, $hotelId)
                    : 0.0;

                $recentActivity = GeneralLedger::query()
                    ->with('chartOfAccount:id,account_code,name', 'department:id,name')
                    ->where('chart_of_account_id', $account->chart_of_account_id)
                    ->orderByDesc('transaction_date')
                    ->orderByDesc('id')
                    ->limit(10)
                    ->get()
                    ->map(fn (GeneralLedger $row) => [
                        'id' => $row->id,
                        'transaction_date' => $row->transaction_date->toDateString(),
                        'description' => $row->description,
                        'reference_number' => $row->reference_number,
                        'debit' => (float) $row->debit,
                        'credit' => (float) $row->credit,
                        'source_type' => $row->source_type,
                        'account_code' => $row->chartOfAccount?->account_code,
                        'account_name' => $row->chartOfAccount?->name,
                        'department_name' => $row->department?->name,
                    ]);

                return [
                    'id' => $account->id,
                    'account_name' => $account->account_name,
                    'bank_name' => $account->bank_name,
                    'account_no' => $account->account_no,
                    'gl_account' => $coa ? "{$coa->account_code} - {$coa->name}" : null,
                    'balance' => $balance,
                    'recent_activity' => $recentActivity,
                ];
            });

        $bankAccounts = BankAccount::query()
            ->where('type', BankAccountType::Bank->value)
            ->where('is_active', true)
            ->orderBy('bank_name')
            ->get(['id', 'bank_name', 'account_name', 'chart_of_account_id']);

        return Inertia::render('Accounting/PettyCash/Index', [
            'pettyCashAccounts' => $pettyCashAccounts,
            'bankAccounts' => $bankAccounts,
            'expenseAccounts' => $this->counterpartAccounts(['expense', 'cogs']),
            'incomeAccounts' => $this->counterpartAccounts(['revenue']),
            'departments' => $this->departmentOptions(),
            'directionOptions' => collect(PettyCashDirection::cases())->map(fn (PettyCashDirection $d) => [
                'value' => $d->value,
                'label' => $d->label(),
            ]),
        ]);
    }

    public function storeCash(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'bank_account_id' => [
                'required',
                Rule::exists('bank_accounts', 'id')->where('type', BankAccountType::PettyCash->value),
            ],
            'direction' => ['required', Rule::enum(PettyCashDirection::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transaction_date' => ['required', 'date'],
            'department_id' => ['nullable', 'exists:departments,id'],
            'description' => ['required', 'string', 'max:255'],
            'chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
        ]);

        $hotelId = (int) session('current_hotel_id');
        $pettyCashAccount = BankAccount::query()->findOrFail($validated['bank_account_id']);
        $direction = PettyCashDirection::from($validated['direction']);
        $amount = round((float) $validated['amount'], 2);
        $pcCoaId = (int) $pettyCashAccount->chart_of_account_id;
        $counterpartCoaId = (int) $validated['chart_of_account_id'];

        DB::transaction(function () use ($validated, $hotelId, $direction, $amount, $pcCoaId, $counterpartCoaId, $request): void {
            $transaction = PettyCashTransaction::query()->create([
                'hotel_id' => $hotelId,
                'bank_account_id' => $validated['bank_account_id'],
                'direction' => $direction->value,
                'amount' => $amount,
                'transaction_date' => $validated['transaction_date'],
                'department_id' => $validated['department_id'] ?? null,
                'chart_of_account_id' => $counterpartCoaId,
                'description' => $validated['description'],
                'reference_no' => $this->generateReferenceNumber($direction),
                'created_by' => $request->user()->id,
            ]);

            $departmentId = $validated['department_id'] ?? null;

            if ($direction === PettyCashDirection::Out) {
                $lines = [
                    [
                        'hotel_id' => $hotelId,
                        'chart_of_account_id' => $counterpartCoaId,
                        'department_id' => $departmentId,
                        'transaction_date' => $validated['transaction_date'],
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => $validated['description'],
                        'reference_number' => $transaction->reference_no,
                        'source_type' => 'petty_cash_transaction',
                        'source_id' => $transaction->id,
                    ],
                    [
                        'hotel_id' => $hotelId,
                        'chart_of_account_id' => $pcCoaId,
                        'department_id' => null,
                        'transaction_date' => $validated['transaction_date'],
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => $validated['description'],
                        'reference_number' => $transaction->reference_no,
                        'source_type' => 'petty_cash_transaction',
                        'source_id' => $transaction->id,
                    ],
                ];
            } else {
                $lines = [
                    [
                        'hotel_id' => $hotelId,
                        'chart_of_account_id' => $pcCoaId,
                        'department_id' => null,
                        'transaction_date' => $validated['transaction_date'],
                        'debit' => $amount,
                        'credit' => 0,
                        'description' => $validated['description'],
                        'reference_number' => $transaction->reference_no,
                        'source_type' => 'petty_cash_transaction',
                        'source_id' => $transaction->id,
                    ],
                    [
                        'hotel_id' => $hotelId,
                        'chart_of_account_id' => $counterpartCoaId,
                        'department_id' => $departmentId,
                        'transaction_date' => $validated['transaction_date'],
                        'debit' => 0,
                        'credit' => $amount,
                        'description' => $validated['description'],
                        'reference_number' => $transaction->reference_no,
                        'source_type' => 'petty_cash_transaction',
                        'source_id' => $transaction->id,
                    ],
                ];
            }

            $this->glPostingService->post($lines);
        });

        return back()->with('success', 'Petty cash transaction recorded.');
    }

    public function replenish(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_bank_account_id' => [
                'required',
                Rule::exists('bank_accounts', 'id')->where('type', BankAccountType::Bank->value),
            ],
            'to_bank_account_id' => [
                'required',
                Rule::exists('bank_accounts', 'id')->where('type', BankAccountType::PettyCash->value),
            ],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transfer_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        if ($validated['from_bank_account_id'] === $validated['to_bank_account_id']) {
            throw new InvalidArgumentException('Source and destination accounts must be different.');
        }

        $fromAccount = BankAccount::query()->findOrFail($validated['from_bank_account_id']);
        $toAccount = BankAccount::query()->findOrFail($validated['to_bank_account_id']);

        $this->fundTransferService->execute(
            hotelId: (int) session('current_hotel_id'),
            fromChartOfAccountId: (int) $fromAccount->chart_of_account_id,
            toChartOfAccountId: (int) $toAccount->chart_of_account_id,
            amount: round((float) $validated['amount'], 2),
            transferDate: $validated['transfer_date'],
            description: $validated['description'],
            fromBankAccountId: $fromAccount->id,
            toBankAccountId: $toAccount->id,
            createdBy: $request->user()->id,
        );

        return back()->with('success', 'Petty cash replenished.');
    }

    /**
     * @param  array<int, string>  $types
     * @return array<int, array{id: int, account_code: string, name: string}>
     */
    private function counterpartAccounts(array $types): array
    {
        return ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->whereIn('account_type', $types)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'name'])
            ->map(fn (ChartOfAccount $account) => [
                'id' => $account->id,
                'account_code' => $account->account_code,
                'name' => $account->name,
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, code: string, name: string}>
     */
    private function departmentOptions(): array
    {
        return Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Department $department) => [
                'id' => $department->id,
                'code' => $department->code,
                'name' => $department->name,
            ])
            ->all();
    }

    private function generateReferenceNumber(PettyCashDirection $direction): string
    {
        return DB::transaction(function () use ($direction): string {
            $prefix = ($direction === PettyCashDirection::Out ? 'BKK' : 'BKM').'-'.now()->format('Ym').'-';

            $lastNo = PettyCashTransaction::query()
                ->withoutGlobalScope('hotel')
                ->where('reference_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('reference_no')
                ->value('reference_no');

            $sequence = 1;
            if ($lastNo !== null) {
                $sequence = (int) substr($lastNo, -4) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
    }
}
