# 🧠 AI_SKILLS.md — AI Skills Constitution for the qaatSystem Project

> **Purpose:** This file is the **mandatory reference** for any AI model (or developer) working on the `qaatSystem` project.
> Read it **in full before making any change**, and comply with every rule in it. Do not violate any rule without an explicit, written justification.
>
> **Version:** 1.0 | **Scope:** the entire `c:\Laravel\qaatSystem` repository | **Official language:** Arabic (RTL) for the UI, comments, and messages.

---

## 0. The Golden Rule (read this first)

> **Don't guess. Don't make things up. Don't forget. Always verify.**

1. **Never invent file names, function names, fields, or tables that do not exist.** If you are unsure → search the repository (`grep_search` / `read`) before writing.
2. **Never claim that work is done without actually doing it.** Do not say "done" unless you made a real edit and have a tool result that confirms it.
3. **When in doubt:** ask one specific question, or do the verification yourself with the tools — do not guess.
4. **Any answer that contains code** must be based on reading the actual file first (not from memory).

---

## 1. Project Identity and Context (Context Lock)

| Item | Confirmed Value |
|---|---|
| System type | Accounting + inventory ERP specialized for the trade/distribution of qat (khat) plant |
| Framework | Laravel 12 (PHP ^8.2) |
| Frontend | Blade Templates + Bootstrap 5.3.8 + Bootstrap Icons + pattern-based JS (no SPA / no React/Vue) |
| Bundler | Vite 6 (+ Tailwind 4 present, but the UI is actually Bootstrap) |
| Direction | **Arabic RTL mandatory** — `<html lang="ar" dir="rtl">` |
| Database | MySQL (per the docs) with a gap: `.env.example` is set to sqlite — **verify before any DB work** |
| Architecture style | MVC + Eloquent + **Observers** for accounting synchronization |
| Primary keys | Non-standard naming in several tables (`accountID`, `StockID`, `itemID`, `CustomersID`, `UnitID`, `coinsID`...) — **do not assume `id`** |
| No Auth | ⚠️ There is currently no authentication on the routes |

> **Context rule:** Any new code must blend into this context, not impose a foreign pattern (no Alpine, no Livewire, no Tailwind utility classes in the current UI unless explicitly requested).

---

## 2. Repository Map — keep it in mind

```
app/
├── Http/Controllers/
│   ├── Controller.php , CustomerController.php , SupplierController.php
│   ├── accounting/  → CharAccountController, BoxController, BankController, CoinController
│   ├── Inventory/   → ItemController, TypeController, UnitController, StockController
│   └── Operation/Purchases/ → PurchaseInvoiceController
├── Http/Requests/   → (currently empty — preferably move validation here)
├── Models/          → Accounting/{CharAccount,Bank,Box,Coin}
│                      Inventory/{Item,Type,unit,Stock}
│                      Purchases/{PurchaseInvoice,PurchaseInvoiceDetail}
│                      Customer.php , Supplier.php
├── Observers/       → {CharAccount,Box,Bank,Stock,Customer,Supplier}Observer
└── Providers/AppServiceProvider.php   ← all Observers are registered here

public/js/
├── Shared: system.js (Toast + Confirm + Sidebar), shared/{utils,state,modals,...}.js
├── CRUD screens: items.js, units.js, type.js, banks.js, coins.js, boxes.js, stocks.js, supplier.js, customer.js
├── Invoices: purchase_invoice.js (huge), invoice/sales_invoice_*.js (split — reference example)
├── Vouchers: payment/payment.js + paymentSearch.js , recepit/recepit.js + receiptSearch.js
└── movements.js

resources/views/
├── layouts/ → app.blade.php (shell), navbar.blade.php, sidebar.blade.php
├── setting/ → {accounting,inventory,suppliers,customers}/...
├── operation/ → purchases, sales, accounting, movements, models/
└── dashboard/

routes/web.php        (all routes — there is no api.php in use)
database/
├── migrations/       ← only one active file currently (purchase_invoice_details)
├── bac/              ⚠️ 20 "disabled" migrations outside migrations — a known issue
├── seeders/          → ChartOfAccountsSeeder (chart of accounts)
└── factories/UserFactory.php
```

---

## 3. Mandatory UI Rules (UI/UX Consistency)

### 3.1 The Shell
- Every screen extends `@extends('layouts.app')` and uses `@section('content')`.
- Page scripts are passed via `@push('scripts')`, and modals via `@stack('modals')`.

### 3.2 Shared Components (do not reinvent them)
| Need | Mandatory usage |
|---|---|
| Success/error message | `showSystemToast('message', 'success'|'danger'|'warning'|'info')` |
| Confirm an action | `showSystemConfirm('message', { title, okText, cancelText, type, onConfirm })` |
| Modal | Defined **once in Blade** with a unique `id` — **building it with innerHTML is forbidden** |
| Buttons | Bootstrap: `btn btn-sm btn-primary/secondary/danger` |
| Icons | Bootstrap Icons: `bi bi-*` |
| Tables | `table table-bordered table-hover align-middle text-center` |

### 3.3 System Colors
- Primary: `success` (green) — active highlight in the sidebar is `bg-success text-white`.
- Backgrounds: `bg-light` / `bg-dark` (sidebar).
- Secondary text: `text-white-50` / `text-muted`.

### 3.4 Sidebar
- Do not modify the sidebar structure except when adding a new screen, and use the same existing collapse pattern.
- Active page item: `request()->routeIs('[prefix].*')` → `bg-success text-white`.
- Auto-open the parent menu with the same existing `$is*Active` logic.

---

## 4. Mandatory Code Patterns (do not deviate)

These patterns are defined in `requirements_final.md` and are **binding**:

### 4.1 Screen State Variables
```javascript
let screenMode  = 'view';   // 'view' | 'add' | 'edit'   ← the unified variable for every screen
let activeRow   = null;     // the active row (for modals)
let currentData = {};       // the displayed record data
```
> ⚠️ Naming the mode variable differently (`salesInvoiceMode`, `invoiceMode`...) on new screens is forbidden — the reference name is `screenMode`. (Old screens may differ — unify them carefully when editing.)

### 4.2 Modal Pattern
- One modal per type, defined in Blade.
- Unified functions: `open[X]Modal()`, `[x]KeyDown()`, `[x]Blur()`, `search[X]()`, `select[X]()`.
- **Auto-open** on typing + Enter/Tab/blur — **no trigger buttons**.

### 4.3 Save Pattern
- `validateForm()` collects errors and shows them in a single message.
- Saving goes through the **real API** (`fetch` + CSRF), not mock — the actual state in `purchase_invoice.js` uses `apiGet/apiSend`.

### 4.4 Navigation Guard
- Shared `navigationWarningModal` in `app.blade.php`.
- `interceptNavigation(url)` + `window.beforeunload`.

### 4.5 Auto-Numbering
```javascript
function getNextNumber(prefix, records) {
    if (!records || records.length === 0) return `${prefix}-0001`;
    const maxId = Math.max(...records.map(r => r.id));
    return `${prefix}-${String(maxId + 1).padStart(4, '0')}`;
}
```

### 4.6 JS API Layer
- Use `apiHeaders()` (CSRF + Accept JSON) and `apiGet` / `apiSend` in `purchase_invoice.js` as the reference.
- Handle errors: `response.ok` + read `json.message`.

---

## 5. Security Rules (Security — non-negotiable)

1. **CSRF is mandatory** on every modifying request: `<meta name="csrf-token">` + `X-CSRF-TOKEN`.
2. **Always validate input on the server** — never trust JS. Validation stays in the Controller (or a Form Request).
3. **Mass Assignment:** always use `$fillable` — never `$guarded = []`.
4. **XSS:** in Blade use `{{ }}` (auto-escaped). **`{!! !!}` is forbidden** except for trusted in-system content only.
5. **SQL Injection:** use Eloquent / Query Builder. **Never interpolate variables into `DB::raw` without binding**.
6. **Do not leak secrets**: no `APP_KEY`, no credentials, no sensitive absolute paths.
7. **Server-side authorization check** for every financial/delete operation.
8. **Transactions:** any multi-table operation (header + details, customer + account) must be wrapped in `DB::beginTransaction` / `commit` / `rollBack`.
9. **Foreign keys:** respect `onDelete('restrict')` — do not delete a record that has movements; deactivate it instead (`IsActive=0`).
10. ⚠️ **Known note:** the system currently has **no authentication** — any new financial screen must be designed so it can easily be placed behind Middleware later, and must not assume a user exists.

---

## 6. Design Rules (Design Consistency)

| Rule | Detail |
|---|---|
| Language | All text in Arabic, numbers as-is, RTL direction |
| Icon per menu item | An appropriate Bootstrap Icon (meaning before form) |
| Field names | Follow the project pattern (`CustomersName2`, `StockName`, `accName`...) — do not invent a different name for the same concept |
| Table names | `characcount`, `coins`, `boxes`, `banks`, `stocks`, `Items`, `type`, `units`, `customers`, `suppliers`, `purchase_invoices`, `purchase_invoice_details` |
| Statuses | The activation field is usually named `is_active` (exceptions: `IsActive` in characcount, `CusIsStopeed` in customers) — **verify against the table before using** |
| Modal pattern | `modal fade` + `modal-dialog modal-lg modal-dialog-centered` + `modal-header/body` |
| Header buttons | Fixed pattern: print / edit / save / cancel — in a consistent order |

---

## 7. Backend Conventions
- **Controller**: every AJAX operation returns JSON as `['data' => ...]` or `['success' => bool, 'message' => ...]`.
- **Naming**: `index/list/show/store/update/destroy` + `nextNumber`/`nextCode`/`toggleStatus` where needed.
- **Observers**: any entity linked to an accounting account must synchronize:
  - Name → `accName`
  - Status → `IsActive` (inverted for customers: `CusIsStopeed=1` → `IsActive=0`)
  - Deletion: handled in the Controller, not in the Observer (respect the existence of children).
- **System accounts**: located via `system_key` (values: `customers`, `suppliers`, `inventory`, `cash`, `banks`, `assets`...).
- **Parent account**: `isPostable = 0` (aggregate), child: `isPostable = 1` (postable).
- **Child account numbering**: `generateNextChildCode()` → parent prefix + a zero-padded sequential number.

---

## 8. Mandatory Workflow for Any Task
```
1) Understand the request → restate it in one sentence, and identify the affected files.
2) Explore              → read/search the actual files before any writing (do not rely on memory).
3) Plan                 → for 3+ step tasks: use a TODO list and show it.
4) Implement            → small surgical edits (edit_file), not a full rewrite.
5) Verify               → re-read the file after the edit / run the check / inspect the page.
6) Summarize            → state exactly what was done, what was not verified, and the next step.
```

**Additional rules:**
- The smallest possible change that achieves the goal — do not touch unrelated code.
- Do not delete a file in order to "edit" it — use `edit_file`.
- When editing a JS/Blade file, preserve the project's Arabic comments and their style.
- After any UI change: verify there are no Console errors (you may launch the browser to check).

---

## 9. Explicit "Forbidden" List (Anti-Hallucination Checklist)

❌ Forbidden to invent a field/table/function/route that does not exist — **verify first**.
❌ Forbidden to use `id` as a primary key without verification (the project uses custom keys).
❌ Forbidden to run `sync`/`migrate` on a production environment without an explicit warning.
❌ Forbidden to delete the `database/bac/` files or ignore their issue without reporting it.
❌ Forbidden to leave stray `console.log`, dead commented code, or meaningless TODOs.
❌ Forbidden to add a new JS/PHP library without an explicit user request.
❌ Forbidden to build a modal dynamically with `innerHTML`.
❌ Forbidden to use `{!! !!}` for user-provided content.
❌ Forbidden to say "done" or "fixed" before a tool result confirms it.
❌ Forbidden to assume that Tailwind is used in the current UI (the UI is Bootstrap).

---

## 10. Known Issues — always take these into account
| # | Issue | Impact | Action |
|---|---|
| 1 | `database/bac/` contains 20 migrations outside `migrations/` | `migrate` does not create the tables | Report it to the user; move them carefully only on request |
| 2 | DB conflict: sqlite in `.env.example` versus MySQL in the docs | Possible startup failure | Verify the actual `.env` before any DB work |
| 3 | `qat_dp` is an undocumented 96KB binary file | Possible security risk | Do not open it; preferably audit/remove it from git |
| 4 | No authentication on the routes | All operations are open | Design so Auth can be added easily |
| 5 | `app/Http/Requests/` is empty | Validation is scattered across Controllers | Gradually move validation into Form Requests |
| 6 | `purchase_invoice.js` is extremely large | Hard to maintain | When working on it, follow the reference split `invoice/sales_invoice_*.js` |
| 7 | `README.md` is the default Laravel readme | No setup instructions | Update it on request |
| 8 | `tests/` has only the default tests | No coverage | Add PHPUnit tests for the core CRUD |
| 9 | `requirements_final.md` mentions mock while the code uses a real API | Reference conflict | Treat the **actual code** as truth, and the doc as direction |

---

## 11. Milestones Roadmap
- **M1 (Core settings):** auto-numbering, banks screen (account number + chart account + currency), print buttons, analytical account (suppliers/customers/boxes).
- **M2 (Purchase invoices):** unified item modal, default unit "kilo", automatic bank/network/box modals, prices with currency, save conditions, fix `loadInvoice`, navigation warning.
- **M3 (Inventory movement):** voucher type, enable direct supply/issue, auto-fill from invoice, warehouse per row, search inside a Modal, auto-create movement from the invoice.

> The full execution order is in `requirements_final.md` ← follow it when implementing tasks.

---

## 12. Ready Templates
### 12.1 JS file header for a new screen
```javascript
/* =========================================================
 * [screen].js — [module] management
 * Follows AI_SKILLS.md standards (screenMode / modal / validation / navigation guard)
 * ========================================================= */
let screenMode = 'view';
let activeRow = null;
let currentData = {};

const SCREEN_CONFIG = {
    editableFields: [],
    requiredFields: [],
};

document.addEventListener('DOMContentLoaded', () => {
    if (typeof setMode === 'function') setMode('view');
});
```

### 12.2 Controller header
```php
<?php

namespace App\Http\Controllers\...;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class [Name]Controller extends Controller
{
    // index / list / show / store / update / destroy
    // every multi-table write goes inside DB::transaction
}
```

### 12.3 Blade modal
```blade
<div class="modal fade" id="[prefix]Modal" tabindex="-1" aria-hidden="true">
 <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-[icon]"></i> [Title]</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-bordered table-hover align-middle text-center">
            <thead class="table-light"><tr>...</tr></thead>
            <tbody id="[prefix]Results"></tbody>
          </table>
        </div>
      </div>
    </div>
 </div>
</div>
```

---

## 13. Definition of Done Checklist
- [ ] I read the actual files and did not guess any name.
- [ ] I followed the patterns (screenMode / Modal Pattern / Toast / Confirm).
- [ ] Security: CSRF, server-side validation, no XSS/SQLi, transactions wrapped multi-table edits.
- [ ] UI is Arabic RTL, Bootstrap, consistent icons.
- [ ] I did not touch code unrelated to the task.
- [ ] I verified the result with a tool (read/run) and claimed nothing unproven.
- [ ] I updated the TODO list (if used) and summarized the status and remaining work.

---

> **Summary:** You are working on a sensitive Arabic accounting system. Security first, then visual consistency, then accuracy. **Verify, don't guess.**
