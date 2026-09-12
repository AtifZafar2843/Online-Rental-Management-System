<?php
/**
 * Online Rental Management System (ORMS)
 * Edit Existing Product Listing (Owner Module)
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 4)
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
        set_flash('success', 'Primary display image updated successfully.');
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
        set_flash('error', 'Cannot delete this image. Every product must retain at least one photo.');
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
            set_flash('success', 'Image was removed successfully.');
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
                $errors[] = "New image #" . ($i + 1) . " error: " . $validation['error'];
                break;
            }

            $randomName = 'prod_' . bin2hex(random_bytes(16)) . '.' . $validation['extension'];
            $targetPath = $uploadDir . $randomName;

            if (move_uploaded_file($singleFile['tmp_name'], $targetPath)) {
                $newUploadedPaths[] = 'uploads/products/' . $randomName;
            } else {
                $errors[] = "Failed to save new image #" . ($i + 1);
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

            set_flash('success', "Product \"{$title}\" has been updated successfully!");
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

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <div class="mb-6 flex items-center justify-between">
        <a href="<?= base_url('owner/dashboard.php') ?>" class="text-xs text-slate-400 hover:text-white flex items-center space-x-1 transition">
            <span>&larr;</span>
            <span>Back to Owner Dashboard</span>
        </a>
        <span class="text-xs text-blue-400 font-medium">Product ID: #<?= $product->getProductID() ?></span>
    </div>

    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <!-- Banner Header -->
        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 p-8 border-b border-slate-800">
            <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Edit Product Listing</h1>
            <p class="mt-1 text-xs sm:text-sm text-blue-200">
                Update details, manage gallery photos, or adjust rental rates and availability.
            </p>
        </div>

        <div class="p-6 sm:p-8">
            <!-- Errors Alert -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-600/70 text-rose-200 text-xs space-y-1">
                    <div class="font-semibold flex items-center space-x-2 text-rose-300">
                        <span>⚠️</span>
                        <span>Please correct the following errors:</span>
                    </div>
                    <ul class="list-disc list-inside space-y-1 pl-1 text-rose-300/90">
                        <?php foreach ($errors as $err): ?>
                            <li><?= htmlspecialchars($err) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- Existing Gallery Section -->
            <div class="mb-8 pb-8 border-b border-slate-800">
                <h3 class="text-sm font-bold text-white uppercase tracking-wider mb-4 flex items-center space-x-2">
                    <span>🖼️</span>
                    <span>Current Photos (<?= count($existingImages) ?>)</span>
                </h3>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
                    <?php foreach ($existingImages as $img): ?>
                        <div class="relative group rounded-xl overflow-hidden border <?= $img['is_primary'] ? 'border-blue-500 ring-2 ring-blue-500/50' : 'border-slate-800' ?> bg-slate-950">
                            <img src="<?= base_url($img['image_path']) ?>" alt="Product image" class="w-full h-32 object-cover">
                            
                            <?php if ($img['is_primary']): ?>
                                <span class="absolute top-2 left-2 px-2 py-0.5 bg-blue-600 text-[10px] font-bold text-white rounded-md shadow">
                                    Primary
                                </span>
                            <?php else: ?>
                                <a href="<?= base_url("owner/edit_product.php?id={$productId}&set_primary={$img['image_id']}") ?>" 
                                   class="absolute top-2 left-2 px-2 py-0.5 bg-slate-900/80 hover:bg-blue-600 text-[10px] font-semibold text-white rounded-md transition shadow">
                                    Make Primary
                                </a>
                            <?php endif; ?>

                            <?php if (count($existingImages) > 1): ?>
                                <a href="<?= base_url("owner/edit_product.php?id={$productId}&delete_image={$img['image_id']}") ?>" 
                                   onclick="return confirm('Remove this image from listing?');"
                                   class="absolute top-2 right-2 w-6 h-6 rounded-full bg-rose-600 hover:bg-rose-500 text-white text-xs flex items-center justify-center transition shadow">
                                    &times;
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
                        <label for="title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Product Title <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="title" name="title" required
                               value="<?= htmlspecialchars($product->getTitle()) ?>"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>

                    <div>
                        <label for="category_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Category <span class="text-rose-500">*</span>
                        </label>
                        <select id="category_id" name="category_id" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
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
                    <label for="description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Detailed Description <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="description" name="description" rows="4" required
                              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"><?= htmlspecialchars($product->getDescription()) ?></textarea>
                </div>

                <!-- Financial Rates, Location & Availability -->
                <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                    <div>
                        <label for="rent_per_day" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Daily Rent (₹) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" step="0.01" min="1" id="rent_per_day" name="rent_per_day" required
                               value="<?= htmlspecialchars((string)$product->getRentPerDay()) ?>"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>

                    <div>
                        <label for="security_deposit" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Deposit (₹) <span class="text-rose-500">*</span>
                        </label>
                        <input type="number" step="0.01" min="0" id="security_deposit" name="security_deposit" required
                               value="<?= htmlspecialchars((string)$product->getSecurityDeposit()) ?>"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>

                    <div>
                        <label for="location" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Location <span class="text-rose-500">*</span>
                        </label>
                        <input type="text" id="location" name="location" required
                               value="<?= htmlspecialchars($product->getLocation()) ?>"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>

                    <div>
                        <label for="avail_status" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Status <span class="text-rose-500">*</span>
                        </label>
                        <select id="avail_status" name="avail_status" required
                                class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                            <option value="Available" <?= $product->getAvailStatus() === 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Rented" <?= $product->getAvailStatus() === 'Rented' ? 'selected' : '' ?>>Rented</option>
                            <option value="Unavailable" <?= $product->getAvailStatus() === 'Unavailable' ? 'selected' : '' ?>>Unavailable</option>
                        </select>
                    </div>
                </div>

                <!-- Condition -->
                <div>
                    <label class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Physical Condition <span class="text-rose-500">*</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php foreach (['New', 'Good', 'Fair', 'Poor'] as $c): ?>
                            <label class="flex items-center space-x-2.5 p-3 rounded-xl border border-slate-800 bg-slate-950/70 hover:border-slate-700 cursor-pointer transition">
                                <input type="radio" name="condition" value="<?= $c ?>" 
                                       <?= ($product->getCondition() === $c) ? 'checked' : '' ?>
                                       class="text-blue-600 focus:ring-blue-500 bg-slate-900 border-slate-700">
                                <span class="text-xs font-medium text-slate-200"><?= $c ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Upload Additional Photos -->
                <div class="pt-4 border-t border-slate-800">
                    <label for="new_images" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Add More Photos (Optional)
                    </label>
                    <input type="file" id="new_images" name="new_images[]" multiple accept=".jpg,.jpeg,.png,.webp"
                           class="w-full text-xs text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-slate-200 hover:file:bg-slate-700 file:cursor-pointer bg-slate-950 border border-slate-800 rounded-xl p-2 focus:outline-none">
                    <p class="text-[11px] text-slate-500 mt-1.5">
                        New photos will be appended to the current gallery with magic-byte verification.
                    </p>
                </div>

                <!-- Action Buttons -->
                <div class="pt-6 border-t border-slate-800 flex items-center justify-end space-x-3">
                    <a href="<?= base_url('owner/dashboard.php') ?>" class="px-5 py-2.5 rounded-xl border border-slate-700 text-xs font-semibold text-slate-300 hover:text-white hover:bg-slate-800 transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-7 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition">
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
