<?php
/**
 * Online Rental Management System (ORMS)
 * Financial & Transaction Module Automated Verification Script (Step 7 Verification)
 * 
 * Accessible via CLI: php test_transaction.php
 * Accessible via Browser: http://localhost/orms/test_transaction.php
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
require_once __DIR__ . '/classes/Transaction.php';
require_once __DIR__ . '/classes/Notification.php';
require_once __DIR__ . '/classes/exceptions/ORMSException.php';
require_once __DIR__ . '/classes/exceptions/PaymentFailedException.php';
require_once __DIR__ . '/classes/exceptions/UnauthorizedActionException.php';

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

try {
    // -------------------------------------------------------------
    // Setup & Cleanup: Clean prior test transaction artifacts
    // -------------------------------------------------------------
    $oldTxProds = $pdo->query("SELECT product_id FROM `PRODUCT` WHERE title LIKE 'TxTest Camera%'")->fetchAll(PDO::FETCH_COLUMN);
    if (!empty($oldTxProds)) {
        $inPids = implode(',', array_map('intval', $oldTxProds));
        $oldReqIds = $pdo->query("SELECT request_id FROM `RENTAL_REQUEST` WHERE product_id IN ($inPids)")->fetchAll(PDO::FETCH_COLUMN);
        if (!empty($oldReqIds)) {
            $inRids = implode(',', array_map('intval', $oldReqIds));
            $pdo->exec("DELETE FROM `FINE` WHERE request_id IN ($inRids)");
            $pdo->exec("DELETE FROM `NOTIFICATION` WHERE type = 'Payment' AND related_id IN (SELECT transaction_id FROM `TRANSACTION` WHERE request_id IN ($inRids))");
            $pdo->exec("DELETE FROM `TRANSACTION` WHERE request_id IN ($inRids)");
            $pdo->exec("DELETE FROM `RENTAL_REQUEST` WHERE request_id IN ($inRids)");
        }
        $pdo->exec("DELETE FROM `PRODUCT_IMAGES` WHERE product_id IN ($inPids)");
        $pdo->exec("DELETE FROM `PRODUCT` WHERE product_id IN ($inPids)");
    }

    // Identify Owner and 2 Renters
    $ownerStmt = $pdo->query("SELECT u.user_id FROM `USER` u JOIN `USER_ROLES` ur ON u.user_id = ur.user_id WHERE ur.role = 'Owner' LIMIT 1");
    $ownerId = (int) ($ownerStmt->fetchColumn() ?: 1);

    $renterStmt = $pdo->prepare("SELECT u.user_id FROM `USER` u JOIN `USER_ROLES` ur ON u.user_id = ur.user_id WHERE ur.role = 'Renter' AND u.user_id != :oid LIMIT 1");
    $renterStmt->execute(['oid' => $ownerId]);
    $renterId = (int) ($renterStmt->fetchColumn() ?: 2);

    $otherRenterStmt = $pdo->prepare("SELECT u.user_id FROM `USER` u JOIN `USER_ROLES` ur ON u.user_id = ur.user_id WHERE ur.role = 'Renter' AND u.user_id NOT IN (:oid, :rid) LIMIT 1");
    $otherRenterStmt->execute(['oid' => $ownerId, 'rid' => $renterId]);
    $otherRenterId = (int) ($otherRenterStmt->fetchColumn() ?: 8);

    // Create an isolated test product
    $catStmt = $pdo->query("SELECT category_id FROM `CATEGORY` LIMIT 1");
    $catId = (int) ($catStmt->fetchColumn() ?: 1);

    $rentPerDay = 1500.00;
    $depositAmount = 6000.00;
    $testProd = new Product(
        null,
        $ownerId,
        $catId,
        'TxTest Camera ' . bin2hex(random_bytes(3)),
        'Dedicated camera listing for transaction module test suite.',
        $rentPerDay,
        $depositAmount,
        'Bangalore',
        'Available',
        'New'
    );
    $testProd->save();
    $productId = (int) $testProd->getProductID();

    // -------------------------------------------------------------
    // Test 1: Payment Attempt on Unapproved Request Rejection
    // -------------------------------------------------------------
    $startDate1 = date('Y-m-d', strtotime('+40 days'));
    $endDate1 = date('Y-m-d', strtotime('+43 days')); // 3 days

    $renterModel = new Renter($renterId);
    $req1 = $renterModel->sendRequest($productId, $startDate1, $endDate1, 'Pending request test');
    $req1Id = (int) $req1->getRequestID();

    $unapprovedPaymentBlocked = false;
    try {
        $renterModel->makePayment($req1Id, 'UPI');
    } catch (PaymentFailedException $e) {
        if (str_contains($e->getMessage(), 'Approved')) {
            $unapprovedPaymentBlocked = true;
        }
    }

    recordTest(
        'Unapproved Request Payment Rejection',
        $unapprovedPaymentBlocked,
        $unapprovedPaymentBlocked 
            ? "PASS: Payment on Pending request #{$req1Id} was correctly rejected with PaymentFailedException." 
            : "FAIL: System allowed payment processing on an unapproved rental request."
    );

    // -------------------------------------------------------------
    // Test 2: Unauthorized Payer Prevention
    // -------------------------------------------------------------
    // Owner approves request #1
    $ownerModel = new Owner($ownerId);
    $ownerModel->manageRentalRequest($req1Id, 'approve');

    $unauthorizedPayerBlocked = false;
    try {
        // Other renter tries to pay for Priya's request
        $otherRenter = new Renter($otherRenterId);
        $otherRenter->makePayment($req1Id, 'UPI');
    } catch (UnauthorizedActionException $e) {
        $unauthorizedPayerBlocked = true;
    }

    recordTest(
        'Unauthorized Payer Access Control',
        $unauthorizedPayerBlocked,
        $unauthorizedPayerBlocked 
            ? "PASS: Payment attempt by unauthorized user (ID #{$otherRenterId}) rejected with UnauthorizedActionException." 
            : "FAIL: Unauthorized user was able to pay for someone else's booking."
    );

    // -------------------------------------------------------------
    // Test 3: Atomic Payment Execution & Transaction Record Insertion
    // -------------------------------------------------------------
    $tx = $renterModel->makePayment($req1Id, 'UPI');
    $txId = (int) $tx->getTransactionID();

    $txCheckStmt = $pdo->prepare("SELECT * FROM `TRANSACTION` WHERE transaction_id = :txid");
    $txCheckStmt->execute(['txid' => $txId]);
    $txRow = $txCheckStmt->fetch();

    $test3Pass = ($txRow && $txRow['payment_status'] === 'Completed' && $txRow['payment_mode'] === 'UPI');
    recordTest(
        'Atomic Payment Processing (Transaction Creation)',
        $test3Pass,
        $test3Pass 
            ? "PASS: Transaction #{$txId} inserted with payment_status='Completed', payment_mode='UPI'." 
            : "FAIL: Transaction record not found or status invalid."
    );

    // -------------------------------------------------------------
    // Test 4: Financial Snapshot Calculation & 3NF Integrity (Rule 7)
    // -------------------------------------------------------------
    $expectedDays = 3;
    $expectedRentAmount = round($expectedDays * $rentPerDay, 2); // 3 * 1500 = 4500
    $actualRentSnapshot = (float) $txRow['rental_amount'];
    $actualDepositSnapshot = (float) $txRow['deposit_amount'];

    $calcMatches = ($actualRentSnapshot === $expectedRentAmount) 
                && ($actualDepositSnapshot === $depositAmount) 
                && ($txRow['deposit_status'] === 'Held');

    recordTest(
        'Financial Snapshot Calculation & Deposit Escrow (Rule 7)',
        $calcMatches,
        $calcMatches 
            ? "PASS: Snapshot recorded: rental_amount=₹{$actualRentSnapshot} (3d × ₹{$rentPerDay}), deposit_amount=₹{$actualDepositSnapshot}, deposit_status='Held'." 
            : "FAIL: Snapshot mismatch: rent={$actualRentSnapshot} (expected {$expectedRentAmount}), deposit={$actualDepositSnapshot} (expected {$depositAmount})."
    );

    // -------------------------------------------------------------
    // Test 5: Status Transitions (Request -> Active, Product -> Rented)
    // -------------------------------------------------------------
    $reqStatusStmt = $pdo->prepare("SELECT status FROM `RENTAL_REQUEST` WHERE request_id = :rid");
    $reqStatusStmt->execute(['rid' => $req1Id]);
    $updatedReqStatus = $reqStatusStmt->fetchColumn();

    $prodStatusStmt = $pdo->prepare("SELECT avail_status FROM `PRODUCT` WHERE product_id = :pid");
    $prodStatusStmt->execute(['pid' => $productId]);
    $updatedProdStatus = $prodStatusStmt->fetchColumn();

    $transitionsPass = ($updatedReqStatus === 'Active' && $updatedProdStatus === 'Rented');
    recordTest(
        'Lifecycle Status Transitions (Request -> Active, Product -> Rented)',
        $transitionsPass,
        $transitionsPass 
            ? "PASS: RENTAL_REQUEST #{$req1Id} status='{$updatedReqStatus}' and PRODUCT #{$productId} avail_status='{$updatedProdStatus}'." 
            : "FAIL: Request status='{$updatedReqStatus}' (expected 'Active'), Product status='{$updatedProdStatus}' (expected 'Rented')."
    );

    // -------------------------------------------------------------
    // Test 6: Multi-Party Database Notifications (Rule 11)
    // -------------------------------------------------------------
    $ownerNotifStmt = $pdo->prepare("
        SELECT notif_id, message FROM `NOTIFICATION` 
        WHERE user_id = :uid AND type = 'Payment' AND related_id = :txid
        ORDER BY notif_id DESC LIMIT 1
    ");
    $ownerNotifStmt->execute(['uid' => $ownerId, 'txid' => $txId]);
    $ownerNotif = $ownerNotifStmt->fetch();

    $renterNotifStmt = $pdo->prepare("
        SELECT notif_id, message FROM `NOTIFICATION` 
        WHERE user_id = :uid AND type = 'Payment' AND related_id = :txid
        ORDER BY notif_id DESC LIMIT 1
    ");
    $renterNotifStmt->execute(['uid' => $renterId, 'txid' => $txId]);
    $renterNotif = $renterNotifStmt->fetch();

    $notifsPass = (!empty($ownerNotif) && !empty($renterNotif));
    recordTest(
        'Multi-Party Payment Notifications (Rule 11)',
        $notifsPass,
        $notifsPass 
            ? "PASS: Notifications sent. Owner Notif #{$ownerNotif['notif_id']} and Renter Notif #{$renterNotif['notif_id']} successfully recorded." 
            : "FAIL: Payment notifications missing for one or both parties."
    );

    // -------------------------------------------------------------
    // Test 7: Receipt Generation & Metadata Retrieval (getReceipt())
    // -------------------------------------------------------------
    $receipt = $tx->getReceipt();
    $expectedReceiptNo = 'ORMS-REC-' . str_pad((string)$txId, 6, '0', STR_PAD_LEFT);

    $receiptValid = (
        $receipt['receipt_number'] === $expectedReceiptNo &&
        (float)$receipt['rental_amount'] === $actualRentSnapshot &&
        (float)$receipt['deposit_amount'] === $actualDepositSnapshot &&
        (float)$receipt['total_paid'] === ($actualRentSnapshot + $actualDepositSnapshot) &&
        !empty($receipt['payer_name']) &&
        !empty($receipt['owner_name'])
    );

    recordTest(
        'Official Receipt Generation (getReceipt())',
        $receiptValid,
        $receiptValid 
            ? "PASS: Generated Receipt #{$receipt['receipt_number']} with total paid ₹{$receipt['total_paid']}." 
            : "FAIL: Receipt metadata incomplete or mismatched."
    );

    // -------------------------------------------------------------
    // Test 8: Deposit Refund Engine (Rule 8)
    // -------------------------------------------------------------
    // Test Clean Return: 100% refund
    $refundSuccess = $tx->refundDeposit(0.00);

    $depCheckStmt = $pdo->prepare("SELECT deposit_status FROM `TRANSACTION` WHERE transaction_id = :txid");
    $depCheckStmt->execute(['txid' => $txId]);
    $updatedDepositStatus = $depCheckStmt->fetchColumn();

    $test8Pass = ($refundSuccess && $updatedDepositStatus === 'Refunded');
    recordTest(
        'Security Deposit Refund Engine (Rule 8 Clean Return)',
        $test8Pass,
        $test8Pass 
            ? "PASS: Deposit status transitioned to 'Refunded'. Full refund of ₹{$actualDepositSnapshot} logged." 
            : "FAIL: Deposit status is '{$updatedDepositStatus}' (expected 'Refunded')."
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
    echo "  ORMS Automated Verification — Step 7: Transactions\n";
    echo "=======================================================\n";
    foreach ($results as $index => $res) {
        $num = $index + 1;
        $statusStr = $res['status'] ? "[ PASS ]" : "[ FAIL ]";
        echo "{$num}. {$statusStr} {$res['title']}\n";
        echo "   -> {$res['detail']}\n\n";
    }
    echo "-------------------------------------------------------\n";
    echo $allPassed ? "OVERALL RESULT: ALL 8 TESTS PASSED! Transaction & Payment module is 100% operational.\n" : "OVERALL RESULT: SOME TESTS FAILED.\n";
    echo "=======================================================\n";
    exit($allPassed ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORMS — Transaction Module Test Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto bg-slate-900 rounded-2xl shadow-2xl border border-slate-800 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 p-6">
            <h1 class="text-2xl font-bold tracking-wide">Online Rental Management System (ORMS)</h1>
            <p class="text-blue-200 text-sm mt-1">Step 7 Verification: Financial & Transaction Module</p>
        </div>

        <div class="p-6">
            <div class="mb-6 flex items-center justify-between p-4 rounded-xl <?= $allPassed ? 'bg-emerald-950/70 border border-emerald-500/50 text-emerald-300' : 'bg-rose-950/70 border border-rose-500/50 text-rose-300' ?>">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl"><?= $allPassed ? '✅' : '❌' ?></span>
                    <div>
                        <h2 class="font-semibold text-lg"><?= $allPassed ? 'All 8 Financial & Payment Tests Passed' : 'Some Tests Failed' ?></h2>
                        <p class="text-xs opacity-85"><?= $allPassed ? 'Payment checkout, 3NF snapshot amount, deposit holding, receipt invoice, and notifications verified.' : 'Review test output details below.' ?></p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $allPassed ? 'bg-emerald-500 text-black' : 'bg-rose-500 text-white' ?>">
                    <?= $allPassed ? 'Ready for Step 8' : 'Action Required' ?>
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
                <a href="<?= base_url('owner/earnings.php') ?>" class="text-blue-400 hover:text-blue-300 font-semibold">&larr; Go to Owner Earnings Ledger</a>
                <span>ORMS &bull; BCSP-064 &bull; Step 7 Complete</span>
            </div>
        </div>
    </div>
</body>
</html>
