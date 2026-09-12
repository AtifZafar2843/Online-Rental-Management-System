<?php
/**
 * Online Rental Management System (ORMS)
 * Renter Dashboard (Initial / Step 3 Setup)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Renter.php';

require_role('Renter');

$pageTitle = 'Renter Dashboard — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-indigo-700 via-purple-700 to-slate-900 rounded-2xl p-8 shadow-xl text-white mb-8 border border-indigo-600/40">
        <span class="px-3 py-1 bg-indigo-500/30 rounded-full text-xs font-semibold tracking-wide uppercase border border-indigo-400/30">
            Renter Workspace
        </span>
        <h1 class="text-3xl font-extrabold mt-3">Welcome, <?= htmlspecialchars(current_user_name()) ?>!</h1>
        <p class="text-indigo-200 text-sm mt-1">Discover items, track active rentals, manage payments, and submit reviews.</p>
    </div>

    <!-- Quick Action Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <div class="w-10 h-10 rounded-xl bg-blue-600/20 text-blue-400 flex items-center justify-center font-bold text-lg mb-3">
                🔍
            </div>
            <h3 class="text-lg font-bold text-white">Browse Catalog</h3>
            <p class="text-xs text-slate-400 mt-1 mb-4">Search available electronics, vehicles, and furniture.</p>
            <span class="text-xs text-slate-500">(Will be active in Step 5)</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <div class="w-10 h-10 rounded-xl bg-purple-600/20 text-purple-400 flex items-center justify-center font-bold text-lg mb-3">
                📦
            </div>
            <h3 class="text-lg font-bold text-white">My Rentals</h3>
            <p class="text-xs text-slate-400 mt-1 mb-4">View status of requested items, active rentals, and returns.</p>
            <span class="text-xs text-slate-500">(Will be active in Step 6)</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <div class="w-10 h-10 rounded-xl bg-amber-600/20 text-amber-400 flex items-center justify-center font-bold text-lg mb-3">
                ⭐
            </div>
            <h3 class="text-lg font-bold text-white">Reviews & Ratings</h3>
            <p class="text-xs text-slate-400 mt-1 mb-4">Rate products and owners after completing a rental.</p>
            <span class="text-xs text-slate-500">(Will be active in Step 9)</span>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
