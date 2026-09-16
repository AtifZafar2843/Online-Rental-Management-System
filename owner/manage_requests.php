<?php
/**
 * Online Rental Management System (ORMS)
 * Owner Manage Rental Requests Page
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Dispute.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';
require_once __DIR__ . '/../classes/exceptions/UnauthorizedActionException.php';

require_role('Owner');

$ownerId = (int) current_user_id();
$owner = new Owner($ownerId);

$statusFilter = trim($_GET['status'] ?? 'all');
if (!in_array($statusFilter, ['all', 'Pending', 'Approved', 'Active', 'Completed', 'Rejected', 'Cancelled'], true)) {
    $statusFilter = 'all';
}

$errors = [];

// Handle POST: Approve or Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $action = trim($_POST['action'] ?? '');
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $reason = trim($_POST['rejection_reason'] ?? '');

        if ($requestId <= 0) {
            $errors[] = 'Invalid request identifier.';
        } elseif ($action === 'approve') {
            try {
                if ($owner->manageRentalRequest($requestId, 'approve')) {
                    set_flash('success', "Rental request #{$requestId} has been APPROVED. The renter has been notified to complete payment.");
                    header('Location: ' . base_url('owner/manage_requests.php?status=' . urlencode($statusFilter)));
                    exit;
                } else {
                    $errors[] = 'Failed to approve request. Please verify current status.';
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        } elseif ($action === 'reject') {
            try {
                if ($owner->manageRentalRequest($requestId, 'reject', $reason)) {
                    set_flash('success', "Rental request #{$requestId} has been REJECTED. The renter was notified.");
                    header('Location: ' . base_url('owner/manage_requests.php?status=' . urlencode($statusFilter)));
                    exit;
                } else {
                    $errors[] = 'Failed to reject request. Please verify current status.';
                }
            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        } else {
            $errors[] = 'Unknown action requested.';
        }
    }
}

// Fetch requests
$requests = RentalRequest::findByOwner($ownerId, $statusFilter);

// Count requests across statuses for tab badges
$allRequests = RentalRequest::findByOwner($ownerId, 'all');
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
}

$pageTitle = 'Manage Rental Requests — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-coral transition">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('owner/dashboard.php') ?>" class="hover:text-coral transition">Owner Workspace</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Manage Requests</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">Rental Requests</h1>
            <p class="text-sm text-slate-500 mt-1">Review, approve, or decline incoming booking inquiries from verified renters.</p>
        </div>

        <?php if ($counts['Pending'] > 0): ?>
            <div class="inline-flex items-center space-x-2 px-4 py-2 bg-amber-50 border border-amber-200 rounded-full text-amber-700 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></span>
                <span><?= $counts['Pending'] ?> Request(s) awaiting your decision</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            <div class="font-bold mb-1 flex items-center space-x-2 text-rose-800">
                <i class="ri-error-warning-line text-base"></i>
                <span>Action could not be processed:</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-xs">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-4 mb-8 border-b border-[#E9E7FF] scrollbar-thin">
        <?php
        $tabs = [
            'all'       => ['label' => 'All Requests', 'count' => $counts['all']],
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
                ? 'bg-coral text-white font-semibold shadow-glow-coral' 
                : 'bg-white hover:bg-slate-50 text-slate-600 hover:text-midnight border border-[#E9E7FF]';
        ?>
            <a href="<?= base_url('owner/manage_requests.php?status=' . $key) ?>" 
               class="px-4 py-2 rounded-full text-xs font-medium whitespace-nowrap transition flex items-center space-x-2 <?= $activeClass ?>">
                <span><?= $tab['label'] ?></span>
                <span class="px-2 py-0.5 rounded-full text-[10px] <?= $isActive ? 'bg-white/25 text-white' : 'bg-[#FAF8F5] text-slate-500' ?>">
                    <?= $tab['count'] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Requests List -->
    <?php if (empty($requests)): ?>
        <div class="bg-white border border-[#E9E7FF] rounded-3xl p-12 text-center shadow-sm">
            <div class="w-16 h-16 mx-auto rounded-full bg-[#FAF8F5] flex items-center justify-center text-2xl text-slate-400 mb-4">
                <i class="ri-inbox-line"></i>
            </div>
            <h3 class="text-base font-bold text-midnight font-display">No rental requests found</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                There are currently no requests matching the "<?= htmlspecialchars(ucfirst($statusFilter)) ?>" status filter.
            </p>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($requests as $req): 
                $st = $req->getStatus();
                $totalDays = $req->getTotalDays();
                $rentAmount = $req->getTotalAmount();
                $deposit = $req->getSecurityDeposit();

                // Status Badge Color Mapping
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
                        $badgeBg = 'bg-slate-50 text-slate-700 border-slate-200';
                        $dotColor = 'bg-slate-500';
                }
            ?>
                <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-7 shadow-sm hover:shadow-md transition flex flex-col lg:flex-row items-start lg:items-center justify-between gap-6">
                    <!-- Left: Item + Info -->
                    <div class="flex items-start space-x-4 sm:space-x-5 flex-1 min-w-0">
                        <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-2xl overflow-hidden bg-[#FAF8F5] border border-[#E9E7FF] flex-shrink-0">
                            <img src="<?= base_url($req->getPrimaryImage()) ?>" 
                                 alt="<?= htmlspecialchars($req->getProductTitle()) ?>" 
                                 class="w-full h-full object-cover"
                                 onerror="this.src='<?= base_url('assets/img/no-image.svg') ?>'">
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center space-x-2 flex-wrap gap-y-1 mb-1">
                                <span class="text-[11px] font-mono text-slate-400">#REQ-<?= $req->getRequestID() ?></span>
                                <span class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold border <?= $badgeBg ?>">
                                    <span class="w-1.5 h-1.5 rounded-full <?= $dotColor ?>"></span>
                                    <span><?= htmlspecialchars($st) ?></span>
                                </span>
                                <span class="text-xs text-slate-300">&bull;</span>
                                <span class="text-xs text-slate-500">Received <?= date('M d, Y h:i A', strtotime($req->getRequestDate())) ?></span>
                            </div>

                            <h3 class="text-lg font-bold text-midnight font-display truncate">
                                <a href="<?= base_url('renter/product_details.php?id=' . $req->getProductID()) ?>" class="hover:text-coral transition">
                                    <?= htmlspecialchars($req->getProductTitle()) ?>
                                </a>
                            </h3>

                            <!-- Renter Details -->
                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-600">
                                <div class="flex items-center space-x-1">
                                    <i class="ri-user-line text-slate-400"></i>
                                    <span class="font-semibold text-midnight"><?= htmlspecialchars($req->getRenterName()) ?></span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <i class="ri-phone-line text-slate-400"></i>
                                    <span class="font-mono"><?= htmlspecialchars($req->getRenterPhone() ?: 'N/A') ?></span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <i class="ri-mail-line text-slate-400"></i>
                                    <span><?= htmlspecialchars($req->getRenterEmail()) ?></span>
                                </div>
                            </div>

                            <!-- Optional Message -->
                            <?php if (!empty($req->getMessage())): ?>
                                <div class="mt-2.5 p-3 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] text-xs text-slate-600">
                                    <span class="text-slate-400 font-semibold block text-[10px] uppercase">Renter Message:</span>
                                    "<?= htmlspecialchars($req->getMessage()) ?>"
                                </div>
                            <?php endif; ?>

                            <!-- Cancellation / Rejection Note -->
                            <?php if (!empty($req->getCancellationReason())): ?>
                                <div class="mt-2.5 p-3 rounded-2xl bg-rose-50 border border-rose-200 text-xs text-rose-700">
                                    <span class="font-semibold">Reason:</span> <?= htmlspecialchars($req->getCancellationReason()) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Middle: Financial Summary -->
                    <div class="w-full lg:w-auto p-5 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] flex-shrink-0 text-xs space-y-2 min-w-[210px]">
                        <div class="flex justify-between text-slate-500">
                            <span>Period:</span>
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
                        <div class="pt-2 border-t border-[#E9E7FF] flex justify-between font-bold">
                            <span class="text-slate-600">Total Booking:</span>
                            <span class="text-coral text-sm">₹<?= number_format($rentAmount + $deposit, 2) ?></span>
                        </div>
                    </div>

                    <!-- Right: Decision Actions -->
                    <div class="w-full lg:w-auto flex lg:flex-col items-center justify-end gap-2 flex-shrink-0">
                        <?php if ($st === 'Pending'): ?>
                            <!-- Approve Form -->
                            <form action="<?= base_url('owner/manage_requests.php?status=' . urlencode($statusFilter)) ?>" method="POST" class="w-full sm:w-auto">
                                <?= csrf_field() ?>
                                <input type="hidden" name="request_id" value="<?= $req->getRequestID() ?>">
                                <input type="hidden" name="action" value="approve">
                                <button type="submit" 
                                        onclick="return confirm('Approve rental request #<?= $req->getRequestID() ?>? The renter will be asked to complete payment.');"
                                        class="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-xs rounded-full shadow-sm transition flex items-center justify-center space-x-1.5">
                                    <i class="ri-check-line"></i>
                                    <span>Approve</span>
                                </button>
                            </form>

                            <!-- Reject Button triggers Modal -->
                            <button type="button" 
                                    onclick="openRejectModal(<?= $req->getRequestID() ?>, '<?= htmlspecialchars(addslashes($req->getProductTitle())) ?>', '<?= htmlspecialchars(addslashes($req->getRenterName())) ?>')"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-white hover:bg-rose-50 border border-rose-200 text-rose-600 font-semibold text-xs rounded-full transition flex items-center justify-center space-x-1.5">
                                <i class="ri-close-line"></i>
                                <span>Reject</span>
                            </button>

                        <?php elseif ($st === 'Approved'): ?>
                            <div class="text-center px-4 py-2.5 rounded-2xl bg-blue-50 border border-blue-200 text-xs text-blue-700">
                                <div class="font-semibold flex items-center justify-center space-x-1">
                                    <i class="ri-time-line"></i>
                                    <span>Approved</span>
                                </div>
                                <div class="text-[10px] text-blue-600 mt-0.5">Awaiting Renter Payment</div>
                            </div>
                        <?php elseif ($st === 'Active'): ?>
                            <div class="text-center px-4 py-2 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs text-emerald-700">
                                <div class="font-semibold flex items-center justify-center space-x-1">
                                    <i class="ri-shield-check-line"></i>
                                    <span>Active Rental</span>
                                </div>
                                <div class="text-[10px] text-emerald-600 mt-0.5">Due: <?= date('M d, Y', strtotime($req->getEndDate())) ?></div>
                            </div>
                            <a href="<?= base_url('owner/raise_fine.php?request_id=' . $req->getRequestID()) ?>" 
                                class="w-full sm:w-auto px-4 py-2 bg-midnight hover:bg-[#1a233d] text-white font-semibold text-xs rounded-full shadow-sm transition flex items-center justify-center space-x-1.5">
                                <i class="ri-box-3-line"></i>
                                <span>Confirm Return</span>
                            </a>
                            <?php 
                                require_once __DIR__ . '/../classes/Transaction.php';
                                $ownerTx = Transaction::findByRequest($req->getRequestID());
                                if ($ownerTx):
                            ?>
                                <a href="<?= base_url('renter/receipt.php?id=' . $ownerTx->getTransactionID()) ?>" 
                                   class="text-xs text-coral hover:underline font-semibold flex items-center space-x-1">
                                    <i class="ri-file-text-line"></i>
                                    <span>View Receipt</span>
                                </a>
                            <?php endif; ?>

                            <?php
                                $dispList = Dispute::findByRequest($req->getRequestID());
                                $hasDispute = !empty($dispList);
                                $activeDisp = $hasDispute ? end($dispList) : null;
                            ?>
                            <?php if ($hasDispute): ?>
                                <a href="<?= base_url('owner/file_dispute.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3 py-1.5 rounded-full text-xs font-semibold border flex items-center space-x-1.5 <?= $activeDisp->getStatus() === 'Resolved' ? 'bg-purple-50 border-purple-200 text-purple-700' : 'bg-rose-50 border-rose-200 text-rose-700' ?>">
                                    <i class="ri-scales-3-line"></i>
                                    <span>Dispute: <?= str_replace('_', ' ', $activeDisp->getStatus()) ?></span>
                                </a>
                            <?php else: ?>
                                <a href="<?= base_url('owner/file_dispute.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3.5 py-1.5 bg-white hover:bg-rose-50 text-slate-600 hover:text-rose-600 border border-[#E9E7FF] hover:border-rose-200 text-xs font-semibold rounded-full transition flex items-center space-x-1">
                                    <i class="ri-scales-3-line"></i>
                                    <span>Dispute</span>
                                </a>
                            <?php endif; ?>

                        <?php elseif ($st === 'Completed'): ?>
                            <div class="text-center px-4 py-2 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] text-xs space-y-1">
                                <div class="font-bold text-emerald-600 flex items-center justify-center space-x-1">
                                    <i class="ri-checkbox-circle-line"></i>
                                    <span>Completed</span>
                                </div>
                            </div>
                            <?php 
                                require_once __DIR__ . '/../classes/Transaction.php';
                                $ownerTx = Transaction::findByRequest($req->getRequestID());
                                if ($ownerTx):
                            ?>
                                <a href="<?= base_url('renter/receipt.php?id=' . $ownerTx->getTransactionID()) ?>" 
                                   class="text-xs text-coral hover:underline font-semibold flex items-center space-x-1">
                                    <i class="ri-file-text-line"></i>
                                    <span>View Receipt</span>
                                </a>
                            <?php endif; ?>

                            <?php
                                $dispList = Dispute::findByRequest($req->getRequestID());
                                $hasDispute = !empty($dispList);
                                $activeDisp = $hasDispute ? end($dispList) : null;
                            ?>
                            <?php if ($hasDispute): ?>
                                <a href="<?= base_url('owner/file_dispute.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3 py-1.5 rounded-full text-xs font-semibold border flex items-center space-x-1.5 <?= $activeDisp->getStatus() === 'Resolved' ? 'bg-purple-50 border-purple-200 text-purple-700' : 'bg-rose-50 border-rose-200 text-rose-700' ?>">
                                    <i class="ri-scales-3-line"></i>
                                    <span>Dispute: <?= str_replace('_', ' ', $activeDisp->getStatus()) ?></span>
                                </a>
                            <?php else: ?>
                                <a href="<?= base_url('owner/file_dispute.php?request_id=' . $req->getRequestID()) ?>" 
                                   class="px-3.5 py-1.5 bg-white hover:bg-rose-50 text-slate-600 hover:text-rose-600 border border-[#E9E7FF] hover:border-rose-200 text-xs font-semibold rounded-full transition flex items-center space-x-1">
                                    <i class="ri-scales-3-line"></i>
                                    <span>Dispute</span>
                                </a>
                            <?php endif; ?>

                        <?php else: ?>
                            <span class="text-xs text-slate-400 italic">No actions pending</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Reject Request Reason -->
<div id="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-midnight/60 backdrop-blur-sm hidden p-4">
    <div class="bg-white border border-[#E9E7FF] rounded-3xl max-w-md w-full p-6 sm:p-8 shadow-2xl space-y-5">
        <div class="flex items-center justify-between pb-3 border-b border-[#E9E7FF]">
            <h3 class="text-base font-bold font-display text-midnight flex items-center space-x-2">
                <i class="ri-close-circle-line text-coral text-xl"></i>
                <span>Reject Rental Request</span>
            </h3>
            <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-midnight text-xl font-bold transition">
                <i class="ri-close-line"></i>
            </button>
        </div>

        <p class="text-xs text-slate-600" id="rejectModalDesc">
            Provide a clear reason for declining this rental request. This reason will be recorded and shared with the renter.
        </p>

        <form action="<?= base_url('owner/manage_requests.php?status=' . urlencode($statusFilter)) ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" id="modalRequestId" value="0">
            <input type="hidden" name="action" value="reject">

            <div>
                <label for="rejection_reason" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                    Decline Reason <span class="text-coral">*</span>
                </label>
                <textarea id="rejection_reason" 
                          name="rejection_reason" 
                          rows="3" 
                          required 
                          placeholder="e.g., Product is scheduled for maintenance, dates conflict with local pickup schedule, etc."
                          class="w-full px-4 py-3 bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl text-midnight text-sm placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-2">
                <button type="button" 
                        onclick="closeRejectModal()" 
                        class="px-5 py-2.5 rounded-full border border-slate-200 text-xs font-semibold text-slate-600 hover:text-midnight hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-6 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-semibold text-xs rounded-full shadow-sm transition">
                    Confirm Rejection
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openRejectModal(requestId, productTitle, renterName) {
    document.getElementById('modalRequestId').value = requestId;
    document.getElementById('rejectModalDesc').textContent = 
        `Decline request #${requestId} for "${productTitle}" from ${renterName}. The reason will be sent via notification.`;
    document.getElementById('rejection_reason').value = '';
    document.getElementById('rejectModal').classList.remove('hidden');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.add('hidden');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
