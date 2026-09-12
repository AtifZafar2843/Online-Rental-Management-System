<?php
/**
 * Online Rental Management System (ORMS)
 * Renter Workspace Dashboard
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 6) & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Renter.php';
require_once __DIR__ . '/../classes/RentalRequest.php';

require_role('Renter');

$renterId = (int) current_user_id();
$renter = new Renter($renterId);
$totalSpent = $renter->calculateTotalSpent();

$requests = RentalRequest::findByRenter($renterId);

$pendingCount = 0;
$approvedCount = 0;
$activeCount = 0;
$completedCount = 0;

foreach ($requests as $r) {
    switch ($r->getStatus()) {
        case 'Pending': $pendingCount++; break;
        case 'Approved': $approvedCount++; break;
        case 'Active': $activeCount++; break;
        case 'Completed': $completedCount++; break;
    }
}

$pageTitle = 'Renter Dashboard — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-indigo-700 via-purple-700 to-slate-900 rounded-2xl p-8 shadow-xl text-white mb-8 border border-indigo-600/40 flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
            <span class="px-3 py-1 bg-indigo-500/30 rounded-full text-xs font-semibold tracking-wide uppercase border border-indigo-400/30">
                Renter Workspace
            </span>
            <h1 class="text-3xl font-extrabold mt-3">Welcome, <?= htmlspecialchars(current_user_name()) ?>!</h1>
            <p class="text-indigo-200 text-sm mt-1">Discover items to rent, track active bookings, manage payments, and write reviews.</p>
        </div>
        <div>
            <a href="<?= base_url('renter/search.php') ?>" 
               class="inline-flex items-center space-x-2 px-6 py-3.5 bg-white text-slate-900 hover:bg-slate-100 font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg transition">
                <span>🔍</span>
                <span>Browse Rental Catalog</span>
            </a>
        </div>
    </div>

    <!-- Quick Metrics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-10">
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-amber-400 uppercase tracking-wider">Pending Requests</span>
            <div class="text-3xl font-bold text-amber-400 mt-2"><?= $pendingCount ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Awaiting owner review</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-blue-400 uppercase tracking-wider">Approved Requests</span>
            <div class="text-3xl font-bold text-blue-400 mt-2"><?= $approvedCount ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Ready for payment</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Active Rentals</span>
            <div class="text-3xl font-bold text-emerald-400 mt-2"><?= $activeCount ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Currently with you</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-5 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-purple-400 uppercase tracking-wider">Total Spent</span>
            <div class="text-3xl font-bold text-white mt-2">₹<?= number_format($totalSpent, 2) ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Lifetime rentals</span>
        </div>
    </div>

    <!-- Recent Bookings Section -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-white tracking-tight">Recent Rental Requests</h2>
                <p class="text-xs text-slate-400 mt-0.5">Your most recent rental inquiries and bookings.</p>
            </div>
            <div>
                <a href="<?= base_url('renter/my_rentals.php') ?>" class="text-xs font-semibold text-blue-400 hover:text-blue-300">
                    View All Bookings (<?= count($requests) ?>) &rarr;
                </a>
            </div>
        </div>

        <?php if (empty($requests)): ?>
            <div class="p-10 text-center text-slate-400">
                <div class="text-3xl mb-2">📦</div>
                <div class="text-sm font-semibold text-slate-300">No rental requests submitted yet</div>
                <p class="text-xs text-slate-500 mt-1">Browse our verified catalog to request cameras, laptops, furniture, and more.</p>
                <a href="<?= base_url('renter/search.php') ?>" class="inline-block mt-4 px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl transition">
                    Browse Catalog &rarr;
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-800">
                <?php foreach (array_slice($requests, 0, 5) as $req): 
                    $st = $req->getStatus();
                    switch ($st) {
                        case 'Pending':   $stColor = 'text-amber-400 bg-amber-500/10 border-amber-500/30'; break;
                        case 'Approved':  $stColor = 'text-blue-400 bg-blue-500/10 border-blue-500/30'; break;
                        case 'Active':    $stColor = 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30'; break;
                        case 'Completed': $stColor = 'text-purple-400 bg-purple-500/10 border-purple-500/30'; break;
                        default:          $stColor = 'text-rose-400 bg-rose-500/10 border-rose-500/30'; break;
                    }
                ?>
                    <div class="p-4 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-slate-850/50 transition">
                        <div class="flex items-center space-x-4">
                            <div class="w-14 h-14 rounded-xl overflow-hidden bg-slate-950 border border-slate-800 flex-shrink-0">
                                <img src="<?= base_url($req->getPrimaryImage()) ?>" alt="" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <h3 class="font-bold text-white text-sm">
                                    <a href="<?= base_url('renter/product_details.php?id=' . $req->getProductID()) ?>" class="hover:text-blue-400">
                                        <?= htmlspecialchars($req->getProductTitle()) ?>
                                    </a>
                                </h3>
                                <div class="text-xs text-slate-400 mt-0.5">
                                    <span><?= date('M d', strtotime($req->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($req->getEndDate())) ?></span>
                                    <span>&bull;</span>
                                    <span><?= $req->getTotalDays() ?> days</span>
                                    <span>&bull;</span>
                                    <span class="text-slate-200 font-semibold">₹<?= number_format($req->getTotalAmount(), 2) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3 self-end sm:self-auto">
                            <span class="px-2.5 py-1 rounded-full text-xs font-semibold border <?= $stColor ?>">
                                <?= htmlspecialchars($st) ?>
                            </span>
                            <a href="<?= base_url('renter/my_rentals.php') ?>" class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300 rounded-lg transition">
                                Details &rarr;
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
