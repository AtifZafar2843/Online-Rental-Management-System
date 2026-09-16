<?php
/**
 * Online Rental Management System (ORMS)
 * Admin User Governance & Role Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';

require_admin();

$admin = new Admin((int) current_user_id(), current_user_name(), current_user_email());
$error = '';

// Handle Status Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_status'])) {
    if (!csrf_verify()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $targetUserId = (int) ($_POST['user_id'] ?? 0);
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        try {
            $admin->updateUserStatus($targetUserId, $newStatus);
            set_flash('success', "User #{$targetUserId} status updated to '{$newStatus}'.");
            header('Location: ' . base_url('admin/manage_users.php'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$allUsers = $admin->manageUsers();

// Search and filtering
$search = trim((string) ($_GET['q'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? 'All'));
$roleFilter = trim((string) ($_GET['role'] ?? 'All'));

$filteredUsers = array_filter($allUsers, function($u) use ($search, $statusFilter, $roleFilter) {
    if ($search !== '') {
        $match = (stripos($u['name'], $search) !== false) ||
                 (stripos($u['email'], $search) !== false) ||
                 (stripos($u['phone'], $search) !== false);
        if (!$match) return false;
    }
    if ($statusFilter !== 'All' && $u['status'] !== $statusFilter) {
        return false;
    }
    if ($roleFilter !== 'All' && !str_contains($u['roles'] ?? '', $roleFilter)) {
        return false;
    }
    return true;
});

// Calculate statistics
$totalCount = count($allUsers);
$activeCount = count(array_filter($allUsers, fn($u) => $u['status'] === 'Active'));
$inactiveCount = count(array_filter($allUsers, fn($u) => $u['status'] === 'Inactive'));
$bannedCount = count(array_filter($allUsers, fn($u) => $u['status'] === 'Banned'));

$pageTitle = 'User Governance & Management — ORMS Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('admin/dashboard.php') ?>" class="hover:text-coral transition">Admin Dashboard</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">User Governance</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#E9E7FF] pb-6 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-coral text-xs font-semibold uppercase tracking-wider mb-1">
                <i class="ri-user-settings-line text-sm"></i>
                <span>Platform Governance</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">User Profiles & Roster</h1>
            <p class="text-sm text-slate-500 mt-1">
                Oversee platform members, dual roles (Owner / Renter), verified listings, and account status controls.
            </p>
        </div>
        <a href="<?= base_url('admin/dashboard.php') ?>" 
           class="inline-flex items-center space-x-1.5 px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-600 hover:text-midnight text-xs font-semibold rounded-full border border-[#E9E7FF] transition self-start sm:self-auto shadow-sm">
            <i class="ri-arrow-left-line"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center space-x-3 shadow-sm">
            <i class="ri-error-warning-line text-lg text-rose-600 flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-white border border-[#E9E7FF] p-5 rounded-3xl shadow-sm">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Total Members</span>
            <span class="text-2xl font-display font-bold text-midnight mt-1 block"><?= $totalCount ?></span>
        </div>
        <div class="bg-white border border-[#E9E7FF] p-5 rounded-3xl shadow-sm">
            <span class="text-[11px] font-semibold text-emerald-600 uppercase tracking-wider block">Active Accounts</span>
            <span class="text-2xl font-display font-bold text-emerald-600 mt-1 block"><?= $activeCount ?></span>
        </div>
        <div class="bg-white border border-[#E9E7FF] p-5 rounded-3xl shadow-sm">
            <span class="text-[11px] font-semibold text-amber-600 uppercase tracking-wider block">Inactive Accounts</span>
            <span class="text-2xl font-display font-bold text-amber-600 mt-1 block"><?= $inactiveCount ?></span>
        </div>
        <div class="bg-white border border-[#E9E7FF] p-5 rounded-3xl shadow-sm">
            <span class="text-[11px] font-semibold text-rose-600 uppercase tracking-wider block">Suspended / Banned</span>
            <span class="text-2xl font-display font-bold text-rose-600 mt-1 block"><?= $bannedCount ?></span>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="bg-white border border-[#E9E7FF] rounded-3xl p-4 sm:p-5 mb-6 shadow-sm flex flex-col md:flex-row items-center justify-between gap-4">
        <form action="<?= base_url('admin/manage_users.php') ?>" method="GET" class="w-full md:w-auto flex flex-wrap items-center gap-3">
            <div class="relative flex-1 sm:w-64">
                <input type="text" 
                       name="q" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search name, email, phone..." 
                       class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl pl-9 pr-3 py-2 text-xs text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral">
                <i class="ri-search-line absolute left-3 top-2.5 text-slate-400 text-xs"></i>
            </div>

            <select name="status" class="bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-2 text-xs text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral">
                <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="Banned" <?= $statusFilter === 'Banned' ? 'selected' : '' ?>>Banned</option>
            </select>

            <select name="role" class="bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-2 text-xs text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral">
                <option value="All" <?= $roleFilter === 'All' ? 'selected' : '' ?>>All Roles</option>
                <option value="Owner" <?= $roleFilter === 'Owner' ? 'selected' : '' ?>>Owners</option>
                <option value="Renter" <?= $roleFilter === 'Renter' ? 'selected' : '' ?>>Renters</option>
            </select>

            <button type="submit" class="px-5 py-2 bg-coral hover:bg-[#e04e53] text-white text-xs font-semibold rounded-full shadow-sm transition">
                Filter
            </button>
            <?php if ($search || $statusFilter !== 'All' || $roleFilter !== 'All'): ?>
                <a href="<?= base_url('admin/manage_users.php') ?>" class="text-xs text-slate-400 hover:text-coral transition">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-white border border-[#E9E7FF] rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-600">
                <thead class="bg-[#FAF8F5] text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-[#E9E7FF]">
                    <tr>
                        <th class="py-4 px-6">User</th>
                        <th class="py-4 px-6">Contact</th>
                        <th class="py-4 px-6">Roles & Rating</th>
                        <th class="py-4 px-6">Activity</th>
                        <th class="py-4 px-6">Status</th>
                        <th class="py-4 px-6 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E9E7FF]">
                    <?php if (empty($filteredUsers)): ?>
                        <tr>
                            <td colspan="6" class="p-12 text-center text-slate-400">
                                No users found matching your search or filter criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredUsers as $u): 
                            $statusClasses = [
                                'Active'   => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'Inactive' => 'bg-amber-50 text-amber-700 border-amber-200',
                                'Banned'   => 'bg-rose-50 text-rose-700 border-rose-200'
                            ];
                            $statusClass = $statusClasses[$u['status']] ?? 'bg-slate-50 text-slate-700 border-slate-200';
                        ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-4 px-6">
                                    <div class="font-bold font-display text-midnight text-sm"><?= htmlspecialchars($u['name']) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono">ID: #<?= $u['user_id'] ?> &bull; Joined <?= date('M Y', strtotime($u['reg_date'])) ?></div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="text-midnight"><?= htmlspecialchars($u['email']) ?></div>
                                    <div class="text-slate-400 text-[11px] font-mono"><?= htmlspecialchars($u['phone']) ?></div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <?php 
                                            $rolesArr = array_map('trim', explode(',', $u['roles'] ?? ''));
                                            foreach ($rolesArr as $r): 
                                                if (!$r) continue;
                                        ?>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold <?= $r === 'Owner' ? 'bg-[#E9E7FF] text-midnight border border-[#d8d5ff]' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>">
                                                <?= htmlspecialchars($r) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-[11px] text-amber-600 font-medium flex items-center space-x-1">
                                        <i class="ri-star-fill text-amber-400"></i>
                                        <span><?= number_format((float) $u['rating'], 1) ?> / 5.0</span>
                                    </div>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="block text-midnight"><strong><?= $u['products_count'] ?></strong> listed</span>
                                    <span class="text-slate-400"><strong><?= $u['rentals_count'] ?></strong> rentals</span>
                                </td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-semibold uppercase tracking-wider border <?= $statusClass ?>">
                                        <?= htmlspecialchars($u['status']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <form action="<?= base_url('admin/manage_users.php') ?>" method="POST" class="inline-flex items-center space-x-1.5">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <input type="hidden" name="update_status" value="1">

                                        <?php if ($u['status'] !== 'Active'): ?>
                                            <button type="submit" name="status" value="Active" title="Activate Account"
                                                    class="px-3 py-1 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 text-[11px] font-semibold rounded-full border border-emerald-200 transition">
                                                Activate
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($u['status'] === 'Active'): ?>
                                            <button type="submit" name="status" value="Inactive" title="Deactivate Account"
                                                    class="px-3 py-1 bg-amber-50 hover:bg-amber-100 text-amber-700 text-[11px] font-semibold rounded-full border border-amber-200 transition">
                                                Deactivate
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($u['status'] !== 'Banned'): ?>
                                            <button type="submit" name="status" value="Banned" title="Ban User"
                                                    onclick="return confirm('Suspend and ban user <?= htmlspecialchars(addslashes($u['name'])) ?>?');"
                                                    class="px-3 py-1 bg-rose-50 hover:bg-rose-100 text-rose-700 text-[11px] font-semibold rounded-full border border-rose-200 transition">
                                                Ban
                                            </button>
                                        <?php endif; ?>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
