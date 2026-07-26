# Hotel & Resort ERP

A full-featured ERP system for hotels and resorts — Front Office, Housekeeping, F&B, Billing, CRM, Inventory, Maintenance, Spa & Wellness, all accessible via web dashboard and Telegram bot.

## Tech Stack
- **Backend:** Laravel 13+ (PHP 8.5)
- **Frontend:** Inertia.js + React + Ant Design ProTable
- **Database:** MySQL 8
- **Real-time:** Laravel Reverb
- **Telegram Bot:** `irazasyed/telegram-bot-sdk`

## Key Features
- 🏨 Room reservation with calendar view
- 🤖 Telegram bot for staff operations (18 commands)
- 🧹 Housekeeping management
- 🍽️ Food & Beverage with Kitchen Display System
- 💰 Billing & Cashier (PPN 11% + Service Charge 10%)
- 👤 Guest CRM with VIP tracking
- 📦 Inventory & Purchasing
- 📊 Reporting & Analytics (ADR, RevPAR, Occupancy)
- 🔧 Maintenance & Asset tracking
- 💆 Spa & Wellness

## Documentation
- [Full Implementation Plan](docs/plan.md) — 11 sections, ERD, schema design, UX flows, Telegram bot spec, implementation phases

## Telegram Bot Setup

1. Create a bot via [@BotFather](https://t.me/BotFather) and copy the token.
2. Add to `.env`:
   ```
   TELEGRAM_BOT_TOKEN=your-bot-token
   TELEGRAM_WEBHOOK_SECRET=your-random-secret
   TELEGRAM_BOT_USERNAME=YourBotUsername
   ```
3. Register the webhook (requires a public HTTPS URL):
   ```bash
   php artisan telegram:webhook set https://your-domain.com/api/telegram/webhook
   ```
4. Staff link their account from **Profile → Telegram Link** in the web app, then send `/link <code>` to the bot.

## Status
🚧 **In Development** — Phase 1–3 implemented (auth, reservations, Telegram bot).

---

Created by [@rachmanj](https://github.com/rachmanj)