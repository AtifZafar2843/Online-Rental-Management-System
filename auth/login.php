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

<div class="py-16 sm:py-24 px-4 sm:px-6 lg:px-8 max-w-md mx-auto">
    <div class="bg-white border border-stone-200/80 rounded-3xl shadow-soft overflow-hidden">
        <!-- Header Banner -->
        <div class="p-8 pb-6 text-center border-b border-stone-100 bg-[#FAF8F5]">
            <img src="<?= base_url('assets/img/ORMS Logo.png') ?>" alt="ORMS" class="h-9 mx-auto mb-3 object-contain">
            <h2 class="font-display text-2xl font-bold text-midnight tracking-tight">Welcome back</h2>
            <p class="mt-1 text-xs text-stone-500">Sign in to manage your rentals, listings, or portal</p>
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

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" class="space-y-5">
                <?= csrf_field() ?>
                <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect) ?>">

                <!-- Email or Username -->
                <div>
                    <label for="identifier" class="block text-xs font-semibold text-stone-700 uppercase tracking-wider mb-2">
                        Email or Admin Username
                    </label>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                            <i class="ri-mail-line text-base"></i>
                        </span>
                        <input type="text" id="identifier" name="identifier" required autofocus
                               value="<?= htmlspecialchars($identifier) ?>"
                               placeholder="you@example.com or admin"
                               class="w-full bg-white border border-stone-200/90 rounded-2xl pl-10 pr-4 py-3 text-sm text-midnight placeholder-stone-400 focus:outline-none focus:border-coral focus:ring-4 focus:ring-coral-50 transition font-medium">
                    </div>
                </div>

                <!-- Password -->
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label for="password" class="block text-xs font-semibold text-stone-700 uppercase tracking-wider">
                            Password
                        </label>
                        <a href="<?= base_url('auth/forgot_password.php') ?>" class="text-xs text-coral hover:text-coral-600 font-medium transition">
                            Forgot password?
                        </a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-stone-400">
                            <i class="ri-lock-2-line text-base"></i>
                        </span>
                        <input type="password" id="password" name="password" required
                               placeholder="••••••••"
                               class="w-full bg-white border border-stone-200/90 rounded-2xl pl-10 pr-4 py-3 text-sm text-midnight placeholder-stone-400 focus:outline-none focus:border-coral focus:ring-4 focus:ring-coral-50 transition font-medium">
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-2">
                    <button type="submit" 
                            class="w-full bg-coral hover:bg-coral-600 text-white font-semibold py-3.5 px-6 rounded-full shadow-glow-coral transition duration-150 transform hover:-translate-y-0.5">
                        Sign In &rarr;
                    </button>
                </div>
            </form>

            <!-- Test Credentials Hint Box -->
            <div class="mt-6 p-3.5 rounded-2xl bg-[#FAF8F5] border border-stone-200/70 text-[11px] text-stone-500">
                <span class="font-semibold text-stone-700 block mb-1">Quick Demo Credentials:</span>
                <div class="grid grid-cols-2 gap-1.5 font-mono text-[10px] text-stone-600">
                    <div>Admin: <span class="text-midnight font-semibold">admin</span> / <span class="text-midnight">Admin@123</span></div>
                    <div>User: <span class="text-midnight font-semibold">rahul@example.com</span> / <span class="text-midnight">Password@123</span></div>
                </div>
            </div>

            <div class="text-center mt-6 pt-4 border-t border-stone-100">
                <p class="text-xs text-stone-500">
                    Don't have an account yet? 
                    <a href="<?= base_url('auth/register.php') ?>" class="text-coral hover:text-coral-600 font-semibold ml-1">
                        Register now &rarr;
                    </a>
                </p>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
