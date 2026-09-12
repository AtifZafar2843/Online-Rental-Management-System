<?php
/**
 * Online Rental Management System (ORMS)
 * User & Administrator Login Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 2) & Section 7 (Security Checklist)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/BaseUser.php';
require_once __DIR__ . '/../classes/Admin.php';

require_guest();

$errors = [];
$identifier = '';
$redirect = $_GET['redirect'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security verification failed (Invalid CSRF token). Please try again.';
    }

    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $redirect = trim($_POST['redirect'] ?? '');

    if (empty($identifier) || empty($password)) {
        $errors[] = 'Please enter both your email/username and password.';
    }

    if (empty($errors)) {
        $pdo = Database::getInstance()->getConnection();
        $authenticated = false;

        // A. Attempt Admin Authentication (ADMIN table)
        $adminStmt = $pdo->prepare("
            SELECT admin_id, username, email, password, full_name, status 
            FROM `ADMIN` 
            WHERE username = :admin_uname OR email = :admin_email 
            LIMIT 1
        ");
        $adminStmt->execute(['admin_uname' => $identifier, 'admin_email' => $identifier]);
        $adminData = $adminStmt->fetch();

        if ($adminData && password_verify($password, $adminData['password'])) {
            if ($adminData['status'] !== 'Active') {
                $errors[] = 'This administrator account is currently inactive.';
            } else {
                // Regenerate session ID on login to prevent session fixation (Section 7)
                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $adminData['admin_id'];
                $_SESSION['name'] = $adminData['full_name'];
                $_SESSION['username'] = $adminData['username'];
                $_SESSION['email'] = $adminData['email'];
                $_SESSION['roles'] = ['Admin'];
                $_SESSION['active_role'] = 'Admin';

                set_flash('success', "Welcome back, Administrator {$adminData['full_name']}!");
                header("Location: " . base_url('admin/dashboard.php'));
                exit;
            }
            $authenticated = true;
        }

        // B. Attempt Standard User Authentication (USER & USER_ROLES tables)
        if (!$authenticated && empty($errors)) {
            $userStmt = $pdo->prepare("
                SELECT user_id, name, email, password, status 
                FROM `USER` 
                WHERE email = :email 
                LIMIT 1
            ");
            $userStmt->execute(['email' => $identifier]);
            $userData = $userStmt->fetch();

            if ($userData && password_verify($password, $userData['password'])) {
                if ($userData['status'] === 'Banned') {
                    $errors[] = 'Your account has been suspended. Please contact customer support.';
                } elseif ($userData['status'] === 'Inactive') {
                    $errors[] = 'Your account is inactive. Please contact support.';
                } else {
                    // Fetch roles from USER_ROLES junction table
                    $roleStmt = $pdo->prepare("SELECT role FROM `USER_ROLES` WHERE user_id = :user_id ORDER BY role");
                    $roleStmt->execute(['user_id' => $userData['user_id']]);
                    $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

                    if (empty($roles)) {
                        $roles = ['Renter']; // Default fallback
                    }

                    // Regenerate session ID (Security checklist)
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $userData['user_id'];
                    $_SESSION['name'] = $userData['name'];
                    $_SESSION['email'] = $userData['email'];
                    $_SESSION['roles'] = $roles;

                    // Choose default active role (prefer Owner if user has both, else first role)
                    $_SESSION['active_role'] = in_array('Owner', $roles, true) ? 'Owner' : $roles[0];

                    set_flash('success', "Welcome back, {$userData['name']}!");

                    // Handle safe redirection
                    if (!empty($redirect) && str_starts_with($redirect, '/orms/')) {
                        header("Location: {$redirect}");
                    } elseif ($_SESSION['active_role'] === 'Owner') {
                        header("Location: " . base_url('owner/dashboard.php'));
                    } else {
                        header("Location: " . base_url('renter/dashboard.php'));
                    }
                    exit;
                }
                $authenticated = true;
            }
        }

        // C. Generic error message on failure (Rule 2: do not reveal email vs password mismatch)
        if (!$authenticated && empty($errors)) {
            $errors[] = 'Invalid email/username or password.';
        }
    }
}

$pageTitle = 'Sign In — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="py-16 px-4 sm:px-6 lg:px-8 max-w-md mx-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-2xl overflow-hidden">
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-blue-700 to-indigo-800 p-8 text-center border-b border-slate-800">
            <div class="w-12 h-12 rounded-2xl bg-white/10 backdrop-blur-md mx-auto flex items-center justify-center mb-3 shadow-inner">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Sign in to ORMS</h2>
            <p class="mt-1 text-xs text-blue-200">Access your Owner, Renter, or Admin portal</p>
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

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="space-y-5">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <!-- Email or Username -->
                <div>
                    <label for="identifier" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Email or Admin Username
                    </label>
                    <input type="text" id="identifier" name="identifier" required autofocus
                           value="<?= htmlspecialchars($identifier) ?>"
                           placeholder="you@example.com or admin"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <!-- Password -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider">
                            Password
                        </label>
                        <a href="<?= base_url('auth/forgot_password.php') ?>" class="text-xs text-blue-400 hover:text-blue-300 transition">
                            Forgot password?
                        </a>
                    </div>
                    <input type="password" id="password" name="password" required
                           placeholder="••••••••"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                            class="w-full bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-blue-500/25 transition duration-150">
                        Sign In
                    </button>
                </div>
            </form>

            <!-- Test Credentials Hint Box -->
            <div class="mt-6 p-3 rounded-xl bg-slate-950 border border-slate-800 text-[11px] text-slate-400">
                <span class="font-semibold text-slate-300 block mb-1">Quick Test Logins (Seeded):</span>
                <div class="grid grid-cols-2 gap-1 font-mono text-[10px]">
                    <div>Admin: <span class="text-slate-200">admin</span> / <span class="text-slate-200">Admin@123</span></div>
                    <div>User: <span class="text-slate-200">rahul@example.com</span> / <span class="text-slate-200">Password@123</span></div>
                </div>
            </div>

            <div class="text-center mt-6 pt-4 border-t border-slate-800">
                <p class="text-xs text-slate-400">
                    Don't have an account yet? 
                    <a href="<?= base_url('auth/register.php') ?>" class="text-blue-400 hover:text-blue-300 font-semibold underline underline-offset-2 ml-1">
                        Register now
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
