<?php
/**
 * Online Rental Management System (ORMS)
 * Reset Password Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 3) & Section 7 (Security Checklist)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_guest();

$errors = [];
$token = $_GET['token'] ?? $_POST['token'] ?? '';
$isValidToken = false;
$userRecord = null;

if (empty($token)) {
    $errors[] = 'Password reset token is missing. Please initiate a reset request.';
} else {
    $pdo = Database::getInstance()->getConnection();
    $hashedToken = hash('sha256', $token);

    // Verify token validity and expiry (1 hour window)
    $stmt = $pdo->prepare("
        SELECT user_id, name, email 
        FROM `USER` 
        WHERE reset_token = :token 
          AND reset_token_expiry > NOW() 
        LIMIT 1
    ");
    $stmt->execute(['token' => $hashedToken]);
    $userRecord = $stmt->fetch();

    if ($userRecord) {
        $isValidToken = true;
    } else {
        $errors[] = 'This password reset link is invalid, has expired (1-hour limit), or has already been used.';
    }
}

// Handle Password Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValidToken) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please try again.';
    }

    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    if (strlen($newPassword) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($newPassword !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $pdo = Database::getInstance()->getConnection();
        $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

        // Update password and invalidate the token (single-use enforcement)
        $updateStmt = $pdo->prepare("
            UPDATE `USER` 
            SET password = :password, 
                reset_token = NULL, 
                reset_token_expiry = NULL 
            WHERE user_id = :user_id
        ");
        $updateStmt->execute([
            'password' => $hashedPassword,
            'user_id' => $userRecord['user_id']
        ]);

        set_flash('success', 'Your password has been reset successfully! Please sign in with your new password.');
        header("Location: " . base_url('auth/login.php'));
        exit;
    }
}

$pageTitle = 'Choose New Password — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="py-16 px-4 sm:px-6 lg:px-8 max-w-md mx-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden">
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-8 text-center border-b border-slate-800">
            <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md mx-auto flex items-center justify-center mb-3 shadow-inner">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Set New Password</h2>
            <p class="mt-1 text-xs text-blue-200">
                <?= $isValidToken ? 'Resetting password for: ' . htmlspecialchars($userRecord['email']) : 'Invalid Link' ?>
            </p>
        </div>

        <div class="p-8">
            <!-- Errors Alert -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-600/70 text-rose-200 text-xs space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <div class="flex items-center space-x-2">
                            <span>⚠️</span>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if ($isValidToken): ?>
                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>?token=<?= urlencode($token) ?>" method="POST" class="space-y-5">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                    <div>
                        <label for="new_password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            New Password <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" id="new_password" name="new_password" required minlength="6" autofocus
                               placeholder="Minimum 6 characters"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Confirm New Password <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                               placeholder="Re-enter new password"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>

                    <div>
                        <button type="submit" 
                                class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-blue-500/25 transition duration-150">
                            Update Password
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center space-y-4">
                    <p class="text-xs text-slate-400">
                        Please request a fresh password reset link from the forgot password page.
                    </p>
                    <a href="<?= base_url('auth/forgot_password.php') ?>" class="inline-block px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition">
                        Request New Reset Link
                    </a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-6 pt-4 border-t border-slate-800">
                <a href="<?= base_url('auth/login.php') ?>" class="text-xs text-slate-400 hover:text-white transition">
                    &larr; Back to Sign In
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
