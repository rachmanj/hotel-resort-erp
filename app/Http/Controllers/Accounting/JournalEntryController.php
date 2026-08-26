<?php

namespace App\Http\Controllers\Accounting;

use App\Enums\JournalEntryStatus;
use App\Http\Controllers\Controller;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Services\Accounting\GlPostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class JournalEntryController extends Controller
{
    public function __construct(
        private GlPostingService $glPostingService,
    ) {}

    public function index(Request $request): Response
    {
        $entries = JournalEntry::query()
            ->with(['createdBy:id,name', 'approvedBy:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (JournalEntry $entry) => [
                'id' => $entry->id,
                'journal_no' => $entry->journal_no,
                'entry_date' => $entry->entry_date->toDateString(),
                'description' => $entry->description,
                'status' => $entry->status->value,
                'status_label' => $entry->status->label(),
                'created_by' => $entry->createdBy?->name,
                'approved_by' => $entry->approvedBy?->name,
                'posted_at' => $entry->posted_at?->toDateTimeString(),
            ]);

        return Inertia::render('Accounting/JournalEntries/Index', [
            'entries' => $entries,
            'filters' => $request->only(['status']),
            'statusOptions' => collect(JournalEntryStatus::cases())->map(fn (JournalEntryStatus $status) => [
                'value' => $status->value,
                'label' => $status->label(),
            ]),
        ]);
    }

    public function create(): Response
    {
        $accounts = ChartOfAccount::query()
            ->where('is_postable', true)
            ->where('is_active', true)
            ->orderBy('account_code')
            ->get(['id', 'account_code', 'name']);

        return Inertia::render('Accounting/JournalEntries/Create', [
            'accounts' => $accounts,
            'departments' => $this->departmentOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2'],
            'lines.*.chart_of_account_id' => ['required', 'exists:chart_of_accounts,id'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
            'lines.*.debit' => ['required', 'numeric', 'min:0'],
            'lines.*.credit' => ['required', 'numeric', 'min:0'],
            'lines.*.department_id' => ['nullable', 'exists:departments,id'],
        ]);

        DB::transaction(function () use ($validated, $request): void {
            $entry = JournalEntry::query()->create([
                'journal_no' => $this->generateJournalNumber(),
                'entry_date' => $validated['entry_date'],
                'description' => $validated['description'],
                'status' => JournalEntryStatus::Draft->value,
                'created_by' => $request->user()->id,
            ]);

            foreach ($validated['lines'] as $line) {
                JournalEntryLine::query()->create([
                    'journal_entry_id' => $entry->id,
                    'chart_of_account_id' => $line['chart_of_account_id'],
                    'department_id' => $line['department_id'] ?? null,
                    'description' => $line['description'] ?? null,
                    'debit' => $line['debit'],
                    'credit' => $line['credit'],
                ]);
            }
        });

        return redirect()->route('accounting.journal-entries.index')->with('success', 'Journal entry created.');
    }

    public function show(JournalEntry $journalEntry): Response
    {
        $journalEntry->load(['lines.chartOfAccount', 'lines.department', 'createdBy:id,name', 'approvedBy:id,name']);

        return Inertia::render('Accounting/JournalEntries/Show', [
            'entry' => [
                'id' => $journalEntry->id,
                'journal_no' => $journalEntry->journal_no,
                'entry_date' => $journalEntry->entry_date->toDateString(),
                'description' => $journalEntry->description,
                'status' => $journalEntry->status->value,
                'status_label' => $journalEntry->status->label(),
                'created_by' => $journalEntry->createdBy?->name,
                'approved_by' => $journalEntry->approvedBy?->name,
                'posted_at' => $journalEntry->posted_at?->toDateTimeString(),
                'lines' => $journalEntry->lines->map(fn (JournalEntryLine $line) => [
                    'id' => $line->id,
                    'account_code' => $line->chartOfAccount?->account_code,
                    'account_name' => $line->chartOfAccount?->name,
                    'department_name' => $line->department?->name,
                    'description' => $line->description,
                    'debit' => (float) $line->debit,
                    'credit' => (float) $line->credit,
                ]),
            ],
        ]);
    }

    public function submit(JournalEntry $journalEntry): RedirectResponse
    {
        if ($journalEntry->status !== JournalEntryStatus::Draft) {
            throw new InvalidArgumentException('Only draft entries can be submitted.');
        }

        $journalEntry->update(['status' => JournalEntryStatus::Submitted->value]);

        return back()->with('success', 'Journal entry submitted.');
    }

    public function approve(Request $request, JournalEntry $journalEntry): RedirectResponse
    {
        if ($journalEntry->status !== JournalEntryStatus::Submitted) {
            throw new InvalidArgumentException('Only submitted entries can be approved.');
        }

        $journalEntry->load('lines');

        DB::transaction(function () use ($journalEntry, $request): void {
            $glLines = $journalEntry->lines->map(fn (JournalEntryLine $line) => [
                'hotel_id' => (int) $journalEntry->hotel_id,
                'chart_of_account_id' => $line->chart_of_account_id,
                'department_id' => $line->department_id,
                'transaction_date' => $journalEntry->entry_date->toDateString(),
                'debit' => (float) $line->debit,
                'credit' => (float) $line->credit,
                'description' => $line->description ?? $journalEntry->description,
                'reference_number' => $journalEntry->journal_no,
                'source_type' => 'journal_entry',
                'source_id' => $journalEntry->id,
            ])->all();

            $this->glPostingService->post($glLines);

            $journalEntry->update([
                'status' => JournalEntryStatus::Posted->value,
                'approved_by' => $request->user()->id,
                'posted_at' => now(),
            ]);
        });

        return back()->with('success', 'Journal entry approved and posted.');
    }

    private function generateJournalNumber(): string
    {
        return DB::transaction(function (): string {
            $prefix = 'JV-'.now()->format('Ym').'-';

            $lastNo = JournalEntry::query()
                ->withoutGlobalScope('hotel')
                ->where('journal_no', 'like', $prefix.'%')
                ->lockForUpdate()
                ->orderByDesc('journal_no')
                ->value('journal_no');

            $sequence = 1;
            if ($lastNo !== null) {
                $sequence = (int) substr($lastNo, -4) + 1;
            }

            return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        });
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
}
