<?php
/**
 * Online Rental Management System (ORMS)
 * Transaction Receipt & Tax Invoice
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 (Transaction::getReceipt) & Synopsis 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Transaction.php';

if (!is_logged_in()) {
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$transactionId = (int) ($_GET['id'] ?? $_GET['transaction_id'] ?? 0);
if ($transactionId <= 0) {
    set_flash('error', 'Invalid transaction specified.');
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

$transaction = Transaction::findById($transactionId);
if (!$transaction) {
    set_flash('error', 'Transaction record not found.');
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

$currentUserId = (int) current_user_id();
$isAdminUser = is_admin();

// Authorization: Only Payer, Owner, or Admin can view this receipt
if (!$isAdminUser && $transaction->getPayerID() !== $currentUserId && $transaction->getOwnerID() !== $currentUserId) {
    set_flash('error', 'Access denied to this transaction receipt.');
    header('Location: ' . base_url('index.php'));
    exit;
}

$receipt = $transaction->getReceipt();

$pageTitle = 'Receipt #' . $receipt['receipt_number'] . ' — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Action Bar (Hide in print) -->
    <div class="flex items-center justify-between mb-8 print:hidden">
        <a href="<?= base_url('renter/my_rentals.php') ?>" 
           class="inline-flex items-center space-x-1.5 text-xs font-semibold text-slate-500 hover:text-coral transition">
            <i class="ri-arrow-left-line"></i>
            <span>Back to My Bookings</span>
        </a>

        <div class="flex items-center space-x-3">
            <button onclick="window.print()" 
                    class="inline-flex items-center space-x-2 px-5 py-2.5 bg-midnight hover:bg-[#1a233d] text-white font-semibold text-xs uppercase tracking-wider rounded-full shadow-sm transition">
                <i class="ri-printer-line text-sm"></i>
                <span>Print Receipt</span>
            </button>
        </div>
    </div>

    <!-- Printable Invoice Document Card -->
    <div class="bg-white border border-[#E9E7FF] rounded-3xl p-8 sm:p-12 shadow-sm space-y-8 print:bg-white print:text-black print:border-none print:shadow-none print:p-0">
        <!-- Top Header: Platform Brand & Invoice Info -->
        <div class="flex flex-col sm:flex-row sm:items-start justify-between pb-8 border-b border-[#E9E7FF] print:border-slate-300 gap-6">
            <div>
                <a href="<?= base_url('index.php') ?>" class="inline-block">
                    <img src="<?= base_url('assets/img/ORMS Logo.png') ?>" alt="ORMS" class="h-10 sm:h-12 w-auto object-contain">
                </a>
                <p class="text-xs text-slate-500 print:text-slate-600 mt-3 max-w-xs">
                    Verified Digital Tax Invoice &bull; Trusted Rental Escrow Transaction
                </p>
            </div>

            <div class="text-left sm:text-right space-y-1">
                <span class="inline-block px-3 py-1 rounded-full text-xs font-semibold uppercase tracking-wider bg-emerald-50 text-emerald-700 border border-emerald-200">
                    Payment <?= htmlspecialchars($receipt['payment_status']) ?>
                </span>
                <div class="text-xs text-slate-500 print:text-slate-600 font-mono mt-2">
                    Invoice: <strong class="text-midnight print:text-black"><?= htmlspecialchars($receipt['receipt_number']) ?></strong>
                </div>
                <div class="text-xs text-slate-500 print:text-slate-600">
                    Date: <?= date('M d, Y h:i A', strtotime($receipt['payment_date'])) ?>
                </div>
                <div class="text-xs text-slate-500 print:text-slate-600">
                    Method: <strong class="text-midnight print:text-black"><?= htmlspecialchars(str_replace('_', ' ', $receipt['payment_mode'])) ?></strong>
                </div>
            </div>
        </div>

        <!-- Two-Party Information Columns -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6 text-xs">
            <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] print:bg-slate-50 print:border-slate-200 space-y-1.5">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Renter (Payer) Details</span>
                <div class="font-bold text-midnight print:text-black text-sm"><?= htmlspecialchars($receipt['payer_name']) ?></div>
                <div class="text-slate-600 print:text-slate-600">Email: <?= htmlspecialchars($receipt['payer_email']) ?></div>
                <div class="text-slate-600 print:text-slate-600">Phone: <?= htmlspecialchars($receipt['payer_phone'] ?: 'N/A') ?></div>
                <div class="text-slate-400 text-[11px] font-mono mt-1">User ID: #USR-<?= $receipt['payer_id'] ?></div>
            </div>

            <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] print:bg-slate-50 print:border-slate-200 space-y-1.5">
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Lender (Owner) Details</span>
                <div class="font-bold text-midnight print:text-black text-sm"><?= htmlspecialchars($receipt['owner_name']) ?></div>
                <div class="text-slate-600 print:text-slate-600">Email: <?= htmlspecialchars($receipt['owner_email']) ?></div>
                <div class="text-slate-600 print:text-slate-600">Phone: <?= htmlspecialchars($receipt['owner_phone'] ?: 'N/A') ?></div>
                <div class="text-slate-400 text-[11px] font-mono mt-1">Owner ID: #OWN-<?= $receipt['owner_id'] ?></div>
            </div>
        </div>

        <!-- Rental Booking Metadata Box -->
        <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] print:bg-slate-100 print:border-slate-300 space-y-3">
            <span class="text-[10px] font-bold text-coral uppercase tracking-wider block">
                Rented Product & Duration
            </span>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-display font-bold text-midnight print:text-black"><?= htmlspecialchars($receipt['product_title']) ?></h2>
                    <span class="text-xs text-slate-500 print:text-slate-600">Product Ref: #PRD-<?= $receipt['product_id'] ?> &bull; Booking Ref: #REQ-<?= $receipt['request_id'] ?></span>
                </div>
                <div class="text-left sm:text-right">
                    <span class="text-xs font-semibold text-slate-600 print:text-slate-700 block">
                        <?= date('M d, Y', strtotime($receipt['start_date'])) ?> &rarr; <?= date('M d, Y', strtotime($receipt['end_date'])) ?>
                    </span>
                    <span class="text-[11px] font-bold text-midnight print:text-blue-700"><?= $receipt['total_days'] ?> Total Rental Day(s)</span>
                </div>
            </div>
        </div>

        <!-- Itemized Financial Breakdown Table -->
        <div class="overflow-x-auto">
            <table class="w-full text-xs text-left">
                <thead>
                    <tr class="border-b border-[#E9E7FF] print:border-slate-300 text-slate-400 print:text-slate-600 font-semibold uppercase text-[10px] tracking-wider">
                        <th class="py-3 px-2">Charge Description</th>
                        <th class="py-3 px-2 text-center">Qty / Days</th>
                        <th class="py-3 px-2 text-right">Rate / Day</th>
                        <th class="py-3 px-2 text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#E9E7FF] print:divide-slate-200">
                    <tr>
                        <td class="py-4 px-2">
                            <div class="font-bold text-midnight print:text-black text-sm"><?= htmlspecialchars($receipt['product_title']) ?> — Equipment Rental</div>
                            <span class="text-[11px] text-slate-500 print:text-slate-500">Rental period: <?= $receipt['start_date'] ?> to <?= $receipt['end_date'] ?></span>
                        </td>
                        <td class="py-4 px-2 text-center text-slate-600 print:text-slate-700"><?= $receipt['total_days'] ?> day(s)</td>
                        <td class="py-4 px-2 text-right text-slate-600 print:text-slate-700 font-mono">₹<?= number_format($receipt['rent_per_day'], 2) ?></td>
                        <td class="py-4 px-2 text-right font-bold text-midnight print:text-black font-mono">₹<?= number_format($receipt['rental_amount'], 2) ?></td>
                    </tr>
                    <tr>
                        <td class="py-4 px-2">
                            <div class="font-bold text-midnight print:text-black text-sm">Refundable Security Deposit</div>
                            <span class="text-[11px] text-slate-500 print:text-slate-500">Held in escrow; 100% refundable upon safe equipment return</span>
                        </td>
                        <td class="py-4 px-2 text-center text-slate-600 print:text-slate-700">1 Item</td>
                        <td class="py-4 px-2 text-right text-slate-600 print:text-slate-700 font-mono">₹<?= number_format($receipt['deposit_amount'], 2) ?></td>
                        <td class="py-4 px-2 text-right font-bold text-emerald-600 print:text-emerald-700 font-mono">₹<?= number_format($receipt['deposit_amount'], 2) ?></td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="border-t-2 border-[#E9E7FF] print:border-slate-400 text-sm">
                        <td colspan="3" class="py-4 px-2 font-bold text-midnight print:text-black text-right uppercase tracking-wider">
                            Grand Total Paid:
                        </td>
                        <td class="py-4 px-2 font-display font-extrabold text-lg text-coral print:text-emerald-700 text-right font-mono">
                            ₹<?= number_format($receipt['total_paid'], 2) ?>
                        </td>
                    </tr>
                </tfoot>
            </table>
        </div>

        <!-- Deposit Escrow Status & Terms Box -->
        <div class="p-4 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] print:bg-slate-50 print:border-slate-200 text-xs text-slate-600 print:text-slate-600 space-y-1.5">
            <div class="flex items-center justify-between">
                <span class="font-semibold text-midnight print:text-slate-700">Deposit Escrow Status:</span>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase bg-blue-50 text-blue-700 border border-blue-200 print:border-blue-600">
                    <?= htmlspecialchars($receipt['deposit_status']) ?>
                </span>
            </div>
            <p class="text-[11px] leading-relaxed">
                As per ORMS terms, security deposit remains in 'Held' status until the lender confirms return inspection. Clean returns receive a 100% refund.
            </p>
        </div>

        <!-- Footer Sign-off -->
        <div class="pt-6 border-t border-[#E9E7FF] print:border-slate-300 text-center text-xs text-slate-500 print:text-slate-600">
            <p>Thank you for choosing the Online Rental Management System (ORMS)!</p>
            <p class="text-[11px] mt-1">This is a system-generated electronic receipt and does not require a physical signature.</p>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
