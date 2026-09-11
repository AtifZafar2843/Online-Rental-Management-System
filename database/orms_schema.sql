-- ==============================================================================
-- Online Rental Management System (ORMS) - Database Schema
-- Project: BCSP-064 (IGNOU BCA Final Project)
-- Student: Atif Zafar
-- Reference: Synopsis Section 12 (3NF Compliant Data Structure) & Prompt Guide Section 3
-- Engine: MySQL 8.0+ / MariaDB 10.4+ (InnoDB Engine, utf8mb4)
-- ==============================================================================

CREATE DATABASE IF NOT EXISTS `orms_db` 
    DEFAULT CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE `orms_db`;

-- Temporarily disable foreign key checks during schema creation/reset
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS `DISPUTE`;
DROP TABLE IF EXISTS `NOTIFICATION`;
DROP TABLE IF EXISTS `REVIEW`;
DROP TABLE IF EXISTS `FINE`;
DROP TABLE IF EXISTS `TRANSACTION`;
DROP TABLE IF EXISTS `RENTAL_REQUEST`;
DROP TABLE IF EXISTS `PRODUCT_IMAGES`;
DROP TABLE IF EXISTS `PRODUCT`;
DROP TABLE IF EXISTS `CATEGORY`;
DROP TABLE IF EXISTS `USER_ROLES`;
DROP TABLE IF EXISTS `USER`;
DROP TABLE IF EXISTS `ADMIN`;

SET FOREIGN_KEY_CHECKS = 1;

-- ------------------------------------------------------------------------------
-- 1. ADMIN TABLE (Synopsis 12.2)
-- ------------------------------------------------------------------------------
CREATE TABLE `ADMIN` (
    `admin_id` INT AUTO_INCREMENT,
    `username` VARCHAR(50) NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `full_name` VARCHAR(100) NOT NULL,
    `status` ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active',
    `created_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`admin_id`),
    UNIQUE KEY `uk_admin_username` (`username`),
    UNIQUE KEY `uk_admin_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 2. USER TABLE (Synopsis 12.3)
-- ------------------------------------------------------------------------------
CREATE TABLE `USER` (
    `user_id` INT AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `email` VARCHAR(100) NOT NULL,
    `phone` VARCHAR(15) NOT NULL,
    `address` TEXT NOT NULL,
    `password` VARCHAR(255) NOT NULL,
    `id_proof_path` VARCHAR(255) NOT NULL,
    `rating` DECIMAL(3,2) NOT NULL DEFAULT 0.00,
    `reg_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Active', 'Inactive', 'Banned') NOT NULL DEFAULT 'Active',
    `reset_token` VARCHAR(64) NULL DEFAULT NULL,
    `reset_token_expiry` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`user_id`),
    UNIQUE KEY `uk_user_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 3. USER_ROLES TABLE (Synopsis 12.4 - Junction Table replacing ENUM anti-pattern)
-- ------------------------------------------------------------------------------
CREATE TABLE `USER_ROLES` (
    `user_id` INT NOT NULL,
    `role` ENUM('Owner', 'Renter') NOT NULL,
    `assigned_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`, `role`),
    CONSTRAINT `fk_user_roles_user` 
        FOREIGN KEY (`user_id`) REFERENCES `USER` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 4. CATEGORY TABLE (Synopsis 12.5)
-- ------------------------------------------------------------------------------
CREATE TABLE `CATEGORY` (
    `category_id` INT AUTO_INCREMENT,
    `category_name` VARCHAR(100) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `parent_category_id` INT NULL DEFAULT NULL,
    PRIMARY KEY (`category_id`),
    UNIQUE KEY `uk_category_name` (`category_name`),
    CONSTRAINT `fk_category_parent` 
        FOREIGN KEY (`parent_category_id`) REFERENCES `CATEGORY` (`category_id`) 
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 5. PRODUCT TABLE (Synopsis 12.6)
-- ------------------------------------------------------------------------------
CREATE TABLE `PRODUCT` (
    `product_id` INT AUTO_INCREMENT,
    `owner_id` INT NOT NULL,
    `category_id` INT NOT NULL,
    `title` VARCHAR(255) NOT NULL,
    `description` TEXT NOT NULL,
    `rent_per_day` DECIMAL(10,2) NOT NULL,
    `security_deposit` DECIMAL(10,2) NOT NULL,
    `location` VARCHAR(100) NOT NULL,
    `avail_status` ENUM('Available', 'Rented', 'Unavailable') NOT NULL DEFAULT 'Available',
    `condition` ENUM('New', 'Good', 'Fair', 'Poor') NOT NULL,
    `listed_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`product_id`),
    CONSTRAINT `chk_rent_per_day` CHECK (`rent_per_day` > 0),
    CONSTRAINT `chk_security_deposit` CHECK (`security_deposit` >= 0),
    CONSTRAINT `fk_product_owner` 
        FOREIGN KEY (`owner_id`) REFERENCES `USER` (`user_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_product_category` 
        FOREIGN KEY (`category_id`) REFERENCES `CATEGORY` (`category_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_product_avail_status` (`avail_status`),
    FULLTEXT KEY `idx_product_fulltext` (`title`, `description`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 6. PRODUCT_IMAGES TABLE (Synopsis 12.7)
-- ------------------------------------------------------------------------------
CREATE TABLE `PRODUCT_IMAGES` (
    `image_id` INT AUTO_INCREMENT,
    `product_id` INT NOT NULL,
    `image_path` VARCHAR(255) NOT NULL,
    `is_primary` BOOLEAN NOT NULL DEFAULT FALSE,
    `upload_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`image_id`),
    CONSTRAINT `fk_product_images_product` 
        FOREIGN KEY (`product_id`) REFERENCES `PRODUCT` (`product_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 7. RENTAL_REQUEST TABLE (Synopsis 12.8)
-- ------------------------------------------------------------------------------
CREATE TABLE `RENTAL_REQUEST` (
    `request_id` INT AUTO_INCREMENT,
    `product_id` INT NOT NULL,
    `renter_id` INT NOT NULL,
    `start_date` DATE NOT NULL,
    `end_date` DATE NOT NULL,
    `status` ENUM('Pending', 'Approved', 'Active', 'Completed', 'Rejected', 'Cancelled') NOT NULL DEFAULT 'Pending',
    `request_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `message` TEXT NULL DEFAULT NULL,
    `cancellation_reason` TEXT NULL DEFAULT NULL,
    PRIMARY KEY (`request_id`),
    CONSTRAINT `chk_rental_dates` CHECK (`end_date` > `start_date`),
    CONSTRAINT `fk_rental_request_product` 
        FOREIGN KEY (`product_id`) REFERENCES `PRODUCT` (`product_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_rental_request_renter` 
        FOREIGN KEY (`renter_id`) REFERENCES `USER` (`user_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    INDEX `idx_rental_request_product` (`product_id`),
    INDEX `idx_rental_request_renter` (`renter_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 8. TRANSACTION TABLE (Synopsis 12.9)
-- ------------------------------------------------------------------------------
CREATE TABLE `TRANSACTION` (
    `transaction_id` INT AUTO_INCREMENT,
    `request_id` INT NOT NULL,
    `payer_id` INT NOT NULL,
    `rental_amount` DECIMAL(10,2) NOT NULL,
    `deposit_amount` DECIMAL(10,2) NOT NULL,
    `deposit_status` ENUM('Held', 'Refunded', 'Partially_Refunded', 'Forfeited') NOT NULL DEFAULT 'Held',
    `payment_mode` VARCHAR(30) NOT NULL,
    `payment_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `payment_status` ENUM('Pending', 'Completed', 'Failed', 'Refunded') NOT NULL DEFAULT 'Pending',
    PRIMARY KEY (`transaction_id`),
    CONSTRAINT `fk_transaction_request` 
        FOREIGN KEY (`request_id`) REFERENCES `RENTAL_REQUEST` (`request_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_transaction_payer` 
        FOREIGN KEY (`payer_id`) REFERENCES `USER` (`user_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 9. FINE TABLE (Synopsis 12.10)
-- ------------------------------------------------------------------------------
CREATE TABLE `FINE` (
    `fine_id` INT AUTO_INCREMENT,
    `request_id` INT NOT NULL,
    `renter_id` INT NOT NULL,
    `transaction_id` INT NOT NULL,
    `fine_type` ENUM('Late_Return', 'Damage', 'Lost') NOT NULL,
    `amount` DECIMAL(10,2) NOT NULL,
    `late_days` INT NULL DEFAULT NULL,
    `rate_per_day` DECIMAL(10,2) NULL DEFAULT NULL,
    `issue_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `paid_date` DATETIME NULL DEFAULT NULL,
    `status` ENUM('Unpaid', 'Paid', 'Waived', 'Deducted_From_Deposit') NOT NULL DEFAULT 'Unpaid',
    PRIMARY KEY (`fine_id`),
    CONSTRAINT `chk_fine_amount` CHECK (`amount` > 0),
    CONSTRAINT `fk_fine_request` 
        FOREIGN KEY (`request_id`) REFERENCES `RENTAL_REQUEST` (`request_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fine_renter` 
        FOREIGN KEY (`renter_id`) REFERENCES `USER` (`user_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_fine_transaction` 
        FOREIGN KEY (`transaction_id`) REFERENCES `TRANSACTION` (`transaction_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 10. REVIEW TABLE (Synopsis 12.11)
-- ------------------------------------------------------------------------------
CREATE TABLE `REVIEW` (
    `review_id` INT AUTO_INCREMENT,
    `request_id` INT NOT NULL,
    `product_id` INT NOT NULL,
    `reviewer_id` INT NOT NULL,
    `rating` TINYINT NOT NULL,
    `comment` TEXT NULL DEFAULT NULL,
    `review_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`review_id`),
    UNIQUE KEY `uk_review_request` (`request_id`),
    CONSTRAINT `chk_review_rating` CHECK (`rating` BETWEEN 1 AND 5),
    CONSTRAINT `fk_review_request` 
        FOREIGN KEY (`request_id`) REFERENCES `RENTAL_REQUEST` (`request_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_review_product` 
        FOREIGN KEY (`product_id`) REFERENCES `PRODUCT` (`product_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_review_reviewer` 
        FOREIGN KEY (`reviewer_id`) REFERENCES `USER` (`user_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 11. NOTIFICATION TABLE (Synopsis 12.12)
-- ------------------------------------------------------------------------------
CREATE TABLE `NOTIFICATION` (
    `notif_id` INT AUTO_INCREMENT,
    `user_id` INT NOT NULL,
    `message` TEXT NOT NULL,
    `type` ENUM('Rental', 'Payment', 'Fine', 'Dispute', 'System') NOT NULL,
    `related_id` INT NULL DEFAULT NULL,
    `is_read` BOOLEAN NOT NULL DEFAULT FALSE,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`notif_id`),
    CONSTRAINT `fk_notification_user` 
        FOREIGN KEY (`user_id`) REFERENCES `USER` (`user_id`) 
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------------------------
-- 12. DISPUTE TABLE (Synopsis 12.13)
-- ------------------------------------------------------------------------------
CREATE TABLE `DISPUTE` (
    `dispute_id` INT AUTO_INCREMENT,
    `request_id` INT NOT NULL,
    `raised_by` INT NOT NULL,
    `against` INT NOT NULL,
    `reason` TEXT NOT NULL,
    `status` ENUM('Open', 'Under_Review', 'Resolved', 'Escalated') NOT NULL DEFAULT 'Open',
    `admin_notes` TEXT NULL DEFAULT NULL,
    `created_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `resolved_date` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`dispute_id`),
    CONSTRAINT `fk_dispute_request` 
        FOREIGN KEY (`request_id`) REFERENCES `RENTAL_REQUEST` (`request_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dispute_raised_by` 
        FOREIGN KEY (`raised_by`) REFERENCES `USER` (`user_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT `fk_dispute_against` 
        FOREIGN KEY (`against`) REFERENCES `USER` (`user_id`) 
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ==============================================================================
-- SEED DATA
-- Default Credentials:
--   Admin:  username = admin, password = Admin@123
--   User 1: email = rahul@example.com, password = Password@123 (Roles: Owner, Renter)
--   User 2: email = priya@example.com, password = Password@123 (Role: Renter)
-- ==============================================================================

-- Seed Admin
INSERT INTO `ADMIN` (`admin_id`, `username`, `password`, `email`, `full_name`, `status`, `created_date`) VALUES
(1, 'admin', '$2y$10$e24XPa9kkffj0GF82rueBOFU6LaatbdXbJjJCvC1Fwil9jS0wGnTG', 'admin@orms.com', 'System Administrator', 'Active', NOW());

-- Seed Categories
INSERT INTO `CATEGORY` (`category_id`, `category_name`, `description`, `parent_category_id`) VALUES
(1, 'Electronics', 'Laptops, cameras, audio equipment, gaming consoles, and tech accessories.', NULL),
(2, 'Furniture', 'Living room sets, ergonomic office chairs, study desks, and home furniture.', NULL),
(3, 'Vehicles', 'Bicycles, motorbikes, scooters, and passenger rental vehicles.', NULL);

-- Seed Sample Users
INSERT INTO `USER` (`user_id`, `name`, `email`, `phone`, `address`, `password`, `id_proof_path`, `rating`, `reg_date`, `status`, `reset_token`, `reset_token_expiry`) VALUES
(1, 'Rahul Sharma', 'rahul@example.com', '9876543210', 'Flat 402, Sunshine Heights, MG Road, Bengaluru, Karnataka 560001', '$2y$10$zNr7lPJ42uHGfjnL7ml2n.L.p3meWFZsHyt.eV5kAh45EYl1UMNKq', 'uploads/id_proofs/seed_rahul_aadhaar.pdf', 4.80, NOW(), 'Active', NULL, NULL),
(2, 'Priya Patel', 'priya@example.com', '9812345678', 'Plot 18, Green Glen Layout, Outer Ring Road, Bellandur, Bengaluru 560103', '$2y$10$zNr7lPJ42uHGfjnL7ml2n.L.p3meWFZsHyt.eV5kAh45EYl1UMNKq', 'uploads/id_proofs/seed_priya_dl.pdf', 5.00, NOW(), 'Active', NULL, NULL);

-- Seed User Roles (Rahul is dual Owner & Renter; Priya is Renter)
INSERT INTO `USER_ROLES` (`user_id`, `role`, `assigned_date`) VALUES
(1, 'Owner', NOW()),
(1, 'Renter', NOW()),
(2, 'Renter', NOW());
