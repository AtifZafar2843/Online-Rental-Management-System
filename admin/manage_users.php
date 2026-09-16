<?php
/**
 * Online Rental Management System (ORMS)
 * Admin User Governance & Role Management
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1, 13.I (Page 29)
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('admin/dashboard.php') ?>" class="hover:text-white transition">Admin Dashboard</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">User Governance</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center space-x-3">
                <span>👥</span>
                <span>User Governance & Profiles</span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Oversee platform members, dual roles (Owner / Renter), verified ID documents, and account status controls.
            </p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800 text-rose-300 text-sm flex items-center space-x-3 shadow-lg">
            <span class="text-xl flex-shrink-0">⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow">
            <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block">Total Members</span>
            <span class="text-2xl font-bold text-white mt-1 block"><?= $totalCount ?></span>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow">
            <span class="text-[11px] font-semibold text-emerald-400 uppercase tracking-wider block">Active Accounts</span>
            <span class="text-2xl font-bold text-emerald-300 mt-1 block"><?= $activeCount ?></span>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow">
            <span class="text-[11px] font-semibold text-amber-400 uppercase tracking-wider block">Inactive Accounts</span>
            <span class="text-2xl font-bold text-amber-300 mt-1 block"><?= $inactiveCount ?></span>
        </div>
        <div class="bg-slate-900 border border-slate-800 p-4 rounded-2xl shadow">
            <span class="text-[11px] font-semibold text-rose-400 uppercase tracking-wider block">Banned / Suspended</span>
            <span class="text-2xl font-bold text-rose-400 mt-1 block"><?= $bannedCount ?></span>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow flex flex-col md:flex-row items-center justify-between gap-4">
        <form action="<?= base_url('admin/manage_users.php') ?>" method="GET" class="w-full md:w-auto flex flex-wrap items-center gap-3">
            <div class="relative flex-1 sm:w-64">
                <input type="text" 
                       name="q" 
                       value="<?= htmlspecialchars($search) ?>" 
                       placeholder="Search name, email, phone..." 
                       class="w-full bg-slate-950 border border-slate-700 rounded-xl pl-9 pr-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                <span class="absolute left-3 top-2 text-slate-500 text-xs">🔍</span>
            </div>

            <select name="status" class="bg-slate-950 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="All" <?= $statusFilter === 'All' ? 'selected' : '' ?>>All Statuses</option>
                <option value="Active" <?= $statusFilter === 'Active' ? 'selected' : '' ?>>Active</option>
                <option value="Inactive" <?= $statusFilter === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                <option value="Banned" <?= $statusFilter === 'Banned' ? 'selected' : '' ?>>Banned</option>
            </select>

            <select name="role" class="bg-slate-950 border border-slate-700 rounded-xl px-3 py-1.5 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                <option value="All" <?= $roleFilter === 'All' ? 'selected' : '' ?>>All Roles</option>
                <option value="Owner" <?= $roleFilter === 'Owner' ? 'selected' : '' ?>>Owners</option>
                <option value="Renter" <?= $roleFilter === 'Renter' ? 'selected' : '' ?>>Renters</option>
            </select>

            <button type="submit" class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-semibold rounded-xl transition">
                Filter
            </button>
            <?php if ($search || $statusFilter !== 'All' || $roleFilter !== 'All'): ?>
                <a href="<?= base_url('admin/manage_users.php') ?>" class="text-xs text-slate-400 hover:text-white transition">
                    Clear
                </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Users Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs text-slate-300">
                <thead class="bg-slate-950 text-slate-400 font-bold uppercase tracking-wider text-[11px] border-b border-slate-800">
                    <tr>
                        <th class="p-4">User</th>
                        <th class="p-4">Contact</th>
                        <th class="p-4">Roles & Rating</th>
                        <th class="p-4">Activity</th>
                        <th class="p-4">Status</th>
                        <th class="p-4 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800">
                    <?php if (empty($filteredUsers)): ?>
                        <tr>
                            <td colspan="6" class="p-8 text-center text-slate-500">
                                No users found matching your criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($filteredUsers as $u): 
                            $statusClasses = [
                                'Active'   => 'bg-emerald-950/80 text-emerald-400 border-emerald-800',
                                'Inactive' => 'bg-amber-950/80 text-amber-400 border-amber-800',
                                'Banned'   => 'bg-rose-950/80 text-rose-400 border-rose-800'
                            ];
                            $statusClass = $statusClasses[$u['status']] ?? 'bg-slate-800 text-slate-300 border-slate-700';
                        ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="p-4">
                                    <div class="font-bold text-white"><?= htmlspecialchars($u['name']) ?></div>
                                    <div class="text-[11px] text-slate-500 font-mono">ID: #<?= $u['user_id'] ?> &bull; Joined <?= date('M Y', strtotime($u['reg_date'])) ?></div>
                                </td>
                                <td class="p-4">
                                    <div><?= htmlspecialchars($u['email']) ?></div>
                                    <div class="text-slate-500 text-[11px] font-mono"><?= htmlspecialchars($u['phone']) ?></div>
                                </td>
                                <td class="p-4">
                                    <div class="flex items-center gap-1.5 mb-1">
                                        <?php 
                                            $rolesArr = array_map('trim', explode(',', $u['roles'] ?? ''));
                                            foreach ($rolesArr as $r): 
                                                if (!$r) continue;
                                        ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold <?= $r === 'Owner' ? 'bg-blue-950 text-blue-300 border border-blue-800' : 'bg-indigo-950 text-indigo-300 border border-indigo-800' ?>">
                                                <?= htmlspecialchars($r) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="text-[11px] text-amber-400">
                                        ★ <?= number_format((float) $u['rating'], 1) ?> / 5.0
                                    </div>
                                </td>
                                <td class="p-4">
                                    <span class="block">📦 <strong><?= $u['products_count'] ?></strong> listed</span>
                                    <span class="text-slate-500">🛒 <strong><?= $u['rentals_count'] ?></strong> rentals</span>
                                </td>
                                <td class="p-4">
                                    <span class="px-2.5 py-1 rounded-full text-[10px] font-bold uppercase tracking-wider border <?= $statusClass ?>">
                                        <?= htmlspecialchars($u['status']) ?>
                                    </span>
                                </td>
                                <td class="p-4 text-right">
                                    <form action="<?= base_url('admin/manage_users.php') ?>" method="POST" class="inline-flex items-center space-x-1.5">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                                        <input type="hidden" name="update_status" value="1">

                                        <?php if ($u['status'] !== 'Active'): ?>
                                            <button type="submit" name="status" value="Active" title="Activate Account"
                                                    class="px-2.5 py-1 bg-emerald-900/60 hover:bg-emerald-800 text-emerald-300 text-[11px] font-semibold rounded-lg border border-emerald-700/50 transition">
                                                Activate
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($u['status'] === 'Active'): ?>
                                            <button type="submit" name="status" value="Inactive" title="Deactivate Account"
                                                    class="px-2.5 py-1 bg-amber-900/60 hover:bg-amber-800 text-amber-300 text-[11px] font-semibold rounded-lg border border-amber-700/50 transition">
                                                Deactivate
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($u['status'] !== 'Banned'): ?>
                                            <button type="submit" name="status" value="Banned" title="Ban User"
                                                    onclick="return confirm('Suspend and ban user <?= htmlspecialchars(addslashes($u['name'])) ?>?');"
                                                    class="px-2.5 py-1 bg-rose-900/60 hover:bg-rose-800 text-rose-300 text-[11px] font-semibold rounded-lg border border-rose-700/50 transition">
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
