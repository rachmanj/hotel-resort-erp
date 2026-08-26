# Spec: Revenue Integration — Pratasaba ERP (v2)

> **Status:** CONFIRMED — semua open questions sudah dijawab tim Pratasaba (email 26 Aug 2026).
> **Author:** Dea (PM) — arahan Iwan.

---

## 1. Goal

Pratasaba ERP jadi **single source of truth** pendapatan resort: generate **laporan pendapatan bulanan format PAA-26 secara native** + import data historis. **End goal (Q25): Accurate tidak lagi dipakai** — ERP mengcover seluruh kebutuhan data keuangan.

## 2. Model Bisnis Terkonfirmasi (dari tim)

### 2.1 Room Types (nama kamar = brand, tipe = kelas)
| Nama | Tipe | Jumlah kamar |
|---|---|---|
| Seroja | Suite | 6 |
| Kasilasa | Grand Deluxe | 5 |
| Seheku | Deluxe | 21 |
| Janti | Standard | 5 |

- **Bed type**: King Size / Twin Bed
- **View**: Gardenview / Seaview (Seheku Deluxe punya 2 pilihan view)
- **Harga**: per kamar per tipe (bukan villa). Rate types: **Publish, Corporate, Agent (beberapa kategori), OTA, Marketing non-Agent Resmi**
- **Unit lain**: Meeting room 2 (Kakaban Small Meeting, Pratasaba Meeting Hall), Mess Karyawan 2 (Mess, Mess Dasimin), **20 kamar baru** (buka akhir Sep/awal Okt, belum nama & harga).

### 2.2 Revenue Categories (12 utama + Lain-lain)
1. Room · 2. Meeting Packages · 3. Saba Resto · 4. Prata Coffee · 5. Pratasaba Dive Center · 6. Rental Mobil · 7. Rental Motor · 8. Rental Speedboat · 9. Showcase · 10. Merchandise · 11. Tiket Pantai · 12. Laundry
- **Lain-lain** = mayoritas vendor pihak ke-3: fee massage, additional guide, tari Dalling, charge listrik, rental underwater camera, charge barang pecah, dll.
- **Showcase** = minuman kemasan (stok barang dagangan) + titipan snack UMKM (dicatat hanya yang terjual).
- **Tiket Pantai** = visitor tidak menginap.
- **Boat (kolom laporan)** = Rental Speedboat Prata untuk trip. **Dive Center** = kegiatan diving (termasuk speedboat untuk diving).
- **Rental boat via vendor ke-3** → pendapatan dicatat sebagai "Travydoor" (bukan Boat Prata).

### 2.3 Biaya / Diskon (kritis — sudah jelas)
| Item | Arti |
|---|---|
| **Sheet "Penjualan Resort" Total = 935jt** | Pendapatan − Discount |
| **Sheet "Biaya" Total = 891jt** | Pendapatan − Discount − Vendor |
| **Deduction** (per kategori) | diskon / fee / compliment + biaya vendor |
| **Discount accurate** | nilai akun "Discount" di Accurate (crosscheck) |
| **Fee Reservation Online** | OTA: Traveloka **19%**, Tiket.com **17%** (+10% variabel program) |
| **Fee Reservation Offline** | marketing non-agent resmi, Rp 100.000/room/malam (Mei = 0) |
| **Bayar Vendor/Guide** | total biaya vendor (guide, rental vendor, snack diving, dll) |
| **BBM** | beli masuk akun "Fuel"; pakai dicatat buku stok BBM (CME); BBM boat diving/rental → COGS manual di Excel |

### 2.4 Dive Center
- **1 karyawan Dive Guide**; sisanya freelance by order.
- **Price list** (lampiran): Dive Package Solo 2jt/hari (3x dive), Group (min 2) 1.5jt; Night Dive 600rb; Discover Scuba (Guest Stay) 800rb solo / 1.2jt group; Discover Scuba (Visitor) 1jt/1.5jt; Rental equipment per hari (wetsuit/BCD/regulator/mask/booties 100rb, full 500rb); Boat dive rent per destinasi 2.5jt–3.5jt.
- **Daily trip** (boat non-diving): Small boat 40Pk 2jt–3.2jt, Medium 200Pk 2.5jt–10.5jt per destinasi (Kakaban, Sangalaki, Derawan, Talisayan whaleshark, dll); Additional guide 600rb.

### 2.5 Invoice & Workflow
- **No invoice `26900309`** = Sales Invoice Accurate: `26`(tahun) `9`(kode Pratasaba Resort) `00309`(urut/00001 per tahun).
- **Workflow sekarang**: Receptionist/Kasir isi form (Captain Order CO Umum & CO Prata Coffee, Rent Receipt, Laundry Form) → input Excel "PENCATATAN" & "PETTY CASH" → scan + upload G-drive tiap akhir hari → HO input Accurate H+1 → laporan tgl 2 ke Director.
- **Accurate diganti ERP** bila ERP cover semua kebutuhan.

## 3. Scope

### IN (fase berurutan)
1. Revenue Categories + COA re-alignment + FolioItem mapping.
2. Room Types (Seroja/Kasilasa/Seheku/Janti + bed/view dimensi) + rate types.
3. Dive Center module (packages, boat units, charter, guide, BBM COGS).
4. OTA fee & commission engine (Traveloka 19%, Tiket.com 17%+variabel, offline fee).
5. Revenue Report generator (PAA-26) + export + breakdown per kategori.
6. Import history (Accurate → ERP).

### OUT (deferred)
- Modul pengganti full Accurate non-revenue (PO, payroll, pajak, dll) — proyek terpisah.
- Integrasi channel manager OTA otomatis.

## 4. Tech Decisions
| Decision | Choice |
|---|---|
| Revenue category | Table `revenue_categories` + `coa_account_code` + sort |
| FolioItem mapping | `folio_items.revenue_category_id` nullable FK |
| Room type | `RoomType` (Seroja/Kasilasa/Seheku/Janti) + `bed_type` & `view` kolom |
| Dive | `dive_packages`, `boat_units`, `boat_charters` + folio charge |
| OTA fee | `ota_fees` table (OTA, base %, variable %, program) dihitung saat posting |
| Report | query folio_items grouped category + period, render PAA-26 |
| Import | artisan `import:revenue` (PhpSpreadsheet — butuh approval dep) |

## 5. DB Changes
- `revenue_categories` — id, hotel_id, code, name, coa_account_code, sort_order, is_active
- `room_types` add `bed_type`, `view` (nullable); seed 4 tipe + bed/view
- `dive_packages` — id, hotel_id, name, type, price_per_dive/pax, min_pax, is_active
- `boat_units` — id, hotel_id, name, capacity, engine_hp, is_own (Prata vs vendor)
- `boat_charters` — id, folio_id?, reservation_id?, boat_unit_id, trip_date, destination, price, bbm_liters, bbm_cost, guide_fee, guide_type (employee/freelance), status
- `ota_fees` — id, ota_name, base_fee_pct, variable_fee_pct, is_active
- `revenue_imports` — id, period, file, gross_total, net_total, status
- COA seeder: tambah akun revenue diving/boat/coffee/extras; rooms → 4 tipe

## 6. UI/UX
- Admin → Revenue Categories (CRUD + COA map + sort)
- Admin → Room Types (Seroja/Kasilasa/Seheku/Janti + bed/view)
- Dive Center → Packages, Boat Units, Charters (guide + BBM)
- Admin → OTA Fees
- Reports → Revenue (PAA-26) + Diving + breakdown; export XLSX/CSV

## 7. API Endpoints
- `GET /reports/revenue?month=` · `GET /reports/revenue/export` · `GET /reports/diving`
- CRUD: `/admin/revenue-categories`, `/admin/ota-fees`, `/dive/packages`, `/dive/boat-units`, `/dive/boat-charters`
- `POST /imports/revenue` (atau artisan `import:revenue`)

## 8. Risks
1. **20 kamar baru** belum nama/harga — sisipkan saat dibuka (jangan block sekarang).
2. **Travydoor** (boat vendor ke-3) — revenue pass-through, perlu dipahami alurnya.
3. **BBM COGS** masih manual — perlu buku stok BBM di ERP atau biarkan manual dulu.
4. **OTA fee variabel** Tiket.com (program cross-selling/gold member) — model harus fleksibel.
5. **Scope**: jangan melebar ke full Accurate replacement (proyek terpisah).

## 9. Phased Plan
1. **Phase 0** — Revenue Categories + COA re-alignment (seeder).
2. **Phase 1** — Room Types (4 tipe + bed/view) + rate types.
3. **Phase 2** — Dive Center module.
4. **Phase 3** — OTA fee & commission engine.
5. **Phase 4** — Revenue Report generator (PAA-26) + export.
6. **Phase 5** — Import history (Mei 2026 dst).
7. **Phase 6** — QA + anti-slop + deploy + training Finance.
