<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Reports & Audit Console
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1, 13.I (Page 29)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Admin.php';

require_admin();

$pdo = Database::getInstance()->getConnection();
$admin = new Admin(
    (int) $_SESSION['admin_id'],
    $_SESSION['username'] ?? 'admin',
    $_SESSION['email'] ?? '',
    $_SESSION['name'] ?? 'Administrator'
);

$validTabs = ['overview', 'rentals', 'revenue', 'fines'];
$activeTab = strtolower(trim($_GET['type'] ?? 'overview'));
if (!in_array($activeTab, $validTabs, true)) {
    $activeTab = 'overview';
}

$reportData = $admin->generateReport($activeTab);

$pageTitle = 'Operational & Financial Reports — ORMS Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Header banner -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 pb-6 border-b border-slate-800 gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <span class="px-3 py-1 bg-red-500/10 text-red-400 border border-red-500/20 rounded-full text-xs font-semibold tracking-wide uppercase">
                    System Audit &amp; Analytics
                </span>
                <span class="text-xs text-slate-500 font-mono">Generated: <?= date('d M Y, H:i:s') ?></span>
            </div>
            <h1 class="text-3xl font-extrabold text-white tracking-tight mt-2">Platform Reports &amp; Financial Audit</h1>
            <p class="text-slate-400 text-sm mt-1">Real-time operational metrics, rental throughput, revenue tracking, and penalty audits.</p>
        </div>

        <div class="flex items-center space-x-3">
            <button onclick="window.print()" class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold rounded-xl border border-slate-700 flex items-center space-x-2 transition shadow-sm">
                <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path>
                </svg>
                <span>Print / Save PDF</span>
            </button>
            <a href="<?= base_url('admin/dashboard.php') ?>" class="px-4 py-2 bg-slate-800/80 hover:bg-slate-800 text-slate-300 text-xs font-semibold rounded-xl border border-slate-700 transition">
                &larr; Admin Dashboard
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-8 bg-slate-900/80 p-2 rounded-2xl border border-slate-800">
        <a href="<?= base_url('admin/reports.php?type=overview') ?>" 
           class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $activeTab === 'overview' ? 'bg-red-600 text-white shadow-lg shadow-red-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <span>📊</span>
            <span>Executive Overview</span>
        </a>
        <a href="<?= base_url('admin/reports.php?type=rentals') ?>" 
           class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $activeTab === 'rentals' ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <span>📦</span>
            <span>Rental Activity Log</span>
        </a>
        <a href="<?= base_url('admin/reports.php?type=revenue') ?>" 
           class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $activeTab === 'revenue' ? 'bg-emerald-600 text-white shadow-lg shadow-emerald-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <span>💳</span>
            <span>Financial Transactions</span>
        </a>
        <a href="<?= base_url('admin/reports.php?type=fines') ?>" 
           class="px-4 py-2.5 rounded-xl text-xs font-bold transition flex items-center space-x-2 <?= $activeTab === 'fines' ? 'bg-amber-600 text-white shadow-lg shadow-amber-600/30' : 'text-slate-400 hover:text-white hover:bg-slate-800' ?>">
            <span>⚡</span>
            <span>Fine &amp; Penalty Collections</span>
        </a>
    </div>

    <?php if ($activeTab === 'overview'): ?>
        <!-- Executive Overview Dashboard -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Gross Rental Revenue</span>
                        <div class="text-3xl font-extrabold text-emerald-400 mt-2">₹<?= number_format($reportData['gross_revenue'] ?? 0, 2) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 text-lg">
                        💰
                    </div>
                </div>
                <span class="text-[11px] text-slate-500 mt-2 block">Completed rental transactions</span>
            </div>

            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Security Deposits Held</span>
                        <div class="text-3xl font-extrabold text-blue-400 mt-2">₹<?= number_format($reportData['deposits_held'] ?? 0, 2) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 text-lg">
                        🔒
                    </div>
                </div>
                <span class="text-[11px] text-slate-500 mt-2 block">Currently locked in escrow</span>
            </div>

            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Fines Collected</span>
                        <div class="text-3xl font-extrabold text-amber-400 mt-2">₹<?= number_format($reportData['fines_collected'] ?? 0, 2) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-amber-400 text-lg">
                        ⚡
                    </div>
                </div>
                <span class="text-[11px] text-slate-500 mt-2 block">Late returns &amp; damage penalties</span>
            </div>

            <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg relative overflow-hidden">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Rentals</span>
                        <div class="text-3xl font-extrabold text-indigo-400 mt-2"><?= (int)($reportData['active_rentals'] ?? 0) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-xl bg-indigo-500/10 border border-indigo-500/20 flex items-center justify-center text-indigo-400 text-lg">
                        📦
                    </div>
                </div>
                <span class="text-[11px] text-slate-500 mt-2 block">Out of <?= (int)($reportData['total_rentals'] ?? 0) ?> total bookings</span>
            </div>
        </div>

        <!-- Operational Secondary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-slate-900/60 border border-slate-800 p-6 rounded-2xl flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold">User Community</span>
                    <div class="text-2xl font-bold text-white mt-1"><?= (int)($reportData['total_users'] ?? 0) ?> Registered</div>
                    <span class="text-[11px] text-slate-500">Renters and Product Owners</span>
                </div>
                <a href="<?= base_url('admin/manage_users.php') ?>" class="text-xs text-blue-400 hover:text-blue-300 font-semibold flex items-center space-x-1">
                    <span>Manage</span> &rarr;
                </a>
            </div>

            <div class="bg-slate-900/60 border border-slate-800 p-6 rounded-2xl flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Live Inventory</span>
                    <div class="text-2xl font-bold text-white mt-1"><?= (int)($reportData['total_products'] ?? 0) ?> Products</div>
                    <span class="text-[11px] text-slate-500">Across verified categories</span>
                </div>
                <a href="<?= base_url('admin/manage_categories.php') ?>" class="text-xs text-blue-400 hover:text-blue-300 font-semibold flex items-center space-x-1">
                    <span>Taxonomy</span> &rarr;
                </a>
            </div>

            <div class="bg-slate-900/60 border border-slate-800 p-6 rounded-2xl flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Active Disputes</span>
                    <div class="text-2xl font-bold text-rose-400 mt-1"><?= (int)($reportData['open_disputes'] ?? 0) ?> Cases</div>
                    <span class="text-[11px] text-slate-500">Require adjudication</span>
                </div>
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="text-xs text-rose-400 hover:text-rose-300 font-semibold flex items-center space-x-1">
                    <span>Review</span> &rarr;
                </a>
            </div>
        </div>

        <!-- System Governance Summary Banner -->
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <h2 class="text-base font-bold text-white mb-2">Compliance &amp; System Integrity Note</h2>
            <p class="text-slate-400 text-xs leading-relaxed mb-4">
                This audit report is generated dynamically from immutable transaction ledgers and relational rental states. 
                All financial summaries correspond to verified payments under Section 13.G and 13.H of the project specification. 
                Platform administrators can trigger detailed table audits using the tabs above.
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="<?= base_url('admin/reports.php?type=rentals') ?>" class="px-3 py-1.5 bg-blue-600/20 text-blue-300 border border-blue-500/30 rounded-lg text-xs font-semibold hover:bg-blue-600/30 transition">
                    View Rentals Log &rarr;
                </a>
                <a href="<?= base_url('admin/reports.php?type=revenue') ?>" class="px-3 py-1.5 bg-emerald-600/20 text-emerald-300 border border-emerald-500/30 rounded-lg text-xs font-semibold hover:bg-emerald-600/30 transition">
                    Audit Financial Transactions &rarr;
                </a>
                <a href="<?= base_url('admin/reports.php?type=fines') ?>" class="px-3 py-1.5 bg-amber-600/20 text-amber-300 border border-amber-500/30 rounded-lg text-xs font-semibold hover:bg-amber-600/30 transition">
                    Audit Fines &amp; Deductions &rarr;
                </a>
            </div>
        </div>

    <?php elseif ($activeTab === 'rentals'): ?>
        <!-- Rentals Log Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-slate-950/40">
                <div>
                    <h2 class="text-lg font-bold text-white">Rental Activity Log</h2>
                    <p class="text-xs text-slate-400">Complete historical records of rental bookings and their lifecycle states.</p>
                </div>
                <span class="text-xs bg-blue-500/20 text-blue-300 border border-blue-500/30 px-3 py-1 rounded-full font-semibold">
                    <?= count($reportData) ?> records shown
                </span>
            </div>

            <?php if (empty($reportData)): ?>
                <div class="p-12 text-center text-slate-400">
                    <span class="text-4xl block mb-2">📦</span>
                    <p class="font-semibold text-sm">No rental requests recorded in system yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                            <tr>
                                <th class="py-3 px-4">Req #</th>
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4">Renter</th>
                                <th class="py-3 px-4">Owner</th>
                                <th class="py-3 px-4">Rental Window</th>
                                <th class="py-3 px-4 text-center">Duration</th>
                                <th class="py-3 px-4 text-right">Rent / Day</th>
                                <th class="py-3 px-4 text-right">Total Fee</th>
                                <th class="py-3 px-4 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 font-mono">
                            <?php foreach ($reportData as $row): 
                                $statusBadge = 'bg-slate-800 text-slate-300';
                                if ($row['status'] === 'Active') $statusBadge = 'bg-indigo-500/20 text-indigo-300 border border-indigo-500/30';
                                elseif ($row['status'] === 'Completed') $statusBadge = 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                                elseif ($row['status'] === 'Pending') $statusBadge = 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
                                elseif ($row['status'] === 'Approved') $statusBadge = 'bg-blue-500/20 text-blue-300 border border-blue-500/30';
                                elseif ($row['status'] === 'Rejected' || $row['status'] === 'Cancelled') $statusBadge = 'bg-rose-500/20 text-rose-300 border border-rose-500/30';
                            ?>
                            <tr class="hover:bg-slate-800/40 transition font-sans">
                                <td class="py-3.5 px-4 font-mono font-bold text-white">#<?= $row['request_id'] ?></td>
                                <td class="py-3.5 px-4 font-semibold text-white"><?= htmlspecialchars($row['product_title']) ?></td>
                                <td class="py-3.5 px-4 text-slate-300"><?= htmlspecialchars($row['renter_name']) ?></td>
                                <td class="py-3.5 px-4 text-slate-300"><?= htmlspecialchars($row['owner_name']) ?></td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                                    <?= date('d M Y', strtotime($row['start_date'])) ?> &rarr; <?= date('d M Y', strtotime($row['end_date'])) ?>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono"><?= (int)$row['total_days'] ?> days</td>
                                <td class="py-3.5 px-4 text-right font-mono">₹<?= number_format((float)$row['rent_per_day'], 2) ?></td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-400">₹<?= number_format((float)$row['total_amount'], 2) ?></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $statusBadge ?>">
                                        <?= htmlspecialchars($row['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($activeTab === 'revenue'): ?>
        <!-- Financial Transactions Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-slate-950/40">
                <div>
                    <h2 class="text-lg font-bold text-white">Financial Transaction Ledger</h2>
                    <p class="text-xs text-slate-400">All gateway payments, escrow deposits, and settlement records.</p>
                </div>
                <span class="text-xs bg-emerald-500/20 text-emerald-300 border border-emerald-500/30 px-3 py-1 rounded-full font-semibold">
                    <?= count($reportData) ?> transactions recorded
                </span>
            </div>

            <?php if (empty($reportData)): ?>
                <div class="p-12 text-center text-slate-400">
                    <span class="text-4xl block mb-2">💳</span>
                    <p class="font-semibold text-sm">No financial transactions recorded in system yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                            <tr>
                                <th class="py-3 px-4">Txn #</th>
                                <th class="py-3 px-4">Req #</th>
                                <th class="py-3 px-4">Payer</th>
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4 text-right">Rental Fee</th>
                                <th class="py-3 px-4 text-right">Security Deposit</th>
                                <th class="py-3 px-4 text-center">Deposit Status</th>
                                <th class="py-3 px-4 text-center">Payment Mode</th>
                                <th class="py-3 px-4 text-center">Payment Status</th>
                                <th class="py-3 px-4">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($reportData as $row): 
                                $payStatusBadge = ($row['payment_status'] === 'Completed') 
                                    ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' 
                                    : 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
                                
                                $depBadge = 'bg-slate-800 text-slate-300';
                                if ($row['deposit_status'] === 'Held') $depBadge = 'bg-blue-500/20 text-blue-300 border border-blue-500/30';
                                elseif ($row['deposit_status'] === 'Refunded') $depBadge = 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                                elseif ($row['deposit_status'] === 'Deducted') $depBadge = 'bg-rose-500/20 text-rose-300 border border-rose-500/30';
                            ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-white">#<?= $row['transaction_id'] ?></td>
                                <td class="py-3.5 px-4 font-mono text-slate-400">#<?= $row['request_id'] ?></td>
                                <td class="py-3.5 px-4 font-semibold text-white"><?= htmlspecialchars($row['payer_name']) ?></td>
                                <td class="py-3.5 px-4 text-slate-300"><?= htmlspecialchars($row['product_title']) ?></td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-emerald-400">₹<?= number_format((float)$row['rental_amount'], 2) ?></td>
                                <td class="py-3.5 px-4 text-right font-mono text-blue-300">₹<?= number_format((float)$row['deposit_amount'], 2) ?></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold <?= $depBadge ?>">
                                        <?= htmlspecialchars($row['deposit_status']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono text-[11px] text-slate-300"><?= htmlspecialchars($row['payment_mode']) ?></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $payStatusBadge ?>">
                                        <?= htmlspecialchars($row['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                                    <?= date('d M Y, H:i', strtotime($row['payment_date'])) ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($activeTab === 'fines'): ?>
        <!-- Fines & Penalties Audit Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
            <div class="p-6 border-b border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-slate-950/40">
                <div>
                    <h2 class="text-lg font-bold text-white">Fine &amp; Penalty Collections Audit</h2>
                    <p class="text-xs text-slate-400">Records of late-return penalties, damages assessments, and deposit deductions.</p>
                </div>
                <span class="text-xs bg-amber-500/20 text-amber-300 border border-amber-500/30 px-3 py-1 rounded-full font-semibold">
                    <?= count($reportData) ?> fines recorded
                </span>
            </div>

            <?php if (empty($reportData)): ?>
                <div class="p-12 text-center text-slate-400">
                    <span class="text-4xl block mb-2">⚡</span>
                    <p class="font-semibold text-sm">No fines or penalty charges issued in the system yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-300">
                        <thead class="bg-slate-950/80 text-slate-400 uppercase tracking-wider font-semibold border-b border-slate-800">
                            <tr>
                                <th class="py-3 px-4">Fine #</th>
                                <th class="py-3 px-4">Req #</th>
                                <th class="py-3 px-4">Renter</th>
                                <th class="py-3 px-4">Product</th>
                                <th class="py-3 px-4">Fine Reason</th>
                                <th class="py-3 px-4 text-center">Late Days</th>
                                <th class="py-3 px-4 text-right">Amount</th>
                                <th class="py-3 px-4 text-center">Status</th>
                                <th class="py-3 px-4">Issued Date</th>
                                <th class="py-3 px-4">Paid Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                            <?php foreach ($reportData as $row): 
                                $statusBadge = 'bg-slate-800 text-slate-300';
                                if ($row['status'] === 'Paid') $statusBadge = 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                                elseif ($row['status'] === 'Deducted_From_Deposit') $statusBadge = 'bg-blue-500/20 text-blue-300 border border-blue-500/30';
                                elseif ($row['status'] === 'Pending') $statusBadge = 'bg-amber-500/20 text-amber-300 border border-amber-500/30';
                                elseif ($row['status'] === 'Waived') $statusBadge = 'bg-purple-500/20 text-purple-300 border border-purple-500/30';
                            ?>
                            <tr class="hover:bg-slate-800/40 transition">
                                <td class="py-3.5 px-4 font-mono font-bold text-white">#<?= $row['fine_id'] ?></td>
                                <td class="py-3.5 px-4 font-mono text-slate-400">#<?= $row['request_id'] ?></td>
                                <td class="py-3.5 px-4 font-semibold text-white"><?= htmlspecialchars($row['renter_name']) ?></td>
                                <td class="py-3.5 px-4 text-slate-300"><?= htmlspecialchars($row['product_title']) ?></td>
                                <td class="py-3.5 px-4">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-800 text-slate-300 border border-slate-700">
                                        <?= htmlspecialchars($row['fine_type']) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 text-center font-mono"><?= (int)$row['late_days'] ?></td>
                                <td class="py-3.5 px-4 text-right font-mono font-bold text-rose-400">₹<?= number_format((float)$row['amount'], 2) ?></td>
                                <td class="py-3.5 px-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $statusBadge ?>">
                                        <?= str_replace('_', ' ', htmlspecialchars($row['status'])) ?>
                                    </span>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                                    <?= date('d M Y', strtotime($row['issue_date'])) ?>
                                </td>
                                <td class="py-3.5 px-4 font-mono text-[11px] text-slate-400">
                                    <?= !empty($row['paid_date']) ? date('d M Y', strtotime($row['paid_date'])) : '&mdash;' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
