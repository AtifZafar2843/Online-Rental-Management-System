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

<div class="py-16 px-4 sm:px-6 lg:px-8 max-w-md mx-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden">
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-8 text-center border-b border-slate-800">
            <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md mx-auto flex items-center justify-center mb-3 shadow-inner">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Reset Password</h2>
            <p class="mt-1 text-xs text-blue-200">Enter your email to receive a secure reset link</p>
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

            <!-- Success Notification -->
            <?php if (!empty($successMessage)): ?>
                <div class="mb-6 p-4 rounded-xl bg-emerald-950/80 border border-emerald-600/70 text-emerald-200 text-xs space-y-3">
                    <div class="flex items-center space-x-2 font-semibold text-emerald-300">
                        <span>✅</span>
                        <span><?= htmlspecialchars($successMessage) ?></span>
                    </div>

                    <?php if (!empty($generatedResetLink)): ?>
                        <div class="p-3 bg-slate-950 rounded-lg border border-slate-800 text-slate-300 space-y-2">
                            <span class="block text-[11px] text-blue-400 font-semibold uppercase tracking-wider">
                                Direct Reset Link (Testing Simulation):
                            </span>
                            <a href="<?= htmlspecialchars($generatedResetLink) ?>" class="text-xs text-indigo-400 hover:text-indigo-300 break-all underline">
                                <?= htmlspecialchars($generatedResetLink) ?>
                            </a>
                            <div class="pt-2">
                                <a href="<?= htmlspecialchars($generatedResetLink) ?>" class="inline-block px-3 py-1.5 bg-blue-600 hover:bg-blue-500 text-white rounded-lg text-xs font-semibold transition">
                                    Click here to Reset Password Now &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="space-y-5">
                <?= csrf_field() ?>

                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Registered Email Address
                    </label>
                    <input type="email" id="email" name="email" required autofocus
                           value="<?= htmlspecialchars($email) ?>"
                           placeholder="you@example.com"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <div>
                    <button type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-blue-500/25 transition duration-150">
                        Generate Reset Link
                    </button>
                </div>
            </form>

            <div class="text-center mt-6 pt-4 border-t border-slate-800">
                <a href="<?= base_url('auth/login.php') ?>" class="text-xs text-slate-400 hover:text-white transition">
                    &larr; Back to Sign In
                </a>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
