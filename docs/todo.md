Keep your task management simple and focused on what you're actually working on:

```markdown
**Purpose**: Track current work and immediate priorities
**Last Updated**: [Auto-updated by AI]

## Task Management Guidelines

### Entry Format

Each task entry must follow this format:
[status] priority: task description [context] (completed: YYYY-MM-DD)

### Context Information

Include relevant context in brackets to help with future AI-assisted coding:

- **Files**: `[src/components/Search.tsx:45]` - specific file and line numbers
- **Functions**: `[handleSearch(), validateInput()]` - relevant function names
- **APIs**: `[/api/jobs/search, POST /api/profile]` - API endpoints
- **Database**: `[job_results table, profiles.skills column]` - tables/columns
- **Error Messages**: `["Unexpected token '<'", "404 Page Not Found"]` - exact errors
- **Dependencies**: `[blocked by auth system, needs API key]` - blockers

### Status Options

- `[ ]` - pending/not started
- `[WIP]` - work in progress
- `[blocked]` - blocked by dependency
- `[testing]` - testing in progress
- `[done]` - completed (add completion date)

### Priority Levels

- `P0` - Critical (app won't work without this)
- `P1` - Important (significantly impacts user experience)
- `P2` - Nice to have (improvements and polish)
- `P3` - Future (ideas for later)

--- Example

# Current Tasks

## Working On Now

- `[WIP] P1: Implement user authentication [src/auth/login.tsx, Firebase Auth]`

## Up Next (This Week)

- `[ ] P0: Fix database connection timeout [src/db/connection.ts, line 23]`
- `[ ] P1: Add error handling to API calls [API endpoints: /users, /profile]`

## Blocked/Waiting

- `[blocked] P2: Add payment integration [waiting for Stripe API keys]`

## Up Next (This Week)

- `[ ] P0: Begin Phase 1 scaffolding per docs/plan.md Section 9 - multi-property foundation + spatie auth + basic room setup [Laravel 13 scaffold, spatie/laravel-permission, hotels/hotel_user/currencies/exchange_rates tables, BelongsToHotel scope, ResolveHotelContext middleware]`
- `[ ] P1: Review and prioritize docs/group-promo-agent-plan.md (Group Booking Types A/B/C, Promotional Rates CRUD + auto-apply engine, Agent Booking + portal + commissions) [new tables: reservation_groups, promotions/promotion_*, agents/agent_rates/agent_commissions; est. ~65 ideal-dev-days]`

## Recently Completed

- `[done] P0: Booking hold state - new reservations start Tentative with a hold limit and auto-cancel when it passes [add_hold_expires_at_to_reservations_table migration, config/reservations.php (hold_days, RESERVATION_HOLD_DAYS), app/Actions/Reservations/CreateReservationAction.php (Tentative default + hold stamp), app/Actions/Reservations/ConfirmReservationAction.php, app/Console/Commands/ExpireReservationHolds.php (reservations:expire-holds, scheduled daily 01:00), POST /reservations/{reservation}/confirm behind reservations.manage, CheckInController/CheckInGuestAction/Telegram CheckInCommand tentative messages, tests/Feature/ReservationHoldTest.php] (completed: 2026-09-22)`
- `[done] P1: Reservation/billing document flow phase 3 - release-gated guest Invoice derived from folio items [guest_invoices/guest_invoice_lines migrations, app/Services/GuestInvoiceNumberService.php (YYMM + 3-digit per-month counter, e.g. 2607003), app/Actions/Billing/SyncGuestInvoiceAction.php + ReleaseGuestInvoiceAction.php, app/Observers/FolioItemGuestInvoiceObserver.php, app/Http/Controllers/GuestInvoiceController.php, resources/views/invoices/guest.blade.php, resources/js/Pages/Folios/GuestInvoice.tsx, config/invoice.php, invoice.release permission] (completed: 2026-09-22)`
- `[done] P1: Reservation document flow phase 2 - down payment recording, finance verification, automatic Payment Receipt and outstanding tracking [proforma_payments migration + received_total/outstanding_total on proforma_invoices, app/Services/ProformaPaymentReceiptNumberService.php + IndonesianNumberWordsService.php, app/Actions/Reservations/RecordProformaPaymentAction.php + VerifyProformaPaymentAction.php + RefreshProformaInvoiceTotalsAction.php, app/Http/Controllers/ProformaPaymentController.php, resources/views/receipts/payment.blade.php, resources/js/Pages/Reservations/Proforma.tsx, proforma.payment.record + proforma.payment.verify permissions] (completed: 2026-09-22)`
- `[done] P1: Reservation document flow phase 1 - Proforma Invoice issued as draft on booking, released by Finance only [proforma_invoices/proforma_invoice_lines migrations, app/Services/ProformaInvoiceNumberService.php, app/Actions/Reservations/SyncProformaInvoiceAction.php + ReleaseProformaInvoiceAction.php, app/Observers/Reservation*ProformaObserver.php, app/Http/Controllers/ProformaInvoiceController.php, resources/views/invoices/proforma.blade.php, resources/js/Pages/Reservations/Proforma.tsx, config/proforma.php, proforma.release permission] (completed: 2026-09-22)`
- `[done] P0: Resequence docs/plan.md Section 9 so Room Reservation (Phase 2) + Telegram Bot (Phase 3) ship before Billing/Housekeeping; split Telegram command rollout across Phases 3-9a [docs/plan.md Sections 6.2, 6.3, 6.3 (new), 6.4, 9, 11.3] (completed: 2026-07-26)`
- `[done] P0: Resolve all 15 stakeholder Open Questions in docs/plan.md; rewrite plan for multi-property + multi-currency + spatie architecture [docs/plan.md Sections 1-11, .cursorrules Auth/Multi-property/Multi-currency lines] (completed: 2026-07-25)`

## Quick Notes

- Reservation/billing document flow is at phase 4: the Proforma Invoice (phase 1), down payments + finance verification + Payment Receipts + outstanding tracking (phase 2), the release-gated guest Invoice built from folio items (phase 3), and the booking hold state with automatic expiry (phase 4). Carrying a verified proforma payment into the folio is a later phase and deliberately absent. Run `php artisan db:seed --class=RolePermissionSeeder` on existing databases to create the `proforma.release`, `proforma.payment.record`, `proforma.payment.verify`, `invoice.release` and `reservations.manage` permissions.
- The guest Invoice lives at `/folios/{folio}/guest-invoice` and is separate from the older simple folio print at `/folios/{folio}/invoice` (`InvoiceController`), which is still in place. The new document is the numbered, release-gated one; the old page is an internal charge sheet.
- Reservations are created as Tentative by `CreateReservationAction` and carry `hold_expires_at` (`config('reservations.hold_days')`, default 3). They become Confirmed either when finance verifies a down payment or when staff with `reservations.manage` post to `/reservations/{reservation}/confirm`; both go through `ConfirmReservationAction`, which clears the hold. `reservations:expire-holds` (daily 01:00) cancels whatever is still Tentative past its hold. The OTA webhook passes an explicit Confirmed status because those bookings arrive already firm.
- Plan is v2.1 as of 2026-07-26 — phases resequenced so Room Reservation + Telegram (core differentiator) land by Phase 3; Telegram bot now ships in 5 incremental slices (Phases 3/4/5/6/7, plus 8/9a for accounting/reporting commands) instead of one monolithic phase. See docs/decisions.md for the full decision record and MEMORY.md [M002] for the summary.
```
