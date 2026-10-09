# BulkBites BBQ — System Architecture (Phase 0)

**Project:** BulkBites BBQ — Digital Ordering and Business Management System
**Based on:** Treb's Ihawan, Greensite Compound, Sta. Lucia, Pasig City (near SM City East)
**Stack:** HTML5 · CSS3 · JavaScript · PHP · MySQL · XAMPP (Apache, phpMyAdmin)
**Status:** Phase 0 complete. Implementation begins at Phase 1 (Database) upon approval.

---

## 1. Recommended Folder Structure

```
BulkBites/
│
├── index.php                     # Public landing page → menu entry point
├── login.php                     # Thin entry wrapper → auth/login.php
├── logout.php                    # Thin entry wrapper → auth/logout.php
├── database.sql                  # Full MySQL schema + seed data (importable in phpMyAdmin)
├── config.example.php            # Safe template of config values (committed to GitHub)
├── config/
│   └── database.php              # Real DB credentials + app settings (NOT committed)
├── README.md                     # Setup instructions for XAMPP
├── .gitignore                    # Excludes config/database.php, uploads private, logs
│
├── auth/
│   ├── login.php                 # Phone number entry + human verification
│   ├── request_otp.php           # OTP issuance (POST handler, rate limited)
│   ├── verify_otp.php            # OTP verification → session creation
│   ├── resend_otp.php            # Resend with cooldown
│   └── logout.php                # Session destruction
│
├── customer/
│   ├── dashboard.php             # Customer home (role-gated)
│   ├── menu.php                  # Browse products (images, price, stock)
│   ├── cart.php                  # Quantity change, remove, reset order
│   ├── checkout.php              # Server-side validation + transaction
│   ├── orders.php                # Order history + live status
│   ├── receipt.php               # View/print receipt (own orders only)
│   ├── notifications.php         # Customer notifications
│   └── profile.php               # Customer profile
│
├── staff/
│   ├── dashboard.php             # Order summary + low stock + notifications
│   ├── orders.php                # Incoming order management
│   ├── kitchen.php               # Kitchen Display System (KDS) board
│   ├── inventory.php             # Stock monitoring (read/update stock)
│   └── notifications.php         # Staff notifications
│
├── manager/
│   ├── dashboard.php             # Sales/orders stats overview
│   ├── products.php              # CRUD products + prices + images
│   ├── categories.php            # CRUD categories
│   ├── inventory.php             # Stock, thresholds, restock, availability
│   ├── customers.php             # Customer list
│   ├── users.php                 # Staff/manager accounts
│   ├── reports.php               # Sales / best-sellers / low-stock reports
│   └── settings.php              # Business settings (store name, OTP mode, etc.)
│
├── api/                          # JSON endpoints for AJAX (same session auth as pages)
│   ├── auth.php                  # Session status checks
│   ├── products.php              # Product/stock data for cart validation
│   ├── orders.php                # Create order, status actions, order polling
│   ├── inventory.php             # Stock queries/updates (role-checked)
│   └── notifications.php         # Poll new notifications (read/unread)
│
├── includes/                     # Shared server-side components (no direct URLs)
│   ├── bootstrap.php             # session_start, config load, db connect, helpers
│   ├── database.php              # PDO connection helper
│   ├── auth_check.php            # Requires logged-in user; else redirect
│   ├── role_check.php            # require_role('STAFF'|'MANAGER'|'CUSTOMER')
│   ├── csrf.php                  # CSRF token generate/verify
│   ├── functions.php             # Sanitize, peso format, flash, order number, JSON out
│   ├── otp.php                   # OTP generate/hash/verify/rate-limit logic
│   ├── sms.php                   # SmsSender interface + Debug + provider adapters
│   ├── checkout.php              # Transactional checkout function (reused by page + api)
│   ├── header.php                # <head>, nav, topbar (role-aware)
│   ├── footer.php                # Scripts, closing markup
│   └── components.php            # status_badge(), flash_messages(), pagination()
│
├── assets/
│   ├── css/
│   │   ├── style.css             # Global theme (charcoal/amber/red identity)
│   │   ├── kds.css               # KDS board-specific styles
│   │   └── print.css             # Receipt printing
│   ├── js/
│   │   ├── app.js                # Shared UI: toasts, confirm dialogs, fetch helper
│   │   ├── password-toggle.js    # 👁 visibility toggle for password fields
│   │   ├── cart.js               # Cart quantity/reset interactions
│   │   ├── checkout.js           # Checkout loading + error states
│   │   ├── kds.js                # KDS polling + status advance buttons
│   │   └── notifications.js      # Notification polling/badge
│   └── images/                   # Logo, product images, placeholders
│
├── uploads/                      # Product images (manager upload) — gitignored content
│
└── docs/
    └── ARCHITECTURE.md           # This document
```

### Deviations from the suggested structure (with reasons)

1. **`config.example.php` at root + real `config/database.php` gitignored** — required by the GitHub/no-secrets rule. The example file documents every setting without exposing credentials.
2. **`includes/bootstrap.php` and `includes/database.php`** — one place that starts the session hardening, loads config, and opens the PDO connection, so no page forgets a security step.
3. **`includes/checkout.php`** — checkout logic is a reusable function so `customer/checkout.php` and `api/orders.php` share one transaction implementation instead of duplicating it.
4. **`includes/otp.php` + `includes/sms.php`** — OTP rules (expiry, attempts, cooldown) and SMS delivery are separated so a real SMS provider can be plugged in later without touching the login flow.
5. **`staff/kitchen.php`** instead of only `orders.php` — the KDS has a distinct large-touch-target layout (`kds.css`, `kds.js`).
6. **Root `login.php` / `logout.php` kept as thin wrappers** — required URLs stay stable while logic lives in `auth/`.
7. **`.gitignore`** — excludes `config/database.php`, `uploads/*` content, and any log files.

**Folder responsibilities**

| Folder | Responsibility |
|---|---|
| root (`*.php`) | Public entry points only; almost no logic |
| `auth/` | OTP request/verify/resend/logout — the only unauthenticated logic |
| `customer/` | Pages reachable only with `role = CUSTOMER` |
| `staff/` | Pages reachable only with `role = STAFF` (or MANAGER where allowed) |
| `manager/` | Pages reachable only with `role = MANAGER` |
| `api/` | JSON responses for AJAX; same session/role/CSRF checks as pages |
| `includes/` | Shared PHP building blocks; never linked to directly |
| `assets/` | Static CSS/JS/images (served by Apache directly) |
| `uploads/` | User-generated product images |
| `config/` | Credentials and settings; never web-readable secrets |

---

## 2. Database Architecture (ERD / Schema Plan)

**Conventions:** InnoDB engine (required for transactions), `utf8mb4` (for ₱ and Filipino text), `DECIMAL(10,2)` for money, `DATETIME` with `Asia/Manila` app timezone, every table has `created_at` / `updated_at` where meaningful, surrogate `INT AUTO_INCREMENT` primary keys, real `FOREIGN KEY` constraints.

### Tables

**`users`** — single authentication/role table for all three roles
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| full_name | VARCHAR(100) NOT NULL | |
| phone | VARCHAR(20) UNIQUE NOT NULL | Login key for all roles; format validated `09XXXXXXXXX` |
| role | ENUM('CUSTOMER','STAFF','MANAGER') NOT NULL | Indexed |
| password_hash | VARCHAR(255) NULL | `password_hash()`; NULL for customers (OTP-only), set for staff/manager |
| is_active | TINYINT(1) DEFAULT 1 | Account disable switch |
| created_at, updated_at | DATETIME | |

**`customers`** — 1:1 profile extension for the CUSTOMER role (no duplicated credentials)
| Column | Type | Notes |
|---|---|---|
| user_id | INT PK, FK → users(id) ON DELETE CASCADE | |
| display_name | VARCHAR(100) | Name shown on receipts |
| email | VARCHAR(100) NULL | Optional |
| address | VARCHAR(255) NULL | Barangay/address for bulk orders |
| remarks | TEXT NULL | Manager notes |
| created_at, updated_at | DATETIME | |

**`categories`**
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| name | VARCHAR(60) UNIQUE NOT NULL | e.g. "Chicken", "Pork" |
| sort_order | INT DEFAULT 0 | |
| is_active | TINYINT(1) DEFAULT 1 | |

**`products`**
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| category_id | INT FK → categories(id) | Indexed |
| name | VARCHAR(100) NOT NULL | BBQ, Isaw Manok, Bituka Baboy, Tenga Baboy, Balunan, Hotdog |
| description | VARCHAR(255) NULL | |
| price | DECIMAL(10,2) NOT NULL | **Authoritative price — never from the browser** |
| image | VARCHAR(255) NULL | Path under `uploads/` or `assets/images/` |
| is_active | TINYINT(1) DEFAULT 1 | Inactive = hidden from menu and unorderable |
| created_at, updated_at | DATETIME | |

**`inventory`** — 1:1 with products (stock is NOT stored on `products`, so stock logic stays in one place)
| Column | Type | Notes |
|---|---|---|
| product_id | INT PK, FK → products(id) ON DELETE CASCADE | |
| quantity_available | INT NOT NULL DEFAULT 0 | Current stock (sticks) |
| unit | VARCHAR(20) DEFAULT 'pcs' | |
| low_stock_threshold | INT NOT NULL DEFAULT 10 | Drives LOW STOCK status |
| is_available | TINYINT(1) DEFAULT 1 | Manual availability toggle (sold out for the day) |
| updated_by | INT FK → users(id) NULL | Who last changed it |
| last_updated | DATETIME | |

**`inventory_movements`** *(recommended addition)* — audit trail for stock
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| product_id | INT FK → products(id) | Indexed |
| movement_type | ENUM('ORDER_DEDUCT','RESTOCK','ADJUSTMENT','WASTE') | |
| quantity_change | INT (+/−) | |
| reference_order_id | INT FK → orders(id) NULL | Set for checkout deductions |
| performed_by | INT FK → users(id) NULL | NULL = system (checkout) |
| remarks | VARCHAR(255) NULL | |
| created_at | DATETIME | |

*Reason:* without it, restocks and checkout deductions are unexplained; managers can answer "where did the stock go".

**`orders`**
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| order_number | VARCHAR(20) UNIQUE NOT NULL | e.g. `BB-261007-0042` (generated at commit time) |
| customer_id | INT FK → users(id) | Indexed with `created_at` |
| status | ENUM('NEW','ACCEPTED','PREPARING','READY','COMPLETED','CANCELLED') DEFAULT 'NEW' | Indexed |
| subtotal | DECIMAL(10,2) NOT NULL | Computed server-side from product prices |
| total | DECIMAL(10,2) NOT NULL | |
| payment_method | ENUM('CASH','GCASH') DEFAULT 'CASH' | |
| notes | VARCHAR(255) NULL | Customer instructions |
| handled_by | INT FK → users(id) NULL | Staff who accepted/completed |
| created_at, updated_at | DATETIME | Indexed on `created_at` for daily reports |

**`order_items`**
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| order_id | INT FK → orders(id) ON DELETE CASCADE | Indexed |
| product_id | INT FK → products(id) | |
| product_name | VARCHAR(100) NOT NULL | **Snapshot** — survives product renames |
| unit_price | DECIMAL(10,2) NOT NULL | **Snapshot of DB price at order time** |
| quantity | INT NOT NULL CHECK (quantity > 0) | |
| line_total | DECIMAL(10,2) NOT NULL | unit_price × quantity |

**`receipts`** — 1:1 with orders
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| order_id | INT UNIQUE FK → orders(id) | |
| receipt_number | VARCHAR(20) UNIQUE NOT NULL | e.g. `RC-261007-0042` |
| issued_at | DATETIME | Created inside the checkout transaction |
| total | DECIMAL(10,2) NOT NULL | Denormalized for fast report joins |
| payment_method | VARCHAR(10) NOT NULL | |

**`otp_records`** — short-lived, hashed, auto-expiring
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| phone | VARCHAR(20) NOT NULL | Indexed |
| purpose | ENUM('LOGIN') DEFAULT 'LOGIN' | Extensible later |
| otp_hash | VARCHAR(255) NOT NULL | `password_hash()` of the code — **never plaintext** |
| attempts | INT DEFAULT 0 | |
| max_attempts | INT DEFAULT 3 | |
| expires_at | DATETIME NOT NULL | e.g. now + 5 minutes |
| consumed_at | DATETIME NULL | Set on successful verify |
| resend_after | DATETIME NOT NULL | Cooldown gate (e.g. +60 s) |
| request_ip | VARCHAR(45) | Rate-limiting / audit |
| created_at | DATETIME | Indexed (phone, created_at) for hourly request caps |

**`notifications`** — one row per recipient (scale is tiny: a few staff + one customer)
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| user_id | INT FK → users(id) | Recipient; indexed with `is_read` |
| order_id | INT FK → orders(id) NULL | |
| type | ENUM('NEW_ORDER','ORDER_STATUS','LOW_STOCK','GENERAL') | |
| title | VARCHAR(120) NOT NULL | |
| message | VARCHAR(255) NOT NULL | |
| is_read | TINYINT(1) DEFAULT 0 | |
| created_at | DATETIME | Indexed |

**`order_status_history`** — full audit of order lifecycle
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| order_id | INT FK → orders(id) ON DELETE CASCADE | Indexed |
| old_status | VARCHAR(20) NULL | NULL on creation |
| new_status | VARCHAR(20) NOT NULL | |
| changed_by | INT FK → users(id) NULL | NULL = system |
| remarks | VARCHAR(255) NULL | |
| created_at | DATETIME | |

**`rate_limits`** *(recommended addition)* — generic server-side throttle for OTP requests and login attempts
| Column | Type | Notes |
|---|---|---|
| id | INT PK AI | |
| action | VARCHAR(40) NOT NULL | e.g. `otp_request`, `otp_verify` |
| identifier | VARCHAR(64) NOT NULL | phone or IP |
| window_start | DATETIME NOT NULL | |
| attempts | INT DEFAULT 1 | |
| UNIQUE(action, identifier, window_start) | | |

**`settings`** *(optional)* — store name, low-stock alert toggle, OTP delivery mode.

### Entity relationships

```
users 1 ──── 1 customers            (only role=CUSTOMER)
users 1 ──── n orders                (customer_id)
users 1 ──── n order_status_history  (changed_by)
users 1 ──── n notifications         (user_id)
users 1 ──── n inventory_movements   (performed_by)
users 1 ──── n otp_records           (by phone, logical)

categories 1 ──── n products
products   1 ──── 1 inventory
products   1 ──── n order_items
products   1 ──── n inventory_movements

orders 1 ──── n order_items
orders 1 ──── 1 receipts
orders 1 ──── n order_status_history
orders 1 ──── n notifications
orders 1 ──── n inventory_movements   (reference_order_id)
```

### Key indexes
- `orders(customer_id, created_at)`, `orders(status)`, `orders(created_at)`
- `order_items(order_id)`
- `products(category_id, is_active)`
- `notifications(user_id, is_read, created_at)`
- `otp_records(phone, created_at)`
- `inventory_movements(product_id, created_at)`

---

## 3. User-Role Permission Matrix

Legend: ✅ allowed · ❌ denied · 👁 read-only

| Capability | CUSTOMER | STAFF | MANAGER |
|---|---|---|---|
| Browse menu / products | ✅ (public + logged-in) | 👁 | ✅ |
| Add to cart / change qty / reset order | ✅ | ❌ | ❌ |
| Checkout (create order) | ✅ | ❌ | ❌ |
| View **own** order history & status | ✅ | ❌ | ❌ |
| View **own** receipts / print | ✅ | ❌ | ❌ |
| View all orders | ❌ | ✅ | ✅ |
| Accept / update order status (KDS) | ❌ | ✅ | ✅ |
| Kitchen Display System | ❌ | ✅ | ✅ |
| View inventory / stock | ❌ | 👁 (+ update stock) | ✅ (full) |
| Receive new-order notifications | ❌ | ✅ | ✅ |
| Manage products (add/edit/price/image/active) | ❌ | ❌ | ✅ |
| Manage categories | ❌ | ❌ | ✅ |
| Manage stock levels / thresholds / restock | ❌ | ✅ (stock adjustments only) | ✅ |
| View customers list | ❌ | ❌ | ✅ |
| Manage staff/manager accounts | ❌ | ❌ | ✅ |
| View statistics & reports | ❌ | ❌ | ✅ |
| Change app settings | ❌ | ❌ | ✅ |
| Manage own profile | ✅ | ✅ | ✅ |
| Logout | ✅ | ✅ | ✅ |

**Enforcement:** every protected PHP page begins with `require role_check.php` on the **server**. Client-side hiding of menu items is convenience only — typing a URL must never bypass authorization. `api/*` endpoints apply the same matrix and return HTTP 403 JSON on violation.

---

## 4. Customer Workflow

```
Guest browses menu (public)
        │
        ▼
Select product ──▶ Add to Cart (session-based cart, qty validated ≥1)
        │
        ▼
Cart page: change quantity · remove item · RESET ORDER (confirm dialog)
        │
        ▼
Checkout (login required → OTP flow if not logged in)
        │
        ▼
Server: BEGIN TRANSACTION
   1. verify each product exists & is_active
   2. verify inventory.is_available and quantity_available ≥ requested
   3. read authoritative price from products table (ignore client prices)
   4. INSERT orders (status=NEW, server-computed totals, generated order_number)
   5. INSERT order_items (price/name snapshots)
   6. UPDATE inventory: quantity_available = quantity_available - qty
        (guarded UPDATE ... WHERE quantity_available >= qty; check affected rows)
   7. INSERT receipts (receipt_number, issued_at)
   8. INSERT notifications for customer + each active staff/manager
   9. INSERT order_status_history (NULL → NEW)
        │
   any failure ──▶ ROLLBACK ──▶ friendly error, cart untouched, stock restored
        │
       COMMIT
        │
        ▼
Customer sees confirmation + order number
        │
        ▼
My Orders page polls status (NEW → ACCEPTED → PREPARING → READY → COMPLETED)
   + notification on every change
        │
        ▼
Receipt viewable & printable; order appears in history
```

Cart design: session-based (`$_SESSION['cart'] = [product_id => qty]`) — no price/stock stored in the cart, only product IDs and quantities, so checkout always re-reads MySQL. Reset Order uses a JS confirmation dialog before clearing.

---

## 5. Staff / KDS Workflow

```
Customer checkout ──▶ notification row per staff/manager (NEW_ORDER)
        │
        ▼
Staff dashboard / KDS polls every ~10 s (fetch → JSON)
        │
        ▼
KDS card (big touch targets: order #, items, qty, age, status button)
        │
        ▼
NEW ──accept──▶ ACCEPTED ──start──▶ PREPARING ──done──▶ READY ──handover──▶ COMPLETED
  └────────────────────── cancel (confirm) ──────────────▶ CANCELLED
        │
        ├── every transition: role check → validate allowed transition
        │      → UPDATE orders.status → INSERT order_status_history
        │      → INSERT notification for the customer
        ▼
Customer's My Orders page shows the new status on next poll
```

- KDS optimized for speed: large buttons, one tap per transition, minimal navigation, color-coded status columns (NEW / IN PROGRESS / READY).
- Invalid transitions (e.g. COMPLETED → NEW) are rejected server-side.
- Staff also sees low-stock alerts and can adjust stock for waste/spoilage (recorded in `inventory_movements`).

---

## 6. Manager Workflow

```
MANAGER login (phone + password)
        │
        ▼
Dashboard: total orders today · total sales today · pending · completed
           · customer count · best-sellers · low-stock list · recent transactions
        │
        ├── Products  → add/edit/deactivate products, set prices, upload images
        ├── Categories→ add/edit/sort categories
        ├── Inventory → current stock, thresholds, restock (+ RESTOCK movement), availability
        ├── Customers → list, contact info, order totals
        ├── Users     → create/deactivate staff & manager accounts, reset passwords
        ├── Reports   → sales by date range, best sellers, low-stock report, status summary
        └── Settings  → store name, OTP delivery mode, thresholds
```

Manager can access staff pages (order management, KDS, inventory) because role checks allow `MANAGER` wherever `STAFF` is allowed; staff can never reach `manager/*`.

---

## 7. Authentication Architecture

### Customer: phone → OTP

```
login.php (GET)
  └─ phone input + ☐ I am human checkbox + CSRF token (rendered by server)
        │ POST
        ▼
request_otp.php — server-side steps, in order:
  1. Verify CSRF token
  2. Verify human-verification token (server-issued, signed/stored server-side)
  3. Validate phone format: ^09[0-9]{9}$
  4. Rate limit: max 5 OTP requests per phone per hour (rate_limits table),
     max 10 per IP per hour
  5. Cooldown: reject if now < existing record's resend_after (60 s)
  6. Generate 6-digit code (random_int), store ONLY password_hash(code)
     in otp_records with expires_at = now + 5 min, attempts = 0
  7. Send via SmsSender::send(phone, code)
        │
        ▼
verify_otp.php (GET form, masked phone) — POST:
  1. CSRF check
  2. Load latest unconsumed, unexpired otp_records row for that phone
     → not found / expired → "Code expired, request a new one"
  3. attempts ≥ max_attempts (3) → invalidate code, force resend
  4. Verify with password_verify(code, otp_hash) → increment attempts on failure
  5. Success: mark consumed_at, session_regenerate_id(true),
     set $_SESSION['user_id'], ['role'], ['phone'], login timestamp
  6. Redirect by role: CUSTOMER → customer/dashboard.php
        │
resend_otp.php → same cooldown + rate limits as request_otp.php
logout.php → clear session data, destroy cookie, session_destroy(), redirect home
```

**OTP rules:** 6 digits · expiry 5 minutes · max 3 verify attempts · 60-second resend cooldown · max 5 requests/hour per phone · hashed at rest · single-use · server-side verification always.

**SMS pluggability (`includes/sms.php`):**

```php
interface SmsSender { public function send(string $phone, string $message): bool; }

class DebugSmsSender implements SmsSender { /* local XAMPP: writes code to server
    log and, when settings.otp_delivery_mode = 'debug', reveals it on the verify
    page with a clear "development mode" notice */ }

class ProviderSmsSender implements SmsSender { /* future: cURL to a real provider
    (e.g. an HTTP SMS API) using credentials read from config — no code change
    in request_otp.php */ }
```

The login flow only ever calls `SmsSender::send()` — switching to a real SMS provider is a config + one new class, not a rewrite. The OTP is never permanently stored as plaintext.

### Staff / Manager: phone + password
- `password_hash()` / `password_verify()` (bcrypt), password field has the 👁 visibility toggle.
- Accounts created by MANAGER in `manager/users.php`; customers cannot use this form.
- Same session hardening and rate limiting on failed logins (e.g. 5 attempts / 15 min per phone).

### Session security (all roles)
- `session.use_strict_mode`, `session.cookie_httponly = 1`, `SameSite=Lax`, `session_regenerate_id(true)` on every privilege change
- Idle timeout (e.g. 30 min customer / 8 h staff) + absolute lifetime
- `auth_check.php` verifies `$_SESSION['user_id']` **and** re-reads the user's role/active status from MySQL (a deactivated account is logged out on next request)
- No secrets or OTP codes ever placed in session or URLs

### Human verification ("☐ I am human")
- Custom checkbox is **not** Google reCAPTCHA and will not be described as equivalent.
- Server-side defense: an HMAC-signed or session-bound token issued when the form renders must be present and valid on POST, plus the rate limits above and CSRF — a bot must fetch the page, hold session state, and stay under throttles.

---

## 8. Checkout Workflow (transaction detail)

Executed by `includes/checkout.php::place_order($pdo, $customerId, $cart, $meta)`:

```
BEGIN TRANSACTION;                       // PDO::beginTransaction, InnoDB
SET innodb_lock_wait_timeout handled by defaults

FOR EACH cart line (product_id, qty):
    SELECT price, is_active, name FROM products WHERE id = ? AND is_active = 1
        FOR UPDATE-less read is fine; products row is locked only if price changes
    → missing/inactive ⇒ ROLLBACK ("An item in your cart is no longer available")

SELECT quantity_available, is_available FROM inventory WHERE product_id = ?
    FOR UPDATE                            // row locks prevent oversell races
    → !is_available OR quantity < qty ⇒ ROLLBACK ("Insufficient stock: X")

price_total += db_price × qty            // browser prices are NEVER read

INSERT INTO orders      (order_number, customer_id, status, subtotal, total, ...)
INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity, line_total)
FOR EACH line:
    UPDATE inventory SET quantity_available = quantity_available - ?
      WHERE product_id = ? AND quantity_available >= ?   // atomic guard
    → affected_rows == 0 ⇒ ROLLBACK
    (log ORDER_DEDUCT movement if inventory_movements adopted)
INSERT INTO receipts    (order_number, order_id, issued_at, total, ...)
INSERT INTO notifications (customer + each active staff/manager, NEW_ORDER)
INSERT INTO order_status_history (order_id, NULL, 'NEW', NULL)

COMMIT;                                   // any exception → ROLLBACK + friendly error
```

Additional rules:
- Order number + receipt number generated **inside** the transaction, unique-constrained
- Checkout form submission is single-flight: button disabled + loading state on the client, and a one-time order token per cart render on the server to block double-submit
- Stock check, price retrieval, and totals all happen on the server; the browser sends only `product_id → qty`
- On ROLLBACK the customer keeps their cart and sees exactly which item failed

---

## 9. Inventory Workflow

```
Seed (Phase 1): each product gets an inventory row (qty, unit, threshold)

READ SIDE
  Menu        → stock shown, "Sold out" when qty = 0 or is_available = 0
  Staff page  → all stock, badges: IN STOCK / LOW STOCK / OUT OF STOCK
  Manager     → same + thresholds + last update + who updated

WRITE SIDE
  Checkout        → automatic deduction (guarded UPDATE, inside transaction)
  Manager restock → +qty with RESTOCK movement, updated_by, timestamp
  Staff adjustment→ waste/spoilage −qty with remarks (ADJUSTMENT/WASTE movement)
  Manager         → toggle is_available (pause a product without deleting it)

STATUS (derived, not stored)
  OUT OF STOCK : quantity_available = 0 OR is_available = 0
  LOW STOCK    : 0 < quantity_available ≤ low_stock_threshold
  IN STOCK     : quantity_available > low_stock_threshold

TRIGGERS
  After any deduction, if status becomes LOW/OUT → notifications
  (LOW_STOCK) to MANAGER role + show on staff/manager dashboards
```

Customers can never order more than available stock: menu caps the quantity selector, **and** the checkout transaction enforces it again atomically (UI cap is a convenience, DB guard is the rule).

---

## 10. Notification Workflow

```
EVENT SOURCES
  order created (NEW_ORDER)            → rows for customer + every active staff/manager
  status changed (ORDER_STATUS)        → row for the customer (+ staff who need it)
  stock crossed threshold (LOW_STOCK)  → rows for active managers
  general/settings (GENERAL)           → targeted rows

DELIVERY
  In-app only for this system (no SMS cost):
    customer: bell badge + /customer/notifications.php (poll ~15 s)
    staff:    dashboard strip + KDS toast (poll ~10 s)
  Polling endpoints (api/notifications.php) return unread count + latest N rows,
  filtered by session user_id — a user can only ever read their own rows.

READ STATE
  "Mark read" / "Mark all read" UPDATE is_read = 1 (owner-checked)
  Unread count drives the badge

FUTURE
  The same event functions could later call SmsSender for SMS/push —
  insert the notification row first, delivery adapter second.
```

Why polling: Apache/XAMPP has no WebSocket support out of the box; short-interval `fetch` polling is simple, GitHub-friendly, and adequate for ~100 daily customers. Keep intervals modest to limit server load.

---

## 11. Receipt Workflow

```
Inside checkout transaction:
  INSERT receipts (receipt_number = RC-YYMMDD-NNNN, order_id UNIQUE, issued_at, total, payment_method)

VIEW
  customer/receipt.php?order=...  (or /receipt/{order_number})
  ├─ ownership check: receipts.order → orders.customer_id === session user_id
  │  (staff/manager may view any receipt from their own pages)
  └─ render from order + order_items (snapshots), not current product data

PRINT
  print.css: A5/80mm-friendly, hides nav/buttons, black-on-white,
  window.print() button → browser print dialog → PDF or paper

RECEIPT CONTENT
  BulkBites BBQ (logo, store name, Greensite Compound Sta. Lucia Pasig City)
  Receipt number · Order number · Date/time
  Customer name
  Items: name | qty | unit price | line subtotal
  Total
  Payment method
  Order status badge
  Footer: "Thank you for supporting BulkBites BBQ!" + system note
```

Receipts are generated once at checkout — completed orders always print identically, even if a product's price changes later (name/price snapshots in `order_items`).

---

## 12. Recommended Reusable Components

**PHP (`includes/`)**
| Component | Purpose |
|---|---|
| `bootstrap.php` | Session hardening, config load, timezone, DB connect — included first by every page |
| `auth_check.php` | Requires logged-in user (server-side) |
| `role_check.php` | `require_role('STAFF')` — redirects/403s unauthorized roles |
| `csrf.php` | `csrf_field()` in forms, `csrf_verify()` on POST |
| `functions.php` | `e()` output escape, `peso()` formatter, `flash()`, `make_order_number()`, `json_out()` |
| `components.php` | `status_badge()`, `flash_messages()`, `pagination()`, `confirm_dialog()` markup |
| `otp.php` / `sms.php` | OTP lifecycle and pluggable delivery |
| `checkout.php` | Single transactional checkout implementation |

**Frontend**
| Asset | Purpose |
|---|---|
| `header.php` / `footer.php` | Role-aware nav, flash area, toast container |
| `password-toggle.js` | 👁 show/hide for **every** password field |
| `app.js` | `fetchJSON()` helper, toast messages, confirm dialogs, loading spinners, disabled-submit pattern |
| `cart.js` / `checkout.js` | Quantity steppers, reset-order confirmation, checkout loading/error states |
| `kds.js` / `notifications.js` | Status polling, notification polling, badges |
| `style.css` | Design tokens (charcoal `#1c1917`-family, amber `#f59e0b`, red accents, neutral backgrounds), cards, tables, badges, forms, responsive grid |
| `print.css` | Receipt printing |

**UI patterns to standardize once, reuse everywhere:** card grid, data table, status badge, flash message, modal confirm, loading spinner, empty state, pagination, "I am human" checkbox block.

---

## 13. Security Architecture

| Threat | Control |
|---|---|
| SQL injection | PDO with prepared statements (bound params) everywhere; zero string-interpolated SQL |
| XSS | `htmlspecialchars($x, ENT_QUOTES, 'UTF-8')` via `e()` on all output; `Content-Type` set server-side |
| CSRF | Per-session token on every POST form and mutating `api/*` call; verified server-side |
| URL role bypass | `role_check.php` at the top of every protected page **and** endpoint; server decides, UI only mirrors |
| Price manipulation | Prices read from `products.price` inside checkout; browser sends only IDs and quantities |
| Stock manipulation / oversell | `FOR UPDATE` row locks + guarded `UPDATE ... WHERE quantity_available >= ?` inside the transaction |
| Partial orders | Single transaction; rollback on any failure |
| OTP brute force | Hashed OTP, 3 attempts, 5-minute expiry, 60 s resend cooldown, per-phone and per-IP hourly caps (`rate_limits`) |
| Bots | "I am human" checkbox + server-issued verification token + rate limiting + CSRF (explicitly **not** reCAPTCHA-equivalent) |
| Session hijack/fixation | Strict mode, HttpOnly + SameSite cookies, `session_regenerate_id()` on login, idle timeout |
| Stale/deactivated accounts | Role and `is_active` re-checked from DB on every request |
| Credential leaks | `config/database.php` gitignored; `config.example.php` committed; DB creds never in HTML/JS; no API keys in repo |
| Passwords (staff/manager) | `password_hash()` bcrypt; 👁 toggle on fields; customers use OTP (no stored password) |
| Error disclosure | `display_errors=0` in prod-like runs; generic user messages, detail to a server log |
| File upload (Phase 7) | Extension + MIME whitelist, re-encode images, store outside web-executable paths, never execute uploads |
| Mass assignment | Explicit field whitelists in every INSERT/UPDATE |

**GitHub hygiene:** commit `README.md`, `database.sql`, `config.example.php`, source; ignore `config/database.php`, `uploads/*`, logs. No real credentials, SMS keys, or tokens anywhere in history.

---

## 14. Recommended API / Backend Structure

**Style:** plain PHP endpoints in `api/`, session-authenticated, returning JSON. No framework, no router — `action` parameter selects behavior.

```
POST   api/auth.php?action=session_status      → { logged_in, role, name }
POST   api/products.php?action=list            → menu + stock (public/roles)
POST   api/orders.php?action=create            → checkout (CUSTOMER, CSRF)
POST   api/orders.php?action=list_my           → own orders (CUSTOMER)
POST   api/orders.php?action=list_open         → NEW/ACCEPTED/PREPARING/READY (STAFF)
POST   api/orders.php?action=advance           → status transition (STAFF/MANAGER, CSRF)
POST   api/inventory.php?action=list           → stock (STAFF/MANAGER)
POST   api/inventory.php?action=adjust         → restock/adjust (role-checked, CSRF)
POST   api/notifications.php?action=poll       → own unread count + latest
POST   api/notifications.php?action=mark_read  → own rows only (CSRF)
```

**Endpoint rules (identical to pages):** session check → role check → CSRF for mutations → server-side validation → prepared statements → typed JSON (`{ ok, data, error }`) with proper HTTP status codes (200/400/401/403/409/500). Every endpoint re-applies the permission matrix.

**Backend layering:**

```
Browser (HTML/CSS/JS)
   │  form POST  or  fetch() JSON
   ▼
PHP page or api/*.php          ← role_check + csrf + validation (request layer)
   │
   ▼
includes/*.php functions        ← business rules: checkout, OTP, status transitions
   │
   ▼
PDO prepared statements
   │
   ▼
MySQL (InnoDB)  ── writes ──▶ orders / order_items / inventory / receipts / notifications / history
```

---

## 15. Data Flow Summary (Frontend → PHP → MySQL)

1. **Render:** page calls `bootstrap` → role check → SELECT from MySQL → `header.php` + `e()`-escaped HTML → browser.
2. **Mutate (forms):** browser POSTs IDs/quantities + CSRF → PHP validates → business function → prepared-statement writes (transactions where multi-table) → redirect with flash message (Post/Redirect/Get) → fresh SELECT renders the truth.
3. **Mutate (AJAX):** `fetchJSON()` → `api/*.php` → same checks → JSON `{ok, data, error}` → JS updates DOM without reload (cart totals, KDS cards, notification badges).
4. **Polling:** KDS/orders ~10 s, notifications ~15 s — `SELECT ... WHERE updated_at > last_seen` style delta queries to stay cheap.
5. **Single source of truth:** product names, prices, stock, order status, receipts — all read from MySQL at all times. Cart and any client cache hold only IDs and quantities.

---

## 16. Development Order for Phases 1–10

| Phase | Scope | Exit criteria |
|---|---|---|
| **1 — Database** | Write `database.sql`: all tables, FKs, indexes, InnoDB/utf8mb4, seed the 6 products (₱30/20/20/22/20/25), categories, 1 manager + 1 staff account. Import via phpMyAdmin. | Fresh import succeeds; FKs and enums verified; `config.example.php` + gitignored `config/database.php` in place; seed rows visible in phpMyAdmin |
| **2 — Authentication** | Bootstrap/session hardening, PDO helper, `auth_check`/`role_check`, OTP request/verify/resend with all limits, Debug SMS sender, staff password login, logout, password toggle, human-verification token | Full phone→OTP login works with expiry/attempt/cooldown limits enforced; staff login works; wrong-role URL access blocked; logout invalidates session |
| **3 — Customer interface** | Public menu, header/footer/theme (`style.css`), customer dashboard/profile/notifications shells, cards, badges, flash, confirm dialogs, responsive layout | Menu renders from MySQL on desktop + mobile widths; role-gated customer pages reachable only when logged in |
| **4 — Ordering system** | Session cart (add/qty/remove/reset-with-confirm), checkout page + `includes/checkout.php` transaction, stock guards, order creation, My Orders + status polling | Place order deducts stock atomically; oversell and double-submit blocked; prices always from DB; RESET asks for confirmation |
| **5 — Receipts & notifications** | Receipt generation at checkout, view/print (`print.css`), notifications table writes + customer bell/polling, status-change notifications | Every completed order has exactly one receipt, printable, own-orders-only; customer sees status changes as notifications |
| **6 — Staff dashboard & KDS** | Staff dashboard, incoming orders, KDS board with NEW→ACCEPTED→PREPARING→READY→COMPLETED, transitions + history, staff inventory view, new-order notifications | Full status lifecycle works end-to-end; customer sees each transition; invalid transitions rejected server-side; KDS usable one-handed on mobile |
| **7 — Manager dashboard & reports** | Product/category CRUD + image upload, inventory management (thresholds/restock/availability), customers list, user management, statistics dashboard, reports | Manager can maintain all catalog/stock/user data; dashboard totals match SQL cross-checks; low-stock alerts appear |
| **8 — Security & validation audit** | Run through the full Security table: prepared statements everywhere, CSRF on all mutations, role matrix on every route/endpoint, output escaping sweep, OTP limits re-test, upload hardening, `.gitignore`/no-secrets review | Every row in §13 verified by a deliberate attack test (URL bypass, price tamper, stock tamper, CSRF replay, OTP flood) |
| **9 — UI/UX refinement** | Consistent spacing/typography, responsive pass (320 px → desktop), loading states, empty states, error pages, accessibility basics, performance (image sizes, polling intervals), visual identity polish | No layout breakage at key widths; all states (loading/empty/error/success) styled; Lighthouse-style sanity pass |
| **10 — Complete testing** | End-to-end scripts: 3 roles × all flows, two-customer concurrent checkout race, OTP lifecycle, receipt totals vs SQL, reports vs raw SQL, clean-machine XAMPP install from README, GitHub push dry-run (no secrets) | All scenarios pass on a clean XAMPP import; README alone is enough to set up; zero blocking bugs; repository clean |

**Rule: one phase at a time, verified before moving on.**

---

## 17. Potential Technical Risks & Mitigations

| # | Risk | Impact | Mitigation |
|---|---|---|---|
| 1 | Overselling under concurrent checkouts (two customers, last stick) | Wrong orders, angry customers | InnoDB + `SELECT ... FOR UPDATE` on inventory rows + guarded `UPDATE ... WHERE quantity_available >= ?`; rollback on 0 affected rows; **MyISAM must never be used** (no transactions/row locks) |
| 2 | Partial orders (order saved, stock not deducted) | Data corruption | Single transaction around the entire checkout; rollback on any exception; test by forcing a mid-transaction failure |
| 3 | Client-side price/stock tampering | Revenue loss, negative stock | Server reads prices and stock from MySQL only; cart carries IDs/qty; JS validation is UX only |
| 4 | OTP abuse (flooding phones, brute-forcing codes) | SMS cost, account takeover | Hashed single-use codes, 5-min expiry, 3 attempts, 60 s cooldown, per-phone/per-IP hourly caps, CSRF + human token |
| 5 | OTP untestable without paid SMS | Development stalls | `SmsSender` interface with `DebugSmsSender` (log/on-screen code in debug mode); real provider = one new class later |
| 6 | Forgetting a role check on one page | Privilege escalation | `role_check.php` required at the top of every protected file; Phase 8 includes a deliberate URL-bypass sweep of every route |
| 7 | CSRF on state-changing forms | Forced actions | Token on every POST form and mutating API call; verify before any write |
| 8 | Polling overloading XAMPP (Apache + MySQL on a laptop) | Slow KDS/dashboard | Modest intervals (10–15 s), delta queries, index `(user_id, is_read, created_at)` and `(status)`, cap result sets; document intervals as tunable |
| 9 | Floating-point money errors | Cent mismatches in reports | `DECIMAL(10,2)` everywhere; no float math; format with `number_format`/`peso()` only at display |
| 10 | Timezone drift in timestamps/reports | Wrong "today" totals | `date_default_timezone_set('Asia/Manila')` in bootstrap; MySQL `DATETIME` written from PHP's clock consistently |
| 11 | Receipt/product drift (price later changed, old receipt wrong) | Incorrect historical records | `order_items` snapshots of name + price; receipts generated once inside checkout |
| 12 | Double form submission (impatient taps) | Duplicate orders | Disable button + loading state; server-side one-time order token per cart render; unique order number constraint as last line |
| 13 | Credentials committed to GitHub | Project rejected / security incident | `config/database.php` gitignored, `config.example.php` committed, Phase 8 includes `git log` secret scan |
| 14 | MySQL connection failures / wrong charset | Garbled ₱ and Filipino text | PDO with `utf8mb4`, clear error page if connection fails, connection settings centralized in `config` |
| 15 | Weak file uploads (Phase 7) | Server compromise | MIME/extension whitelist, rename uploads, serve from `uploads/` with execution denied, size cap |
| 16 | Session left open after logout/deactivation | Account takeover on shared PC | Destroy session + cookie on logout; re-check `is_active` from DB every request; idle timeout |
| 17 | Scope creep ("build everything at once") | Broken, untestable system | Strict phase gating — this document's §16 is the contract |
| 18 | XAMPP environment differences (PHP version, extensions) | "Works on my machine" | Target PHP 8.x with PDO + pdo_mysql (standard in current XAMPP); document required extensions in README; no framework/composer dependencies |

---

## Seed Data (Phase 1 preview)

| Product | Price | Unit |
|---|---|---|
| BBQ | ₱30.00 | pcs (sticks) |
| Isaw Manok | ₱20.00 | pcs |
| Bituka Baboy | ₱20.00 | pcs |
| Tenga Baboy | ₱22.00 | pcs |
| Balunan | ₱20.00 | pcs |
| Hotdog | ₱25.00 | pcs |

Initial accounts: 1 MANAGER (phone + password), 1 STAFF (phone + password). Customer accounts are created implicitly on first successful OTP login.

---

*End of Phase 0. Awaiting "START PHASE 1" to begin the database schema implementation (`database.sql`).*
