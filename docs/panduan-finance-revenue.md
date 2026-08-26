# Panduan Singkat — Revenue & Dive Center (Pratasaba ERP)

> Untuk tim Finance. Menjelaskan modul pendapatan hasil integrasi laporan PAA-26 ke ERP.
> Update: 26 Agustus 2026.

## 1. Revenue Report (Laporan Pendapatan Bulanan)

**Lokasi:** menu **Reports → Revenue** (`/reports/revenue`)

Ini pengganti laporan PAA-26 manual. Fitur:
- **Pilih bulan** (month picker) → tampil ringkasan per kategori + rincian per hari.
- **Badge "Imported"** (biru) = bulan dari data historis yang di-import (mis. Mei 2026). **Badge "Live"** (hijau) = bulan berjalan yang tercatat langsung dari folio.
- **Export CSV** → tombol export mengunduh file CSV yang bisa dibuka di Excel.

**Cara baca ringkasan:**
- **Total Revenue** = jumlah semua kategori (gross).
- **Discount / OTA Fee / Agent Commission** = ringkasan biaya (untuk sheet "Biaya").

## 2. Revenue Categories (Kategori Pendapatan)

**Lokasi:** **Administration → Revenue Categories** (`/admin/revenue-categories`)

16 kategori pendapatan (sesuai kolom laporan):
| Kode | Nama | Akun COA |
|---|---|---|
| room_seroja | Seroja (Suite) | 4-1300 |
| room_kasilasa | Kasilasa (Grand Deluxe) | 4-1400 |
| room_seheku | Seheku (Deluxe) | 4-1200 |
| room_janti | Janti (Standard) | 4-1100 |
| dive_center | Pratasaba Dive Center | 4-4300 |
| boat | Boat / Rental Speedboat | 4-4310 |
| laundry | Laundry | 4-1600 |
| transport_car | Car Maratua / Shuttle Berau | 4-4700 |
| transport_motor | Motor / Sepeda | 4-4700 |
| meeting | Meeting Packages | 4-4800 |
| resto | Saba Resto | 4-2100 |
| coffee | Prata Coffee | 4-2500 |
| merchandise | Merchandise | 4-4400 |
| showcase | Showcase | 4-4500 |
| tiket_pantai | Tiket Pantai | 4-4600 |
| lain_lain | Lain-lain | 4-9000 |

- **Sort Order** menentukan urutan kolom di laporan.
- Kategori bisa ditambah/diubah via tombol **New Revenue Category** / **Edit**.

## 3. Dive Center

**Lokasi:** menu **Dive Center** (3 sub-menu)

### 3a. Dive Packages (Katalog Harga Dive)
`/admin/dive-packages` — jenis kegiatan diving + harga per orang:
- Dive Package (Solo) Rp 2.000.000 · (Group, min 2) Rp 1.500.000
- Night Dive Rp 600.000
- Discover Scuba (Guest Stay) Rp 800.000 · (Visitor) Rp 1.000.000

### 3b. Boat Units (Armada Boat)
`/admin/boat-units` — daftar boat:
- Small Boat (40 PK, 3 orang) · Medium Boat (200 PK, 12 orang)
- Kolom **Own/Vendor** menandai boat milik Prata vs vendor pihak ke-3.

### 3c. Boat Charters (Booking Trip)
`/admin/boat-charters` — pencatatan trip boat (diving / non-diving):
1. Klik **New Boat Charter**, isi: boat, destinasi, tanggal, tipe (Diving/Trip), harga, jumlah pax, guide (Employee/Freelance) + fee, BBM (liter + biaya).
2. **Bill** → memindahkan charge ke folio tamu (otomatis masuk kategori `dive_center` atau `boat`).
3. Charter yang sudah di-bill tidak bisa dihapus.

## 4. OTA Fees (Fee Reservasi Online/Offline)

**Lokasi:** **Administration → OTA Fees** (`/admin/ota-fees`)

| Nama | Tipe | Nilai |
|---|---|---|
| Traveloka | Persen | 19% |
| Tiket.com | Persen | 17% + 10% variabel |
| Marketing Non-Agent | Flat | Rp 100.000 / room / malam |

- Fee **dihitung otomatis saat checkout** untuk reservasi dengan source OTA.
- Reservasi source OTA wajib memilih OTA-nya (dropdown "OTA" di form reservasi).

## 5. Room Types (Tipe Kamar Villa)

**Lokasi:** menu **Rooms → Room Types** (`/room-types`)

4 tipe villa (nama brand = nama kamar):
| Nama | Tipe | Jumlah |
|---|---|---|
| Seroja | Suite | 6 |
| Kasilasa | Grand Deluxe | 5 |
| Seheku | Deluxe | 21 |
| Janti | Standard | 5 |

- Dimensi tambahan: **Bed Type** (King/Twin) + **View** (Gardenview/Seaview).
- Charge kamar saat check-in **otomatis** masuk kategori villa yang sesuai.

## 6. Rekonsiliasi (angka kunci Mei 2026)

| Item | Nominal |
|---|---|
| Gross (jumlah kategori) | Rp 1.074.800.050 |
| − Deduction | Rp 139.169.800 |
| **Total Penjualan Resort** | **Rp 935.630.250** |
| − Bayar Vendor/Guide | Rp 44.246.500 |
| **Total "Biaya" (net)** | **Rp 891.383.750** |

## 7. Alur Kerja Harian (ringkas)

1. **Reservasi** → pilih source (Walk-in / OTA / Agent / dst). Source OTA wajib pilih OTA-nya.
2. **Check-in** → charge kamar otomatis masuk kategori villa.
3. **Dive/trip** → catat di Boat Charters → klik **Bill** → masuk folio tamu.
4. **Checkout** → OTA fee + komisi agen terakrual otomatis.
5. **Akhir bulan** → buka Reports → Revenue, pilih bulan, export CSV.

## Catatan

- Import data historis saat ini via CSV (Dea konversi XLSX→CSV). Upload XLSX langsung = enhancement berikutnya.
- Kategori **Uncategorized** muncul bila ada charge yang belum dipetakan ke kategori (mis. F&B belum otomatis ke resto/coffee).
