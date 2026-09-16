<?php
/**
 * Online Rental Management System (ORMS)
 * Owner — Confirm Return & Assess Fines
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
    if (!csrf_verify()) {
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

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-coral transition">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('owner/dashboard.php') ?>" class="hover:text-coral transition">Dashboard</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('owner/manage_requests.php?status=Active') ?>" class="hover:text-coral transition">Active Rentals</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Confirm Return #<?= $requestId ?></li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-2 text-coral text-xs font-semibold uppercase tracking-wider mb-1">
            <i class="ri-box-3-line text-sm"></i>
            <span>Inspection & Escrow Settlement</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">Confirm Return & Assess Fines</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
            Verify product return date, evaluate physical condition, and settle escrow security deposit deductions or full refunds.
        </p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            <div class="font-bold mb-1 flex items-center space-x-2 text-rose-800">
                <i class="ri-error-warning-line text-base"></i>
                <span>Please correct the following errors:</span>
            </div>
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
            <div class="p-6 border border-[#E9E7FF] rounded-3xl bg-white shadow-sm space-y-4">
                <div class="flex items-center justify-between border-b border-[#E9E7FF] pb-3">
                    <span class="text-xs font-semibold text-midnight uppercase tracking-wider">Rental Details</span>
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Active
                    </span>
                </div>

                <div>
                    <h3 class="font-display font-bold text-midnight text-base"><?= htmlspecialchars($request->getProductTitle()) ?></h3>
                    <p class="text-xs text-slate-500 mt-0.5">Renter: <span class="text-midnight font-semibold"><?= htmlspecialchars($request->getRenterName()) ?></span></p>
                </div>

                <div class="space-y-2 text-xs pt-3 border-t border-[#E9E7FF]">
                    <div class="flex justify-between text-slate-500">
                        <span>Start Date:</span>
                        <span class="font-semibold text-midnight"><?= date('M d, Y', strtotime($request->getStartDate())) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Scheduled End:</span>
                        <span class="font-semibold text-midnight"><?= date('M d, Y', strtotime($request->getEndDate())) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Rent Per Day:</span>
                        <span class="font-semibold text-midnight">₹<?= number_format($request->getRentPerDay(), 2) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Deposit Held:</span>
                        <span class="font-bold text-emerald-600">₹<?= number_format($depositHeld, 2) ?></span>
                    </div>
                </div>

                <div class="p-4 bg-[#FAF8F5] rounded-2xl border border-[#E9E7FF] text-[11px] text-slate-600 space-y-1.5">
                    <div class="font-semibold text-midnight flex items-center space-x-1.5">
                        <i class="ri-shield-check-line text-coral text-sm"></i>
                        <span>Escrow Settlement Policy:</span>
                    </div>
                    <div>• Clean Return: 100% deposit released back to renter.</div>
                    <div>• Fine &le; Deposit: Deducted directly from deposit; balance refunded.</div>
                    <div>• Fine &gt; Deposit: Full deposit forfeited; remaining balance invoiced to renter.</div>
                </div>
            </div>
        </div>

        <!-- Right: Assessment Form -->
        <div class="lg:col-span-2">
            <form method="POST" action="" class="p-6 sm:p-8 border border-[#E9E7FF] rounded-3xl bg-white shadow-sm space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                <!-- 1. Actual Return Date -->
                <div>
                    <label for="actual_return_date" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                        Actual Return Date <span class="text-coral">*</span>
                    </label>
                    <input type="date" 
                           id="actual_return_date" 
                           name="actual_return_date" 
                           value="<?= htmlspecialchars($actualReturnDate) ?>" 
                           onchange="calculateSummary()"
                           class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                    <p class="text-[11px] text-slate-400 mt-1.5">
                        Scheduled End Date: <strong class="text-midnight"><?= date('M d, Y', strtotime($request->getEndDate())) ?></strong>. Any return after this date incurs daily late fees automatically.
                    </p>
                </div>

                <!-- Late Return Detection Alert -->
                <div id="lateAlertBox" class="p-4 rounded-2xl border <?= $isLate ? 'bg-amber-50 border-amber-200 text-amber-800' : 'bg-[#FAF8F5] border-[#E9E7FF] text-slate-600' ?> text-xs space-y-1">
                    <div class="font-semibold flex items-center justify-between">
                        <span class="flex items-center space-x-1.5">
                            <i class="ri-time-line text-sm"></i>
                            <span>Late Return Fee Status:</span>
                        </span>
                        <span id="lateDaysBadge" class="font-mono font-bold"><?= $isLate ? "{$lateDays} Day(s) Late" : "On Time" ?></span>
                    </div>
                    <div class="text-[11px]" id="lateDetailsText">
                        <?php if ($isLate): ?>
                            <?= $lateDays ?> late day(s) × ₹<?= number_format($currentFineRate, 2) ?>/day = <strong class="text-amber-900">₹<?= number_format($lateFee, 2) ?></strong>
                            <?php if ($lateDetection['is_capped'] ?? false): ?>
                                <span class="text-rose-600">(Capped at 2× Security Deposit: ₹<?= number_format($maxFineCap, 2) ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            Item is recorded as returned on or before scheduled end date. Zero late fee.
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Physical Condition Assessment -->
                <div class="pt-4 border-t border-[#E9E7FF]">
                    <label class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-3">
                        Physical Condition Assessment <span class="text-coral">*</span>
                    </label>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <label id="card_clean" class="relative flex flex-col p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral/40 cursor-pointer transition">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-bold text-midnight">Clean / Good</span>
                                <input type="radio" name="condition_assessment" value="Clean" <?= $conditionAssessment === 'Clean' ? 'checked' : '' ?> onchange="toggleDamageFields()" class="w-4 h-4 text-emerald-600 accent-emerald-600 cursor-pointer">
                            </div>
                            <span class="text-[11px] text-slate-500">Normal wear, zero damage or missing parts.</span>
                        </label>

                        <label id="card_damage" class="relative flex flex-col p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral/40 cursor-pointer transition">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-bold text-midnight">Damaged</span>
                                <input type="radio" name="condition_assessment" value="Damage" <?= $conditionAssessment === 'Damage' ? 'checked' : '' ?> onchange="toggleDamageFields()" class="w-4 h-4 text-amber-600 accent-amber-600 cursor-pointer">
                            </div>
                            <span class="text-[11px] text-slate-500">Repairable scratches, dents, or broken parts.</span>
                        </label>

                        <label id="card_lost" class="relative flex flex-col p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral/40 cursor-pointer transition">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-xs font-bold text-midnight">Lost / Destroyed</span>
                                <input type="radio" name="condition_assessment" value="Lost" <?= $conditionAssessment === 'Lost' ? 'checked' : '' ?> onchange="toggleDamageFields()" class="w-4 h-4 text-rose-600 accent-rose-600 cursor-pointer">
                            </div>
                            <span class="text-[11px] text-slate-500">Item unreturned or totally beyond repair.</span>
                        </label>
                    </div>
                </div>

                <!-- Damage / Loss Fine Inputs -->
                <div id="damageFieldsBox" class="<?= in_array($conditionAssessment, ['Damage', 'Lost']) ? '' : 'hidden' ?> space-y-4 p-5 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF]">
                    <div>
                        <label for="damage_amount" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Assessed Repair / Replacement Cost (₹) <span class="text-coral">*</span>
                        </label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 text-sm">₹</span>
                            <input type="number" 
                                   id="damage_amount" 
                                   name="damage_amount" 
                                   step="0.01" 
                                   min="0" 
                                   value="<?= $damageAmount > 0 ? htmlspecialchars((string) $damageAmount) : '' ?>" 
                                   oninput="calculateSummary()"
                                   placeholder="0.00"
                                   class="w-full bg-white border border-[#E9E7FF] rounded-2xl pl-8 pr-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                        </div>
                    </div>

                    <div>
                        <label for="damage_notes" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                            Damage Description / Assessment Notes
                        </label>
                        <textarea id="damage_notes" 
                                  name="damage_notes" 
                                  rows="2" 
                                  placeholder="Describe the nature of damage, repair estimate, or missing accessories..."
                                  class="w-full bg-white border border-[#E9E7FF] rounded-2xl px-4 py-3 text-xs text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition"><?= htmlspecialchars($damageNotes) ?></textarea>
                    </div>
                </div>

                <!-- 3. Financial Settlement Preview Box -->
                <div class="p-6 rounded-3xl bg-[#FAF8F5] border border-[#E9E7FF] space-y-3">
                    <h4 class="text-xs font-semibold text-midnight uppercase tracking-wider flex items-center space-x-1.5">
                        <i class="ri-scales-3-line text-coral text-sm"></i>
                        <span>Settlement & Deductions Preview</span>
                    </h4>
                    
                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between text-slate-500">
                            <span>Escrow Security Deposit Held:</span>
                            <span class="font-semibold text-midnight">₹<?= number_format($depositHeld, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Late Return Fine:</span>
                            <span id="summaryLateFine" class="font-semibold text-amber-600">₹<?= number_format($lateFee, 2) ?></span>
                        </div>
                        <div class="flex justify-between text-slate-500">
                            <span>Damage / Loss Fine:</span>
                            <span id="summaryDamageFine" class="font-semibold text-amber-600">₹<?= number_format($damageAmount, 2) ?></span>
                        </div>
                        <div class="pt-2 border-t border-[#E9E7FF] flex justify-between font-bold text-midnight">
                            <span>Total Assessed Fines:</span>
                            <span id="summaryTotalFine" class="text-rose-600">₹<?= number_format($lateFee + $damageAmount, 2) ?></span>
                        </div>
                        <div class="pt-2 border-t border-[#E9E7FF] flex justify-between font-extrabold text-sm" id="summaryNetRow">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="pt-4 flex flex-col sm:flex-row items-center justify-end gap-3 border-t border-[#E9E7FF]">
                    <a href="<?= base_url('owner/manage_requests.php?status=Active') ?>" 
                       class="w-full sm:w-auto px-5 py-2.5 rounded-full border border-slate-200 text-slate-600 hover:text-midnight text-xs font-semibold text-center transition">
                        Cancel
                    </a>
                    <button type="submit" 
                            onclick="return confirm('Are you sure you want to finalize this return and process deposit deductions/refunds?');"
                            class="w-full sm:w-auto px-7 py-3 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs rounded-full shadow-glow-coral transition flex items-center justify-center space-x-1.5">
                        <i class="ri-check-line"></i>
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

    const cardClean = document.getElementById('card_clean');
    const cardDamage = document.getElementById('card_damage');
    const cardLost = document.getElementById('card_lost');

    if (cardClean && cardDamage && cardLost) {
        cardClean.className = "relative flex flex-col p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral/40 cursor-pointer transition";
        cardDamage.className = "relative flex flex-col p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral/40 cursor-pointer transition";
        cardLost.className = "relative flex flex-col p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral/40 cursor-pointer transition";

        if (selected === 'Clean') {
            cardClean.className = "relative flex flex-col p-4 rounded-2xl border-2 border-emerald-500 bg-emerald-50/60 cursor-pointer transition shadow-sm";
        } else if (selected === 'Damage') {
            cardDamage.className = "relative flex flex-col p-4 rounded-2xl border-2 border-amber-500 bg-amber-50/60 cursor-pointer transition shadow-sm";
        } else if (selected === 'Lost') {
            cardLost.className = "relative flex flex-col p-4 rounded-2xl border-2 border-rose-500 bg-rose-50/60 cursor-pointer transition shadow-sm";
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
        alertBox.className = "p-4 rounded-2xl border bg-amber-50 border-amber-200 text-amber-800 text-xs space-y-1";
        badge.innerText = lateDays + " Day(s) Late";
        details.innerHTML = lateDays + " late day(s) × ₹" + fineRatePerDay.toFixed(2) + "/day = <strong class='text-amber-900'>₹" + lateFee.toFixed(2) + "</strong>";
        if (maxCap > 0 && (lateDays * fineRatePerDay) > maxCap) {
            details.innerHTML += " <span class='text-rose-600'>(Capped at 2× Security Deposit: ₹" + maxCap.toFixed(2) + ")</span>";
        }
    } else {
        alertBox.className = "p-4 rounded-2xl border bg-[#FAF8F5] border-[#E9E7FF] text-slate-600 text-xs space-y-1";
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
            <span class="text-emerald-700">Net Refund Released to Renter:</span>
            <span class="text-emerald-700 font-bold text-base">₹${refund.toFixed(2)}</span>
        `;
    } else {
        const excess = totalFine - depositHeld;
        netRow.innerHTML = `
            <span class="text-rose-700">Deposit Forfeited (Renter Owes Difference):</span>
            <span class="text-rose-700 font-bold text-base">₹${excess.toFixed(2)}</span>
        `;
    }
}

document.addEventListener('DOMContentLoaded', () => {
    toggleDamageFields();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
