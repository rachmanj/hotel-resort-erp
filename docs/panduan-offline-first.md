# Panduan Singkat — Mode Offline (Tahan Sinyal Lemah)

**Pratasaba ERP · Pembaruan Agustus 2026**

---

## 1. Ringkasan

Karena lokasi Resort (Maratua) sering mengalami kendala sinyal, Pratasaba ERP kini punya **mode offline**. Artinya, operasional front-desk tetap berjalan walau internet mati — transaksi disimpan di perangkat dan otomatis dikirim ke server begitu sinyal kembali.

---

## 2. Memasang ERP sebagai Aplikasi (PWA)

Supaya mode offline bekerja optimal, pasang ERP sebagai aplikasi di perangkat (HP/tablet/laptop):

1. Buka `https://pratasaba.sbs` di browser (Chrome/Safari/Edge).
2. Login seperti biasa.
3. **Chrome/Edge (Android/PC):** tekan menu (⋮) → **"Add to Home screen"** / **"Instal aplikasi"**.
4. **Safari (iPhone/iPad):** tekan tombol **Bagikan (□↑)** → **"Add to Home Screen"**.

Setelah itu, ERP bisa dibuka dari ikon aplikasi di layar utama — lebih cepat dan mendukung mode offline.

---

## 3. Perilaku Saat Sinyal Hilang (Offline)

| Hal | Perilaku |
|---|---|
| Membuka ERP | ✅ Tetap bisa dibuka |
| Melihat data (reservasi, tamu, kamar) | ✅ Tampil data terakhir yang ter-cache |
| Melihat laporan | ⚠️ Butuh online (dibuka di kantor/area berkoneksi) |

---

## 4. Transaksi Saat Offline (disimpan otomatis)

Saat offline, transaksi berikut **tetap bisa dilakukan** dan akan tersimpan di perangkat:

- Check-in & check-out tamu
- Posting biaya ke folio + terima pembayaran
- Petty cash (kas masuk/keluar + pengisian dana)
- Pesanan F&B (resto)
- Reservasi baru (walk-in)

**Penanda status** di pojok atas layar:
- 🟠 **"Offline — N pending"** = sedang offline, ada N transaksi menunggu dikirim.
- 🔵 **"Syncing N…"** = sudah online, sedang mengirim N transaksi.
- (Badge hilang = semua transaksi sudah terkirim)

> Klik badge untuk melihat daftar transaksi yang masih menunggu.

---

## 5. Saat Sinyal Kembali (sinkronisasi otomatis)

- Transaksi yang tertunda **otomatis dikirim** ke server.
- **Tidak akan dobel** — setiap transaksi punya kode unik (idempotency key), jadi kalau terkirim dua kali, server hanya mencatat sekali.
- Sesi login berlaku **7 hari**, jadi staff tidak perlu login ulang setiap hari.

---

## 6. Catatan Penting

- **Reservasi walk-in saat offline** = tercatat sebagai *pending*, dikonfirmasi server begitu online. Jika ternyata kamar sudah terisi (bentrok), sistem akan menandai untuk ditinjau manual — hubungi resepsionis/atasan.
- **Laporan & pengaturan** sebaiknya dilakukan saat ada koneksi (mis. dari kantor).
- Pastikan perangkat **tidak memakai mode hemat baterai ketat** yang mematikan browser di latar belakang, supaya pengiriman otomatis berjalan.

---

*Dokumen ini bersifat panduan singkat. Untuk pertanyaan teknis, hubungi Bapak Iwan.*
