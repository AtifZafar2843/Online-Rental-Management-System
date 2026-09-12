<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Dashboard (Initial / Step 3 Setup)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Admin.php';

require_admin();

$pdo = Database::getInstance()->getConnection();

$userCount = (int) $pdo->query("SELECT COUNT(*) FROM `USER`")->fetchColumn();
$catCount = (int) $pdo->query("SELECT COUNT(*) FROM `CATEGORY`")->fetchColumn();
$productCount = (int) $pdo->query("SELECT COUNT(*) FROM `PRODUCT`")->fetchColumn();

$pageTitle = 'Administrator Dashboard — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-red-700 via-rose-700 to-slate-900 rounded-2xl p-8 shadow-xl text-white mb-8 border border-rose-600/40">
        <span class="px-3 py-1 bg-red-500/30 rounded-full text-xs font-semibold tracking-wide uppercase border border-red-400/30">
            System Administration
        </span>
        <h1 class="text-3xl font-extrabold mt-3">Admin Portal &bull; <?= htmlspecialchars(current_user_name()) ?></h1>
        <p class="text-rose-200 text-sm mt-1">Platform management, dispute resolution, user governance, and audit reports.</p>
    </div>

    <!-- Metric Counters -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Registered Users</span>
            <div class="text-3xl font-bold text-white mt-2"><?= $userCount ?></div>
            <span class="text-xs text-blue-400 mt-1 block">Includes Owners & Renters</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Categories</span>
            <div class="text-3xl font-bold text-white mt-2"><?= $catCount ?></div>
            <span class="text-xs text-emerald-400 mt-1 block">Catalog taxonomy</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Products Listed</span>
            <div class="text-3xl font-bold text-white mt-2"><?= $productCount ?></div>
            <span class="text-xs text-indigo-400 mt-1 block">Live inventory</span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
