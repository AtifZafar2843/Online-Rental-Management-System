<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Dispute Resolution Center
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 13) & Synopsis Section 13.X (Page 31)
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_dispute'])) {
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('admin/dashboard.php') ?>" class="hover:text-white transition">Admin Dashboard</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">Dispute Resolution</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center space-x-3">
                <span>⚖️</span>
                <span>Dispute Resolution Center</span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Neutral adjudication portal &bull; Review evidence, inspect financial and fine records, and record mandatory ruling notes.
            </p>
        </div>

        <div>
            <a href="<?= base_url('admin/reports.php') ?>" 
               class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 transition flex items-center space-x-2">
                <span>📊</span>
                <span>View Compliance Reports</span>
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800 text-rose-300 text-sm flex items-center space-x-3 shadow-lg">
            <span class="text-xl flex-shrink-0">⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Status Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-8 bg-slate-900 border border-slate-800 p-2 rounded-2xl">
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
               class="px-3.5 py-2 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $isActive ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                <span><?= $tab['label'] ?></span>
                <span class="px-1.5 py-0.2 rounded-full text-[10px] font-mono <?= $isActive ? 'bg-blue-800 text-white' : 'bg-slate-800 text-slate-300' ?>">
                    <?= $tab['count'] ?>
                </span>
            </a>
        <?php endforeach; ?>
    </div>

    <!-- Disputes List -->
    <?php if (empty($disputes)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center shadow-xl">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/80 flex items-center justify-center text-3xl mb-4">
                🎉
            </div>
            <h3 class="text-base font-bold text-white">No disputes found</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                There are no dispute cases matching the selected filter status.
            </p>
        </div>
    <?php else: ?>
        <div class="space-y-6">
            <?php foreach ($disputes as $disp): 
                $req = RentalRequest::findById($disp->getRequestID());
                $tx = Transaction::findByRequest($disp->getRequestID());
                $fine = Fine::findByRequest($disp->getRequestID());

                $statusColors = [
                    'Open'         => 'bg-amber-950/80 text-amber-300 border-amber-700',
                    'Under_Review' => 'bg-blue-950/80 text-blue-300 border-blue-700',
                    'Escalated'    => 'bg-rose-950/80 text-rose-300 border-rose-700',
                    'Resolved'     => 'bg-emerald-950/80 text-emerald-300 border-emerald-700'
                ];
                $badgeColor = $statusColors[$disp->getStatus()] ?? 'bg-slate-800 text-slate-300 border-slate-700';
            ?>
                <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
                    <!-- Case Header -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-4">
                        <div class="flex items-center space-x-3">
                            <span class="text-xl">⚖️</span>
                            <div>
                                <h3 class="font-bold text-white text-base">
                                    Dispute #<?= $disp->getDisputeID() ?> &bull; Rental #<?= $disp->getRequestID() ?>
                                </h3>
                                <span class="text-xs text-slate-400">
                                    Item: <strong class="text-slate-200"><?= htmlspecialchars($disp->getProductTitle() ?? 'Product') ?></strong>
                                </span>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <span class="px-3 py-1 text-xs font-bold rounded-full border <?= $badgeColor ?>">
                                <?= str_replace('_', ' ', $disp->getStatus()) ?>
                            </span>
                            <span class="text-xs text-slate-500 font-mono">
                                <?= date('M d, Y • h:i A', strtotime($disp->getCreatedDate())) ?>
                            </span>
                        </div>
                    </div>

                    <!-- Case Parties & Financial Context -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 p-4 bg-slate-950 rounded-xl border border-slate-800 text-xs">
                        <div>
                            <span class="text-slate-500 block">Raised By</span>
                            <span class="text-indigo-400 font-bold"><?= htmlspecialchars($disp->getRaisedByName() ?? 'User #' . $disp->getRaisedByID()) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-500 block">Against</span>
                            <span class="text-amber-400 font-bold"><?= htmlspecialchars($disp->getAgainstName() ?? 'User #' . $disp->getAgainstID()) ?></span>
                        </div>
                        <div>
                            <span class="text-slate-500 block">Rental Escrow / Deposit</span>
                            <span class="text-slate-200 font-semibold">
                                <?= $tx ? "₹" . number_format($tx->getDepositAmount(), 2) . " (" . $tx->getDepositStatus() . ")" : "No deposit logged" ?>
                            </span>
                        </div>
                        <div>
                            <span class="text-slate-500 block">Assessed Fines</span>
                            <span class="text-rose-300 font-semibold">
                                <?= $fine ? "₹" . number_format($fine->getAmount(), 2) . " (" . $fine->getStatus() . ")" : "No fines assessed" ?>
                            </span>
                        </div>
                    </div>

                    <!-- Raiser Statement -->
                    <div>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-wider block mb-1.5">
                            Filing Party Claim & Details:
                        </span>
                        <div class="p-4 bg-slate-950/80 rounded-xl border border-slate-800/80 text-slate-200 text-sm leading-relaxed">
                            <?= nl2br(htmlspecialchars($disp->getReason())) ?>
                        </div>
                    </div>

                    <!-- Existing Admin Ruling (If any) -->
                    <?php if ($disp->getAdminNotes()): ?>
                        <div class="p-4 bg-emerald-950/20 border border-emerald-800/40 rounded-xl space-y-1">
                            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider block">
                                🛡️ Previous / Current Administrator Ruling:
                            </span>
                            <p class="text-emerald-200 text-sm leading-relaxed">
                                <?= nl2br(htmlspecialchars($disp->getAdminNotes())) ?>
                            </p>
                            <?php if ($disp->getResolvedDate()): ?>
                                <span class="text-[11px] text-slate-500 block pt-1 font-mono">
                                    Resolved on <?= date('M d, Y • h:i A', strtotime($disp->getResolvedDate())) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action & Resolution Form -->
                    <div class="pt-4 border-t border-slate-800">
                        <form action="<?= base_url('admin/resolve_disputes.php') ?>" method="POST" class="space-y-4">
                            <?= csrf_field() ?>
                            <input type="hidden" name="dispute_id" value="<?= $disp->getDisputeID() ?>">
                            <input type="hidden" name="update_dispute" value="1">

                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-start">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">Target Status</label>
                                    <select name="status" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                                        <option value="Resolved" <?= $disp->getStatus() === 'Resolved' ? 'selected' : '' ?>>Resolved (Close Case)</option>
                                        <option value="Under_Review" <?= $disp->getStatus() === 'Under_Review' ? 'selected' : '' ?>>Under Review</option>
                                        <option value="Escalated" <?= $disp->getStatus() === 'Escalated' ? 'selected' : '' ?>>Escalate Case</option>
                                    </select>
                                </div>

                                <div class="md:col-span-2">
                                    <label class="block text-xs font-semibold text-slate-300 mb-1.5">
                                        Administrative Notes <span class="text-rose-400">* (Mandatory on Resolve per Rule 13)</span>
                                    </label>
                                    <textarea name="admin_notes" 
                                              rows="2" 
                                              placeholder="Document formal justification, ruling outcome, and required actions..."
                                              class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500 leading-relaxed"><?= htmlspecialchars($disp->getAdminNotes() ?? '') ?></textarea>
                                </div>
                            </div>

                            <?php if ($fine && $fine->getStatus() === 'Unpaid'): ?>
                                <div class="flex items-center space-x-2 pt-1">
                                    <input type="checkbox" id="waive_fine_<?= $disp->getDisputeID() ?>" name="waive_fine" value="1" class="rounded border-slate-700 bg-slate-950 text-blue-600 focus:ring-blue-500 w-4 h-4">
                                    <label for="waive_fine_<?= $disp->getDisputeID() ?>" class="text-xs text-amber-300 font-medium">
                                        Waive pending fine of ₹<?= number_format($fine->getAmount(), 2) ?> for Renter on case resolution
                                    </label>
                                </div>
                            <?php endif; ?>

                            <div class="flex items-center justify-end space-x-3 pt-2">
                                <button type="submit" 
                                        class="px-5 py-2 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-600/30 transition flex items-center space-x-1.5">
                                    <span>💾</span>
                                    <span>Submit Ruling & Update</span>
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
