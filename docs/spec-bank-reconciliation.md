# Bank Reconciliation Module — Technical Specification (Pratasaba ERP)

**Status:** Implemented (Sep 2026 rebuild). **Audience:** developers and finance integrators.

## Sign convention and matching

| Side | Polarity | `netAmount()` |
|------|-----------|---------------|
| Bank statement line | Bank polarity: `debit` = money out, `credit` = money in | `debit − credit` |
| Book line (GL snapshot) | GL polarity on the bank COA | `debit − credit` |

A **match group** is valid when cleared bank and book nets sum to zero within tolerance:

- `difference = bankNet + bookNet` (summation, not subtraction)
- `BankReconciliationSupport::TOLERANCE = 0.005` (IDR)

Manual and automatic groups store `bank_total`, `book_total`, and `difference` on `bank_reconciliation_matches`.

## Balance proof

All formulas live in `BankReconciliationBalanceService`.

| Metric | Formula / rule |
|--------|----------------|
| Cleared bank / book nets | Sum `netAmount()` of lines **excluding** `excluded` and `outstanding` |
| Cleared difference | `bankNet + bookNet` (must be ≈ 0 to submit) |
| Cross-foot | `statement_closing − statement_opening` vs `−Σ netAmount(statement lines)` (non-excluded); tolerance 0.005 |
| Deposits in transit | Outstanding **book** lines with positive net |
| Outstanding checks | Absolute sum of outstanding **book** lines with negative net |
| Outstanding bank net | Sum of outstanding **statement** line nets |
| Adjusted statement balance | `statement_closing + deposits_in_transit − outstanding_checks − outstanding_bank_net` |
| Reconciliation difference | `adjusted_statement_balance − book_closing` |
| Unexplained difference | `(statement_closing + unmatched_book_net) − (book_closing − unmatched_bank_net)` |

Submission (`BankReconciliationWorkflowService::submitForValidation`) requires: both closings set, zero unmatched lines, cleared difference ≈ 0, cross-foot true, unexplained difference ≈ 0, and no stale book lines.

## Status machines

### Session (`BankReconciliationStatus`)

`draft` → `in_progress` / `in_review` → `pending_validation` (locked) → `completed` (locked). Also: `processing`, `failed`, `reopened`, `void`.

`isLockedForEditing()` is true for `pending_validation`, `completed`, and `void`.

### Validation (`BankReconciliationValidationStatus`)

`pending` while awaiting a second user; `validated` when completed; `rejected` returns the session to `in_review`.

Preparer cannot validate their own submission (`isPreparer()` checks `created_by`, `submitted_by`, `validated_by`).

### Line statuses

- **Statement:** `unmatched`, `matched`, `manual`, `excluded`, `outstanding`, plus `adjusted` (stored string when a bank adjustment journal exists)
- **Book:** `unmatched`, `matched`, `manual`, `excluded`, `outstanding`

## Matching ladder (`BankReconciliationMatchingService`)

Auto-match clears prior **automatic** groups only, then runs in order:

| Step | Constant | Behaviour |
|------|----------|-----------|
| Reference | `REFERENCE_DATE_WINDOW_DAYS = 7` | Normalized reference + opposite amounts |
| Exact | `EXACT_DATE_WINDOW_DAYS = 1` | Opposite amount, posting date within ±1 day |
| Fuzzy | `FUZZY_DATE_WINDOW_DAYS = 5`, `FUZZY_MIN_SCORE = 40` | Description similarity + amount + date window |
| Split (many book → one bank) | `SPLIT_DATE_WINDOW_DAYS = 7`, `SPLIT_MAX_NEARBY_CANDIDATES = 20`, `SPLIT_MIN_LINES = 2`, `SPLIT_MAX_LINES = 5` | Subset-sum on book lines |
| Split (many bank → one book) | Same split constants | Subset-sum on statement lines |

Manual match requires at least one line on each side and a zero-sum group within tolerance.

## Adjustments and reversal

- `PostBankAdjustmentAction`: unmatched statement line only; creates a posted `JournalEntry` and posts GL with `source_type = journal_entry`, `source_id = journal entry id` (idempotent per source pair in `GlPostingService`).
- `ReverseBankAdjustmentAction`: reversing journal linked via `reversed_from_id`; clears `adjusting_journal_id` on the statement line.
- After adjustment, `BankBookLineFetcher::fetchAndReplace` runs and auto-match retries.

Counter-account hints: `BankReconciliationSupport::COUNTER_ACCOUNT_BY_KEYWORD` (e.g. `6-8600` bank charges, `4-9100` jasa giro, `2-2220` PPh final jasa giro).

## Book-side snapshot and staleness

`BankBookLineFetcher::fetchAndReplace` loads GL rows for the bank COA between `periode` and `period_end_date`, preserves matched/carried rows, deletes only unmatched non-carried rows missing from GL, and refreshes `book_opening_balance` / `book_closing_balance`.

`refreshStaleFlags` compares snapshot debit/credit/date to live `general_ledger` rows. Submit is blocked while any book line is stale.

## Carry-forward

`CarryForwardOutstandingAction::importOutstandingInto` runs when a new session is created for the same bank account. It copies `outstanding` statement and book lines from the latest prior period into the new session with `is_carried_forward`, `carried_from_*`, and `origin_reconciliation_id`. `outstandingNeedingAttention($days = 60)` flags aged items for the reconcile UI.

## Permissions (Spatie)

| Permission | Typical roles |
|------------|----------------|
| `bankrec.view` | admin, finance, manager, front_office |
| `bankrec.import` | admin, finance |
| `bankrec.reconcile` | admin, finance |
| `bankrec.adjust` | admin, finance |
| `bankrec.validate` | admin, finance, manager |

There is no `bankrec.finalize`; validation completes the session.

## HTTP API (prefix `/accounting/bank-reconciliation`)

Registered in `routes/web.php` (23 routes): index, store, reconcile workspace, report + PDF download, status JSON, import lines, balances, book refresh, auto-match, match/unmatch, exclude/include/outstanding (statement and book), adjustments + reverse, submit, validate, reject, reopen, void. Idempotency middleware on mutating routes.

## Operations commands

| Command | Schedule | Purpose |
|---------|----------|---------|
| `bankrec:health` | Daily | Orphan match groups, unbalanced completed sessions, stale book lines, carry-forwards > 60 days; exit 1 if any anomaly |
| `bankrec:purge-sessions --days=90` | Weekly | Delete old `draft`/`failed` sessions with no matches, adjustments, or non-unmatched statement lines; safety cap 50 deletions unless `--force` |

## File map

| Area | Location |
|------|----------|
| Balance / proof | `app/Services/Accounting/BankReconciliation/BankReconciliationBalanceService.php` |
| Matching | `app/Services/Accounting/BankReconciliation/BankReconciliationMatchingService.php` |
| Book fetch / stale | `app/Services/Accounting/BankReconciliation/BankBookLineFetcher.php` |
| Workflow | `app/Services/Accounting/BankReconciliation/BankReconciliationWorkflowService.php` |
| Adjustments | `app/Actions/Accounting/PostBankAdjustmentAction.php`, `ReverseBankAdjustmentAction.php` |
| Carry-forward | `app/Actions/Accounting/CarryForwardOutstandingAction.php` |
| HTTP | `app/Http/Controllers/Accounting/BankReconciliationController.php` |
| UI | `resources/js/Pages/Accounting/BankReconciliation/*` |
| PDF | `resources/views/bank-reconciliation/report.blade.php` |
| Commands | `app/Console/Commands/BankReconciliationHealthCommand.php`, `PurgeBankReconciliationSessionsCommand.php` |
| Tests | `tests/Feature/BankReconciliation*.php`, `tests/Unit/BankReconciliation*.php` |
