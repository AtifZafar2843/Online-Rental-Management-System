<?php
/**
 * Online Rental Management System (ORMS)
 * Database Connection Verification Script (Step 2 Verification)
 * 
 * Accessible via CLI: php test_db.php
 * Accessible via Browser: http://localhost/orms/test_db.php
 */

declare(strict_types=1);

// Set appropriate content type
$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/config/database.php';

$results = [];
$allPassed = true;

function recordTest(string $title, bool $status, string $detail): void {
    global $results, $allPassed;
    if (!$status) {
        $allPassed = false;
    }
    $results[] = [
        'title' => $title,
        'status' => $status,
        'detail' => $detail
    ];
}

try {
    // Test 1: Singleton Instance Creation
    $db1 = Database::getInstance();
    $db2 = Database::getInstance();
    $isSingleton = ($db1 === $db2);
    recordTest(
        'Database Singleton Pattern Test',
        $isSingleton,
        $isSingleton ? 'PASS: Database::getInstance() returns identical instance reference.' : 'FAIL: Instances are not identical.'
    );

    // Test 2: Active PDO Connection Retrieval
    $pdo1 = $db1->getConnection();
    $pdo2 = $db2->getConnection();
    $isSameConnection = ($pdo1 === $pdo2 && $pdo1 instanceof PDO);
    recordTest(
        'PDO Connection Retrieval Test',
        $isSameConnection,
        $isSameConnection ? 'PASS: PDO connection initialized successfully with same connection handle.' : 'FAIL: PDO connection failed.'
    );

    // Test 3: Character Set Verification
    $charsetStmt = $pdo1->query("SELECT @@character_set_connection AS conn_charset, @@collation_connection AS conn_collation");
    $charsetInfo = $charsetStmt->fetch();
    $isUtf8 = (stripos($charsetInfo['conn_charset'], 'utf8') !== false);
    recordTest(
        'Connection Charset & Collation Test',
        $isUtf8,
        "PASS: Character set: {$charsetInfo['conn_charset']}, Collation: {$charsetInfo['conn_collation']}."
    );

    // Test 4: MySQL Server Version & Attributes
    $serverVersion = $pdo1->getAttribute(PDO::ATTR_SERVER_VERSION);
    $driverName = $pdo1->getAttribute(PDO::ATTR_DRIVER_NAME);
    $emulatePrepares = $pdo1->getAttribute(PDO::ATTR_EMULATE_PREPARES);
    $isSecurityConfigured = ($driverName === 'mysql' && empty($emulatePrepares));
    recordTest(
        'Security & Prepared Statement Settings',
        $isSecurityConfigured,
        ($isSecurityConfigured ? 'PASS' : 'FAIL') . ": Driver: {$driverName}, Version: {$serverVersion}, Emulated Prepares: " . ($emulatePrepares ? 'ENABLED (INSECURE)' : 'DISABLED (SECURE - NATIVE PDO PREPARES)')
    );

    // Test 5: Table Count & Data Verification
    $tablesStmt = $pdo1->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
    $tables = $tablesStmt->fetchAll(PDO::FETCH_COLUMN);
    $tableCount = count($tables);
    $has12Tables = ($tableCount === 12);
    recordTest(
        'Database Schema & Table Verification',
        $has12Tables,
        ($has12Tables ? "PASS: Found exactly 12 tables in orms_db." : "FAIL: Expected 12 tables, found {$tableCount}.") . " Tables: " . implode(', ', $tables)
    );

    // Test 6: Seed Admin Verification via Prepared Statement
    $adminStmt = $pdo1->prepare("SELECT admin_id, username, email, full_name, status FROM `ADMIN` WHERE username = :username");
    $adminStmt->execute(['username' => 'admin']);
    $admin = $adminStmt->fetch();
    $adminExists = ($admin !== false && $admin['email'] === 'admin@orms.com');
    recordTest(
        'Seed Admin Account Check (Prepared Query)',
        $adminExists,
        $adminExists ? "PASS: Seed admin found: {$admin['full_name']} ({$admin['email']}), Status: {$admin['status']}." : "FAIL: Seed admin account not found."
    );

    // Test 7: Seed Categories Verification
    $catStmt = $pdo1->query("SELECT category_name FROM `CATEGORY` ORDER BY category_id");
    $categories = $catStmt->fetchAll(PDO::FETCH_COLUMN);
    $hasCategories = (count($categories) === 3);
    recordTest(
        'Seed Categories Check',
        $hasCategories,
        $hasCategories ? "PASS: 3 Seed categories found: " . implode(', ', $categories) . "." : "FAIL: Categories missing or count mismatch."
    );

    // Test 8: Seed Users & Roles Verification
    $userStmt = $pdo1->query("
        SELECT u.email, GROUP_CONCAT(ur.role ORDER BY ur.role SEPARATOR ', ') AS roles 
        FROM `USER` u 
        JOIN `USER_ROLES` ur ON u.user_id = ur.user_id 
        GROUP BY u.user_id 
        ORDER BY u.user_id
    ");
    $userRoles = $userStmt->fetchAll();
    $hasUsers = (count($userRoles) >= 2);
    $userDetails = [];
    foreach ($userRoles as $ur) {
        $userDetails[] = "{$ur['email']} [{$ur['roles']}]";
    }
    recordTest(
        'Seed Users & Multi-Role Junction Check',
        $hasUsers,
        $hasUsers ? "PASS: Users found: " . implode('; ', $userDetails) . "." : "FAIL: Seed users missing."
    );

} catch (Throwable $e) {
    recordTest(
        'Database Connection Exception',
        false,
        'CRITICAL ERROR: ' . $e->getMessage()
    );
}

// Render Results
if ($isCli) {
    echo "\n=======================================================\n";
    echo "  ORMS Database Connection Test (Step 2 Verification)\n";
    echo "=======================================================\n\n";
    foreach ($results as $i => $res) {
        $badge = $res['status'] ? "[ PASS ]" : "[ FAIL ]";
        echo ($i + 1) . ". {$badge} {$res['title']}\n";
        echo "   -> {$res['detail']}\n\n";
    }
    echo "-------------------------------------------------------\n";
    echo $allPassed ? "OVERALL RESULT: ALL TESTS PASSED! Database connection is 100% operational.\n" : "OVERALL RESULT: SOME TESTS FAILED. Please review the errors above.\n";
    echo "=======================================================\n";
    exit($allPassed ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORMS — Database Connection Test</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-900 text-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto bg-slate-800 rounded-xl shadow-2xl border border-slate-700 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-6">
            <h1 class="text-2xl font-bold tracking-wide">Online Rental Management System (ORMS)</h1>
            <p class="text-blue-200 text-sm mt-1">Step 2 Verification: PDO Database Connection (Singleton Pattern)</p>
        </div>

        <div class="p-6">
            <div class="mb-6 flex items-center justify-between p-4 rounded-lg <?= $allPassed ? 'bg-emerald-950/70 border border-emerald-500/50 text-emerald-300' : 'bg-rose-950/70 border border-rose-500/50 text-rose-300' ?>">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl"><?= $allPassed ? '✅' : '❌' ?></span>
                    <div>
                        <h2 class="font-semibold text-lg"><?= $allPassed ? 'All Connection Tests Passed Successfully' : 'Some Tests Failed' ?></h2>
                        <p class="text-xs opacity-85"><?= $allPassed ? 'Database singleton connection is fully configured and ready for Module 3 (Auth).' : 'Check MySQL service or credentials.' ?></p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $allPassed ? 'bg-emerald-500 text-black' : 'bg-rose-500 text-white' ?>">
                    <?= $allPassed ? 'Ready for Step 3' : 'Action Required' ?>
                </span>
            </div>

            <div class="space-y-4">
                <?php foreach ($results as $index => $res): ?>
                    <div class="border border-slate-700/80 bg-slate-850 p-4 rounded-lg">
                        <div class="flex items-center justify-between">
                            <span class="font-medium text-slate-200"><?= ($index + 1) . '. ' . htmlspecialchars($res['title']) ?></span>
                            <span class="px-2.5 py-0.5 rounded text-xs font-semibold <?= $res['status'] ? 'bg-emerald-900/60 text-emerald-400 border border-emerald-700/50' : 'bg-rose-900/60 text-rose-400 border border-rose-700/50' ?>">
                                <?= $res['status'] ? 'PASS' : 'FAIL' ?>
                            </span>
                        </div>
                        <p class="mt-2 text-xs text-slate-400 font-mono bg-slate-900/70 p-2.5 rounded border border-slate-800">
                            <?= htmlspecialchars($res['detail']) ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-8 pt-6 border-t border-slate-700 flex justify-between items-center text-xs text-slate-400">
                <span>ORMS &bull; BCSP-064 &bull; Atif Zafar</span>
                <span>PHP <?= PHP_VERSION ?> &bull; PDO MySQL</span>
            </div>
        </div>
    </div>
</body>
</html>
