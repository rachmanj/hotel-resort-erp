<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\FundTransfer;
use App\Services\Accounting\FundTransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FundTransferController extends Controller
{
    public function __construct(
        private FundTransferService $fundTransferService,
    ) {}

    public function index(): Response
    {
        $transfers = FundTransfer::query()
            ->with([
                'fromChartOfAccount:id,account_code,name',
                'toChartOfAccount:id,account_code,name',
                'fromBankAccount:id,bank_name,account_name',
                'toBankAccount:id,bank_name,account_name',
                'createdBy:id,name',
            ])
            ->orderByDesc('transfer_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->through(fn (FundTransfer $transfer) => [
                'id' => $transfer->id,
                'transfer_no' => $transfer->transfer_no,
                'transfer_date' => $transfer->transfer_date->toDateString(),
                'description' => $transfer->description,
                'amount' => (float) $transfer->amount,
                'from_account' => $transfer->fromChartOfAccount
                    ? "{$transfer->fromChartOfAccount->account_code} - {$transfer->fromChartOfAccount->name}"
                    : null,
                'to_account' => $transfer->toChartOfAccount
                    ? "{$transfer->toChartOfAccount->account_code} - {$transfer->toChartOfAccount->name}"
                    : null,
                'from_bank' => $transfer->fromBankAccount?->bank_name,
                'to_bank' => $transfer->toBankAccount?->bank_name,
                'created_by' => $transfer->createdBy?->name,
            ]);

        $cashAccounts = ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->where('account_code', 'like', '1-1%')
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'name']);

        $allPostableAccounts = ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'name']);

        return Inertia::render('Accounting/Transfers/Index', [
            'transfers' => $transfers,
            'cashAccounts' => $cashAccounts,
            'allAccounts' => $allPostableAccounts,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'from_chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'to_chart_of_account_id' => ['required', 'exists:chart_of_accounts,id', 'different:from_chart_of_account_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'transfer_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
        ]);

        $this->fundTransferService->execute(
            hotelId: (int) session('current_hotel_id'),
            fromChartOfAccountId: (int) $validated['from_chart_of_account_id'],
            toChartOfAccountId: (int) $validated['to_chart_of_account_id'],
            amount: round((float) $validated['amount'], 2),
            transferDate: $validated['transfer_date'],
            description: $validated['description'],
            createdBy: $request->user()->id,
        );

        return back()->with('success', 'Fund transfer recorded.');
    }
}
