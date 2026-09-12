<?php
/**
 * Online Rental Management System (ORMS)
 * Owner Workspace Dashboard
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 4)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/RentalRequest.php';

require_role('Owner');

$pdo = Database::getInstance()->getConnection();
$ownerId = (int) current_user_id();

// Fetch Owner's products
$products = Product::findByOwner($ownerId);

// Fetch Pending Requests
$pendingRequests = RentalRequest::findByOwner($ownerId, 'Pending');
$pendingCount = count($pendingRequests);

// Compute Dashboard Metrics
$totalProducts = count($products);
$availableCount = 0;
$rentedCount = 0;
$unavailableCount = 0;

foreach ($products as $p) {
    $st = $p->getAvailStatus();
    if ($st === 'Available') $availableCount++;
    elseif ($st === 'Rented') $rentedCount++;
    else $unavailableCount++;
}

// Calculate lifetime earnings for this owner
$ownerModel = new Owner($ownerId);
$totalEarnings = $ownerModel->calculateTotalEarnings();

$pageTitle = 'Owner Dashboard — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Top Welcome Banner -->
    <div class="bg-gradient-to-r from-blue-700 via-indigo-700 to-slate-900 rounded-2xl p-8 shadow-xl text-white mb-8 border border-blue-600/40 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <span class="px-3 py-1 bg-blue-500/30 rounded-full text-xs font-semibold tracking-wide uppercase border border-blue-400/30">
                Owner Workspace
            </span>
            <h1 class="text-3xl font-extrabold mt-3">Welcome, <?= htmlspecialchars(current_user_name()) ?>!</h1>
            <p class="text-blue-200 text-sm mt-1">Manage your rental inventory, monitor product bookings, and track your revenue.</p>
        </div>
        <div class="flex-shrink-0 flex items-center space-x-3">
            <a href="<?= base_url('owner/manage_requests.php') ?>" 
               class="inline-flex items-center space-x-2 px-5 py-3.5 bg-blue-600/30 hover:bg-blue-600/50 border border-blue-400/40 text-white font-bold text-xs uppercase tracking-wider rounded-xl transition">
                <span>📬</span>
                <span>Manage Requests</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="px-2 py-0.5 rounded-full bg-amber-500 text-slate-950 font-extrabold text-[10px] ml-1">
                        <?= $pendingCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= base_url('owner/add_product.php') ?>" 
               class="inline-flex items-center space-x-2 px-6 py-3.5 bg-white text-slate-900 hover:bg-slate-100 font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg transition">
                <span>➕</span>
                <span>List New Product</span>
            </a>
        </div>
    </div>

    <?php if ($pendingCount > 0): ?>
        <div class="mb-8 p-4 rounded-2xl bg-amber-950/40 border border-amber-600/50 text-amber-200 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-lg">
            <div class="flex items-center space-x-3">
                <span class="w-3 h-3 rounded-full bg-amber-400 animate-pulse"></span>
                <span class="text-sm font-semibold">You have <strong><?= $pendingCount ?></strong> pending rental request(s) awaiting your decision.</span>
            </div>
            <a href="<?= base_url('owner/manage_requests.php?status=Pending') ?>" 
               class="px-4 py-2 bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs rounded-xl transition self-start sm:self-auto">
                Review Pending Requests &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- Metrics Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-10">
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total Listings</span>
            <div class="text-3xl font-bold text-white mt-2"><?= $totalProducts ?></div>
            <span class="text-xs text-slate-500 mt-1 block">In your inventory</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Available</span>
            <div class="text-3xl font-bold text-emerald-400 mt-2"><?= $availableCount ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Ready for rent</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-blue-400 uppercase tracking-wider">Rented Out</span>
            <div class="text-3xl font-bold text-blue-400 mt-2"><?= $rentedCount ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Active on rent</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">Total Earnings</span>
            <div class="text-3xl font-bold text-white mt-2">₹<?= number_format($totalEarnings, 2) ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Completed rentals</span>
        </div>
    </div>

    <!-- Product Inventory Table / Section -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-white tracking-tight">Your Product Inventory</h2>
                <p class="text-xs text-slate-400 mt-0.5">All products listed under your owner account.</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="<?= base_url('owner/add_product.php') ?>" class="text-xs font-semibold text-blue-400 hover:text-blue-300">
                    + Add Another Listing
                </a>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <!-- Empty State -->
            <div class="text-center py-16 px-4">
                <div class="w-16 h-16 rounded-2xl bg-slate-850 border border-slate-800 mx-auto flex items-center justify-center text-3xl mb-4">
                    📦
                </div>
                <h3 class="text-base font-bold text-white">No products listed yet</h3>
                <p class="text-xs text-slate-400 max-w-sm mx-auto mt-1 mb-6">
                    Start earning by listing your idle cameras, electronics, vehicles, or furniture for rent today.
                </p>
                <a href="<?= base_url('owner/add_product.php') ?>" 
                   class="inline-flex items-center space-x-2 px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/25 transition">
                    <span>➕</span>
                    <span>List Your First Product</span>
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                        <tr>
                            <th class="py-3.5 px-4">Product</th>
                            <th class="py-3.5 px-4">Category</th>
                            <th class="py-3.5 px-4">Daily Rent</th>
                            <th class="py-3.5 px-4">Deposit</th>
                            <th class="py-3.5 px-4">Condition</th>
                            <th class="py-3.5 px-4">Status</th>
                            <th class="py-3.5 px-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php foreach ($products as $prod): 
                            $primaryImg = $prod->getPrimaryImagePath();
                            $status = $prod->getAvailStatus();

                            $statusBadge = 'bg-slate-800 text-slate-400 border border-slate-700';
                            if ($status === 'Available') {
                                $statusBadge = 'bg-emerald-950 text-emerald-400 border border-emerald-700/60';
                            } elseif ($status === 'Rented') {
                                $statusBadge = 'bg-blue-950 text-blue-400 border border-blue-700/60';
                            } elseif ($status === 'Unavailable') {
                                $statusBadge = 'bg-amber-950 text-amber-400 border border-amber-700/60';
                            }
                        ?>
                            <tr class="hover:bg-slate-850/60 transition">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center space-x-3">
                                        <img src="<?= base_url($primaryImg) ?>" alt="Product" 
                                             class="w-12 h-12 rounded-lg object-cover bg-slate-950 border border-slate-800 flex-shrink-0">
                                        <div>
                                            <div class="font-bold text-white line-clamp-1"><?= htmlspecialchars($prod->getTitle()) ?></div>
                                            <div class="text-[11px] text-slate-400 flex items-center space-x-1 mt-0.5">
                                                <span>📍</span>
                                                <span><?= htmlspecialchars($prod->getLocation()) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-medium text-slate-300">
                                    <?= htmlspecialchars($prod->getCategoryName() ?? 'Category') ?>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-white">
                                    ₹<?= number_format($prod->getRentPerDay(), 2) ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-400">
                                    ₹<?= number_format($prod->getSecurityDeposit(), 2) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                        <?= htmlspecialchars($prod->getCondition()) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold inline-block <?= $statusBadge ?>">
                                        <?= htmlspecialchars($status) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-right">
                                    <div class="inline-flex items-center space-x-2">
                                        <!-- Edit Link -->
                                        <a href="<?= base_url("owner/edit_product.php?id={$prod->getProductID()}") ?>" 
                                           class="px-2.5 py-1.5 rounded-lg bg-blue-950/60 text-blue-400 hover:bg-blue-900/80 border border-blue-800/50 font-medium transition">
                                            Edit
                                        </a>

                                        <!-- Toggle Status Link -->
                                        <?php if ($status !== 'Rented'): ?>
                                            <a href="<?= base_url("owner/toggle_status.php?id={$prod->getProductID()}") ?>" 
                                               title="Toggle Available / Unavailable"
                                               class="px-2.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 font-medium transition">
                                                <?= ($status === 'Available') ? 'Deactivate' : 'Activate' ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
