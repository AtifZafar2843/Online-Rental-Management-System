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
| **4** | **Product Listing Module (Owner) (`owner/`)** | **COMPLETED** | Multi-image upload (magic-bytes), CRUD, condition, deposit, toggle status |
| **5** | **Search & Discovery Module (`renter/`, `index.php`)** | **COMPLETED** | Public catalog (Guest), filters (category/price/location), product details |
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

## 6. Step 4: Product Listing Module (Owner) (`owner/`) Testing Guide

### 6.1 Architecture & Security Implemented
1. **Product OOP Entity ([`classes/Product.php`](file:///c:/Users/Dell/Desktop/Projects/ORMS/classes/Product.php)):**
   - Implements all methods from Synopsis Section 11.1: `checkAvailability($start, $end)`, `updateStatus($status)`, `getImages()`, `getPrimaryImagePath()`, `save()`, `addImage()`, `findById()`, `findByOwner()`.
   - Strictly enforces CHECK constraints (`rent_per_day > 0`, `security_deposit >= 0`) at both OOP and MySQL levels.
2. **Owner Entity Integration ([`classes/Owner.php`](file:///c:/Users/Dell/Desktop/Projects/ORMS/classes/Owner.php)):**
   - `addProduct(...)`: Persists product and associates multi-image records within an atomic transaction.
   - `editProduct(...)`: Verifies product ownership before saving updates (`owner_id === current_user_id()`).
   - `calculateTotalEarnings()`: Computes completed rental earnings directly from `TRANSACTION`.
3. **Multi-Image Magic-Byte Validation & Randomization:**
   - Validates each uploaded file using `finfo_file()` to inspect genuine binary magic bytes for JPEG, PNG, and WEBP.
   - Saves files to `uploads/products/` with cryptographically random filenames (`prod_[16-bytes-hex].[ext]`) to prevent path traversal or filename collision attacks (Prompt Guide Section 5 Rule 4 & Section 7).
   - Automatically marks the first image as Primary (`is_primary = 1`), and subsequent images as Secondary (`is_primary = 0`).
4. **Owner Inventory Dashboard ([`owner/dashboard.php`](file:///c:/Users/Dell/Desktop/Projects/ORMS/owner/dashboard.php)):**
   - Displays dynamic metrics: Total Listings, Available, Rented Out, and Total Earnings.
   - Interactive inventory table showing primary image thumbnails, category, daily rent, deposit, condition, availability badges (`Available`, `Rented`, `Unavailable`), and quick action links (Edit, Activate/Deactivate).

---

### 6.2 How to Test the Product Listing Module

#### Method A: Automated Test Suite (Instant Verification)
Run the automated verification script in PowerShell:
```powershell
C:\xampp\php\php.exe test_product.php
```
Or view the visual dashboard in your browser:
```
http://localhost/orms/test_product.php
```
**Expected Output:** All 8 automated tests pass with green badges:
1. `[ PASS ]` Product Model Check Constraint Validation
2. `[ PASS ]` Multi-Image Magic-Byte Verification (JPEG & PNG binaries)
3. `[ PASS ]` Product Creation & DB Insertion (`PRODUCT` Table)
4. `[ PASS ]` `PRODUCT_IMAGES` DB Rows & Files On Disk Check (2 images verified)
5. `[ PASS ]` `Product::getImages()` & Primary Image Retrieval
6. `[ PASS ]` `Owner::editProduct()` Update Verification
7. `[ PASS ]` `checkAvailability()` & `updateStatus()` Logic
8. `[ PASS ]` `Product::findByOwner()` Inventory Query

---

#### Method B: Manual Interactive Browser Walkthrough

##### Test 1: List a New Product with Multiple Images
1. Open your browser and log in as an Owner:
   - Go to: `http://localhost/orms/auth/login.php`
   - Email: `rahul@example.com`
   - Password: `Password@123`
2. Once logged in, click **List New Product** or visit:
   ```
   http://localhost/orms/owner/add_product.php
   ```
3. Fill in the product details:
   - **Product Title:** `Sony Alpha A7 III Full Frame Camera Kit`
   - **Category:** Select `Electronics`
   - **Detailed Description:** `Includes 28-70mm lens, 2 rechargeable batteries, 128GB high-speed SD card, and carrying case. Ideal for weddings and video shoots.`
   - **Daily Rent (₹/day):** `850`
   - **Security Deposit (₹):** `10000`
   - **Location:** `Indiranagar, Bangalore`
   - **Condition:** Select `Good` or `New`
   - **Product Images:** Select **2 or more image files** (JPG or PNG) from your computer.
4. Click **Publish Product Listing**.
5. **Expected Result:**
   - Redirected to `owner/dashboard.php` with a green banner: *"Product ... was listed successfully with 2 images!"*
   - The product card/row appears in the inventory table showing the primary photo thumbnail, category, ₹850/day rent, ₹10,000 deposit, and green **Available** badge.

##### Test 2: Verify in Database & File System
1. Open phpMyAdmin (`http://localhost/phpmyadmin/`), select `orms_db`:
   - Run query: `SELECT * FROM PRODUCT ORDER BY product_id DESC LIMIT 1;` &rarr; Your newly added product row is present.
   - Run query: `SELECT * FROM PRODUCT_IMAGES WHERE product_id = [NEW_ID];` &rarr; Exactly 2 (or more) image rows with `is_primary = 1` for the first image and `0` for the rest.
2. In File Explorer, check folder `C:\xampp\htdocs\orms\uploads\products\` &rarr; Notice the randomized filenames (e.g. `prod_...jpg`) corresponding to the database records.

##### Test 3: Edit Product & Manage Photos
1. In the Owner Dashboard (`owner/dashboard.php`), click **Edit** next to your product or visit:
   ```
   http://localhost/orms/owner/edit_product.php?id=[PRODUCT_ID]
   ```
2. Verify all current details and photos are pre-loaded.
3. In the **Current Photos** gallery:
   - Click **Make Primary** on the second photo &rarr; Notice it now becomes the primary thumbnail.
   - Test deleting a non-primary photo &rarr; Image is cleanly removed from both database and disk.
4. Change the **Daily Rent** to `950.00` and change **Condition** to `New`.
5. Click **Save Changes**.
6. **Expected Result:**
   - Redirected to `owner/dashboard.php` with success notification.
   - Updated daily rate (₹950.00) is reflected on the dashboard table.

##### Test 4: Toggle Availability (Deactivate / Activate)
1. On `owner/dashboard.php`, click **Deactivate** next to the product.
2. **Expected Result:**
   - Status badge turns to amber/gray **Unavailable**.
   - The button label changes to **Activate**.
3. Click **Activate** &rarr; Status returns to green **Available**.

---

## 7. Step 5: Search & Discovery Module (`renter/`, `index.php`) Testing Guide

### 7.1 Architecture & Features Implemented
1. **Public Catalog Access ([`renter/search.php`](file:///c:/Users/Dell/Desktop/Projects/ORMS/renter/search.php)):**
   - Completely open to Guests (unauthenticated visitors) as well as registered Renters and Owners without requiring login.
   - Live category count, responsive product cards, primary image thumbnail previews, location, and owner trust ratings.
2. **Multi-Dimensional Filters & Sorting:**
   - **Fulltext / Keyword Search:** Searches across product title and description.
   - **Category Filter:** Dynamic dropdown populated from `CATEGORY` table.
   - **Price Range Filter:** Min and max daily rental price (`rent_per_day`).
   - **Location Filter:** Matches pickup area or city name.
   - **Sorting:** Newest First, Rent: Low to High, Rent: High to Low, Title: A to Z.
3. **Product Detail View ([`renter/product_details.php`](file:///c:/Users/Dell/Desktop/Projects/ORMS/renter/product_details.php)):**
   - High-resolution interactive photo gallery with clickable thumbnails.
   - Full specifications, rental terms, and refundable security deposit details.
   - Verified Customer Reviews section pulling from `REVIEW` table with star ratings.
   - **Role-Aware Smart CTA:**
     - **If Guest:** Displays *"Sign In to Rent This Item"* &rarr; Redirects to login while preserving return path (`?redirect=...`).
     - **If Listing Owner:** Displays *"You own this product listing — Edit Listing Details"*.
     - **If Logged-in Renter:** Displays *"Request Rental Booking &rarr;"* (ready for Step 6).

---

### 7.2 How to Test the Search & Discovery Module

#### Method A: Automated Test Suite (Instant Verification)
Run the automated verification script in PowerShell:
```powershell
C:\xampp\php\php.exe test_search.php
```
Or view the visual dashboard in your browser:
```
http://localhost/orms/test_search.php
```
**Expected Output:** All 8 automated tests pass with green badges:
1. `[ PASS ]` Public Guest Access State (No login required)
2. `[ PASS ]` Test Product Inventory Ingestion (Electronics & Furniture items)
3. `[ PASS ]` Category Filter Verification
4. `[ PASS ]` Price Range Filter (`min_price` & `max_price`)
5. `[ PASS ]` Location Substring Filter
6. `[ PASS ]` Keyword Query Search
7. `[ PASS ]` Catalog Sorting (Price: Low to High)
8. `[ PASS ]` Product Details Entity & Relational Joins

---

#### Method B: Manual Interactive Browser Walkthrough (As Guest)

##### Test 1: Public Catalog & Hero Search (Not Logged In)
1. If you are currently logged in, click **Logout** in the top navbar.
2. Visit the homepage:
   ```
   http://localhost/orms/index.php
   ```
3. Notice the top navbar shows **Sign In** and **Get Started** (confirming you are a Guest).
4. In the hero section, type a keyword (e.g. `Camera` or `Sony`) into the search bar and press **Search** or Enter.
5. **Expected Result:**
   - Redirected to `renter/search.php?query=...` displaying matching items without asking for login.

##### Test 2: Category, Price & Location Filtering
1. On `http://localhost/orms/renter/search.php`:
   - Change **Category** dropdown to `Electronics` &rarr; Catalog immediately filters to show only electronic products.
   - Enter `Min Rent:` `500` and `Max Rent:` `2000` &rarr; Results update to items within this price band.
   - Enter a location substring (e.g. `Indiranagar` or `Bangalore`) &rarr; Location-matched items appear.
   - Change **Sort By** to `Rent: Low to High` &rarr; Lowest priced items appear first.
   - Click **Reset** &rarr; Restores default full catalog view.

##### Test 3: Product Details Page & Gallery Interaction
1. Click on any product card or title in the catalog, or visit:
   ```
   http://localhost/orms/renter/product_details.php?id=[PRODUCT_ID]
   ```
2. **Gallery Test:** If the item has multiple photos, click on the thumbnail images below the main photo &rarr; The main view smoothly switches images.
3. Verify that Daily Rent, Refundable Security Deposit, Condition, Description, and Owner details are displayed.
4. **Guest Booking Prompt Test:**
   - Since you are not logged in, you will see a prominent button: **"Sign In to Rent This Item &rarr;"**.
   - Click this button &rarr; You are taken to `auth/login.php?redirect=...`.
   - Log in with `priya@example.com` / `Password@123`.
   - Notice that after login, you are redirected back to the product details page.

##### Test 4: Owner Self-Listing View
1. Log in as `rahul@example.com` / `Password@123`.
2. Open a product that Rahul created (e.g. `http://localhost/orms/renter/product_details.php?id=[RAHUL_PRODUCT_ID]`).
3. **Expected Result:**
   - Instead of a rental booking button, the card displays: *"You own this product listing. [Edit Listing Details & Photos]"*.

---

## 8. Default Seed Credentials Reference

Save these credentials for future testing during the upcoming modules:

| Role | Username / Email | Password | Intended Use |
|---|---|---|---|
| **Admin** | `admin` (or `admin@orms.com`) | `Admin@123` | Testing Admin Dashboard, Category Management, Disputes, Fine Rates |
| **Owner (+ Renter)** | `rahul@example.com` | `Password@123` | Testing Product Listing, Request Approvals, Returns, Fines |
| **Renter** | `priya@example.com` | `Password@123` | Testing Search, Booking Requests, Payments, Reviews, Disputes |

---

## 9. Troubleshooting Common Issues

1. **Error: "Access denied for user 'root'@'localhost'"**
   - In XAMPP, the default MySQL user is `root` with an empty password. If you set a root password, supply it when connecting.
2. **Error: "Table already exists"**
   - The script contains `DROP TABLE IF EXISTS` in safe order with `FOREIGN_KEY_CHECKS = 0`. Re-importing will cleanly recreate the database.
3. **Foreign key error on import:**
   - Always run the entire file as a single script so `SET FOREIGN_KEY_CHECKS = 0;` runs first.
4. **File upload error ("File format not allowed"):**
   - Ensure the uploaded file has genuine magic bytes of JPEG (`FF D8 FF`), PNG (`89 50 4E 47`), or PDF (`%PDF`). Renaming a text file to `.jpg` will be rejected by `finfo_file` for security.
5. **Product images not showing in browser:**
   - Ensure `uploads/products/` exists and has standard read permissions. Relative paths are stored as `uploads/products/filename.jpg` and resolved via `base_url()`.




