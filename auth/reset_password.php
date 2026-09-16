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

<div class="py-16 sm:py-24 px-4 sm:px-6 lg:px-8 max-w-md mx-auto">
    <div class="bg-white border border-stone-200/80 rounded-3xl shadow-soft overflow-hidden">
        <!-- Header Banner -->
        <div class="p-8 pb-6 text-center border-b border-stone-100 bg-[#FAF8F5]">
            <img src="<?= base_url('assets/img/ORMS Logo.png') ?>" alt="ORMS" class="h-12 sm:h-14 mx-auto mb-4 object-contain">
            <h2 class="font-display text-2xl font-bold text-midnight tracking-tight">Set New Password</h2>
            <p class="mt-1 text-xs text-stone-500">
                <?= $isValidToken ? 'Resetting password for: <strong class="text-midnight">' . htmlspecialchars($userRecord['email']) . '</strong>' : 'Password Reset Verification' ?>
            </p>
        </div>

        <div class="p-8">
            <!-- Errors Alert -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-800 text-xs space-y-1">
                    <?php foreach ($errors as $error): ?>
                        <div class="flex items-center space-x-2">
                            <i class="ri-error-warning-fill text-rose-500 text-base flex-shrink-0"></i>
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
                        <label for="new_password" class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-2">
                            New Password <span class="text-coral">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                                <i class="ri-lock-password-line text-base"></i>
                            </span>
                            <input type="password" id="new_password" name="new_password" required minlength="6" autofocus
                                   placeholder="Minimum 6 characters"
                                   class="w-full bg-white border border-stone-200/90 rounded-2xl pl-10 pr-4 py-3 text-sm text-midnight placeholder-stone-400 focus:outline-none focus:border-coral focus:ring-4 focus:ring-coral-50 transition font-medium">
                        </div>
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-2">
                            Confirm New Password <span class="text-coral">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                                <i class="ri-lock-check-line text-base"></i>
                            </span>
                            <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                                   placeholder="Re-enter new password"
                                   class="w-full bg-white border border-stone-200/90 rounded-2xl pl-10 pr-4 py-3 text-sm text-midnight placeholder-stone-400 focus:outline-none focus:border-coral focus:ring-4 focus:ring-coral-50 transition font-medium">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit" 
                                class="w-full bg-coral hover:bg-coral-600 text-white font-semibold py-3.5 px-6 rounded-full shadow-glow-coral transition duration-150 transform hover:-translate-y-0.5">
                            Update Password &rarr;
                        </button>
                    </div>
                </form>
            <?php else: ?>
                <div class="text-center space-y-4 py-4">
                    <div class="w-12 h-12 rounded-full bg-rose-50 text-rose-500 mx-auto flex items-center justify-center text-2xl">
                        <i class="ri-close-circle-line"></i>
                    </div>
                    <p class="text-xs text-stone-500">
                        Please request a fresh password reset link from the forgot password page.
                    </p>
                    <a href="<?= base_url('auth/forgot_password.php') ?>" class="inline-flex items-center space-x-1.5 px-5 py-2.5 bg-coral hover:bg-coral-600 text-white text-xs font-semibold rounded-full shadow-glow-coral transition">
                        <span>Request New Link</span>
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-6 pt-4 border-t border-stone-100">
                <a href="<?= base_url('auth/login.php') ?>" class="text-xs text-stone-500 hover:text-midnight transition inline-flex items-center space-x-1 font-medium">
                    <i class="ri-arrow-left-line"></i>
                    <span>Back to Sign In</span>
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
