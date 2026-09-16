# ORMS (Online Rental Management System) — Setup and Testing Guide

> **Project:** Online Rental Management System (BCSP-064, IGNOU BCA Final Project)  
> **Student:** Atif Zafar  
> **Tech Stack:** PHP 8.1+, MySQL 8.0 / MariaDB 10.4+, Apache (XAMPP), Tailwind CSS  
> **Status:** Updated with **Step 9 (Review & Rating Module & Owner Product Deletion)**

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
| **6** | **Rental Request Module (`renter/`, `owner/`)** | **COMPLETED** | Concurrency-safe `SELECT ... FOR UPDATE`, approve/reject, free cancel, 3NF compliant |
| **7** | **Financial / Transaction Module (`renter/`, `owner/`)** | **COMPLETED** | Dynamic snapshot amounts (Rule 7), escrow deposit, academic simulated checkout, tax receipts |
| **8** | **Fine Module (`owner/`, `admin/`, `renter/`)** | **COMPLETED** | Late auto-detection, Rule 9 2× deposit cap, Rule 8 deposit deductions, Rule 12 auto-refund |
| **9** | **Review & Rating Module (`renter/`, `owner/`)** | **COMPLETED** | Rule 10 post-completion check, duplicate review prevention, dynamic averages & trust score, owner product deletion |
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

## 8. Step 6: Rental Request & Concurrency Module Testing Guide

### 8.1 What Was Built
1. **`classes/RentalRequest.php`**: Complete entity class with `createWithLock()`, `approve()`, `reject()`, `cancel()`, dynamic runtime calculation methods `getTotalDays()` and `getTotalAmount()`, and static query finders (`findById()`, `findByRenter()`, `findByOwner()`).
2. **`classes/Notification.php`**: Entity class for database-driven notifications (`create()`, `send()`, `countUnread()`, `findByUser()`, `markAsRead()`, `markAllReadByUser()`).
3. **`classes/Owner.php` & `classes/Renter.php`**: Integrated with `manageRentalRequest()`, `sendRequest()`, and `cancelRequest()`.
4. **`renter/request_rental.php`**: Interactive rental booking UI with live JavaScript cost calculator (Days, Rent, Deposit, Total), date pickers, CSRF protection, and atomic booking submission.
5. **`owner/manage_requests.php`**: Owner decision workspace with status filter tabs (All, Pending, Approved, Active, Completed, Rejected, Cancelled), Approve button, and modal Reject dialog with mandatory reason.
6. **`renter/my_rentals.php`**: Renter rental history with status filter tabs, action buttons (Proceed to Pay for Step 7, Cancel Booking modal with reason), and dynamic price breakdowns.
7. **`renter/cancel_request.php`**: Secure POST endpoint for renter cancellations with CSRF verification.
8. **`notifications/view_notifications.php`**: Notification feed with unread badges, mark single/all as read, and filter by event type.
9. **`test_rental.php`**: Automated test suite covering 8 critical verification tests, including double-booking concurrency race condition tests and 3NF schema compliance.

---

### 8.2 Automated Verification (Instant Test)

Run the automated verification suite from your project folder:

#### Method A: Via Command Line (CLI)
```powershell
C:\xampp\php\php.exe test_rental.php
```

**Expected CLI Output:**
```
=======================================================
  ORMS Automated Verification — Step 6: Rental Requests
=======================================================
1. [ PASS ] 3NF Schema Compliance (No Redundant Computed Columns)
   -> PASS: Verified RENTAL_REQUEST table conforms to 3NF — total_days and total_amount are NOT stored in the database table and are computed dynamically.

2. [ PASS ] Date Range Validation (Rejects Past & Reversed Dates)
   -> PASS: InvalidDateRangeException properly thrown for both past dates and end_date <= start_date.

3. [ PASS ] Owner Self-Rental Prevention
   -> PASS: Owner cannot book their own listing; rejected with ORMSException.

4. [ PASS ] Atomic Rental Request Submission (SELECT ... FOR UPDATE Locking)
   -> PASS: Request #1 created in 'Pending' status. Dynamic days=4, Dynamic total=₹4800. Owner notified.

5. [ PASS ] Owner Approval Workflow & Status Transition
   -> PASS: Request #1 transitioned from 'Pending' to 'Approved'. Renter received notification to pay.

6. [ PASS ] Double-Booking Prevention (Overlapping Booking Race Condition)
   -> PASS: Overlapping dates rejected with ProductUnavailableException ("The product has already been reserved or rented for the selected dates.").

7. [ PASS ] Owner Rejection with Mandatory Reason
   -> PASS: Request #2 rejected. Reason 'Equipment is reserved for scheduled maintenance.' successfully persisted.

8. [ PASS ] Renter Cancellation Workflow
   -> PASS: Request #3 cancelled by renter. Reason 'Personal plans changed.' persisted. Owner notified.

-------------------------------------------------------
OVERALL RESULT: ALL 8 TESTS PASSED! Rental Request module is 100% operational.
=======================================================
```

#### Method B: Via Browser
Open your browser and navigate to:
```
http://localhost/orms/test_rental.php
```
You will see a Tailwind-styled status dashboard displaying all 8 test badges marked as `PASS` in green.

---

### 8.3 Step-by-Step Manual Browser Walkthrough

##### Test 1: Dynamic Pricing Calculator & Booking Request Submission (Renter)
1. Log in as Renter: `priya@example.com` / `Password@123`.
2. Go to Catalog: `http://localhost/orms/renter/search.php`.
3. Click on any product listed by Rahul (e.g. Camera or MacBook) to view its details.
4. Click **"Request Rental Booking &rarr;"** (or visit `http://localhost/orms/renter/request_rental.php?product_id=[ID]`).
5. **Interactive Calculator Test:**
   - Change the **Start Date** and **End Date**.
   - Watch the **Price Breakdown** card dynamically update in real time without refreshing the page.
   - Notice that the rent multiplies by the duration, adds the refundable deposit, and calculates the total payable.
   - Set an invalid date (e.g., End Date before Start Date) &rarr; The submit button is automatically disabled and warns of invalid range.
6. Select valid future dates (e.g. 3 days from now for a 4-day rental), type a short message, and click **"Submit Booking Request &rarr;"**.
7. **Expected Result:**
   - You are redirected to `http://localhost/orms/renter/my_rentals.php`.
   - A green notification banner appears: *"Rental request #[ID] submitted successfully! The owner will review your booking."*
   - The booking card is displayed with an amber **Pending** badge.

##### Test 2: Owner Review & Approval Workflow
1. Log in as Owner: `rahul@example.com` / `Password@123`.
2. Go to **Owner Dashboard** (`http://localhost/orms/owner/dashboard.php`).
   - Notice the amber notification banner: *"You have X pending rental request(s) awaiting your decision."*
3. Click **"Manage Requests"** in the top navbar or banner (or visit `http://localhost/orms/owner/manage_requests.php`).
4. Click the **Pending** tab.
5. You will see Priya's booking card with her requested dates, duration, total amounts, contact details, and message.
6. Click the green **"✓ Approve"** button and confirm.
7. **Expected Result:**
   - The status updates immediately to **Approved**.
   - A green flash message confirms approval.
   - The card now displays: *"Awaiting Renter Payment"*.

##### Test 3: Notification Verification
1. Log back in as Renter: `priya@example.com` / `Password@123`.
2. Click the 🔔 **Notification Icon** in the top navbar (or visit `http://localhost/orms/notifications/view_notifications.php`).
3. **Expected Result:**
   - An unread notification is shown: *"Your rental request for '[Item]' has been approved! Please proceed with payment to confirm your booking."*
   - Go to `http://localhost/orms/renter/my_rentals.php`.
   - Priya's booking now displays a blue **Approved** badge and a prominent green **"💳 Proceed to Pay &rarr;"** button (ready for Step 7).

##### Test 4: Concurrency & Double-Booking Prevention Test
1. While Priya's request is **Approved** for specific dates, try to book the exact same product for overlapping dates (as another renter or via `test_rental.php`).
2. **Expected Result:**
   - The system intercepts the transaction during `SELECT ... FOR UPDATE` row inspection and prevents double-booking, throwing a `ProductUnavailableException` ("The product has already been reserved or rented for the selected dates.").

##### Test 5: Rejection and Cancellation Flow
1. Submit another test request as Renter.
2. In Owner workspace (`owner/manage_requests.php`), click **"✕ Reject"** &rarr; Enter a decline reason in the modal and submit.
3. Renter will see the status updated to **Rejected** with the reason displayed.
4. Submit a third test request as Renter. In `renter/my_rentals.php`, click **"✕ Cancel Request"** &rarr; The booking transitions to **Cancelled** with zero penalty per Section 5 Rule 6.

---

## 9. Step 7: Financial & Transaction Module Testing Guide

### 9.1 Automated Test Suite Execution

We have built a dedicated automated verification test suite for Step 7 (`test_transaction.php`) that runs 8 assertions covering atomic payments, 3NF snapshot calculations, deposit escrow holds, lifecycle transitions, automated multi-party notifications, receipt generation, and refund logic.

#### Method A: Via Command Line (PowerShell)
Run the following command in the project root:
```powershell
C:\xampp\php\php.exe test_transaction.php
```
**Expected Output:**
```
=======================================================
  ORMS Automated Verification — Step 7: Transactions
=======================================================
1. [ PASS ] Unapproved Request Payment Rejection
2. [ PASS ] Unauthorized Payer Access Control
3. [ PASS ] Atomic Payment Processing (Transaction Creation)
4. [ PASS ] Financial Snapshot Calculation & Deposit Escrow (Rule 7)
5. [ PASS ] Lifecycle Status Transitions (Request -> Active, Product -> Rented)
6. [ PASS ] Multi-Party Payment Notifications (Rule 11)
7. [ PASS ] Official Receipt Generation (getReceipt())
8. [ PASS ] Security Deposit Refund Engine (Rule 8 Clean Return)
-------------------------------------------------------
OVERALL RESULT: ALL 8 TESTS PASSED!
=======================================================
```

#### Method B: Via Web Browser
Open your browser and navigate to:
```
http://localhost/orms/test_transaction.php
```
You will see an emerald badge: **"Ready for Step 8"** with all 8 tests passing with clean green badges.

---

### 9.2 Manual End-to-End Walkthrough (Renter & Owner Flow)

##### Test 1: Payment Checkout Flow (Renter End)
1. Log in as Renter: `priya@example.com` / `Password@123`.
2. Ensure you have an **Approved** rental request (if not, request any item and approve it using `rahul@example.com` in `owner/manage_requests.php`).
3. Navigate to **My Rentals** (`http://localhost/orms/renter/my_rentals.php`).
4. Look for the card with the blue **Approved** badge.
5. Click the green **"💳 Proceed to Pay &rarr;"** button.
6. You will be redirected to the secure checkout page: `http://localhost/orms/renter/pay.php?request_id=...`
7. Verify the itemized breakdown:
   - Rental Charges: `Number of Days × Rent Per Day`
   - Refundable Security Deposit: Held in Escrow
   - **Total Payable Amount** = Rental Charges + Deposit
8. Select an Academic Payment Mode (e.g., **UPI / QR Code**, **Debit Card**, **Credit Card**, or **Net Banking**).
9. Click **"Confirm & Complete Payment"**.
10. **Expected Result:**
    - Payment is atomically processed within a database transaction.
    - Renter is redirected to the official Tax & Rental Receipt (`renter/receipt.php`).
    - The booking status transitions to **Active**.
    - The product availability transitions to **Rented**.

##### Test 2: Official Printable Invoice / Receipt Verification
1. On the Receipt page (`renter/receipt.php?request_id=...`):
   - Notice the unique receipt number (e.g., `ORMS-REC-000001`).
   - Check the transaction timestamp, payment method, payer details, and lender details.
   - Verify the itemized table displays the rent breakdown and deposit escrow term.
   - Click the **"🖨️ Print / Download PDF"** button (triggers browser print dialog with print-optimized CSS).
2. Go back to **My Rentals** (`renter/my_rentals.php`).
   - The booking now shows an active green **Active** badge.
   - A new button **"📄 View Receipt"** is displayed linking directly to the invoice.

##### Test 3: Owner Financial Ledger & Earnings Review
1. Log in as Owner: `rahul@example.com` / `Password@123`.
2. Go to **Owner Earnings** (`http://localhost/orms/owner/earnings.php`) or click **Earnings** in the top navbar.
3. **Expected Result:**
   - **Lifetime Gross Earnings** metric card correctly sums rental charges earned.
   - **Active Security Deposits** card tracks escrow funds held securely until item return.
   - **Completed Transactions** count card updates.
   - The **Transaction History Table** lists the transaction with date, receipt link, product name, payer, rental rent, and escrow deposit status (`Held`).
4. Go to **Manage Requests** (`owner/manage_requests.php`).
   - Under the **Active** tab, the booking appears with an **"Active & Paid"** badge and a **"Receipt"** link.

---

## 10. Step 8: Fine & Return Management Module Testing Guide

### 10.1 Automated Test Suite Execution

We have built a dedicated automated verification test suite for Step 8 (`test_fine.php`) covering 8 critical test cases: late return auto-detection, Rule 9 2× deposit statutory cap, clean return 100% deposit refund, fine $\le$ deposit deduction, fine $>$ deposit forfeiture with excess unpaid balance, renter fine payment via `payFine()`, security access controls, and Rule 12 7-day auto-refund timeout.

#### Method A: Via Command Line (PowerShell)
Run the following command in the project root:
```powershell
C:\xampp\php\php.exe test_fine.php
```
**Expected Output:**
```
=======================================================
  ORMS Automated Verification — Step 8: Fine Module
=======================================================
1. [ PASS ] Late Return Formula & Calculation
2. [ PASS ] Statutory Maximum Fine Cap Enforcement (Rule 9)
3. [ PASS ] Clean Return Execution (100% Escrow Deposit Refund)
4. [ PASS ] Fine <= Deposit Deduction (Partial Refund per Rule 8)
5. [ PASS ] Fine > Deposit Handling (Deposit Forfeited & Excess Unpaid)
6. [ PASS ] Renter Payment of Outstanding Fine (payFine())
7. [ PASS ] Security & Authorization Access Control
8. [ PASS ] Rule 12 Auto-Refund 7-Day Timeout Engine
-------------------------------------------------------
OVERALL RESULT: ALL 8 TESTS PASSED! Fine & Return module is 100% operational.
=======================================================
```

#### Method B: Via Web Browser
Open your browser and navigate to:
```
http://localhost/orms/test_fine.php
```
You will see an emerald badge: **"Ready for Step 9"** with all 8 tests passing with clean green badges.

---

### 10.2 Manual End-to-End Walkthrough (Owner, Renter & Admin Flows)

##### Test 1: Clean Return Confirmation (100% Full Deposit Refund)
1. Log in as Owner: `rahul@example.com` / `Password@123`.
2. Go to **Manage Requests** (`http://localhost/orms/owner/manage_requests.php?status=Active`).
3. Under an active rental card, click the blue button **"📦 Confirm Return"**.
4. You will be redirected to the assessment page: `http://localhost/orms/owner/raise_fine.php?request_id=...`
5. Keep the actual return date as today (or within scheduled dates) and select condition **"Clean / Good"**.
6. Note the settlement preview showing **Net Refund to Renter: 100% of Security Deposit**.
7. Click **"✓ Confirm Return & Finalize Settlement"**.
8. **Expected Result:**
   - Green flash confirmation message.
   - Rental status transitions to **Completed**.
   - Product status transitions back to **Available**.
   - Transaction deposit status transitions to **Refunded**.
   - Notifications sent to both Renter and Owner.

##### Test 2: Overdue Return Assessment (Rule 9 Fine Calculation & Cap)
1. On an active rental, navigate to `owner/raise_fine.php?request_id=...`.
2. Pick an actual return date that is 3 days past the scheduled `end_date`.
3. **Expected Result:**
   - The late detection alert activates immediately: shows `3 Day(s) Late × ₹150.00/day = ₹450.00`.
   - The fine preview updates in real-time.
   - If test dates are pushed 20+ days into the future, verify the cap message: *(Capped at 2× Security Deposit)*.

##### Test 3: Damage Assessment & Deposit Deduction (Rule 8)
1. On the return page (`owner/raise_fine.php?request_id=...`), select condition radio **"Damaged"**.
2. An assessment panel slides open. Enter assessed repair cost (e.g. ₹500) and notes.
3. Observe the live settlement breakdown:
   - Total Assessed Fines = Late Fee + Damage Fee.
   - If Total Fine $\le$ Deposit: Net Refund = Deposit - Total Fine. Deposit status becomes **Partially_Refunded**.
   - If Total Fine $>$ Deposit: Deposit is **Forfeited**. Net Refund is ₹0. Renter owes remaining balance with status **Unpaid**.
4. Submit the form.
5. In **My Rentals** (`renter/my_rentals.php`), Renter Priya will see the detailed breakdown. If there is an unpaid excess balance, a red **"⚠️ Pay Fine"** button is displayed.
6. Clicking **Pay Fine** opens `renter/pay_fine.php`, where Priya can settle the balance using simulated UPI/Cards.

##### Test 4: Admin Daily Late Rate Configuration
1. Log in as Admin: `admin` / `Admin@123`.
2. Go to `http://localhost/orms/admin/configure_fine_rate.php`.
3. Update the daily late rate (e.g. from ₹150.00 to ₹200.00 / day).
4. Click **"💾 Save Configuration"**.
5. The new rate is persisted in `config/fine_settings.json` and will be applied to all future late return calculations.

##### Test 5: Rule 12 Auto-Refund Timeout Script
1. Run the auto-refund scheduled task:
   ```powershell
   C:\xampp\php\php.exe scripts/auto_refund_timeout.php
   ```
2. Any active rentals where `end_date` is older than 7 days without owner confirmation are automatically processed with 100% full deposit refunds.

---

## 11. Step 9: Review & Rating Module & Owner Product Deletion Testing Guide

### 11.1 Features Implemented
1. **Rule 10 Verified Review Constraint:** Reviews can only be submitted for rental requests in `Completed` status (`validateRentalCompleted()`).
2. **Duplicate Review Prevention:** A database-level `UNIQUE KEY uk_review_request (request_id)` prevents multiple reviews per rental. Second attempts throw `DuplicateReviewException`.
3. **1–5 Star Rating & Feedback:** Comprehensive star rating widget with hover animation, validation, and optional text feedback.
4. **Dynamic Computed Averages (3NF Compliant):**
   - Product average rating: `AVG(rating)` computed dynamically via query, never stored as a static column.
   - Owner trust score: `AVG(r.rating)` across all reviews for products owned by that user.
5. **Real-Time Notification:** Owner receives an immediate in-app notification when a renter submits a review for their product.
6. **Owner Product Deletion (User-Requested Feature):**
   - Owners can permanently delete products they own directly from their dashboard.
   - **Safety Protection:** Products currently marked `Rented` or having `Pending`/`Approved`/`Active` rental requests cannot be deleted (`ORMSException`).
   - Image file cleanup: Associated product images uploaded to disk are automatically deleted.

### 11.2 Automated Verification Script
Run the automated 9-assertion verification suite via CLI:
```powershell
C:\xampp\php\php.exe test_review.php
```

Or run via browser:
```
http://localhost/orms/test_review.php
```

Expected output:
```
=======================================================
  ORMS Automated Verification — Step 9: Review Module
=======================================================
1. [ PASS ] Uncompleted Rental Review Rejection (Rule 10)
2. [ PASS ] Unauthorized Reviewer Rejection
3. [ PASS ] Valid Review Submission & Persistence
4. [ PASS ] Duplicate Review Prevention (DuplicateReviewException)
5. [ PASS ] Out-of-Range Star Rating Rejection
6. [ PASS ] Dynamic Product Average Rating Calculation (Rule 10)
7. [ PASS ] Dynamic Owner Trust Score Calculation (Rule 10)
8. [ PASS ] Automated Notification to Owner on Review Submission
9. [ PASS ] Owner Product Deletion & Disk Image Cleanup
-------------------------------------------------------
OVERALL RESULT: ALL 9 TESTS PASSED! Review & Product Deletion module is 100% operational.
=======================================================
```

### 11.3 Manual Walkthrough & Testing

#### Scenario A: Submitting a Verified Review (Renter Flow)
1. Log in as Renter Priya: `priya@example.com` / `Password@123`.
2. Go to **My Rentals** (`http://localhost/orms/renter/my_rentals.php`).
3. Locate any rental card in the **Completed Rentals** section.
4. If not yet reviewed, click the amber **"⭐ Write Review"** button.
5. On the review page (`renter/submit_review.php`):
   - Hover and click on the stars to select a rating (1 to 5).
   - Enter feedback in the text area (e.g., *"Excellent condition, well maintained and very helpful host!"*).
   - Click **"Submit Verified Review"**.
6. You will be redirected back to My Rentals with a success message, and the card now displays **"Reviewed (★ X/5)"**.
7. Try opening `renter/submit_review.php?request_id=<completed_id>` again: it will open in read-only mode showing your previously submitted review and preventing duplicates.

#### Scenario B: Checking Dynamic Star Ratings & Owner Trust Score
1. Go to the public catalog (`http://localhost/orms/index.php` or `renter/search.php`).
2. Click on the product that was just reviewed to open its details (`renter/product_details.php?id=<product_id>`).
3. Observe:
   - Dynamic star rating (e.g., ★ 5.0) and review count.
   - Individual verified customer reviews listed with star ratings, reviewer names, dates, and comments.
   - Owner trust badge showing the dynamically computed average score across all products.

#### Scenario C: Owner Product Deletion Feature
1. Log in as Owner Rahul: `rahul@example.com` / `Password@123`.
2. Go to **Owner Dashboard** (`http://localhost/orms/owner/dashboard.php`).
3. Scroll to the **"My Listed Products"** section.
4. For any product that is `Available` or `Inactive`:
   - Click the red **"🗑️ Delete"** button.
   - A confirmation modal appears asking: *"Are you sure you want to permanently delete this product?"*
   - Confirm deletion.
   - The product is permanently removed from the database and its images are cleared from `uploads/products/`.
5. For any product that is currently **Rented**:
   - The Delete button is disabled with a tooltip: *"Cannot delete a currently rented product"*.
   - Direct POST requests to `owner/delete_product.php` for rented items are strictly blocked with an error.

---

## 12. Default Seed Credentials Reference

Save these credentials for future testing during the upcoming modules:

| Role | Username / Email | Password | Intended Use |
|---|---|---|---|
| **Admin** | `admin` (or `admin@orms.com`) | `Admin@123` | Testing Admin Dashboard, Category Management, Disputes, Fine Rates |
| **Owner (+ Renter)** | `rahul@example.com` | `Password@123` | Testing Product Listing, Request Approvals, Returns, Fines |
| **Renter** | `priya@example.com` | `Password@123` | Testing Search, Booking Requests, Payments, Reviews, Disputes |

---

## 13. Troubleshooting Common Issues

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
6. **Booking Error ("The product has already been reserved or rented for the selected dates"):**
   - This indicates that `SELECT ... FOR UPDATE` concurrency protection is actively working. Check existing bookings under `RENTAL_REQUEST` or choose non-overlapping dates.




