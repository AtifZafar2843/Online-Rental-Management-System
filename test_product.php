<?php
/**
 * Online Rental Management System (ORMS)
 * Product Listing Module Automated Verification Script (Step 4 Verification)
 * 
 * Accessible via CLI: php test_product.php
 * Accessible via Browser: http://localhost/orms/test_product.php
 */

declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/BaseUser.php';
require_once __DIR__ . '/classes/Owner.php';
require_once __DIR__ . '/classes/Product.php';

$results = [];
$allPassed = true;

function recordTest(string $title, bool $status, string $detail): void {
    global $results, $allPassed;
    if (!$status) {
        $allPassed = false;
    }
    $results[] = [
        'title'  => $title,
        'status' => $status,
        'detail' => $detail
    ];
}

$pdo = Database::getInstance()->getConnection();
$createdProductId = null;
$createdImagePaths = [];

try {
    // -------------------------------------------------------------
    // Test 1: Model Validation (Check Constraints Enforcement)
    // -------------------------------------------------------------
    $invalidRentCaught = false;
    try {
        $badProduct = new Product(null, 1, 1, 'Bad Rent', 'Desc', -50.0, 500.0, 'Bangalore');
        $badProduct->save();
    } catch (ORMSException $e) {
        $invalidRentCaught = true;
    }

    recordTest(
        'Product Model Check Constraint Validation',
        $invalidRentCaught,
        $invalidRentCaught ? 'PASS: Negative rent_per_day was rejected with ORMSException.' : 'FAIL: Negative rent was accepted.'
    );

    // -------------------------------------------------------------
    // Test 2: Multi-Image Magic-Byte Validation (2 Sample Images)
    // -------------------------------------------------------------
    $uploadDir = __DIR__ . '/uploads/products/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Image 1: Valid JPEG binary with JFIF header
    $img1Name = 'prod_test_' . bin2hex(random_bytes(8)) . '.jpg';
    $img1Path = $uploadDir . $img1Name;
    file_put_contents($img1Path, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00" . str_repeat("\xAA", 128));

    // Image 2: Valid PNG binary with PNG header
    $img2Name = 'prod_test_' . bin2hex(random_bytes(8)) . '.png';
    $img2Path = $uploadDir . $img2Name;
    file_put_contents($img2Path, "\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89" . str_repeat("\xBB", 128));

    $allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];

    $checkImg1 = validate_file_upload([
        'tmp_name' => $img1Path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($img1Path)
    ], $allowedMimes);

    $checkImg2 = validate_file_upload([
        'tmp_name' => $img2Path,
        'error'    => UPLOAD_ERR_OK,
        'size'     => filesize($img2Path)
    ], $allowedMimes);

    $imagesValidated = ($checkImg1['valid'] && $checkImg2['valid']);
    recordTest(
        'Multi-Image Magic-Byte Verification',
        $imagesValidated,
        $imagesValidated ? "PASS: Image 1 detected as {$checkImg1['mime']}, Image 2 detected as {$checkImg2['mime']}." : "FAIL: Image validation failed."
    );

    // -------------------------------------------------------------
    // Test 3: Product Creation with 2 Images (DB & File Storage)
    // -------------------------------------------------------------
    $owner = new Owner(1, 'Rahul Sharma', 'rahul@example.com');
    $relativeImg1 = 'uploads/products/' . $img1Name;
    $relativeImg2 = 'uploads/products/' . $img2Name;

    $product = $owner->addProduct(
        1, // Electronics category
        'Canon EOS R5 Mirrorless Camera Kit',
        'Professional 45MP full-frame mirrorless camera with 8K video, RF 24-70mm f/2.8L lens, 2 batteries and 128GB CFexpress card.',
        1200.00,
        15000.00,
        'Indiranagar, Bangalore',
        'New',
        [$relativeImg1, $relativeImg2]
    );

    $createdProductId = $product ? $product->getProductID() : null;
    $createdImagePaths = [$img1Path, $img2Path];

    $productCreated = ($product !== null && $createdProductId > 0);
    recordTest(
        'Product Creation & DB Insertion (PRODUCT Table)',
        $productCreated,
        $productCreated ? "PASS: Product inserted successfully with Product ID #{$createdProductId}." : "FAIL: Could not insert product."
    );

    // -------------------------------------------------------------
    // Test 4: PRODUCT_IMAGES Table Rows & Primary Flag
    // -------------------------------------------------------------
    $imgStmt = $pdo->prepare("SELECT image_id, image_path, is_primary FROM `PRODUCT_IMAGES` WHERE product_id = :pid ORDER BY is_primary DESC");
    $imgStmt->execute(['pid' => $createdProductId]);
    $dbImages = $imgStmt->fetchAll();

    $has2ImagesInDb = (count($dbImages) === 2);
    $primaryCorrect = ($has2ImagesInDb && (int)$dbImages[0]['is_primary'] === 1 && (int)$dbImages[1]['is_primary'] === 0);
    $filesExistOnDisk = (file_exists($img1Path) && file_exists($img2Path));

    $step4CoreSuccess = ($has2ImagesInDb && $primaryCorrect && $filesExistOnDisk);
    recordTest(
        'PRODUCT_IMAGES DB Rows & Files On Disk Check',
        $step4CoreSuccess,
        $step4CoreSuccess ? "PASS: Exactly 2 images recorded in DB. Primary image flag verified. Both physical files confirmed on disk." : "FAIL: DB images or physical files mismatch."
    );

    // -------------------------------------------------------------
    // Test 5: Product::getImages() Method Check
    // -------------------------------------------------------------
    $fetchedImages = $product->getImages();
    $methodOk = (count($fetchedImages) === 2 && $product->getPrimaryImagePath() === $relativeImg1);

    recordTest(
        'Product::getImages() & Primary Image Retrieval',
        $methodOk,
        $methodOk ? "PASS: getImages() returned 2 gallery records with primary image correctly identified." : "FAIL: getImages() output mismatch."
    );

    // -------------------------------------------------------------
    // Test 6: Product Edit / Update Functionality
    // -------------------------------------------------------------
    $newTitle = 'Canon EOS R5 Mirrorless Camera Kit [Updated]';
    $newRent = 1350.00;
    $editOk = $owner->editProduct(
        $createdProductId,
        1,
        $newTitle,
        'Updated description with extra memory card and battery grip.',
        $newRent,
        15000.00,
        'Koramangala, Bangalore',
        'Good',
        'Available'
    );

    $verifyUpdated = Product::findById($createdProductId);
    $updateConfirmed = ($editOk && $verifyUpdated && $verifyUpdated->getTitle() === $newTitle && $verifyUpdated->getRentPerDay() === $newRent);

    recordTest(
        'Owner::editProduct() Update Verification',
        $updateConfirmed,
        $updateConfirmed ? "PASS: Product updated. Title: \"{$verifyUpdated->getTitle()}\", Rent: ₹{$verifyUpdated->getRentPerDay()}/day." : "FAIL: Product update failed."
    );

    // -------------------------------------------------------------
    // Test 7: Product Availability Logic
    // -------------------------------------------------------------
    $isAvailBefore = $verifyUpdated->checkAvailability('2026-10-01', '2026-10-05');
    $verifyUpdated->updateStatus('Unavailable');
    $isAvailAfter = $verifyUpdated->checkAvailability('2026-10-01', '2026-10-05');

    $availLogicOk = ($isAvailBefore === true && $isAvailAfter === false);
    recordTest(
        'checkAvailability() & updateStatus() Logic',
        $availLogicOk,
        $availLogicOk ? "PASS: Available returned true when status=Available; returned false when status=Unavailable." : "FAIL: Availability check logic error."
    );

    // -------------------------------------------------------------
    // Test 8: Product::findByOwner() Query Test
    // -------------------------------------------------------------
    $ownerListings = Product::findByOwner(1);
    $foundInOwnerListings = false;
    foreach ($ownerListings as $ol) {
        if ($ol->getProductID() === $createdProductId) {
            $foundInOwnerListings = true;
            break;
        }
    }

    recordTest(
        'Product::findByOwner() Inventory Query',
        $foundInOwnerListings,
        $foundInOwnerListings ? "PASS: Newly listed product confirmed in owner's active inventory." : "FAIL: Product not found in owner inventory."
    );

} catch (Throwable $e) {
    recordTest('Product Test Suite Exception', false, 'CRITICAL ERROR: ' . $e->getMessage());
} finally {
    // Clean up test product and test files
    if ($createdProductId) {
        $pdo->prepare("DELETE FROM `PRODUCT_IMAGES` WHERE product_id = :pid")->execute(['pid' => $createdProductId]);
        $pdo->prepare("DELETE FROM `PRODUCT` WHERE product_id = :pid")->execute(['pid' => $createdProductId]);
    }
    foreach ($createdImagePaths as $fp) {
        if (file_exists($fp)) {
            unlink($fp);
        }
    }
}

// Render Results
if ($isCli) {
    echo "\n=======================================================\n";
    echo "  ORMS Product Module Automated Test (Step 4 Verification)\n";
    echo "=======================================================\n\n";
    foreach ($results as $i => $res) {
        $badge = $res['status'] ? "[ PASS ]" : "[ FAIL ]";
        echo ($i + 1) . ". {$badge} {$res['title']}\n";
        echo "   -> {$res['detail']}\n\n";
    }
    echo "-------------------------------------------------------\n";
    echo $allPassed ? "OVERALL RESULT: ALL TESTS PASSED! Product listing module is 100% operational.\n" : "OVERALL RESULT: SOME TESTS FAILED.\n";
    echo "=======================================================\n";
    exit($allPassed ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORMS — Product Module Test Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto bg-slate-900 rounded-2xl shadow-2xl border border-slate-800 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 p-6">
            <h1 class="text-2xl font-bold tracking-wide">Online Rental Management System (ORMS)</h1>
            <p class="text-blue-200 text-sm mt-1">Step 4 Verification: Product Listing Module (Owner)</p>
        </div>

        <div class="p-6">
            <div class="mb-6 flex items-center justify-between p-4 rounded-xl <?= $allPassed ? 'bg-emerald-950/70 border border-emerald-500/50 text-emerald-300' : 'bg-rose-950/70 border border-rose-500/50 text-rose-300' ?>">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl"><?= $allPassed ? '✅' : '❌' ?></span>
                    <div>
                        <h2 class="font-semibold text-lg"><?= $allPassed ? 'All Product Listing Tests Passed' : 'Some Tests Failed' ?></h2>
                        <p class="text-xs opacity-85"><?= $allPassed ? 'Add product, multi-image upload, DB rows, files saved, and edit features verified.' : 'Review error messages below.' ?></p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $allPassed ? 'bg-emerald-500 text-black' : 'bg-rose-500 text-white' ?>">
                    <?= $allPassed ? 'Ready for Step 5' : 'Action Required' ?>
                </span>
            </div>

            <div class="space-y-4">
                <?php foreach ($results as $index => $res): ?>
                    <div class="border border-slate-800 bg-slate-950/60 p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-200 text-sm"><?= ($index + 1) . '. ' . htmlspecialchars($res['title']) ?></span>
                            <span class="px-2.5 py-0.5 rounded text-xs font-semibold <?= $res['status'] ? 'bg-emerald-900/60 text-emerald-400 border border-emerald-700/50' : 'bg-rose-900/60 text-rose-400 border border-rose-700/50' ?>">
                                <?= $res['status'] ? 'PASS' : 'FAIL' ?>
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-slate-400 font-mono bg-slate-950 p-2.5 rounded-lg border border-slate-800 break-all">
                            <?= htmlspecialchars($res['detail']) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-800 flex justify-between items-center text-xs text-slate-400">
                <a href="<?= base_url('owner/dashboard.php') ?>" class="text-blue-400 hover:text-blue-300 font-semibold">&larr; Go to Owner Dashboard</a>
                <span>ORMS &bull; BCSP-064 &bull; Step 4 Complete</span>
            </div>
        </div>
    </div>
</body>
</html>
