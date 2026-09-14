<?php
/**
 * Online Rental Management System (ORMS)
 * Owner Manage Rental Requests Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 6) & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
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
                    set_flash('success', "Rental request #{$requestId} has been APPROVED. The renter has been notified to proceed with payment.");
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-white">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('owner/dashboard.php') ?>" class="hover:text-white">Owner Workspace</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">Manage Requests</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">Rental Requests</h1>
            <p class="text-sm text-slate-400 mt-1">Review, approve, or reject incoming booking inquiries from prospective renters.</p>
        </div>

        <?php if ($counts['Pending'] > 0): ?>
            <div class="inline-flex items-center space-x-2 px-4 py-2 bg-amber-500/10 border border-amber-500/30 rounded-xl text-amber-400 text-xs font-semibold">
                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                <span><?= $counts['Pending'] ?> Request(s) awaiting your decision</span>
            </div>
        <?php endif; ?>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-sm">
            <div class="font-bold mb-1">Errors:</div>
            <ul class="list-disc list-inside space-y-1 text-xs">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Filter Tabs -->
    <div class="flex items-center space-x-2 overflow-x-auto pb-4 mb-8 border-b border-slate-800 scrollbar-thin">
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
                ? 'bg-blue-600 text-white font-bold shadow-md shadow-blue-600/20' 
                : 'bg-slate-900 hover:bg-slate-800 text-slate-400 hover:text-white border border-slate-800';
        ?>
            <a href="<?= base_url('owner/manage_requests.php?status=' . $key) ?>" 
               class="px-4 py-2 rounded-xl text-xs font-medium whitespace-nowrap transition flex items-center space-x-2 <?= $activeClass ?>">
                <span><?= $tab['label'] ?></span>
                <span class="px-1.5 py-0.5 rounded-full text-[10px] <?= $isActive ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400' ?>">
                    <?= $tab['count'] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Requests List -->
    <?php if (empty($requests)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center shadow-xl">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/80 flex items-center justify-center text-2xl text-slate-400 mb-4">
                📬
            </div>
            <h3 class="text-base font-bold text-white">No rental requests found</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                There are currently no requests matching the "<?= htmlspecialchars(ucfirst($statusFilter)) ?>" filter.
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
                    <!-- Left: Item + Info -->
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
                                <span class="text-xs text-slate-400">Received <?= date('M d, Y h:i A', strtotime($req->getRequestDate())) ?></span>
                            </div>

                            <h3 class="text-lg font-bold text-white truncate">
                                <a href="<?= base_url('renter/product_details.php?id=' . $req->getProductID()) ?>" class="hover:text-blue-400 transition">
                                    <?= htmlspecialchars($req->getProductTitle()) ?>
                                </a>
                            </h3>

                            <!-- Renter Details -->
                            <div class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-300">
                                <div>
                                    <span class="text-slate-500">Renter:</span>
                                    <span class="font-semibold text-white ml-1"><?= htmlspecialchars($req->getRenterName()) ?></span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Phone:</span>
                                    <span class="font-mono text-slate-300 ml-1"><?= htmlspecialchars($req->getRenterPhone() ?: 'N/A') ?></span>
                                </div>
                                <div>
                                    <span class="text-slate-500">Email:</span>
                                    <span class="text-slate-300 ml-1"><?= htmlspecialchars($req->getRenterEmail()) ?></span>
                                </div>
                            </div>

                            <!-- Optional Message -->
                            <?php if (!empty($req->getMessage())): ?>
                                <div class="mt-2.5 p-2.5 rounded-lg bg-slate-950/70 border border-slate-800 text-xs text-slate-300">
                                    <span class="text-slate-500 font-semibold block text-[10px] uppercase">Renter Message:</span>
                                    "<?= htmlspecialchars($req->getMessage()) ?>"
                                </div>
                            <?php endif; ?>

                            <!-- Cancellation / Rejection Note -->
                            <?php if (!empty($req->getCancellationReason())): ?>
                                <div class="mt-2 p-2 rounded-lg bg-rose-950/40 border border-rose-900/50 text-xs text-rose-300">
                                    <span class="font-semibold">Reason:</span> <?= htmlspecialchars($req->getCancellationReason()) ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Middle: Financial Summary -->
                    <div class="w-full lg:w-auto p-4 rounded-xl bg-slate-950/60 border border-slate-800 flex-shrink-0 text-xs space-y-1.5 min-w-[200px]">
                        <div class="flex justify-between text-slate-400">
                            <span>Period:</span>
                            <span class="font-semibold text-white"><?= date('M d', strtotime($req->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($req->getEndDate())) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Total Duration:</span>
                            <span class="font-bold text-white"><?= $totalDays ?> day(s)</span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Rent (₹<?= number_format($req->getRentPerDay(), 2) ?>/d):</span>
                            <span class="font-bold text-white">₹<?= number_format($rentAmount, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Deposit:</span>
                            <span class="font-bold text-emerald-400">₹<?= number_format($deposit, 2) ?></span>
                        </div>
                        <div class="pt-1.5 border-t border-slate-800 flex justify-between font-bold">
                            <span class="text-slate-300">Total Booking:</span>
                            <span class="text-blue-400 text-sm">₹<?= number_format($rentAmount + $deposit, 2) ?></span>
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
                                        class="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-600/20 transition flex items-center justify-center space-x-1.5">
                                    <span>✓</span>
                                    <span>Approve</span>
                                </button>
                            </form>

                            <!-- Reject Button triggers Modal -->
                            <button type="button" 
                                    onclick="openRejectModal(<?= $req->getRequestID() ?>, '<?= htmlspecialchars(addslashes($req->getProductTitle())) ?>', '<?= htmlspecialchars(addslashes($req->getRenterName())) ?>')"
                                    class="w-full sm:w-auto px-5 py-2.5 bg-rose-950/60 hover:bg-rose-900/80 border border-rose-800/80 text-rose-300 font-bold text-xs uppercase tracking-wider rounded-xl transition flex items-center justify-center space-x-1.5">
                                <span>✕</span>
                                <span>Reject</span>
                            </button>

                        <?php elseif ($st === 'Approved'): ?>
                            <div class="text-center px-4 py-2 rounded-xl bg-blue-950/40 border border-blue-900/60 text-xs text-blue-300">
                                <div class="font-semibold">Approved</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">Awaiting Renter Payment</div>
                            </div>
                        <?php elseif ($st === 'Active'): ?>
                            <div class="text-center px-4 py-2 rounded-xl bg-emerald-950/40 border border-emerald-900/60 text-xs text-emerald-300">
                                <div class="font-semibold">Active Rental</div>
                                <div class="text-[10px] text-slate-400 mt-0.5">Due: <?= date('M d, Y', strtotime($req->getEndDate())) ?></div>
                            </div>
                            <a href="<?= base_url('owner/raise_fine.php?request_id=' . $req->getRequestID()) ?>" 
                               class="w-full sm:w-auto px-4 py-2 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-md shadow-blue-600/20 transition flex items-center justify-center space-x-1.5">
                                <span>📦</span>
                                <span>Confirm Return</span>
                            </a>
                            <?php 
                                require_once __DIR__ . '/../classes/Transaction.php';
                                $ownerTx = Transaction::findByRequest($req->getRequestID());
                                if ($ownerTx):
                            ?>
                                <a href="<?= base_url('renter/receipt.php?id=' . $ownerTx->getTransactionID()) ?>" 
                                   class="text-xs text-blue-400 hover:text-blue-300 font-medium underline">
                                    View Receipt &rarr;
                                </a>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-xs text-slate-500 italic">No actions pending</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: Reject Request Reason -->
<div id="rejectModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm hidden p-4">
    <div class="bg-slate-900 border border-slate-800 rounded-2xl max-w-md w-full p-6 shadow-2xl space-y-4">
        <div class="flex items-center justify-between pb-3 border-b border-slate-800">
            <h3 class="text-base font-bold text-white flex items-center space-x-2">
                <span class="text-rose-400">⚠️</span>
                <span>Reject Rental Request</span>
            </h3>
            <button type="button" onclick="closeRejectModal()" class="text-slate-400 hover:text-white text-lg font-bold">&times;</button>
        </div>

        <p class="text-xs text-slate-300" id="rejectModalDesc">
            Provide a reason for declining this rental request. This reason will be recorded and shared with the renter.
        </p>

        <form action="<?= base_url('owner/manage_requests.php?status=' . urlencode($statusFilter)) ?>" method="POST" class="space-y-4">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" id="modalRequestId" value="0">
            <input type="hidden" name="action" value="reject">

            <div>
                <label for="rejection_reason" class="block text-xs font-semibold text-slate-300 mb-1.5">
                    Decline Reason <span class="text-rose-400">*</span>
                </label>
                <textarea id="rejection_reason" 
                          name="rejection_reason" 
                          rows="3" 
                          required 
                          placeholder="e.g., Product is scheduled for maintenance, dates conflict with local pickup, etc."
                          class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-700 rounded-xl text-white text-sm placeholder-slate-500 focus:ring-2 focus:ring-rose-500 focus:border-transparent outline-none transition"></textarea>
            </div>

            <div class="flex items-center justify-end space-x-3 pt-2">
                <button type="button" 
                        onclick="closeRejectModal()" 
                        class="px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-semibold rounded-xl transition">
                    Cancel
                </button>
                <button type="submit" 
                        class="px-5 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-rose-600/20 transition">
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
