<?php
/**
 * Online Rental Management System (ORMS)
 * Add New Product Listing (Owner Module)
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 4) & Section 6 (Step 4)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../classes/Product.php';

require_role('Owner');

$pdo = Database::getInstance()->getConnection();
$ownerId = (int) current_user_id();

// Fetch active categories for dropdown
$catStmt = $pdo->query("SELECT category_id, category_name FROM `CATEGORY` ORDER BY category_name ASC");
$categories = $catStmt->fetchAll();

$errors = [];
$title = '';
$categoryId = 0;
$description = '';
$rentPerDay = '';
$securityDeposit = '';
$location = '';
$condition = 'Good';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token verification failed. Please try submitting again.';
    }

    // 2. Retrieve and sanitize form inputs
    $title = trim($_POST['title'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $rentPerDayInput = trim($_POST['rent_per_day'] ?? '');
    $securityDepositInput = trim($_POST['security_deposit'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $condition = trim($_POST['condition'] ?? 'Good');

    // 3. Validation
    if (empty($title)) {
        $errors[] = 'Product title is required.';
    } elseif (strlen($title) > 255) {
        $errors[] = 'Product title must not exceed 255 characters.';
    }

    if ($categoryId <= 0) {
        $errors[] = 'Please select a valid category.';
    }

    if (empty($description)) {
        $errors[] = 'Product description is required.';
    }

    if ($rentPerDayInput === '' || !is_numeric($rentPerDayInput) || (float) $rentPerDayInput <= 0) {
        $errors[] = 'Rent per day must be a valid amount greater than 0.';
    }

    if ($securityDepositInput === '' || !is_numeric($securityDepositInput) || (float) $securityDepositInput < 0) {
        $errors[] = 'Security deposit must be a valid amount (0 or more).';
    }

    if (empty($location)) {
        $errors[] = 'Item location/city is required for local pickup/logistics.';
    }

    $validConditions = ['New', 'Good', 'Fair', 'Poor'];
    if (!in_array($condition, $validConditions, true)) {
        $errors[] = 'Please select a valid product condition.';
    }

    // 4. Multi-Image Upload & Magic-Byte Validation
    $uploadedImagePaths = [];
    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
    $maxBytes = 5242880; // 5MB

    if (empty($_FILES['images']) || empty($_FILES['images']['name'][0])) {
        $errors[] = 'At least one product image is required.';
    } else {
        $fileCount = count($_FILES['images']['name']);
        $uploadDir = __DIR__ . '/../uploads/products/';

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        for ($i = 0; $i < $fileCount; $i++) {
            $singleFile = [
                'name'     => $_FILES['images']['name'][$i],
                'type'     => $_FILES['images']['type'][$i],
                'tmp_name' => $_FILES['images']['tmp_name'][$i],
                'error'    => $_FILES['images']['error'][$i],
                'size'     => $_FILES['images']['size'][$i],
            ];

            // Skip if empty slot
            if ($singleFile['error'] === UPLOAD_ERR_NO_FILE) {
                continue;
            }

            $validation = validate_file_upload($singleFile, $allowedMimes, $maxBytes);
            if (!$validation['valid']) {
                $errors[] = "Image #" . ($i + 1) . " error: " . $validation['error'];
                break;
            }

            // Generate cryptographically randomized filename (Section 5 Rule 4)
            $ext = $validation['extension'];
            $randomName = 'prod_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $targetPath = $uploadDir . $randomName;

            if (move_uploaded_file($singleFile['tmp_name'], $targetPath)) {
                $uploadedImagePaths[] = 'uploads/products/' . $randomName;
            } else {
                $errors[] = "Failed to save uploaded image #" . ($i + 1) . ".";
                break;
            }
        }
    }

    // 5. Database Insertion
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $rent = (float) $rentPerDayInput;
            $deposit = (float) $securityDepositInput;

            $product = new Product(
                null,
                $ownerId,
                $categoryId,
                $title,
                $description,
                $rent,
                $deposit,
                $location,
                'Available',
                $condition
            );

            if ($product->save()) {
                $newProductId = $product->getProductID();

                foreach ($uploadedImagePaths as $idx => $path) {
                    $isPrimary = ($idx === 0);
                    $product->addImage($path, $isPrimary);
                }

                $pdo->commit();

                set_flash('success', "Product \"{$title}\" was listed successfully with " . count($uploadedImagePaths) . " images!");
                header("Location: " . base_url('owner/dashboard.php'));
                exit;
            } else {
                throw new ORMSException("Failed to persist product record.");
            }

        } catch (Throwable $e) {
            $pdo->rollBack();
            // Clean up any uploaded image files on database failure
            foreach ($uploadedImagePaths as $path) {
                $fullPath = __DIR__ . '/../' . $path;
                if (file_exists($fullPath)) {
                    unlink($fullPath);
                }
            }
            $errors[] = 'Failed to create product listing: ' . $e->getMessage();
        }
    } else {
        // Clean up any uploaded images if validation failed on subsequent files
        foreach ($uploadedImagePaths as $path) {
            $fullPath = __DIR__ . '/../' . $path;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
        }
    }
}

$pageTitle = 'List New Product — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb & Back Link -->
    <div class="mb-6 flex items-center justify-between">
        <a href="<?= base_url('owner/dashboard.php') ?>" class="text-xs text-slate-500 hover:text-coral flex items-center space-x-1.5 transition">
            <i class="ri-arrow-left-line"></i>
            <span>Back to Owner Dashboard</span>
        </a>
        <span class="text-xs text-slate-400 font-medium">Inventory Management</span>
    </div>

    <div class="bg-white border border-[#E9E7FF] rounded-3xl shadow-sm overflow-hidden">
        <!-- Banner Header -->
        <div class="bg-gradient-to-br from-midnight via-[#131b33] to-midnight p-6 sm:p-10 text-white border-b border-[#E9E7FF]">
            <h1 class="text-2xl sm:text-3xl font-display font-bold tracking-tight">List a Product for Rent</h1>
            <p class="mt-1 text-xs sm:text-sm text-slate-300">
                Provide comprehensive specifications, pricing, security deposit, and genuine photos of your rental item.
            </p>
        </div>

        <div class="p-6 sm:p-10">
            <!-- Validation Errors Alert -->
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

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                <?= csrf_field() ?>

                <!-- Title & Category Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div class="sm:col-span-2">
                        <label for="title" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Product Title <span class="text-coral">*</span>
                        </label>
                        <input type="text" id="title" name="title" required
                               value="<?= htmlspecialchars($title) ?>"
                               placeholder="e.g. Sony Alpha A7 III Mirrorless Camera with 28-70mm Lens"
                               class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                    </div>

                    <div>
                        <label for="category_id" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Category <span class="text-coral">*</span>
                        </label>
                        <select id="category_id" name="category_id" required
                                class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                            <option value="">-- Choose Category --</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= $cat['category_id'] ?>" <?= ($categoryId === (int)$cat['category_id']) ? 'selected' : '' ?>>
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
                              placeholder="Describe the product specs, what is included in the package, usage guidelines, and any terms..."
                              class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition"><?= htmlspecialchars($description) ?></textarea>
                </div>

                <!-- Financial Rates & Location -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                    <div>
                        <label for="rent_per_day" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Daily Rent (₹/day) <span class="text-coral">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 font-bold text-sm">₹</span>
                            <input type="number" step="0.01" min="1" id="rent_per_day" name="rent_per_day" required
                                   value="<?= htmlspecialchars((string)$rentPerDayInput) ?>"
                                   placeholder="500.00"
                                   class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl pl-8 pr-4 py-3 text-sm text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Rate charged per calendar day.</p>
                    </div>

                    <div>
                        <label for="security_deposit" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Security Deposit (₹) <span class="text-coral">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-400 font-bold text-sm">₹</span>
                            <input type="number" step="0.01" min="0" id="security_deposit" name="security_deposit" required
                                   value="<?= htmlspecialchars((string)$securityDepositInput) ?>"
                                   placeholder="2000.00"
                                   class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl pl-8 pr-4 py-3 text-sm text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                        </div>
                        <p class="text-[10px] text-slate-400 mt-1">Refundable upon undamaged return.</p>
                    </div>

                    <div>
                        <label for="location" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Location / Area <span class="text-coral">*</span>
                        </label>
                        <input type="text" id="location" name="location" required
                               value="<?= htmlspecialchars($location) ?>"
                               placeholder="e.g. Indiranagar, Bangalore"
                               class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                        <p class="text-[10px] text-slate-400 mt-1">Neighborhood / City for pickup.</p>
                    </div>
                </div>

                <!-- Condition -->
                <div>
                    <label class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                        Physical Condition <span class="text-coral">*</span>
                    </label>
                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                        <?php foreach (['New', 'Good', 'Fair', 'Poor'] as $c): ?>
                            <label class="flex items-center space-x-2.5 p-3.5 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral cursor-pointer transition">
                                <input type="radio" name="condition" value="<?= $c ?>" 
                                       <?= ($condition === $c) ? 'checked' : '' ?>
                                       class="text-coral focus:ring-coral">
                                <span class="text-xs font-semibold text-midnight"><?= $c ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Multi-Image Upload -->
                <div>
                    <label for="images" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                        Product Images (Select 1 or more) <span class="text-coral">*</span>
                    </label>
                    <input type="file" id="images" name="images[]" multiple required accept=".jpg,.jpeg,.png,.webp"
                           class="w-full text-xs text-slate-500 file:mr-4 file:py-2.5 file:px-5 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-coral file:text-white hover:file:bg-[#e04e53] file:cursor-pointer bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl p-2.5 focus:outline-none">
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        Allowed: JPEG, PNG, WEBP (Max 5MB per file). First uploaded image will automatically be set as the <strong>Primary Display Image</strong>.
                    </p>
                </div>

                <!-- Submit Button -->
                <div class="pt-4 border-t border-[#E9E7FF] flex items-center justify-end space-x-3">
                    <a href="<?= base_url('owner/dashboard.php') ?>" class="px-5 py-2.5 rounded-full bg-slate-100 text-xs font-semibold text-slate-600 hover:text-midnight transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            class="px-7 py-3 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition">
                        Publish Product Listing
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
