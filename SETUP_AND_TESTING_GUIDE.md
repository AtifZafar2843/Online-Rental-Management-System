# ORMS (Online Rental Management System) — Setup and Testing Guide

> **Project:** Online Rental Management System (BCSP-064, IGNOU BCA Final Project)  
> **Student:** Atif Zafar  
> **Tech Stack:** PHP 8.1+, MySQL 8.0 / MariaDB 10.4+, Apache (XAMPP), Tailwind CSS  
> **Status:** Updated with **Step 1 (Database Schema & Seed Data)**

---

## 1. Project Directory & XAMPP Environment Setup

### Prerequisites
1. **XAMPP** (with PHP 8.1+ and MySQL 8.0+ / MariaDB 10.4+) installed on your system (usually `C:\xampp`).
2. Make sure this project folder is accessible by Apache.
   - Either copy/clone this `ORMS` directory to `C:\xampp\htdocs\orms\`
   - OR create a directory symbolic link in PowerShell (Run as Administrator):
     ```powershell
     New-Item -ItemType SymbolicLink -Path "C:\xampp\htdocs\orms" -Target "c:\Users\Dell\Desktop\Projects\ORMS"
     ```

### Starting Services
1. Open the **XAMPP Control Panel**.
2. Click **Start** next to **Apache**.
3. Click **Start** next to **MySQL**.
4. Both module indicators should turn green.

---

## 2. Build Order Roadmap Tracker

| Step | Module / Component | Status | Notes |
|:---:|:---|:---:|:---|
| **1** | **Database Schema (`database/orms_schema.sql`)** | **COMPLETED** | 12 tables, 3NF normalized, constraints, indexes & seed data |
| **2** | **PDO Connection Class (`config/database.php`)** | **COMPLETED** | Thread-safe Singleton, UTF-8, native prepared statements |
| **3** | **Authentication & User Management (`auth/`)** | **COMPLETED** | Register (magic-bytes), Login, Logout, Forgot/Reset password, Dual-Role |
| 4 | Product Listing Module (Owner) (`owner/`) | Pending | Multi-image upload, condition, pricing, CRUD |
| 5 | Search & Discovery Module (`renter/`, `index.php`) | Pending | Public catalog, category/price/location filters |
| 6 | Rental Request Module (`renter/`, `owner/`) | Pending | Concurrency-safe `SELECT ... FOR UPDATE`, approve/reject |
| 7 | Financial / Transaction Module (`renter/`) | Pending | Dynamic rental amount calculation, deposit hold/refund |
| 8 | Fine Module (`owner/`, `admin/`) | Pending | Late return auto-calc, damage fines, deposit deduction |
| 9 | Review & Rating Module (`renter/`) | Pending | Post-completion check, duplicate review prevention |
| 10 | Notification System (`notifications/`) | Pending | Event-driven notifications, polling UI |
| 11 | Dispute & Admin Management (`admin/`) | Pending | Dispute resolution, fine rate config, reports |
| 12 | End-to-End Integration & Final Walkthrough | Pending | Complete lifecycle testing across all user roles |

---

## 3. Step 1: Database Setup & Testing Guide

### 3.1 Importing the Schema

You can import `database/orms_schema.sql` using either phpMyAdmin or the MySQL Command Line.

#### Method A: Using phpMyAdmin (Recommended for GUI)
1. Open your browser and go to: `http://localhost/phpmyadmin/`
2. Click on the **Import** tab in the top navigation bar.
3. Under **File to import**, click **Choose File** (Browse) and select:
   ```
   c:\Users\Dell\Desktop\Projects\ORMS\database\orms_schema.sql
   ```
4. Leave other settings as default (Format: SQL).
5. Scroll to the bottom and click **Import** (or **Go**).
6. You should see a green success message: *"Import has been successfully finished, ... queries executed."*

#### Method B: Using MySQL Command Line
1. Open PowerShell or Command Prompt.
2. Run:
   ```powershell
   # If MySQL is in your PATH or in C:\xampp\mysql\bin
   C:\xampp\mysql\bin\mysql.exe -u root -p < "c:\Users\Dell\Desktop\Projects\ORMS\database\orms_schema.sql"
   ```
   *(Press Enter if your root user has no password).*

---

### 3.2 Verification Checklist & Queries

Open the `orms_db` database in phpMyAdmin, go to the **SQL** tab, and run each test query below to verify:

#### Test 1: Verify All 12 Tables Exist
Run this query:
```sql
USE `orms_db`;
SHOW FULL TABLES WHERE Table_type = 'BASE TABLE';
```
**Expected Output:** 12 tables listed:
- `ADMIN`
- `CATEGORY`
- `DISPUTE`
- `FINE`
- `NOTIFICATION`
- `PRODUCT`
- `PRODUCT_IMAGES`
- `RENTAL_REQUEST`
- `REVIEW`
- `TRANSACTION`
- `USER`
- `USER_ROLES`

---

#### Test 2: Verify Seed Admin Account
Run:
```sql
SELECT admin_id, username, email, full_name, status, created_date FROM `ADMIN`;
```
**Expected Output:**
| admin_id | username | email | full_name | status | created_date |
|:---:|:---|:---|:---|:---|:---|
| 1 | admin | admin@orms.com | System Administrator | Active | [Timestamp] |

---

#### Test 3: Verify Seed Categories
Run:
```sql
SELECT category_id, category_name, description, parent_category_id FROM `CATEGORY`;
```
**Expected Output:** 3 categories:
1. `Electronics`
2. `Furniture`
3. `Vehicles`

---

#### Test 4: Verify Seed Users & Roles
Run:
```sql
SELECT u.user_id, u.name, u.email, u.phone, u.status, ur.role 
FROM `USER` u
JOIN `USER_ROLES` ur ON u.user_id = ur.user_id
ORDER BY u.user_id, ur.role;
```
**Expected Output:**
- Rahul Sharma (`user_id = 1`) has **two roles**: `Owner` and `Renter`
- Priya Patel (`user_id = 2`) has **one role**: `Renter`

---

#### Test 5: Verify Table Engine & Foreign Key Constraints
Run:
```sql
SELECT TABLE_NAME, ENGINE 
FROM information_schema.TABLES 
WHERE TABLE_SCHEMA = 'orms_db';
```
**Expected Output:** Every table has `ENGINE = InnoDB`.

Run:
```sql
SELECT 
    TABLE_NAME, 
    COLUMN_NAME, 
    CONSTRAINT_NAME, 
    REFERENCED_TABLE_NAME, 
    REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = 'orms_db' AND REFERENCED_TABLE_NAME IS NOT NULL
ORDER BY TABLE_NAME;
```
**Expected Output:** Foreign keys properly established across all related tables:
- `USER_ROLES` &rarr; `USER(user_id)`
- `CATEGORY` &rarr; `CATEGORY(category_id)` (self-referencing for subcategories)
- `PRODUCT` &rarr; `USER(user_id)`, `CATEGORY(category_id)`
- `PRODUCT_IMAGES` &rarr; `PRODUCT(product_id)`
- `RENTAL_REQUEST` &rarr; `PRODUCT(product_id)`, `USER(user_id)`
- `TRANSACTION` &rarr; `RENTAL_REQUEST(request_id)`, `USER(user_id)`
- `FINE` &rarr; `RENTAL_REQUEST(request_id)`, `USER(user_id)`, `TRANSACTION(transaction_id)`
- `REVIEW` &rarr; `RENTAL_REQUEST(request_id)`, `PRODUCT(product_id)`, `USER(user_id)`
- `NOTIFICATION` &rarr; `USER(user_id)`
- `DISPUTE` &rarr; `RENTAL_REQUEST(request_id)`, `USER(user_id) [raised_by]`, `USER(user_id) [against]`

---

#### Test 6: Verify Indexes & Fulltext Index
Run:
```sql
SHOW INDEX FROM `PRODUCT`;
```
**Expected Output:**
- Primary key on `product_id`
- Index `idx_product_avail_status` on `avail_status`
- Fulltext index `idx_product_fulltext` on `(title, description)`

---

## 4. Step 2: Database Connection (`config/database.php`) & Testing Guide

### 4.1 Architecture & Security Overview
The file `config/database.php` implements a thread-safe **Singleton pattern**:
- **Single Connection Instance:** Guarantees only one PDO instance is created and shared across the PHP process lifecycle.
- **Genuine Server-Side Prepared Statements:** `PDO::ATTR_EMULATE_PREPARES => false` ensures queries are securely parsed and executed on MySQL, completely eliminating SQL injection vectors (Synopsis Section 5 & Prompt Guide Section 7).
- **UTF-8 Support:** Enforces `utf8mb4` encoding and collation `utf8mb4_unicode_ci` at connection initialization.
- **Exception Mode:** Configured with `PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION` for structured exception handling.

### 4.2 How to Test the Connection

A standalone test suite [`test_db.php`](file:///c:/Users/Dell/Desktop/Projects/ORMS/test_db.php) is provided in the project root. You can test it either via Terminal (CLI) or directly through your Web Browser.

#### Method A: Testing via Terminal / Command Line (Instant)
Run the following command in PowerShell:
```powershell
C:\xampp\php\php.exe test_db.php
```

#### Method B: Testing via Web Browser
1. Make sure Apache and MySQL are running in the XAMPP Control Panel.
2. Open your browser and navigate to:
   ```
   http://localhost/orms/test_db.php
   ```
3. You will see a Tailwind CSS visual dashboard displaying 8 automated tests.

---

### 4.3 Automated Verification Checklist (8 Tests)

| # | Test Case | Target Checked | Expected Status |
|:---:|:---|:---|:---:|
| 1 | **Singleton Pattern Test** | `Database::getInstance() === Database::getInstance()` | **PASS** |
| 2 | **PDO Instance Retrieval** | `$pdo instanceof PDO` with identical connection handle | **PASS** |
| 3 | **Charset & Collation** | `utf8mb4` / `utf8mb4_unicode_ci` active on connection | **PASS** |
| 4 | **Security Configuration** | `PDO::ATTR_EMULATE_PREPARES` is `false` (Native Prepares) | **PASS** |
| 5 | **Schema Table Integrity** | All 12 tables present in `orms_db` | **PASS** |
| 6 | **Seed Admin Check** | Prepared statement retrieves `admin@orms.com` | **PASS** |
| 7 | **Seed Categories Check** | Exactly 3 categories: Electronics, Furniture, Vehicles | **PASS** |
| 8 | **Seed Users & Roles Check** | Rahul Sharma (Owner, Renter) & Priya Patel (Renter) | **PASS** |

---

## 5. Step 3: Authentication & User Management Module (`auth/`) Testing Guide

### 5.1 Architecture & Security Implemented
1. **Multi-Role User Architecture (`USER_ROLES`):**
   - Users can register as **Owner**, **Renter**, or **Both**.
   - Dual-role users can toggle their active workspace dynamically via the navbar pill or [`auth/switch_role.php`](file:///c:/Users/Dell/Desktop/Projects/ORMS/auth/switch_role.php) without having to re-authenticate (Synopsis Rule 14).
2. **Strict Magic-Byte MIME Validation:**
   - Identity proof documents are validated using PHP `finfo_file(FILEINFO_MIME_TYPE)` inspecting true file header bytes, not easily spoofed client-side extensions or `Content-Type` headers. Allowed types: PDF, PNG, JPEG.
   - Uploaded files are renamed using cryptographically secure random names (`id_[16-bytes-hex].[ext]`) and stored in `uploads/id_proofs/`, protected by `.htaccess`.
3. **Session Security & Fixation Prevention:**
   - Calls `session_regenerate_id(true)` upon successful authentication and role switching (Prompt Guide Section 7).
   - Cookies configured with `HttpOnly`, `SameSite=Lax`, and strict cookie parameters.
4. **Hashed, Time-Limited Password Reset Tokens:**
   - Generates 64-character cryptographically secure hex tokens via `bin2hex(random_bytes(32))`.
   - The raw token is sent to the user/simulation link, while **only the SHA-256 hash** (`hash('sha256', $rawToken)`) is stored in `USER.reset_token`.
   - Expires strictly after 1 hour (`DATE_ADD(NOW(), INTERVAL 1 HOUR)`).
   - Once used, the token is permanently invalidated (`reset_token = NULL, reset_token_expiry = NULL`).
5. **Anti-CSRF Protection & Generic Failure Messages:**
   - Every state-changing POST form embeds `<?= csrf_field() ?>` and verifies it server-side.
   - Login failures display generic messages ("Invalid email/username or password") to prevent username enumeration attacks.

---

### 5.2 How to Test the Auth Module

#### Method A: Automated Test Suite (Instant Verification)
Run the automated verification script in PowerShell:
```powershell
C:\xampp\php\php.exe test_auth.php
```
Or view the visual dashboard in your browser:
```
http://localhost/orms/test_auth.php
```
**Expected Output:** All 8 automated tests pass with green badges:
1. `[ PASS ]` User Registration & Dual-Role Junction Insert
2. `[ PASS ]` Multi-Role Junction Integrity Check
3. `[ PASS ]` Authentication Rejection (Wrong Password)
4. `[ PASS ]` User Login & Session Regeneration Test
5. `[ PASS ]` Admin Login & Role Verification
6. `[ PASS ]` Password Reset Token Hashing & Expiry (Rule 3)
7. `[ PASS ]` Password Reset Execution & Single-Use Invalidation
8. `[ PASS ]` File Upload `finfo_file` Magic-Byte Security Check

---

#### Method B: Manual Interactive Browser Walkthrough

##### Test 1: User Registration
1. Go to: `http://localhost/orms/auth/register.php`
2. Fill out the form:
   - **Full Name:** `Amit Verma`
   - **Email:** `amit@example.com`
   - **Phone:** `9876501234`
   - **Address:** `Flat 101, Palm Grove, Sector 14, Gurgaon`
   - **Password:** `Amit@123` / Confirm: `Amit@123`
   - **ID Proof Document:** Attach any sample JPG, PNG, or PDF file.
   - **Role Checkboxes:** Check both **Owner** and **Renter**.
3. Click **Create My Account**.
4. **Expected Result:** Redirected to `auth/login.php` with a green banner: *"Registration successful! Your account has been created."*

##### Test 2: User Login & Dual-Role Switching
1. On `http://localhost/orms/auth/login.php`, enter:
   - **Email:** `rahul@example.com` (or your newly registered email)
   - **Password:** `Password@123` (or `Amit@123`)
2. Click **Sign In**.
3. **Expected Result:**
   - Logged in and redirected to `owner/dashboard.php`.
   - In the top navbar, you will see your name and an **Active Role** switcher pill: `[Owner] [Renter]`.
   - Click on **Renter** in the navbar: instantly switches to `renter/dashboard.php` without re-login!
   - Click on **Owner**: switches back to `owner/dashboard.php`.

##### Test 3: Admin Login
1. Click **Logout** in the top navbar.
2. Go to `http://localhost/orms/auth/login.php` and enter:
   - **Email or Admin Username:** `admin` (or `admin@orms.com`)
   - **Password:** `Admin@123`
3. Click **Sign In**.
4. **Expected Result:**
   - Redirected to `admin/dashboard.php` showing the Administrator Portal and live metrics.

##### Test 4: Forgot & Reset Password Flow
1. Log out, then click **Forgot password?** on the login page or go to:
   ```
   http://localhost/orms/auth/forgot_password.php
   ```
2. Enter `priya@example.com` and click **Generate Reset Link**.
3. **Expected Result:**
   - A green confirmation box appears with a direct testing link (e.g. `http://localhost/orms/auth/reset_password.php?token=...`).
4. Click the link to open the reset password screen.
5. Enter a new password (e.g. `NewPriya@123`), confirm it, and submit.
6. **Expected Result:**
   - Redirected to `login.php` with message *"Your password has been reset successfully!"*
   - Log in with `priya@example.com` and `NewPriya@123` &rarr; Successfully signed in!

---

## 6. Default Seed Credentials Reference

Save these credentials for future testing during the upcoming modules:

| Role | Username / Email | Password | Intended Use |
|---|---|---|---|
| **Admin** | `admin` (or `admin@orms.com`) | `Admin@123` | Testing Admin Dashboard, Category Management, Disputes, Fine Rates |
| **Owner (+ Renter)** | `rahul@example.com` | `Password@123` | Testing Product Listing, Request Approvals, Returns, Fines |
| **Renter** | `priya@example.com` | `Password@123` | Testing Search, Booking Requests, Payments, Reviews, Disputes |

---

## 7. Troubleshooting Common Issues

1. **Error: "Access denied for user 'root'@'localhost'"**
   - In XAMPP, the default MySQL user is `root` with an empty password. If you set a root password, supply it when connecting.
2. **Error: "Table already exists"**
   - The script contains `DROP TABLE IF EXISTS` in safe order with `FOREIGN_KEY_CHECKS = 0`. Re-importing will cleanly recreate the database.
3. **Foreign key error on import:**
   - Always run the entire file as a single script so `SET FOREIGN_KEY_CHECKS = 0;` runs first.
4. **File upload error ("File format not allowed"):**
   - Ensure the uploaded file has genuine magic bytes of JPEG (`FF D8 FF`), PNG (`89 50 4E 47`), or PDF (`%PDF`). Renaming a text file to `.jpg` will be rejected by `finfo_file` for security.


