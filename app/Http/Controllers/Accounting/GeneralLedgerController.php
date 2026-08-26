<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\GeneralLedger;
use App\Services\Accounting\GlPostingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GeneralLedgerController extends Controller
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function index(Request $request): Response
    {
        $hotelId = (int) session('current_hotel_id');
        $asOfDate = $request->filled('as_of') ? Carbon::parse($request->string('as_of')) : now();

        $entries = GeneralLedger::query()
            ->with(['chartOfAccount:id,account_code,name', 'department:id,name'])
            ->when($request->filled('account_id'), fn ($q) => $q->where('chart_of_account_id', $request->integer('account_id')))
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', $request->integer('department_id')))
            ->when($request->filled('from'), fn ($q) => $q->where('transaction_date', '>=', $request->string('from')))
            ->when($request->filled('to'), fn ($q) => $q->where('transaction_date', '<=', $request->string('to')))
            ->when(! $request->filled('from') && ! $request->filled('to'), fn ($q) => $q->where('transaction_date', '<=', $asOfDate->toDateString()))
            ->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (GeneralLedger $entry) => [
                'id' => $entry->id,
                'transaction_date' => $entry->transaction_date->toDateString(),
                'account_code' => $entry->chartOfAccount?->account_code,
                'account_name' => $entry->chartOfAccount?->name,
                'department_name' => $entry->department?->name,
                'description' => $entry->description,
                'debit' => (float) $entry->debit,
                'credit' => (float) $entry->credit,
                'reference_number' => $entry->reference_number,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
            ]);

        $accounts = ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'name']);

        $selectedAccount = $request->filled('account_id')
            ? ChartOfAccount::query()->find($request->integer('account_id'))
            : null;

        $departments = Department::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Accounting/GeneralLedger/Index', [
            'entries' => $entries,
            'accounts' => $accounts,
            'departments' => $departments,
            'filters' => $request->only(['account_id', 'department_id', 'from', 'to', 'as_of']),
            'accountBalance' => $selectedAccount !== null
                ? $this->glPostingService->getBalance($selectedAccount, $asOfDate, $hotelId)
                : null,
        ]);
    }
}
