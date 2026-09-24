# Audit `setData({ ... })` — Inertia v3 (Pratasaba ERP)

Status audit: **selesai, tidak ada sisa pemakaian yang berpotensi kehilangan data** (diverifikasi ulang 24 Sep 2026 dengan skrip pemeriksa, bukan pembacaan manual).

## 1. Kenapa ini penting

Di Inertia v3 React, `form.setData({ key: value })` **mengganti seluruh isi data form** dengan objek yang dikirim. Hanya bentuk dua argumen `form.setData('key', value)` yang menimpa satu field. Ini terkonfirmasi dari kode paketnya: `node_modules/@inertiajs/react/dist/index.js` cabang objek langsung memanggil `commitData(keyOrData)` tanpa merge.

Insiden nyata (22 Sep 2026, modal Add Charge folio): handler `onChargeDivePackageChange` dan `onChargeDiveRouteChange` mengirim objek parsial, sehingga:

- memilih **Boat Route** menghapus **Dive Package** yang sudah dipilih, kolom route/boat ikut hilang;
- **Quantity** dan **Description** ikut terhapus saat memilih paket, dan efek lanjutannya tampil sebagai total **"Rp NaN"** di pratinjau (nilainya kosong, bukan salah rumus).

Kedua handler sudah diperbaiki (commit `b2b3062`) dan alurnya sudah diverifikasi ulang di produksi: pilih paket -> pilih rute -> pilih boat -> ubah qty, semua pilihan tetap utuh.

Insiden serupa (24 Sep 2026, modal Add Charge folio): `applyGuideChargePricing` dan `applyRateItemPricing` mengirim objek parsial tanpa spread, sehingga memilih **Guide (additional)** mengosongkan **Charge type**, selector **Billing unit** tidak muncul, dan harga **Per day** tidak terkunci; pemilihan rental/boat class/qty juga menghapus `charge_item_group` dan field guide. Keduanya diperbaiki dengan `...chargeForm.data` (sama seperti handler dive).

## 2. Aturan

Benar untuk update sebagian field:

```tsx
/** satu field */
form.setData('quantity', 2);

/** beberapa field, sisanya dipertahankan */
form.setData({ ...form.data, dive_package_id: id, dive_route_label: null });
```

Hindari (kecuali memang reset penuh yang disengaja, mis. `openChargeModal` / ganti tipe charge di `onChargeItemGroupChange`):

```tsx
form.setData({ dive_route_label: label }); // field lain hilang
```

## 3. Hasil audit

Total **49 panggilan** `setData({ ... })` di `resources/js`:

- **9 panggilan merge aman** (memakai spread `...form.data`) — daftar di bagian 4;
- **40 panggilan reset penuh** yang disengaja (`openCreate`/`openEdit`/buka modal / ganti tipe charge: seluruh key form `useForm` dikirim ulang) — daftar di bagian 5;
- **0 panggilan yang kehilangan field**.

Cara memeriksa ulang cepat: cari `setData({` lalu pastikan objeknya memuat **semua** key `useForm` pada berkas itu, atau memakai `...form.data`.

## 4. Merge aman (pola yang benar)

| Berkas | Baris | Handler | Isi objek |
|--------|-------|---------|-----------|
| `Pages/Folios/Show.tsx` | 240, 248 | `applyGuideChargePricing` | 4 key + spread `...chargeForm.data` |
| `Pages/Folios/Show.tsx` | 274 | `applyRateItemPricing` | 4 key + spread `...chargeForm.data` |
| `Pages/Folios/Show.tsx` | 305 | `onChargeItemGroupChange` (cabang `car_rental`) | 3 key + spread `...chargeForm.data` |
| `Pages/Folios/Show.tsx` | 315 | `onDailyTripDestinationChange` | 4 key + spread `...chargeForm.data` |
| `Pages/Folios/Show.tsx` | 369 | `onChargeDivePackageChange` | 6 key + spread `...chargeForm.data` |
| `Pages/Folios/Show.tsx` | 389 | `onChargeDiveRouteChange` | 2 key + spread `...chargeForm.data` |
| `Pages/Reservations/Create.tsx` | 197 | `submit` | 3 key + spread `...form.data` |
| `Pages/Reservations/Edit.tsx` | 202 | `submit` | 3 key + spread `...form.data` |

Panggilan `setData('field', value)` satu argumen (mis. qty, `rate_item_id`, deskripsi dive) tidak masuk tabel ini; mereka aman secara definisi.

## 5. Reset penuh (disengaja, aman)

Angka di kolom terakhir = jumlah key yang dikirim pada panggilan itu; semuanya sama dengan jumlah key `useForm`, jadi tidak ada field yang tersisa kosong.

| Berkas | Panggilan | Baris dan handler (jumlah key) |
|--------|-----------|--------------------------------|
| `Pages/Accounting/Departments/Index.tsx` | 1 | 48 `DepartmentsIndex` (2 key) |
| `Pages/Admin/AgentTierRates/Index.tsx` | 2 | 73 `openCreate` (6 key); 86 `openEdit` (6 key) |
| `Pages/Admin/Agents/Index.tsx` | 2 | 78 `openCreate` (16 key); 101 `openEdit` (16 key) |
| `Pages/Admin/Agents/Rates.tsx` | 2 | 49 `openCreate` (8 key); 64 `openEdit` (8 key) |
| `Pages/Admin/BoatCharters/Index.tsx` | 2 | 113 `openCreate` (15 key); 135 `openEdit` (15 key) |
| `Pages/Admin/BoatUnits/Index.tsx` | 2 | 40 `openCreate` (6 key); 53 `openEdit` (6 key) |
| `Pages/Admin/Currencies/Index.tsx` | 1 | 41 `openRateModal` (2 key) |
| `Pages/Admin/DivePackages/Index.tsx` | 2 | 53 `openCreate` (7 key); 67 `openEdit` (7 key) |
| `Pages/Admin/OtaFees/Index.tsx` | 2 | 48 `openCreate` (7 key); 62 `openEdit` (7 key) |
| `Pages/Admin/Promotions/Index.tsx` | 2 | 105 `openCreate` (0 key); 114 `openCreate` (17 key) |
| `Pages/Admin/RatePlans/Index.tsx` | 2 | 52 `openCreate` (6 key); 65 `openEdit` (6 key) |
| `Pages/Admin/RevenueCategories/Index.tsx` | 2 | 41 `openCreate` (5 key); 53 `openEdit` (5 key) |
| `Pages/Admin/Roles/Index.tsx` | 2 | 55 `openCreate` (2 key); 61 `openEdit` (2 key) |
| `Pages/Admin/Seasons/Index.tsx` | 2 | 35 `openCreate` (3 key); 41 `openEdit` (3 key) |
| `Pages/Admin/TaxRules/Index.tsx` | 1 | 42 `openEdit` (5 key) |
| `Pages/Admin/Users/Index.tsx` | 2 | 41 `openCreate` (5 key); 53 `openEdit` (5 key) |
| `Pages/FB/Menu/Index.tsx` | 1 | 60 `openEdit` (5 key) |
| `Pages/Floors/Index.tsx` | 2 | 31 `openCreate` (2 key); 37 `openEdit` (2 key) |
| `Pages/Folios/Show.tsx` | 2 | 216 `openChargeModal` (11 key); 285 `onChargeItemGroupChange` (11 key, reset awal saat ganti tipe charge) |
| `Pages/RoomTypes/Index.tsx` | 2 | 60 `openCreate` (8 key); 75 `openEdit` (8 key) |
| `Pages/Rooms/Index.tsx` | 2 | 60 `openCreate` (4 key); 71 `openEdit` (4 key) |
| `Pages/Spa/Therapists/Index.tsx` | 1 | 40 `openEdit` (3 key) |
| `Pages/Spa/Treatments/Index.tsx` | 1 | 42 `openEdit` (4 key) |

## 6. Rekomendasi lanjutan

1. Untuk update bertahap, biasakan `setData('field', value)` (satu field) atau spread `...form.data` (beberapa field). Pola objek parsial tanpa spread hanya untuk reset penuh.
2. Opsional: tambahkan helper bersama, mis. `mergeFormData(form, partial)` di `resources/js/lib`, supaya polanya konsisten dan mudah ditinjau.
3. Checklist saat meninjau form baru: (a) apakah handler bertingkat (pilih A -> pilih B) yang memanggil `setData`? (b) kalau ya, pastikan spread; (c) uji alur **sampai langkah terakhir**, karena bug kelas ini baru muncul di langkah kedua, bukan di langkah pertama.
4. Saat menguji form di produksi, periksa juga bahwa field lain (quantity, deskripsi, kategori) tidak ikut kosong setelah memilih opsi bertingkat.

## 7. Skrip pemeriksa

Pemeriksaan dilakukan dengan skrip Python yang membaca setiap `*.tsx` di `resources/js`, mengambil key dari `useForm({...})`, mengambil key dari objek `setData`, lalu melaporkan key yang hilang. Skrip ada di `docs/audit-setdata.py` (jalankan dari root repo: `python3 docs/audit-setdata.py`); ia mencetak ringkasan dan menyimpan detail per panggilan ke `/tmp/setdata_audit.json`. Perbarui daftar di dokumen ini bila ada form baru.
