# Spec — WhatsApp Opsional: Tombol Kirim Manual (Reservasi)

**Status:** Final (grill 2026-08-29) · **Scope:** front-desk · **Live:** `pratasaba.sbs`

## Keputusan Grill
1. **Auto-send DIHAPUS total** — create & cancel tidak lagi kirim WA otomatis.
2. **Tombol manual** hanya di **halaman detail reservasi** (`Reservations/Show.tsx`).
3. **Jenis pesan otomatis sesuai status**: `confirmed` → konfirmasi, `cancelled` → pembatalan. Status lain → ditolak.
4. **Tracking**: toast sukses/gagal + activity log (cukup, tanpa kolom status di DB).
5. **Permission baru** `reservations.send-whatsapp` → role `admin` + `front_office`.

## Perubahan Backend
- Hapus blok `$reservation->guest->notify(...)` dari `CreateReservationAction` & `CancelReservationAction`.
- `RolePermissionSeeder`: tambah `reservations.send-whatsapp` di array `admin` + `front_office` (seeder sudah idempotent: `findOrCreate` + `syncPermissions` — aman di-run ulang produksi).
- Route baru: `POST /reservations/{reservation}/send-whatsapp` → middleware `can:reservations.send-whatsapp` + `idempotency` (anti double-click).
- Method `sendWhatsApp` (di ReservationController, ikut pola `cancel`):
  - Guest tanpa `phone` → 422 "Guest tidak memiliki nomor WhatsApp".
  - Status bukan `confirmed`/`cancelled` → 422.
  - **Kirim SYNC** (bukan queue): panggil `toWhatsApp($guest)` dari notification class utk bangun teks → `WhatsAppResponder::sendText()` langsung (biar toast jujur sukses/gagal).
  - Throwable → `report()` + respon error.
  - Sukses → catat activity log + flash sukses.
- Prop baru `canSendWhatsApp` di `show()` (pola: `request()->user()?->can(...)`).

## Perubahan Frontend (`Show.tsx`)
- Tombol **"Kirim WA"** di area aksi, disabled + tooltip saat: guest tanpa phone / status tak layak / tanpa permission.
- `useForm().post` ke route baru + header `X-Idempotency-Key` (reuse `newIdempotencyKey()`), loading state, toast sukses/gagal (pola flash/error existing).

## Test
- `ReservationWhatsAppTest`: 403 tanpa permission · 422 tanpa phone · 422 status tak layak · sukses confirmed (mock responder, activity log terisi) · sukses cancelled.
- Update test lama yang masih assert auto-notify (cek `GroupBookingTest` dkk).

## Deploy (setelah review)
`db:seed --class=RolePermissionSeeder` di produksi + tar build → docker cp → cache clear → verifikasi live.
