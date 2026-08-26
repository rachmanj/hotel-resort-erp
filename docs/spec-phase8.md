# Spec — Phase 8: Cost-Center, Petty Cash & Intercompany (Pratasaba ERP)

**Status:** re-scoped 26 Aug 2026 setelah audit modul accounting yang ternyata sudah lengkap.
**Sumber:** Bank Book Accurate Juli 2026 (`docs/bank-book-july-2026-analysis.md`) + grill decisions.

## Reassessment (penting)

Modul accounting ERP **sudah lengkap** dan ter-wire (routes + UI):
- ✅ Chart of Accounts (hierarchical, double-entry)
- ✅ General Ledger + `GlPostingService` (post, trial balance, income statement, balance sheet, period-aware)
- ✅ **Bank Reconciliation** — `BankReconciliationService` (start, import statement lines, match, auto-match, complete) + UI (`Accounting/BankReconciliation/*`)
- ✅ Journal Entries (submit/approve workflow)
- ✅ Supplier Invoice (AP) → GL, AR Invoice, Budget, Fixed Asset, Tax, Cash Flow report

→ **Item "bank reconciliation" dari grill #4 SUDAH ADA.** Yang perlu dibangun hanya 3 hal.

## Scope (3 sub-feature)

### 8A. Dimensi Cost-Center / Departemen
- Model `Department` (hotel-scoped nullable, configurable) + seed 9 departemen GOL:
  Kitchen, Front Office, Housekeeping, Head Office, Engineering & Maintenance (CME), Civil/Construction, Bar, Driver/Transport, Marketing.
- Kolom `department_id` (nullable FK) di: `general_ledger`, `folio_items`, `supplier_invoice_lines`, journal entry lines.
- Thread di posting: `GlPostingService::post()`, `FolioPostingService::postCharge()`, `PostFolioChargeToGl`, `PostSupplierInvoiceToGl`, journal entry create.
- Default mapping item_type → department (Room→Front Office, Fb→Kitchen, Spa→Spa, dst).

### 8B. Petty Cash
- `BankAccountType` enum (bank | petty_cash) + kolom `type` di `bank_accounts`.
- Flow kas masuk/keluar PC (BKM/BKK) → journal entry terhadap akun PC + `department_id`.
- Replenishment (Tarik Dana) → transfer bank → PC.

### 8C. Intercompany + Transfer
- Akun COA intercompany di seeder (asset `1-15xx` Due from TravyDoor, liability `2-22xx` Due to TravyDoor).
- Flow "Fund Transfer" (JVC / PAA-PBR): journal entry balanced antara 2 akun (bank ↔ PC, bank ↔ intercompany).

## Konvensi (wajib)
- Laravel 13, PHP 8.5, Inertia v3 + React, AntD ProTable. Dark mode, English UI.
- ProTable anti-Chinese: `searchText/resetText`, `fieldProps.placeholder`, `showTotal`, `options={false}`, `dayjs.locale('en')`.
- `expandable={{ childrenColumnName: 'rowChildren' }}` kalau ada field `children`.
- Permission Spatie: `accounting.view` / `accounting.manage` / `accounting.post` (ikuti pola yang ada).
- Validasi Laravel: string `required_if:field,value` (implicit) + `nullable`, JANGAN `Rule::requiredIf()`.
- Double-entry wajib balance; idempotent posting via `source_type`+`source_id`.
- `php artisan test` harus tetap pass; `npm run build` 0 error.

## Verifikasi
1. `php artisan migrate --pretend` (schema valid).
2. `php artisan test` (semua pass).
3. `npm run build` (0 error).
4. `php artisan route:list | grep <feature>` (semua route terdaftar).
