<?php
/**
 * Online Rental Management System (ORMS)
 * Owner Financial Ledger & Earnings Workspace
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Owner.php';
require_once __DIR__ . '/../classes/Transaction.php';

require_role('Owner');

$ownerId = (int) current_user_id();
$owner = new Owner($ownerId);

// Fetch all transactions for this owner's products
$transactions = Transaction::findByOwner($ownerId);

$totalEarnings = 0.00;
$heldDeposits = 0.00;
$activeCount = 0;

foreach ($transactions as $t) {
    if ($t->getPaymentStatus() === 'Completed') {
        $totalEarnings += $t->getRentalAmount();
        if ($t->getDepositStatus() === 'Held') {
            $heldDeposits += $t->getDepositAmount();
            $activeCount++;
        }
    }
}

$pageTitle = 'Earnings & Financial Ledger — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-coral transition">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('owner/dashboard.php') ?>" class="hover:text-coral transition">Owner Workspace</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Earnings & Ledger</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-coral text-xs font-semibold uppercase tracking-wider mb-1">
                <i class="ri-wallet-3-line text-sm"></i>
                <span>Financial Ledger</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">Owner Earnings</h1>
            <p class="text-sm text-slate-500 mt-1">Real-time breakdown of your equipment rental revenue, completed payouts, and deposits held in escrow.</p>
        </div>

        <a href="<?= base_url('owner/dashboard.php') ?>" 
           class="inline-flex items-center space-x-1.5 px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-600 hover:text-midnight text-xs font-semibold rounded-full border border-[#E9E7FF] transition self-start sm:self-auto shadow-sm">
            <i class="ri-arrow-left-line"></i>
            <span>Owner Dashboard</span>
        </a>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
        <!-- Lifetime Revenue -->
        <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-emerald-600 uppercase tracking-wider">Lifetime Revenue</span>
                <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                    <i class="ri-money-dollar-circle-line"></i>
                </div>
            </div>
            <div class="text-3xl font-display font-bold text-midnight mt-1">₹<?= number_format($totalEarnings, 2) ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Net rental fees received directly</span>
        </div>

        <!-- Escrow Deposits -->
        <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-blue-600 uppercase tracking-wider">Escrow Deposits</span>
                <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                    <i class="ri-shield-check-line"></i>
                </div>
            </div>
            <div class="text-3xl font-display font-bold text-blue-600 mt-1">₹<?= number_format($heldDeposits, 2) ?></div>
            <span class="text-xs text-slate-400 mt-1 block">Secured until return inspection</span>
        </div>

        <!-- Total Bookings -->
        <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-3">
                <span class="text-xs font-semibold text-purple-600 uppercase tracking-wider">Total Transactions</span>
                <div class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                    <i class="ri-exchange-dollar-line"></i>
                </div>
            </div>
            <div class="text-3xl font-display font-bold text-purple-600 mt-1"><?= count($transactions) ?></div>
            <span class="text-xs text-slate-400 mt-1 block"><?= $activeCount ?> active ongoing booking(s)</span>
        </div>
    </div>

    <!-- Transactions Ledger Table -->
    <div class="bg-white border border-[#E9E7FF] rounded-3xl shadow-sm overflow-hidden">
        <div class="p-6 sm:p-7 border-b border-[#E9E7FF] flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold font-display text-midnight tracking-tight">Payment Transactions History</h2>
                <p class="text-xs text-slate-500 mt-0.5">Verified digital records of all customer payments for your inventory.</p>
            </div>
            <span class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                <i class="ri-verified-badge-line"></i>
                <span>Protected Escrow Ledger</span>
            </span>
        </div>

        <?php if (empty($transactions)): ?>
            <div class="p-12 text-center text-slate-400">
                <div class="w-16 h-16 mx-auto rounded-full bg-[#FAF8F5] flex items-center justify-center text-2xl text-slate-400 mb-4">
                    <i class="ri-bank-card-line"></i>
                </div>
                <div class="text-sm font-semibold text-midnight">No payment transactions recorded yet</div>
                <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                    Once a renter pays for an approved booking, their transaction details, rental revenue, and deposit records will appear here.
                </p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-[#FAF8F5] border-b border-[#E9E7FF] text-slate-500 uppercase text-[10px] tracking-wider font-semibold">
                            <th class="py-4 px-6">Invoice #</th>
                            <th class="py-4 px-6">Rented Item</th>
                            <th class="py-4 px-6">Renter</th>
                            <th class="py-4 px-6">Date & Mode</th>
                            <th class="py-4 px-6 text-right">Rental Fee</th>
                            <th class="py-4 px-6 text-right">Deposit Status</th>
                            <th class="py-4 px-6 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E9E7FF]">
                        <?php foreach ($transactions as $t): 
                            $depStatus = $t->getDepositStatus();
                            $depBadge = ($depStatus === 'Held') ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200';
                        ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-4 px-6 font-mono font-semibold text-midnight">
                                    #REC-<?= str_pad((string)$t->getTransactionID(), 6, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="font-bold text-midnight truncate max-w-xs"><?= htmlspecialchars($t->getProductTitle()) ?></div>
                                    <span class="text-[11px] text-slate-400 font-mono">Booking #REQ-<?= $t->getRequestID() ?></span>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="font-semibold text-midnight"><?= htmlspecialchars($t->getPayerName()) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($t->getPayerPhone() ?: $t->getPayerEmail()) ?></div>
                                </td>
                                <td class="py-4 px-6">
                                    <div class="text-midnight font-medium"><?= date('M d, Y', strtotime($t->getPaymentDate() ?? 'now')) ?></div>
                                    <span class="text-[10px] text-slate-400 uppercase font-semibold"><?= htmlspecialchars(str_replace('_', ' ', $t->getPaymentMode())) ?></span>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <div class="font-bold text-emerald-600 font-mono text-sm">₹<?= number_format($t->getRentalAmount(), 2) ?></div>
                                    <span class="text-[10px] text-slate-400"><?= $t->getTotalDays() ?> days</span>
                                </td>
                                <td class="py-4 px-6 text-right">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold border <?= $depBadge ?>">
                                        ₹<?= number_format($t->getDepositAmount(), 2) ?> (<?= htmlspecialchars($depStatus) ?>)
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center">
                                    <a href="<?= base_url('renter/receipt.php?id=' . $t->getTransactionID()) ?>" 
                                       class="inline-flex items-center space-x-1 px-3.5 py-1.5 bg-white hover:bg-slate-50 text-coral border border-[#E9E7FF] text-xs font-semibold rounded-full transition shadow-sm">
                                        <i class="ri-file-text-line"></i>
                                        <span>Invoice</span>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

