<?php
/**
 * Online Rental Management System (ORMS)
 * Renter My Rentals History & Active Requests Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 6) & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Renter.php';

require_role('Renter');

$renterId = (int) current_user_id();

$statusFilter = trim($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'Pending', 'Approved', 'Active', 'Completed', 'Rejected', 'Cancelled'], true)) {
    $statusFilter = 'all';
}

$allRequests = RentalRequest::findByRenter($renterId);

// Filter requests if a specific tab is chosen
$filteredRequests = [];
$counts = [
    'all'       => count($allRequests),
    'Pending'   => 0,
    'Approved'  => 0,
    'Active'    => 0,
    'Completed' => 0,
    'Rejected'  => 0,
    'Cancelled' => 0,
];

foreach ($allRequests as $r) {
    $st = $r->getStatus();
    if (isset($counts[$st])) {
        $counts[$st]++;
    }

    if ($statusFilter === 'all' || $statusFilter === $st) {
        $filteredRequests[] = $r;
    }
}

$pageTitle = 'My Rental Bookings — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-white">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('renter/dashboard.php') ?>" class="hover:text-white">Renter Workspace</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">My Rentals</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">My Rental Bookings</h1>
            <p class="text-sm text-slate-400 mt-1">Track pending approval requests, approved items ready for payment, and active rentals.</p>
        </div>

        <a href="<?= base_url('renter/search.php') ?>" 
           class="inline-flex items-center space-x-2 px-5 py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition self-start md:self-auto">
            <span>🔍</span>
            <span>Explore More Items</span>
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-4 mb-8 border-b border-slate-800 scrollbar-thin">
        <?php
        $tabs = [
            'all'       => ['label' => 'All Bookings', 'count' => $counts['all']],
            'Pending'   => ['label' => 'Pending',      'count' => $counts['Pending']],
            'Approved'  => ['label' => 'Approved',     'count' => $counts['Approved']],
            'Active'    => ['label' => 'Active',       'count' => $counts['Active']],
            'Completed' => ['label' => 'Completed',    'count' => $counts['Completed']],
            'Rejected'  => ['label' => 'Rejected',     'count' => $counts['Rejected']],
            'Cancelled' => ['label' => 'Cancelled',    'count' => $counts['Cancelled']],
        ];
        foreach ($tabs as $key => $tab):
            $isActive = ($statusFilter === $key);
            $activeClass = $isActive 
                ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/20' 
                : 'bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white border border-slate-800';
        ?>
            <a href="<?= base_url('renter/my_rentals.php?status=' . $key) ?>" 
               class="px-4 py-2 rounded-xl text-xs font-medium whitespace-nowrap transition flex items-center space-x-2 <?= $activeClass ?>">
                <span><?= $tab['label'] ?></span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400' ?>">
                    <?= $tab['count'] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Rental Requests List -->
    <?php if (empty($filteredRequests)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center shadow-xl">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/80 flex items-center justify-center text-2xl text-slate-400 mb-4">
                📦
            </div>
            <h3 class="text-base font-bold text-white">No rental bookings found</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                You haven't requested any items under the "<?= htmlspecialchars(ucfirst($statusFilter)) ?>" category.
            </p>
            <div class="mt-6">
                <a href="<?= base_url('renter/search.php') ?>" class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition">
                    Browse Rental Catalog
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($filteredRequests as $req): 
                $st = $req->getStatus();
                $totalDays = $req->getTotalDays();
                $rentAmount = $req->getTotalAmount();
                $deposit = $req->getSecurityDeposit();

                switch ($st) {
                    case 'Pending':
                        $badgeBg = 'bg-amber-950/80 text-amber-300 border-amber-700/60';
                        $dotColor = 'bg-amber-400 animate-pulse';
                        break;
                    case 'Approved':
                        $badgeBg = 'bg-blue-950/80 text-blue-300 border-blue-700/60';
                        $dotColor = 'bg-blue-400';
                        break;
                    case 'Active':
                        $badgeBg = 'bg-emerald-950/80 text-emerald-300 border-emerald-700/60';
                        $dotColor = 'bg-emerald-400';
                        break;
                    case 'Completed':
                        $badgeBg = 'bg-purple-950/80 text-purple-300 border-purple-700/60';
                        $dotColor = 'bg-purple-400';
                        break;
                    case 'Rejected':
                    case 'Cancelled':
                        $badgeBg = 'bg-rose-950/80 text-rose-300 border-rose-700/60';
                        $dotColor = 'bg-rose-400';
                        break;
                    default:
                        $badgeBg = 'bg-slate-800 text-slate-300 border-slate-700';
                        $dotColor = 'bg-slate-400';
                }
            ?>
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-7 shadow-xl hover:border-slate-700/80 transition flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                    <!-- Left: Product info -->
                    <div class="flex items-start space-x-4 sm:space-x-5 flex-1 min-w-0">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-xl overflow-hidden bg-slate-950 border border-slate-800 flex-shrink-0">
                            <img src="<?= base_url($req->getPrimaryImage()) ?>" 
                                 alt="<?= htmlspecialchars($req->getProductTitle()) ?>" 
                                 class="w-full h-full object-cover"
                                 onerror="this.src='<?= base_url('assets/img/no-image.svg') ?>'">
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1 mb-1">
                                <span class="text-[11px] font-mono text-slate-500">#REQ-<?= $req->getRequestID() ?></span>
                                <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border <?= $badgeBg ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $dotColor ?>"></span>
                                    <span><?= htmlspecialchars($st) ?></span>
                                </span>
                                <span class="text-xs text-slate-400">&bull;</span>
                                <span class="text-xs text-slate-400">Booked on <?= date('M d, Y', strtotime($req->getRequestDate())) ?></span>
                            </div>

                            <h3 class="text-lg font-bold text-white truncate">
                                <a href="<?= base_url('renter/product_details.php?id=' . $req->getProductID()) ?>" class="hover:text-blue-400 transition">
                                    <?= htmlspecialchars($req->getProductTitle()) ?>
                                </a>
                            </h3>

                            <div class="mt-1 flex items-center space-x-3 text-xs text-slate-400">
                                <span>📍 <?= htmlspecialchars($req->getProductLocation()) ?></span>
                                <span>&bull;</span>
                                <span>Owner: <strong class="text-slate-200"><?= htmlspecialchars($req->getOwnerName()) ?></strong></span>
                            </div>

                            <?php if (!empty($req->getMessage())): ?>
                                <div class="mt-2 text-xs text-slate-400 italic">
                                    "<?= htmlspecialchars($req->getMessage()) ?>"
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($req->getCancellationReason())): ?>
                                <div class="mt-2.5 p-2 rounded-lg bg-rose-950/40 border border-rose-900/50 text-xs text-rose-300">
                                    <span class="font-semibold">Reason:</span> <?= htmlspecialchars($req->getCancellationReason()) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Middle: Pricing and Schedule Card -->
                    <div class="w-full lg:w-auto p-4 rounded-xl bg-slate-950/60 border border-slate-800 flex-shrink-0 text-xs space-y-1.5 min-w-[210px]">
                        <div class="flex justify-between text-slate-400">
                            <span>Dates:</span>
                            <span class="font-semibold text-white"><?= date('M d', strtotime($req->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($req->getEndDate())) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Duration:</span>
                            <span class="font-bold text-white"><?= $totalDays ?> day(s)</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Rent (₹<?= number_format($req->getRentPerDay(), 2) ?>/d):</span>
                            <span class="font-bold text-white">₹<?= number_format($rentAmount, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Security Deposit:</span>
                            <span class="font-bold text-emerald-400">₹<?= number_format($deposit, 2) ?></span>
                        </div>
                        <div class="pt-1.5 border-t border-slate-800 flex justify-between font-bold">
                            <span class="text-slate-300">Total Payable:</span>
                            <span class="text-blue-400 text-sm">₹<?= number_format($rentAmount + $deposit, 2) ?></span>
                        </div>
                    </div>

                    <!-- Right: Dynamic Actions -->
                    <div class="w-full lg:w-auto flex lg:flex-col items-center justify-end gap-2 flex-shrink-0">
                        <?php if ($st === 'Pending'): ?>
                            <!-- Renter can cancel pending requests anytime for free -->
                            <button type="button" 
                                    onclick="openCancelModal(<?= $req->getRequestID() ?>, '<?= htmlspecialchars(addslashes($req->getProductTitle())) ?>', 'Pending')"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-rose-950/60 hover:bg-rose-900/80 border border-rose-800/80 text-rose-300 font-bold text-xs uppercase tracking-wider rounded-xl transition flex items-center justify-center space-x-1">
                                <span>✕</span>
                                <span>Cancel Request</span>
                            </button>

                        <?php elseif ($st === 'Approved'): ?>
                            <!-- Ready for payment (Step 7) -->
                            <a href="<?= base_url('renter/pay.php?request_id=' . $req->getRequestID()) ?>" 
                               class="w-full sm:w-auto px-5 py-2.5 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-500/25 transition flex items-center justify-center space-x-1.5">
                                <span>💳</span>
                                <span>Proceed to Pay &rarr;</span>
                            </a>
                            <!-- Cancellation before payment is free per Rule 6 -->
                            <button type="button" 
                                    onclick="openCancelModal(<?= $req->getRequestID() ?>, '<?= htmlspecialchars(addslashes($req->getProductTitle())) ?>', 'Approved')"
                                    class="w-full sm:w-auto text-xs text-rose-400 hover:text-rose-300 underline py-1">
                                Cancel Booking
                            </button>

                        <?php elseif ($st === 'Active'): ?>
                            <div class="text-center px-4 py-2 rounded-xl bg-emerald-950/40 border border-emerald-900/60 text-xs text-emerald-300">
                                <div class="font-semibold">Currently Active</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">Return by <?= date('M d, Y', strtotime($req->getEndDate())) ?></div>
                            </div>
                            <?php 
                                require_once __DIR__ . '/../classes/Transaction.php';
                                $activeTx = Transaction::findByRequest($req->getRequestID());
                                if ($activeTx):
                            ?>
                                <a href="<?= base_url('renter/receipt.php?id=' . $activeTx->getTransactionID()) ?>" 
                                   class="text-xs text-blue-400 hover:text-blue-300 font-medium underline">
                                    View Receipt &rarr;
                                </a>
                            <?php endif; ?>

                        <?php elseif ($st === 'Completed'): ?>
                            <?php
                                require_once __DIR__ . '/../classes/Transaction.php';
                                require_once __DIR__ . '/../classes/Fine.php';
                                $compTx = Transaction::findByRequest($req->getRequestID());
                                $fines = Fine::findByRequest($req->getRequestID());
                            ?>
                            <div class="text-center px-4 py-2 rounded-xl bg-slate-950/80 border border-slate-800 text-xs space-y-1">
                                <div class="font-bold text-emerald-400">Rental Completed</div>
                                <?php if ($compTx): ?>
                                    <div class="text-[10px] text-slate-400">
                                        Deposit: <span class="text-slate-200 font-semibold"><?= str_replace('_', ' ', $compTx->getDepositStatus()) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php foreach ($fines as $fn): ?>
                                <?php if ($fn->getStatus() === 'Unpaid'): ?>
                                    <a href="<?= base_url('renter/pay_fine.php?fine_id=' . $fn->getFineID()) ?>" 
                                       class="w-full sm:w-auto px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-md shadow-rose-600/25 transition flex items-center justify-center space-x-1.5 animate-pulse">
                                        <span>⚠️</span>
                                        <span>Pay Fine ₹<?= number_format($fn->getAmount(), 2) ?></span>
                                    </a>
                                <?php else: ?>
                                    <div class="text-[10px] px-2.5 py-1 rounded-lg bg-slate-800 text-slate-300 border border-slate-700">
                                        Fine: ₹<?= number_format($fn->getAmount(), 2) ?> (<?= str_replace('_', ' ', $fn->getStatus()) ?>)
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if ($compTx): ?>
                                <a href="<?= base_url('renter/receipt.php?id=' . $compTx->getTransactionID()) ?>" 
                                   class="text-xs text-blue-400 hover:text-blue-300 font-medium underline">
                                    View Receipt &rarr;
                                </a>
                            <?php endif; ?>

                            <?php
                                require_once __DIR__ . '/../classes/Review.php';
                                $rev = Review::findByRequest($req->getRequestID());
                                if ($rev):
                            ?>
                                <a href="<?= base_url('renter/submit_review.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3 py-1.5 bg-amber-500/10 hover:bg-amber-500/20 border border-amber-500/30 text-amber-300 font-semibold text-xs rounded-xl transition flex items-center space-x-1.5">
                                    <span>★ <?= $rev->getRating() ?>/5</span>
                                    <span class="text-[10px] text-slate-400">Reviewed</span>
                                </a>
                            <?php else: ?>
                                <a href="<?= base_url('renter/submit_review.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs rounded-xl shadow-md shadow-amber-500/20 transition flex items-center space-x-1">
                                    <span>⭐</span>
                                    <span>Write Review</span>
                                </a>
                            <?php endif; ?>

                        <?php else: ?>
                            <span class="text-xs text-slate-500 italic">Booking Closed</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Cancel Request Confirmation -->
<div id="cancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm hidden p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-base font-bold text-white flex items-center space-x-2">
                <span class="text-rose-400">⚠️</span>
                <span>Cancel Rental Request</span>
            </h3>
            <button type="button" onclick="closeCancelModal()" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
        </div>

        <p class="text-xs text-slate-300" id="cancelModalDesc">
            Are you sure you want to cancel this booking?
        </p>

        <form action="<?= base_url('renter/cancel_request.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" id="cancelRequestId" value="0">

            <div>
                <label for="cancellation_reason" class="block text-xs font-semibold text-slate-300 mb-1.5">
                    Reason for Cancellation <span class="text-slate-500 font-normal">(Optional)</span>
                </label>
                <textarea id="cancellation_reason" 
                          name="cancellation_reason" 
                          rows="2" 
                          placeholder="Change of schedule, found alternative, etc."
                          class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-white text-sm placeholder-slate-500 focus:ring-2 focus:ring-rose-500 focus:border-transparent outline-none transition"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-2">
                <button type="button" 
                        onclick="closeCancelModal()" 
                        class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition">
                    Keep Booking
                </button>
                <button type="submit" 
                        class="px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-rose-600/20 transition">
                    Confirm Cancellation
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCancelModal(requestId, productTitle, status) {
    document.getElementById('cancelRequestId').value = requestId;
    let desc = `Cancel request #${requestId} for "${productTitle}"? `;
    if (status === 'Pending') {
        desc += `This request is pending approval, so cancellation is 100% free with no penalties.`;
    } else {
        desc += `As per ORMS cancellation terms, cancellation before payment incurs no deduction.`;
    }
    document.getElementById('cancelModalDesc').textContent = desc;
    document.getElementById('cancellation_reason').value = '';
    document.getElementById('cancelModal').classList.remove('hidden');
}

function closeCancelModal() {
    document.getElementById('cancelModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
