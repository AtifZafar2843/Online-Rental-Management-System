<?php
/**
 * Online Rental Management System (ORMS)
 * File Dispute Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 13) & Synopsis Section 13.X (Page 31)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Dispute.php';
require_once __DIR__ . '/../classes/Renter.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';

require_login();

$userId = (int) current_user_id();
$userRole = current_role();
$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

if ($requestId <= 0) {
    set_flash('error', 'Invalid rental request selected for dispute.');
    header('Location: ' . base_url($userRole === 'Owner' ? 'owner/manage_requests.php' : 'renter/my_rentals.php'));
    exit;
}

$rental = RentalRequest::findById($requestId);
if (!$rental) {
    set_flash('error', 'Rental request not found.');
    header('Location: ' . base_url($userRole === 'Owner' ? 'owner/manage_requests.php' : 'renter/my_rentals.php'));
    exit;
}

// Authorization check: current user must be Renter or Owner of this rental
$isRenter = ($rental->getRenterID() === $userId);
$isOwner = ($rental->getOwnerID() === $userId);

if (!$isRenter && !$isOwner) {
    set_flash('error', 'You do not have permission to file or view disputes for this rental.');
    header('Location: ' . base_url('index.php'));
    exit;
}

// Rule 13: Must be Active or Completed
if (!in_array($rental->getStatus(), ['Active', 'Completed'], true)) {
    set_flash('error', "Disputes can only be filed against Active or Completed rentals. Current status: {$rental->getStatus()}.");
    header('Location: ' . base_url($userRole === 'Owner' ? 'owner/manage_requests.php' : 'renter/my_rentals.php'));
    exit;
}

// Check if dispute already exists for this rental request
$existingDisputes = Dispute::findByRequest($requestId);
$activeDispute = null;
foreach ($existingDisputes as $disp) {
    $activeDispute = $disp;
    break; // Most recent
}

$error = '';

// Handle POST: Submit Dispute
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dispute'])) {
    if (!csrf_verify()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $reason = trim((string) ($_POST['reason'] ?? ''));
        try {
            if ($isRenter) {
                $renter = new Renter($userId);
                $renter->fileDispute($requestId, $reason);
            } else {
                $owner = new Owner($userId);
                $owner->fileDispute($requestId, $reason);
            }
            set_flash('success', 'Your dispute has been formally submitted. An administrator has been notified to investigate.');
            header('Location: ' . base_url($userRole === 'Owner' ? 'owner/manage_requests.php' : 'renter/my_rentals.php'));
            exit;
        } catch (ORMSException $e) {
            $error = $e->getMessage();
        } catch (Throwable $e) {
            $error = 'An error occurred while submitting your dispute: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'File Dispute — Rental #' . $requestId;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-coral transition">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url($userRole === 'Owner' ? 'owner/manage_requests.php' : 'renter/my_rentals.php') ?>" class="hover:text-coral transition"><?= $userRole === 'Owner' ? 'Manage Requests' : 'My Rentals' ?></a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Dispute Resolution</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="border-b border-[#E9E7FF] pb-6 mb-8">
        <div class="flex items-center space-x-3">
            <div class="w-12 h-12 rounded-2xl bg-rose-50 border border-rose-100 flex items-center justify-center text-rose-600 text-2xl">
                <i class="ri-scales-3-line"></i>
            </div>
            <div>
                <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">Formal Dispute Center</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Neutral administrator mediation for rental discrepancies, damages, or deposit claims.</p>
            </div>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center space-x-3">
            <i class="ri-error-warning-line text-lg flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <!-- Rental Context Card -->
    <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-8 shadow-sm mb-8">
        <h2 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Rental Case Summary</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
            <div>
                <span class="text-slate-500 text-xs block">Product</span>
                <span class="text-midnight font-semibold"><?= htmlspecialchars($rental->getProductTitle()) ?></span>
            </div>
            <div>
                <span class="text-slate-500 text-xs block">Booking Reference</span>
                <span class="text-midnight font-mono font-semibold">#<?= $rental->getRequestID() ?></span>
            </div>
            <div>
                <span class="text-slate-500 text-xs block">Rental Schedule</span>
                <span class="text-slate-600 font-mono text-xs"><?= date('M d, Y', strtotime($rental->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($rental->getEndDate())) ?></span>
            </div>
            <div>
                <span class="text-slate-500 text-xs block">Status</span>
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $rental->getStatus() === 'Completed' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-blue-50 text-blue-700 border border-blue-200' ?>">
                    <?= htmlspecialchars($rental->getStatus()) ?>
                </span>
            </div>
            <div>
                <span class="text-slate-500 text-xs block">Filing Party (You)</span>
                <span class="text-midnight font-semibold"><?= htmlspecialchars(current_user_name()) ?> (<?= $isRenter ? 'Renter' : 'Owner' ?>)</span>
            </div>
            <div>
                <span class="text-slate-500 text-xs block">Opposing Party</span>
                <span class="text-midnight font-semibold"><?= htmlspecialchars($isRenter ? 'Product Host / Owner' : 'Verified Renter') ?></span>
            </div>
        </div>
    </div>

    <!-- Active Dispute Status (If exists) -->
    <?php if ($activeDispute): ?>
        <div class="bg-white border border-rose-200 rounded-3xl p-6 sm:p-8 shadow-sm mb-8">
            <div class="flex items-center justify-between border-b border-[#E9E7FF] pb-4 mb-4">
                <div class="flex items-center space-x-2">
                    <i class="ri-scales-3-line text-rose-500 text-lg"></i>
                    <h3 class="font-display font-bold text-midnight text-base">Dispute Case #<?= $activeDispute->getDisputeID() ?></h3>
                </div>
                <?php
                    $statusStyles = [
                        'Open'         => 'bg-amber-50 text-amber-700 border-amber-200',
                        'Under_Review' => 'bg-blue-50 text-blue-700 border-blue-200',
                        'Escalated'    => 'bg-rose-50 text-rose-700 border-rose-200',
                        'Resolved'     => 'bg-emerald-50 text-emerald-700 border-emerald-200'
                    ];
                    $badgeStyle = $statusStyles[$activeDispute->getStatus()] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                ?>
                <span class="px-3 py-1 text-xs font-bold rounded-full border <?= $badgeStyle ?>">
                    <?= str_replace('_', ' ', $activeDispute->getStatus()) ?>
                </span>
            </div>

            <div class="space-y-4 text-sm">
                <div>
                    <span class="text-xs text-slate-500 block mb-1">Dispute Statement:</span>
                    <div class="p-4 bg-[#FAF8F5] rounded-2xl border border-[#E9E7FF] text-slate-700 leading-relaxed">
                        <?= nl2br(htmlspecialchars($activeDispute->getReason())) ?>
                    </div>
                </div>

                <div class="flex items-center justify-between text-xs text-slate-500">
                    <span>Filed: <?= date('M d, Y • h:i A', strtotime($activeDispute->getCreatedDate())) ?></span>
                    <span>Raised By: <strong class="text-midnight"><?= htmlspecialchars($activeDispute->getRaisedByName() ?? 'You') ?></strong></span>
                </div>

                <?php if ($activeDispute->getAdminNotes()): ?>
                    <div class="mt-4 pt-4 border-t border-[#E9E7FF]">
                        <span class="text-xs font-bold text-emerald-700 uppercase tracking-wider block mb-1">
                            <i class="ri-shield-check-line"></i> Administrator Resolution Notes:
                        </span>
                        <div class="p-4 bg-emerald-50 rounded-2xl border border-emerald-200 text-emerald-800 text-sm leading-relaxed">
                            <?= nl2br(htmlspecialchars($activeDispute->getAdminNotes())) ?>
                        </div>
                        <?php if ($activeDispute->getResolvedDate()): ?>
                            <span class="text-[11px] text-slate-400 mt-2 block font-mono">
                                Resolved on <?= date('M d, Y • h:i A', strtotime($activeDispute->getResolvedDate())) ?>
                            </span>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="mt-2 p-3.5 bg-amber-50 rounded-2xl border border-amber-200 text-xs text-amber-800 flex items-center space-x-2">
                        <i class="ri-hourglass-line text-base text-amber-600"></i>
                        <span>This case is currently awaiting review by the ORMS administrative team. You will be notified immediately upon resolution.</span>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <!-- Dispute Submission Form -->
        <form action="<?= base_url('renter/file_dispute.php') ?>" method="POST" class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="request_id" value="<?= $rental->getRequestID() ?>">
            <input type="hidden" name="submit_dispute" value="1">

            <div>
                <label for="reason" class="block text-sm font-semibold text-midnight mb-2">
                    Describe your dispute in detail <span class="text-coral">*</span>
                </label>
                <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                    Be specific regarding condition discrepancies, deposit refund concerns, unexpected fines, or communication issues. The administrator will inspect full payment logs, condition photos, and communications to reach an equitable ruling.
                </p>
                <textarea id="reason" 
                          name="reason" 
                          rows="6" 
                          required 
                          maxlength="2000"
                          placeholder="Provide all facts, timelines, and reasons for this dispute..."
                          class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-coral focus:border-transparent transition leading-relaxed"></textarea>
            </div>

            <div class="p-4 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] text-slate-600 text-xs space-y-1.5">
                <div class="font-bold text-midnight flex items-center space-x-1.5">
                    <i class="ri-information-line text-coral text-base"></i>
                    <span>Dispute Resolution Guidelines</span>
                </div>
                <p class="leading-relaxed">
                    1. Filing a dispute notifies the platform administrator and the opposing party immediately.<br>
                    2. The administrator's decision is final and recorded in the permanent audit trail.<br>
                    3. If a fine is disputed and deemed unjustified, the administrator can waive the fine.
                </p>
            </div>

            <div class="flex items-center justify-end space-x-4 pt-4 border-t border-[#E9E7FF]">
                <a href="<?= base_url($userRole === 'Owner' ? 'owner/manage_requests.php' : 'renter/my_rentals.php') ?>" 
                   class="px-5 py-2.5 text-xs font-semibold text-slate-600 hover:text-midnight bg-slate-100 rounded-full transition">
                    Cancel
                </a>
                <button type="submit" 
                        class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold uppercase tracking-wider rounded-full shadow-sm transition flex items-center space-x-2">
                    <i class="ri-scales-3-line"></i>
                    <span>Submit Dispute Case</span>
                </button>
            </div>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
