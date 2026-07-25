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
