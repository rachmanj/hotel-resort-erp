**Purpose**: Record technical decisions and rationale for future reference
**Last Updated**: [Auto-updated by AI]

# Technical Decision Records

## Decision Template

Decision: [Title] - [YYYY-MM-DD]

**Context**: [What situation led to this decision?]

**Options Considered**:

1. **Option A**: [Description]
   - ✅ Pros: [Benefits]
   - ❌ Cons: [Drawbacks]
2. **Option B**: [Description]
   - ✅ Pros: [Benefits]
   - ❌ Cons: [Drawbacks]

**Decision**: [What we chose]

**Rationale**: [Why we chose this option]

**Implementation**: [How this affects the codebase]

**Review Date**: [When to revisit this decision]

---

## Recent Decisions

Decision: The guest Invoice is derived from folio items and release-gated by Finance; its number is a per-month counter - 2026-09-22

**Context**: Front office records guest activities and charges onto the folio throughout the stay. Pratasaba's paper Invoice (`Rincian / QTY / Ns / Harga / Total` with three signature lines and an invoice number like `2607003` = year 26, month 07, running number 003) may not be used or sent to the guest until Finance releases it. Phase 1 and 2 already ship the pre-arrival Proforma Invoice and its payment receipts; this is the arrival-to-departure document and must not duplicate either.

**Options Considered**:

1. **Store invoice lines as a snapshot copied from folio items on release only**: no lines exist until Finance releases.
   - ✅ Pros: nothing to keep in sync, no wasted numbers.
   - ❌ Cons: nobody can see or check the invoice before releasing it, which is exactly what Finance needs in order to decide.
2. **Render the invoice straight from folio items every time, with release only stamping a flag**: no `guest_invoice_lines` table.
   - ✅ Pros: no duplication of charge data at all.
   - ❌ Cons: a released invoice would silently change whenever a late charge is posted — the guest's copy and the system disagree, and there is no evidence of what was billed.
3. **Live draft rewritten from folio items, frozen on release, changes issue the next revision (chosen)**: same shape as the proforma decision below.
   - ✅ Pros: Finance reviews the real document before releasing; a released invoice is immutable evidence; late charges are visible as a new draft revision instead of a silent edit.
   - ❌ Cons: charge data is stored twice, and a folio whose charges keep moving burns sequence numbers.

**Decision**: Option 3. `guest_invoice_lines` mirror folio items (description, quantity, nights, unit price, amount, plus the tax and service charge actually posted) while the invoice is draft; a `FolioItemGuestInvoiceObserver` re-derives them on every folio item change so the draft always matches the folio. Release stamps `issued_at`, `released_at`, `released_by` and `approved_by`, after which the row is never written again — a later folio change issues `revision + 1` as a fresh draft with a new number.

**Rationale**: The invoice is the document the guest signs for ("Invoice Received by"), so it has to be frozen at the moment Finance approves it, while still being reviewable before that moment. Copying the posted tax and service charge amounts onto the line — rather than recomputing them at print time — means a later tax rule change cannot retroactively alter a released invoice.

**Implementation**: `guest_invoices` + `guest_invoice_lines`; `GuestInvoiceNumberService::reserveNext()` formats `YYMM` + 3-digit sequence from a counter keyed on `(hotel_id, year, month)`, called inside the inserting transaction so its `lockForUpdate` range lock still holds, with `unique(hotel_id, year, month, sequence)` and `unique(hotel_id, number)` as the backstop against a concurrent claim. `SyncGuestInvoiceAction` decides create/regenerate/revise; `ReleaseGuestInvoiceAction` refuses an already-released invoice with an `InvalidArgumentException` the controller turns into a flash error. Permission `invoice.release` (admin, finance only, mirroring `proforma.release`) gates release; `billing.view` opens and prints. The PDF at `resources/views/invoices/guest.blade.php` hides the SC and Tax columns when no line carries either, since dive, boat and other misc charges are sold tax-inclusive.

**Review Date**: When check-out and city ledger billing are built - decide whether release should also close the folio, and whether a split-billing folio needs one invoice per payer.

---

Decision: Down payments live on the proforma invoice, not the folio, and only count as received once Finance verifies them - 2026-09-22

**Context**: Pratasaba takes a down payment at booking time to secure a reservation. Marketing collects the transfer slip, Finance checks the bank account, and the guest gets a paper `Tanda Terima Pembayaran` whose number mirrors the proforma invoice (`PR-045/PI/PRATA/VI/2026`). The existing `payments` table hangs off a folio, and a folio does not exist until check-in, so there is nowhere to post the money at the moment it arrives.

**Options Considered**:

1. **Create the folio early so the down payment can be posted to it**: open a folio at booking and post a deposit payment.
   - ✅ Pros: one payments table, GL posting is already solved.
   - ❌ Cons: folios drive check-out and billing; an open folio for every tentative booking corrupts occupancy and billing reports, and cancelled bookings leave orphan folios.
2. **A separate `proforma_payments` table keyed to the proforma invoice (chosen)**: record the claim, verify it, then issue a receipt.
   - ✅ Pros: models what the paperwork actually is (a pre-arrival deposit against a quotation), keeps folios meaning "a stay in progress", and the receipt naturally derives its number from the invoice.
   - ❌ Cons: a second money table, and the transfer into the folio at check-in has to be built later.
3. **One-step recording with no verification**: whoever enters the payment also confirms it.
   - ✅ Pros: fewer clicks.
   - ❌ Cons: removes the segregation of duties the client explicitly asked for; Marketing could clear an outstanding balance on the strength of an unverified screenshot.

**Decision**: Option 2 with the two-step `recorded` → `verified` status from Option 3 rejected. Recording is a claim and issues nothing; verification is what issues the receipt number, sets `received_total`/`outstanding_total`, and promotes a Tentative reservation to Confirmed.

**Rationale**: The outstanding amount is a financial statement shown to the customer, so it may only move on money Finance has seen in the bank account. Splitting record from verify also gives the receipt an unambiguous issue event to hang its number and timestamp on.

**Implementation**: `proforma_payments` (unique `receipt_number`, nullable until issued), `RecordProformaPaymentAction` / `VerifyProformaPaymentAction`, `ProformaPaymentReceiptNumberService::reserveNext()` (locks the invoice row and counts only issued receipts inside the verifying transaction; a second and later receipt against the same invoice is suffixed `-2`, `-3`), `RefreshProformaInvoiceTotalsAction` (also called by `SyncProformaInvoiceAction` so a rate change reprices the outstanding balance), permissions `proforma.payment.record` (admin, finance, front_office) and `proforma.payment.verify` (admin, finance only).

**Review Date**: When the folio/check-in phase picks up deposits - decide how a verified proforma payment is carried into the folio and posted to the GL without being counted twice.

---

Decision: Released proforma invoices are immutable; reservation changes issue a new numbered revision - 2026-09-22

**Context**: Marketing books a reservation and the system issues a Proforma Invoice that only Finance may release. After Finance releases it the document has usually been sent to the customer, yet reservations keep changing (rooms added, dates moved, rates renegotiated). The document number is a per-year counter printed on paper (`066/PI/PRATA/VIII/2026`), so it cannot be silently reused.

**Options Considered**:

1. **Rewrite the released document in place**: keep one row per reservation and regenerate its lines whenever the reservation changes.
   - ✅ Pros: simplest model, one document per reservation, no numbering pressure.
   - ❌ Cons: the customer holds a copy that no longer matches the system; no audit trail of what was actually quoted.
2. **Same number, bump the revision counter**: keep the released row, overwrite nothing, but reuse the number with a revision suffix.
   - ✅ Pros: matches how staff talk about a "revised PI".
   - ❌ Cons: the number is no longer a unique key, and two documents in circulation carry the same `No.` field.
3. **New revision as a new numbered document (chosen)**: released rows are frozen; a change issues a fresh draft with `revision = previous + 1` and its own sequence number.
   - ✅ Pros: every piece of paper in circulation maps to exactly one immutable row; unique index on the number holds; Finance re-approves what changed.
   - ❌ Cons: burns sequence numbers faster, and the reservation detail page must show only the latest revision.

**Decision**: Option 3. Drafts are regenerated in place; released documents are never written to again. A change to reservation rooms, dates, or rates creates the next revision as a new draft with a new number, and only when the computed lines actually differ from the released ones.

**Rationale**: A proforma invoice is an external commitment, not internal state. Once it leaves the building the stored row is the only evidence of what was quoted, so freezing it is what makes later phases (down payment tracking, receipts, invoice release) auditable.

**Implementation**: `proforma_invoices.revision` + `status`; `SyncProformaInvoiceAction` decides create/regenerate/revise and is driven by `ReservationProformaObserver` and `ReservationRoomProformaObserver` so all booking paths behave identically. Uniqueness is `(hotel_id, number)` rather than `number` alone, because the number is only unique per property. `ProformaInvoiceNumberService::reserveNext()` must be called inside the transaction that inserts the row, so its `lockForUpdate` range lock is still held when the insert lands.

**Review Date**: When the payment and receipt phases are built - confirm with Finance whether a revision should carry forward the down payment already collected against the superseded document.

---

Decision: Resolve all 15 stakeholder Open Questions in docs/plan.md; adopt multi-property, multi-currency, and spatie/laravel-permission as active Phase 1 architecture - 2026-07-25

**Context**: `docs/plan.md` (Section 11) carried 15 unresolved Open Questions blocking Phase 1 scaffolding. The stakeholder reviewed and decided all 15; three are architecture-changing (spatie/laravel-permission, multi-property from Phase 1, multi-currency from Phase 1) and required updating every affected section of the plan for internal consistency, not just the decisions log.

**Options Considered**:

1. **Multi-property deferred (prior recommendation)**: single implicit hotel context now, retrofit `hotels`/`hotel_id` later.
   - ✅ Pros: leaner Phase 1 schema.
   - ❌ Cons: retrofitting `hotel_id` onto live reservation/folio/GL data later is high-risk; stakeholder confirmed multi-property need is imminent.
2. **Multi-property active from Phase 1 (chosen)**: `hotels` first-class table, `hotel_id` scoping via `BelongsToHotel` global scope from the first migration.
   - ✅ Pros: no risky retrofit; every table/query is written correctly from day one.
   - ❌ Cons: Phase 1 complexity/duration increases (rated Medium→Medium-High).

**Decision**: Adopt `spatie/laravel-permission` (replaces hand-rolled roles/permissions), build `hotels`/`hotel_user`/`BelongsToHotel` multi-property scoping from Phase 1, and add `currencies`/`exchange_rates` + foreign-currency capture columns (`original_currency_code`/`original_amount`/`exchange_rate_id` on `folio_items`, `payments`, `ar_invoices`) for multi-currency (IDR base, USD guest-facing) from Phase 1. GL posting remains IDR-only; no dual-currency ledger.

**Rationale**: Retrofitting tenancy/currency columns onto live transactional and GL data is far more expensive and risky than building them in from the first migration. The remaining 12 decisions matched the plan's original recommendations and required no architecture change, only formal sign-off.

**Implementation**: `docs/plan.md` updated end-to-end — ERD (Section 3), schema (Section 4: `hotels`, `hotel_user`, spatie tables, `currencies`, `exchange_rates`, `hotel_id` on `floors`/`rooms`/`rate_plans`/`reservations`/`folios`/`inventory_items`/`suppliers`/`chart_of_accounts`/`accounting_periods`/`general_ledger`/`bank_accounts`, multi-currency columns on `folio_items`/`payments`/`ar_invoices`), UX flow (new Section 5.7 Property Switch), routes (Section 7: hotel/currency admin routes, `/hotel-context/switch`), frontend (Section 8: `Hotels`/`Currencies` pages, `PropertySwitcher`, updated `HandleInertiaRequests`), implementation phases (Section 9: Phase 1 scope expanded), conventions (Section 10.6 rewritten, new Section 10.8), and Section 11 rewritten as a resolved decisions log.

**Review Date**: Before Phase 1 migrations are written — confirm `hotels` seed data (initial properties) and `currencies` seed data (IDR + USD rate) with the stakeholder.

---

Decision: Resequence Section 9 phases so Room Reservation + Telegram ship by Phase 3, splitting the Telegram bot into five incremental phase-slices instead of one monolithic phase - 2026-07-26

**Context**: The prior sequencing (v2.0) placed the Telegram bot at Phase 5, blocked behind Billing (Phase 3) and Housekeeping (Phase 4). The stakeholder wants Room Reservation + Telegram — the product's core differentiator — live and usable as early as possible, without waiting for the rest of the operational stack.

**Options Considered**:

1. **Keep Telegram as one monolithic phase, just move it earlier**: relocate old Phase 5 (full bot: rooms, reservations, check-in/out) to right after Reservation Core.
   - ✅ Pros: simplest edit, no command-list restructuring.
   - ❌ Cons: would still require Billing (folios) to exist first, since the old Phase 5 bundled `/checkin`/`/checkout` (which post folio charges) together with pure reservation commands — doesn't actually unblock Telegram until Billing lands either way.
2. **Split the Telegram bot into phase-aligned slices (chosen)**: Phase 3 ships only the reservation-dependent commands (`/rooms`, `/available`, `/newres`, `/editres`, `/cancelres`, basic `/roomstatus`/`/myrooms`, linking); later phases (4–7) each add their own small command slice as the underlying module (Billing, Housekeeping, F&B, Inventory/Maintenance) lands.
   - ✅ Pros: bot ships genuinely early (Phase 3, right after web reservations) with zero dependency on Billing/Housekeeping; spreads bot integration risk across the project instead of one late big-bang phase; each later slice is a small addition to already-proven webhook/router infra.
   - ❌ Cons: command list/roadmap needs an explicit phase mapping to avoid confusion about which commands exist at which point; `/roomstatus` and `/myrooms` need a documented "basic → full" transition (Phase 3 → Phase 5) since they exist before housekeeping data does.

**Decision**: Reorganize `docs/plan.md` Section 9 to: Phase 1 (unchanged) → Phase 2 Reservation Core (now also carries bare-minimum `guests`) → Phase 3 Telegram Bot: Room Reservation (new) → Phase 4 Check-in/out + Folio/Billing + Guest CRM (merges old Phase 3 + old Phase 6) → Phase 5 Housekeeping → Phase 6 F&B → Phase 7 Inventory/Purchasing/Maintenance → Phase 8 Accounting Core (was 8b) → 9a Reporting → 9b Spa → 10 Accounting Extensions → 11 Hardening. Telegram commands are delivered across Phases 3/4/5/6/7/8/9a per the new [Telegram Delivery Roadmap](../docs/plan.md#631-telegram-delivery-roadmap).

**Rationale**: Getting a usable, demoable reservation + Telegram workflow live by Phase 3 (vs. Phase 5) is worth splitting the bot's command set across phases and moving `guests` bare-minimum fields earlier — the alternative (wait for the full command set in one late phase) delays the differentiator without any corresponding benefit, since most bot commands are trivially separable by the module they call into.

**Implementation**: `docs/plan.md` Section 9 (phases table + rationale) rewritten; Section 6.2/6.3 command tables got a `Phase` column; new Section 6.3.1 (Telegram Delivery Roadmap) added; Section 6.4 alerts table got a `Phase` column; Section 11.3 in-scope bullet updated to reference the phased rollout. ERD, schema (Section 4), routes, and frontend sections were **not** changed — feature content is identical, only sequencing and the `guests` table's two-stage delivery (bare-minimum in Phase 2, full profile in Phase 4) changed.

**Review Date**: Before Phase 2 migrations are written — confirm the bare-minimum `guests` columns (Phase 2) vs. full profile columns (Phase 4) split doesn't require two separate migrations that fight each other (should be additive, not destructive).

---
