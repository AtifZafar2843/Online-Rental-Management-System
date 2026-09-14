<?php
/**
 * Online Rental Management System (ORMS)
 * Owner Financial Ledger & Earnings Workspace
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 (Owner::totalEarnings) & Section 6 (Step 7)
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-white">Home</a></li>
            <li><span>/</span></li>
            <li><a href="<?= base_url('owner/dashboard.php') ?>" class="hover:text-white">Owner Workspace</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">Earnings</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center space-x-3">
                <span>💰</span>
                <span>Owner Financial Ledger</span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">Real-time breakdown of your equipment rental revenues, completed payments, and deposits held in escrow.</p>
        </div>

        <a href="<?= base_url('owner/dashboard.php') ?>" 
           class="inline-flex items-center space-x-2 px-4 py-2.5 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold rounded-xl border border-slate-700 transition self-start sm:self-auto">
            <span>&larr;</span>
            <span>Owner Dashboard</span>
        </a>
    </div>

    <!-- Metrics Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-10">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <span class="text-xs font-semibold text-emerald-400 uppercase tracking-wider">Lifetime Rental Revenue</span>
            <div class="text-3xl font-black text-white mt-2">₹<?= number_format($totalEarnings, 2) ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Net rental fees received</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <span class="text-xs font-semibold text-blue-400 uppercase tracking-wider">Deposits Held in Escrow</span>
            <div class="text-3xl font-black text-blue-400 mt-2">₹<?= number_format($heldDeposits, 2) ?></div>
            <span class="text-xs text-slate-500 mt-1 block">Held until item return inspection</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-xl">
            <span class="text-xs font-semibold text-purple-400 uppercase tracking-wider">Total Transactions</span>
            <div class="text-3xl font-black text-purple-400 mt-2"><?= count($transactions) ?></div>
            <span class="text-xs text-slate-500 mt-1 block"><?= $activeCount ?> actively ongoing rentals</span>
        </div>
    </div>

    <!-- Transactions Ledger Table -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
        <div class="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-lg font-bold text-white tracking-tight">Payment Transactions History</h2>
                <p class="text-xs text-slate-400 mt-0.5">All customer payments processed for your equipment.</p>
            </div>
            <span class="text-xs text-slate-500 font-medium">3NF Snapshot Verified</span>
        </div>

        <?php if (empty($transactions)): ?>
            <div class="p-12 text-center text-slate-400">
                <div class="text-3xl mb-2">💳</div>
                <div class="text-sm font-semibold text-slate-300">No payment transactions recorded yet</div>
                <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                    Once a renter pays for an approved booking, their transaction details, rental revenue, and deposit records will appear here.
                </p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="bg-slate-950/70 border-b border-slate-800 text-slate-400 uppercase text-[10px] tracking-wider font-semibold">
                            <th class="py-3.5 px-4">Invoice #</th>
                            <th class="py-3.5 px-4">Rented Product</th>
                            <th class="py-3.5 px-4">Renter</th>
                            <th class="py-3.5 px-4">Date & Mode</th>
                            <th class="py-3.5 px-4 text-right">Rental Revenue</th>
                            <th class="py-3.5 px-4 text-right">Deposit Status</th>
                            <th class="py-3.5 px-4 text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/60">
                        <?php foreach ($transactions as $t): 
                            $depStatus = $t->getDepositStatus();
                            $depBadge = ($depStatus === 'Held') ? 'bg-blue-950 text-blue-300 border-blue-800' : 'bg-emerald-950 text-emerald-300 border-emerald-800';
                        ?>
                            <tr class="hover:bg-slate-850/40 transition">
                                <td class="py-4 px-4 font-mono font-semibold text-slate-300">
                                    #REC-<?= str_pad((string)$t->getTransactionID(), 6, '0', STR_PAD_LEFT) ?>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-bold text-white truncate max-w-xs"><?= htmlspecialchars($t->getProductTitle()) ?></div>
                                    <span class="text-[11px] text-slate-500">Booking #REQ-<?= $t->getRequestID() ?></span>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="font-semibold text-slate-200"><?= htmlspecialchars($t->getPayerName()) ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono"><?= htmlspecialchars($t->getPayerPhone() ?: $t->getPayerEmail()) ?></div>
                                </td>
                                <td class="py-4 px-4">
                                    <div class="text-slate-300"><?= date('M d, Y', strtotime($t->getPaymentDate() ?? 'now')) ?></div>
                                    <span class="text-[10px] text-slate-500 uppercase font-semibold"><?= htmlspecialchars(str_replace('_', ' ', $t->getPaymentMode())) ?></span>
                                </td>
                                <td class="py-4 px-4 text-right">
                                    <div class="font-extrabold text-emerald-400 font-mono text-sm">₹<?= number_format($t->getRentalAmount(), 2) ?></div>
                                    <span class="text-[10px] text-slate-500"><?= $t->getTotalDays() ?> days</span>
                                </td>
                                <td class="py-4 px-4 text-right">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold border <?= $depBadge ?>">
                                        ₹<?= number_format($t->getDepositAmount(), 2) ?> (<?= htmlspecialchars($depStatus) ?>)
                                    </span>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <a href="<?= base_url('renter/receipt.php?id=' . $t->getTransactionID()) ?>" 
                                       class="px-3 py-1.5 bg-slate-800 hover:bg-slate-700 text-blue-400 hover:text-blue-300 text-xs font-semibold rounded-lg border border-slate-700 transition">
                                        View Receipt
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
