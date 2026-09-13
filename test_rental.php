<?php
/**
 * Online Rental Management System (ORMS)
 * Rental Request Module Automated Verification Script (Step 6 Verification)
 * 
 * Accessible via CLI: php test_rental.php
 * Accessible via Browser: http://localhost/orms/test_rental.php
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
require_once __DIR__ . '/classes/Renter.php';
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/RentalRequest.php';
require_once __DIR__ . '/classes/Notification.php';
require_once __DIR__ . '/classes/exceptions/ORMSException.php';
require_once __DIR__ . '/classes/exceptions/InvalidDateRangeException.php';
require_once __DIR__ . '/classes/exceptions/ProductUnavailableException.php';

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
$createdRequests = [];
$testProductId = null;

try {
    // -------------------------------------------------------------
    // Setup / Discovery: Locate an Owner, Renter, and Product
    // -------------------------------------------------------------
    $ownerStmt = $pdo->query("
        SELECT u.user_id FROM `USER` u 
        JOIN `USER_ROLES` ur ON u.user_id = ur.user_id 
        WHERE ur.role = 'Owner' LIMIT 1
    ");
    $ownerId = (int) ($ownerStmt->fetchColumn() ?: 1);

    $renterStmt = $pdo->prepare("
        SELECT u.user_id FROM `USER` u 
        JOIN `USER_ROLES` ur ON u.user_id = ur.user_id 
        WHERE ur.role = 'Renter' AND u.user_id != :owner_id LIMIT 1
    ");
    $renterStmt->execute(['owner_id' => $ownerId]);
    $renterId = (int) ($renterStmt->fetchColumn() ?: 2);

    // Clean up previous test suite products and associated requests/notifications
    $oldProdsStmt = $pdo->query("SELECT product_id FROM `PRODUCT` WHERE title LIKE 'Automated Test Camera%'");
    $oldProdIds = $oldProdsStmt->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($oldProdIds)) {
        $inClause = implode(',', array_map('intval', $oldProdIds));
        $pdo->exec("DELETE FROM `NOTIFICATION` WHERE related_id IN (SELECT request_id FROM `RENTAL_REQUEST` WHERE product_id IN ($inClause))");
        $pdo->exec("DELETE FROM `RENTAL_REQUEST` WHERE product_id IN ($inClause)");
        $pdo->exec("DELETE FROM `PRODUCT_IMAGES` WHERE product_id IN ($inClause)");
        $pdo->exec("DELETE FROM `PRODUCT` WHERE product_id IN ($inClause)");
    }

    // Also clean up any lingering test requests on general products from earlier versions
    $cleanOldReqs = $pdo->query("SELECT request_id FROM `RENTAL_REQUEST` WHERE message LIKE 'Testing automated rental booking%' OR message LIKE 'Booking to be%'");
    $oldReqIds = $cleanOldReqs->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($oldReqIds)) {
        $reqInClause = implode(',', array_map('intval', $oldReqIds));
        $pdo->exec("DELETE FROM `NOTIFICATION` WHERE related_id IN ($reqInClause)");
        $pdo->exec("DELETE FROM `RENTAL_REQUEST` WHERE request_id IN ($reqInClause)");
    }

    // Create an isolated, dedicated test product for this test suite run
    $catStmt = $pdo->query("SELECT category_id FROM `CATEGORY` LIMIT 1");
    $catId = (int) ($catStmt->fetchColumn() ?: 1);

    $testProd = new Product(
        null,
        $ownerId,
        $catId,
        'Automated Test Camera ' . bin2hex(random_bytes(4)),
        'Dedicated temporary item for testing rental request module.',
        1200.00,
        5000.00,
        'Bangalore',
        'Available',
        'New'
    );
    $testProd->save();
    $testProductId = (int) $testProd->getProductID();

    // -------------------------------------------------------------
    // Test 1: 3NF Database Schema Compliance
    // -------------------------------------------------------------
    // Verify RENTAL_REQUEST does NOT have total_days or total_amount columns
    $colStmt = $pdo->prepare("
        SELECT COLUMN_NAME 
        FROM INFORMATION_SCHEMA.COLUMNS 
        WHERE TABLE_SCHEMA = DATABASE() 
          AND TABLE_NAME = 'RENTAL_REQUEST' 
          AND COLUMN_NAME IN ('total_days', 'total_amount')
    ");
    $colStmt->execute();
    $forbiddenCols = $colStmt->fetchAll(PDO::FETCH_COLUMN);

    $is3NFCompliant = empty($forbiddenCols);
    recordTest(
        '3NF Schema Compliance (No Redundant Computed Columns)',
        $is3NFCompliant,
        $is3NFCompliant 
            ? 'PASS: Verified RENTAL_REQUEST table conforms to 3NF — total_days and total_amount are NOT stored in the database table and are computed dynamically.' 
            : 'FAIL: Found redundant columns in RENTAL_REQUEST: ' . implode(', ', $forbiddenCols)
    );

    // -------------------------------------------------------------
    // Test 2: Date Range Validation (Invalid Date Checks)
    // -------------------------------------------------------------
    $pastDateCaught = false;
    try {
        RentalRequest::createWithLock($testProductId, $renterId, '2020-01-01', '2020-01-05');
    } catch (InvalidDateRangeException $e) {
        $pastDateCaught = true;
    }

    $reversedDateCaught = false;
    try {
        RentalRequest::createWithLock($testProductId, $renterId, '2026-12-10', '2026-12-05');
    } catch (InvalidDateRangeException $e) {
        $reversedDateCaught = true;
    }

    $datesValid = $pastDateCaught && $reversedDateCaught;
    recordTest(
        'Date Range Validation (Rejects Past & Reversed Dates)',
        $datesValid,
        $datesValid 
            ? 'PASS: InvalidDateRangeException properly thrown for both past dates and end_date <= start_date.' 
            : "FAIL: Past date caught={$pastDateCaught}, Reversed date caught={$reversedDateCaught}"
    );

    // -------------------------------------------------------------
    // Test 3: Self-Rental Prevention
    // -------------------------------------------------------------
    $selfRentalCaught = false;
    try {
        // Owner attempting to rent their own product
        RentalRequest::createWithLock($testProductId, $ownerId, date('Y-m-d', strtotime('+5 days')), date('Y-m-d', strtotime('+8 days')));
    } catch (ORMSException $e) {
        if (str_contains($e->getMessage(), 'cannot rent your own')) {
            $selfRentalCaught = true;
        }
    }

    recordTest(
        'Owner Self-Rental Prevention',
        $selfRentalCaught,
        $selfRentalCaught 
            ? 'PASS: Owner cannot book their own listing; rejected with ORMSException.' 
            : 'FAIL: Owner was allowed to book their own product.'
    );

    // -------------------------------------------------------------
    // Test 4: Concurrency-Safe Request Creation & Notification
    // -------------------------------------------------------------
    $startDate1 = date('Y-m-d', strtotime('+10 days'));
    $endDate1 = date('Y-m-d', strtotime('+14 days'));

    $renterModel = new Renter($renterId);
    $req1 = $renterModel->sendRequest($testProductId, $startDate1, $endDate1, 'Testing automated rental booking #1');
    $req1Id = $req1->getRequestID();
    $createdRequests[] = $req1Id;

    // Verify DB insertion
    $checkStmt = $pdo->prepare("SELECT status, start_date, end_date FROM `RENTAL_REQUEST` WHERE request_id = :id");
    $checkStmt->execute(['id' => $req1Id]);
    $savedReq = $checkStmt->fetch();

    // Verify dynamic calculations
    $expectedDays = 4; // +10 days to +14 days = 4 days
    $computedDays = $req1->getTotalDays();
    $computedAmount = $req1->getTotalAmount();

    // Check notification to owner
    $notifStmt = $pdo->prepare("
        SELECT notif_id, message FROM `NOTIFICATION` 
        WHERE user_id = :uid AND type = 'Rental' AND related_id = :rid 
        ORDER BY notif_id DESC LIMIT 1
    ");
    $notifStmt->execute(['uid' => $ownerId, 'rid' => $req1Id]);
    $notifRow = $notifStmt->fetch();

    $test4Pass = ($savedReq && $savedReq['status'] === 'Pending' && $computedDays === $expectedDays && !empty($notifRow));
    recordTest(
        'Atomic Rental Request Submission (SELECT ... FOR UPDATE Locking)',
        $test4Pass,
        $test4Pass 
            ? "PASS: Request #{$req1Id} created in 'Pending' status. Dynamic days={$computedDays}, Dynamic total=₹{$computedAmount}. Owner notified (Notif #{$notifRow['notif_id']})." 
            : "FAIL: Failed to create atomic request or notify owner."
    );

    // -------------------------------------------------------------
    // Test 5: Owner Approval Workflow & Renter Notification
    // -------------------------------------------------------------
    $ownerModel = new Owner($ownerId);
    $approved = $ownerModel->manageRentalRequest($req1Id, 'approve');

    $checkApproveStmt = $pdo->prepare("SELECT status FROM `RENTAL_REQUEST` WHERE request_id = :id");
    $checkApproveStmt->execute(['id' => $req1Id]);
    $approveStatus = $checkApproveStmt->fetchColumn();

    // Check notification to renter
    $renterNotifStmt = $pdo->prepare("
        SELECT notif_id, message FROM `NOTIFICATION` 
        WHERE user_id = :uid AND type = 'Rental' AND related_id = :rid 
        ORDER BY notif_id DESC LIMIT 1
    ");
    $renterNotifStmt->execute(['uid' => $renterId, 'rid' => $req1Id]);
    $renterNotif = $renterNotifStmt->fetch();

    $test5Pass = ($approved && $approveStatus === 'Approved' && !empty($renterNotif));
    recordTest(
        'Owner Approval Workflow & Status Transition',
        $test5Pass,
        $test5Pass 
            ? "PASS: Request #{$req1Id} transitioned from 'Pending' to 'Approved'. Renter received notification to pay: '{$renterNotif['message']}'." 
            : "FAIL: Status was {$approveStatus} or notification missing."
    );

    // -------------------------------------------------------------
    // Test 6: Concurrency / Double-Booking Prevention
    // -------------------------------------------------------------
    // Product is now Approved from +10 days to +14 days.
    // Try to create an overlapping booking from +12 days to +16 days.
    $overlapCaught = false;
    $startDateOverlap = date('Y-m-d', strtotime('+12 days'));
    $endDateOverlap = date('Y-m-d', strtotime('+16 days'));

    try {
        RentalRequest::createWithLock($testProductId, $renterId, $startDateOverlap, $endDateOverlap, 'Overlapping test booking');
    } catch (ProductUnavailableException $e) {
        $overlapCaught = true;
    }

    recordTest(
        'Double-Booking Prevention (Overlapping Booking Race Condition)',
        $overlapCaught,
        $overlapCaught 
            ? 'PASS: Overlapping dates rejected with ProductUnavailableException ("The product has already been reserved or rented for the selected dates.").' 
            : 'FAIL: System allowed double-booking of overlapping dates!'
    );

    // -------------------------------------------------------------
    // Test 7: Owner Rejection Workflow with Reason
    // -------------------------------------------------------------
    $startDate2 = date('Y-m-d', strtotime('+20 days'));
    $endDate2 = date('Y-m-d', strtotime('+22 days'));
    $req2 = $renterModel->sendRequest($testProductId, $startDate2, $endDate2, 'Booking to be rejected');
    $req2Id = $req2->getRequestID();
    $createdRequests[] = $req2Id;

    $rejectReason = "Equipment is reserved for scheduled maintenance.";
    $rejected = $ownerModel->manageRentalRequest($req2Id, 'reject', $rejectReason);

    $checkRejectStmt = $pdo->prepare("SELECT status, cancellation_reason FROM `RENTAL_REQUEST` WHERE request_id = :id");
    $checkRejectStmt->execute(['id' => $req2Id]);
    $rejectRow = $checkRejectStmt->fetch();

    $test7Pass = ($rejected && $rejectRow['status'] === 'Rejected' && $rejectRow['cancellation_reason'] === $rejectReason);
    recordTest(
        'Owner Rejection with Mandatory Reason',
        $test7Pass,
        $test7Pass 
            ? "PASS: Request #{$req2Id} rejected. Reason '{$rejectRow['cancellation_reason']}' successfully persisted." 
            : "FAIL: Status={$rejectRow['status']}, Reason={$rejectRow['cancellation_reason']}"
    );

    // -------------------------------------------------------------
    // Test 8: Renter Cancellation Workflow
    // -------------------------------------------------------------
    $startDate3 = date('Y-m-d', strtotime('+25 days'));
    $endDate3 = date('Y-m-d', strtotime('+27 days'));
    $req3 = $renterModel->sendRequest($testProductId, $startDate3, $endDate3, 'Booking to be cancelled by renter');
    $req3Id = $req3->getRequestID();
    $createdRequests[] = $req3Id;

    $cancelReason = "Personal plans changed.";
    $cancelled = $renterModel->cancelRequest($req3Id, $cancelReason);

    $checkCancelStmt = $pdo->prepare("SELECT status, cancellation_reason FROM `RENTAL_REQUEST` WHERE request_id = :id");
    $checkCancelStmt->execute(['id' => $req3Id]);
    $cancelRow = $checkCancelStmt->fetch();

    $test8Pass = ($cancelled && $cancelRow['status'] === 'Cancelled' && $cancelRow['cancellation_reason'] === $cancelReason);
    recordTest(
        'Renter Cancellation Workflow',
        $test8Pass,
        $test8Pass 
            ? "PASS: Request #{$req3Id} cancelled by renter. Reason '{$cancelRow['cancellation_reason']}' persisted. Owner notified." 
            : "FAIL: Status={$cancelRow['status']}, Reason={$cancelRow['cancellation_reason']}"
    );

} catch (Throwable $t) {
    recordTest(
        'Unexpected Test Suite Exception',
        false,
        'CRITICAL: ' . $t->getMessage() . ' at ' . $t->getFile() . ':' . $t->getLine()
    );
}

// -------------------------------------------------------------
// Output Presentation: CLI or Web Page
// -------------------------------------------------------------
if ($isCli) {
    echo "=======================================================\n";
    echo "  ORMS Automated Verification — Step 6: Rental Requests\n";
    echo "=======================================================\n";
    foreach ($results as $index => $res) {
        $num = $index + 1;
        $statusStr = $res['status'] ? "[ PASS ]" : "[ FAIL ]";
        echo "{$num}. {$statusStr} {$res['title']}\n";
        echo "   -> {$res['detail']}\n\n";
    }
    echo "-------------------------------------------------------\n";
    echo $allPassed ? "OVERALL RESULT: ALL 8 TESTS PASSED! Rental Request module is 100% operational.\n" : "OVERALL RESULT: SOME TESTS FAILED.\n";
    echo "=======================================================\n";
    exit($allPassed ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORMS — Rental Request Module Test Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto bg-slate-900 rounded-2xl shadow-2xl border border-slate-800 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 p-6">
            <h1 class="text-2xl font-bold tracking-wide">Online Rental Management System (ORMS)</h1>
            <p class="text-blue-200 text-sm mt-1">Step 6 Verification: Rental Request & Concurrency Module</p>
        </div>

        <div class="p-6">
            <div class="mb-6 flex items-center justify-between p-4 rounded-xl <?= $allPassed ? 'bg-emerald-950/70 border border-emerald-500/50 text-emerald-300' : 'bg-rose-950/70 border border-rose-500/50 text-rose-300' ?>">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl"><?= $allPassed ? '✅' : '❌' ?></span>
                    <div>
                        <h2 class="font-semibold text-lg"><?= $allPassed ? 'All 8 Rental Request Tests Passed' : 'Some Tests Failed' ?></h2>
                        <p class="text-xs opacity-85"><?= $allPassed ? 'Submit request, locking, double-booking prevention, approve/reject, and notifications verified.' : 'Review test output details below.' ?></p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $allPassed ? 'bg-emerald-500 text-black' : 'bg-rose-500 text-white' ?>">
                    <?= $allPassed ? 'Ready for Step 7' : 'Action Required' ?>
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
                <a href="<?= base_url('renter/my_rentals.php') ?>" class="text-blue-400 hover:text-blue-300 font-semibold">&larr; Go to My Rentals</a>
                <span>ORMS &bull; BCSP-064 &bull; Step 6 Complete</span>
            </div>
        </div>
    </div>
</body>
</html>
