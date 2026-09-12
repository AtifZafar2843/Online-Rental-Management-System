<?php
/**
 * Online Rental Management System (ORMS)
 * User Registration Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 1) & Section 7 (Security Checklist)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

require_guest();

$errors = [];
$name = '';
$email = '';
$phone = '';
$address = '';
$selectedRoles = ['Renter']; // Default selection

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Verify CSRF Token
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Security verification failed (Invalid CSRF token). Please try again.';
    }

    // 2. Retrieve and Sanitize Form Inputs
    $name = trim($_POST['name'] ?? '');
    $email = trim(filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL));
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $selectedRoles = $_POST['roles'] ?? [];

    // 3. Validation Rules
    if (empty($name)) {
        $errors[] = 'Full name is required.';
    }

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'A valid email address is required.';
    }

    if (empty($phone) || !preg_match('/^[0-9+ -]{10,15}$/', $phone)) {
        $errors[] = 'A valid phone number (10–15 digits) is required.';
    }

    if (empty($address)) {
        $errors[] = 'Physical address is required for identity verification.';
    }

    if (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($password !== $confirmPassword) {
        $errors[] = 'Passwords do not match.';
    }

    if (empty($selectedRoles) || !is_array($selectedRoles)) {
        $errors[] = 'Please select at least one role (Owner, Renter, or Both).';
    } else {
        $validRoles = ['Owner', 'Renter'];
        $selectedRoles = array_intersect($selectedRoles, $validRoles);
        if (empty($selectedRoles)) {
            $errors[] = 'Invalid role selected.';
        }
    }

    // 4. File Upload Validation (ID Proof via finfo_file magic-bytes)
    $idProofPath = '';
    if (empty($_FILES['id_proof']) || $_FILES['id_proof']['error'] === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Government-issued ID proof document (PDF, PNG, or JPG) is required.';
    } else {
        $allowedMimes = ['image/jpeg', 'image/png', 'application/pdf'];
        $uploadCheck = validate_file_upload($_FILES['id_proof'], $allowedMimes, 5242880); // 5MB

        if (!$uploadCheck['valid']) {
            $errors[] = $uploadCheck['error'];
        } else {
            // Generate randomized secure filename (Section 5 Rule 4)
            $ext = $uploadCheck['extension'];
            $randomFileName = 'id_' . bin2hex(random_bytes(16)) . '.' . $ext;
            $uploadDir = __DIR__ . '/../uploads/id_proofs/';

            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $targetFilePath = $uploadDir . $randomFileName;
            if (!move_uploaded_file($_FILES['id_proof']['tmp_name'], $targetFilePath)) {
                $errors[] = 'Failed to securely store the uploaded ID proof file.';
            } else {
                $idProofPath = 'uploads/id_proofs/' . $randomFileName;
            }
        }
    }

    // 5. Database Duplicate Check & Insertion
    if (empty($errors)) {
        $pdo = Database::getInstance()->getConnection();

        // Check if email already exists
        $checkStmt = $pdo->prepare("SELECT user_id FROM `USER` WHERE email = :email LIMIT 1");
        $checkStmt->execute(['email' => $email]);
        if ($checkStmt->fetch()) {
            $errors[] = 'An account with this email address already exists. Please sign in or use forgot password.';
            // Delete uploaded file if duplicate registration
            if ($idProofPath && file_exists(__DIR__ . '/../' . $idProofPath)) {
                unlink(__DIR__ . '/../' . $idProofPath);
            }
        } else {
            try {
                $pdo->beginTransaction();

                // Hash password securely with Bcrypt
                $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

                // Insert into USER table
                $insertUser = $pdo->prepare("
                    INSERT INTO `USER` 
                    (`name`, `email`, `phone`, `address`, `password`, `id_proof_path`, `rating`, `status`, `reg_date`) 
                    VALUES 
                    (:name, :email, :phone, :address, :password, :id_proof_path, 0.00, 'Active', NOW())
                ");

                $insertUser->execute([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                    'address' => $address,
                    'password' => $hashedPassword,
                    'id_proof_path' => $idProofPath
                ]);

                $newUserId = (int) $pdo->lastInsertId();

                // Insert selected roles into USER_ROLES junction table
                $insertRole = $pdo->prepare("
                    INSERT INTO `USER_ROLES` (`user_id`, `role`, `assigned_date`) 
                    VALUES (:user_id, :role, NOW())
                ");

                foreach ($selectedRoles as $role) {
                    $insertRole->execute([
                        'user_id' => $newUserId,
                        'role' => $role
                    ]);
                }

                $pdo->commit();

                set_flash('success', 'Registration successful! Your account has been created. Please sign in below.');
                header("Location: " . base_url('auth/login.php'));
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                if ($idProofPath && file_exists(__DIR__ . '/../' . $idProofPath)) {
                    unlink(__DIR__ . '/../' . $idProofPath);
                }
                $errors[] = 'Registration failed due to a database error: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Register New Account — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="py-12 px-4 sm:px-6 lg:px-8 max-w-2xl mx-auto">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 p-8 border-b border-slate-800">
            <h2 class="text-3xl font-extrabold text-white tracking-tight">Create an ORMS Account</h2>
            <p class="mt-2 text-sm text-blue-200">
                Join our peer-to-peer rental community as an Owner, Renter, or both.
            </p>
        </div>

        <div class="p-8">
            <!-- Display Validation Errors -->
            <?php if (!empty($errors)): ?>
                <div class="mb-6 p-4 rounded-xl bg-rose-950/80 border border-rose-600/70 text-rose-200 space-y-1">
                    <div class="font-semibold flex items-center space-x-2 text-rose-300">
                        <span>⚠️</span>
                        <span>Please correct the following issues:</span>
                    </div>
                    <ul class="list-disc list-inside text-xs space-y-1 pl-1 text-rose-300/90">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" enctype="multipart/form-data" class="space-y-6">
                <?= csrf_field() ?>

                <!-- Full Name -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Full Name <span class="text-rose-500">*</span>
                    </label>
                    <input type="text" id="name" name="name" required
                           value="<?= htmlspecialchars($name) ?>"
                           placeholder="e.g. John Doe"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <!-- Email & Phone Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Email Address <span class="text-rose-500">*</span>
                        </label>
                        <input type="email" id="email" name="email" required
                               value="<?= htmlspecialchars($email) ?>"
                               placeholder="you@example.com"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>

                    <div>
                        <label for="phone" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Phone Number <span class="text-rose-500">*</span>
                        </label>
                        <input type="tel" id="phone" name="phone" required
                               value="<?= htmlspecialchars($phone) ?>"
                               placeholder="e.g. 9876543210"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>
                </div>

                <!-- Physical Address -->
                <div>
                    <label for="address" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Physical Address <span class="text-rose-500">*</span>
                    </label>
                    <textarea id="address" name="address" rows="2" required
                              placeholder="Complete address (for rental verification & logistics)"
                              class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"><?= htmlspecialchars($address) ?></textarea>
                </div>

                <!-- Passwords Grid -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Password <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" id="password" name="password" required minlength="6"
                               placeholder="Minimum 6 characters"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>

                    <div>
                        <label for="confirm_password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                            Confirm Password <span class="text-rose-500">*</span>
                        </label>
                        <input type="password" id="confirm_password" name="confirm_password" required minlength="6"
                               placeholder="Re-enter password"
                               class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                    </div>
                </div>

                <!-- ID Proof Upload (Magic Byte Checked) -->
                <div>
                    <label for="id_proof" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        Identity Verification Document (Aadhaar / DL / Passport) <span class="text-rose-500">*</span>
                    </label>
                    <input type="file" id="id_proof" name="id_proof" required accept=".pdf,.png,.jpg,.jpeg"
                           class="w-full text-xs text-slate-400 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-blue-600 file:text-white hover:file:bg-blue-500 file:cursor-pointer bg-slate-950 border border-slate-800 rounded-xl p-2 focus:outline-none">
                    <p class="text-[11px] text-slate-500 mt-1.5">
                        Allowed: PDF, JPG, PNG (Max 5MB). Files are secured and validated via MIME magic bytes.
                    </p>
                </div>

                <!-- Role Selection Checkboxes -->
                <div class="bg-slate-950 p-4 rounded-xl border border-slate-800">
                    <span class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">
                        I want to register as: <span class="text-rose-500">*</span>
                    </span>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <label class="flex items-center space-x-3 p-3 rounded-lg border border-slate-800 bg-slate-900/60 hover:border-slate-700 cursor-pointer transition">
                            <input type="checkbox" name="roles[]" value="Renter" 
                                   <?= in_array('Renter', $selectedRoles, true) ? 'checked' : '' ?>
                                   class="w-4 h-4 text-blue-600 rounded bg-slate-950 border-slate-700 focus:ring-blue-500">
                            <div>
                                <span class="text-sm font-semibold text-white">Renter</span>
                                <p class="text-xs text-slate-400">Discover and rent items</p>
                            </div>
                        </label>

                        <label class="flex items-center space-x-3 p-3 rounded-lg border border-slate-800 bg-slate-900/60 hover:border-slate-700 cursor-pointer transition">
                            <input type="checkbox" name="roles[]" value="Owner" 
                                   <?= in_array('Owner', $selectedRoles, true) ? 'checked' : '' ?>
                                   class="w-4 h-4 text-blue-600 rounded bg-slate-950 border-slate-700 focus:ring-blue-500">
                            <div>
                                <span class="text-sm font-semibold text-white">Owner</span>
                                <p class="text-xs text-slate-400">List items and earn rental fees</p>
                            </div>
                        </label>
                    </div>
                    <p class="text-[11px] text-slate-500 mt-2">
                        Tip: You can select both roles to rent products and list your own items seamlessly.
                    </p>
                </div>

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold py-3.5 px-4 rounded-xl shadow-lg shadow-blue-500/20 transition duration-150">
                        Create My Account
                    </button>
                </div>

                <div class="text-center pt-2">
                    <p class="text-xs text-slate-400">
                        Already have an account? 
                        <a href="<?= base_url('auth/login.php') ?>" class="text-blue-400 hover:text-blue-300 font-semibold underline underline-offset-2 ml-1">
                            Sign in here
                        </a>
                    </p>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
