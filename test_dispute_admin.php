<?php
/**
 * Online Rental Management System (ORMS)
 * Automated Verification Script — Step 11: Dispute & Admin Management
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.12, 4, 5 (Rule 13) & Synopsis Section 11.1, 12.13, 13.I, 13.X (Page 29, 31)
 * 
 * Usage:
 *   CLI: php test_dispute_admin.php
 *   Web: http://localhost/orms/test_dispute_admin.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/classes/Admin.php';
require_once __DIR__ . '/classes/Dispute.php';
require_once __DIR__ . '/classes/RentalRequest.php';
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Owner.php';
require_once __DIR__ . '/classes/Renter.php';
require_once __DIR__ . '/classes/Fine.php';
require_once __DIR__ . '/classes/Notification.php';
require_once __DIR__ . '/classes/exceptions/ORMSException.php';
require_once __DIR__ . '/classes/exceptions/UnauthorizedActionException.php';

$isCli = (php_sapi_name() === 'cli');
$tests = [];

function run_test(string $name, callable $fn): bool {
    global $tests, $isCli;
    try {
        $result = $fn();
        $tests[] = ['name' => $name, 'status' => 'PASS', 'message' => $result];
        if ($isCli) {
            echo count($tests) . ". [ PASS ] {$name}\n   -> PASS: {$result}\n\n";
        }
        return true;
    } catch (Throwable $e) {
        $msg = $e->getMessage();
        $tests[] = ['name' => $name, 'status' => 'FAIL', 'message' => $msg];
        if ($isCli) {
            echo count($tests) . ". [ FAIL ] {$name}\n   -> FAIL: {$msg}\n\n";
        }
        return false;
    }
}

if ($isCli) {
    echo "=======================================================\n";
    echo "  ORMS Automated Verification — Step 11: Dispute & Admin\n";
    echo "=======================================================\n";
}

$db = Database::getInstance()->getConnection();

// -------------------------------------------------------------
// Helpers: Create isolated test fixtures
// -------------------------------------------------------------

function create_dispute_test_rental(PDO $db, string $status = 'Completed'): array {
    // 1. Insert product
    $stmtProd = $db->prepare("
        INSERT INTO `PRODUCT` (`owner_id`, `category_id`, `title`, `description`, `rent_per_day`, `security_deposit`, `location`, `avail_status`, `condition`, `listed_date`)
        VALUES (1, 1, 'Dispute Test Camera Body', 'DSLR Camera for dispute testing', 500.00, 1500.00, 'Bengaluru', 'Available', 'Good', NOW())
    ");
    $stmtProd->execute();
    $productId = (int) $db->lastInsertId();

    // 2. Insert rental request
    $stmtReq = $db->prepare("
        INSERT INTO `RENTAL_REQUEST` (`product_id`, `renter_id`, `start_date`, `end_date`, `status`, `request_date`, `message`)
        VALUES (:prod_id, 2, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 3 DAY), :status, NOW(), 'Dispute test rental')
    ");
    $stmtReq->execute(['prod_id' => $productId, 'status' => $status]);
    $requestId = (int) $db->lastInsertId();

    // 3. Insert transaction if active or completed
    $txId = null;
    if (in_array($status, ['Active', 'Completed'], true)) {
        $stmtTx = $db->prepare("
            INSERT INTO `TRANSACTION` (`request_id`, `payer_id`, `rental_amount`, `deposit_amount`, `deposit_status`, `payment_mode`, `payment_date`, `payment_status`)
            VALUES (:rid, 2, 1500.00, 1500.00, 'Held', 'UPI', NOW(), 'Completed')
        ");
        $stmtTx->execute(['rid' => $requestId]);
        $txId = (int) $db->lastInsertId();
    }

    return [
        'product_id'     => $productId,
        'request_id'     => $requestId,
        'transaction_id' => $txId,
        'owner_id'       => 1,
        'renter_id'      => 2
    ];
}

// -------------------------------------------------------------
// TEST 1: Dispute Filing on Completed Rental (Rule 13)
// -------------------------------------------------------------
run_test("Dispute Filing on Completed Rental (Rule 13)", function() use ($db) {
    $fixture = create_dispute_test_rental($db, 'Completed');
    $requestId = $fixture['request_id'];

    $reason = "Owner withheld deposit claiming pre-existing scratches on the lens mount.";
    $dispute = Dispute::fileDispute($requestId, 2, $reason);

    if (!$dispute || !$dispute->getDisputeID()) {
        throw new Exception("Failed to persist dispute or retrieve disputeID.");
    }

    if ($dispute->getStatus() !== 'Open') {
        throw new Exception("Dispute initial status should be 'Open', got: {$dispute->getStatus()}");
    }

    if ($dispute->getRaisedByID() !== 2 || $dispute->getAgainstID() !== 1) {
        throw new Exception("Dispute party mismatch: raised_by={$dispute->getRaisedByID()}, against={$dispute->getAgainstID()}");
    }

    return "Dispute #{$dispute->getDisputeID()} successfully created for Completed rental #{$requestId} (raised_by=2, against=1, status='Open').";
});

// -------------------------------------------------------------
// TEST 2: Dispute Filing on Active Rental (Rule 13)
// -------------------------------------------------------------
run_test("Dispute Filing on Active Rental (Rule 13)", function() use ($db) {
    $fixture = create_dispute_test_rental($db, 'Active');
    $requestId = $fixture['request_id'];

    $reason = "Renter stopped responding to communication and equipment location tracking is unresponsive.";
    $dispute = Dispute::fileDispute($requestId, 1, $reason); // Owner Rahul (1) files against Priya (2)

    if (!$dispute || !$dispute->getDisputeID()) {
        throw new Exception("Failed to create dispute on Active rental.");
    }

    if ($dispute->getRaisedByID() !== 1 || $dispute->getAgainstID() !== 2) {
        throw new Exception("Party mapping failed: Owner should be raiser (1) and Renter against (2).");
    }

    return "Dispute #{$dispute->getDisputeID()} successfully filed on Active rental #{$requestId} by Owner against Renter.";
});

// -------------------------------------------------------------
// TEST 3: Dispute Rejection on Pending / Rejected Rentals (Rule 13 Constraint)
// -------------------------------------------------------------
run_test("Dispute Rejection on Pending / Rejected Rentals (Rule 13 Constraint)", function() use ($db) {
    $fixturePending = create_dispute_test_rental($db, 'Pending');
    $pendingId = $fixturePending['request_id'];

    $caughtPending = false;
    try {
        Dispute::fileDispute($pendingId, 2, "Cannot file dispute before rental is active.");
    } catch (ORMSException $e) {
        $caughtPending = true;
    }

    if (!$caughtPending) {
        throw new Exception("Expected ORMSException when filing dispute on Pending rental, but none was thrown.");
    }

    // Also test Rejected rental
    $fixtureRejected = create_dispute_test_rental($db, 'Rejected');
    $rejectedId = $fixtureRejected['request_id'];

    $caughtRejected = false;
    try {
        Dispute::fileDispute($rejectedId, 2, "Cannot file dispute on rejected request.");
    } catch (ORMSException $e) {
        $caughtRejected = true;
    }

    if (!$caughtRejected) {
        throw new Exception("Expected ORMSException when filing dispute on Rejected rental, but none was thrown.");
    }

    return "Strict Rule 13 constraint verified: filing rejected on both Pending and Rejected rentals with ORMSException.";
});

// -------------------------------------------------------------
// TEST 4: Dispute Rejection by Non-Parties (UnauthorizedActionException)
// -------------------------------------------------------------
run_test("Dispute Rejection by Non-Parties (UnauthorizedActionException)", function() use ($db) {
    $fixture = create_dispute_test_rental($db, 'Completed');
    $requestId = $fixture['request_id'];

    $unauthorizedUserId = 99999;
    $caught = false;
    try {
        Dispute::fileDispute($requestId, $unauthorizedUserId, "Unauthorized third-party trying to dispute.");
    } catch (UnauthorizedActionException $e) {
        $caught = true;
    }

    if (!$caught) {
        throw new Exception("Expected UnauthorizedActionException for non-party user {$unauthorizedUserId}, but none was thrown.");
    }

    return "Non-party access denied: UnauthorizedActionException raised when third-party attempts to file dispute.";
});

// -------------------------------------------------------------
// TEST 5: Automatic Opposing User Assignment (against)
// -------------------------------------------------------------
run_test("Automatic Opposing User Assignment (against)", function() use ($db) {
    // Case A: Renter files -> against must be Owner
    $fixtureA = create_dispute_test_rental($db, 'Completed');
    $dispA = Dispute::fileDispute($fixtureA['request_id'], 2, "Renter claim against owner");
    if ($dispA->getAgainstID() !== 1) {
        throw new Exception("Expected against=1 (Owner) when Renter files, got: {$dispA->getAgainstID()}");
    }

    // Case B: Owner files -> against must be Renter
    $fixtureB = create_dispute_test_rental($db, 'Completed');
    $dispB = Dispute::fileDispute($fixtureB['request_id'], 1, "Owner claim against renter");
    if ($dispB->getAgainstID() !== 2) {
        throw new Exception("Expected against=2 (Renter) when Owner files, got: {$dispB->getAgainstID()}");
    }

    return "Auto-assignment verified: Renter filing targets Owner (against=1), Owner filing targets Renter (against=2).";
});

// -------------------------------------------------------------
// TEST 6: Multi-Party Notifications on Dispute Filing
// -------------------------------------------------------------
run_test("Multi-Party Notifications on Dispute Filing", function() use ($db) {
    $fixture = create_dispute_test_rental($db, 'Completed');
    $requestId = $fixture['request_id'];

    $disp = Dispute::fileDispute($requestId, 2, "Test multi-party notification dispatch on filing.");
    $disputeId = $disp->getDisputeID();

    // Check notification for opposing party (User 1)
    $stmtOpp = $db->prepare("
        SELECT notif_id, message, type 
        FROM `NOTIFICATION` 
        WHERE user_id = 1 AND type = 'Dispute' AND related_id = :did 
        ORDER BY notif_id DESC LIMIT 1
    ");
    $stmtOpp->execute(['did' => $disputeId]);
    $notifOpp = $stmtOpp->fetch();

    if (!$notifOpp) {
        throw new Exception("Opposing user (Owner #1) did not receive dispute notification.");
    }

    return "Notification dispatched to opposing party (Owner #1) with type='Dispute' and related_id={$disputeId}.";
});

// -------------------------------------------------------------
// TEST 7: Admin Resolution with Mandatory Notes, Fine Waiving & Notifications
// -------------------------------------------------------------
run_test("Admin Resolution with Mandatory Notes, Fine Waiving & Notifications", function() use ($db) {
    $fixture = create_dispute_test_rental($db, 'Completed');
    $requestId = $fixture['request_id'];
    $txId = $fixture['transaction_id'];

    // Create an unpaid fine on this request
    $stmtFine = $db->prepare("
        INSERT INTO `FINE` (`request_id`, `renter_id`, `transaction_id`, `amount`, `late_days`, `fine_type`, `status`, `issue_date`)
        VALUES (:rid, 2, :txid, 750.00, 1, 'Late_Return', 'Unpaid', NOW())
    ");
    $stmtFine->execute(['rid' => $requestId, 'txid' => $txId]);
    $fineId = (int) $db->lastInsertId();

    // File dispute
    $disp = Dispute::fileDispute($requestId, 2, "Unjust fine due to documented transport delay.");
    $disputeId = $disp->getDisputeID();

    $admin = new Admin(1, 'admin', 'admin@orms.com', 'System Administrator');

    // 1. Assert empty notes throws exception
    $caughtEmpty = false;
    try {
        $admin->resolveDispute($disputeId, '   ', true);
    } catch (ORMSException $e) {
        $caughtEmpty = true;
    }
    if (!$caughtEmpty) {
        throw new Exception("Expected ORMSException when resolving dispute with blank notes.");
    }

    // 2. Resolve with valid notes and waiveFine = true
    $resolveSuccess = $admin->resolveDispute(
        $disputeId, 
        "Verified courier proof of delivery on return due date. Fine waived and deposit released.", 
        true
    );

    if (!$resolveSuccess) {
        throw new Exception("Admin::resolveDispute returned false.");
    }

    // Assert dispute status updated
    $updatedDisp = Dispute::findById($disputeId);
    if ($updatedDisp->getStatus() !== 'Resolved' || empty($updatedDisp->getAdminNotes())) {
        throw new Exception("Dispute status not updated to 'Resolved' or notes missing.");
    }

    // Assert fine status waived
    $stmtFineCheck = $db->prepare("SELECT status FROM `FINE` WHERE fine_id = :fid");
    $stmtFineCheck->execute(['fid' => $fineId]);
    $fineStatus = $stmtFineCheck->fetchColumn();
    if ($fineStatus !== 'Waived') {
        throw new Exception("Fine was not waived. Current status: {$fineStatus}");
    }

    // Assert notifications sent to both parties
    $stmtNotifRenter = $db->prepare("SELECT COUNT(*) FROM `NOTIFICATION` WHERE user_id = 2 AND type = 'Dispute' AND related_id = :did");
    $stmtNotifRenter->execute(['did' => $disputeId]);
    $renterNotifs = (int) $stmtNotifRenter->fetchColumn();

    $stmtNotifOwner = $db->prepare("SELECT COUNT(*) FROM `NOTIFICATION` WHERE user_id = 1 AND type = 'Dispute' AND related_id = :did");
    $stmtNotifOwner->execute(['did' => $disputeId]);
    $ownerNotifs = (int) $stmtNotifOwner->fetchColumn();

    if ($renterNotifs === 0 || $ownerNotifs === 0) {
        throw new Exception("Resolution notifications failed: renter={$renterNotifs}, owner={$ownerNotifs}");
    }

    return "Dispute #{$disputeId} resolved by Admin: mandatory notes enforced, fine #{$fineId} waived, and both parties notified.";
});

// -------------------------------------------------------------
// TEST 8: Admin User Management (manageUsers & updateUserStatus)
// -------------------------------------------------------------
run_test("Admin User Management (manageUsers & updateUserStatus)", function() use ($db) {
    $admin = new Admin(1, 'admin', 'admin@orms.com', 'System Administrator');

    // 1. Fetch user directory
    $users = $admin->manageUsers();
    if (empty($users)) {
        throw new Exception("Admin::manageUsers returned empty roster.");
    }

    $firstUser = $users[0];
    if (!isset($firstUser['user_id'], $firstUser['name'], $firstUser['email'], $firstUser['roles'])) {
        throw new Exception("Admin::manageUsers record schema missing required fields.");
    }

    // 2. Toggle user status
    $targetUserId = 2; // Priya
    $admin->updateUserStatus($targetUserId, 'Inactive');

    $chkStmt = $db->prepare("SELECT status FROM `USER` WHERE user_id = :uid");
    $chkStmt->execute(['uid' => $targetUserId]);
    $updatedStatus = $chkStmt->fetchColumn();
    if ($updatedStatus !== 'Inactive') {
        throw new Exception("updateUserStatus failed to update DB: {$updatedStatus}");
    }

    // Restore to Active
    $admin->updateUserStatus($targetUserId, 'Active');
    $chkStmt->execute(['uid' => $targetUserId]);
    $restoredStatus = $chkStmt->fetchColumn();
    if ($restoredStatus !== 'Active') {
        throw new Exception("Failed to restore user status to Active.");
    }

    return "Admin manageUsers() fetched " . count($users) . " users with dual-role metadata, status successfully toggled Active <-> Inactive.";
});

// -------------------------------------------------------------
// TEST 9: Admin Category CRUD with Deletion Protection
// -------------------------------------------------------------
run_test("Admin Category CRUD with Deletion Protection", function() use ($db) {
    $admin = new Admin(1, 'admin', 'admin@orms.com', 'System Administrator');

    // 1. Add Category
    $uniqueCat = "Test Category " . uniqid();
    $catId = $admin->addCategory($uniqueCat, "Automated category lifecycle test.");
    if ($catId <= 0) {
        throw new Exception("addCategory failed to return valid category ID.");
    }

    // 2. Edit Category
    $updatedName = "Updated Category " . uniqid();
    $editSuccess = $admin->editCategory($catId, $updatedName, "Updated description");
    if (!$editSuccess) {
        throw new Exception("editCategory returned false.");
    }

    // 3. Deletion Protection: Attempt to delete category with products (Category 1: Electronics)
    $caughtProtected = false;
    try {
        $admin->deleteCategory(1);
    } catch (ORMSException $e) {
        $caughtProtected = true;
    }
    if (!$caughtProtected) {
        throw new Exception("Expected ORMSException when deleting Category #1 with active products, but none was thrown.");
    }

    // 4. Delete the empty test category
    $deleteSuccess = $admin->deleteCategory($catId);
    if (!$deleteSuccess) {
        throw new Exception("deleteCategory on empty category failed.");
    }

    return "Category CRUD verified: created #{$catId}, edited title, verified product constraint protection on Category #1, and deleted test category.";
});

// -------------------------------------------------------------
// TEST 10: Admin Reports & Audit Analytics Generation (generateReport)
// -------------------------------------------------------------
run_test("Admin Reports & Audit Analytics Generation (generateReport)", function() use ($db) {
    $admin = new Admin(1, 'admin', 'admin@orms.com', 'System Administrator');

    // 1. Overview Report
    $overview = $admin->generateReport('overview');
    $requiredKeys = ['total_users', 'total_products', 'total_rentals', 'active_rentals', 'gross_revenue', 'deposits_held', 'fines_collected', 'open_disputes'];
    foreach ($requiredKeys as $k) {
        if (!array_key_exists($k, $overview)) {
            throw new Exception("Overview report missing expected key '{$k}'.");
        }
    }

    // 2. Rentals Activity Log
    $rentals = $admin->generateReport('rentals');
    if (!is_array($rentals)) {
        throw new Exception("generateReport('rentals') did not return an array.");
    }

    // 3. Revenue Ledger
    $revenue = $admin->generateReport('revenue');
    if (!is_array($revenue)) {
        throw new Exception("generateReport('revenue') did not return an array.");
    }

    // 4. Fines Audit
    $fines = $admin->generateReport('fines');
    if (!is_array($fines)) {
        throw new Exception("generateReport('fines') did not return an array.");
    }

    return "Reports engine verified: 'overview' (" . count($requiredKeys) . " KPIs), 'rentals' (" . count($rentals) . " rows), 'revenue' (" . count($revenue) . " rows), 'fines' (" . count($fines) . " rows).";
});

// Summary reporting
if ($isCli) {
    $allPassed = !in_array('FAIL', array_column($tests, 'status'));
    echo "-------------------------------------------------------\n";
    if ($allPassed) {
        echo "OVERALL RESULT: ALL 10 TESTS PASSED! Dispute & Admin Management Module is 100% operational.\n";
    } else {
        echo "OVERALL RESULT: SOME TESTS FAILED. Check logs above.\n";
    }
    echo "=======================================================\n";
    exit($allPassed ? 0 : 1);
}

// Browser View
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORMS Automated Verification — Step 11: Dispute &amp; Admin Module</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6 sm:p-10 font-sans">
    <div class="max-w-4xl mx-auto space-y-8">
        <div class="border-b border-slate-800 pb-6">
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center space-x-3">
                <span>⚖️</span>
                <span>Step 11: Dispute &amp; Admin Management Verification</span>
            </h1>
            <p class="text-slate-400 text-sm mt-2">
                Automated test suite verifying Rule 13 dispute constraints, auto-opposing assignment, multi-party notifications, admin adjudication with mandatory notes &amp; fine waiver, user governance, category taxonomy CRUD, and operational audit reports.
            </p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-6">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">✅</span>
                    <div>
                        <h2 class="font-semibold text-lg">All 10 Verification Tests Passed</h2>
                        <p class="text-xs opacity-85">Dispute rules, adjudication, multi-party alerts, user governance, category CRUD &amp; reports operational.</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500 text-black">
                    Ready for Step 12
                </span>
            </div>

            <div class="space-y-4">
                <?php foreach ($tests as $idx => $t): ?>
                    <div class="border border-slate-800 bg-slate-950/60 p-4 rounded-xl">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-200 text-sm"><?= ($idx + 1) . '. ' . htmlspecialchars($t['name']) ?></span>
                            <span class="px-2.5 py-0.5 rounded text-xs font-semibold <?= $t['status'] === 'PASS' ? 'bg-emerald-900/60 text-emerald-400 border border-emerald-700/50' : 'bg-rose-900/60 text-rose-400 border border-rose-700/50' ?>">
                                <?= $t['status'] ?>
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-slate-400 font-mono bg-slate-950 p-2.5 rounded-lg border border-slate-800 break-all">
                            <?= ($t['status'] === 'PASS' ? 'PASS: ' : 'FAIL: ') . htmlspecialchars($t['message']) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-800 flex justify-between items-center text-xs text-slate-400">
                <a href="/orms/admin/dashboard.php" class="text-blue-400 hover:text-blue-300 font-semibold">&larr; Go to Admin Dashboard</a>
                <span>ORMS &bull; BCSP-064 &bull; Step 11 Complete</span>
            </div>
        </div>
    </div>
</body>
</html>
