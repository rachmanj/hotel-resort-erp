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

## Recently Completed

- `[done] P0: Resolve all 15 stakeholder Open Questions in docs/plan.md; rewrite plan for multi-property + multi-currency + spatie architecture [docs/plan.md Sections 1-11, .cursorrules Auth/Multi-property/Multi-currency lines] (completed: 2026-07-25)`

## Quick Notes

- Plan is v2.0 as of 2026-07-25 — all Open Questions decided, no remaining blockers before Phase 1 migrations. See docs/decisions.md for the full decision record and MEMORY.md [M001] for the summary.
```
