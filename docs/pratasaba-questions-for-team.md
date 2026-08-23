# Daftar Pertanyaan Konfirmasi — Tim Pratasaba

> **Tujuan:** Melengkapi data yang dibutuhkan sebelum integrasi laporan pendapatan (PAA-26) ke Pratasaba ERP.
> **Cara pakai:** Bagi per bagian sesuai audiens. Tidak semua harus dijawab sekaligus — yang ditandai ⚠️ PALING kritis (menentukan desain sistem).

---

## Bagian 1 — Struktur Kamar & Villa *(Front Office / Management)*

Laporan mengelompokkan kamar jadi 4 nama: **Seroja, Kasilasa, Seheku, Janti House**. ERP saat ini pakai tipe "Deluxe Twin Gardenview/Seaview". Perlu disamakan.

1. Seroja/Kasilasa/Seheku/Janti House itu sebenarnya apa?
   - (a) Villa/bangunan terpisah yang disewa utuh (1 tamu = 1 villa)
   - (b) Tipe kamar (tiap villa isi beberapa kamar)
   - (c) Campuran
2. Berapa jumlah kamar/unit di tiap Seroja, Kasilasa, Seheku, Janti House?
3. Ada kamar/unit lain yang TIDAK masuk 4 nama itu? (dorm, kamar staff, camping ground, dll.)
4. Istilah "Deluxe Twin Gardenview / Seaview" masih dipakai tim, atau cuma istilah lama yang sudah tidak relevan?
5. Harga kamar: per-villa flat, atau beda tiap tipe kamar di dalam satu villa?

## Bagian 2 — Kategori Pendapatan *(Finance)* ⚠️

Laporan punya 16 kolom kategori pendapatan. Perlu dipastikan lengkap dan jelas artinya.

6. Daftar 16 kategori ini sudah lengkap? Ada pendapatan yang **tidak tercatat** atau **digabung**? *(contoh: **Spa** tidak muncul di Mei 2026 — apakah Spa punya pendapatan sendiri?)*
7. "Boat" vs "Dive Center" vs "Snorkeling + Diving" — bedanya apa? *(di sheet pertama tulisannya "Snorkeling + Diving", di sheet kedua "Dive Center")*
8. "Meeting Packages" nilainya 0 di Mei — kategori ini tetap dipertahankan atau dihapus?
9. "Lain-lain" (± Rp 15,6jt) biasanya isinya apa saja?
10. "Showcase" itu apa — penjualan produk di etalase/toko?
11. "Tiket Pantai" itu tiket masuk pantai untuk non-tamu, atau termasuk tamu menginap?

## Bagian 3 — Bisnis Diving *(Dive Center)* ⚠️

Diving = ±58% pendapatan tapi ERP belum punya modulnya. Butuh detail operasional.

12. Jenis dive yang dijual apa saja + daftar harga? *(Dive Packages, Discovery Scuba Diving, Fun Dive, Course/Sertifikasi, dst.)*
13. Boat charter: berapa unit boat, nama & kapasitas, destinasi umum (Turtle Point, dst.), harga sewa per trip?
14. Dive guide: karyawan tetap atau freelance/vendor? *(berhubungan dengan pos "Bayar Vendor/Guide" Rp 44jt)*
15. BBM boat: dihitung per trip (liter) atau flat? Siapa yang bayar?
16. Siapa yang mencatat order diving — dive center, front office, atau tamu langsung pesan?

## Bagian 4 — Biaya & Diskon *(Finance)* ⚠️⚠️ PALING KRITIS

Ringkasan biaya di laporan belum jelas. Ini menentukan mapping ke buku besar.

17. "**Deduction**" (Rp 183.416.300) itu apa persisnya — diskon, komisi, atau potongan lain?
18. "**Discount accurate**" (Rp 129.359.050) bedanya sama "Deduction" apa?
19. "**Fee Reservation Online**" (Rp 9.810.750) itu komisi OTA (Traveloka / Tiket.com)? Berapa % per OTA?
20. "**Fee Reservation Offline**" (Rp 0) maksudnya apa? Kenapa nilainya 0?
21. "**Bayar Vendor / Guide**" (Rp 44.246.500) bayar ke siapa — dive guide freelance, vendor lain?
22. Kolom **"Total" (Rp 935jt)** vs **jumlah semua kategori (± Rp 1,35M)** tidak sama. Aturan hitungnya bagaimana? Mana yang "gross", mana yang "net"?

## Bagian 5 — Nomor Invoice & Alur Kerja *(Finance / Front Office)* ⚠️

23. Nomor invoice `26900309` dst — itu nomor dari sistem apa? (Accurate, PMS, atau manual?)
24. Alur sekarang: siapa yang input transaksi, kapan (harian/mingguan/bulanan), dari sumber apa (struk POS, laporan OTA, catatan manual)?
25. **Accurate** dipakai untuk apa saja saat ini? Setelah ERP jalan, Accurate mau dipakai buat apa (pajak saja? tetap full pembukuan?)

## Bagian 6 — Scope Import & Data Historis *(Finance / Management)*

26. Laporan bulanan seperti PAA-26 sudah ada sejak kapan? Berapa bulan ke belakang yang bisa di-import?
27. Format Excel-nya konsisten tiap bulan, atau sering berubah?
28. Nama tamu tidak konsisten (Traveloka Guest / Tiket.com / Walk-in / UMUM / nama orang). Ada master data tamu yang lebih rapi, atau perlu dibersihkan saat import?

## Bagian 7 — Kebutuhan Laporan ke Depan *(Management)*

29. Siapa yang membaca laporan ini? (pemilik, finance, manager, investor)
30. Format laporan ke depan: **tetap persis** seperti PAA-26, atau **boleh diperbaiki/dikembangkan**?
31. Butuh breakdown tambahan? (per OTA, per kategori per hari, per tamu, per agen, per villa)
32. Periode pelaporan: bulanan saja, atau perlu harian/mingguan?

---

### Ringkasan: 5 hal paling penting (jawab dulu ini kalau waktunya terbatas)

1. Villa vs room type + jumlah kamar tiap villa *(Bagian 1, no. 1–2)*
2. Arti "Deduction" vs "Discount accurate" vs "Fee OTA" vs "Bayar Vendor/Guide" *(Bagian 4, no. 17–21)*
3. Nomor invoice 26900309 dari sistem apa *(Bagian 5, no. 23)*
4. Sumber laporan: Accurate atau manual *(Bagian 5, no. 25)*
5. Berapa bulan ke belakang yang perlu di-import *(Bagian 6, no. 26)*
