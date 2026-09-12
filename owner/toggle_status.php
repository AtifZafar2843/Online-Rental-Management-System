<?php
/**
 * Online Rental Management System (ORMS)
 * Toggle Product Availability Endpoint (Owner Module)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Product.php';

require_role('Owner');

$ownerId = (int) current_user_id();
$productId = (int) ($_GET['id'] ?? 0);

if ($productId > 0) {
    $product = Product::findById($productId);
    if ($product && $product->getOwnerID() === $ownerId) {
        $current = $product->getAvailStatus();
        $newStatus = ($current === 'Available') ? 'Unavailable' : 'Available';

        if ($current === 'Rented') {
            set_flash('error', 'Cannot change availability while product is actively rented.');
        } else {
            $product->updateStatus($newStatus);
            set_flash('success', "Status for \"{$product->getTitle()}\" changed to {$newStatus}.");
        }
    } else {
        set_flash('error', 'Product not found or access denied.');
    }
}

header("Location: " . base_url('owner/dashboard.php'));
exit;
