# Spec: Revenue Integration — Pratasaba ERP

> **Status:** Draft (hasil grill-me + analisa PAA-26, menunggu konfirmasi open questions)
> **Date:** 2026-08-23
> **Author:** Dea (PM) — dari arahan Iwan

---

## 1. Goal

Pratasaba ERP jadi **single source of truth** pendapatan resort: mampu **generate laporan pendapatan bulanan format PAA-26 secara native** (folio → kategori → report), plus **import data historis** (Mei 2026 dst) sebagai backfill. Tim finance berhenti maintain Excel/Accurate paralel; Accurate cukup buat pajak/final.

## 2. Scope

### IN
1. **Revenue Categories** — model + CRUD: 16 kategori pendapatan dari laporan dipetakan ke COA account & FolioItem.
2. **COA re-alignment** — tambah revenue accounts yang hilang (diving, boat, F&B outlets Pratasaba, extras), ubah rooms ke villa-based.
3. **Dive Center module** — DivePackage, BoatCharter, guide assignment, BBM. (Diving = ~58% revenue, saat ini nol representasi.)
4. **Revenue Report generator** — halaman + export (XLSX/CSV) format PAA-26 per bulan.
5. **Import history** — artisan command untuk import laporan bulanan lama (Mei 2026 + bulan sebelumnya).

### OUT (deferred)
- Migrasi full accounting dari Accurate (hanya sisi *revenue* saat ini).
- OTA channel manager integration baru (yang ada dipertahankan).
- Tax filing / e-Faktur.

## 3. Tech Decisions

| Decision | Choice | Rationale |
|---|---|---|
| Revenue category modeling | Table `revenue_categories` (bukan enum) + `coa_account_code` FK | 16 kategori, bisa berubah, perlu mapping ke COA & report order |
| FolioItem → category | Tambah kolom `revenue_category_id` nullable FK di `folio_items` | Retain existing `item_type`; category = dimensi report |
| Dive module | Tabel terpisah (`dive_packages`, `boat_charters`, `boat_units`) + charge ke folio sebagai `FolioItem` | Dive punya struktur sendiri (QTY×harga, guide, BBM), tapi tetap settle via folio |
| Room/villa | Tambah Seroja/Kasilasa/Seheku/Janti House sebagai `RoomType` **atau** dimensi `building`/`villa` — **OPEN** (Q1) | Lihat Open Questions |
| Report engine | Query `folio_items` grouped by `revenue_category_id` + period; render PAA-26 layout | Satu source of truth |
| Import | Artisan command `import:revenue` + XLSX reader (PhpSpreadsheet/maatwebsite) — **OPEN** (Q6, butuh approval dep) | Backfill history |
| COA seeding | Extend `ChartOfAccountsSeeder` (idempotent) | Jangan edit DB live manual |

## 4. DB Changes

**New tables:**
- `revenue_categories` — id, hotel_id, code, name, coa_account_code (FK→chart_of_accounts.account_code), sort_order, is_active
- `dive_packages` — id, hotel_id, name, type (DivePackage/DiscoveryScubaDiving), price_per_dive, is_active
- `boat_units` — id, hotel_id, name, capacity, bbm_cost_per_liter
- `boat_charters` — id, hotel_id, folio_id (nullable), reservation_id (nullable), boat_unit_id, trip_date, destination, price, bbm_liters, bbm_cost, guide_fee, status
- `revenue_imports` — id, hotel_id, period (YYYY-MM), file_name, gross_total, net_total, status, imported_by, imported_at

**Altered:**
- `folio_items` — add `revenue_category_id` (nullable FK)
- `chart_of_accounts` — (via seeder) tambah revenue accounts baru
- `room_types` / `rooms` — villa mapping (sesuai Q1)

**COA revenue accounts baru (proposed):**
- `4-4000` Departemen Lain → `4-4300 Diving Revenue`, `4-4310 Boat Charter Revenue`, `4-4320 Dive Guide Service`
- `4-2000` F&B → `4-2500 Prata Coffee Revenue` (atau rename `4-2100` → Resto)
- `4-4000` → `4-4400 Merchandise Revenue`, `4-4500 Showcase Revenue`, `4-4600 Tiket Pantai Revenue`, `4-4700 Transport Revenue (Car/Shuttle/Motor)`, `4-4800 Meeting Package Revenue`
- Room revenue: `4-1100/4-1200/4-1300` (Standard/Deluxe/Suite) → rename/remap ke villa (Seroja/Kasilasa/Seheku/Janti) **OPEN** (Q1)

## 5. UI/UX

- **Admin → Revenue Categories** — CRUD kategori + mapping COA + sort (urutan kolom report).
- **Dive Center** — menu baru: Packages, Boat Units, Boat Charters (booking + guide + BBM), posting ke folio.
- **Reports → Revenue (PAA-26)** — filter bulan, tabel 16 kolom kategori, kolom Total, blok biaya (deduction/discount/fee/vendor), export XLSX/CSV.
- **Reports → Diving** — detail dive per invoice (paket, QTY, harga, discount, guide, BBM).
- Anti-slop: finance-professional theme (teal/emerald), compact numbers (juta/ribu), 38 rules.

## 6. API Endpoints

- `GET /reports/revenue?month=2026-05` — report data (JSON via Inertia props)
- `GET /reports/revenue/export?month=2026-05&format=xlsx|csv`
- `GET /reports/diving?month=2026-05`
- `POST /admin/revenue-categories`, `PUT/PATCH /admin/revenue-categories/{id}`, `DELETE`
- `GET/POST/PUT/PATCH/DELETE /dive/packages`, `/dive/boat-units`, `/dive/boat-charters`
- `POST /imports/revenue` (file upload) atau artisan `php artisan import:revenue --file=...`

## 7. Risks

1. **Taxonomy mismatch** — rooms di COA/ERP = view-based (Standard/Deluxe/Suite), laporan = villa-based (Seroja/Kasilasa/Seheku/Janti). Salah mapping → report tidak reconcile.
2. **Semantik biaya belum jelas** — "deduction" (183jt) vs "discount accurate" (129jt) vs "fee reservation online/offline" (9.8jt) vs "bayar vendor/guide" (44jt) — perlu definisi finance untuk map ke GL/komisi/vendor.
3. **Reconciliation** — kolom kategori (sum ~1.35M) ≠ kolom Total (935jt); selisih ≈ blok biaya. Perlu aturan rekonsiliasi eksplisit.
4. **Scope creep** — godaan menggeser full accounting. Batasi ke revenue.
5. **Dependency** — XLSX parsing butuh library baru (butuh approval AGENTS.md).
6. **Data historis kotor** — nama tamu/OTA tidak konsisten (Traveloka Guest / Tiket.com / Walk-in / UMUM), perlu cleaning saat import.

## 8. Open Questions (butuh Iwan)

- **Q1** — `Seroja/Kasilasa/Seheku/Janti House` itu **villa/bangunan** (1 unit bookable masing-masing) atau **room type** (punya beberapa kamar tiap villa)? Nentuin model RoomType vs dimensi building.
- **Q2** — Definisi tepat `deduction` vs `discount accurate` vs `fee reservation online/offline` vs `bayar vendor/guide`. Map ke apa di GL (discount/komisi/fee OTA/vendor payable)?
- **Q3** — Nomor invoice `26900309...` itu nomor folio ERP atau nomor invoice eksternal (Accurate)? (join key saat import)
- **Q4** — Sumber laporan: Accurate atau manual Excel? (nentuin stabilitas format import)
- **Q5** — Scope import history: cuma Mei 2026, atau Jan–Mei 2026 (dan berapa bulan ke belakang)?

## 9. Phased Plan (proposed)

1. **Phase 0 — Confirm** open questions Q1–Q5 (blocking).
2. **Phase 1 — Revenue Categories + COA** re-alignment (seeder) + FolioItem mapping.
3. **Phase 2 — Dive Center module** (packages, boat units, charters, folio posting).
4. **Phase 3 — Revenue Report generator** (PAA-26 native) + export.
5. **Phase 4 — Import history** (artisan command + reconciliation).
6. **Phase 5 — QA + anti-slop audit + deploy + training finance.**
