# Panduan Singkat — Cost Center, Petty Cash & Transfer Antar-Entitas

**Pratasaba ERP · Phase 8 (Agustus 2026)**

---

## 1. Ringkasan

Pembaruan ini menambahkan tiga kemampuan akuntansi agar ERP semakin dekat menggantikan Accurate:

| Fitur | Fungsi |
|---|---|
| **Cost Center / Departemen** | Mencatat biaya & pendapatan per departemen (mirror kode GOL di Accurate) |
| **Petty Cash** | Mengelola kas kecil (PC) beserta kas masuk/keluar dan pengisian dana |
| **Transfer & Intercompany** | Memindahkan dana antar rekening & antar badan usaha (Pratasaba ↔ TravyDoor) |

---

## 2. Cost Center / Departemen

Setiap transaksi biaya/pendapatan kini bisa dikaitkan dengan **departemen**. Ada **10 departemen** yang sudah disiapkan (sesuai kode GOL Accurate):

| Kode GOL Accurate | Departemen di ERP |
|---|---|
| Kitc / KIT | Kitchen & Restaurant |
| FO | Front Office |
| HK | Housekeeping |
| HO | Head Office |
| CME | Engineering & Maintenance |
| Civi / CIV | Civil / Construction |
| BAR | Bar |
| DRV | Driver / Transport |
| MKT | Marketing |
| — | Spa & Wellness |

**Auto-mapping:** ketika biaya kamar / makanan / spa di-posting otomatis, departemen terisi sendiri:
- Biaya **kamar (Room)** → Front Office
- Biaya **makanan/minuman (F&B)** → Kitchen
- Biaya **spa** → Spa & Wellness

**Mengelola departemen:** menu **Accounting → Departments**. Departemen global berlaku untuk semua hotel; bisa ditambah/diubah sesuai kebutuhan.

**Menentukan departemen manual:** saat membuat **Journal Entry** atau **Supplier Invoice (Payables)**, pilih departemen pada tiap baris. Departemen juga tampil sebagai kolom & filter di **General Ledger**.

---

## 3. Petty Cash (Kas Kecil)

Petty cash dipakai untuk pengeluaran/penerimaan tunai harian (belanja GOL, penjualan kecil, dll).

**Langkah awal — buat rekening PC:**
1. Buka **Accounting → Bank Accounts** (atau menu terkait).
2. Tambah rekening dengan **Type = Petty Cash** (bukan Bank).
3. Hubungkan ke akun COA kas kecil.

**Kas masuk / kas keluar** (menu **Accounting → Petty Cash**):
- Pilih rekening PC, arah (**Kas Masuk** / **Kas Keluar**), nominal, tanggal, deskripsi.
- Pilih **departemen** (mis. Kitchen) dan **akun lawan** (akun biaya untuk kas keluar, akun pendapatan untuk kas masuk).
- Sistem otomatis mencatat jurnal berimbang (double-entry).

**Pengisian dana (Replenishment / "Tarik Dana"):**
- Menu **Petty Cash → Replenish**: pilih rekening bank sumber → rekening PC tujuan → nominal.
- Mencatat transfer: debit PC, kredit bank.

---

## 4. Transfer Dana & Intercompany

**Transfer antar rekening** (menu **Accounting → Transfers**):
- Untuk memindahkan dana antar rekening kas/bank/GL, mis. bank → petty cash.
- Mencatat jurnal berimbang otomatis (debit tujuan, kredit sumber).

**Transfer antar badan usaha (Intercompany):**
- Disediakan 2 akun khusus:
  - `1-1450` **Due from TravyDoor Tour** (aset — piutang dari TravyDoor)
  - `2-2210` **Due to TravyDoor Tour** (liabilitas — utang ke TravyDoor)
- Saat ada pinbuk/transfer dana Pratasaba ↔ TravyDoor (pola `PAA-PBR` di Accurate), catat lewat menu **Transfers** memakai akun intercompany di atas.

---

## 5. Padanan Kode Accurate → ERP

| Kode Accurate | Makna | Fitur ERP |
|---|---|---|
| BKM (kas masuk) | Penerimaan kas | Folio payment / Petty Cash kas masuk |
| BKK (kas keluar) | Pengeluaran kas | Petty Cash kas keluar |
| PMT | Pembayaran bank | Payment / Supplier Invoice |
| JVC | Transfer antar rekening | **Transfers** |
| GOL.xxxxx | Cost center departemen | **Departments** |

---

## 6. Alur Kerja Harian (Ringkas)

1. **Kas kecil harian** → catat belanja/penerimaan di **Petty Cash** (pilih departemen).
2. **Pengisian PC** → **Transfers** / **Petty Cash → Replenish** dari bank.
3. **Transfer antar entity** → **Transfers** dengan akun intercompany.
4. **Pantau biaya per departemen** → **General Ledger** (filter departemen) / laporan akuntansi.

---

*Dokumen ini bersifat panduan singkat. Untuk pertanyaan teknis, hubungi Bapak Iwan.*
