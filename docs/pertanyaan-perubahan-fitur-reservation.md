# Pertanyaan & Konfirmasi — Perubahan Fitur Reservation
**Pratasaba Resort ERP** · disusun 29 September 2026

Kepada Ibu Sarina dan Tim Pratasaba,

Terima kasih atas dokumen **"Perubahan Fitur Reservation"** beserta contoh Proforma Invoice (WNI & WNA), Payment Receipt, Guest Folio, dan Sales Report Agustus 2026. Semuanya sudah kami baca dan pelajari.

Dokumen ini berisi hal-hal yang perlu dikonfirmasi tim Pratasaba **sebelum** kami kerjakan, supaya hasilnya langsung sesuai dengan kebiasaan operasional di resort dan tidak perlu diperbaiki dua kali. Bagian yang memengaruhi **angka uang** (harga kamar, fee, diskon, akun jurnal) kami tandai **[PENTING]**. Mohon diisi pada kolom **Koreksi (diisi tim)**.

---

## 1. Yang sudah ada di aplikasi (agar tidak dikerjakan dua kali)

| Sudah tersedia | Keterangan |
|---|---|
| Banyak kamar per reservasi | Satu reservasi sudah bisa memuat lebih dari satu kamar/tipe kamar |
| Harga agen kategori A–D | Halaman khusus pengelolaan harga per kategori agen & tipe kamar sudah ada |
| Dokumen | Template Proforma Invoice, Payment Receipt, Guest Folio, dan Invoice sudah ada — yang kami tambahkan adalah penyesuaian format serta pembatasan hak akses |
| Pembatalan otomatis | Sistem sudah memiliki mekanisme pembatalan otomatis untuk reservasi yang belum dikonfirmasi; yang belum ada hanya **ketentuannya** (berapa lama) |
| Alur status | Booked → Confirmed → Checked In → Checked Out sudah tersedia |

**Group Booking** akan kami hilangkan dari menu sesuai permintaan (poin 7). Saat ini belum ada satu pun data Group Booking di sistem, sehingga tidak ada data yang hilang.

| Konfirmasi | Jawaban tim |
|---|---|
| Setuju Group Booking dihilangkan dari menu? | |

---

## 2. Kategori tamu (Source) & data reservasi yang sudah ada

Kategori yang kami pahami: **OTA · Travel Agent · Corporate · Direct · Walk-in**, dengan rincian sebagai berikut — mohon dicek:

| Source | Harga yang dipakai | Perlakuan akun |
|---|---|---|
| OTA | OTA Publish Rate | Fee OTA dicatat sebagai Fee Reservasi Online |
| Travel Agent | Agent Rate sesuai kategori | Pendapatan kamar memakai harga Publish; **selisihnya** dicatat sebagai Discount. Fee Marketing non Agent Resmi dicatat sebagai Fee Reservasi Offline |
| Corporate | Corporate Rate | — |
| Direct | Publish Rate | — |
| Walk-in | Publish Rate | — |
| Direct / Walk-in + Marketing Non Agent | Publish Rate | Fee marketing Rp 100.000/ns/kamar → Fee Reservasi Offline |

Di sistem saat ini sudah ada reservasi lama dengan kategori yang **belum sama** dengan kategori baru ini. Mohon konfirmasi pemetaan berikut agar arti data lama tidak berubah:

| Kategori lama di sistem | Jumlah data | Usulan pemetaan kami | Koreksi (diisi tim) |
|---|---|---|---|
| `ota` | 9 reservasi | tetap **OTA** | |
| `agent` | 3 reservasi | menjadi **Travel Agent** | |
| `walkin` | 3 reservasi | tetap **Walk-in** | |
| `phone` | 4 reservasi | menjadi **Direct** dengan kanal *Phone* | |
| `web` (belum terpakai) | 0 | **Direct** dengan kanal *Web* | |
| `telegram` (belum terpakai) | 0 | **Direct** dengan kanal *Telegram* | |

| Pertanyaan | Jawaban tim |
|---|---|
| Untuk Direct, kanal apa saja yang perlu disediakan? Kami usulkan: Web, Phone, Instagram/TikTok (digabung "Social Media"), Telegram | |
| Apakah pilihan **nama Marketing** (Eeng, Maya, Sabina, Clarisa) tetap sama? Apakah daftarnya berubah bila ada staf masuk/keluar? | |
| Apakah Marketing Non Agent boleh diubah **hanya saat Checked-in**, atau juga setelah tamu check-out? | |
| Fee Marketing Non Agent: apakah benar **Rp 100.000 per malam per kamar**, dan dibayarkan ke siapa (dicatat atas nama siapa di laporan)? | |

---

## 3. Harga kamar **[PENTING]**

Kami membandingkan tabel harga pada dokumen dengan data tipe kamar di aplikasi. **Nama dan angkanya belum sama**, sehingga kami belum berani mengubah harga apa pun. Mohon diisi tabel berikut agar pemetaannya pasti:

| Tipe kamar di aplikasi | Harga di aplikasi saat ini | Nama resmi menurut tim | Publish Rate | Corporate Rate | Koreksi (diisi tim) |
|---|---|---|---|---|---|
| Seroja (Suite) | 1.500.000 | Saroja Suite? | 2.690.000 | 2.500.000 | |
| Kasilasa | 1.200.000 | Kasilasa Grand Deluxe? | 2.090.000 | 1.900.000 | |
| Seheku | 850.000 | Seheku Deluxe? | 1.690.000 | 1.500.000 | |
| Janti | 600.000 | Janti Standard? | 900.000 | 800.000 | |
| Deluxe Twin Seaview | 1.690.000 | Tipe ini masuk yang mana? | | | |

| Pertanyaan | Jawaban tim |
|---|---|
| Apakah harga di aplikasi saat ini (kolom "Harga di aplikasi") memang **sudah benar** dan hanya nama tipe kamarnya yang perlu disesuaikan? | |
| Harga yang dipakai menagih tamu adalah **Publish Rate** pada tabel dokumen — mohon konfirmasi | |
| Agent Rate kategori A–D pada dokumen: apakah menggantikan harga agen yang berlaku sekarang, atau berlaku mulai periode tertentu? | |
| OTA: Tiket.com fee 17% + promo 10%, Traveloka fee 19% — mohon konfirmasi apakah angka ini masih berlaku dan sejak kapan bila berubah | |
| Mohon lampirkan **file Excel tabel harga** (bukan PDF) agar tidak ada angka yang salah ketik saat kami input | |

### Akun jurnal

Penamaan dan kode akun **kami tetapkan sendiri**, mengikuti bagan akun aplikasi supaya konsisten dengan jurnal yang sudah berjalan. Rencana kami:

| Akun di aplikasi | Perlakuannya |
|---|---|
| **Fee Reservasi Online (OTA)** — kode 6-3400 | Biaya pemasaran penjualan. Akun "OTA Booking Fee" yang sudah ada kami sesuaikan namanya (belum ada transaksi di dalamnya) |
| **Fee Reservasi Offline (Marketing Non Agent)** — kode 6-3410 | Biaya pemasaran penjualan (akun baru) |
| **Diskon Agent Rate & Corporate** — kode 4-8900 | Potongan pendapatan: pendapatan kamar tetap tercatat pada harga Publish, selisihnya dibebankan ke akun ini |
| **Utang Fee OTA** — kode 2-1410 | Tetap dipakai bila fee OTA dibayar belakangan |
| **Travel Agent Commission** — kode 6-3300 | Tetap dipakai untuk komisi tunai ke agen — berbeda dari diskon rate |

Bila ada di antara penetapan di atas yang bertentangan dengan bagan akun tim, mohon ditandai di sini:

| Akun yang perlu disesuaikan | Keterangan tim |
|---|---|
| | |

---

## 4. Aturan hunian, anak, dan crew **[PENTING]**

Kami menerima ketentuan berikut dari dokumen; mohon dikonfirmasi karena langsung menentukan nominal yang ditagih ke tamu:

| Ketentuan | Yang kami pahami | Koreksi (diisi tim) |
|---|---|---|
| Termasuk breakfast | Semua tipe kamar termasuk breakfast **2 orang** | |
| Anak 0–4 tahun | Gratis (tanpa charge kamar) | |
| Anak >4–9 tahun | Charge kamar Rp 100.000/malam | |
| Anak >9 tahun | Charge kamar Rp 150.000/malam | |
| Alternatif charge anak | Diganti penambahan 1 Extra Bed (termasuk breakfast 1 pax) Rp 400.000/malam | |
| Extra bed – Suite | Maksimal 4 extra bed per kamar | |
| Extra bed – Grand Deluxe & Deluxe | Maksimal 1 extra bed per kamar | |
| Janti Standard | Belum diatur — apakah sama seperti Grand Deluxe/Deluxe (maks 1)? | |

| Pertanyaan | Jawaban tim |
|---|---|
| Bila anak di atas 9 tahun dihitung sebagai orang dewasa (mis. kamar diisi 3 dewasa), bagaimana ketentuannya? | |
| Apakah batas maksimal orang per kamar (mis. Suite 4 + 4 extra bed) perlu **ditolak sistem** bila terlampaui, atau hanya peringatan? | |
| **Crew** (guide/motoris/tour leader): berapa orang yang **bebas makan** per reservasi, dan bagaimana perlakuan crew ke-4 dan seterusnya? | |
| Apakah jumlah crew perlu muncul di laporan/dokumen tamu, atau hanya catatan internal? | |

---

## 5. Data tamu & proses front office

| Pertanyaan | Jawaban tim |
|---|---|
| Tipe tamu WNA/WNI: mohon konfirmasi bahwa **paspor dilampirkan Front Office saat check-in**, sedangkan Marketing hanya mengisi perkiraan (tanpa paspor) | |
| Untuk WNA, apakah **nama negara** wajib diisi? Apakah diambil dari paspor atau dipilih dari daftar? | |
| Saat Checked-in, Front Office mengisi ulang jumlah pax riil. Bila berbeda dari isian Marketing, apakah perlu **disimpan riwayat perubahan** (siapa mengubah, dari berapa ke berapa)? | |
| Pembatalan otomatis (poin 14): berapa lama tenggat konfirmasi sebelum reservasi otomatis dibatalkan — **24 jam, 48 jam, atau lain**? Apakah ketentuannya berbeda untuk OTA? | |
| Apakah pembatalan otomatis perlu **notifikasi email/WhatsApp** ke Marketing yang bersangkutan? | |

---

## 6. Dokumen tagihan

Kami sudah mempelajari contoh yang dilampirkan. Beberapa hal yang perlu dipastikan:

| Dokumen | Pertanyaan | Jawaban tim |
|---|---|---|
| **Proforma Invoice** | Format WNA menambahkan Bank Provider, Branch, Bank Address, dan Swift Code (BMRIIDJA). Apakah ada rekening bank lain yang perlu ditampilkan untuk WNA? | |
| Proforma Invoice | Nomor dokumen berformat `066/PI/PRATA/VIII/2026`. Apakah penomoran berlanjut otomatis dari sistem dengan pola ini, atau tetap dibuat manual oleh Finance? | |
| Proforma Invoice | Apakah Proforma otomatis terbit begitu Marketing menyimpan reservasi (status Booked), atau menunggu Finance menekan tombol? | |
| Proforma Invoice | Proforma diperbarui otomatis mengikuti jumlah pembayaran yang diterima (poin 5 alur) — mohon konfirmasi apakah dokumen yang sama di-**update**, atau menerbitkan dokumen baru | |
| **Payment Receipt** | Nomor `PR-045/PI/PRATA/VI/2026`. Mohon konfirmasi pola penomoran dan tanda tangan yang muncul (nama Sarina sebagai Finance & Accounting) | |
| Payment Receipt | Metode pembayaran: Cash / Transfer Bank (BCA 7810285758 atau Mandiri 1490075575557) — apakah bank lain mungkin digunakan? | |
| **Guest Folio** | Sesuai contoh: folio berisi **kamar saja**, 1 halaman per kamar. Mohon konfirmasi apakah benar-benar tanpa makanan/minuman/aktivitas | |
| **Guest Registration Form** | Kami **belum punya contohnya**. Mohon dilampirkan format yang diinginkan (isi, urutan data, kolom tanda tangan) | |
| **Invoice** | Menunggu email lanjutan dari tim (4 varian: keseluruhan, per kamar, per nama, lain-lain) | |
| Hak akses | Semua dokumen hanya dapat di-generate/release/download oleh **Finance**. Mohon disebutkan **nama user** yang termasuk, agar akses kami batasi dengan tepat | |

---

## 7. Laporan

Kami memakai Sales Report Agustus 2026 sebagai acuan. Mohon dikonfirmasi isi tiap kolom agar angkanya sama dengan yang biasa tim susun:

| Kolom pada contoh | Yang kami pahami | Koreksi (diisi tim) |
|---|---|---|
| NAMA / GRUP | Nama tamu, atau nama grup bila merupakan grup | |
| ROOM | Tipe kamar (mis. SUITE + EXTRA BED) | |
| MARKETING | Nama marketing penanggung jawab reservasi | |
| CHECK IN / CHECK OUT | Tanggal masuk & keluar | |
| Ns | Jumlah malam | |
| JUMLAH ROOM | Jumlah kamar pada baris tersebut | |
| TOTAL OKUPANSI PAX | Jumlah orang. **Apakah dihitung pax × malam, atau pax saja?** (pada contoh: 1.8.26–2.8.26, 5 kamar, tertulis 5) | |
| JUMLAH | Nominal (rupiah) | |

| Pertanyaan | Jawaban tim |
|---|---|
| Sales Report: apakah baris dipisah per **nama/grup**, atau per **kamar**? Contoh menunjukkan satu grup dengan beberapa baris tipe kamar | |
| Laporan Wisatawan: rincian negara perlu diisi otomatis dari data tamu saat check-in? Mohon konfirmasi | |
| Laporan Kategori Tamu: perlu rincian per OTA (Tiket.com/Traveloka) dan per Travel Agent. Apakah OTA/Travel Agent yang belum terdaftar tetap ditampilkan sebagai satu baris "Lainnya"? | |
| Periode laporan: cukup rentang tanggal bebas, atau perlu juga rekap bulanan/tahunan? | |
| Format keluaran: cukup PDF, atau perlu Excel (agar bisa diolah tim)? | |

---

## 8. Lima hal paling penting

Mohon diprioritaskan pada lima hal ini, karena tanpa jawabannya pekerjaan tidak bisa dimulai:

1. **Pemetaan tipe kamar ↔ tabel harga** (Bagian 3) — nama & angka di aplikasi belum sama dengan dokumen.
2. **Daftar Corporate Rate** — perusahaan/instansi mana saja dan berapa tarifnya.
3. **Ketentuan yang menentukan tagihan tamu**: usia anak, batas extra bed per tipe kamar, ketentuan crew, dan lama tenggat pembatalan otomatis (Bagian 4 & 5).
4. **Contoh Guest Registration Form** dan **daftar nama user Finance** yang berhak menerbitkan/mengunduh dokumen tagihan (Bagian 6).
5. **Penegasan bahwa Invoice menyusul** beserta contoh 4 variannya (Bagian 6).

## 9. Berkas yang kami minta

1. **Tabel harga dalam Excel** (Publish, Corporate, Agent A–D, OTA).
2. **Contoh Guest Registration Form** yang diinginkan.
3. **Daftar nama user Finance** yang berhak menerbitkan/mengunduh dokumen tagihan.
4. Contoh **Invoice** beserta 4 variannya (menyusul sesuai email tim).
5. Bila ada: contoh Sales Report **Januari 2026** (agar kami bisa mencocokkan angka antara sistem dan laporan manual).

Setelah jawaban ini kami terima, kami susun jadwal pengerjaan bertahap dan sampaikan ke tim Pratasaba sebelum mulai.

Terima kasih.
