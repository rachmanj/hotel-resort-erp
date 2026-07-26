# Hotel & Resort ERP

A full-featured ERP system for hotels and resorts — Front Office, Housekeeping, F&B, Billing, CRM, Inventory, Accounting, Maintenance, Spa & Wellness, all accessible via web dashboard and Telegram bot.

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 13 (PHP 8.5) |
| Frontend | Inertia.js v3 + React + Ant Design ProTable |
| Database | MySQL 8 (`hotel_resort`) |
| Real-time | Laravel Reverb |
| Auth | `spatie/laravel-permission` (roles/permissions) |
| Queue | Laravel Queue (database driver) |
| Telegram | `irazasyed/telegram-bot-sdk` (webhook-based) |
| PDF / Excel | DomPDF, `maatwebsite/excel` |

## Architecture Overview

```
HTTP (Web) ──► Controllers (thin) ──► Actions / Services ──► Models ──► MySQL
Telegram Webhook ──► ProcessTelegramUpdate Job ──► same Actions / Services
OTA Webhook (stub) ──► /api/ota/bookings ──► future AvailabilityService integration
```

- **Multi-property:** every record is scoped by `hotel_id` via `BelongsToHotel` global scope; users switch property context in-session.
- **Multi-currency:** guest-facing IDR/USD capture; GL always posts in IDR.
- **Double-entry accounting:** all GL posts go through `GlPostingService`; periods are lockable.
- **Audit trail:** `activity_logs` table tracks create/update/delete on reservations, folios, payments, and journal entries.

Key directories:

| Path | Purpose |
|------|---------|
| `app/Actions/` | Single-purpose invokable business operations |
| `app/Services/` | Cross-cutting domain logic (tax, GL, availability, folio posting) |
| `app/Enums/` | Backed PHP enums (DB stores varchar) |
| `app/Telegram/` | Bot router, conversation manager, command handlers |
| `resources/js/Pages/` | Inertia React pages |

Full design docs: [docs/plan.md](docs/plan.md) · [docs/architecture.md](docs/architecture.md)

## Requirements

- PHP 8.5+
- Composer 2
- Node.js 20+ and npm
- MySQL 8 (or SQLite for local/testing)

## Setup

```bash
# 1. Clone and install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# 3. Configure database in .env (MySQL recommended for production)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=hotel_resort
DB_USERNAME=root
DB_PASSWORD=

# 4. Migrate and seed demo data
php artisan migrate
php artisan db:seed   # runs DemoDataSeeder (roles, hotels, sample data)

# 5. Storage link (hotel logos, guest documents)
php artisan storage:link

# 6. Build frontend
npm run build        # or: npm run dev (during development)

# 7. Start the app
php artisan serve    # http://localhost:8000
```

For full dev stack (server + queue + Vite + Reverb):

```bash
composer run dev
```

## Environment Variables

| Variable | Description |
|----------|-------------|
| `APP_URL` | Base URL (used for links and webhooks) |
| `DB_*` | Database connection (`hotel_resort` for MySQL) |
| `QUEUE_CONNECTION` | `database` (default) — run `php artisan queue:work` |
| `BROADCAST_CONNECTION` | `reverb` for real-time KDS/notifications |
| `TELEGRAM_BOT_TOKEN` | Bot token from [@BotFather](https://t.me/BotFather) |
| `TELEGRAM_WEBHOOK_SECRET` | Random secret; verified via `X-Telegram-Bot-Api-Secret-Token` header |
| `TELEGRAM_BOT_USERNAME` | Bot username (no `@`) |
| `OTA_WEBHOOK_SECRET` | Optional secret for `/api/ota/bookings` (`X-OTA-Webhook-Secret` header) |

## Telegram Bot Setup

1. Create a bot via [@BotFather](https://t.me/BotFather) and copy the token.
2. Add `TELEGRAM_BOT_TOKEN`, `TELEGRAM_WEBHOOK_SECRET`, and `TELEGRAM_BOT_USERNAME` to `.env`.
3. Register the webhook (requires public HTTPS):
   ```bash
   php artisan telegram:webhook set https://your-domain.com/api/telegram/webhook
   ```
4. Staff link their account from **Profile → Telegram Link**, then send `/link <code>` to the bot.

## Testing

```bash
php artisan test --compact
```

## Key Modules

- Front Office / Reservation — calendar, check-in/out, rate plans
- Telegram Bot — staff commands for reservations, housekeeping, F&B, etc.
- Housekeeping — assignments, room status pipeline
- F&B — menu, orders, Kitchen Display System (Reverb)
- Billing & Cashier — folios, PPN 11% + Service 10%, invoices
- Guest CRM — profiles, VIP, blacklist
- Inventory & Purchasing — stock, PR→PO, suppliers
- Accounting — CoA, GL, journal entries, financial statements
- Maintenance & Engineering — work orders, assets
- Spa & Wellness — treatments, appointments, therapists
- Reporting — daily revenue, occupancy, ADR/RevPAR, consolidated (stub)

## Status

In development — Phases 1–11 implemented (auth, reservations, Telegram, billing, housekeeping, F&B, inventory, accounting, spa, hardening & polish).

---

Created by [@rachmanj](https://github.com/rachmanj)
