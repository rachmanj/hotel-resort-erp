# Bank Book Juli 2026 — Analisa Struktur (Accurate → Pratasaba ERP)

**Sumber:** Email Ibu Sarina (acc.pratasaba@gmail.com), lampiran `26.07.Bank Book Resort.xls` (Bank Book Accurate periode 01–31 Juli 2026).
**Tujuan:** Memahami struktur akun & pola transaksi aktual Pratasaba sebagai dasar memetakan/mirror struktur Accurate ke ERP (agar ERP bisa menggantikan Accurate).

---

## 1. Ringkasan Eksekutif

Bank Book Juli 2026 berisi **8 rekening** di bawah **2 badan usaha**:

| Entity | Rekening | Sifat |
|---|---|---|
| **Pratasaba Resort** | BCA-756, Mandiri-557, Mandiri-399 (Berau) | Bank operasional |
| | PC Office Berau, PC Resort | Petty cash |
| **TravyDoor Tour** | BNI-556, Mandiri-995 | Bank operasional |
| | PC Balikpapan | Petty cash |

- Data ini **cash-basis** (mencatat kapan uang masuk/keluar), bukan accrual. DP booking + pelunasan checkout tercatat sebagai 2 entri terpisah.
- Travel agent aktif terlihat: **Supri Travel, HV Trip**; promo aktif: **"Sun, Sea & School Break Promo"**.
- Offline OTA fee **"Fee 100rb/Ns/Room"** terkonfirmasi muncul di data riil (cocok dengan fee Marketing non-agent yang sudah kita set Rp 100.000/room/malam).

---

## 2. Struktur Akun (8 rekening)

| Sheet | Entity | Nomor Akun | Nama |
|---|---|---|---|
| BCA-756 | Pratasaba Resort | 1010210 | BCA IDR - 781 0285758 |
| Mandiri-557 | Pratasaba Resort | 1010207 | Mandiri IDR - 149 0075575557 |
| Mandiri-399 (Berau) | Pratasaba Resort | 1010202 | Mandiri IDR - 148 0013895399 |
| PC Office Berau | Pratasaba Resort | 1010113 | PC Office Berau |
| PC Resort | Pratasaba Resort | 1010112 | PC Resort |
| BNI-556 | TravyDoor Tour | 1010209 | BNI IDR - 595 7585756 |
| Mandiri-995 | TravyDoor Tour | 1010208 | Mandiri IDR - 149 0099595995 |
| PC Balikpapan | TravyDoor Tour | 1010114 | PC Balikpapan |

**Catatan:** kode akun Accurate `101xxxx` = Kas & Bank. PC (petty cash) diisi ulang lewat voucher `JVC` "Tarik Dana utk Belanja GOL" / "Transfer Dana PC ...".

---

## 3. Jenis Dokumen Transaksi (kode Accurate)

| Kode | Nama | Makna | Padanan ERP |
|---|---|---|---|
| BKM | Bukti Kas Masuk | Penerimaan kas (DP, pelunasan, penjualan) | Folio charge / payment (debit kas) |
| BKK | Bukti Kas Keluar | Pengeluaran kas | Payment / expense (kredit kas) |
| PMT | Payment | Pembayaran via bank | Payment (kredit kas) |
| JVC | Journal Voucher Cash | Transfer antar rekening (isi PC, pinbuk) | Transfer/journal antar akun kas |
| RFN | Refund | Pengembalian | Refund/negative posting |
| RTN | Return/Retur | Retur/pengembalian titipan | Credit note |
| DEP | Deposit (bunga) | Bunga bank | Interest income |
| ADJ | Adjustment | Koreksi | Adjustment journal |

---

## 4. GOL Cost Center (kode biaya departemen)

Accurate memakai kode **GOL.xxxxx** + suffix departemen untuk tracking biaya per cost center. Ini belum ada padanannya di ERP.

| Suffix | Departemen | Rentang GOL | Contoh biaya |
|---|---|---|---|
| Kitc/KIT | Kitchen & Resto | 26069–26087 | Bahan baku, gas LPG, supply |
| FO | Front Office | 26143, 26165–26203 | Guest supplies, napkin, laundry linen |
| HK | Housekeeping | 26147, 26169–26206 | Galon, parfum laundry, amenity |
| HO | Head Office | 26167, 26177, 26197, 26206 | Merchandise T-shirt, brosur, hanger |
| CME | CME (teknik/maintenance) | 24402, 26113–26199 | Pompa air, sparepart, relay |
| Civi/CIV | Civil (proyek C5) | 26025–26039 | Hollow plafon, keramik, upah carpenter |
| BAR | Bar | 26163, 26180 | Biji kopi, kebutuhan bar |
| DRV | Driver | 26004 | Sparepart Innova |
| MKT | Marketing | 26003 | Cetak brosur, konten medsos |

---

## 5. Pola Pendapatan (BKM — kas masuk, cash-basis)

Distribusi kasar (total BKM ≈ Rp 1,38 M; angka *cash-basis*, DP+pelunasan terpisah):

| Stream | Porsi | Catatan |
|---|---|---|
| Room (DP + pelunasan) | ~84% | Deskripsi "ci. [tgl] co. [tgl] - N saroja/kasilasa/seheku/janti" |
| Penjualan F&B/misc | ~11% | "Penjualan tgl …" (agregat harian, bukan per-item) |
| Visitor walk-in | ~2.6% | Tamu non-menginap |
| Dive/Trip/Rental | ~0.1% | Sebagian besar dive/trip **biaya** (lihat §6), bukan pendapatan di sini |

> ⚠️ Dive/trip **pendapatan** hampir tidak muncul sebagai BKM di Bank Book — karena dive/trip banyak lewat **TravyDoor** (entity terpisah) dan/atau dibukukan sebagai net (dipotong vendor). Ini perlu diklarifikasi.

---

## 6. Pola Biaya (berkode GOL)

| Departemen | Total (Jul) | Catatan |
|---|---|---|
| Kitchen | ~Rp 156 jt | Terbesar |
| HO | ~Rp 104 jt | Head office |
| Vendor dive/trip/rental | ~Rp 64 jt | Speedboat, shuttle, rental motor/mobil, guide |
| CME | ~Rp 41 jt | Maintenance |
| Civil (C5) | ~Rp 40 jt | Proyek pembangunan |
| FO | ~Rp 23 jt | |
| HK, BAR, DRV, MKT | ~Rp 15 jt | |

---

## 7. Gap & Keputusan Terbuka (→ grill)

1. **Entity TravyDoor Tour** — ERP sekarang single-hotel (Pratasaba). TravyDoor = lengan tour (dive trip, rental, shuttle) dengan rekening sendiri. Multi-entity, cost center, atau out-of-scope?
2. **GOL cost center** — ERP belum punya tracking biaya per departemen (Kitchen/FO/HK/HO/CME). Wajib untuk "ganti Accurate total".
3. **Petty cash** — ERP belum punya modul PC + alur replenishment (JVC "Tarik Dana").
4. **Cash vs accrual** — Bank Book cash-basis (DP+pelunasan terpisah), ERP accrual (folio saat checkout). Bagaimana rekonsiliasi?
5. **Migrasi historis** — data Jul 2026 ini dijadikan referensi struktur saja, atau perlu di-import sebagai opening balance/transaksi historis?

---

## 8. Keputusan (hasil grill, 26 Aug 2026)

| # | Topik | Keputusan |
|---|---|---|
| 1 | Entity TravyDoor | **Out of scope dulu** — transfer antar-entity dicatat ke akun intercompany |
| 2 | GOL cost center | **Ya, fase terpisah (Phase 8+)** — dimensi departemen di posting biaya |
| 3 | Petty Cash | **Ya, fase terpisah** — ERP kelola PC + alur replenishment |
| 4 | Rekonsiliasi | **ERP accrual + modul bank reconciliation** (cocokkan statement) |
| 5 | Migrasi data | **Referensi struktur saja** (mapping akun/GOL), bukan import |

### Scope Phase 8+ (digabung dari keputusan #2, #3, #4)
1. Dimensi **cost-center/departemen** di posting biaya (mirror GOL: Kitchen/FO/HK/HO/CME/Civil/BAR/DRV/MKT).
2. Modul **Petty Cash** (rekening PC + replenishment "Tarik Dana" via JVC).
3. Modul **Bank Reconciliation** (accrual ERP ↔ statement bank cash-basis).
4. Akun **intercompany** untuk transfer antar-entity (Pratasaba ↔ TravyDoor).
