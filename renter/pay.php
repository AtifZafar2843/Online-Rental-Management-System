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

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-coral transition">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('renter/my_rentals.php') ?>" class="hover:text-coral transition">My Rentals</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Payment Checkout</li>
        </ol>
    </nav>

    <!-- Page Title -->
    <div class="mb-8">
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight flex items-center space-x-3">
            <i class="ri-secure-payment-line text-coral text-2xl"></i>
            <span>Secure Rental Checkout</span>
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">Review your rental charges, select your payment method, and complete your reservation.</p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm">
            <div class="font-bold flex items-center space-x-2 mb-1">
                <i class="ri-error-warning-line text-lg"></i>
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
            <form action="<?= base_url('renter/pay.php') ?>" method="POST" class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-8 shadow-sm space-y-6">
                <?= csrf_field() ?>
                <input type="hidden" name="request_id" value="<?= $requestId ?>">

                <div>
                    <h2 class="text-base font-display font-bold text-midnight mb-2 flex items-center space-x-2">
                        <i class="ri-bank-card-line text-coral"></i>
                        <span>Select Payment Method</span>
                    </h2>
                    <p class="text-xs text-slate-500 mb-4">Choose your preferred payment gateway:</p>

                    <!-- Payment Mode Options -->
                    <div class="space-y-3">
                        <!-- UPI Option -->
                        <label class="flex items-center justify-between p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="UPI" checked class="text-coral focus:ring-coral">
                                <div>
                                    <div class="font-bold text-sm text-midnight flex items-center space-x-2">
                                        <span>UPI Instant Transfer</span>
                                        <span class="px-2 py-0.5 rounded-full text-[10px] bg-emerald-50 text-emerald-700 font-bold border border-emerald-200">Fastest</span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">Google Pay, PhonePe, Paytm, BHIM</p>
                                </div>
                            </div>
                            <i class="ri-smartphone-line text-xl text-slate-400"></i>
                        </label>

                        <!-- Debit Card -->
                        <label class="flex items-center justify-between p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="Debit_Card" class="text-coral focus:ring-coral">
                                <div>
                                    <div class="font-bold text-sm text-midnight">Debit Card (ATM)</div>
                                    <p class="text-xs text-slate-500 mt-0.5">Visa, Mastercard, RuPay</p>
                                </div>
                            </div>
                            <i class="ri-bank-card-2-line text-xl text-slate-400"></i>
                        </label>

                        <!-- Credit Card -->
                        <label class="flex items-center justify-between p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="Credit_Card" class="text-coral focus:ring-coral">
                                <div>
                                    <div class="font-bold text-sm text-midnight">Credit Card</div>
                                    <p class="text-xs text-slate-500 mt-0.5">Visa, Mastercard, Amex</p>
                                </div>
                            </div>
                            <i class="ri-vip-diamond-line text-xl text-slate-400"></i>
                        </label>

                        <!-- Net Banking -->
                        <label class="flex items-center justify-between p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="Net_Banking" class="text-coral focus:ring-coral">
                                <div>
                                    <div class="font-bold text-sm text-midnight">Net Banking</div>
                                    <p class="text-xs text-slate-500 mt-0.5">SBI, HDFC, ICICI, Axis, PNB</p>
                                </div>
                            </div>
                            <i class="ri-government-line text-xl text-slate-400"></i>
                        </label>

                        <!-- Pay on Pickup / COD -->
                        <label class="flex items-center justify-between p-4 rounded-2xl border border-[#E9E7FF] bg-[#FAF8F5] hover:border-coral cursor-pointer transition">
                            <div class="flex items-center space-x-3">
                                <input type="radio" name="payment_mode" value="COD" class="text-coral focus:ring-coral">
                                <div>
                                    <div class="font-bold text-sm text-midnight">Pay at Pickup / Physical Handover</div>
                                    <p class="text-xs text-slate-500 mt-0.5">Verify item condition with owner before finalizing</p>
                                </div>
                            </div>
                            <i class="ri-hand-coin-line text-xl text-slate-400"></i>
                        </label>
                    </div>
                </div>

                <!-- Trust Guarantee Box -->
                <div class="p-4 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] text-xs text-slate-600 space-y-1">
                    <div class="font-bold text-midnight flex items-center space-x-1.5">
                        <i class="ri-shield-check-line text-emerald-600 text-base"></i>
                        <span>Secure Escrow Protection</span>
                    </div>
                    <p>Security deposit is safely held in escrow. Authorizing payment will activate your booking and generate your digital invoice.</p>
                </div>

                <!-- Authorize Button -->
                <div class="pt-2">
                    <button type="submit" 
                            class="w-full py-4 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-glow-coral transition flex items-center justify-center space-x-2">
                        <i class="ri-lock-2-line text-sm"></i>
                        <span>Confirm Payment of ₹<?= number_format($totalPayable, 2) ?></span>
                    </button>
                    <div class="text-center mt-3">
                        <a href="<?= base_url('renter/my_rentals.php') ?>" class="text-xs text-slate-500 hover:text-coral underline">
                            Cancel and return to My Rentals
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Right Column: Order Summary & Item Breakdown -->
        <div class="lg:col-span-5 space-y-6">
            <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-8 shadow-sm space-y-5">
                <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider block border-b border-[#E9E7FF] pb-3">
                    Order Summary
                </span>

                <!-- Product Mini Card -->
                <div class="flex items-start space-x-3.5">
                    <div class="w-16 h-16 rounded-2xl overflow-hidden bg-slate-100 border border-[#E9E7FF] flex-shrink-0">
                        <img src="<?= base_url($rentalRequest->getPrimaryImage()) ?>" 
                             alt="<?= htmlspecialchars($rentalRequest->getProductTitle()) ?>" 
                             class="w-full h-full object-cover"
                             onerror="this.src='<?= base_url('assets/img/no-image.svg') ?>'">
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="font-display font-bold text-midnight text-sm truncate"><?= htmlspecialchars($rentalRequest->getProductTitle()) ?></h3>
                        <div class="text-xs text-slate-500 mt-0.5">Owner: <strong class="text-midnight"><?= htmlspecialchars($rentalRequest->getOwnerName()) ?></strong></div>
                        <div class="text-[11px] font-mono text-coral mt-0.5">Booking #REQ-<?= $rentalRequest->getRequestID() ?></div>
                    </div>
                </div>

                <!-- Schedule Box -->
                <div class="p-3.5 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] text-xs space-y-1.5">
                    <div class="flex justify-between text-slate-500">
                        <span>Rental Schedule:</span>
                        <span class="font-bold text-midnight"><?= date('M d', strtotime($rentalRequest->getStartDate())) ?> &rarr; <?= date('M d, Y', strtotime($rentalRequest->getEndDate())) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-500">
                        <span>Total Duration:</span>
                        <span class="font-bold text-midnight"><?= $totalDays ?> day(s)</span>
                    </div>
                </div>

                <!-- Price Breakdown Table -->
                <div class="space-y-2.5 text-xs">
                    <div class="flex justify-between text-slate-600">
                        <span>Rental Fee (<?= $totalDays ?>d × ₹<?= number_format($rentalRequest->getRentPerDay(), 2) ?>/d):</span>
                        <span class="font-bold text-midnight">₹<?= number_format($rentAmount, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Security Deposit (Refundable):</span>
                        <span class="font-bold text-emerald-600">₹<?= number_format($depositAmount, 2) ?></span>
                    </div>
                    <div class="flex justify-between text-slate-600">
                        <span>Platform Convenience Fee:</span>
                        <span class="text-slate-400">₹0.00 (Free)</span>
                    </div>

                    <div class="pt-3 border-t border-[#E9E7FF] flex justify-between items-baseline">
                        <div>
                            <span class="text-sm font-bold text-midnight">Grand Total:</span>
                            <span class="text-[10px] text-slate-400 block">All taxes & deposit included</span>
                        </div>
                        <div class="text-2xl font-display font-extrabold text-coral">
                            ₹<?= number_format($totalPayable, 2) ?>
                        </div>
                    </div>
                </div>

                <!-- Deposit Trust Guarantee -->
                <div class="pt-4 border-t border-[#E9E7FF] space-y-2 text-[11px] text-slate-500">
                    <div class="flex items-center space-x-2 text-emerald-600 font-bold">
                        <i class="ri-shield-check-line text-base"></i>
                        <span>100% Security Deposit Protection</span>
                    </div>
                    <p>Your deposit of ₹<?= number_format($depositAmount, 2) ?> is securely held in escrow throughout the rental and is refunded upon clean item return.</p>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
