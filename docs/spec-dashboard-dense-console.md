# Spec — Dashboard style "Dense Console" (varian 2)

Dipilih Iwan 18 Sep 2026 dari 3 varian di `~/sketches/pratasaba-dashboard/`. Implementasi di Pratasaba ERP (Laravel 13 + Inertia + React + AntD 5), tanpa menambah library baru.

## Arah desain (dari sketch varian 2)

- **Dials**: ENERGY 1 (tenang, seperti alat kerja) / RHYTHM 2 (konsisten, sedikit variasi) / MOTION 1 (hover saja)
- **Sasaran**: staf front office yang membuka halaman ini sepanjang shift. Kepadatan informasi menang atas dekorasi.
- **Focal point**: antrian pergerakan hari ini (siapa masuk, siapa keluar).
- **Satu aksen**: teal. Hijau dan amber hanya sebagai penanda status (masuk/keluar, bersih/kotor). Tidak ada gradien dekoratif.

## Token

| Bagian | Nilai |
| --- | --- |
| Aksen (colorPrimary) | `#0d9488` |
| Rail sidebar | latar `#12211f`, teks `#c9d6d4`, item aktif `#16302d` dengan garis aksen 2px di kiri, caption `#7f918f` |
| Lebar rail | 212px |
| Panel | `token.colorBgContainer`, border `token.colorBorderSecondary`, radius 6px, tanpa shadow |
| Teks | utama `token.colorText`, sekunder `token.colorTextSecondary`, caption 10px/700 uppercase letter-spacing .06em |
| Angka KPI | 19 sampai 20px, weight 700, `font-variant-numeric: tabular-nums` |
| Status | masuk atau siap: `#15803d`; keluar atau perlu servis: `#b45309`; error: `#b91c1c` |

## Blok halaman Dashboard (urutan)

1. **KPI strip** satu panel berisi 5 kolom dengan garis pemisah: Occupancy (dengan selisih vs kemarin), Arrivals today, Departures today, In-house guests, Revenue today.
2. **Movement today**: daftar baris jam, jenis (Check-in atau Checkout), tamu, villa dan tipe kamar, jumlah malam, sumber, status. Filter tab: All, Arrivals, Departures.
3. **Housekeeping**: grid kotak kamar berwarna status (pakai prop `rooms` yang sudah ada), legenda, jumlah kamar yang perlu servis.
4. **Occupancy, last 14 days**: bar SVG inline dari prop `occupancySeries`.
5. **Revenue mix this month**: tabel per kategori pendapatan dari prop `revenueMix`, dengan persentase.

## Kontrak data (prop baru dari DashboardController)

- `arrivalsToday`: `[{ id, time, guest_name, room_label, nights, guest_count, source_label, agent_name, status_label, status_kind, folio_open }]`
- `departuresToday`: bentuk sama
- `occupancySeries`: `[{ date, label, occupancy }]` untuk 14 hari terakhir
- `occupancyDelta`: selisih poin hunian hari ini vs kemarin
- `revenueMix`: `[{ category, amount, share }]` untuk bulan berjalan
- `inHouseGuests`: jumlah tamu yang sedang menginap
- Prop lama tetap dikirim: `occupancy`, `checkinsToday`, `occupiedRooms`, `sellableRooms`, `revenueToday`, `roomStatusSummary`, `rooms`

## Aturan

- Semua label English, tidak ada emoji, tidak ada em dash
- Warna selalu dari token AntD (mode gelap harus benar)
- Keadaan kosong wajib: kalau tidak ada arrival atau departure hari ini, tampilkan pesan yang jelas, bukan tabel kosong tanpa keterangan
- Data kosong tetap ditampilkan apa adanya (tidak ada angka karangan)
