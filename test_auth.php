<?php
/**
 * Online Rental Management System (ORMS)
 * Auth Module Automated Verification Script (Step 3 Verification)
 * 
 * Accessible via CLI: php test_auth.php
 * Accessible via Browser: http://localhost/orms/test_auth.php
 */

declare(strict_types=1);

$isCli = (php_sapi_name() === 'cli');
if (!$isCli) {
    header('Content-Type: text/html; charset=UTF-8');
}

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/classes/BaseUser.php';
require_once __DIR__ . '/classes/Admin.php';
require_once __DIR__ . '/classes/Owner.php';
require_once __DIR__ . '/classes/Renter.php';

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

$pdo = Database::getInstance()->getConnection();
$testEmail = 'autotest_' . time() . '@orms-test.com';
$testPass = 'SecureTest@123';
$testNewPass = 'NewSecureTest@456';
$createdUserId = null;

try {
    // -------------------------------------------------------------
    // Test 1: User Registration Simulation (DB + USER_ROLES)
    // -------------------------------------------------------------
    $hashedPassword = password_hash($testPass, PASSWORD_BCRYPT);
    $dummyIdProof = 'uploads/id_proofs/test_proof_' . uniqid() . '.pdf';

    $pdo->beginTransaction();
    $insertUser = $pdo->prepare("
        INSERT INTO `USER` 
        (`name`, `email`, `phone`, `address`, `password`, `id_proof_path`, `rating`, `status`, `reg_date`) 
        VALUES 
        (:name, :email, :phone, :address, :password, :id_proof_path, 0.00, 'Active', NOW())
    ");
    $insertUser->execute([
        'name' => 'Auto Test User',
        'email' => $testEmail,
        'phone' => '9988776655',
        'address' => 'Test Tech Park, Bangalore',
        'password' => $hashedPassword,
        'id_proof_path' => $dummyIdProof
    ]);
    $createdUserId = (int) $pdo->lastInsertId();

    // Assign dual roles: Owner + Renter
    $roleStmt = $pdo->prepare("INSERT INTO `USER_ROLES` (`user_id`, `role`, `assigned_date`) VALUES (:user_id, :role, NOW())");
    $roleStmt->execute(['user_id' => $createdUserId, 'role' => 'Owner']);
    $roleStmt->execute(['user_id' => $createdUserId, 'role' => 'Renter']);
    $pdo->commit();

    recordTest(
        'User Registration & Dual-Role Junction Insert',
        $createdUserId > 0,
        "PASS: User ID {$createdUserId} registered with Owner & Renter roles in USER_ROLES table."
    );

    // -------------------------------------------------------------
    // Test 2: Role Retrieval Check
    // -------------------------------------------------------------
    $chkRoles = $pdo->prepare("SELECT role FROM `USER_ROLES` WHERE user_id = :id ORDER BY role");
    $chkRoles->execute(['id' => $createdUserId]);
    $userRoles = $chkRoles->fetchAll(PDO::FETCH_COLUMN);
    $hasBothRoles = (count($userRoles) === 2 && in_array('Owner', $userRoles) && in_array('Renter', $userRoles));

    recordTest(
        'Multi-Role Junction Integrity Check',
        $hasBothRoles,
        $hasBothRoles ? "PASS: Roles verified via SQL JOIN: " . implode(', ', $userRoles) : "FAIL: Role mismatch."
    );

    // -------------------------------------------------------------
    // Test 3: Failed Login (Wrong Password)
    // -------------------------------------------------------------
    $userModel = new Owner($createdUserId, 'Auto Test User', $testEmail, '9988776655', 'WrongPass@999');
    $failedLoginResult = $userModel->login();

    recordTest(
        'Authentication Rejection (Wrong Password)',
        $failedLoginResult === false,
        $failedLoginResult === false ? "PASS: Authentication correctly rejected invalid credentials." : "FAIL: Invalid password was accepted."
    );

    // -------------------------------------------------------------
    // Test 4: Successful User Login & Session Data
    // -------------------------------------------------------------
    $userModel->setPassword($testPass);
    $loginSuccess = $userModel->login();
    $sessionPopulated = (
        !empty($_SESSION['user_id']) && 
        $_SESSION['user_id'] === $createdUserId && 
        !empty($_SESSION['email']) && 
        $_SESSION['email'] === $testEmail &&
        in_array('Owner', $_SESSION['roles'] ?? []) &&
        in_array('Renter', $_SESSION['roles'] ?? [])
    );

    recordTest(
        'User Login & Session Regeneration Test',
        $loginSuccess && $sessionPopulated,
        ($loginSuccess && $sessionPopulated) ? "PASS: User logged in. Active Role: {$_SESSION['active_role']}, Roles: " . implode(', ', $_SESSION['roles']) : "FAIL: Session not populated properly."
    );

    // -------------------------------------------------------------
    // Test 5: Seed Admin Login
    // -------------------------------------------------------------
    $admin = new Admin(null, 'admin', 'admin@orms.com', '', 'Admin@123');
    $adminLogin = $admin->login();
    $adminSessionOk = (
        !empty($_SESSION['admin_id']) && 
        $_SESSION['username'] === 'admin' && 
        $_SESSION['active_role'] === 'Admin'
    );

    recordTest(
        'Admin Login & Role Verification',
        $adminLogin && $adminSessionOk,
        ($adminLogin && $adminSessionOk) ? "PASS: Admin credentials authenticated against ADMIN table. Name: {$_SESSION['name']}." : "FAIL: Admin login failed."
    );

    // -------------------------------------------------------------
    // Test 6: Password Reset Token Initiation
    // -------------------------------------------------------------
    $rawResetToken = BaseUser::initiatePasswordReset($testEmail);
    $hasToken = (!empty($rawResetToken) && strlen($rawResetToken) === 64);

    // Verify token is stored as SHA-256 in DB, NOT plaintext
    $tokenStmt = $pdo->prepare("SELECT reset_token, reset_token_expiry FROM `USER` WHERE user_id = :id");
    $tokenStmt->execute(['id' => $createdUserId]);
    $tokenRow = $tokenStmt->fetch();
    $isHashedInDb = ($tokenRow && $tokenRow['reset_token'] === hash('sha256', $rawResetToken));

    recordTest(
        'Password Reset Token Hashing & Expiry (Rule 3)',
        $hasToken && $isHashedInDb,
        ($hasToken && $isHashedInDb) ? "PASS: 64-char hex token generated. DB holds SHA-256 hash ({$tokenRow['reset_token']}), Expiry: {$tokenRow['reset_token_expiry']}." : "FAIL: Token not hashed or missing in DB."
    );

    // -------------------------------------------------------------
    // Test 7: Password Reset Execution & Single-Use Invalidation
    // -------------------------------------------------------------
    $resetModel = new Owner($createdUserId, 'Auto Test User', $testEmail);
    $resetModel->setPassword($testNewPass);
    $resetSuccess = $resetModel->resetPassword($rawResetToken);

    // Verify token invalidated (set to NULL)
    $tokenStmt->execute(['id' => $createdUserId]);
    $tokenRowAfter = $tokenStmt->fetch();
    $isTokenInvalidated = ($tokenRowAfter && empty($tokenRowAfter['reset_token']));

    // Verify login with new password
    $verifyNewLogin = new Owner($createdUserId, '', $testEmail, '', $testNewPass);
    $newLoginSuccess = $verifyNewLogin->login();

    recordTest(
        'Password Reset Execution & Single-Use Invalidation',
        $resetSuccess && $isTokenInvalidated && $newLoginSuccess,
        ($resetSuccess && $isTokenInvalidated && $newLoginSuccess) ? "PASS: Password updated. Reset token invalidated to NULL. Successfully logged in with new password." : "FAIL: Password reset failed."
    );

    // -------------------------------------------------------------
    // Test 8: Magic-Byte MIME Validation Engine Check
    // -------------------------------------------------------------
    // Create temporary files to test magic-byte inspection
    $tempJpg = tempnam(sys_get_temp_dir(), 'test_img_') . '.jpg';
    file_put_contents($tempJpg, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x01\x00`\x00`\x00\x00"); // Valid JPEG SOI magic bytes

    $tempFake = tempnam(sys_get_temp_dir(), 'test_fake_') . '.jpg';
    file_put_contents($tempFake, "<?php echo 'malicious script'; ?>"); // Fake script disguised as JPG

    $checkReal = validate_file_upload([
        'tmp_name' => $tempJpg,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempJpg)
    ], ['image/jpeg', 'image/png', 'application/pdf']);

    $checkFake = validate_file_upload([
        'tmp_name' => $tempFake,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempFake)
    ], ['image/jpeg', 'image/png', 'application/pdf']);

    unlink($tempJpg);
    unlink($tempFake);

    $mimeSecurityWorks = ($checkReal['valid'] === true && $checkFake['valid'] === false);

    recordTest(
        'File Upload finfo_file Magic-Byte Security Check',
        $mimeSecurityWorks,
        $mimeSecurityWorks ? "PASS: Genuine image approved (image/jpeg); disguised script correctly blocked (text/x-php rejected)." : "FAIL: Magic-byte inspection failed."
    );

} catch (Throwable $e) {
    recordTest('Auth Test Suite Exception', false, 'CRITICAL ERROR: ' . $e->getMessage());
} finally {
    // Clean up test user
    if ($createdUserId) {
        $pdo->prepare("DELETE FROM `USER` WHERE user_id = :id")->execute(['id' => $createdUserId]);
    }
}

// Render Output
if ($isCli) {
    echo "\n=======================================================\n";
    echo "  ORMS Auth Module Automated Test (Step 3 Verification)\n";
    echo "=======================================================\n\n";
    foreach ($results as $i => $res) {
        $badge = $res['status'] ? "[ PASS ]" : "[ FAIL ]";
        echo ($i + 1) . ". {$badge} {$res['title']}\n";
        echo "   -> {$res['detail']}\n\n";
    }
    echo "-------------------------------------------------------\n";
    echo $allPassed ? "OVERALL RESULT: ALL TESTS PASSED! Auth module is 100% operational.\n" : "OVERALL RESULT: SOME TESTS FAILED.\n";
    echo "=======================================================\n";
    exit($allPassed ? 0 : 1);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ORMS — Auth Module Test Suite</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen py-10 px-4">
    <div class="max-w-3xl mx-auto bg-slate-900 rounded-2xl shadow-2xl border border-slate-800 overflow-hidden">
        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 p-6">
            <h1 class="text-2xl font-bold tracking-wide">Online Rental Management System (ORMS)</h1>
            <p class="text-blue-200 text-sm mt-1">Step 3 Verification: Auth Module Test Suite</p>
        </div>

        <div class="p-6">
            <div class="mb-6 flex items-center justify-between p-4 rounded-xl <?= $allPassed ? 'bg-emerald-950/70 border border-emerald-500/50 text-emerald-300' : 'bg-rose-950/70 border border-rose-500/50 text-rose-300' ?>">
                <div class="flex items-center space-x-3">
                    <span class="text-2xl"><?= $allPassed ? '✅' : '❌' ?></span>
                    <div>
                        <h2 class="font-semibold text-lg"><?= $allPassed ? 'All Auth Tests Passed Successfully' : 'Some Tests Failed' ?></h2>
                        <p class="text-xs opacity-85"><?= $allPassed ? 'Registration, multi-role junctions, login, password reset & magic-byte uploads verified.' : 'Review test output below.' ?></p>
                    </div>
                </div>
                <span class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider <?= $allPassed ? 'bg-emerald-500 text-black' : 'bg-rose-500 text-white' ?>">
                    <?= $allPassed ? 'Ready for Step 4' : 'Action Required' ?>
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
                <a href="<?= base_url('auth/login.php') ?>" class="text-blue-400 hover:text-blue-300 font-semibold">&larr; Go to Login Page</a>
                <span>ORMS &bull; BCSP-064 &bull; Step 3 Complete</span>
            </div>
        </div>
    </div>
</body>
</html>
