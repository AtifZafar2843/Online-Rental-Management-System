<?php
/**
 * Online Rental Management System (ORMS)
 * Renter — Pay Outstanding Fine
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.9, 4 & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Fine.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Renter.php';

require_role('Renter');

$userId = (int) $_SESSION['user_id'];
$fineId = (int) ($_GET['fine_id'] ?? $_POST['fine_id'] ?? 0);

if ($fineId <= 0) {
    set_flash('error', 'Invalid fine specified.');
    redirect('renter/my_rentals.php');
}

$fine = Fine::findById($fineId);
if (!$fine) {
    set_flash('error', 'Fine record not found.');
    redirect('renter/my_rentals.php');
}

if ($fine->getRenterID() !== $userId) {
    set_flash('error', 'You do not have authorization to pay this fine.');
    redirect('renter/my_rentals.php');
}

if ($fine->getStatus() !== 'Unpaid') {
    set_flash('info', "This fine is already {$fine->getStatus()}. No payment required.");
    redirect('renter/my_rentals.php');
}

$request = RentalRequest::findById($fine->getRequestID());
$errors = [];
$paymentMode = $_POST['payment_mode'] ?? 'UPI';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = "Security validation failed. Please try again.";
    }

    $validModes = ['UPI', 'Debit Card', 'Credit Card', 'Net Banking'];
    if (!in_array($paymentMode, $validModes)) {
        $errors[] = "Please select a valid payment method.";
    }

    if (empty($errors)) {
        try {
            $renter = new Renter($userId);
            if ($renter->payFine($fineId, $paymentMode)) {
                set_flash('success', "Fine #{$fineId} of ₹" . number_format($fine->getAmount(), 2) . " paid successfully via {$paymentMode}!");
                redirect('renter/my_rentals.php');
            } else {
                $errors[] = "Payment processing failed. Please try again.";
            }
        } catch (Exception $e) {
            $errors[] = "Payment error: " . $e->getMessage();
        }
    }
}

$page_title = "Pay Outstanding Fine — #" . $fineId;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-2xl mx-auto px-4 py-8">
    <!-- Breadcrumb -->
    <div class="mb-6 flex items-center space-x-2 text-xs text-slate-400">
        <a href="<?= base_url('renter/my_rentals.php') ?>" class="hover:text-white transition">My Rentals</a>
        <span>&rsaquo;</span>
        <span class="text-slate-200">Pay Fine #<?= $fineId ?></span>
    </div>

    <div class="card p-6 border border-slate-800 rounded-2xl bg-slate-900/60 shadow-xl space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-slate-800">
            <span class="p-2.5 rounded-xl bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xl">⚠️</span>
            <div>
                <h1 class="text-xl font-bold text-white tracking-tight">Pay Outstanding Fine</h1>
                <p class="text-xs text-slate-400 mt-0.5">Settle unpaid balance for damages or late return on completed rental.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-xs">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Fine Details Card -->
        <div class="p-4 rounded-xl bg-slate-950/60 border border-slate-800 space-y-3 text-xs">
            <div class="flex justify-between items-center">
                <span class="text-slate-400">Rental Product:</span>
                <span class="font-semibold text-white"><?= htmlspecialchars($request ? $request->getProductTitle() : 'Rental #' . $fine->getRequestID()) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-400">Fine Type:</span>
                <span class="px-2 py-0.5 rounded-md font-semibold text-[11px] bg-rose-500/10 text-rose-400 border border-rose-500/20">
                    <?= str_replace('_', ' ', $fine->getFineType()) ?>
                </span>
            </div>
            <?php if ($fine->getLateDays()): ?>
                <div class="flex justify-between items-center">
                    <span class="text-slate-400">Late Duration:</span>
                    <span class="text-slate-200"><?= $fine->getLateDays() ?> days @ ₹<?= number_format($fine->getRatePerDay() ?? 0, 2) ?>/day</span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between items-center">
                <span class="text-slate-400">Issued On:</span>
                <span class="text-slate-200"><?= date('M d, Y H:i', strtotime($fine->getIssueDate())) ?></span>
            </div>
            <div class="pt-2 border-t border-slate-800 flex justify-between items-center font-bold text-sm">
                <span class="text-white">Amount Due:</span>
                <span class="text-rose-400 font-mono text-base">₹<?= number_format($fine->getAmount(), 2) ?></span>
            </div>
        </div>

        <!-- Payment Mode Form -->
        <form method="POST" action="" class="space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="fine_id" value="<?= $fineId ?>">

            <div>
                <label class="block text-xs font-semibold text-slate-300 mb-3">
                    Select Payment Mode (Academic Simulation)
                </label>
                <div class="grid grid-cols-2 gap-3">
                    <?php
                    $modes = [
                        'UPI'          => ['label' => 'UPI / QR',     'desc' => 'Instant UPI transfer',     'icon' => '📱'],
                        'Debit Card'   => ['label' => 'Debit Card',   'desc' => 'Visa / Mastercard / RuPay','icon' => '💳'],
                        'Credit Card'  => ['label' => 'Credit Card',  'desc' => 'Credit card checkout',     'icon' => '💳'],
                        'Net Banking'  => ['label' => 'Net Banking',  'desc' => 'All Indian banks',         'icon' => '🏦']
                    ];
                    foreach ($modes as $key => $m):
                        $checked = ($paymentMode === $key) ? 'checked' : '';
                    ?>
                        <label class="relative flex items-start p-3.5 rounded-xl border border-slate-800 bg-slate-950 hover:border-slate-700 cursor-pointer transition">
                            <input type="radio" name="payment_mode" value="<?= $key ?>" <?= $checked ?> class="sr-only peer">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-xs font-bold text-slate-200 peer-checked:text-blue-400 flex items-center space-x-1.5">
                                        <span><?= $m['icon'] ?></span>
                                        <span><?= $m['label'] ?></span>
                                    </span>
                                    <span class="w-3.5 h-3.5 rounded-full border border-slate-700 peer-checked:border-blue-500 peer-checked:bg-blue-500"></span>
                                </div>
                                <span class="text-[10px] text-slate-400"><?= $m['desc'] ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-4 border-t border-slate-800 flex items-center justify-between">
                <a href="<?= base_url('renter/my_rentals.php') ?>" 
                   class="px-4 py-2 rounded-xl text-xs font-semibold text-slate-400 hover:text-white transition">
                    &larr; Back to Rentals
                </a>
                <button type="submit" 
                        class="px-6 py-2.5 bg-rose-600 hover:bg-rose-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-rose-600/20 transition flex items-center space-x-2">
                    <span>Pay ₹<?= number_format($fine->getAmount(), 2) ?></span>
                    <span>&rarr;</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
