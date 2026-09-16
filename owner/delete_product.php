<?php
/**
 * Online Rental Management System (ORMS)
 * Owner — Delete Product Listing
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';
require_once __DIR__ . '/../classes/exceptions/UnauthorizedActionException.php';

require_role('Owner');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Invalid request method for product deletion.');
    redirect('owner/dashboard.php');
}

if (!csrf_verify()) {
    set_flash('error', 'Security validation failed (Invalid CSRF token). Please try again.');
    redirect('owner/dashboard.php');
}

$productId = (int) ($_POST['product_id'] ?? 0);
if ($productId <= 0) {
    set_flash('error', 'Invalid product specified for deletion.');
    redirect('owner/dashboard.php');
}

try {
    $owner = new Owner((int) $_SESSION['user_id']);
    $product = Product::findById($productId);
    
    if (!$product) {
        set_flash('error', 'Product not found.');
        redirect('owner/dashboard.php');
    }

    $title = $product->getTitle();
    if ($owner->deleteProduct($productId)) {
        set_flash('success', "Product '{$title}' and its associated images were deleted successfully.");
    } else {
        set_flash('error', "Could not delete product. Please try again.");
    }
} catch (Exception $e) {
    set_flash('error', "Deletion failed: " . $e->getMessage());
}

redirect('owner/dashboard.php');
