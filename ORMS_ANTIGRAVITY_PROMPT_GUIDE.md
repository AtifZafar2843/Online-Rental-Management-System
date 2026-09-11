# ORMS (Online Rental Management System) — Antigravity Build Instructions

> **Project:** Online Rental Management System (BCSP-064, IGNOU)
> **Student:** Atif Zafar
> **Reference Document:** `ORMS_Synopsis_Final_Draft.pdf` (present in this project folder)
> **Rule #1:** Everything built MUST match the synopsis exactly — table names, field names, data types, class names, method names, and module logic. This code will be evaluated against the submitted synopsis, so no deviation is allowed unless explicitly instructed below.

---

## 0. HOW TO USE THIS FILE

Antigravity should:
1. Read `ORMS_Synopsis_Final_Draft.pdf` fully first (all 39 pages) to internalize the full spec.
2. Use this file as the **execution plan** — build strictly in the order given in Section 6 (Build Order). Do not skip ahead or build multiple modules in one shot.
3. After each module is built, **stop and report** what was created, then wait for confirmation before moving to the next module.
4. Never invent tables, fields, or modules that are not in the synopsis. If something is ambiguous, follow the synopsis text verbatim over assumption.

---

## 1. TECH STACK (Fixed — Do Not Substitute)

| Component | Technology |
|---|---|
| Backend | PHP 8.1+ (OOP — classes, inheritance, interfaces, typed properties) |
| Database | MySQL 8.0, InnoDB engine, 3NF normalized |
| Frontend | HTML5, CSS3, Tailwind CSS (utility-first, via CDN or CLI build), JavaScript ES6+ (AJAX, no full-page reloads where noted) |
| Server | Apache 2.4 (via XAMPP) |
| Environment | XAMPP — project lives in `htdocs/orms/` |
| Password Security | `password_hash()` / `password_verify()` (bcrypt) |
| SQL Injection Prevention | PDO or MySQLi **prepared statements only** — no raw string-concatenated queries anywhere |
| File Upload Validation | `finfo_file()` for magic-byte MIME verification (never trust `Content-Type` header or extension alone) |
| Session Security | PHP native sessions + `session_regenerate_id(true)` on login and privilege change |
| Notifications | Database-driven `NOTIFICATION` table, polling-based (no WebSocket in this version) |

Do NOT introduce Laravel, Composer frameworks, Node backend, or any ORM. This is meant to be plain, well-structured OOP PHP as per the synopsis's academic scope.

---

## 2. PROJECT FOLDER STRUCTURE

Create this exact structure inside the project root:

```
orms/
├── config/
│   └── database.php          # PDO connection (singleton pattern)
├── classes/
│   ├── BaseUser.php          # abstract class
│   ├── Owner.php             # extends BaseUser
│   ├── Renter.php            # extends BaseUser
│   ├── Admin.php             # extends BaseUser
│   ├── Product.php
│   ├── RentalRequest.php
│   ├── Transaction.php
│   ├── Fine.php
│   ├── Review.php
│   ├── Notification.php
│   ├── Dispute.php
│   └── exceptions/
│       ├── ORMSException.php
│       ├── ProductUnavailableException.php
│       ├── InvalidDateRangeException.php
│       ├── PaymentFailedException.php
│       ├── UnauthorizedActionException.php
│       └── DuplicateReviewException.php
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── navbar.php
│   └── functions.php         # shared helper functions
├── auth/
│   ├── register.php
│   ├── login.php
│   ├── logout.php
│   ├── forgot_password.php
│   └── reset_password.php
├── owner/
│   ├── dashboard.php
│   ├── add_product.php
│   ├── edit_product.php
│   ├── manage_requests.php
│   ├── raise_fine.php
│   └── earnings.php
├── renter/
│   ├── dashboard.php
│   ├── search.php
│   ├── product_details.php
│   ├── request_rental.php
│   ├── my_rentals.php
│   ├── pay.php
│   ├── submit_review.php
│   └── file_dispute.php
├── admin/
│   ├── dashboard.php
│   ├── manage_users.php
│   ├── manage_categories.php
│   ├── resolve_disputes.php
│   ├── configure_fine_rate.php
│   └── reports.php
├── notifications/
│   └── view_notifications.php
├── uploads/
│   ├── products/              # product images (server path, randomized filenames)
│   └── id_proofs/             # ID proof docs — should NOT be web-root accessible if possible
├── assets/
│   ├── css/
│   ├── js/
│   └── img/
├── database/
│   └── orms_schema.sql        # full schema + seed data
├── index.php
└── logout.php
```

---

## 3. DATABASE SCHEMA (Build This First — Exact From Synopsis Section 12)

Generate a single `database/orms_schema.sql` file that creates database `orms_db` and all 11 tables below, in this exact structure. Use `InnoDB` engine, add all foreign keys, checks, enums, and defaults exactly as specified. Add a seed script with: 1 admin, 3 categories (Electronics, Furniture, Vehicles), 2 sample users.

### 3.1 ADMIN
- admin_id INT PK AUTO_INCREMENT
- username VARCHAR(50) UNIQUE NOT NULL
- password VARCHAR(255) NOT NULL (bcrypt hash)
- email VARCHAR(100) UNIQUE NOT NULL
- full_name VARCHAR(100) NOT NULL
- status ENUM('Active','Inactive') DEFAULT 'Active'
- created_date DATETIME DEFAULT CURRENT_TIMESTAMP

### 3.2 USER
- user_id INT PK AUTO_INCREMENT
- name VARCHAR(100) NOT NULL
- email VARCHAR(100) UNIQUE NOT NULL
- phone VARCHAR(15) NOT NULL
- address TEXT NOT NULL
- password VARCHAR(255) NOT NULL (bcrypt hash)
- id_proof_path VARCHAR(255) NOT NULL
- rating DECIMAL(3,2) DEFAULT 0.00
- reg_date DATETIME DEFAULT CURRENT_TIMESTAMP
- status ENUM('Active','Inactive','Banned') DEFAULT 'Active'
- reset_token VARCHAR(64) NULL
- reset_token_expiry DATETIME NULL

### 3.3 USER_ROLES (junction table — replaces ENUM anti-pattern)
- user_id INT, FK → USER(user_id), part of composite PK
- role ENUM('Owner','Renter'), part of composite PK
- assigned_date DATETIME DEFAULT CURRENT_TIMESTAMP

### 3.4 CATEGORY
- category_id INT PK AUTO_INCREMENT
- category_name VARCHAR(100) UNIQUE NOT NULL
- description TEXT NULL
- parent_category_id INT NULL, FK → CATEGORY(category_id) (self-reference for subcategories)

### 3.5 PRODUCT
- product_id INT PK AUTO_INCREMENT
- owner_id INT NOT NULL, FK → USER(user_id)
- category_id INT NOT NULL, FK → CATEGORY(category_id)
- title VARCHAR(255) NOT NULL
- description TEXT NOT NULL
- rent_per_day DECIMAL(10,2) NOT NULL, CHECK (rent_per_day > 0)
- security_deposit DECIMAL(10,2) NOT NULL, CHECK (security_deposit >= 0)
- location VARCHAR(100) NOT NULL
- avail_status ENUM('Available','Rented','Unavailable') DEFAULT 'Available'
- `condition` ENUM('New','Good','Fair','Poor') NOT NULL
- listed_date DATETIME DEFAULT CURRENT_TIMESTAMP

### 3.6 PRODUCT_IMAGES
- image_id INT PK AUTO_INCREMENT
- product_id INT NOT NULL, FK → PRODUCT(product_id)
- image_path VARCHAR(255) NOT NULL (randomized server path)
- is_primary BOOLEAN DEFAULT FALSE
- upload_date DATETIME DEFAULT CURRENT_TIMESTAMP

### 3.7 RENTAL_REQUEST
- request_id INT PK AUTO_INCREMENT
- product_id INT NOT NULL, FK → PRODUCT(product_id)
- renter_id INT NOT NULL, FK → USER(user_id)
- start_date DATE NOT NULL
- end_date DATE NOT NULL, CHECK (end_date > start_date)
- status ENUM('Pending','Approved','Active','Completed','Rejected','Cancelled') DEFAULT 'Pending'
- request_date DATETIME DEFAULT CURRENT_TIMESTAMP
- message TEXT NULL
- cancellation_reason TEXT NULL

### 3.8 TRANSACTION
- transaction_id INT PK AUTO_INCREMENT
- request_id INT NOT NULL, FK → RENTAL_REQUEST(request_id)
- payer_id INT NOT NULL, FK → USER(user_id)
- rental_amount DECIMAL(10,2) NOT NULL (snapshot: total_days × rent_per_day, computed at payment time)
- deposit_amount DECIMAL(10,2) NOT NULL
- deposit_status ENUM('Held','Refunded','Partially_Refunded','Forfeited') DEFAULT 'Held'
- payment_mode VARCHAR(30) NOT NULL
- payment_date DATETIME DEFAULT CURRENT_TIMESTAMP
- payment_status ENUM('Pending','Completed','Failed','Refunded') DEFAULT 'Pending'

### 3.9 FINE
- fine_id INT PK AUTO_INCREMENT
- request_id INT NOT NULL, FK → RENTAL_REQUEST(request_id)
- renter_id INT NOT NULL, FK → USER(user_id)
- transaction_id INT NOT NULL, FK → TRANSACTION(transaction_id)
- fine_type ENUM('Late_Return','Damage','Lost') NOT NULL
- amount DECIMAL(10,2) NOT NULL, CHECK (amount > 0)
- late_days INT NULL (only for Late_Return)
- rate_per_day DECIMAL(10,2) NULL (admin-configured snapshot)
- issue_date DATETIME DEFAULT CURRENT_TIMESTAMP
- paid_date DATETIME NULL
- status ENUM('Unpaid','Paid','Waived','Deducted_From_Deposit') DEFAULT 'Unpaid'

**Fine formula:** `amount = late_days × rate_per_day`, where `late_days = DATEDIFF(actual_return_date, end_date)`. Cap fine at `2 × security_deposit`.

### 3.10 REVIEW
- review_id INT PK AUTO_INCREMENT
- request_id INT NOT NULL, UNIQUE, FK → RENTAL_REQUEST(request_id) (enforces one review per rental)
- product_id INT NOT NULL, FK → PRODUCT(product_id)
- reviewer_id INT NOT NULL, FK → USER(user_id)
- rating TINYINT NOT NULL, CHECK (rating BETWEEN 1 AND 5)
- comment TEXT NULL
- review_date DATETIME DEFAULT CURRENT_TIMESTAMP

### 3.11 NOTIFICATION
- notif_id INT PK AUTO_INCREMENT
- user_id INT NOT NULL, FK → USER(user_id)
- message TEXT NOT NULL
- type ENUM('Rental','Payment','Fine','Dispute','System') NOT NULL
- related_id INT NULL
- is_read BOOLEAN DEFAULT FALSE
- created_at DATETIME DEFAULT CURRENT_TIMESTAMP

### 3.12 DISPUTE
- dispute_id INT PK AUTO_INCREMENT
- request_id INT NOT NULL, FK → RENTAL_REQUEST(request_id)
- raised_by INT NOT NULL, FK → USER(user_id)
- against INT NOT NULL, FK → USER(user_id)
- reason TEXT NOT NULL
- status ENUM('Open','Under_Review','Resolved','Escalated') DEFAULT 'Open'
- admin_notes TEXT NULL
- created_date DATETIME DEFAULT CURRENT_TIMESTAMP
- resolved_date DATETIME NULL

**Indexes to add:** `RENTAL_REQUEST(product_id)`, `RENTAL_REQUEST(renter_id)`, `PRODUCT(avail_status)`, FULLTEXT index on `PRODUCT(title, description)`.

---

## 4. OOP CLASS DESIGN (Build in `classes/`)

Implement PHP classes matching the synopsis Class Diagram (Section 11) exactly:

- **BaseUser (abstract):** userID, username, email, phone, password, address, status, rating | `login(): bool`, `logout(): void`, `updateProfile(): bool`, `resetPassword($token): bool`
- **Owner extends BaseUser:** products, totalEarnings | `addProduct()`, `editProduct($id)`, `manageRentalRequest($id, $decision)`, `confirmReturn($requestId)`, `raiseFine($requestId, $type, $amount)`
- **Renter extends BaseUser:** activeRentals, totalSpent | `sendRequest($productId, $start, $end)`, `makePayment($requestId)`, `cancelRequest($requestId)`, `submitReview($requestId, $rating, $comment)`, `payFine($fineId)`, `fileDispute($requestId, $reason)`
- **Admin extends BaseUser:** securityLevel | `configSystem()`, `manageUsers()`, `generateReport($type)`, `configureFineRate($rate)`, `resolveDispute($disputeId, $notes)`
- **Product:** productID, title, description, categoryID, rentPerDay, securityDeposit, availStatus, condition, location, listedDate | `checkAvailability($start, $end): bool`, `updateStatus($status)`, `getImages()`
- **RentalRequest:** requestID, productID, renterID, startDate, endDate, status, message, requestDate | `approve()`, `reject($reason)`, `cancel()`, `getTotalDays(): int`, `getTotalAmount(): float` (computed, not stored)
- **Transaction:** transactionID, requestID, payerID, amount, depositAmount, paymentMode, paymentDate, paymentStatus | `processPayment()`, `refundDeposit($fineDeduction)`, `getReceipt()`
- **Fine:** fineID, requestID, renterID, fineType, amount, issueDate, paidDate, status | `calcLateReturnAmount($days, $ratePerDay): float`, `processPay()`
- **Review:** reviewID, requestID, productID, reviewerID, rating, comment, reviewDate | `validateRentalCompleted(): bool`, `submitReview()`
- **Notification:** notifID, userID, message, type, isRead, createdAt | `markAsRead()`, `send()`
- **Dispute:** disputeID, requestID, raisedByID, againstID, reason, status, adminNotes, createdDate | `resolve($adminNotes)`, `escalate()`

### Custom Exception Classes (`classes/exceptions/`)
- `ORMSException` (base for all)
- `ProductUnavailableException` — thrown when rental requested for already-rented product
- `InvalidDateRangeException` — thrown when start_date >= end_date or past dates given
- `PaymentFailedException` — thrown on transaction failure
- `UnauthorizedActionException` — thrown on role-based access violation
- `DuplicateReviewException` — thrown on second review attempt for same rental

---

## 5. CORE BUSINESS LOGIC RULES (Must Be Implemented Exactly)

1. **Registration:** ID proof upload validated with `finfo_file()` (magic bytes, not extension/Content-Type). Roles assigned via `USER_ROLES` (a user can be Owner, Renter, or both simultaneously). Password hashed with `password_hash()`.
2. **Login:** `password_verify()`, then `session_regenerate_id(true)`. Failed logins should not reveal whether the email or password was wrong.
3. **Password reset:** Token via `random_bytes(32)` → `bin2hex()`, stored HASHED in `reset_token`, 1-hour expiry, invalidated after use.
4. **Image upload:** `finfo_file()` check for JPEG/PNG/WEBP magic bytes only, max 5MB, filename randomized via `uniqid()`.
5. **Concurrency control:** Rental request submission must use `SELECT ... FOR UPDATE` on the PRODUCT row inside a transaction (check availability → update status → create request, all atomic) to prevent race conditions on double-booking. Throw `ProductUnavailableException` if the second concurrent request loses the race.
6. **Cancellation policy:**
   - Pending status: free cancellation.
   - Approved status, cancelled within 24 hours: full deposit refund.
   - Approved status, cancelled after 24 hours: deposit forfeited (`deposit_status = 'Forfeited'`).
7. **Rental amount:** computed dynamically as `DATEDIFF(end_date, start_date) × rent_per_day`. NOT stored in `RENTAL_REQUEST`. Snapshot IS stored in `TRANSACTION.rental_amount` at payment time (this is intentional and correct per synopsis).
8. **Deposit refund logic:**
   - Clean return → full refund, `deposit_status = 'Refunded'`.
   - Fine exists but ≤ deposit → deduct fine, refund remainder, `deposit_status = 'Partially_Refunded'`.
   - Fine exceeds deposit → renter pays difference, `deposit_status = 'Forfeited'`, remaining fine stays `'Unpaid'`.
9. **Late fine formula:** `amount = late_days × rate_per_day`, `late_days = DATEDIFF(actual_return_date, end_date)`, capped at `2 × security_deposit`. `rate_per_day` is Admin-configured and stored as a snapshot per fine record.
10. **Review constraint:** `submitReview()` must call `validateRentalCompleted()` first — checks `status = 'Completed'` AND `reviewer_id = renter_id` of that request. DB-level `UNIQUE` on `REVIEW.request_id` is the final guard. Throw `DuplicateReviewException` on violation.
11. **Notifications:** create a `NOTIFICATION` row on every event listed in synopsis Section 13.IX (new request, approval/rejection, payment success, due-date reminder, return confirmed, fine issued, dispute filed/resolved, deposit refunded).
12. **Auto-refund timeout:** if Owner doesn't confirm return within 7 days of `end_date`, deposit auto-refunds (implement as a script that can be run manually or via a scheduled task — cron is optional for this academic version, but the PHP function/logic must exist and be callable/testable).
13. **Disputes:** either party can file against a Completed/Active rental; both parties get notified; Admin resolves with mandatory `admin_notes`.
14. **Role switching:** dual-role users switch via `$_SESSION['active_role']` — no re-login required.

---

## 6. BUILD ORDER (Strict — One Module at a Time, Test Before Moving On)

Build and verify each step before starting the next:

1. **Database schema** — run `orms_schema.sql`, verify all 11 tables + relations in phpMyAdmin.
2. **`config/database.php`** — PDO connection class (singleton), test connection.
3. **Auth module** — register, login, logout, forgot/reset password. Test: create a user, log in, log out.
4. **Product Listing module (Owner)** — add/edit product with multi-image upload. Test: add a product with 2 images, verify DB rows + files saved.
5. **Search & Discovery module** — public catalog, filters (category/price/location), product detail page. Test as Guest (not logged in).
6. **Rental Request module** — submit, approve/reject, cancel, concurrency-safe locking. Test: two rapid requests for the same product — only one should succeed.
7. **Financial/Transaction module** — payment page, rental amount calculation, deposit handling. Test full request → approve → pay flow.
8. **Fine module** — late return detection, damage fine by owner, deposit deduction logic. Test with a manually backdated `end_date`.
9. **Review module** — post-completion review, duplicate prevention. Test submitting twice — second should fail.
10. **Notification module** — verify notification rows created for each event type; build a simple polling view page.
11. **Dispute + Admin module** — file dispute, admin resolves; admin dashboard for users/categories/fine-rate config/reports.
12. **Final integration pass** — full end-to-end walkthrough: register → list product → search → request → approve → pay → return → fine (if applicable) → review → notifications throughout.

Do not batch multiple modules into a single generation step. Generate one, pause for testing/confirmation, then continue.

---

## 7. SECURITY CHECKLIST (Non-Negotiable — College Will Check These)

- [ ] All SQL queries use prepared statements (PDO with bound parameters) — zero string concatenation into SQL.
- [ ] All passwords hashed with `password_hash()` / verified with `password_verify()`.
- [ ] `session_regenerate_id(true)` called on login and role switch.
- [ ] File uploads validated via `finfo_file()` magic bytes, not extension or `Content-Type`.
- [ ] Uploaded files renamed with `uniqid()` — never trust user-supplied filenames.
- [ ] CSRF tokens on all state-changing forms (add/edit/delete/approve/reject/pay).
- [ ] Role-based access control checked via `USER_ROLES` lookup on every protected page (a Renter should never reach Owner-only pages and vice versa).
- [ ] Input validation/sanitization on all form inputs server-side (never trust client-side JS validation alone).
- [ ] Reset tokens stored hashed, time-limited, single-use.

---

## 8. WHAT TO OUTPUT AFTER DEVELOPMENT IS DONE

Once all 12 build-order steps are complete, Antigravity must generate a **second file**: `SETUP_AND_TESTING_GUIDE.md` in the project root, containing:

1. **How to access ORMS locally** — exact URL (e.g. `http://localhost/orms/`), how to start XAMPP services, how to import the DB if not already done.
2. **Default test accounts** — the seeded admin username/password, and instructions to register at least one Owner and one Renter test account.
3. **Step-by-step test walkthrough for every module**, written as numbered checklists — e.g. "Go to X page → do Y → expect Z result" — covering: registration, login, product listing, search, rental request (including the concurrency test), payment, fine calculation, review, notifications, disputes, and admin functions.
4. **A checklist mapped to every method in the Class Diagram** (Section 11 of synopsis) confirming each method has a corresponding testable UI action or script.
5. **Known limitations / things not implemented** (e.g. no real payment gateway, no cron for auto-refund — manual trigger only) — so it's clear what's academic-scope vs production-scope.
6. **Troubleshooting section** — common XAMPP/MySQL connection errors and fixes.

---

## 9. THINGS TO NEVER DO

- Never rename tables/fields/classes/methods from what's specified above.
- Never add a payment gateway, SMS, or real-time chat (those are explicitly "future scope" in the synopsis, section 17).
- Never store `total_days` or `total_amount` in `RENTAL_REQUEST` (violates the deliberate 3NF design — these are computed values).
- Never skip the `SELECT ... FOR UPDATE` locking on rental request creation.
- Never store plaintext passwords or plaintext reset tokens.
- Never combine multiple modules into one massive code dump — follow the build order.
