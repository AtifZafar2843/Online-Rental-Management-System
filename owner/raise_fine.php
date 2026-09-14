<?php
/**
 * Online Rental Management System (ORMS)
 * Owner — Confirm Return & Assess Fines
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.9, 4, 5 (Rule 8 & 9)
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Product.php';
require_once __DIR__ . '/../classes/Transaction.php';
require_once __DIR__ . '/../classes/Fine.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../config/fine_config.php';

require_role('Owner');

$userId = (int) $_SESSION['user_id'];
$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

if ($requestId <= 0) {
    set_flash('error', 'Invalid rental request specified.');
    redirect('owner/manage_requests.php?status=Active');
}

$request = RentalRequest::findById($requestId);
if (!$request) {
    set_flash('error', 'Rental request not found.');
    redirect('owner/manage_requests.php?status=Active');
}

// Verify ownership
if ($request->getOwnerID() !== $userId) {
    set_flash('error', 'You do not have authorization to manage returns for this product.');
    redirect('owner/manage_requests.php?status=Active');
}

// Verify status is Active
if ($request->getStatus() !== 'Active') {
    set_flash('error', "Return can only be processed for 'Active' rentals (Current: {$request->getStatus()}).");
    redirect('owner/manage_requests.php');
}

$transaction = Transaction::findByRequest($requestId);
if (!$transaction) {
    set_flash('error', 'Transaction record for this rental could not be found.');
    redirect('owner/manage_requests.php?status=Active');
}

$product = Product::findById($request->getProductID());
$depositHeld = $transaction->getDepositAmount();
$maxFineCap = round(2.0 * $depositHeld, 2);
$currentFineRate = get_fine_rate();

$errors = [];
$actualReturnDate = $_POST['actual_return_date'] ?? date('Y-m-d');
$conditionAssessment = $_POST['condition_assessment'] ?? 'Clean';
$damageAmount = (float) ($_POST['damage_amount'] ?? 0.00);
$damageNotes = trim((string) ($_POST['damage_notes'] ?? ''));

// Calculate late detection
$lateDetection = Fine::autoDetectLateReturn($request, $actualReturnDate);
$isLate = ($lateDetection !== null);
$lateDays = $isLate ? (int) $lateDetection['late_days'] : 0;
$lateFee = $isLate ? (float) $lateDetection['amount'] : 0.00;

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security validation failed. Please refresh and try again.";
    }

    if (empty($actualReturnDate)) {
        $errors[] = "Please specify the item return date.";
    } elseif ($actualReturnDate < $request->getStartDate()) {
        $errors[] = "Return date cannot be earlier than rental start date ({$request->getStartDate()}).";
    }

    if (in_array($conditionAssessment, ['Damage', 'Lost'])) {
        if ($damageAmount <= 0) {
            $errors[] = "Please enter a valid fine amount greater than ₹0 for reported " . strtolower($conditionAssessment) . ".";
        }
    } else {
        $damageAmount = 0.00;
        $conditionAssessment = null;
    }

    if (empty($errors)) {
        try {
            $owner = new Owner($userId);
            $result = $owner->raiseFine(
                $requestId,
                $conditionAssessment ?: 'Damage',
                $damageAmount,
                $damageNotes,
                $actualReturnDate
            );

            if ($result['success']) {
                $totalFine = (float) $result['total_fine'];
                $netRefund = (float) $result['net_refund'];
                $unpaid = (float) $result['unpaid_fine'];

                if ($totalFine == 0.00) {
                    $msg = "Return confirmed successfully! Item returned in clean condition with 100% deposit refund (₹" . number_format($netRefund, 2) . ") released to renter.";
                } elseif ($unpaid > 0) {
                    $msg = "Return confirmed. Total fines (₹" . number_format($totalFine, 2) . ") exceeded the deposit. Deposit of ₹" . number_format($depositHeld, 2) . " forfeited. Remaining balance due from renter: ₹" . number_format($unpaid, 2) . ".";
                } else {
                    $msg = "Return confirmed. Total fine of ₹" . number_format($totalFine, 2) . " deducted from deposit. Net remaining refund released: ₹" . number_format($netRefund, 2) . ".";
                }

                set_flash('success', $msg);
                redirect('owner/manage_requests.php?status=Completed');
            } else {
                $errors[] = "Failed to process return. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "Error processing return: " . $e->getMessage();
        }
    }
}

$page_title = "Confirm Return & Assess Fines — #" . $requestId;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 py-8">
    <!-- Breadcrumb -->
    <div class="mb-6 flex items-center space-x-2 text-xs text-slate-400">
        <a href="<?= base_url('owner/dashboard.php') ?>" class="hover:text-white transition">Dashboard</a>
        <span>&rsaquo;</span>
        <a href="<?= base_url('owner/manage_requests.php?status=Active') ?>" class="hover:text-white transition">Manage Requests</a>
        <span>&rsaquo;</span>
        <span class="text-slate-200">Confirm Return #<?= $requestId ?></span>
    </div>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-3">
            <span class="p-2.5 rounded-xl bg-blue-600/10 border border-blue-500/20 text-blue-400 text-xl">📦</span>
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Confirm Product Return & Fine Assessment</h1>
                <p class="text-xs text-slate-400 mt-1">Review rental completion, verify scheduled vs actual return dates, and assess any damages or late fees.</p>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-sm">
            <div class="font-bold mb-1">Please correct the following errors:</div>
            <ul class="list-disc list-inside space-y-1 text-xs">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Left: Product & Rental Summary -->
        <div class="space-y-6">
            <div class="card p-5 border border-slate-800 rounded-2xl bg-slate-900/60 space-y-4">
                <h2 class="text-sm font-bold text-white border-b border-slate-800 pb-2 flex items-center justify-between">
                    <span>Rental Details</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">Active</span>
                </h2>

                <div>
                    <h3 class="font-semibold text-slate-200 text-sm"><?= htmlspecialchars($request->getProductTitle()) ?></h3>
                    <p class="text-xs text-slate-400 mt-0.5">Rented by: <span class="text-white font-medium"><?= htmlspecialchars($request->getRenterName()) ?></span></p>
                </div>

                <div class="space-y-2 text-xs pt-2 border-t border-slate-800">
                    <div class="flex justify-between text-slate-400">
                        <span>Start Date:</span>
                        <span class="font-semibold text-white"><?= date('M d, Y', strtotime($request->getStartDate())) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Scheduled End:</span>
                        <span class="font-semibold text-white"><?= date('M d, Y', strtotime($request->getEndDate())) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Rent Per Day:</span>
                        <span class="font-semibold text-white">₹<?= number_format($request->getRentPerDay(), 2) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Security Deposit Held:</span>
                        <span class="font-bold text-emerald-400">₹<?= number_format($depositHeld, 2) ?></span>
                    </div>
                </div>

                <div class="p-3 bg-slate-950/60 rounded-xl border border-slate-800 text-[11px] text-slate-400 space-y-1">
                    <div class="font-medium text-slate-300">Rule 8 Escrow Terms:</div>
                    <div>• Clean Return: 100% deposit refunded to renter.</div>
                    <div>• Fine $\le$ Deposit: Deducted from deposit, balance refunded.</div>
                    <div>• Fine $>$ Deposit: Deposit forfeited; renter pays remaining.</div>
                </div>
            </div>
        </div>

        <!-- Right: Assessment Form -->
        <div class="lg:col-span-2">
            <form method="POST" action="" class="card p-6 border border-slate-800 rounded-2xl bg-slate-900/60 space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                <!-- 1. Actual Return Date -->
                <div>
                    <label for="actual_return_date" class="block text-xs font-semibold text-slate-300 mb-2">
                        Actual Return Date <span class="text-rose-400">*</span>
                    </label>
                    <input type="date" 
                           id="actual_return_date" 
                           name="actual_return_date" 
                           value="<?= htmlspecialchars($actualReturnDate) ?>" 
                           onchange="calculateSummary()"
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl px-4 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500 transition">
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        Scheduled End Date was: <strong class="text-white"><?= date('M d, Y', strtotime($request->getEndDate())) ?></strong>. If returned after this date, daily late fees apply automatically.
                    </p>
                </div>

                <!-- Late Return Detection Alert -->
                <div id="lateAlertBox" class="p-4 rounded-xl border <?= $isLate ? 'bg-amber-950/30 border-amber-800/60 text-amber-300' : 'bg-slate-950 border-slate-800 text-slate-400' ?> text-xs space-y-1">
                    <div class="font-semibold flex items-center justify-between">
                        <span>Late Return Fee Status:</span>
                        <span id="lateDaysBadge" class="font-mono font-bold"><?= $isLate ? "{$lateDays} Day(s) Late" : "On Time" ?></span>
                    </div>
                    <div class="text-[11px]" id="lateDetailsText">
                        <?php if ($isLate): ?>
                            <?= $lateDays ?> late day(s) × ₹<?= number_format($currentFineRate, 2) ?>/day = <strong class="text-amber-200">₹<?= number_format($lateFee, 2) ?></strong>
                            <?php if ($lateDetection['is_capped'] ?? false): ?>
                                <span class="text-rose-300">(Capped at 2× Security Deposit: ₹<?= number_format($maxFineCap, 2) ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            Item is recorded as returned on or before scheduled end date. Zero late fee.
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Physical Condition Assessment -->
                <div class="pt-4 border-t border-slate-800">
                    <label class="block text-xs font-semibold text-slate-300 mb-3">
                        Physical Condition Assessment <span class="text-rose-400">*</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label class="relative flex flex-col p-4 rounded-xl border border-slate-800 bg-slate-950 hover:border-slate-700 cursor-pointer transition">
                            <input type="radio" name="condition_assessment" value="Clean" <?= $conditionAssessment === 'Clean' ? 'checked' : '' ?> onchange="toggleDamageFields()" class="sr-only peer">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-200 peer-checked:text-emerald-400">Clean / Good</span>
                                <span class="w-4 h-4 rounded-full border border-slate-700 peer-checked:border-emerald-500 peer-checked:bg-emerald-500 flex items-center justify-center"></span>
                            </div>
                            <span class="text-[11px] text-slate-400">Normal wear, no damages or missing parts.</span>
                        </label>

                        <label class="relative flex flex-col p-4 rounded-xl border border-slate-800 bg-slate-950 hover:border-slate-700 cursor-pointer transition">
                            <input type="radio" name="condition_assessment" value="Damage" <?= $conditionAssessment === 'Damage' ? 'checked' : '' ?> onchange="toggleDamageFields()" class="sr-only peer">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-200 peer-checked:text-amber-400">Damaged</span>
                                <span class="w-4 h-4 rounded-full border border-slate-700 peer-checked:border-amber-500 peer-checked:bg-amber-500 flex items-center justify-center"></span>
                            </div>
                            <span class="text-[11px] text-slate-400">Repairable scratches, cracks, or broken parts.</span>
                        </label>

                        <label class="relative flex flex-col p-4 rounded-xl border border-slate-800 bg-slate-950 hover:border-slate-700 cursor-pointer transition">
                            <input type="radio" name="condition_assessment" value="Lost" <?= $conditionAssessment === 'Lost' ? 'checked' : '' ?> onchange="toggleDamageFields()" class="sr-only peer">
                            <div class="flex items-center justify-between mb-1">
                                <span class="text-xs font-bold text-slate-200 peer-checked:text-rose-400">Lost / Total Loss</span>
                                <span class="w-4 h-4 rounded-full border border-slate-700 peer-checked:border-rose-500 peer-checked:bg-rose-500 flex items-center justify-center"></span>
                            </div>
                            <span class="text-[11px] text-slate-400">Item not returned or completely destroyed.</span>
                        </label>
                    </div>
                </div>

                <!-- Damage / Loss Fine Inputs -->
                <div id="damageFieldsBox" class="<?= in_array($conditionAssessment, ['Damage', 'Lost']) ? '' : 'hidden' ?> space-y-4 p-4 rounded-xl bg-slate-950 border border-slate-800">
                    <div>
                        <label for="damage_amount" class="block text-xs font-semibold text-slate-300 mb-1.5">
                            Assessed Repair / Replacement Cost (₹) <span class="text-rose-400">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-sm">₹</span>
                            <input type="number" 
                                   id="damage_amount" 
                                   name="damage_amount" 
                                   step="0.01" 
                                   min="0" 
                                   value="<?= $damageAmount > 0 ? htmlspecialchars((string) $damageAmount) : '' ?>" 
                                   oninput="calculateSummary()"
                                   placeholder="0.00"
                                   class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-8 pr-4 py-2 text-sm text-white focus:outline-none focus:border-blue-500 transition">
                        </div>
                    </div>

                    <div>
                        <label for="damage_notes" class="block text-xs font-semibold text-slate-300 mb-1.5">
                            Damage Description / Assessment Notes
                        </label>
                        <textarea id="damage_notes" 
                                  name="damage_notes" 
                                  rows="2" 
                                  placeholder="Describe the nature of damage, repair invoice details, or missing components..."
                                  class="w-full bg-slate-900 border border-slate-800 rounded-xl px-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-blue-500 transition"><?= htmlspecialchars($damageNotes) ?></textarea>
                    </div>
                </div>

                <!-- 3. Financial Settlement Preview Box -->
                <div class="p-5 rounded-2xl bg-blue-950/20 border border-blue-900/50 space-y-3">
                    <h4 class="text-xs font-bold text-blue-300 uppercase tracking-wider">Settlement & Deposit Deductions Breakdown</h4>
                    
                    <div class="space-y-1.5 text-xs">
                        <div class="flex justify-between text-slate-400">
                            <span>Escrow Security Deposit Held:</span>
                            <span class="font-semibold text-white">₹<?= number_format($depositHeld, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Late Return Fine:</span>
                            <span id="summaryLateFine" class="font-semibold text-amber-400">₹<?= number_format($lateFee, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-400">
                            <span>Damage / Loss Fine:</span>
                            <span id="summaryDamageFine" class="font-semibold text-amber-400">₹<?= number_format($damageAmount, 2) ?></span>
                        </div>
                        <div class="pt-2 border-t border-slate-800 flex justify-between font-bold text-slate-200">
                            <span>Total Assessed Fines:</span>
                            <span id="summaryTotalFine" class="text-rose-400">₹<?= number_format($lateFee + $damageAmount, 2) ?></span>
                        </div>
                        <div class="pt-1.5 flex justify-between font-extrabold text-sm" id="summaryNetRow">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-end gap-3">
                    <a href="<?= base_url('owner/manage_requests.php?status=Active') ?>" 
                       class="w-full sm:w-auto px-5 py-2.5 rounded-xl border border-slate-800 text-slate-400 hover:text-white text-xs font-semibold text-center transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            onclick="return confirm('Are you sure you want to finalize this return and process deposit deductions/refunds?');"
                            class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-600/20 transition flex items-center justify-center space-x-2">
                        <span>✓</span>
                        <span>Confirm Return & Finalize Settlement</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
const scheduledEndDate = "<?= $request->getEndDate() ?>";
const fineRatePerDay = <?= (float) $currentFineRate ?>;
const depositHeld = <?= (float) $depositHeld ?>;
const maxCap = <?= (float) $maxFineCap ?>;

function toggleDamageFields() {
    const radios = document.getElementsByName('condition_assessment');
    let selected = 'Clean';
    for (const r of radios) {
        if (r.checked) {
            selected = r.value;
            break;
        }
    }
    const box = document.getElementById('damageFieldsBox');
    if (selected === 'Damage' || selected === 'Lost') {
        box.classList.remove('hidden');
    } else {
        box.classList.add('hidden');
        document.getElementById('damage_amount').value = '';
    }
    calculateSummary();
}

function calculateSummary() {
    const returnDateInput = document.getElementById('actual_return_date').value;
    let lateFee = 0.00;
    let lateDays = 0;

    if (returnDateInput) {
        const ret = new Date(returnDateInput);
        const end = new Date(scheduledEndDate);
        const diffTime = ret - end;
        if (diffTime > 0) {
            lateDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
            let rawLate = lateDays * fineRatePerDay;
            lateFee = (maxCap > 0 && rawLate > maxCap) ? maxCap : rawLate;
        }
    }

    // Update Late Alert Box
    const alertBox = document.getElementById('lateAlertBox');
    const badge = document.getElementById('lateDaysBadge');
    const details = document.getElementById('lateDetailsText');

    if (lateDays > 0) {
        alertBox.className = "p-4 rounded-xl border bg-amber-950/30 border-amber-800/60 text-amber-300 text-xs space-y-1";
        badge.innerText = lateDays + " Day(s) Late";
        details.innerHTML = lateDays + " late day(s) × ₹" + fineRatePerDay.toFixed(2) + "/day = <strong class='text-amber-200'>₹" + lateFee.toFixed(2) + "</strong>";
        if (maxCap > 0 && (lateDays * fineRatePerDay) > maxCap) {
            details.innerHTML += " <span class='text-rose-300'>(Capped at 2× Security Deposit: ₹" + maxCap.toFixed(2) + ")</span>";
        }
    } else {
        alertBox.className = "p-4 rounded-xl border bg-slate-950 border-slate-800 text-slate-400 text-xs space-y-1";
        badge.innerText = "On Time";
        details.innerText = "Item is recorded as returned on or before scheduled end date. Zero late fee.";
    }

    // Damage fine
    let damageFee = parseFloat(document.getElementById('damage_amount').value) || 0.00;
    if (damageFee < 0) damageFee = 0.00;

    const totalFine = lateFee + damageFee;

    document.getElementById('summaryLateFine').innerText = '₹' + lateFee.toFixed(2);
    document.getElementById('summaryDamageFine').innerText = '₹' + damageFee.toFixed(2);
    document.getElementById('summaryTotalFine').innerText = '₹' + totalFine.toFixed(2);

    const netRow = document.getElementById('summaryNetRow');
    if (totalFine <= depositHeld) {
        const refund = depositHeld - totalFine;
        netRow.innerHTML = `
            <span class="text-emerald-300">Net Refund to Renter:</span>
            <span class="text-emerald-400">₹${refund.toFixed(2)}</span>
        `;
    } else {
        const excess = totalFine - depositHeld;
        netRow.innerHTML = `
            <span class="text-rose-300">Deposit Forfeited (Renter Owes Difference):</span>
            <span class="text-rose-400">₹${excess.toFixed(2)}</span>
        `;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    calculateSummary();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
