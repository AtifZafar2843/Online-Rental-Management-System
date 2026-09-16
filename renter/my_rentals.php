<?php
/**
 * Online Rental Management System (ORMS)
 * Renter My Rentals History & Active Requests Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Renter.php';
require_once __DIR__ . '/../classes/Dispute.php';

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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-coral transition">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('renter/dashboard.php') ?>" class="hover:text-coral transition">Renter Workspace</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">My Rentals</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">My Rental Bookings</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Track pending approval requests, approved items ready for payment, and active rentals.</p>
        </div>

        <a href="<?= base_url('renter/search.php') ?>" 
           class="inline-flex items-center gap-2 px-5 py-2.5 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition self-start md:self-auto">
            <i class="ri-search-line"></i>
            <span>Explore More Items</span>
        </a>
    </div>

    <!-- Filter Tabs -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-4 mb-8 border-b border-[#E9E7FF] scrollbar-thin">
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
                ? 'bg-midnight text-white font-semibold shadow-sm' 
                : 'bg-white hover:bg-slate-50 text-slate-600 hover:text-midnight border border-[#E9E7FF]';
        ?>
            <a href="<?= base_url('renter/my_rentals.php?status=' . $key) ?>" 
               class="px-4 py-2 rounded-full text-xs font-medium whitespace-nowrap transition flex items-center space-x-2 <?= $activeClass ?>">
                <span><?= $tab['label'] ?></span>
                <span class="px-2 py-0.5 rounded-full text-[10px] <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-100 text-slate-500' ?>">
                    <?= $tab['count'] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Rental Requests List -->
    <?php if (empty($filteredRequests)): ?>
        <div class="bg-white border border-[#E9E7FF] rounded-3xl p-12 sm:p-16 text-center shadow-sm">
            <div class="w-16 h-16 mx-auto rounded-full bg-lilac/40 flex items-center justify-center text-coral mb-4">
                <i class="ri-inbox-line text-3xl"></i>
            </div>
            <h3 class="text-base font-display font-bold text-midnight">No rental bookings found</h3>
            <p class="text-xs text-slate-500 mt-1 max-w-md mx-auto">
                You haven't requested any items under the "<?= htmlspecialchars(ucfirst($statusFilter)) ?>" category.
            </p>
            <div class="mt-6">
                <a href="<?= base_url('renter/search.php') ?>" class="inline-flex items-center gap-2 px-6 py-2.5 bg-coral hover:bg-[#e04e53] text-white text-xs font-semibold uppercase tracking-wider rounded-full shadow-glow-coral transition">
                    <i class="ri-compass-3-line"></i>
                    <span>Browse Catalog</span>
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
                        $badgeBg = 'bg-amber-50 text-amber-700 border-amber-200';
                        $dotColor = 'bg-amber-500 animate-pulse';
                        break;
                    case 'Approved':
                        $badgeBg = 'bg-blue-50 text-blue-700 border-blue-200';
                        $dotColor = 'bg-blue-500';
                        break;
                    case 'Active':
                        $badgeBg = 'bg-emerald-50 text-emerald-700 border-emerald-200';
                        $dotColor = 'bg-emerald-500';
                        break;
                    case 'Completed':
                        $badgeBg = 'bg-purple-50 text-purple-700 border-purple-200';
                        $dotColor = 'bg-purple-500';
                        break;
                    case 'Rejected':
                    case 'Cancelled':
                        $badgeBg = 'bg-rose-50 text-rose-700 border-rose-200';
                        $dotColor = 'bg-rose-500';
                        break;
                    default:
                        $badgeBg = 'bg-slate-100 text-slate-600 border-slate-200';
                        $dotColor = 'bg-slate-400';
                }
            ?>
                <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-7 shadow-sm hover:shadow-md transition flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                    <!-- Left: Product info -->
                    <div class="flex items-start space-x-4 sm:space-x-5 flex-1 min-w-0">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl overflow-hidden bg-slate-100 border border-[#E9E7FF] flex-shrink-0">
                            <img src="<?= base_url($req->getPrimaryImage()) ?>" 
                                 alt="<?= htmlspecialchars($req->getProductTitle()) ?>" 
                                 class="w-full h-full object-cover"
                                 onerror="this.src='<?= base_url('assets/img/no-image.svg') ?>'">
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1 mb-1.5">
                                <span class="text-[11px] font-mono text-slate-400">#REQ-<?= $req->getRequestID() ?></span>
                                <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border <?= $badgeBg ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $dotColor ?>"></span>
                                    <span><?= htmlspecialchars($st) ?></span>
                                </span>
                                <span class="text-xs text-slate-300">&bull;</span>
                                <span class="text-xs text-slate-500">Booked on <?= date('M d, Y', strtotime($req->getRequestDate())) ?></span>
                            </div>

                            <h3 class="text-base sm:text-lg font-display font-bold text-midnight truncate">
                                <a href="<?= base_url('renter/product_details.php?id=' . $req->getProductID()) ?>" class="hover:text-coral transition">
                                    <?= htmlspecialchars($req->getProductTitle()) ?>
                                </a>
                            </h3>

                            <div class="mt-1 flex items-center space-x-3 text-xs text-slate-500">
                                <span><i class="ri-map-pin-line text-slate-400"></i> <?= htmlspecialchars($req->getProductLocation()) ?></span>
                                <span>&bull;</span>
                                <span>Owner: <strong class="text-midnight"><?= htmlspecialchars($req->getOwnerName()) ?></strong></span>
                            </div>

                            <?php if (!empty($req->getMessage())): ?>
                                <div class="mt-2 text-xs text-slate-500 italic bg-slate-50 p-2 rounded-xl border border-slate-100">
                                    "<?= htmlspecialchars($req->getMessage()) ?>"
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($req->getCancellationReason())): ?>
                                <div class="mt-2.5 p-2 rounded-xl bg-rose-50 border border-rose-100 text-xs text-rose-700">
                                    <span class="font-semibold">Reason:</span> <?= htmlspecialchars($req->getCancellationReason()) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Middle: Pricing and Schedule Card -->
                    <div class="w-full lg:w-auto p-4 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] flex-shrink-0 text-xs space-y-1.5 min-w-[220px]">
                        <div class="flex justify-between text-slate-500">
                            <span>Dates:</span>
                            <span class="font-semibold text-midnight"><?= date('M d', strtotime($req->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($req->getEndDate())) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Duration:</span>
                            <span class="font-bold text-midnight"><?= $totalDays ?> day(s)</span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Rent (₹<?= number_format($req->getRentPerDay(), 2) ?>/d):</span>
                            <span class="font-bold text-midnight">₹<?= number_format($rentAmount, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Security Deposit:</span>
                            <span class="font-bold text-emerald-600">₹<?= number_format($deposit, 2) ?></span>
                        </div>
                        <div class="pt-1.5 border-t border-[#E9E7FF] flex justify-between font-bold">
                            <span class="text-midnight">Total Payable:</span>
                            <span class="text-coral text-sm">₹<?= number_format($rentAmount + $deposit, 2) ?></span>
                        </div>
                    </div>

                    <!-- Right: Dynamic Actions -->
                    <div class="w-full lg:w-auto flex lg:flex-col items-center justify-end gap-2 flex-shrink-0">
                        <?php if ($st === 'Pending'): ?>
                            <button type="button" 
                                    onclick="openCancelModal(<?= $req->getRequestID() ?>, '<?= htmlspecialchars(addslashes($req->getProductTitle())) ?>', 'Pending')"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-rose-50 hover:bg-rose-100 border border-rose-200 text-rose-700 font-semibold text-xs uppercase tracking-wider rounded-full transition flex items-center justify-center space-x-1">
                                <i class="ri-close-line text-sm"></i>
                                <span>Cancel Request</span>
                            </button>

                        <?php elseif ($st === 'Approved'): ?>
                            <a href="<?= base_url('renter/pay.php?request_id=' . $req->getRequestID()) ?>" 
                               class="w-full sm:w-auto px-5 py-2.5 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition flex items-center justify-center space-x-1.5">
                                <i class="ri-secure-payment-line text-sm"></i>
                                <span>Proceed to Pay &rarr;</span>
                            </a>
                            <button type="button" 
                                    onclick="openCancelModal(<?= $req->getRequestID() ?>, '<?= htmlspecialchars(addslashes($req->getProductTitle())) ?>', 'Approved')"
                                    class="w-full sm:w-auto text-xs text-rose-600 hover:text-rose-700 underline py-1">
                                Cancel Booking
                            </button>

                        <?php elseif ($st === 'Active'): ?>
                            <div class="text-center px-4 py-2 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
                                <div class="font-semibold flex items-center justify-center gap-1">
                                    <i class="ri-checkbox-circle-fill text-emerald-500"></i>
                                    <span>Currently Active</span>
                                </div>
                                <div class="text-[10px] text-slate-500 mt-0.5">Return by <?= date('M d, Y', strtotime($req->getEndDate())) ?></div>
                            </div>
                            <?php 
                                require_once __DIR__ . '/../classes/Transaction.php';
                                $activeTx = Transaction::findByRequest($req->getRequestID());
                                if ($activeTx):
                            ?>
                                <a href="<?= base_url('renter/receipt.php?id=' . $activeTx->getTransactionID()) ?>" 
                                   class="text-xs text-slate-600 hover:text-coral font-medium underline">
                                    View Receipt &rarr;
                                </a>
                            <?php endif; ?>

                            <?php
                                $dispList = Dispute::findByRequest($req->getRequestID());
                                $hasDispute = !empty($dispList);
                                $activeDisp = $hasDispute ? end($dispList) : null;
                            ?>
                            <?php if ($hasDispute): ?>
                                <a href="<?= base_url('renter/file_dispute.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3 py-1.5 rounded-full text-xs font-semibold border flex items-center space-x-1.5 <?= $activeDisp->getStatus() === 'Resolved' ? 'bg-purple-50 border-purple-200 text-purple-700' : 'bg-rose-50 border-rose-200 text-rose-700' ?>">
                                    <i class="ri-scales-3-line"></i>
                                    <span>Dispute: <?= str_replace('_', ' ', $activeDisp->getStatus()) ?></span>
                                </a>
                            <?php else: ?>
                                <a href="<?= base_url('renter/file_dispute.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3 py-1.5 bg-slate-50 hover:bg-rose-50 text-slate-600 hover:text-rose-700 border border-slate-200 hover:border-rose-200 text-xs font-semibold rounded-full transition flex items-center space-x-1">
                                    <i class="ri-scales-3-line"></i>
                                    <span>Dispute</span>
                                </a>
                            <?php endif; ?>

                        <?php elseif ($st === 'Completed'): ?>
                            <?php
                                require_once __DIR__ . '/../classes/Transaction.php';
                                require_once __DIR__ . '/../classes/Fine.php';
                                $compTx = Transaction::findByRequest($req->getRequestID());
                                $fines = Fine::findByRequest($req->getRequestID());
                            ?>
                            <div class="text-center px-4 py-2 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] text-xs space-y-1">
                                <div class="font-bold text-emerald-600 flex items-center justify-center gap-1">
                                    <i class="ri-checkbox-circle-fill"></i>
                                    <span>Rental Completed</span>
                                </div>
                                <?php if ($compTx): ?>
                                    <div class="text-[10px] text-slate-500">
                                        Deposit: <span class="text-midnight font-semibold"><?= str_replace('_', ' ', $compTx->getDepositStatus()) ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php foreach ($fines as $fn): ?>
                                <?php if ($fn->getStatus() === 'Unpaid'): ?>
                                    <a href="<?= base_url('renter/pay_fine.php?fine_id=' . $fn->getFineID()) ?>" 
                                       class="w-full sm:w-auto px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-sm transition flex items-center justify-center space-x-1.5 animate-pulse">
                                        <i class="ri-error-warning-line"></i>
                                        <span>Pay Fine ₹<?= number_format($fn->getAmount(), 2) ?></span>
                                    </a>
                                <?php else: ?>
                                    <div class="text-[10px] px-2.5 py-1 rounded-full bg-slate-100 text-slate-600 border border-slate-200">
                                        Fine: ₹<?= number_format($fn->getAmount(), 2) ?> (<?= str_replace('_', ' ', $fn->getStatus()) ?>)
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>

                            <?php if ($compTx): ?>
                                <a href="<?= base_url('renter/receipt.php?id=' . $compTx->getTransactionID()) ?>" 
                                   class="text-xs text-slate-600 hover:text-coral font-medium underline">
                                    View Receipt &rarr;
                                </a>
                            <?php endif; ?>

                            <?php
                                require_once __DIR__ . '/../classes/Review.php';
                                $rev = Review::findByRequest($req->getRequestID());
                                if ($rev):
                            ?>
                                <a href="<?= base_url('renter/submit_review.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3 py-1.5 bg-amber-50 hover:bg-amber-100 border border-amber-200 text-amber-800 font-semibold text-xs rounded-full transition flex items-center space-x-1.5">
                                    <i class="ri-star-fill text-amber-500"></i>
                                    <span><?= $rev->getRating() ?>/5 Reviewed</span>
                                </a>
                            <?php else: ?>
                                <a href="<?= base_url('renter/submit_review.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-4 py-2 bg-amber-400 hover:bg-amber-500 text-midnight font-bold text-xs rounded-full shadow-sm transition flex items-center space-x-1">
                                    <i class="ri-star-line"></i>
                                    <span>Write Review</span>
                                </a>
                            <?php endif; ?>

                            <?php
                                $dispList = Dispute::findByRequest($req->getRequestID());
                                $hasDispute = !empty($dispList);
                                $activeDisp = $hasDispute ? end($dispList) : null;
                            ?>
                            <?php if ($hasDispute): ?>
                                <a href="<?= base_url('renter/file_dispute.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3 py-1.5 rounded-full text-xs font-semibold border flex items-center space-x-1.5 <?= $activeDisp->getStatus() === 'Resolved' ? 'bg-purple-50 border-purple-200 text-purple-700' : 'bg-rose-50 border-rose-200 text-rose-700' ?>">
                                    <i class="ri-scales-3-line"></i>
                                    <span>Dispute: <?= str_replace('_', ' ', $activeDisp->getStatus()) ?></span>
                                </a>
                            <?php else: ?>
                                <a href="<?= base_url('renter/file_dispute.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3 py-1.5 bg-slate-50 hover:bg-rose-50 text-slate-600 hover:text-rose-700 border border-slate-200 hover:border-rose-200 text-xs font-semibold rounded-full transition flex items-center space-x-1">
                                    <i class="ri-scales-3-line"></i>
                                    <span>Dispute</span>
                                </a>
                            <?php endif; ?>

                        <?php else: ?>
                            <span class="text-xs text-slate-400 italic">Booking Closed</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Cancel Request Confirmation -->
<div id="cancelModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm hidden p-4">
    <div class="bg-white border border-[#E9E7FF] rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-[#E9E7FF]">
            <h3 class="text-base font-display font-bold text-midnight flex items-center space-x-2">
                <i class="ri-alert-line text-rose-500 text-lg"></i>
                <span>Cancel Rental Request</span>
            </h3>
            <button type="button" onclick="closeCancelModal()" class="text-slate-400 hover:text-midnight text-xl">&times;</button>
        </div>

        <p class="text-xs text-slate-600" id="cancelModalDesc">
            Are you sure you want to cancel this booking?
        </p>

        <form action="<?= base_url('renter/cancel_request.php') ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" id="cancelRequestId" value="0">

            <div>
                <label for="cancellation_reason" class="block text-xs font-semibold text-midnight mb-1.5">
                    Reason for Cancellation <span class="text-slate-400 font-normal">(Optional)</span>
                </label>
                <textarea id="cancellation_reason" 
                          name="cancellation_reason" 
                          rows="2" 
                          placeholder="Change of schedule, found alternative, etc."
                          class="w-full px-4 py-2.5 bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl text-midnight text-sm placeholder-slate-400 focus:ring-2 focus:ring-coral focus:border-transparent outline-none transition"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-2">
                <button type="button" 
                        onclick="closeCancelModal()" 
                        class="px-5 py-2.5 bg-slate-100 hover:bg-slate-200 text-midnight text-xs font-semibold rounded-full transition">
                    Keep Booking
                </button>
                <button type="submit" 
                        class="px-5 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-sm transition">
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
        desc += `This request is pending approval, so cancellation is 100% free with no deductions.`;
    } else {
        desc += `As per ORMS terms, cancellation before payment incurs no deduction.`;
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
