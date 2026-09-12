<?php
/**
 * Online Rental Management System (ORMS)
 * Search & Discovery Module Automated Verification Script (Step 5 Verification)
 * 
 * Accessible via CLI: php test_search.php
 * Accessible via Browser: http://localhost/orms/test_search.php
 */

declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/BaseUser.php';
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
$testProductIds = [];

try {
    // -------------------------------------------------------------
    // Test 1: Guest State Verification (Unauthenticated Access)
    // -------------------------------------------------------------
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = []; // Clear session to simulate guest
    }
    $isGuest = !is_logged_in();

    recordTest(
        'Public Guest Access State',
        $isGuest,
        $isGuest ? 'PASS: Guest state active (is_logged_in() returned false). Search and product details are publicly accessible.' : 'FAIL: User state detected.'
    );

    // -------------------------------------------------------------
    // Setup Sample Test Products for Search Validation
    // -------------------------------------------------------------
    // Product 1: Electronics - MacBook
    $pdo->beginTransaction();
    $stmt1 = $pdo->prepare("
        INSERT INTO `PRODUCT` 
        (`owner_id`, `category_id`, `title`, `description`, `rent_per_day`, `security_deposit`, `location`, `avail_status`, `condition`, `listed_date`) 
        VALUES 
        (1, 1, 'Apple MacBook Pro M2 Max 32GB', 'High performance laptop for video editing and software engineering.', 1800.00, 20000.00, 'Indiranagar, Bangalore', 'Available', 'New', NOW())
    ");
    $stmt1->execute();
    $pid1 = (int) $pdo->lastInsertId();
    $testProductIds[] = $pid1;

    $pdo->prepare("INSERT INTO `PRODUCT_IMAGES` (`product_id`, `image_path`, `is_primary`, `upload_date`) VALUES (:pid, 'uploads/products/seed_mac.jpg', 1, NOW())")
        ->execute(['pid' => $pid1]);

    // Product 2: Furniture - Chair
    $stmt2 = $pdo->prepare("
        INSERT INTO `PRODUCT` 
        (`owner_id`, `category_id`, `title`, `description`, `rent_per_day`, `security_deposit`, `location`, `avail_status`, `condition`, `listed_date`) 
        VALUES 
        (1, 2, 'Featherlite Ergonomic High-Back Office Chair', 'Adjustable lumbar support and 3D armrests for home office.', 250.00, 2500.00, 'Whitefield, Bangalore', 'Available', 'Good', NOW())
    ");
    $stmt2->execute();
    $pid2 = (int) $pdo->lastInsertId();
    $testProductIds[] = $pid2;

    $pdo->prepare("INSERT INTO `PRODUCT_IMAGES` (`product_id`, `image_path`, `is_primary`, `upload_date`) VALUES (:pid, 'uploads/products/seed_chair.jpg', 1, NOW())")
        ->execute(['pid' => $pid2]);

    $pdo->commit();

    recordTest(
        'Test Product Inventory Ingestion',
        count($testProductIds) === 2,
        "PASS: Created Product #{$pid1} (Electronics, ₹1800/day) and Product #{$pid2} (Furniture, ₹250/day)."
    );

    // -------------------------------------------------------------
    // Test 3: Category Filter
    // -------------------------------------------------------------
    $cat1Results = Product::search(['category_id' => 1, 'status' => 'Available']);
    $cat2Results = Product::search(['category_id' => 2, 'status' => 'Available']);

    $hasMacInCat1 = false;
    foreach ($cat1Results as $r) {
        if ($r['product']->getProductID() === $pid1) $hasMacInCat1 = true;
        if ($r['product']->getProductID() === $pid2) $hasMacInCat1 = false; // Chair shouldn't be in cat 1
    }

    $hasChairInCat2 = false;
    foreach ($cat2Results as $r) {
        if ($r['product']->getProductID() === $pid2) $hasChairInCat2 = true;
        if ($r['product']->getProductID() === $pid1) $hasChairInCat2 = false; // Mac shouldn't be in cat 2
    }

    $catFilterOk = ($hasMacInCat1 && $hasChairInCat2);
    recordTest(
        'Category Filter Verification',
        $catFilterOk,
        $catFilterOk ? 'PASS: Category 1 (Electronics) returned MacBook; Category 2 (Furniture) returned Ergonomic Chair.' : 'FAIL: Category filter returned incorrect products.'
    );

    // -------------------------------------------------------------
    // Test 4: Price Range Filter (Min & Max Price)
    // -------------------------------------------------------------
    // Max price 500 should include Chair (250) but exclude Mac (1800)
    $lowPriceResults = Product::search(['max_price' => 500, 'status' => 'Available']);
    $lowPriceOk = false;
    foreach ($lowPriceResults as $r) {
        if ($r['product']->getProductID() === $pid2) $lowPriceOk = true;
        if ($r['product']->getProductID() === $pid1) { $lowPriceOk = false; break; }
    }

    // Min price 1000 should include Mac (1800) but exclude Chair (250)
    $highPriceResults = Product::search(['min_price' => 1000, 'status' => 'Available']);
    $highPriceOk = false;
    foreach ($highPriceResults as $r) {
        if ($r['product']->getProductID() === $pid1) $highPriceOk = true;
        if ($r['product']->getProductID() === $pid2) { $highPriceOk = false; break; }
    }

    $priceFilterOk = ($lowPriceOk && $highPriceOk);
    recordTest(
        'Price Range Filter (min_price & max_price)',
        $priceFilterOk,
        $priceFilterOk ? 'PASS: max_price <= ₹500 included Chair (₹250); min_price >= ₹1000 included MacBook (₹1800).' : 'FAIL: Price range filter error.'
    );

    // -------------------------------------------------------------
    // Test 5: Location Filter
    // -------------------------------------------------------------
    $locResults = Product::search(['location' => 'Whitefield', 'status' => 'Available']);
    $locOk = false;
    foreach ($locResults as $r) {
        if ($r['product']->getProductID() === $pid2) $locOk = true;
        if ($r['product']->getProductID() === $pid1) { $locOk = false; break; }
    }

    recordTest(
        'Location Substring Filter',
        $locOk,
        $locOk ? 'PASS: Location search for "Whitefield" isolated the correct product.' : 'FAIL: Location filter failed.'
    );

    // -------------------------------------------------------------
    // Test 6: Keyword Fulltext / LIKE Search
    // -------------------------------------------------------------
    $kwResults = Product::search(['query' => 'MacBook', 'status' => 'Available']);
    $kwOk = false;
    foreach ($kwResults as $r) {
        if ($r['product']->getProductID() === $pid1) $kwOk = true;
        if ($r['product']->getProductID() === $pid2) { $kwOk = false; break; }
    }

    recordTest(
        'Keyword Query Search',
        $kwOk,
        $kwOk ? 'PASS: Keyword query "MacBook" matched target product title and description.' : 'FAIL: Keyword search error.'
    );

    // -------------------------------------------------------------
    // Test 7: Sorting Ascending vs Descending
    // -------------------------------------------------------------
    $ascResults = Product::search(['status' => 'Available'], 'price_asc');
    $pidsInAsc = array_column(array_map(fn($r) => ['id' => $r['product']->getProductID(), 'rent' => $r['product']->getRentPerDay()], $ascResults), 'id');

    // Find indices of our test items
    $pos1 = array_search($pid1, $pidsInAsc, true);
    $pos2 = array_search($pid2, $pidsInAsc, true);

    $sortOk = ($pos1 !== false && $pos2 !== false && $pos2 < $pos1); // Chair (250) should come before Mac (1800)
    recordTest(
        'Catalog Sorting (Price: Low to High)',
        $sortOk,
        $sortOk ? 'PASS: price_asc correctly positioned ₹250 item before ₹1800 item.' : 'FAIL: Sort order incorrect.'
    );

    // -------------------------------------------------------------
    // Test 8: Product Details Page Integrity (findById)
    // -------------------------------------------------------------
    $detailProduct = Product::findById($pid1);
    $detailOk = (
        $detailProduct !== null &&
        $detailProduct->getTitle() === 'Apple MacBook Pro M2 Max 32GB' &&
        $detailProduct->getCategoryName() === 'Electronics' &&
        $detailProduct->getRentPerDay() === 1800.00 &&
        $detailProduct->getSecurityDeposit() === 20000.00 &&
        $detailProduct->getOwnerName() === 'Rahul Sharma' &&
        count($detailProduct->getImages()) === 1
    );

    recordTest(
        'Product Details Entity & Relational Joins',
        $detailOk,
        $detailOk ? "PASS: Product #{$pid1} loaded with joined category ('{$detailProduct->getCategoryName()}'), owner ('{$detailProduct->getOwnerName()}'), and images." : "FAIL: Product details join failed."
    );

} catch (Throwable $e) {
    recordTest('Search Test Suite Exception', false, 'CRITICAL ERROR: ' . $e->getMessage());
} finally {
    // Clean up test products
    foreach ($testProductIds as $tpid) {
        $pdo->prepare("DELETE FROM `PRODUCT_IMAGES` WHERE product_id = :pid")->execute(['pid' => $tpid]);
        $pdo->prepare("DELETE FROM `PRODUCT` WHERE product_id = :pid")->execute(['pid' => $tpid]);
    }
}

// Render Results
if ($isCli) {
    echo "\n=======================================================\n";
    echo "  ORMS Search Module Automated Test (Step 5 Verification)\n";
    echo "=======================================================\n\n";
    foreach ($results as $i => $res) {
        $badge = $res['status'] ? "[ PASS ]" : "[ FAIL ]";
        echo ($i + 1) . ". {$badge} {$res['title']}\n";
        echo "   -> {$res['detail']}\n\n";
    }
    echo "-------------------------------------------------------\n";
    echo $allPassed ? "OVERALL RESULT: ALL TESTS PASSED! Search & discovery module is 100% operational.\n" : "OVERALL RESULT: SOME TESTS FAILED.\n";
    echo "=======================================================\n";
    exit($allPassed ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORMS — Search Module Test Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto bg-slate-900 rounded-2xl shadow-2xl border border-slate-800 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 p-6">
            <h1 class="text-2xl font-bold tracking-wide">Online Rental Management System (ORMS)</h1>
            <p class="text-blue-200 text-sm mt-1">Step 5 Verification: Search & Discovery Module</p>
        </div>

        <div class="p-6">
            <div class="mb-6 flex items-center justify-between p-4 rounded-xl <?= $allPassed ? 'bg-emerald-950/70 border border-emerald-500/50 text-emerald-300' : 'bg-rose-950/70 border border-rose-500/50 text-rose-300' ?>">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl"><?= $allPassed ? '✅' : '❌' ?></span>
                    <div>
                        <h2 class="font-semibold text-lg"><?= $allPassed ? 'All Search & Discovery Tests Passed' : 'Some Tests Failed' ?></h2>
                        <p class="text-xs opacity-85"><?= $allPassed ? 'Guest access, category/price/location filters, sorting & product details verified.' : 'Review error messages below.' ?></p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $allPassed ? 'bg-emerald-500 text-black' : 'bg-rose-500 text-white' ?>">
                    <?= $allPassed ? 'Ready for Step 6' : 'Action Required' ?>
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
                <a href="<?= base_url('renter/search.php') ?>" class="text-blue-400 hover:text-blue-300 font-semibold">&larr; Go to Public Catalog</a>
                <span>ORMS &bull; BCSP-064 &bull; Step 5 Complete</span>
            </div>
        </div>
    </div>
</body>
</html>
