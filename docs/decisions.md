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
