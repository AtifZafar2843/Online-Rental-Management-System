<?php
/**
 * Online Rental Management System (ORMS)
 * Owner Dashboard (Initial / Step 3 Setup)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Owner.php';

require_role('Owner');

$pageTitle = 'Owner Dashboard — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 rounded-2xl p-8 shadow-xl text-white mb-8 border border-blue-600/40">
        <span class="px-3 py-1 bg-blue-500/30 rounded-full text-xs font-semibold tracking-wide uppercase border border-blue-400/30">
            Owner Workspace
        </span>
        <h1 class="text-3xl font-extrabold mt-3">Welcome, <?= htmlspecialchars(current_user_name()) ?>!</h1>
        <p class="text-blue-200 text-sm mt-1">Manage your listed rental products, track customer requests, and view earnings.</p>
    </div>

    <!-- Quick Action Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <div class="w-10 h-10 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center font-bold text-lg mb-3">
                ➕
            </div>
            <h3 class="text-lg font-bold text-white">List New Product</h3>
            <p class="text-xs text-slate-400 mt-1 mb-4">Add a new rental item with daily rates and images.</p>
            <span class="text-xs text-slate-500">(Will be active in Step 4)</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <div class="w-10 h-10 rounded-xl bg-indigo-600/20 text-indigo-400 flex items-center justify-center font-bold text-lg mb-3">
                📋
            </div>
            <h3 class="text-lg font-bold text-white">Rental Requests</h3>
            <p class="text-xs text-slate-400 mt-1 mb-4">Review pending booking requests from renters.</p>
            <span class="text-xs text-slate-500">(Will be active in Step 6)</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <div class="w-10 h-10 rounded-xl bg-emerald-600/20 text-emerald-400 flex items-center justify-center font-bold text-lg mb-3">
                💰
            </div>
            <h3 class="text-lg font-bold text-white">Earnings & Revenue</h3>
            <p class="text-xs text-slate-400 mt-1 mb-4">Track payments received and security deposits.</p>
            <span class="text-xs text-slate-500">(Will be active in Step 7)</span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
