<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Dispute Resolution Center
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Dispute.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Transaction.php';
require_once __DIR__ . '/../classes/Fine.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';

require_admin();

$db = Database::getInstance()->getConnection();
$error = '';
$success = '';

// Handle Dispute Resolution / Status Update
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['update_dispute'])) {
    if (!csrf_verify()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $disputeId = (int) ($_POST['dispute_id'] ?? 0);
        $newStatus = trim((string) ($_POST['status'] ?? ''));
        $adminNotes = trim((string) ($_POST['admin_notes'] ?? ''));
        $waiveFine = isset($_POST['waive_fine']) && $_POST['waive_fine'] === '1';

        $dispute = Dispute::findById($disputeId);
        if (!$dispute) {
            $error = "Dispute #{$disputeId} not found.";
        } else {
            try {
                if ($newStatus === 'Resolved') {
                    if (empty($adminNotes)) {
                        throw new ORMSException("Administrator notes are mandatory when resolving a dispute.");
                    }
                    $dispute->resolve($adminNotes, $waiveFine);
                    set_flash('success', "Dispute #{$disputeId} marked as Resolved. Both parties have been notified.");
                } elseif ($newStatus === 'Under_Review') {
                    $dispute->underReview($adminNotes ?: null);
                    set_flash('success', "Dispute #{$disputeId} status updated to Under Review.");
                } elseif ($newStatus === 'Escalated') {
                    $dispute->escalate($adminNotes ?: null);
                    set_flash('success', "Dispute #{$disputeId} status updated to Escalated.");
                }
                header('Location: ' . base_url('admin/resolve_disputes.php'));
                exit;
            } catch (ORMSException $e) {
                $error = $e->getMessage();
            } catch (Throwable $e) {
                $error = 'Failed to update dispute: ' . $e->getMessage();
            }
        }
    }
}

// Filter parameter
$filterStatus = $_GET['status'] ?? 'All';
$validStatuses = ['All', 'Open', 'Under_Review', 'Escalated', 'Resolved'];
if (!in_array($filterStatus, $validStatuses, true)) {
    $filterStatus = 'All';
}

$disputes = Dispute::findAll($filterStatus);
$counts = Dispute::countByStatus();

$pageTitle = 'Dispute Resolution Center — ORMS Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('admin/dashboard.php') ?>" class="hover:text-coral transition">Admin Dashboard</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Dispute Resolution</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#E9E7FF] pb-6 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-coral text-xs font-semibold uppercase tracking-wider mb-1">
                <i class="ri-scales-3-line text-sm"></i>
                <span>Platform Arbitration</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">Dispute Resolution Center</h1>
            <p class="text-sm text-slate-500 mt-1">
                Neutral adjudication portal &bull; Review evidence, inspect financial and fine records, and record ruling notes.
            </p>
        </div>

        <div>
            <a href="<?= base_url('admin/reports.php') ?>" 
               class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-600 hover:text-midnight text-xs font-semibold rounded-full border border-[#E9E7FF] transition flex items-center space-x-1.5 shadow-sm">
                <i class="ri-bar-chart-2-line text-coral"></i>
                <span>Compliance Reports</span>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center space-x-3 shadow-sm">
            <i class="ri-error-warning-line text-lg text-rose-600 flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Status Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-8 bg-white border border-[#E9E7FF] p-2 rounded-full shadow-sm">
        <?php
            $tabs = [
                'All'          => ['label' => 'All Disputes', 'count' => $counts['All']],
                'Open'         => ['label' => 'Open Cases', 'count' => $counts['Open']],
                'Under_Review' => ['label' => 'Under Review', 'count' => $counts['Under_Review']],
                'Escalated'    => ['label' => 'Escalated', 'count' => $counts['Escalated']],
                'Resolved'     => ['label' => 'Resolved', 'count' => $counts['Resolved']]
            ];
            foreach ($tabs as $key => $tab):
                $isActive = ($filterStatus === $key);
                $url = base_url('admin/resolve_disputes.php' . ($key !== 'All' ? '?status=' . urlencode($key) : ''));
        ?>
            <a href="<?= $url ?>" 
               class="px-4 py-2 rounded-full text-xs font-semibold transition flex items-center space-x-2 <?= $isActive ? 'bg-coral text-white shadow-glow-coral' : 'text-slate-600 hover:text-midnight hover:bg-slate-50' ?>">
                <span><?= $tab['label'] ?></span>
                <span class="px-2 py-0.5 rounded-full text-[10px] <?= $isActive ? 'bg-white/25 text-white' : 'bg-[#FAF8F5] text-slate-500' ?>">
                    <?= $tab['count'] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Disputes List -->
    <?php if (empty($disputes)): ?>
        <div class="bg-white border border-[#E9E7FF] rounded-3xl p-12 text-center shadow-sm">
            <div class="w-16 h-16 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-3xl mb-4">
                <i class="ri-checkbox-circle-line"></i>
            </div>
            <h3 class="text-base font-bold font-display text-midnight">No disputes found</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                There are currently no dispute cases matching the selected filter status.
            </p>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($disputes as $disp): 
                $req = RentalRequest::findById($disp->getRequestID());
                $tx = Transaction::findByRequest($disp->getRequestID());
                $fines = Fine::findByRequest($disp->getRequestID());
                $hasFines = !empty($fines);
                $totalFineAmt = 0.0;
                $hasWaivableFine = false;
                foreach ($fines as $fn) {
                    $totalFineAmt += $fn->getAmount();
                    if (in_array($fn->getStatus(), ['Unpaid', 'Pending', 'Deducted_From_Deposit'], true)) {
                        $hasWaivableFine = true;
                    }
                }
                $firstFine = $hasFines ? $fines[0] : null;

                $statusColors = [
                    'Open'         => 'bg-amber-50 text-amber-700 border-amber-200',
                    'Under_Review' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'Escalated'    => 'bg-rose-50 text-rose-700 border-rose-200',
                    'Resolved'     => 'bg-emerald-50 text-emerald-700 border-emerald-200'
                ];
                $badgeColor = $statusColors[$disp->getStatus()] ?? 'bg-slate-50 text-slate-700 border-slate-200';
            ?>
                <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-7 shadow-sm space-y-5">
                    <!-- Case Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[#E9E7FF] pb-4">
                        <div class="flex items-center space-x-3">
                            <div class="w-10 h-10 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] flex items-center justify-center text-coral text-lg flex-shrink-0">
                                <i class="ri-scales-3-line"></i>
                            </div>
                            <div>
                                <h3 class="font-bold font-display text-midnight text-base">
                                    Dispute #<?= $disp->getDisputeID() ?> &bull; Rental #<?= $disp->getRequestID() ?>
                                </h3>
                                <span class="text-xs text-slate-500">
                                    Item: <strong class="text-midnight"><?= htmlspecialchars($disp->getProductTitle() ?? 'Product') ?></strong>
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <span class="px-3 py-1 text-xs font-semibold rounded-full border <?= $badgeColor ?>">
                                <?= str_replace('_', ' ', $disp->getStatus()) ?>
                            </span>
                            <span class="text-xs text-slate-400 font-mono">
                                <?= date('M d, Y • h:i A', strtotime($disp->getCreatedDate())) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Case Parties & Financial Context -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-5 bg-[#FAF8F5] rounded-2xl border border-[#E9E7FF] text-xs">
                        <div>
                            <span class="text-slate-400 block mb-0.5">Raised By</span>
                            <span class="text-midnight font-bold"><?= htmlspecialchars($disp->getRaisedByName() ?? 'User #' . $disp->getRaisedByID()) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block mb-0.5">Against</span>
                            <span class="text-midnight font-bold"><?= htmlspecialchars($disp->getAgainstName() ?? 'User #' . $disp->getAgainstID()) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-400 block mb-0.5">Escrow Security Deposit</span>
                            <span class="text-emerald-700 font-semibold">
                                <?= $tx ? "₹" . number_format($tx->getDepositAmount(), 2) . " (" . $tx->getDepositStatus() . ")" : "No deposit logged" ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-400 block mb-0.5">Assessed Penalties</span>
                            <span class="text-rose-600 font-semibold">
                                <?php if ($hasFines): ?>
                                    ₹<?= number_format($totalFineAmt, 2) ?> 
                                    <span class="text-[10px] text-slate-400 font-normal">
                                        (<?= count($fines) === 1 ? str_replace('_', ' ', $firstFine->getStatus()) : count($fines) . ' penalties' ?>)
                                    </span>
                                <?php else: ?>
                                    No fines assessed
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>

                    <!-- Raiser Statement -->
                    <div>
                        <span class="text-xs font-semibold text-midnight uppercase tracking-wider block mb-2">
                            Filing Party Claim & Details:
                        </span>
                        <div class="p-4 bg-[#FAF8F5] rounded-2xl border border-[#E9E7FF] text-slate-700 text-sm leading-relaxed">
                            <?= nl2br(htmlspecialchars($disp->getReason())) ?>
                        </div>
                    </div>

                    <!-- Existing Admin Ruling (If any) -->
                    <?php if ($disp->getAdminNotes()): ?>
                        <div class="p-4 bg-emerald-50 border border-emerald-200 rounded-2xl space-y-1">
                            <span class="text-xs font-semibold text-emerald-800 uppercase tracking-wider block flex items-center space-x-1.5">
                                <i class="ri-shield-check-line text-emerald-600"></i>
                                <span>Recorded Administrator Ruling:</span>
                            </span>
                            <p class="text-emerald-900 text-sm leading-relaxed">
                                <?= nl2br(htmlspecialchars($disp->getAdminNotes())) ?>
                            </p>
                            <?php if ($disp->getResolvedDate()): ?>
                                <span class="text-[11px] text-emerald-700 block pt-1 font-mono">
                                    Resolved on <?= date('M d, Y • h:i A', strtotime($disp->getResolvedDate())) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action & Resolution Form -->
                    <div class="pt-4 border-t border-[#E9E7FF]">
                        <form action="<?= base_url('admin/resolve_disputes.php') ?>" method="POST" class="space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="dispute_id" value="<?= $disp->getDisputeID() ?>">
                            <input type="hidden" name="update_dispute" value="1">

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                                <div>
                                    <label class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">Target Status</label>
                                    <select name="status" class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-2.5 text-xs text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                                        <option value="Resolved" <?= $disp->getStatus() === 'Resolved' ? 'selected' : '' ?>>Resolved (Close Case)</option>
                                        <option value="Under_Review" <?= $disp->getStatus() === 'Under_Review' ? 'selected' : '' ?>>Under Review</option>
                                        <option value="Escalated" <?= $disp->getStatus() === 'Escalated' ? 'selected' : '' ?>>Escalate Case</option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                                        Administrative Notes <span class="text-coral">* (Mandatory on Resolve)</span>
                                    </label>
                                    <textarea name="admin_notes" 
                                              rows="2" 
                                              placeholder="Document formal justification, ruling outcome, and required actions..."
                                              class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-2.5 text-xs text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition leading-relaxed"><?= htmlspecialchars($disp->getAdminNotes() ?? '') ?></textarea>
                                </div>
                            </div>

                            <?php if ($hasWaivableFine): ?>
                                <div class="flex items-center space-x-2 pt-1">
                                    <input type="checkbox" id="waive_fine_<?= $disp->getDisputeID() ?>" name="waive_fine" value="1" class="rounded border-[#E9E7FF] text-coral focus:ring-coral w-4 h-4">
                                    <label for="waive_fine_<?= $disp->getDisputeID() ?>" class="text-xs text-amber-700 font-medium">
                                        Waive assessed penalty / fine (₹<?= number_format($totalFineAmt, 2) ?>) for Renter on case resolution
                                    </label>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center justify-end space-x-3 pt-2">
                                <button type="submit" 
                                        class="px-6 py-2.5 bg-coral hover:bg-[#e04e53] text-white text-xs font-semibold rounded-full shadow-glow-coral transition flex items-center space-x-1.5">
                                    <i class="ri-check-line"></i>
                                    <span>Submit Ruling &amp; Update</span>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
