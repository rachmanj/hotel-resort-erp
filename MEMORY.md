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

[Add new memory entries below, following the format above]
