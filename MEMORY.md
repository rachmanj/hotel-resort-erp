**Purpose**: AI's persistent knowledge base for project context and learnings
**Last Updated**: [Auto-updated by AI]

## Memory Maintenance Guidelines

### Structure Standards

- Entry Format: ### [ID] [Title (YYYY-MM-DD)] ✅ STATUS
- Required Fields: Date, Challenge/Decision, Solution, Key Learning
- Length Limit: 3-6 lines per entry (excluding sub-bullets)
- Status Indicators: ✅ COMPLETE, ⚠️ PARTIAL, ❌ BLOCKED

### Content Guidelines

- Focus: Architecture decisions, critical bugs, security fixes, major technical challenges
- Exclude: Routine features, minor bug fixes, documentation updates
- Learning: Each entry must include actionable learning or decision rationale
- Redundancy: Remove duplicate information, consolidate similar issues

### File Management

- Archive Trigger: When file exceeds 500 lines or 6 months old
- Archive Format: `memory-YYYY-MM.md` (e.g., `memory-2025-01.md`)
- New File: Start fresh with current date and carry forward only active decisions

---

## Project Memory Entries

### [M001] Plan v2.0: All 15 Open Questions resolved, multi-property + multi-currency now active (2026-07-25) ✅ COMPLETE

- **Challenge/Decision**: `docs/plan.md` had 15 unresolved Open Questions; 3 were architecture-changing (spatie/laravel-permission, multi-property, multi-currency) and touched nearly every section of the plan.
- **Solution**: Rewrote `docs/plan.md` end-to-end for internal consistency — `hotels`/`hotel_user`/`currencies`/`exchange_rates` tables, `hotel_id` scoping on every property-level table, foreign-currency columns on `folio_items`/`payments`/`ar_invoices`, spatie schema replacing hand-rolled roles/permissions, new Section 5.7 (property switch flow) and 10.8 (multi-currency conventions), Section 10.6 rewritten, Section 11 rewritten as a resolved decisions log. Also updated `.cursorrules` (Auth, Multi-property, Multi-currency lines) which had contradicted the new decisions.
- **Key Learning**: When a plan doc's Open Questions include architecture-changing decisions (not just scope decisions), resolving them requires a full consistency pass across ERD/schema/routes/frontend/phases/conventions — marking the decision alone in one table is not sufficient; every table that would need a schema-breaking retrofit later (e.g. `hotel_id`, currency columns) should be built into the *first* migration instead.

---

### [M002] Plan resequenced: Room Reservation + Telegram moved to Phase 3, bot split into 5 phase-slices (2026-07-26) ✅ COMPLETE

- **Challenge/Decision**: Stakeholder wants Room Reservation + Telegram (the core differentiator) usable before other modules; old sequencing put Telegram at Phase 5, blocked behind Billing and Housekeeping.
- **Solution**: Rewrote `docs/plan.md` Section 9 — Phase 2 now includes bare-minimum `guests`; new Phase 3 ships a reservation-only Telegram bot; old Phase 3 (Billing) + old Phase 6 (Guest CRM) merged into new Phase 4; Housekeeping/F&B/Inventory shifted to 5/6/7; Accounting Core renumbered 8b→8. Telegram command set split across Phases 3/4/5/6/7/8/9a via a new Section 6.3.1 roadmap table instead of shipping all at once.
- **Key Learning**: A "ship the differentiator early" resequencing request usually also implies *splitting* whichever feature was previously bundled into one late phase (here, the Telegram bot) — moving the phase number alone isn't enough if that phase's deliverables still transitively depend on later phases (old Phase 5 bundled `/checkin` which needs Billing). Also: when a table's full column set isn't needed by the earliest consumer, split it (bare-minimum now, full profile later) rather than front-loading unused columns.

---

### [M003] Phase 1 implemented: Inertia/React/AntD + spatie auth + multi-property + rooms foundation (2026-07-26) ✅ COMPLETE

- **Challenge/Decision**: First implementation pass — needed full scaffold with `BelongsToHotel` global scope, `ResolveHotelContext` middleware, spatie permission matrix (8 roles), hotels/currencies/rooms CRUD, and React+AntD ProLayout from day one.
- **Solution**: Laravel 13 backend with Actions (`SwitchHotelContext`, `CreateHotel`, `StoreExchangeRate`, etc.), thin controllers + FormRequests, `RolePermissionSeeder` + `HotelCurrencySeeder` (GNB hotel, admin@hotel.test). Frontend: `AuthenticatedLayout` (role-aware ProLayout sidebar), `PropertySwitcher`, ProTable pages for rooms/hotels/currencies.
- **Key Learning**: `session('current_hotel_id')` must be set in `ResolveHotelContext` before any hotel-scoped Eloquent query runs; super-admin (`users.hotel_id = null`) gets all active hotels via `User::accessibleHotels()`. npm install for Ant Design Pro packages needs `--legacy-peer-deps` on this stack.

---

### [M004] Proforma Invoice (reservation document flow, phase 1) implemented (2026-09-22) ✅ COMPLETE

- **Challenge/Decision**: Pratasaba issues a paper Proforma Invoice when Marketing books a reservation, but only Finance may release it. The document must stay identical to the copy already sent to the customer once released, and its number format (`066/PI/PRATA/VIII/2026`) counts per year with no gaps or collisions.
- **Solution**: `proforma_invoices` + `proforma_invoice_lines` (`BelongsToHotel`), `ProformaInvoiceNumberService::reserveNext()` (roman month, `lockForUpdate` range lock, refuses to run outside a transaction), `SyncProformaInvoiceAction` wired through `ReservationProformaObserver` + `ReservationRoomProformaObserver` so every booking path (web, Telegram, OTA) issues and regenerates the draft, `ReleaseProformaInvoiceAction` behind the new `proforma.release` permission (admin + finance), `ProformaInvoiceController` (show/download/release), DomPDF view `invoices.proforma`, Inertia page `Reservations/Proforma`.
- **Key Learning**: The document number is only unique per property (the `PRATA` segment *is* the property token), so the unique index must be `(hotel_id, number)` — a bare unique on `number` broke `DashboardTest`, where two hotels each legitimately issue `001/PI/PRATA/IX/2026`. Also, a number reserved in its own committed transaction is unsafe: the range lock has to be held by the same transaction that inserts the row, which is why the sequence is reserved inside `SyncProformaInvoiceAction`'s transaction.

---

### [M005] Down payment, payment receipt and outstanding tracking (reservation document flow, phase 2) implemented (2026-09-22) ✅ COMPLETE

- **Challenge/Decision**: Marketing records a down payment with proof of transfer, Finance verifies the money actually reached the bank account, and only then does the guest get a paper `Tanda Terima Pembayaran` and the booking become firm. The money cannot go into the folio-level `payments` table because no folio exists at booking time, and the receipt number must be readable next to the proforma invoice number it belongs to (`PR-045/PI/PRATA/VI/2026`).
- **Solution**: `proforma_payments` (`BelongsToHotel`, unique `receipt_number`) with a two-step `recorded` → `verified` status. `RecordProformaPaymentAction` only logs the claim; `VerifyProformaPaymentAction` issues the receipt via `ProformaPaymentReceiptNumberService::reserveNext()` (locks the invoice row, counts only issued receipts, refuses to run outside a transaction), flips a Tentative reservation to Confirmed, and refuses a second verification with an `InvalidArgumentException` the controller turns into a flash error. `RefreshProformaInvoiceTotalsAction` keeps `received_total`/`outstanding_total` on the invoice in step and is also called from `SyncProformaInvoiceAction` so a rate change reprices the outstanding balance. `IndonesianNumberWordsService` renders the amount in words for the receipt PDF (`receipts.payment`).
- **Key Learning**: `activity_logs.event` is `varchar(20)`, so descriptive event names like `proforma_payment_recorded` blow up with a MySQL 1406 truncation error at runtime rather than at validation — keep new event names under 20 characters (`payment_recorded`). Also, only *verified* payments may count toward `received_total`: counting recorded ones would let Marketing zero out the outstanding balance without any money arriving.

---

### [M006] Release-gated guest Invoice derived from folio items (billing document flow, phase 3) implemented (2026-09-22) ✅ COMPLETE

- **Challenge/Decision**: Front office posts guest charges to the folio all stay long and the Invoice must keep updating itself, but it cannot be used or sent until Finance releases it. The paper document is `Rincian / QTY / Ns / Harga / Total` with three signature lines (Prepared / Approved / Received by) and a number like `2607003` — year 26, month 07, running number 003, so the counter restarts monthly, unlike the proforma's yearly one.
- **Solution**: `guest_invoices` + `guest_invoice_lines` (`BelongsToHotel`), `GuestInvoiceNumberService::reserveNext()` (counter keyed on `(hotel_id, year, month)`, `lockForUpdate` range lock, refuses to run outside a transaction), `SyncGuestInvoiceAction` driven by `FolioItemGuestInvoiceObserver` so any charge path (web, Telegram, F&B charge-to-room, night audit) rewrites the draft, `ReleaseGuestInvoiceAction` behind the new `invoice.release` permission (admin + finance), `GuestInvoiceController` (show/download/release) on `billing.view`, DomPDF view `invoices.guest`, Inertia page `Folios/GuestInvoice`, terms and bank accounts in `config/invoice.php`.
- **Key Learning**: The `Ns` (nights) column has no folio column behind it — room charges are posted with `quantity` holding the nights and `unit_price` holding the nightly rate, so the invoice line has to reinterpret that as `QTY 1 / Ns 3` while every other charge type keeps its own quantity and leaves `Ns` blank. Also, the posted `tax_amount`/`service_charge_amount` must be copied onto the invoice line rather than recomputed at print time, otherwise a later tax rule change would silently restate an already-released invoice.

---

[Add new memory entries below, following the format above]
