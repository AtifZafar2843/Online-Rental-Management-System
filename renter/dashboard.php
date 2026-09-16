<?php
/**
 * Online Rental Management System (ORMS)
 * Renter Workspace Dashboard
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Welcome Card -->
    <div class="bg-gradient-to-br from-midnight via-[#131b33] to-midnight rounded-3xl p-6 sm:p-10 text-white mb-8 sm:mb-10 shadow-xl relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-coral/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/10 text-lilac rounded-full text-xs font-semibold tracking-wider uppercase backdrop-blur-sm border border-white/10">
                <i class="ri-user-smile-line text-coral"></i>
                Renter Workspace
            </span>
            <h1 class="text-2xl sm:text-4xl font-display font-bold mt-3 tracking-tight">
                Welcome back, <?= htmlspecialchars(current_user_name()) ?>!
            </h1>
            <p class="text-slate-300 text-xs sm:text-sm mt-1 max-w-xl">
                Discover quality equipment, manage live bookings, complete payments, and share your rental reviews.
            </p>
        </div>
        <div class="relative z-10 flex-shrink-0">
            <a href="<?= base_url('renter/search.php') ?>" 
               class="inline-flex items-center gap-2 px-6 py-3.5 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition transform hover:-translate-y-0.5">
                <i class="ri-search-line text-base"></i>
                <span>Explore Catalog</span>
            </a>
        </div>
    </div>

    <!-- Quick Metrics Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8 sm:mb-10">
        <div class="bg-white border border-[#E9E7FF] p-5 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Pending</span>
                <div class="w-8 h-8 rounded-full bg-amber-50 text-amber-600 flex items-center justify-center">
                    <i class="ri-time-line text-base"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-display font-bold text-midnight mt-3"><?= $pendingCount ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Awaiting owner review</span>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-5 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Approved</span>
                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="ri-checkbox-circle-line text-base"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-display font-bold text-midnight mt-3"><?= $approvedCount ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Ready for checkout</span>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-5 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active</span>
                <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="ri-key-line text-base"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-display font-bold text-midnight mt-3"><?= $activeCount ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Currently with you</span>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-5 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Spent</span>
                <div class="w-8 h-8 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i class="ri-wallet-3-line text-base"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-display font-bold text-midnight mt-3">₹<?= number_format($totalSpent, 2) ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Completed rentals</span>
        </div>
    </div>

    <!-- Recent Bookings Section -->
    <div class="bg-white border border-[#E9E7FF] rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 border-b border-[#E9E7FF] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg sm:text-xl font-display font-bold text-midnight tracking-tight">Recent Rental Requests</h2>
                <p class="text-xs text-slate-500 mt-0.5">Your most recent rental inquiries, bookings, and active contracts.</p>
            </div>
            <div>
                <a href="<?= base_url('renter/my_rentals.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-coral hover:text-[#e04e53] transition">
                    <span>View All Bookings (<?= count($requests) ?>)</span>
                    <i class="ri-arrow-right-line"></i>
                </a>
            </div>
        </div>

        <?php if (empty($requests)): ?>
            <div class="p-12 sm:p-16 text-center text-slate-500">
                <div class="w-16 h-16 mx-auto rounded-full bg-lilac/40 flex items-center justify-center text-coral mb-4">
                    <i class="ri-archive-line text-3xl"></i>
                </div>
                <h3 class="text-base font-display font-bold text-midnight">No rental requests submitted yet</h3>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">Browse our verified catalog to request cameras, laptops, furniture, and more.</p>
                <a href="<?= base_url('renter/search.php') ?>" class="inline-flex items-center gap-2 mt-5 px-6 py-2.5 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition">
                    <i class="ri-compass-3-line"></i>
                    <span>Browse Catalog</span>
                </a>
            </div>
        <?php else: ?>
            <div class="divide-y divide-[#E9E7FF]">
                <?php foreach (array_slice($requests, 0, 5) as $req): 
                    $st = $req->getStatus();
                    switch ($st) {
                        case 'Pending':   $stBadge = 'bg-amber-50 text-amber-700 border-amber-200'; break;
                        case 'Approved':  $stBadge = 'bg-blue-50 text-blue-700 border-blue-200'; break;
                        case 'Active':    $stBadge = 'bg-emerald-50 text-emerald-700 border-emerald-200'; break;
                        case 'Completed': $stBadge = 'bg-purple-50 text-purple-700 border-purple-200'; break;
                        default:          $stBadge = 'bg-rose-50 text-rose-700 border-rose-200'; break;
                    }
                ?>
                    <div class="p-5 sm:p-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-[#FAF8F5] transition">
                        <div class="flex items-center gap-4">
                            <div class="w-16 h-16 rounded-2xl overflow-hidden bg-slate-100 border border-[#E9E7FF] flex-shrink-0">
                                <img src="<?= base_url($req->getPrimaryImage()) ?>" alt="" class="w-full h-full object-cover">
                            </div>
                            <div>
                                <h3 class="font-display font-bold text-midnight text-sm sm:text-base">
                                    <a href="<?= base_url('renter/product_details.php?id=' . $req->getProductID()) ?>" class="hover:text-coral transition">
                                        <?= htmlspecialchars($req->getProductTitle()) ?>
                                    </a>
                                </h3>
                                <div class="text-xs text-slate-500 mt-1 flex flex-wrap items-center gap-2">
                                    <span><i class="ri-calendar-line text-slate-400"></i> <?= date('M d', strtotime($req->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($req->getEndDate())) ?></span>
                                    <span>&bull;</span>
                                    <span><?= $req->getTotalDays() ?> days</span>
                                    <span>&bull;</span>
                                    <span class="text-midnight font-bold">₹<?= number_format($req->getTotalAmount(), 2) ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 self-end sm:self-auto">
                            <span class="px-3 py-1 rounded-full text-xs font-semibold border <?= $stBadge ?>">
                                <?= htmlspecialchars($st) ?>
                            </span>
                            <a href="<?= base_url('renter/my_rentals.php') ?>" class="px-4 py-2 bg-slate-100 hover:bg-lilac/30 text-xs font-semibold text-midnight rounded-full border border-[#E9E7FF] transition">
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
