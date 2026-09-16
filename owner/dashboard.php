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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Top Welcome Banner -->
    <div class="bg-gradient-to-br from-midnight via-[#131b33] to-midnight rounded-3xl p-6 sm:p-10 text-white mb-8 sm:mb-10 shadow-xl relative overflow-hidden flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="absolute -right-12 -bottom-12 w-64 h-64 bg-coral/10 rounded-full blur-3xl pointer-events-none"></div>
        <div class="relative z-10">
            <span class="inline-flex items-center gap-1.5 px-3 py-1 bg-white/10 text-lilac rounded-full text-xs font-semibold tracking-wider uppercase backdrop-blur-sm border border-white/10">
                <i class="ri-store-2-line text-coral"></i>
                Owner Workspace
            </span>
            <h1 class="text-2xl sm:text-4xl font-display font-bold mt-3 tracking-tight">
                Welcome back, <?= htmlspecialchars(current_user_name()) ?>!
            </h1>
            <p class="text-slate-300 text-xs sm:text-sm mt-1 max-w-xl">
                Manage your rental inventory, respond to renter inquiries, monitor item returns, and track your revenue.
            </p>
        </div>
        <div class="relative z-10 flex-shrink-0 flex flex-wrap items-center gap-3">
            <a href="<?= base_url('owner/manage_requests.php') ?>" 
               class="inline-flex items-center gap-2 px-5 py-3.5 bg-white/10 hover:bg-white/20 border border-white/20 text-white font-semibold text-xs uppercase tracking-wider rounded-full transition">
                <i class="ri-mail-line text-base"></i>
                <span>Manage Requests</span>
                <?php if ($pendingCount > 0): ?>
                    <span class="px-2 py-0.5 rounded-full bg-coral text-white font-bold text-[10px]">
                        <?= $pendingCount ?>
                    </span>
                <?php endif; ?>
            </a>
            <a href="<?= base_url('owner/add_product.php') ?>" 
               class="inline-flex items-center gap-2 px-6 py-3.5 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition transform hover:-translate-y-0.5">
                <i class="ri-add-line text-base"></i>
                <span>List New Product</span>
            </a>
        </div>
    </div>

    <?php if ($pendingCount > 0): ?>
        <div class="mb-8 p-4 sm:p-5 rounded-2xl bg-amber-50 border border-amber-200 text-amber-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
            <div class="flex items-center space-x-3">
                <span class="w-2.5 h-2.5 rounded-full bg-amber-500 animate-pulse"></span>
                <span class="text-xs sm:text-sm font-semibold">You have <strong><?= $pendingCount ?></strong> pending rental request(s) awaiting your decision.</span>
            </div>
            <a href="<?= base_url('owner/manage_requests.php?status=Pending') ?>" 
               class="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-midnight font-bold text-xs rounded-full transition self-start sm:self-auto">
                Review Requests &rarr;
            </a>
        </div>
    <?php endif; ?>

    <!-- Metrics Cards Grid -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6 mb-8 sm:mb-10">
        <div class="bg-white border border-[#E9E7FF] p-5 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Listings</span>
                <div class="w-8 h-8 rounded-full bg-slate-100 text-slate-600 flex items-center justify-center">
                    <i class="ri-archive-line text-base"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-display font-bold text-midnight mt-3"><?= $totalProducts ?></div>
            <span class="text-xs text-slate-400 mt-1 block">In your inventory</span>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-5 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Available</span>
                <div class="w-8 h-8 rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center">
                    <i class="ri-checkbox-circle-line text-base"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-display font-bold text-midnight mt-3"><?= $availableCount ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Ready for rent</span>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-5 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Rented Out</span>
                <div class="w-8 h-8 rounded-full bg-blue-50 text-blue-600 flex items-center justify-center">
                    <i class="ri-key-line text-base"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-display font-bold text-midnight mt-3"><?= $rentedCount ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Active on rent</span>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-5 sm:p-6 rounded-2xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Total Earnings</span>
                <div class="w-8 h-8 rounded-full bg-purple-50 text-purple-600 flex items-center justify-center">
                    <i class="ri-wallet-3-line text-base"></i>
                </div>
            </div>
            <div class="text-2xl sm:text-3xl font-display font-bold text-midnight mt-3">₹<?= number_format($totalEarnings, 2) ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Completed rentals</span>
        </div>
    </div>

    <!-- Product Inventory Section -->
    <div class="bg-white border border-[#E9E7FF] rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 sm:p-8 border-b border-[#E9E7FF] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg sm:text-xl font-display font-bold text-midnight tracking-tight">Your Product Inventory</h2>
                <p class="text-xs text-slate-500 mt-0.5">All products currently listed under your owner account.</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="<?= base_url('owner/add_product.php') ?>" class="inline-flex items-center gap-1.5 text-xs font-semibold text-coral hover:text-[#e04e53] transition">
                    <i class="ri-add-circle-line text-base"></i>
                    <span>Add Another Listing</span>
                </a>
            </div>
        </div>

        <?php if (empty($products)): ?>
            <!-- Empty State -->
            <div class="text-center py-16 px-4">
                <div class="w-16 h-16 rounded-full bg-lilac/40 mx-auto flex items-center justify-center text-coral text-3xl mb-4">
                    <i class="ri-box-3-line"></i>
                </div>
                <h3 class="text-base font-display font-bold text-midnight">No products listed yet</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1 mb-6">
                    Start earning by listing your idle cameras, electronics, vehicles, or equipment for rent today.
                </p>
                <a href="<?= base_url('owner/add_product.php') ?>" 
                   class="inline-flex items-center gap-2 px-6 py-3 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition">
                    <i class="ri-add-line"></i>
                    <span>List Your First Product</span>
                </a>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-[#FAF8F5] text-slate-500 uppercase tracking-wider font-semibold border-b border-[#E9E7FF]">
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
                    <tbody class="divide-y divide-[#E9E7FF]">
                        <?php foreach ($products as $prod): 
                            $primaryImg = $prod->getPrimaryImagePath();
                            $status = $prod->getAvailStatus();

                            $statusBadge = 'bg-slate-100 text-slate-600 border border-slate-200';
                            if ($status === 'Available') {
                                $statusBadge = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                            } elseif ($status === 'Rented') {
                                $statusBadge = 'bg-blue-50 text-blue-700 border border-blue-200';
                            } elseif ($status === 'Unavailable') {
                                $statusBadge = 'bg-amber-50 text-amber-700 border border-amber-200';
                            }
                        ?>
                            <tr class="hover:bg-[#FAF8F5] transition">
                                <td class="py-3.5 px-4">
                                    <div class="flex items-center space-x-3">
                                        <img src="<?= base_url($primaryImg) ?>" alt="Product" 
                                             class="w-12 h-12 rounded-xl object-cover bg-slate-100 border border-[#E9E7FF] flex-shrink-0"
                                             onerror="this.src='<?= base_url('assets/img/no-image.svg') ?>'">
                                        <div>
                                            <div class="font-display font-bold text-midnight line-clamp-1"><?= htmlspecialchars($prod->getTitle()) ?></div>
                                            <div class="text-[11px] text-slate-400 flex items-center space-x-1 mt-0.5">
                                                <i class="ri-map-pin-line"></i>
                                                <span><?= htmlspecialchars($prod->getLocation()) ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="py-3.5 px-4 font-medium text-slate-700">
                                    <?= htmlspecialchars($prod->getCategoryName() ?? 'Category') ?>
                                </td>
                                <td class="py-3.5 px-4 font-bold text-midnight">
                                    ₹<?= number_format($prod->getRentPerDay(), 2) ?>
                                </td>
                                <td class="py-3.5 px-4 text-slate-500">
                                    ₹<?= number_format($prod->getSecurityDeposit(), 2) ?>
                                </td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
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
                                           class="px-3 py-1.5 rounded-full bg-slate-100 text-slate-700 hover:bg-slate-200 font-medium transition">
                                            Edit
                                        </a>

                                        <!-- Toggle Status Link -->
                                        <?php if ($status !== 'Rented'): ?>
                                            <a href="<?= base_url("owner/toggle_status.php?id={$prod->getProductID()}") ?>" 
                                                title="Toggle Available / Unavailable"
                                                class="px-3 py-1.5 rounded-full bg-slate-100 hover:bg-slate-200 text-slate-700 font-medium transition">
                                                <?= ($status === 'Available') ? 'Deactivate' : 'Activate' ?>
                                            </a>

                                            <!-- Delete Button -->
                                            <button type="button" 
                                                    onclick="openDeleteModal(<?= $prod->getProductID() ?>, '<?= htmlspecialchars(addslashes($prod->getTitle())) ?>')"
                                                    title="Permanently Delete Listing"
                                                    class="px-3 py-1.5 rounded-full bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 font-medium transition">
                                                Delete
                                            </button>
                                        <?php else: ?>
                                            <button type="button" 
                                                    disabled 
                                                    title="Cannot delete item while rented"
                                                    class="px-3 py-1.5 rounded-full bg-slate-50 text-slate-400 border border-slate-200 cursor-not-allowed font-medium text-xs">
                                                Delete
                                            </button>
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

<!-- Delete Product Modal -->
<div id="deleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden p-4">
    <div class="max-w-md w-full p-6 sm:p-8 border border-[#E9E7FF] bg-white rounded-3xl shadow-2xl space-y-4">
        <div class="flex items-center space-x-3 text-rose-600">
            <i class="ri-delete-bin-line text-2xl"></i>
            <h3 class="text-lg font-display font-bold text-midnight">Delete Product Listing</h3>
        </div>
        <p class="text-xs text-slate-600">
            Are you sure you want to permanently delete <strong id="deleteProdTitle" class="text-midnight"></strong>?
        </p>
        <div class="p-3.5 bg-rose-50 border border-rose-200 rounded-2xl text-[11px] text-rose-700 space-y-1">
            <div>• This action will permanently remove the product and its uploaded images from disk.</div>
            <div>• Deletion will fail if there are any active, pending, or approved rental requests.</div>
        </div>
        <form method="POST" action="<?= base_url('owner/delete_product.php') ?>" class="flex items-center justify-end space-x-3 pt-2">
            <?= csrf_field() ?>
            <input type="hidden" name="product_id" id="deleteProdId" value="">
            <button type="button" onclick="closeDeleteModal()" class="px-5 py-2.5 rounded-full text-xs font-semibold text-slate-600 hover:text-midnight bg-slate-100 transition">
                Cancel
            </button>
            <button type="submit" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs uppercase tracking-wider rounded-full transition shadow-sm">
                Delete Permanently
            </button>
        </form>
    </div>
</div>

<script>
function openDeleteModal(id, title) {
    document.getElementById('deleteProdId').value = id;
    document.getElementById('deleteProdTitle').innerText = title;
    document.getElementById('deleteModal').classList.remove('hidden');
}
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
