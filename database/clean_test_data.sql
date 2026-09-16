-- ==============================================================================
-- Online Rental Management System (ORMS) - Clean Test Data Script
-- Compatible with phpMyAdmin, MySQL CLI, and Workbench (InnoDB Safe)
-- ==============================================================================

USE `orms_db`;

-- Step 1: Temporarily disable foreign key checks
SET FOREIGN_KEY_CHECKS = 0;

-- Step 2: Safely delete activity data in child-to-parent order & reset Auto-Increments to 1
DELETE FROM `DISPUTE`;
ALTER TABLE `DISPUTE` AUTO_INCREMENT = 1;

DELETE FROM `REVIEW`;
ALTER TABLE `REVIEW` AUTO_INCREMENT = 1;

DELETE FROM `FINE`;
ALTER TABLE `FINE` AUTO_INCREMENT = 1;

DELETE FROM `TRANSACTION`;
ALTER TABLE `TRANSACTION` AUTO_INCREMENT = 1;

DELETE FROM `RENTAL_REQUEST`;
ALTER TABLE `RENTAL_REQUEST` AUTO_INCREMENT = 1;

DELETE FROM `NOTIFICATION`;
ALTER TABLE `NOTIFICATION` AUTO_INCREMENT = 1;

-- Step 3: Re-enable foreign key checks
SET FOREIGN_KEY_CHECKS = 1;

-- Step 4: Reset all 'Rented' products back to 'Available' so they can be booked fresh
UPDATE `PRODUCT` 
SET `avail_status` = 'Available' 
WHERE `avail_status` = 'Rented';
