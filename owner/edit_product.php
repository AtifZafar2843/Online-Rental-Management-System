<?php
/**
 * Online Rental Management System (ORMS)
 * Edit Existing Product Listing (Owner Module)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../classes/Product.php';

require_role('Owner');

$pdo = Database::getInstance()->getConnection();
$ownerId = (int) current_user_id();

$productId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($productId <= 0) {
    set_flash('error', 'Invalid product ID specified.');
    header("Location: " . base_url('owner/dashboard.php'));
    exit;
}

$product = Product::findById($productId);
if (!$product || $product->getOwnerID() !== $ownerId) {
    set_flash('error', 'Access Denied: Product not found or you do not have permission to edit it.');
    header("Location: " . base_url('owner/dashboard.php'));
    exit;
}

// Fetch categories for dropdown
$catStmt = $pdo->query("SELECT category_id, category_name FROM `CATEGORY` ORDER BY category_name ASC");
$categories = $catStmt->fetchAll();

$errors = [];

// Handle Action: Set Primary Image
if (isset($_GET['set_primary'])) {
    $targetImageId = (int) $_GET['set_primary'];
    $imgStmt = $pdo->prepare("SELECT image_id FROM `PRODUCT_IMAGES` WHERE image_id = :img_id AND product_id = :pid");
    $imgStmt->execute(['img_id' => $targetImageId, 'pid' => $productId]);

    if ($imgStmt->fetch()) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE `PRODUCT_IMAGES` SET is_primary = 0 WHERE product_id = :pid")->execute(['pid' => $productId]);
        $pdo->prepare("UPDATE `PRODUCT_IMAGES` SET is_primary = 1 WHERE image_id = :img_id")->execute(['img_id' => $targetImageId]);
        $pdo->commit();
        set_flash('success', 'Primary display photo updated successfully.');
    }
    header("Location: " . base_url("owner/edit_product.php?id={$productId}"));
    exit;
}

// Handle Action: Delete Single Image
if (isset($_GET['delete_image'])) {
    $targetImageId = (int) $_GET['delete_image'];
    $imgCountStmt = $pdo->prepare("SELECT COUNT(*) FROM `PRODUCT_IMAGES` WHERE product_id = :pid");
    $imgCountStmt->execute(['pid' => $productId]);
    $totalImgs = (int) $imgCountStmt->fetchColumn();

    if ($totalImgs <= 1) {
        set_flash('error', 'Cannot delete this photo. Every listing must retain at least one image.');
    } else {
        $fetchImg = $pdo->prepare("SELECT image_path, is_primary FROM `PRODUCT_IMAGES` WHERE image_id = :img_id AND product_id = :pid");
        $fetchImg->execute(['img_id' => $targetImageId, 'pid' => $productId]);
        $imgRow = $fetchImg->fetch();

        if ($imgRow) {
            $pdo->beginTransaction();
            $delStmt = $pdo->prepare("DELETE FROM `PRODUCT_IMAGES` WHERE image_id = :img_id");
            $delStmt->execute(['img_id' => $targetImageId]);

            // If we deleted the primary image, promote another image to primary
            if ($imgRow['is_primary']) {
                $promoteStmt = $pdo->prepare("UPDATE `PRODUCT_IMAGES` SET is_primary = 1 WHERE product_id = :pid LIMIT 1");
                $promoteStmt->execute(['pid' => $productId]);
            }
            $pdo->commit();

            // Safely delete file from disk
            $filePath = __DIR__ . '/../' . $imgRow['image_path'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }
            set_flash('success', 'Photo removed successfully from listing.');
        }
    }
    header("Location: " . base_url("owner/edit_product.php?id={$productId}"));
    exit;
}

// Handle POST: Update Details & Upload Extra Images
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please try again.';
    }

    $title = trim($_POST['title'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $rentPerDayInput = trim($_POST['rent_per_day'] ?? '');
    $securityDepositInput = trim($_POST['security_deposit'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $condition = trim($_POST['condition'] ?? 'Good');
    $availStatus = trim($_POST['avail_status'] ?? 'Available');

    if (empty($title)) {
        $errors[] = 'Product title is required.';
    }
    if ($categoryId <= 0) {
        $errors[] = 'Please select a valid category.';
    }
    if (empty($description)) {
        $errors[] = 'Description is required.';
    }
    if ($rentPerDayInput === '' || !is_numeric($rentPerDayInput) || (float)$rentPerDayInput <= 0) {
        $errors[] = 'Rent per day must be a positive amount.';
    }
    if ($securityDepositInput === '' || !is_numeric($securityDepositInput) || (float)$securityDepositInput < 0) {
        $errors[] = 'Security deposit must be 0 or positive.';
    }
    if (empty($location)) {
        $errors[] = 'Location is required.';
    }

    $validStatuses = ['Available', 'Rented', 'Unavailable'];
    if (!in_array($availStatus, $validStatuses, true)) {
        $errors[] = 'Invalid availability status.';
    }

    // Process new image uploads if any
    $newUploadedPaths = [];
    if (!empty($_FILES['new_images']) && !empty($_FILES['new_images']['name'][0])) {
        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
        $uploadDir = __DIR__ . '/../uploads/products/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $count = count($_FILES['new_images']['name']);
        for ($i = 0; $i < $count; $i++) {
            $singleFile = [
                'name'     => $_FILES['new_images']['name'][$i],
                'type'     => $_FILES['new_images']['type'][$i],
                'tmp_name' => $_FILES['new_images']['tmp_name'][$i],
                'error'    => $_FILES['new_images']['error'][$i],
                'size'     => $_FILES['new_images']['size'][$i],
            ];

            if ($singleFile['error'] === UPLOAD_ERR_NO_FILE) continue;

            $validation = validate_file_upload($singleFile, $allowedMimes, 5242880);
            if (!$validation['valid']) {
                $errors[] = "New photo #" . ($i + 1) . " error: " . $validation['error'];
                break;
            }

            $randomName = 'prod_' . bin2hex(random_bytes(16)) . '.' . $validation['extension'];
            $targetPath = $uploadDir . $randomName;

            if (move_uploaded_file($singleFile['tmp_name'], $targetPath)) {
                $newUploadedPaths[] = 'uploads/products/' . $randomName;
            } else {
                $errors[] = "Failed to save new photo #" . ($i + 1);
                break;
            }
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $product->setTitle($title);
            $product->setCategoryID($categoryId);
            $product->setDescription($description);
            $product->setRentPerDay((float)$rentPerDayInput);
            $product->setSecurityDeposit((float)$securityDepositInput);
            $product->setLocation($location);
            $product->setCondition($condition);
            $product->setAvailStatus($availStatus);

            $product->save();

            // Insert new images
            foreach ($newUploadedPaths as $path) {
                $product->addImage($path, false);
            }

            $pdo->commit();

            set_flash('success', "Listing \"{$title}\" updated successfully!");
            header("Location: " . base_url('owner/dashboard.php'));
            exit;

        } catch (Throwable $e) {
            $pdo->rollBack();
            foreach ($newUploadedPaths as $path) {
                $fp = __DIR__ . '/../' . $path;
                if (file_exists($fp)) unlink($fp);
            }
            $errors[] = 'Failed to update product: ' . $e->getMessage();
        }
    }
}

$existingImages = $product->getImages();

$pageTitle = 'Edit Product — ' . htmlspecialchars($product->getTitle());
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumbs -->
    <div class="mb-6 flex items-center justify-between">
        <a href="<?= base_url('owner/dashboard.php') ?>" class="text-xs text-slate-500 hover:text-coral flex items-center space-x-1.5 transition">
            <i class="ri-arrow-left-line"></i>
            <span>Back to Owner Dashboard</span>
        </a>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-midnight/5 text-midnight">
            Product #<?= $product->getProductID() ?>
        </span>
    </div>

    <div class="bg-white border border-[#E9E7FF] rounded-3xl shadow-sm overflow-hidden">
        <!-- Banner Header -->
        <div class="bg-gradient-to-br from-midnight via-[#131b33] to-midnight p-6 sm:p-10 text-white border-b border-[#E9E7FF]">
            <div class="flex items-center space-x-2 text-coral text-xs font-semibold uppercase tracking-wider mb-2">
                <i class="ri-edit-circle-line text-sm"></i>
                <span>Listing Editor</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold tracking-tight">Edit Product Listing</h1>
            <p class="mt-1 text-xs sm:text-sm text-slate-300">
                Update specifications, manage gallery photos, or adjust rental rates and availability status.
            </p>
        </div>

        <div class="p-6 sm:p-10">
            <!-- Errors Alert -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs space-y-1">
                    <div class="font-semibold flex items-center space-x-2 text-rose-800">
                        <i class="ri-error-warning-line text-base"></i>
                        <span>Please correct the following errors:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 pl-1 text-rose-700">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Existing Photos Gallery Section -->
            <div class="mb-8 pb-8 border-b border-[#E9E7FF]">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-semibold text-midnight uppercase tracking-wider flex items-center space-x-2">
                        <i class="ri-image-2-line text-coral text-base"></i>
                        <span>Current Photos (<?= count($existingImages) ?>)</span>
                    </h3>
                    <span class="text-xs text-slate-400">Select star to designate primary photo</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <?php foreach ($existingImages as $img): ?>
                        <div class="relative group rounded-2xl overflow-hidden border <?= $img['is_primary'] ? 'border-coral ring-2 ring-coral/20' : 'border-[#E9E7FF]' ?> bg-[#FAF8F5]">
                            <img src="<?= base_url($img['image_path']) ?>" alt="Product image" class="w-full h-32 object-cover">
                            
                            <?php if ($img['is_primary']): ?>
                                <span class="absolute top-2 left-2 px-2.5 py-1 bg-coral text-[10px] font-semibold text-white rounded-full shadow-sm flex items-center space-x-1">
                                    <i class="ri-star-fill text-[10px]"></i>
                                    <span>Primary</span>
                                </span>
                            <?php else: ?>
                                <a href="<?= base_url("owner/edit_product.php?id={$productId}&set_primary={$img['image_id']}") ?>" 
                                   class="absolute top-2 left-2 px-2.5 py-1 bg-white/90 hover:bg-coral hover:text-white text-[10px] font-semibold text-midnight rounded-full shadow-sm transition flex items-center space-x-1">
                                    <i class="ri-star-line text-[10px]"></i>
                                    <span>Make Primary</span>
                                </a>
                            <?php endif; ?>

                            <?php if (count($existingImages) > 1): ?>
                                <a href="<?= base_url("owner/edit_product.php?id={$productId}&delete_image={$img['image_id']}") ?>" 
                                   onclick="return confirm('Remove this photo from the listing?');"
                                   title="Delete photo"
                                   class="absolute top-2 right-2 w-7 h-7 rounded-full bg-white/90 hover:bg-rose-500 hover:text-white text-slate-600 text-xs flex items-center justify-center shadow-sm transition">
                                    <i class="ri-delete-bin-line"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Edit Form -->
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?id=<?= $productId ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $productId ?>">

                <!-- Title & Category -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="sm:col-span-2">
                        <label for="title" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Product Title <span class="text-coral">*</span>
                        </label>
                        <input type="text" id="title" name="title" required
                               value="<?= htmlspecialchars($product->getTitle()) ?>"
                               class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                    </div>

                    <div>
                        <label for="category_id" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Category <span class="text-coral">*</span>
                        </label>
                        <select id="category_id" name="category_id" required
                                class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($product->getCategoryID() === (int)$cat['category_id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($cat['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <!-- Description -->
                <div>
                    <label for="description" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                        Detailed Description <span class="text-coral">*</span>
                    </label>
                    <textarea id="description" name="description" rows="4" required
                              class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition"><?= htmlspecialchars($product->getDescription()) ?></textarea>
                </div>

                <!-- Financial Rates, Location & Availability -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label for="rent_per_day" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Daily Rent (₹) <span class="text-coral">*</span>
                        </label>
                        <input type="number" step="0.01" min="1" id="rent_per_day" name="rent_per_day" required
                               value="<?= htmlspecialchars((string)$product->getRentPerDay()) ?>"
                               class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                    </div>

                    <div>
                        <label for="security_deposit" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Deposit (₹) <span class="text-coral">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" id="security_deposit" name="security_deposit" required
                               value="<?= htmlspecialchars((string)$product->getSecurityDeposit()) ?>"
                               class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                    </div>

                    <div>
                        <label for="location" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Location <span class="text-coral">*</span>
                        </label>
                        <input type="text" id="location" name="location" required
                               value="<?= htmlspecialchars($product->getLocation()) ?>"
                               class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                    </div>

                    <div>
                        <label for="avail_status" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Status <span class="text-coral">*</span>
                        </label>
                        <select id="avail_status" name="avail_status" required
                                class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                            <option value="Available" <?= $product->getAvailStatus() === 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Rented" <?= $product->getAvailStatus() === 'Rented' ? 'selected' : '' ?>>Rented</option>
                            <option value="Unavailable" <?= $product->getAvailStatus() === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                        </select>
                    </div>
                </div>

                <!-- Condition -->
                <div>
                    <label class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                        Physical Condition <span class="text-coral">*</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php foreach (['New', 'Good', 'Fair', 'Poor'] as $c): ?>
                            <label class="flex items-center space-x-2.5 p-3 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral/40 cursor-pointer transition">
                                <input type="radio" name="condition" value="<?= $c ?>" 
                                       <?= ($product->getCondition() === $c) ? 'checked' : '' ?>
                                       class="text-coral focus:ring-coral">
                                <span class="text-xs font-medium text-midnight"><?= $c ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Upload Additional Photos -->
                <div class="pt-4 border-t border-[#E9E7FF]">
                    <label for="new_images" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                        Add More Photos (Optional)
                    </label>
                    <input type="file" id="new_images" name="new_images[]" multiple accept=".jpg,.jpeg,.png,.webp"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-[#E9E7FF] file:text-midnight hover:file:bg-[#d8d5ff] file:cursor-pointer bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl p-2 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        New photos will be appended to the current gallery with magic-byte verification. Max 5MB per file.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="pt-6 border-t border-[#E9E7FF] flex items-center justify-end space-x-3">
                    <a href="<?= base_url('owner/dashboard.php') ?>" class="px-5 py-2.5 rounded-full border border-slate-200 text-xs font-semibold text-slate-600 hover:text-midnight hover:bg-slate-50 transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-7 py-3 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs rounded-full shadow-glow-coral transition flex items-center space-x-1.5">
                        <i class="ri-check-line"></i>
                        <span>Save Changes</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

