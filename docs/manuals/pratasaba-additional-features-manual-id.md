# Pratasaba ERP — User Manual: Fitur Tambahan

Dokumen ini mencakup fitur-fitur baru yang ditambahkan setelah rilis awal. Untuk modul dasar (Reservations, Rooms, F&B, Spa, Accounting) lihat dokumentasi sebelumnya.

---

## 1. Group Booking

Group Booking memungkinkan pengelolaan reservasi banyak kamar dalam satu transaksi.

### Tipe Group
| Tipe | Deskripsi | Kode |
|---|---|---|
| Single Multi-Room | Satu reservasi, banyak kamar, satu tamu PIC | `single_multi_room` |
| Linked Reservations | Banyak reservasi individual di-link ke satu group | `linked` |
| Corporate/AR | Booking korporat dengan billing ke company | `corporate` |

### Navigasi
Menu **Front Office → Group Bookings** (`/groups`)

### Membuat Group Booking
1. Klik **New Group** (`/groups/create`)
2. **Step 1 — Group Info**: isi Group Name, Group Type, PIC Guest, Company (untuk Type C), Invoice Mode, Special Requests
3. **Step 2 — Rooms**: pilih Arrival/Departure, lalu pilih room type + quantity (Type A saja)
4. **Step 3 — Deposit**: set jumlah deposit yang diminta
5. Klik **Create Group**

### Invoice Mode
| Mode | Keterangan |
|---|---|
| Per Room | Tiap kamar invoice sendiri |
| Per Group | Satu master invoice untuk semua kamar |
| Split | Pilih kamar mana yang digabung/dipisah |

### Aksi di Group Detail (`/groups/{group}`)
- **Check In All** — check-in semua member group sekaligus
- **Check Out All** — check-out semua member group sekaligus
- **Collect Deposit** — catat pembayaran deposit group
- **Generate Invoice** — buat AR invoice konsolidasi (pakai pivot `ar_invoice_folios`)

### Deposit Management
- Deposit dikumpulkan via folio deposit group
- Saat group checkout penuh, deposit dialokasikan sebagai `deposit_credit` ke folio member

---

## 2. Promotional Room Rates

Sistem promo otomatis dengan kode promo dan auto-apply.

### Tipe Promo
| Tipe | Deskripsi |
|---|---|
| Corporate Discount | Diskon % / fixed untuk corporate tertentu |
| Early Bird | Diskon otomatis berdasarkan lead time (hari sebelum check-in) |
| Last Minute | Diskon untuk booking mendadak |
| Seasonal Promo | Rate khusus saat low season (dengan minimum night) |
| Package | Bundle room + F&B/Spa dengan harga paket |

### Navigasi
Menu **Administration → Promotions** (`/admin/promotions`)

### Membuat Promo
1. Klik create → isi name, type, discount type (percentage/fixed), discount value
2. Set valid_from / valid_to, is_active, is_stackable
3. Kondisi opsional: min_nights, max_nights, lead_time_days, applicable_days
4. **Conditions** — tentukan room types / rate plans yang berlaku
5. **Package items** — untuk tipe package, pilih item F&B/Spa yang dibundle

### Kode Promo
- Generate kode di detail promo → **Codes** tab
- Set max_uses, lihat used_count
- Kode bisa di-copy & share ke tamu

### Auto-Apply
- `PromotionEngine` otomatis cek promo yang berlaku saat booking dibuat
- Promo yang cocok otomatis diterapkan ke rate
- Di Reservation detail, gross rate ditampilkan dengan strikethrough + net rate

### Prioritas
- **Agent rate** meng-override promo (jika booking via agent dengan negotiated rate)

---

## 3. Agent Booking

Kelola agent (OTA, travel agent, corporate, internal) dengan komisi dan portal.

### Tipe Agent
| Tipe | Deskripsi |
|---|---|
| OTA | Booking.com, Traveloka, Agoda, Expedia |
| Travel Agent | Agen perjalanan manual |
| Corporate | Perusahaan dengan corporate rate |
| Internal | Sales/marketing hotel sendiri |

### Navigasi
Menu **Administration → Agents** (`/admin/agents`)

### Membuat Agent
1. Klik create → isi name, type, company, contact, email, phone
2. Set commission type (percentage/fixed) + commission value
3. Set payment_terms_days, is_active

### Agent Rates
- Di detail agent → **Rates** tab
- Set rate khusus per room type + validity window

### Komisi
- `AgentCommissionService` otomatis akrual komisi saat guest checkout penuh
- CoA: **2-1400 Utang Komisi Agen** (liability), expense via **6-3300**
- Laporan komisi: `/admin/agents/{agent}/commissions`

### Agent Portal
- URL: `/agent-portal/bookings`
- Agent login dengan role **agent** (portal-only)
- Hanya bisa lihat booking milik agent tersebut
- Middleware: `EnsureUserIsAgent` (permission `agents.portal`)

### OTA Webhook
- Endpoint: `POST /api/ota/bookings`
- `BookingWebhookController` — dedup via `external_booking_id`
- Route via `AvailabilityService` + `CreateReservationAction`

---

## 4. Activity Log

Audit trail: siapa melakukan apa, kapan.

### Navigasi
Menu **Administration → Activity Logs** (`/admin/activity-logs`)

### Fitur
- Filter by date range, user, action, subject type
- Kolom: Date, User, Action, Subject Type, Description
- **Description** menampilkan teks human-readable (mis. "Reservation RES-20260812-0001 cancelled by Admin Hotel")

### Model yang Dilacak
Reservation, Guest, Room, Folio, Payment, Journal Entry, Purchase Requisition, Purchase Order, Inventory Item, Supplier, Work Order, Maintenance Request, Agent, Promotion, dll.

### Event Khusus
| Event | Keterangan |
|---|---|
| `created` | Pembuatan record |
| `updated` | Perubahan field (old → new tersimpan di properties) |
| `deleted` | Penghapusan record |
| `cancelled` | Cancel reservasi (dengan reason) |
| `checked_in` / `checked_out` | Proses tamu |

### Sumber User
- Web: `auth()->id()`
- Telegram/queue: fallback ke `setActingUser()` → `created_by`/`received_by`

---

## 5. F&B — Charge to Room (Order yang sudah ada)

Sebelumnya charge-to-room hanya saat buat order. Sekarang bisa juga setelah order dibuat.

### Navigasi
`/fb/orders/{id}` (halaman detail order)

### Cara Pakai
1. Buka detail order
2. Tombol **Charge to Room** muncul jika order belum di-charge & user punya `fb.manage`
3. Klik → modal pilih reservasi tamu yang checked-in
4. Konfirmasi → charge otomatis post ke folio tamu (GL 4-2100)

### Guard
- Tidak bisa double-charge (order yang sudah `charged_to_room` / ada `folio_item_id` tidak bisa di-charge lagi)

---

## 6. F&B — Granular Permissions (Kitchen vs Manager)

Pemisahan hak akses dapur dan manager F&B.

### Permissions
| Permission | Siapa | Akses |
|---|---|---|
| `fb.view` | Semua F&B | Lihat menu & order |
| `fb.orders.create` | Waiter | Buat order |
| `fb.orders.update_status` | Kitchen | Update status item (preparing/ready) |
| `fb.manage` | Manager F&B | Semua: update status, cancel, charge-to-room, served |

### Kitchen Display System (KDS)
- URL: `/fb/kds`
- Kitchen lihat order masuk, update item status (Preparing → Ready)
- Auto-refresh tiap 30 detik
- Tombol **Served** hanya untuk `fb.manage`

### Rule Status Item
| Role | Bisa update ke |
|---|---|
| Kitchen (`fb.orders.update_status`) | `preparing`, `ready` |
| Manager (`fb.manage`) | `preparing`, `ready`, `served` |

---

## 7. Telegram Commands (Baru)

| Command | Fungsi | Contoh |
|---|---|---|
| `/groups` | List group aktif | `/groups` |
| `/groupcheckin` | Check-in group | `/groupcheckin GRP-0001` |
| `/groupcheckout` | Check-out group | `/groupcheckout GRP-0001` |
| `/promo` | Validasi kode promo + quote rate | `/promo CODE 2026-08-20 2026-08-22 DLX` |
| `/agentbookings` | Booking via Telegram untuk agent | `/agentbookings` |
| `/available` | Cek ketersediaan kamar | `/available 2026-08-20 2026-08-22` |

---

## Permissions Reference

| Permission | Kontrol |
|---|---|
| `groups.view` | Lihat group bookings |
| `groups.manage` | Buat/edit/delete group |
| `groups.checkin` | Group check-in |
| `groups.checkout` | Group check-out |
| `promotions.view` | Lihat promo |
| `promotions.manage` | CRUD promo + kode |
| `agents.view` | Lihat agent |
| `agents.manage` | CRUD agent + rates |
| `agents.portal` | Akses agent portal |
| `fb.orders.update_status` | Kitchen update status item |

---

## Role Assignment

| Role | Permission Utama |
|---|---|
| **admin** | Semua |
| **manager** | promotions.view, agents.view/manage, groups.view/manage |
| **front_office** | groups.view/checkin/checkout, fb.view, fb.orders.create |
| **finance** | promotions.view |
| **fb** | fb.view, fb.manage, fb.orders.create, fb.orders.update_status |
| **agent** | agents.portal (portal-only) |

---

## Troubleshooting

| Issue | Penyebab | Solusi |
|---|---|---|
| Page blank setelah deploy | Vite manifest tidak ditemukan | `npm run build` ulang + deploy assets |
| Page blank (mix content) | ASSET_URL http di page https | Set `ASSET_URL` ke domain https / relative |
| `/groups/create` blank | `Space` import missing | Fix import + rebuild |
| Charge to Room gak muncul | Order sudah charged / user tanpa `fb.manage` | Cek status order + permission |
| KDS gak update | Queue worker mati | Restart `php artisan queue:work` |
| Promo gak auto-apply | Kondisi promo gak cocok | Cek valid_from/to + conditions |

---

## Quick Reference

### URL
| Halaman | Path |
|---|---|
| Group Bookings | `/groups` |
| Promo | `/admin/promotions` |
| Agents | `/admin/agents` |
| Agent Portal | `/agent-portal/bookings` |
| Activity Log | `/admin/activity-logs` |
| KDS | `/fb/kds` |

### CoA Baru
| Akun | Nama |
|---|---|
| 2-1400 | Utang Komisi Agen |
| 4-2100 | Pendapatan F&B |
| 4-3100 | Pendapatan Spa |
