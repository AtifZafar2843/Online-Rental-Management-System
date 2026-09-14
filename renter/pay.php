<?php
/**
 * Online Rental Management System (ORMS)
 * Payment Checkout Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 7), Section 3.8, Section 5 (Rule 7, 8)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/RentalRequest.php';
require_once __DIR__ . '/../classes/Renter.php';
require_once __DIR__ . '/../classes/Transaction.php';
require_once __DIR__ . '/../classes/exceptions/PaymentFailedException.php';
require_once __DIR__ . '/../classes/exceptions/UnauthorizedActionException.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';

require_role('Renter');

$renterId = (int) current_user_id();
$requestId = (int) ($_GET['request_id'] ?? $_POST['request_id'] ?? 0);

if ($requestId <= 0) {
    set_flash('error', 'Invalid rental request specified.');
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

$rentalRequest = RentalRequest::findById($requestId);
if (!$rentalRequest) {
    set_flash('error', 'Rental request not found.');
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

if ($rentalRequest->getRenterID() !== $renterId) {
    set_flash('error', 'Unauthorized access to this booking.');
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

// If already Active, check if transaction exists and redirect to receipt
if ($rentalRequest->getStatus() === 'Active') {
    $existingTx = Transaction::findByRequest($requestId);
    if ($existingTx) {
        header('Location: ' . base_url('renter/receipt.php?id=' . $existingTx->getTransactionID()));
        exit;
    }
    set_flash('info', 'This rental is already active.');
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

// Only Approved requests can be paid for
if ($rentalRequest->getStatus() !== 'Approved') {
    set_flash('error', 'Only Approved rental requests can be paid for. Current status: ' . $rentalRequest->getStatus());
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

$totalDays = $rentalRequest->getTotalDays();
$rentAmount = $rentalRequest->getTotalAmount();
$depositAmount = $rentalRequest->getSecurityDeposit();
$totalPayable = round($rentAmount + $depositAmount, 2);

$errors = [];

// Handle Payment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'Security token expired. Please try again.';
    } else {
        $paymentMode = trim($_POST['payment_mode'] ?? 'UPI');

        try {
            $renter = new Renter($renterId);
            $transaction = $renter->makePayment($requestId, $paymentMode);

            set_flash('success', "Payment of ₹" . number_format($transaction->getTotalPaid(), 2) . " completed successfully! Your rental booking is now ACTIVE.");
            header('Location: ' . base_url('renter/receipt.php?id=' . $transaction->getTransactionID()));
            exit;

        } catch (PaymentFailedException $e) {
            $errors[] = $e->getMessage();
        } catch (UnauthorizedActionException $e) {
            $errors[] = $e->getMessage();
        } catch (ORMSException $e) {
            $errors[] = $e->getMessage();
        } catch (Exception $e) {
            $errors[] = 'An unexpected payment error occurred: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Checkout & Payment — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-white">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('renter/my_rentals.php') ?>" class="hover:text-white">My Rentals</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">Payment Checkout</li>
        </ol>
    </nav>

    <!-- Page Title -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center space-x-3">
            <span>💳</span>
            <span>Secure Rental Checkout</span>
        </h1>
        <p class="text-sm text-slate-400 mt-1">Review your rental charges, choose a simulated payment option, and confirm your booking.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-sm">
            <div class="font-bold flex items-center space-x-2 mb-1">
                <span>⚠️</span>
                <span>Payment Processing Error:</span>
            </div>
            <ul class="list-disc list-inside space-y-1 text-xs">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
        <!-- Left Column: Payment Form -->
        <div class="lg:col-span-7 space-y-6">
            <form action="<?= base_url('renter/pay.php') ?>" method="POST" class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                <div>
                    <h2 class="text-base font-bold text-white mb-3 flex items-center space-x-2">
                        <span>🏷️</span>
                        <span>Select Payment Method</span>
                    </h2>
                    <p class="text-xs text-slate-400 mb-4">Choose your preferred academic payment simulation method:</p>

                    <!-- Payment Mode Options -->
                    <div class="space-y-3">
                        <!-- UPI Option -->
                        <label class="flex items-center justify-between p-4 rounded-xl border border-slate-700 bg-slate-950 hover:border-blue-500 cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="UPI" checked class="text-blue-600 focus:ring-blue-500 bg-slate-900 border-slate-700">
                                <div>
                                    <div class="font-bold text-sm text-white flex items-center space-x-2">
                                        <span>UPI Instant Transfer</span>
                                        <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-950 text-emerald-300 font-bold border border-emerald-700/60">Fastest</span>
                                    </div>
                                    <p class="text-xs text-slate-400 mt-0.5">Google Pay, PhonePe, Paytm, BHIM</p>
                                </div>
                            </div>
                            <span class="text-xl">📱</span>
                        </label>

                        <!-- Debit Card -->
                        <label class="flex items-center justify-between p-4 rounded-xl border border-slate-700 bg-slate-950 hover:border-blue-500 cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="Debit_Card" class="text-blue-600 focus:ring-blue-500 bg-slate-900 border-slate-700">
                                <div>
                                    <div class="font-bold text-sm text-white">Debit Card (ATM)</div>
                                    <p class="text-xs text-slate-400 mt-0.5">Visa, Mastercard, RuPay</p>
                                </div>
                            </div>
                            <span class="text-xl">💳</span>
                        </label>

                        <!-- Credit Card -->
                        <label class="flex items-center justify-between p-4 rounded-xl border border-slate-700 bg-slate-950 hover:border-blue-500 cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="Credit_Card" class="text-blue-600 focus:ring-blue-500 bg-slate-900 border-slate-700">
                                <div>
                                    <div class="font-bold text-sm text-white">Credit Card</div>
                                    <p class="text-xs text-slate-400 mt-0.5">Visa, Mastercard, Amex</p>
                                </div>
                            </div>
                            <span class="text-xl">💎</span>
                        </label>

                        <!-- Net Banking -->
                        <label class="flex items-center justify-between p-4 rounded-xl border border-slate-700 bg-slate-950 hover:border-blue-500 cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="Net_Banking" class="text-blue-600 focus:ring-blue-500 bg-slate-900 border-slate-700">
                                <div>
                                    <div class="font-bold text-sm text-white">Net Banking</div>
                                    <p class="text-xs text-slate-400 mt-0.5">SBI, HDFC, ICICI, Axis, PNB</p>
                                </div>
                            </div>
                            <span class="text-xl">🏦</span>
                        </label>

                        <!-- Pay on Pickup / COD -->
                        <label class="flex items-center justify-between p-4 rounded-xl border border-slate-700 bg-slate-950 hover:border-blue-500 cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="COD" class="text-blue-600 focus:ring-blue-500 bg-slate-900 border-slate-700">
                                <div>
                                    <div class="font-bold text-sm text-white">Pay at Pickup / Physical Inspection</div>
                                    <p class="text-xs text-slate-400 mt-0.5">Verify item condition with owner before cash payment</p>
                                </div>
                            </div>
                            <span class="text-xl">🤝</span>
                        </label>
                    </div>
                </div>

                <!-- Simulation Info Disclaimer -->
                <div class="p-3.5 rounded-xl bg-blue-950/30 border border-blue-900/50 text-[11px] text-blue-300 space-y-1">
                    <div class="font-bold flex items-center space-x-1.5">
                        <span>🔒</span>
                        <span>Academic Demonstration Environment</span>
                    </div>
                    <p>No real money will be deducted from your bank. Clicking authorize will instantly confirm the transaction, transition booking status to <strong>Active</strong>, and generate your tax receipt.</p>
                </div>

                <!-- Authorize Button -->
                <div class="pt-2">
                    <button type="submit" 
                            class="w-full py-4 bg-gradient-to-r from-emerald-600 via-teal-600 to-blue-600 hover:from-emerald-500 hover:to-blue-500 text-white font-extrabold text-sm uppercase tracking-wider rounded-xl shadow-lg shadow-emerald-500/25 transition flex items-center justify-center space-x-2">
                        <span>🔒</span>
                        <span>Authorize & Confirm Payment of ₹<?= number_format($totalPayable, 2) ?></span>
                    </button>
                    <div class="text-center mt-3">
                        <a href="<?= base_url('renter/my_rentals.php') ?>" class="text-xs text-slate-400 hover:text-white underline">
                            Cancel and return to My Rentals
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Right Column: Order Summary & Item Breakdown -->
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl space-y-5">
                <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider block border-b border-slate-800 pb-3">
                    Order Summary
                </span>

                <!-- Product Mini Card -->
                <div class="flex items-start space-x-3.5">
                    <div class="w-16 h-16 rounded-xl overflow-hidden bg-slate-950 border border-slate-800 flex-shrink-0">
                        <img src="<?= base_url($rentalRequest->getPrimaryImage()) ?>" 
                             alt="<?= htmlspecialchars($rentalRequest->getProductTitle()) ?>" 
                             class="w-full h-full object-cover"
                             onerror="this.src='<?= base_url('assets/img/no-image.svg') ?>'">
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-bold text-white text-sm truncate"><?= htmlspecialchars($rentalRequest->getProductTitle()) ?></h3>
                        <div class="text-xs text-slate-400 mt-0.5">Owner: <strong class="text-slate-200"><?= htmlspecialchars($rentalRequest->getOwnerName()) ?></strong></div>
                        <div class="text-[11px] font-mono text-blue-400 mt-0.5">Booking #REQ-<?= $rentalRequest->getRequestID() ?></div>
                    </div>
                </div>

                <!-- Schedule Box -->
                <div class="p-3.5 rounded-xl bg-slate-950/60 border border-slate-800 text-xs space-y-1.5">
                    <div class="flex justify-between text-slate-400">
                        <span>Rental Schedule:</span>
                        <span class="font-bold text-white"><?= date('M d', strtotime($rentalRequest->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($rentalRequest->getEndDate())) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Total Duration:</span>
                        <span class="font-bold text-white"><?= $totalDays ?> day(s)</span>
                    </div>
                </div>

                <!-- Price Breakdown Table -->
                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between text-slate-400">
                        <span>Rental Fee (<?= $totalDays ?>d × ₹<?= number_format($rentalRequest->getRentPerDay(), 2) ?>/d):</span>
                        <span class="font-bold text-white">₹<?= number_format($rentAmount, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Security Deposit (Refundable):</span>
                        <span class="font-bold text-emerald-400">₹<?= number_format($depositAmount, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Platform Convenience Fee:</span>
                        <span class="text-slate-500">₹0.00 (Free)</span>
                    </div>

                    <div class="pt-3 border-t border-slate-800 flex justify-between items-baseline">
                        <div>
                            <span class="text-sm font-bold text-white">Grand Total:</span>
                            <span class="text-[10px] text-slate-500 block">All taxes & deposit included</span>
                        </div>
                        <div class="text-2xl font-black text-emerald-400">
                            ₹<?= number_format($totalPayable, 2) ?>
                        </div>
                    </div>
                </div>

                <!-- Deposit Trust Guarantee -->
                <div class="pt-4 border-t border-slate-800 space-y-2 text-[11px] text-slate-400">
                    <div class="flex items-center space-x-2 text-emerald-400 font-bold">
                        <span>🛡️</span>
                        <span>100% Security Deposit Protection</span>
                    </div>
                    <p>Your deposit of ₹<?= number_format($depositAmount, 2) ?> is securely held in escrow throughout the rental and is refunded upon clean item return.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
