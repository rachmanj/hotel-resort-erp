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

[Add new memory entries below, following the format above]
