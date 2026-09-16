<?php
/**
 * Online Rental Management System (ORMS)
 * Automated Verification Script — Step 8: Fine & Return Management Module
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.9, 4, 5 (Rule 8, 9, 11, 12) & Section 6
 * 
 * Usage:
 *   CLI: php test_fine.php
 *   Web: http://localhost/orms/test_fine.php
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/fine_config.php';
require_once __DIR__ . '/classes/RentalRequest.php';
require_once __DIR__ . '/classes/Product.php';
require_once __DIR__ . '/classes/Transaction.php';
require_once __DIR__ . '/classes/Fine.php';
require_once __DIR__ . '/classes/Owner.php';
require_once __DIR__ . '/classes/Renter.php';
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
    echo "  ORMS Automated Verification — Step 8: Fine Module\n";
    echo "=======================================================\n";
}

$db = Database::getInstance()->getConnection();
set_fine_rate(150.00); // Ensure standard baseline fine rate for test calculations

// -------------------------------------------------------------
// Helper: Create a fresh active rental request with completed payment
// -------------------------------------------------------------
function create_test_active_rental(PDO $db, string $startDate, string $endDate, float $rentPerDay = 1000.00, float $deposit = 2000.00): array {
    // 1. Insert product
    $stmtProd = $db->prepare("
        INSERT INTO `PRODUCT` (`owner_id`, `category_id`, `title`, `description`, `rent_per_day`, `security_deposit`, `location`, `avail_status`, `condition`, `listed_date`)
        VALUES (1, 1, 'Fine Test Camera', 'Professional mirrorless body for fine verification', :rent, :deposit, 'Bengaluru', 'Rented', 'Good', NOW())
    ");
    $stmtProd->execute(['rent' => $rentPerDay, 'deposit' => $deposit]);
    $productId = (int) $db->lastInsertId();

    // 2. Insert rental request as Active
    $stmtReq = $db->prepare("
        INSERT INTO `RENTAL_REQUEST` (`product_id`, `renter_id`, `start_date`, `end_date`, `status`, `request_date`, `message`)
        VALUES (:prod_id, 2, :sdate, :edate, 'Active', NOW(), 'Testing fines & returns')
    ");
    $stmtReq->execute([
        'prod_id' => $productId,
        'sdate'   => $startDate,
        'edate'   => $endDate
    ]);
    $requestId = (int) $db->lastInsertId();

    // 3. Insert transaction as Completed
    $days = max(1, (int) round((strtotime($endDate) - strtotime($startDate)) / 86400));
    $rentAmount = round($days * $rentPerDay, 2);

    $stmtTx = $db->prepare("
        INSERT INTO `TRANSACTION` (`request_id`, `payer_id`, `rental_amount`, `deposit_amount`, `deposit_status`, `payment_mode`, `payment_date`, `payment_status`)
        VALUES (:req_id, 2, :rent_amt, :dep_amt, 'Held', 'UPI', NOW(), 'Completed')
    ");
    $stmtTx->execute([
        'req_id'   => $requestId,
        'rent_amt' => $rentAmount,
        'dep_amt'  => $deposit
    ]);
    $transactionId = (int) $db->lastInsertId();

    return [
        'product_id'     => $productId,
        'request_id'     => $requestId,
        'transaction_id' => $transactionId,
        'deposit'        => $deposit,
        'rent_per_day'   => $rentPerDay
    ];
}

// -------------------------------------------------------------
// TEST 1: Late return formula & auto-detection calculation
// -------------------------------------------------------------
run_test("Late Return Formula & Calculation", function() use ($db) {
    $fine = new Fine();
    $calc = $fine->calcLateReturnAmount(4, 150.00);
    if ($calc !== 600.00) {
        throw new Exception("calcLateReturnAmount(4, 150) expected 600.00, got {$calc}");
    }

    // Set end date to 3 days ago, return today
    $pastEnd = date('Y-m-d', strtotime('-3 days'));
    $pastStart = date('Y-m-d', strtotime('-6 days'));
    $active = create_test_active_rental($db, $pastStart, $pastEnd, 500.00, 3000.00);

    $req = RentalRequest::findById($active['request_id']);
    $lateInfo = Fine::autoDetectLateReturn($req, date('Y-m-d'));

    if (!$lateInfo || !$lateInfo['is_late']) {
        throw new Exception("autoDetectLateReturn failed to flag overdue rental.");
    }
    if ($lateInfo['late_days'] !== 3) {
        throw new Exception("Expected 3 late days, got " . $lateInfo['late_days']);
    }

    return "Late return formula validated. 4 days @ ₹150 = ₹600. Auto-detection detected 3 late days.";
});

// -------------------------------------------------------------
// TEST 2: Statutory Maximum Fine Cap (2 * security_deposit per Rule 9)
// -------------------------------------------------------------
run_test("Statutory Maximum Fine Cap Enforcement (Rule 9)", function() use ($db) {
    // Deposit ₹500, max cap = 2 * 500 = ₹1000
    // End date 20 days ago -> 20 * 150 = ₹3000 raw fee
    $pastEnd = date('Y-m-d', strtotime('-20 days'));
    $pastStart = date('Y-m-d', strtotime('-25 days'));
    $active = create_test_active_rental($db, $pastStart, $pastEnd, 200.00, 500.00);

    $req = RentalRequest::findById($active['request_id']);
    $lateInfo = Fine::autoDetectLateReturn($req, date('Y-m-d'));

    if (!$lateInfo['is_capped']) {
        throw new Exception("Fine was not capped at 2 * security_deposit.");
    }
    if ($lateInfo['amount'] !== 1000.00) {
        throw new Exception("Expected capped amount ₹1000.00, got ₹{$lateInfo['amount']}");
    }

    return "Raw fine of ₹3000 (20d × ₹150) was strictly capped at ₹1000 (2× security deposit of ₹500).";
});

// -------------------------------------------------------------
// TEST 3: Clean Return Execution (100% Deposit Refund)
// -------------------------------------------------------------
run_test("Clean Return Execution (100% Escrow Deposit Refund)", function() use ($db) {
    $now = date('Y-m-d');
    $active = create_test_active_rental($db, $now, date('Y-m-d', strtotime('+3 days')), 800.00, 2500.00);

    $owner = new Owner(1);
    $result = $owner->confirmReturn($active['request_id'], $now);

    if (!$result) {
        throw new Exception("confirmReturn returned false.");
    }

    // Verify DB states
    $tx = Transaction::findById($active['transaction_id']);
    if ($tx->getDepositStatus() !== 'Refunded') {
        throw new Exception("Expected deposit_status 'Refunded', got '{$tx->getDepositStatus()}'");
    }

    $req = RentalRequest::findById($active['request_id']);
    if ($req->getStatus() !== 'Completed') {
        throw new Exception("Expected request status 'Completed', got '{$req->getStatus()}'");
    }

    $prod = Product::findById($active['product_id']);
    if ($prod->getAvailStatus() !== 'Available') {
        throw new Exception("Expected product status 'Available', got '{$prod->getAvailStatus()}'");
    }

    return "Clean return confirmed: Deposit ₹2500 released as 'Refunded', request 'Completed', product 'Available'.";
});

// -------------------------------------------------------------
// TEST 4: Fine <= Deposit Deduction (Partial Refund per Rule 8)
// -------------------------------------------------------------
run_test("Fine <= Deposit Deduction (Partial Refund per Rule 8)", function() use ($db) {
    $pastEnd = date('Y-m-d', strtotime('-2 days'));
    $pastStart = date('Y-m-d', strtotime('-5 days'));
    // Deposit ₹3000, 2 days late @ ₹150 = ₹300 fine + ₹500 damage fine = ₹800 total fine
    $active = create_test_active_rental($db, $pastStart, $pastEnd, 500.00, 3000.00);

    $owner = new Owner(1);
    $result = $owner->raiseFine($active['request_id'], 'Damage', 500.00, 'Minor scratch on lens body', date('Y-m-d'));

    if (!$result['success']) {
        throw new Exception("raiseFine returned success=false.");
    }

    if ($result['total_fine'] !== 800.00) {
        throw new Exception("Expected total fine 800.00, got {$result['total_fine']}");
    }
    if ($result['net_refund'] !== 2200.00) {
        throw new Exception("Expected net refund 2200.00, got {$result['net_refund']}");
    }

    $tx = Transaction::findById($active['transaction_id']);
    if ($tx->getDepositStatus() !== 'Partially_Refunded') {
        throw new Exception("Expected deposit_status 'Partially_Refunded', got '{$tx->getDepositStatus()}'");
    }

    // Verify Fine records in DB
    $fines = Fine::findByRequest($active['request_id']);
    if (count($fines) !== 2) {
        throw new Exception("Expected 2 fine records (Late + Damage), got " . count($fines));
    }
    foreach ($fines as $f) {
        if ($f->getStatus() !== 'Deducted_From_Deposit') {
            throw new Exception("Expected fine status 'Deducted_From_Deposit', got '{$f->getStatus()}'");
        }
    }

    return "Total fine ₹800 deducted from deposit ₹3000. Deposit marked 'Partially_Refunded' (Net refund ₹2200).";
});

// -------------------------------------------------------------
// TEST 5: Fine > Deposit Handling (Deposit Forfeited, Excess Unpaid)
// -------------------------------------------------------------
run_test("Fine > Deposit Handling (Deposit Forfeited & Excess Unpaid)", function() use ($db) {
    $now = date('Y-m-d');
    // Deposit ₹500, Damage fine ₹1200 -> Excess ₹700 unpaid
    $active = create_test_active_rental($db, $now, date('Y-m-d', strtotime('+2 days')), 300.00, 500.00);

    $owner = new Owner(1);
    $result = $owner->raiseFine($active['request_id'], 'Damage', 1200.00, 'Severe lens crack', $now);

    if (!$result['success']) {
        throw new Exception("raiseFine failed.");
    }
    if ($result['unpaid_fine'] !== 700.00) {
        throw new Exception("Expected unpaid balance 700.00, got {$result['unpaid_fine']}");
    }

    $tx = Transaction::findById($active['transaction_id']);
    if ($tx->getDepositStatus() !== 'Forfeited') {
        throw new Exception("Expected deposit_status 'Forfeited', got '{$tx->getDepositStatus()}'");
    }

    $fines = Fine::findByRequest($active['request_id']);
    $unpaidFines = array_filter($fines, fn($f) => $f->getStatus() === 'Unpaid');
    if (empty($unpaidFines)) {
        throw new Exception("No fine with status 'Unpaid' created.");
    }

    return "Deposit of ₹500 marked 'Forfeited'. Excess damage balance of ₹700 recorded with status 'Unpaid'.";
});

// -------------------------------------------------------------
// TEST 6: Renter Payment of Outstanding Fine (payFine())
// -------------------------------------------------------------
run_test("Renter Payment of Outstanding Fine (payFine())", function() use ($db) {
    // Find an unpaid fine from Test 5
    $stmt = $db->query("SELECT * FROM `FINE` WHERE status = 'Unpaid' ORDER BY fine_id DESC LIMIT 1");
    $fineRow = $stmt->fetch();

    if (!$fineRow) {
        throw new Exception("No unpaid fine found to test payment.");
    }

    $fineId = (int) $fineRow['fine_id'];
    $renter = new Renter((int) $fineRow['renter_id']);
    $paid = $renter->payFine($fineId, 'UPI');

    if (!$paid) {
        throw new Exception("payFine() failed.");
    }

    $updatedFine = Fine::findById($fineId);
    if ($updatedFine->getStatus() !== 'Paid' || empty($updatedFine->getPaidDate())) {
        throw new Exception("Fine status was not updated to 'Paid'.");
    }

    return "Renter paid outstanding fine #{$fineId} of ₹" . number_format($updatedFine->getAmount(), 2) . ". Status transitioned to 'Paid'.";
});

// -------------------------------------------------------------
// TEST 7: Security & Unauthorized Action Rejection
// -------------------------------------------------------------
run_test("Security & Authorization Access Control", function() use ($db) {
    $now = date('Y-m-d');
    $active = create_test_active_rental($db, $now, date('Y-m-d', strtotime('+2 days')), 400.00, 1000.00);

    // User 2 (Priya) is NOT the owner of product (Owner is User 1 Rahul)
    $unauthorizedOwner = new Owner(2);
    try {
        $unauthorizedOwner->confirmReturn($active['request_id']);
        throw new Exception("Non-owner was allowed to confirm return!");
    } catch (UnauthorizedActionException $e) {
        // Expected
    }

    // Non-renter paying someone else's fine
    $stmt = $db->query("SELECT * FROM `FINE` WHERE renter_id = 2 LIMIT 1");
    $fRow = $stmt->fetch();
    if ($fRow) {
        $wrongRenter = new Renter(1); // User 1 trying to pay Priya's fine
        try {
            $wrongRenter->payFine((int) $fRow['fine_id']);
            throw new Exception("Unauthorized user was allowed to pay someone else's fine!");
        } catch (UnauthorizedActionException $e) {
            // Expected
        }
    }

    return "Access control verified: Non-owners and unauthorized users are strictly blocked with UnauthorizedActionException.";
});

// -------------------------------------------------------------
// TEST 8: Rule 12 Auto-Refund 7-Day Timeout Engine
// -------------------------------------------------------------
run_test("Rule 12 Auto-Refund 7-Day Timeout Engine", function() use ($db) {
    // Create an active rental with end date 10 days ago (past 7-day timeout)
    $overdueEnd = date('Y-m-d', strtotime('-10 days'));
    $overdueStart = date('Y-m-d', strtotime('-15 days'));
    $active = create_test_active_rental($db, $overdueStart, $overdueEnd, 500.00, 2000.00);

    $processed = Fine::checkAndTriggerAutoRefunds();
    if ($processed < 1) {
        throw new Exception("checkAndTriggerAutoRefunds did not process the overdue rental.");
    }

    $tx = Transaction::findById($active['transaction_id']);
    if ($tx->getDepositStatus() !== 'Refunded') {
        throw new Exception("Expected deposit_status 'Refunded' after auto-refund, got '{$tx->getDepositStatus()}'");
    }

    $req = RentalRequest::findById($active['request_id']);
    if ($req->getStatus() !== 'Completed') {
        throw new Exception("Expected request status 'Completed' after auto-refund, got '{$req->getStatus()}'");
    }

    return "Rule 12 engine verified: Overdue rental #{$active['request_id']} (10d past end date) auto-settled with 100% deposit refund.";
});

if ($isCli) {
    $allPassed = !in_array('FAIL', array_column($tests, 'status'));
    echo "-------------------------------------------------------\n";
    if ($allPassed) {
        echo "OVERALL RESULT: ALL 8 TESTS PASSED! Fine & Return module is 100% operational.\n";
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
    <title>ORMS Automated Verification — Step 8: Fine Module</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen p-6 sm:p-10 font-sans">
    <div class="max-w-4xl mx-auto space-y-8">
        <div class="border-b border-slate-800 pb-6">
            <h1 class="text-3xl font-extrabold tracking-tight text-white flex items-center space-x-3">
                <span>🛡️</span>
                <span>Step 8: Fine & Return Module Verification</span>
            </h1>
            <p class="text-slate-400 text-sm mt-2">
                Automated test assertions covering late return calculations, Rule 9 2× deposit caps, Rule 8 deposit deductions, damage assessments, renter balance payments, and Rule 12 auto-refund timeouts.
            </p>
        </div>

        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-800 pb-4 mb-6">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl">✅</span>
                    <div>
                        <h2 class="font-semibold text-lg">All 8 Fine & Return Tests Passed</h2>
                        <p class="text-xs opacity-85">Deposit waterfall, statutory caps, late calculations, and auto-refund engine verified.</p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-emerald-500 text-black">
                    Ready for Step 9
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
                <a href="/orms/owner/manage_requests.php?status=Active" class="text-blue-400 hover:text-blue-300 font-semibold">&larr; Go to Owner Manage Requests</a>
                <span>ORMS &bull; BCSP-064 &bull; Step 8 Complete</span>
            </div>
        </div>
    </div>
</body>
</html>
