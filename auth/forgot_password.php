<?php
/**
 * Online Rental Management System (ORMS)
 * Forgot Password Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 3) & Section 7 (Security Checklist)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/BaseUser.php';

require_guest();

$errors = [];
$successMessage = '';
$generatedResetLink = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security token invalid. Please try again.';
    }

    $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid registered email address.';
    }

    if (empty($errors)) {
        $rawToken = BaseUser::initiatePasswordReset($email);

        if ($rawToken) {
            // Build the reset URL
            $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $resetUrl = "{$protocol}://{$host}" . base_url("auth/reset_password.php?token={$rawToken}");

            $generatedResetLink = $resetUrl;
            $successMessage = 'A password reset token has been generated. The link expires in 1 hour.';
        } else {
            // For security, do not explicitly reveal whether the email exists
            $successMessage = 'If an active account exists for that email address, a password reset link has been processed.';
        }
    }
}

$pageTitle = 'Forgot Password — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="py-16 sm:py-24 px-4 sm:px-6 lg:px-8 max-w-md mx-auto">
    <div class="bg-white border border-stone-200/80 rounded-3xl shadow-soft overflow-hidden">
        <!-- Header Banner -->
        <div class="p-8 pb-6 text-center border-b border-stone-100 bg-[#FAF8F5]">
            <img src="<?= base_url('assets/img/ORMS Logo.png') ?>" alt="ORMS" class="h-12 sm:h-14 mx-auto mb-4 object-contain">
            <h2 class="font-display text-2xl font-bold text-midnight tracking-tight">Reset Password</h2>
            <p class="mt-1 text-xs text-stone-500">Enter your registered email to receive a secure password reset link</p>
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

            <!-- Success Notification -->
            <?php if (!empty($successMessage)): ?>
                <div class="mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-800 text-xs space-y-3">
                    <div class="flex items-center space-x-2 font-semibold text-emerald-900">
                        <i class="ri-checkbox-circle-fill text-emerald-600 text-base flex-shrink-0"></i>
                        <span><?= htmlspecialchars($successMessage) ?></span>
                    </div>

                    <?php if (!empty($generatedResetLink)): ?>
                        <div class="p-3.5 bg-[#FAF8F5] rounded-2xl border border-stone-200/80 text-stone-700 space-y-2">
                            <span class="block text-[11px] text-stone-500 font-semibold uppercase tracking-wider">
                                Direct Reset Link:
                            </span>
                            <a href="<?= htmlspecialchars($generatedResetLink) ?>" class="text-xs text-coral hover:text-coral-600 break-all underline font-medium block">
                                <?= htmlspecialchars($generatedResetLink) ?>
                            </a>
                            <div class="pt-2">
                                <a href="<?= htmlspecialchars($generatedResetLink) ?>" class="inline-flex items-center space-x-1.5 px-4 py-2 bg-coral hover:bg-coral-600 text-white rounded-full text-xs font-semibold shadow-glow-coral transition">
                                    <span>Reset Password Now</span>
                                    <i class="ri-arrow-right-line"></i>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-2">
                        Registered Email Address
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                            <i class="ri-mail-line text-base"></i>
                        </span>
                        <input type="email" id="email" name="email" required autofocus
                               value="<?= htmlspecialchars($email) ?>"
                               placeholder="you@example.com"
                               class="w-full bg-white border border-stone-200/90 rounded-2xl pl-10 pr-4 py-3 text-sm text-midnight placeholder-stone-400 focus:outline-none focus:border-coral focus:ring-4 focus:ring-coral-50 transition font-medium">
                    </div>
                </div>

                <div class="pt-2">
                    <button type="submit" 
                            class="w-full bg-coral hover:bg-coral-600 text-white font-semibold py-3.5 px-6 rounded-full shadow-glow-coral transition duration-150 transform hover:-translate-y-0.5">
                        Generate Reset Link &rarr;
                    </button>
                </div>
            </form>

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
