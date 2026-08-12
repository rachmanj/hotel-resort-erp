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

- `[done] P0: Resequence docs/plan.md Section 9 so Room Reservation (Phase 2) + Telegram Bot (Phase 3) ship before Billing/Housekeeping; split Telegram command rollout across Phases 3-9a [docs/plan.md Sections 6.2, 6.3, 6.3 (new), 6.4, 9, 11.3] (completed: 2026-07-26)`
- `[done] P0: Resolve all 15 stakeholder Open Questions in docs/plan.md; rewrite plan for multi-property + multi-currency + spatie architecture [docs/plan.md Sections 1-11, .cursorrules Auth/Multi-property/Multi-currency lines] (completed: 2026-07-25)`

## Quick Notes

- Plan is v2.1 as of 2026-07-26 — phases resequenced so Room Reservation + Telegram (core differentiator) land by Phase 3; Telegram bot now ships in 5 incremental slices (Phases 3/4/5/6/7, plus 8/9a for accounting/reporting commands) instead of one monolithic phase. See docs/decisions.md for the full decision record and MEMORY.md [M002] for the summary.
```
