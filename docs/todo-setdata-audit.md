# Inertia `setData({ ... })` audit (partial object calls)

In Inertia v3 React, `form.setData({ key: value })` **replaces** the entire form data object. Only `setData('key', value)` or `setData({ ...form.data, key: value })` preserves other keys.

Scope: `resources/js` — object-form `setData` / destructured `setData` calls found via search for `setData({`.

**Legend**

- **No loss** — every key in `useForm` initial state is set in the object (intentional full replace, e.g. open create/edit).
- **Would lose fields** — partial object during an in-progress form / chained handlers; unrelated keys are dropped.

| File | Line | Would lose unrelated fields? | Notes |
|------|------|------------------------------|-------|
| `Pages/Folios/Show.tsx` | 157 | No | `openChargeModal`: full charge form reset (all 7 fields). |
| `Pages/Folios/Show.tsx` | 194 | **Yes (fixed)** | `onChargeDivePackageChange`: was partial; cleared quantity, description, revenue, unit_price, etc. |
| `Pages/Folios/Show.tsx` | 210 | **Yes (fixed)** | `onChargeDiveRouteChange`: was partial; cleared dive package and other charge fields. |
| `Pages/Admin/BoatCharters/Index.tsx` | 113 | No | `openCreate`: all form keys set. |
| `Pages/Admin/BoatCharters/Index.tsx` | 135 | No | `openEdit`: all form keys set. |
| `Pages/FB/Menu/Index.tsx` | 60 | No | `openEdit`: all `editForm` keys set. |
| `Pages/Admin/OtaFees/Index.tsx` | 48 | No | `openCreate`: all form keys set. |
| `Pages/Admin/OtaFees/Index.tsx` | 62 | No | `openEdit`: all form keys set. |
| `Pages/Admin/DivePackages/Index.tsx` | 53 | No | `openCreate`: all form keys set. |
| `Pages/Admin/DivePackages/Index.tsx` | 67 | No | `openEdit`: all form keys set. |
| `Pages/Admin/BoatUnits/Index.tsx` | 40 | No | `openCreate`: all form keys set. |
| `Pages/Admin/BoatUnits/Index.tsx` | 53 | No | `openEdit`: all form keys set. |
| `Pages/Admin/RevenueCategories/Index.tsx` | 41 | No | `openCreate`: all form keys set. |
| `Pages/Admin/RevenueCategories/Index.tsx` | 53 | No | `openEdit`: all form keys set. |
| `Pages/Admin/Users/Index.tsx` | 41 | No | `openCreate`: all form keys set. |
| `Pages/Admin/Users/Index.tsx` | 53 | No | `openEdit`: all form keys set. |
| `Pages/Admin/Roles/Index.tsx` | 55 | No | `openCreate`: both form keys set. |
| `Pages/Admin/Roles/Index.tsx` | 61 | No | `openEdit`: both form keys set. |
| `Pages/Admin/Agents/Rates.tsx` | 49 | No | `openCreate`: all form keys set. |
| `Pages/Admin/Agents/Rates.tsx` | 64 | No | `openEdit`: all form keys set. |
| `Pages/Admin/AgentTierRates/Index.tsx` | 73 | No | `openCreate`: all form keys set. |
| `Pages/Admin/AgentTierRates/Index.tsx` | 86 | No | `openEdit`: all form keys set. |
| `Pages/Admin/Promotions/Index.tsx` | 105 | No | `openCreate`: spreads `defaultForm` (full shape). |
| `Pages/Admin/Promotions/Index.tsx` | 114 | No | `openEdit`: all keys from `defaultForm` set from API. |
| `Pages/Admin/RatePlans/Index.tsx` | 52 | No | `openCreate`: all form keys set. |
| `Pages/Admin/RatePlans/Index.tsx` | 65 | No | `openEdit`: all form keys set. |
| `Pages/Admin/Agents/Index.tsx` | 78 | No | `openCreate`: all form keys set. |
| `Pages/Admin/Agents/Index.tsx` | 101 | No | `openEdit`: all form keys set. |
| `Pages/Reservations/Create.tsx` | 197 | No | `onNewGuest`: already merges `...form.data`. |
| `Pages/Reservations/Edit.tsx` | 202 | No | `onNewGuest`: already merges `...form.data`. |
| `Pages/Accounting/Departments/Index.tsx` | 48 | No | `editForm` only has `name` and `is_active`; both set. |
| `Pages/RoomTypes/Index.tsx` | 60 | No | `openCreate`: all form keys set. |
| `Pages/RoomTypes/Index.tsx` | 75 | No | `openEdit`: all form keys set. |
| `Pages/Rooms/Index.tsx` | 60 | No | `openCreate`: all form keys set. |
| `Pages/Rooms/Index.tsx` | 71 | No | `openEdit`: all form keys set. |
| `Pages/Accounting/FixedAssets/Index.tsx` | 54 | No | `openEdit`: replaces with all keys on the edit form (same count as `useForm`; `residual_value` forced to `0` by design). |
| `Pages/Floors/Index.tsx` | 31 | No | `openCreate`: both form keys set. |
| `Pages/Floors/Index.tsx` | 37 | No | `openEdit`: both form keys set. |
| `Pages/Spa/Therapists/Index.tsx` | 40 | No | `openEdit`: all `editForm` keys set. |
| `Pages/Spa/Treatments/Index.tsx` | 42 | No | `openEdit`: all `editForm` keys set. |
| `Pages/Admin/TaxRules/Index.tsx` | 42 | No | `openEdit`: all form keys set (edit-only form). |
| `Pages/Admin/Seasons/Index.tsx` | 35 | No | `openCreate`: all form keys set. |
| `Pages/Admin/Seasons/Index.tsx` | 41 | No | `openEdit`: all form keys set. |
| `Pages/Admin/Currencies/Index.tsx` | 41 | No | `openRateModal`: both form keys set. |

## Modules with no object-form `setData`

Searched under `Pages/Folios` (other than `Show.tsx`), `Inventory`, `Purchasing`, and related Accounting pages: no additional `setData({` calls. Purchasing uses single-key `setData('items', ...)`.

## Follow-up ideas

- Prefer single-key `setData` for incremental updates to avoid accidental full replace.
- Optional: extract shared `mergeFormData(form, partial)` helper if this pattern repeats.
