# Spec — Contract Rate per Kategori Agen (Tier A/B/C/D)

Sumber data: `26.Master Contract Rate.xlsx` (dikirim Iwan, 18 Sep 2026). Data agen dipindahkan ke `database/data/pratasaba_agents_2026.csv`.

## Keputusan desain

1. **Kategori agen = tier**, bukan harga per agen. Agen diberi field `rate_category` (A/B/C/D); harga kontrak disimpan per tier, bukan per agen. Alasan: 44 agen × 13 room type = 572 baris kalau per agen; per tier hanya 4 × jumlah room type yang ikut kontrak, dan sekali ubah langsung berlaku ke semua agen di tier itu.
2. **Override per agen tetap ada.** Tabel `agent_rates` yang sudah ada dipakai untuk negosiasi khusus per agen dan **menang** atas harga tier.
3. **Urutan pencarian harga** saat reservasi: override agen → harga tier → harga normal (base rate / rate plan). Kalau tidak ada sama sekali, pakai harga biasa.
4. **Kolom Publish tidak diinput ulang.** Publish di file = `room_types.base_rate` yang sudah ada:
   - Saroja Suite = Suite (STKS/STTS) = 2.690.000
   - Kasilasa Grand Deluxe = Grand Deluxe (GDKS/GDTS) = 2.090.000
   - Seheku Deluxe = Deluxe (DLTG/DLKS/DLKS 06/DLKG/DLTS) = 1.690.000
5. **Harga kontrak 2026** yang dipakai (per kamar per malam):

   | Kategori kamar | Publish | Tier A | Tier B | Tier C | Tier D |
   | --- | --- | --- | --- | --- | --- |
   | Suite (Saroja Suite) | 2.690.000 | 2.100.000 | 2.200.000 | 2.500.000 | 2.590.000 |
   | Grand Deluxe (Kasilasa) | 2.090.000 | 1.650.000 | 1.750.000 | 1.900.000 | 1.990.000 |
   | Deluxe (Seheku) | 1.690.000 | 1.300.000 | 1.400.000 | 1.500.000 | 1.590.000 |

6. **Komisi agen di-set 0 saat import** (belum ada keputusan nett vs komisi). Kalau nanti ternyata masih ada komisi, tinggal diisi per agen — tidak akan ada potongan dobel karena defaultnya 0.
7. **Kategori agen bersifat internal.** Definisi tier di file sumber menyebut "bukan informasi eksternal ke klien" → tier **tidak ditampilkan** di portal agen, hanya di halaman admin.
8. **"Trans Borneo" duplikat** (baris 21 PIC Purwanti dan baris 41 PIC Mr. Joko, dua-duanya tier A). Untuk import diambil **satu** (Purwanti, data lebih lengkap); PIC Mr. Joko dicatat sebagai temuan untuk dikonfirmasi ke tim.

## Masih menunggu jawaban tim (tidak menghalangi implementasi)

1. Harga kontrak itu **nett** atau masih dipotong komisi.
2. **Pajak 11% + service 10%** sudah termasuk di angka itu atau belum (kalau sudah termasuk, harga tier perlu diperlakukan sebagai harga final — saat ini aplikasi menambahkan pajak & service di atas nightly rate).
3. **Tanggal berlaku** kontrak 2026 (file hanya menyebut tahun).
4. Harga **per kamar** atau **per orang**; termasuk breakfast atau tidak.
5. Kamar **villa** (Seroja 1.500.000 / Kasilasa 1.200.000 / Seheku 850.000 / Janti 600.000) apakah juga dijual ke agen dengan harga tier ini.
6. High season / surcharge, minimum stay, extra bed & anak, term of payment per tier.

## Tahapan implementasi

- **Fase 1 (backend)**: migrasi `agents.rate_category` + tabel `agent_tier_rates`, enum `AgentRateCategory`, model `AgentTierRate`, `AgentRateService` (override agen → tier), CRUD admin + validasi, test.
- **Fase 2 (frontend)**: field tier di halaman Agen, halaman matriks "Contract Rate per Kategori" (baris = kategori kamar, kolom = tier A–D), menu admin.
- **Fase 3 (data)**: perintah import 44 agen dari CSV (dedupe + tier + komisi 0) dan seed harga kontrak 2026 untuk room type kategori Suite / Grand Deluxe / Deluxe.

## Status implementasi (18 Sep 2026 — sudah live)

- **Fase 1 & 2 (kode)**: `agents.rate_category` (A–D) + tabel `agent_tier_rates`, enum `AgentRateCategory`, `AgentRateService` dengan urutan override agen → harga tier → harga normal, CRUD `/admin/agent-tier-rates` + endpoint bulk `agent-tier-rates/bulk`, kolom & pilihan Tier di halaman Agen, halaman matriks Contract Rate. Commit `8d7fa34`, `7d2a930`, `1a819f4`.
- **Fase 3 (data)**: perintah `php artisan agents:import-contract` (ada `--dry-run`, idempotent). Sudah dijalankan di produksi: **43 agen** masuk (44 baris CSV dikurangi 1 duplikat "Trans Borneo"; total agen di DB 45 termasuk 2 agen test lama), **36 baris harga tier** (4 tier × 9 room type kategori Suite / Grand Deluxe / Deluxe), periode 2026-01-01 s.d. 2026-12-31.
- **Verifikasi live di produksi**: agen tier A + kamar STKS (Suite) = 2.100.000; agen tier C + DLTS (Deluxe) = 1.500.000; tanggal di luar 2026 = null (jatuh ke harga normal); agen tanpa tier = null. Suite test 94 test hijau.
- **Belum diinput**: kamar villa (SRJ/KSL/SHK/JNT) — menunggu jawaban tim apakah villa ikut harga tier (lihat daftar pertanyaan).

### Catatan operasional

- Semua agen hasil import **komisi = 0%**. Setelah tim memastikan nett/komisi, isi per agen lewat halaman Agen (kalau nett, biarkan 0 supaya tidak potong dobel).
- Kalau harga kontrak berubah: buka `/admin/agent-tier-rates`, isi matriks (tanggal berlaku + harga per tier), simpan sekali — langsung berlaku untuk semua agen di tier tersebut.
