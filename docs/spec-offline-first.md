# Spec — Offline-First & Sync (Pratasaba ERP)

**Status:** DRAFT (26 Aug 2026) — menunggu konfirmasi decision points.
**Masalah:** Resort di pulau remote (Maratua) dengan koneksi intermiten. ERP (Inertia SPA + Telegram bot) saat ini **online-only** — front-desk berhenti total saat sinyal drop.

---

## 1. Tujuan & Non-Tujuan (v1)

**Tujuan (harus jalan offline):**
- Check-in / check-out tamu
- Posting folio charge (room, F&B, dive, dll) + terima pembayaran
- Petty cash (kas masuk/keluar + replenish)
- F&B order (resto)
- Reservasi walk-in baru

**Non-tujuan (v1 — tetap butuh online):**
- Laporan/report & admin/config (dikerjakan di HO yang ada koneksi)
- Sinkronisasi real-time multi-terminal (fase lanjutan)

---

## 2. Arsitektur yang Direkomendasikan: PWA + Offline Write Queue

Pendekatan **client-side PWA** (tanpa server lokal tambahan), pakai mekanisme Workbox bawaan:

```
[Browser resort] -- offline -->
  1. App shell di-cache service worker (bisa buka walau offline)
  2. Submit tulis (check-in, folio charge, dll) gagal karena offline
     -> Workbox BackgroundSync nge-queue otomatis
  3. Koneksi balik -> queue di-replay otomatis
     -> Server dedupe via idempotency key (tidak dobel posting)
```

**Komponen:**
| Komponen | Teknologi | Fungsi |
|---|---|---|
| PWA shell | `vite-plugin-pwa` + Workbox | Cache asset JS/CSS + manifest, app bisa dibuka offline |
| Read cache | Workbox `NetworkFirst`/`StaleWhileRevalidate` | GET (data halaman) di-cache untuk lihat offline |
| Write queue | Workbox `BackgroundSyncPlugin` | POST yang gagal (offline) di-queue + replay saat online |
| Idempotency | kolom `idempotency_key` + middleware | Cegah dobel posting saat replay |

**Kenapa bukan edge deployment (server lokal) untuk v1:**
- Edge (mini-PC + Laravel lokal + sync DB) lebih robust untuk multi-terminal, tapi butuh infra + replikasi DB + resolusi konflik yang jauh lebih berat.
- PWA queue bisa jalan **tanpa perubahan infra**, cukup deploy build baru. Edge = fase lanjutan kalau dibutuhkan.

---

## 3. Rincian Implementasi

### 3a. PWA shell
- `vite-plugin-pwa`: `injectRegister: 'auto'`, `registerType: 'autoUpdate'`.
- `manifest.json` (nama "Pratasaba ERP", ikon, `display: standalone`).
- Workbox precache semua build asset + cache-first untuk entry HTML.
- Registrasi service worker di `app.jsx`.

### 3b. Offline read cache (opsional, fase 9B)
- Runtime cache untuk endpoint GET yang sering (today reservations, guests, room types, departments, COA) — `StaleWhileRevalidate`.

### 3c. Offline write queue (inti, fase 9C)
- `BackgroundSyncPlugin` di service worker untuk method `POST`/`PUT` ke route operasional.
- Saat offline, request gagal → di-queue; `sync` event replay saat online.
- Header `X-Idempotency-Key` (UUID dibuat client per operasi) disisipkan di tiap submit.

### 3d. Idempotency server (fase 9C)
- Middleware `IdempotencyMiddleware` untuk route operasional (check-in/out, folio charge, payment, petty cash, F&B order, reservation).
- Tabel `idempotency_keys` (key unik, source_type, source_id, response snapshot) — kalau key sudah ada, kembalikan hasil lama tanpa eksekusi ulang.
- Alternatif lebih sederhana: kolom `idempotency_key` (nullable, unique) di tabel inti, `updateOrCreate`/skip jika sudah ada.

### 3e. UI status (fase 9D)
- Badge "Offline — N transaksi tertunda" di header saat `!navigator.onLine` / ada queue pending.
- Konfirmasi "reservasi walk-in offline = pending sampai server konfirmasi".

---

## 4. Fase Eksekusi

| Fase | Isi | Kompleksitas |
|---|---|---|
| **9A** | PWA shell + service worker + manifest | Rendah |
| **9B** | Offline read cache (GET) | Rendah |
| **9C** | Offline write queue (BackgroundSync) + idempotency server | Sedang–Tinggi |
| **9D** | UI status offline + konflik/retry handling | Sedang |

---

## 5. Open Decisions (perlu konfirmasi)

1. **Lokasi queue** — browser (per-terminal) vs edge server lokal (shared). Rekomendasi: browser dulu (v1), edge fase lanjutan.
2. **Scope offline** — hanya operasional front-desk (rekomendasi), atau termasuk laporan?
3. **Konflik double-booking** — saat replay ada kamar bentrok: flag manual (rekomendasi) atau auto-reject?
4. **Timeline** — ini butuh cepat (sebelum musim ramai), atau bisa bertahap (9A→9D)?
