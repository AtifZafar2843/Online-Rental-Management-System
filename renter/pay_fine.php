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

<div class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('renter/my_rentals.php') ?>" class="hover:text-coral transition">My Rentals</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Pay Fine #<?= $fineId ?></li>
        </ol>
    </nav>

    <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
        <div class="flex items-center space-x-3 pb-4 border-b border-[#E9E7FF]">
            <div class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center">
                <i class="ri-error-warning-line text-xl"></i>
            </div>
            <div>
                <h1 class="text-xl font-display font-bold text-midnight tracking-tight">Pay Outstanding Fine</h1>
                <p class="text-xs text-slate-500 mt-0.5">Settle unpaid balance for damages or late return on completed rental.</p>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
                <ul class="list-disc list-inside space-y-1">
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars($err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <!-- Fine Details Card -->
        <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] space-y-3 text-xs">
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Rental Product:</span>
                <span class="font-semibold text-midnight"><?= htmlspecialchars($request ? $request->getProductTitle() : 'Rental #' . $fine->getRequestID()) ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Fine Type:</span>
                <span class="px-2.5 py-0.5 rounded-full font-semibold text-[11px] bg-rose-50 text-rose-700 border border-rose-200">
                    <?= str_replace('_', ' ', $fine->getFineType()) ?>
                </span>
            </div>
            <?php if ($fine->getLateDays()): ?>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">Late Duration:</span>
                    <span class="text-midnight"><?= $fine->getLateDays() ?> days @ ₹<?= number_format($fine->getRatePerDay() ?? 0, 2) ?>/day</span>
                </div>
            <?php endif; ?>
            <div class="flex justify-between items-center">
                <span class="text-slate-500">Issued On:</span>
                <span class="text-midnight"><?= date('M d, Y H:i', strtotime($fine->getIssueDate())) ?></span>
            </div>
            <div class="pt-3 border-t border-[#E9E7FF] flex justify-between items-center font-bold text-sm">
                <span class="text-midnight">Amount Due:</span>
                <span class="text-rose-600 font-display font-extrabold text-lg">₹<?= number_format($fine->getAmount(), 2) ?></span>
            </div>
        </div>

        <!-- Payment Mode Form -->
        <form method="POST" action="" class="space-y-6">
            <?= csrf_field() ?>
            <input type="hidden" name="fine_id" value="<?= $fineId ?>">

            <div>
                <label class="block text-xs font-semibold text-midnight mb-3">
                    Select Payment Mode
                </label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <?php
                    $modes = [
                        'UPI'          => ['label' => 'UPI / QR',     'desc' => 'Instant UPI transfer',     'icon' => 'ri-smartphone-line'],
                        'Debit Card'   => ['label' => 'Debit Card',   'desc' => 'Visa / Mastercard / RuPay','icon' => 'ri-bank-card-2-line'],
                        'Credit Card'  => ['label' => 'Credit Card',  'desc' => 'Credit card checkout',     'icon' => 'ri-vip-diamond-line'],
                        'Net Banking'  => ['label' => 'Net Banking',  'desc' => 'All Indian banks',         'icon' => 'ri-government-line']
                    ];
                    foreach ($modes as $key => $m):
                        $checked = ($paymentMode === $key) ? 'checked' : '';
                    ?>
                        <label class="relative flex items-start p-3.5 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral cursor-pointer transition">
                            <input type="radio" name="payment_mode" value="<?= $key ?>" <?= $checked ?> class="sr-only peer">
                            <div class="flex-1">
                                <div class="flex items-center justify-between mb-0.5">
                                    <span class="text-xs font-bold text-midnight peer-checked:text-coral flex items-center space-x-1.5">
                                        <i class="<?= $m['icon'] ?> text-base"></i>
                                        <span><?= $m['label'] ?></span>
                                    </span>
                                    <span class="w-3.5 h-3.5 rounded-full border border-slate-300 peer-checked:border-coral peer-checked:bg-coral"></span>
                                </div>
                                <span class="text-[10px] text-slate-500"><?= $m['desc'] ?></span>
                            </div>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="pt-4 border-t border-[#E9E7FF] flex items-center justify-between">
                <a href="<?= base_url('renter/my_rentals.php') ?>" 
                   class="px-5 py-2.5 rounded-full text-xs font-semibold text-slate-600 hover:text-midnight bg-slate-100 transition">
                    &larr; Back to Rentals
                </a>
                <button type="submit" 
                        class="px-6 py-2.5 bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-sm transition flex items-center space-x-2">
                    <span>Pay ₹<?= number_format($fine->getAmount(), 2) ?></span>
                    <span>&rarr;</span>
                </button>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
