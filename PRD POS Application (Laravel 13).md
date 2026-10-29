# PRD: POS Application (Laravel 13)

Version 1.0 (Draft) · October 28, 2026 · Backend: Laravel 13 · Status: ready for workflow review

## 1. Overview

**TL;DR.** A lightweight, zero-license-cost Point of Sale for a small retail client. Cashiers sell fast (scan/search → cart → pay → receipt), the system keeps stock and cash drawers consistent, and the owner gets daily reports. Built as a Laravel 13 API plus a Vue 3 PWA on PostgreSQL, deployed with Docker Compose and Caddy. Five integrity rules drive every workflow: every stock change is a ledger entry, every sale needs an open shift, every sale is idempotent, closed shifts and completed sales are immutable, and corrections are new records (void, return, adjustment), never edits.

### 1.1 Problem

The client needs a cashier program that is free to run, light enough for modest hardware, easy for us to update on request, and reliable when several cashiers sell at the same time or the connection drops. Spreadsheets and paper do not keep stock and cash reconciled.

### 1.2 Goals

- **G1** Fast checkout: a 3-item cart paid in cash completes in about 10 seconds with a scanner or keyboard (design target, measured in UAT).
- **G2** Stock and cash are always reconcilable: any number on a report can be traced to ledger rows.
- **G3** Zero license cost; runs on modest hardware (low RAM, no Redis, no paid services).
- **G4** Easy to maintain: conventional Laravel structure, few moving parts, strong tests around money and stock.
- **G5** Selling continues during a short connection or server outage, then syncs without duplicates or lost sales.

### 1.3 Non-goals (MVP)

Customer accounts, loyalty and credit sales (bon/utang), multi-outlet, payment gateway or EDC integration, online store sync, promotion engine (buy X get Y), product variants and bundles, purchase orders and supplier accounting, F&B features (tables, kitchen printing), tax invoice (faktur pajak) generation, accounting export.

### 1.4 Assumptions (to confirm with the client)

- **A1** Retail store (not F&B), single outlet, one or more registers.
- **A2** Currency IDR (whole rupiah). Store timezone Asia/Jakarta. UI language Indonesian by default.
- **A3** Sizing baseline: 20 simultaneous cashiers. The real number is an open question (section 14).
- **A4** Hardware: PC or tablet browser; optional USB barcode scanner (acts as a keyboard); optional 58/80 mm thermal printer through the OS print dialog.
- **A5** Prices include tax by default. Tax is configurable and stays off until the client's accountant confirms the rate and mode.
- **A6** Quantities may be decimal (for example kg) only for products flagged for it.

## 2. Users and roles

Three fixed roles. PIN means a manager enters their PIN on the cashier's screen to approve that single action; the approval is logged with approver, requester and reason, and is not available offline.

| Capability | Cashier | Manager | Owner |
| --- | --- | --- | --- |
| Sell, hold and resume carts | ✓ | ✓ | ✓ |
| Open and close own shift | ✓ | ✓ | ✓ |
| Line or order discount up to the cashier limit | ✓ | ✓ | ✓ |
| Discount above the limit | PIN | ✓ | ✓ |
| Void a sale (same open shift only) | PIN | ✓ | ✓ |
| Return and refund | PIN | ✓ | ✓ |
| Cash in / cash out | ✓ | ✓ | ✓ |
| Receive stock, adjust stock, stock opname | — | ✓ | ✓ |
| Manage products, prices, categories | — | ✓ | ✓ |
| Force-close another user's shift | — | ✓ | ✓ |
| View own shift report | ✓ | ✓ | ✓ |
| View all reports and audit log | — | ✓ | ✓ |
| Manage users, registers, settings | — | — | ✓ |

## 3. Tech stack and decisions

| Layer | Choice | Why |
| --- | --- | --- |
| Backend | Laravel 13 (PHP 8.3 or higher) | Requested. Conventional structure is easy to maintain; Eloquent transactions and row locks cover our concurrency needs. |
| Auth | Sanctum SPA cookie auth, same origin | HttpOnly session cookie, no token in JS storage. Session lifetime is longer than a shift. A 401 during sync pauses the outbox; it never drops it. |
| Frontend | Vue 3 + Vite + Pinia + Tailwind CSS, inside the same repo (resources/js) | One repo, one deploy. Keeps the Vue 3 PWA direction already chosen. |
| Offline | Workbox service worker (vite-plugin-pwa) + IndexedDB (Dexie) | Catalog cache and a sale outbox need client-side state. This is why we do not use server-rendered Inertia pages. |
| Database | PostgreSQL | Row-level locks, CHECK constraints, partial unique indexes (needed for the one-open-shift rules). |
| Queue, cache, session | Database or file drivers | No Redis to run. Add Redis only if measurements demand it. |
| Web | Caddy + PHP-FPM (tuned pm.max\_children, OPcache) | Automatic HTTPS, small footprint. Octane is a later optimization only if load tests show the need. |
| Packaging | Docker Compose: caddy, app, postgres, worker, scheduler | Same image for app, worker and scheduler. |
| Authorization | Role enum + Laravel Policies | Three fixed roles do not justify a permissions package. |
| Quality | Pest, Larastan, Pint, GitHub Actions, k6 load tests | All free; focus tests on money and stock. |
| Printing | Browser print with CSS @page for 58/80 mm | No driver code in MVP. Direct ESC/POS is a later option. |

## 4. Domain rules (apply to every workflow)

- **R1 Money.** Stored as bigint rupiah. No floats anywhere.
- **R2 Quantity.** numeric(12,3). Decimals only when the product has allow\_decimal\_qty.
- **R3 Stock ledger.** stock\_movements is append-only. products.stock\_qty is a cached sum, updated in the same DB transaction while holding a row lock. A nightly job recomputes the sum and alerts on drift. Nobody edits stock\_qty directly.
- **R4 Snapshots.** Sale items copy product name, SKU, unit price and unit cost at sale time. Later price changes never alter history.
- **R5 Calculation order.** Line total = qty × unit price − line discount. Subtotal = sum of line totals. Order discount (amount, or percent converted to an amount at confirmation) is applied to the subtotal. Taxable base = subtotal − order discount. Tax is computed once per order: inclusive mode tax = round(base × r / (1 + r)), grand total = base; exclusive mode tax = round(base × r), grand total = base + tax. The order discount is allocated across lines proportionally (largest remainder so the parts sum exactly) to give each line a net\_total, which refunds and margin use.
- **R6 Payments.** Sum of payment.amount equals grand\_total exactly. Cash may be tendered above the amount due; change = tendered − cash amount applied, stored separately. Non-cash payments can never exceed the remaining due. Split payments are allowed.
- **R7 Invoice number.** `{REGISTER}-{yymmdd}-{seq}`, assigned when the sale is confirmed, sequence per register per day, unique constraint. Numbers are never reused because sales are never deleted.
- **R8 Idempotency.** Each checkout gets a client\_uuid when it starts. If the server sees the same uuid again it returns the existing sale and creates nothing.
- **R9 Immutability.** Completed sales, closed shifts, confirmed stock receipts and ledger rows are never edited or deleted. Corrections are new records.
- **R10 Time.** DB stores UTC; reports use the store timezone. Shift membership is defined by shift\_id, never by clock time.
- **R11 Negative stock.** Blocked online by default (setting allow\_negative\_stock). Sales that originated offline are always accepted, because the goods already left the store, and are flagged stock\_conflict for manager review.
- **R12 Price authority.** Online: the server re-prices from the database; if totals differ from the client's, it returns HTTP 409 with fresh totals and the cashier re-confirms. Offline-originated: the server trusts the client's price snapshots after validating the arithmetic.

## 5. Workflows

Each workflow lists preconditions, steps, postconditions and failure handling, in the order they must happen.

### W1. First-time setup (Owner, once)

1. Deploy, run migrations, seed the first owner account.
2. Settings: store name, address, phone, timezone, receipt footer, tax mode and rate, return window in days (default 7), cashier discount limit in percent, allow\_negative\_stock.
3. Create registers (code and name).
4. Create users and roles; managers set a PIN (stored hashed, rate-limited).
5. Create categories, then products (manual or CSV import: sku, barcode, name, category, price, cost, track\_stock, min\_stock).
6. Enter opening stock as a stock receipt of type opening, which writes ledger rows.

**Post:** system is configured, but nobody can sell until a shift is open.

### W2. Login

1. User signs in with email and password.
2. Rate limit applies to failed attempts.
3. The app loads the profile, role and (for cashiers) the current open shift if any.

**Edge:** inactive users cannot sign in. If the session expires mid-sale, the cart stays in local state and the user signs in again without losing it.

### W3. Open shift (Cashier)

**Pre:** authenticated, online, no open shift on the chosen register, and the cashier has no other open shift.

1. Choose the register.
2. Enter opening cash (zero or more).
3. Confirm. The server creates the shift (status open) and returns the catalog snapshot, settings and the last invoice sequence for this register and day.
4. The PWA caches the catalog and shows the sales screen.

**Edge:** the register has an open shift by someone else: blocked and shows who; a manager can force-close it (W12). An older shift is still open: it must be closed first. Opening a shift requires being online, so two devices can never open the same register.

### W4. Checkout (core flow)

**Pre:** open shift on this register; cart has at least one line.

1. **Build cart.** Scan barcode, search by name or SKU, or tap a product tile. A client\_uuid is generated when the first item is added. Rules: product must be active; qty greater than zero; if track\_stock and local stock is below the requested qty, show a warning (online hard check happens at confirmation).
2. **Edit.** Change qty, remove a line, apply a line discount.
3. **Order discount.** Allowed up to the cashier limit; above it requires a manager PIN (online only).
4. **Review totals** using the order in R5.
5. **Take payment.** Choose one or more methods: cash (enter tendered amount; quick-cash buttons for exact amount and the next rupiah denominations), QRIS, bank transfer or card (cashier verifies the payment on the phone, bank app or EDC, then marks it received and optionally records a reference). Validate with R6.
6. **Confirm.** Nothing has been persisted yet except the local draft. The client sends the sale; the server runs one DB transaction:
   1. If client\_uuid already exists, return that sale (idempotent).
   2. Verify the shift is open and belongs to this register and cashier.
   3. Lock the product rows with SELECT ... FOR UPDATE, ordered by product id to avoid deadlocks.
   4. Re-check stock (R11) and re-price (R12).
   5. Insert the sale, items (with snapshots), payments.
   6. Insert a stock\_movements row of type sale (negative qty) per tracked item and update stock\_qty.
   7. Commit.
7. **Receipt.** Show the receipt and print. The drawer expected-cash formula includes this sale automatically (W9).

**Failure paths:** out of stock at confirmation: nothing is saved, the cashier sees which lines to fix; price changed: 409 with fresh totals; network lost during confirm: the app retries with the same client\_uuid (safe), or moves the sale to the offline outbox (W13); print fails: the sale stays saved, reprint is available and marked COPY.

### W5. Hold and resume cart (Cashier)

1. Hold stores the cart locally on this device with a label and time. Held carts are not sales and do not reserve stock.
2. Resume reloads the cart, re-prices every line from the current catalog and re-checks stock, and shows any changes before continuing at W4 step 2.
3. A shift cannot be closed while the register has held carts; the cashier must resume or discard them.

### W6. Void a sale (Manager, or Cashier with PIN)

**Pre:** sale status completed, no returns on it, and its shift is the register's currently open shift.

1. Find the sale by invoice number or scan.
2. Enter a reason and obtain manager approval.
3. In one transaction: status becomes voided; stock\_movements of type void restore the quantities; audit log is written.
4. Cash from a voided sale drops out of expected cash automatically (voided sales are excluded). Non-cash payments are marked for manual reversal outside the system.

**Edge:** the shift is already closed, or any item was returned: voiding is blocked; use a return (W7). This keeps closed-shift totals immutable.

### W7. Return and refund (Manager, or Cashier with PIN)

**Pre:** original sale is completed or partially returned, within the return window (a manager can override), and for a cash refund the current register has an open shift.

1. Look up the original invoice (type or scan the receipt barcode).
2. Select items and quantities. The server enforces: returned qty per line never exceeds sold qty minus previously returned qty.
3. Choose for each line: restock (qty returns to stock) or write-off (damaged: no stock change, recorded for audit).
4. Refund amount per line = round(net\_total × returned qty / sold qty), capped so cumulative refunds on a line never exceed its net\_total.
5. Choose the refund method. Cash refund is paid from the open shift and reduces expected cash. Other methods are recorded for manual settlement.
6. In one transaction: create the return and items, ledger rows for restocked lines, update returned\_qty and the sale status (partially\_returned or returned), write the audit log.

**Note:** a return is recorded in the shift and on the date it happens, not on the original sale's date.

### W8. Cash in and cash out (Cashier)

**Pre:** open shift. Record the amount (greater than zero) and a reason (for example petty cash, bank deposit, change float). Each entry changes expected cash and appears on the shift report.

### W9. Close shift (Cashier)

**Pre:** the register's outbox has no unsynced sales, no held carts, and the device is online.

1. Cashier counts the drawer and enters the counted cash (blind count by default: expected cash is hidden until submission).
2. Server computes expected cash = opening cash + cash payments applied on non-voided sales in the shift + cash in − cash out − cash refunds paid in the shift.
3. Difference = counted − expected. A non-zero difference requires a note.
4. Status becomes closed; the shift summary (Z-report) is shown and printable: sales count and total, totals by payment method, discounts, tax, voids, returns, cash in/out, expected, counted, difference.

**Post:** a closed shift is immutable; new sales on that register require a new shift (W3).

### W10. Receive stock (Manager)

1. Create a stock receipt: supplier name (free text), reference, items with qty greater than zero and unit cost.
2. Confirm. In one transaction: ledger rows of type purchase, stock\_qty updated, product cost set to the latest unit cost.
3. The receipt cannot be edited afterwards. A mistake is fixed by a stock adjustment (W11).

### W11. Stock adjustment and opname (Manager)

1. Start an adjustment with a reason (opname, damaged, expired, lost, correction).
2. Enter the counted quantity per product. The system shows the current system quantity.
3. On confirm, the delta is counted − current system quantity (read under row lock). If the product had any movement since the screen was opened, the line is flagged and must be recounted.
4. Write ledger rows of type adjustment and an audit log entry with the reason.

**Operational rule:** run opname when the store is closed or the register is idle so counts do not race with sales.

### W12. Force-close a shift (Manager)

Used when a cashier left without closing. The manager enters the counted cash and a reason. If a sale from that shift arrives later through sync, it is attached to the shift, the shift is marked late\_adjusted, the report shows the delta, and it appears in the review queue. Nothing else about a closed shift changes.

### W13. Offline selling and sync

**Offline scope:** selling (W4, W5) and printing receipts. Everything else (open or close shift, void, return, stock, reports, PIN approvals) requires being online.

1. The PWA works from the cached catalog. The status bar shows online or offline and the number of pending sales.
2. A confirmed offline sale is written to the IndexedDB outbox with its client\_uuid, invoice number from the local register counter, and full price snapshots; the receipt prints normally.
3. When connectivity returns, the outbox is sent in order (POST /sales/batch). Per sale the server answers created, duplicate (treated as success) or rejected with a reason.
4. Rejected sales stay in the outbox flagged needs attention. They are never silently dropped. A network error is not a rejection and is retried with backoff.
5. Offline sales that exceed stock are accepted and flagged (R11). The manager resolves them with a stock receipt or adjustment.
6. If the session expired, sync pauses, the user signs in again, and the outbox resumes.

**Invoice numbering offline:** the register keeps a local counter; at login the server sends the last sequence for the register and day and the device continues from the higher value. A rare duplicate invoice number is rejected by the unique constraint and lands in the review queue.

### W14. Reporting (Manager, Owner)

- Daily sales: gross, discounts, tax, net; excludes voided; returns subtract on their own date.
- By product, category, cashier, payment method; gross margin from cost snapshots.
- Shift reports; low-stock list (stock at or below min\_stock); stock card (ledger per product); needs-review queue (stock\_conflict, late\_adjusted, rejected sync).
- Export CSV.

### W15. Backup and restore (Ops)

Nightly pg\_dump by the scheduler, kept for 7 days on a separate volume, with an optional offsite copy (for example rclone to free cloud storage). A restore drill on a clean machine is part of acceptance.

## 6. State machines

| Entity | Allowed transitions | Notes |
| --- | --- | --- |
| Sale | completed → voided; completed → partially\_returned → returned | voided and returned are terminal. Void only before any return and inside the open shift. |
| Shift | open → closed | One open shift per register and one per cashier (partial unique indexes). Closed is immutable except late\_adjusted flag. |
| Stock receipt | confirmed | Single step, never edited. |
| Outbox item | pending → synced, or pending → needs attention | needs attention is resolved by a manager, never auto-deleted. |

## 7. Data model (summary)

| Table | Key columns | Constraints |
| --- | --- | --- |
| users | name, email, password, pin\_hash, role, is\_active | role enum |
| registers | code, name, is\_active | unique code |
| categories | name, sort\_order |  |
| products | sku, barcode, name, category\_id, price, cost, track\_stock, allow\_decimal\_qty, stock\_qty, min\_stock, is\_active | unique sku, unique nullable barcode, soft delete, price and cost not negative |
| shifts | register\_id, cashier\_id, opened\_at, opening\_cash, closed\_at, counted\_cash, expected\_cash, difference, status, forced\_by, late\_adjusted | partial unique (register\_id) where open; partial unique (cashier\_id) where open |
| sales | client\_uuid, invoice\_no, shift\_id, register\_id, cashier\_id, status, subtotal, discount\_total, tax\_total, grand\_total, is\_offline, stock\_conflict, created\_at, received\_at, void fields | unique client\_uuid, unique invoice\_no |
| sale\_items | sale\_id, product\_id, name, sku, qty, unit\_price, unit\_cost, line\_discount, order\_discount\_alloc, net\_total, returned\_qty | qty greater than 0, returned\_qty not above qty |
| payments | sale\_id, method, amount, tendered, change\_amount, reference | amount greater than 0; sum equals grand\_total |
| sale\_returns, sale\_return\_items | sale\_id, shift\_id, return\_no, refund\_method, refund\_total, reason, approved\_by; item qty, refund\_amount, restock | qty not above returnable qty |
| cash\_movements | shift\_id, type, amount, reason, user\_id | amount greater than 0 |
| stock\_movements | product\_id, type, qty\_delta, ref\_type, ref\_id, user\_id, note, created\_at | append-only (DB trigger blocks UPDATE and DELETE), qty\_delta not zero |
| stock\_receipts, items | receipt\_no, supplier\_name, note; product\_id, qty, unit\_cost | qty greater than 0 |
| stock\_adjustments, items | reason, user\_id; product\_id, system\_qty, counted\_qty, delta |  |
| audit\_logs | user\_id, action, entity, entity\_id, before, after, approved\_by, created\_at | append-only |
| settings | key, value |  |

## 8. API (REST, /api/v1, JSON)

| Endpoint | Purpose |
| --- | --- |
| POST /auth/login, POST /auth/logout, GET /me | Session handling |
| GET /catalog?since= | Delta sync of products, categories, settings (inactive products included so caches can remove them) |
| POST /shifts/open, GET /shifts/current, POST /shifts/{id}/close, POST /shifts/{id}/force-close | Shift lifecycle |
| POST /sales | Single sale, idempotent by client\_uuid |
| POST /sales/batch | Outbox sync with per-item result |
| GET /sales?invoice= | Lookup for void and return |
| POST /sales/{id}/void, POST /sales/{id}/returns | Corrections |
| POST /approvals | Verify manager PIN, returns a short-lived single-use approval bound to the action |
| POST /cash-movements | Cash in and out |
| POST /stock-receipts, POST /stock-adjustments, GET /products/{id}/stock-card | Inventory |
| GET /reports/\* | Reports |

All endpoints use Form Requests for validation, Policies for authorization, and API Resources for output.

## 9. UI and UX

**Design reference.** The Dribbble design could not be loaded in this session (the image could not be fetched), so this section describes a conventional modern POS layout and must be aligned with the reference once the screenshot is shared.

- **Sales screen (tablet and desktop):** left area with search or scan bar, category chips and a product grid; right panel with the order (lines, qty steppers, discount, subtotal, tax, grand total) and a large Pay button. Top bar: store, register, cashier, shift status, online/offline indicator, pending-sync count.
- **Payment dialog:** amount due, method tabs, numeric keypad, quick-cash buttons, live change, split payment rows, Confirm.
- **Keyboard:** F2 focus search, F4 hold, F8 pay, Esc back, Enter confirm.
- **Other screens:** shift open and close, sales history with void and return, products, stock, reports, users and settings.
- **Principles:** touch targets of at least 44 px, readable on a 10-inch tablet, high-contrast totals, no blocking spinners on cart actions (cart state is local), clear error text that says what to do next.

## 10. Non-functional requirements

- **Performance (targets to validate by k6 on the real hardware):** cart interactions respond instantly from local state; checkout API p95 under 500 ms at the sizing baseline; catalog delta payload small.
- **Footprint:** design target of roughly 1 GB RAM for caddy, app, postgres, worker and scheduler together, confirmed by measurement before go-live.
- **Concurrency:** row locks in a fixed order, short transactions, idempotent writes; two registers selling the last unit must produce exactly one success.
- **Security:** HTTPS only, APP\_DEBUG off in production, login rate limiting, hashed passwords and PINs, Policy check on every endpoint, least-privilege DB user, audit log for sensitive actions, `composer audit` and `npm audit` in CI.
- **Reliability:** nightly backup and restore drill, nightly stock reconciliation, structured logs.
- **Accessibility and locale:** Indonesian UI, rupiah formatting with thousands separators, dates in store timezone.

## 11. Deployment and operations

- Docker Compose on a Linux host: caddy (TLS and reverse proxy), app (PHP-FPM), postgres, worker (queue:work), scheduler (schedule:work).
- **Hosting options (open decision):** (a) a mini PC or old PC in the store LAN, which costs nothing and keeps selling even if the internet is down; (b) a small VPS or free tier. Note that service workers (needed for the PWA) require HTTPS or localhost, so a LAN setup needs either Caddy internal certificates trusted on each device, or a real domain pointed at the LAN address with a normal certificate.
- Deploy steps: pull, build, migrate, restart; use maintenance mode for the migration window.
- CI: lint (Pint), static analysis (Larastan), tests (Pest), build assets.

## 12. Scope (MVP)

| Must | Should | Later |
| --- | --- | --- |
| Roles and auth, products and categories, stock ledger, shifts, checkout with split payments, receipts, hold and resume, void, return, cash in/out, stock receipt and adjustment, core reports, backup | Offline outbox and sync, CSV import and export, low-stock alerts, keyboard shortcuts | Customer and credit sales, promotions, multi-outlet, direct ESC/POS printing, camera barcode scanning, payment integrations |

## 13. Logic safeguards and acceptance tests

| Scenario | Expected result |
| --- | --- |
| Two registers sell the last unit at the same moment | Exactly one sale succeeds; the other gets an out-of-stock error and nothing is saved |
| Cashier double-taps Confirm or the network retries | One sale only (same client\_uuid) |
| Sale attempted with no open shift | Rejected |
| Opening a second shift on a register or for a cashier who already has one | Rejected by unique index and by validation |
| Non-cash payment larger than the remaining due | Rejected |
| Cash tendered above due | Change computed, payment amounts still sum to grand total |
| Void after the shift is closed, or after a return | Blocked; return flow works |
| Void in the open shift | Stock restored, expected cash excludes the sale, audit written |
| Return quantity above sold minus already returned | Rejected |
| Two partial returns on one line | Total refund never exceeds the line net\_total |
| Close shift with unsynced offline sales or held carts | Blocked |
| Offline sale oversells stock after sync | Accepted, flagged for review, stock shown negative until resolved |
| Product price changed after a sale | Old sale and its receipt reprint unchanged |
| Sum of stock\_movements per product versus stock\_qty | Equal; nightly job alerts on drift |
| Restore a backup on a clean machine | App starts and totals match |

## 14. Risks and open questions

| Item | Impact | Mitigation or question |
| --- | --- | --- |
| Store type may be F&B, not retail | Different workflows (tables, kitchen) | Confirm before build |
| Number of registers and concurrent users | Sizing and tuning | Agree on a baseline number |
| LAN vs internet hosting | Offline importance, HTTPS setup | Decide in milestone 0 |
| Tax (PPN) treatment | Wrong totals | Accountant to confirm mode and rate |
| Credit sales (bon/utang) and customer accounts | Common in small stores, out of MVP | Ask the client if needed in phase 2 |
| Decimal quantities (kg) | Scale input and UI | Confirm product types |
| Printer model, paper width, scanner | Receipt layout | Collect hardware list |
| Duplicate invoice number after device storage loss | Rare, manual resolution | Unique constraint plus review queue |
| IndexedDB cleared with pending sales | Lost unsynced sales | Warn before logout with pending sales; sync at shift end |

## 15. Milestones (build order)

The order follows the dependencies: a sale needs a shift, and a shift needs a register and a user.

1. **M0 Foundation:** repo, Docker Compose, Caddy, auth, roles, settings, CI, base layout.
2. **M1 Catalog and stock ledger:** categories, products, CSV import, stock receipt, adjustment, stock card.
3. **M2 Registers and shifts:** open and close shift, cash in/out, Z-report.
4. **M3 Checkout (online):** cart, discounts, payments, idempotent confirm, receipt print.
5. **M4 Corrections:** hold and resume, manager PIN approvals, void, return and refund.
6. **M5 Reports and dashboards.**
7. **M6 Offline PWA:** catalog cache, outbox, batch sync, review queue.
8. **M7 Hardening:** concurrency tests, k6 load test, backup and restore drill, UAT with the client.
