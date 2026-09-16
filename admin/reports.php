<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Reports & Audit Console
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Header banner -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between mb-8 pb-6 border-b border-[#E9E7FF] gap-4">
        <div>
            <div class="flex items-center space-x-3">
                <span class="inline-flex items-center space-x-1 px-3 py-1 bg-coral/10 text-coral border border-coral/20 rounded-full text-xs font-semibold tracking-wide uppercase">
                    <i class="ri-bar-chart-2-line text-sm"></i>
                    <span>Business Intelligence &amp; Audit</span>
                </span>
                <span class="text-xs text-slate-400 font-mono">Generated: <?= date('d M Y, H:i:s') ?></span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight mt-2">Platform Reports &amp; Financial Ledger</h1>
            <p class="text-slate-500 text-xs sm:text-sm mt-1">Real-time operational metrics, rental volume, revenue tracking, and escrow audits.</p>
        </div>

        <div class="flex items-center space-x-3">
            <button onclick="window.print()" class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-600 hover:text-midnight text-xs font-semibold rounded-full border border-[#E9E7FF] flex items-center space-x-1.5 transition shadow-sm">
                <i class="ri-printer-line text-slate-400"></i>
                <span>Print / PDF</span>
            </button>
            <a href="<?= base_url('admin/dashboard.php') ?>" class="px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-600 hover:text-midnight text-xs font-semibold rounded-full border border-[#E9E7FF] transition flex items-center space-x-1.5 shadow-sm">
                <i class="ri-arrow-left-line"></i>
                <span>Dashboard</span>
            </a>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex flex-wrap items-center gap-2 mb-8 bg-white p-2 rounded-full border border-[#E9E7FF] shadow-sm">
        <a href="<?= base_url('admin/reports.php?type=overview') ?>" 
           class="px-4 py-2 rounded-full text-xs font-semibold transition flex items-center space-x-2 <?= $activeTab === 'overview' ? 'bg-coral text-white shadow-glow-coral' : 'text-slate-600 hover:text-midnight hover:bg-slate-50' ?>">
            <i class="ri-dashboard-line"></i>
            <span>Executive Overview</span>
        </a>
        <a href="<?= base_url('admin/reports.php?type=rentals') ?>" 
           class="px-4 py-2 rounded-full text-xs font-semibold transition flex items-center space-x-2 <?= $activeTab === 'rentals' ? 'bg-coral text-white shadow-glow-coral' : 'text-slate-600 hover:text-midnight hover:bg-slate-50' ?>">
            <i class="ri-inbox-line"></i>
            <span>Rental Activity Log</span>
        </a>
        <a href="<?= base_url('admin/reports.php?type=revenue') ?>" 
           class="px-4 py-2 rounded-full text-xs font-semibold transition flex items-center space-x-2 <?= $activeTab === 'revenue' ? 'bg-coral text-white shadow-glow-coral' : 'text-slate-600 hover:text-midnight hover:bg-slate-50' ?>">
            <i class="ri-wallet-3-line"></i>
            <span>Financial Transactions</span>
        </a>
        <a href="<?= base_url('admin/reports.php?type=fines') ?>" 
           class="px-4 py-2 rounded-full text-xs font-semibold transition flex items-center space-x-2 <?= $activeTab === 'fines' ? 'bg-coral text-white shadow-glow-coral' : 'text-slate-600 hover:text-midnight hover:bg-slate-50' ?>">
            <i class="ri-scales-3-line"></i>
            <span>Penalties &amp; Deductions</span>
        </a>
    </div>

    <?php if ($activeTab === 'overview'): ?>
        <!-- Executive Overview Dashboard -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Gross Rental Revenue</span>
                        <div class="text-3xl font-display font-bold text-emerald-600 mt-2">₹<?= number_format($reportData['gross_revenue'] ?? 0, 2) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="ri-money-dollar-circle-line"></i>
                    </div>
                </div>
                <span class="text-[11px] text-slate-400 mt-2 block">Completed rental bookings</span>
            </div>

            <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Deposits in Escrow</span>
                        <div class="text-3xl font-display font-bold text-blue-600 mt-2">₹<?= number_format($reportData['deposits_held'] ?? 0, 2) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                        <i class="ri-shield-check-line"></i>
                    </div>
                </div>
                <span class="text-[11px] text-slate-400 mt-2 block">Currently locked in trust</span>
            </div>

            <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Penalties Collected</span>
                        <div class="text-3xl font-display font-bold text-amber-600 mt-2">₹<?= number_format($reportData['fines_collected'] ?? 0, 2) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                        <i class="ri-alarm-warning-line"></i>
                    </div>
                </div>
                <span class="text-[11px] text-slate-400 mt-2 block">Late returns &amp; damage fees</span>
            </div>

            <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
                <div class="flex justify-between items-start">
                    <div>
                        <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Rentals</span>
                        <div class="text-3xl font-display font-bold text-purple-600 mt-2"><?= (int)($reportData['active_rentals'] ?? 0) ?></div>
                    </div>
                    <div class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                        <i class="ri-box-3-line"></i>
                    </div>
                </div>
                <span class="text-[11px] text-slate-400 mt-2 block">Out of <?= (int)($reportData['total_rentals'] ?? 0) ?> total bookings</span>
            </div>
        </div>

        <!-- Operational Secondary Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
            <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold">User Roster</span>
                    <div class="text-2xl font-display font-bold text-midnight mt-1"><?= (int)($reportData['total_users'] ?? 0) ?> Registered</div>
                    <span class="text-[11px] text-slate-400">Renters and Equipment Owners</span>
                </div>
                <a href="<?= base_url('admin/manage_users.php') ?>" class="text-xs text-coral hover:underline font-semibold flex items-center space-x-1">
                    <span>Manage</span> &rarr;
                </a>
            </div>

            <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Live Inventory</span>
                    <div class="text-2xl font-display font-bold text-midnight mt-1"><?= (int)($reportData['total_products'] ?? 0) ?> Products</div>
                    <span class="text-[11px] text-slate-400">Across verified departments</span>
                </div>
                <a href="<?= base_url('admin/manage_categories.php') ?>" class="text-xs text-coral hover:underline font-semibold flex items-center space-x-1">
                    <span>Taxonomy</span> &rarr;
                </a>
            </div>

            <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 uppercase tracking-wider font-semibold">Active Disputes</span>
                    <div class="text-2xl font-display font-bold text-rose-600 mt-1"><?= (int)($reportData['open_disputes'] ?? 0) ?> Cases</div>
                    <span class="text-[11px] text-slate-400">Pending adjudication</span>
                </div>
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="text-xs text-rose-600 hover:underline font-semibold flex items-center space-x-1">
                    <span>Review</span> &rarr;
                </a>
            </div>
        </div>

        <!-- System Governance Summary Banner -->
        <div class="bg-[#FAF8F5] border border-[#E9E7FF] rounded-3xl p-6 sm:p-8 shadow-sm">
            <h2 class="text-base font-bold font-display text-midnight mb-2">Platform Audit &amp; System Integrity</h2>
            <p class="text-slate-600 text-xs leading-relaxed mb-4">
                This audit report is compiled directly from immutable transaction logs, verified digital invoices, and relational state snapshots. 
                All financial calculations conform to escrow protection policies and statutory liability caps.
            </p>
            <div class="flex flex-wrap gap-3">
                <a href="<?= base_url('admin/reports.php?type=rentals') ?>" class="px-4 py-2 bg-white text-midnight border border-[#E9E7FF] rounded-full text-xs font-semibold hover:border-coral/40 transition shadow-sm">
                    View Rentals Log &rarr;
                </a>
                <a href="<?= base_url('admin/reports.php?type=revenue') ?>" class="px-4 py-2 bg-white text-midnight border border-[#E9E7FF] rounded-full text-xs font-semibold hover:border-coral/40 transition shadow-sm">
                    Audit Transactions &rarr;
                </a>
                <a href="<?= base_url('admin/reports.php?type=fines') ?>" class="px-4 py-2 bg-white text-midnight border border-[#E9E7FF] rounded-full text-xs font-semibold hover:border-coral/40 transition shadow-sm">
                    Audit Penalties &rarr;
                </a>
            </div>
        </div>

    <?php elseif ($activeTab === 'rentals'): ?>
        <!-- Rentals Log Table -->
        <div class="bg-white border border-[#E9E7FF] rounded-3xl overflow-hidden shadow-sm">
            <div class="p-6 border-b border-[#E9E7FF] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-[#FAF8F5]">
                <div>
                    <h2 class="text-lg font-bold font-display text-midnight">Rental Activity Log</h2>
                    <p class="text-xs text-slate-500">Historical records of equipment rentals and status transitions.</p>
                </div>
                <span class="text-xs bg-[#E9E7FF] text-midnight border border-[#d8d5ff] px-3.5 py-1 rounded-full font-semibold">
                    <?= count($reportData) ?> records shown
                </span>
            </div>

            <?php if (empty($reportData)): ?>
                <div class="p-12 text-center text-slate-400">
                    <div class="w-16 h-16 mx-auto rounded-full bg-[#FAF8F5] flex items-center justify-center text-2xl text-slate-400 mb-3">
                        <i class="ri-inbox-line"></i>
                    </div>
                    <p class="font-semibold text-sm text-midnight">No rental bookings recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-[#FAF8F5] text-slate-500 uppercase tracking-wider font-semibold border-b border-[#E9E7FF]">
                            <tr>
                                <th class="py-4 px-6">Req #</th>
                                <th class="py-4 px-6">Product</th>
                                <th class="py-4 px-6">Renter</th>
                                <th class="py-4 px-6">Owner</th>
                                <th class="py-4 px-6">Rental Window</th>
                                <th class="py-4 px-6 text-center">Duration</th>
                                <th class="py-4 px-6 text-right">Rent / Day</th>
                                <th class="py-4 px-6 text-right">Total Fee</th>
                                <th class="py-4 px-6 text-center">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E9E7FF]">
                            <?php foreach ($reportData as $row): 
                                $statusBadge = 'bg-slate-50 text-slate-700 border border-slate-200';
                                if ($row['status'] === 'Active') $statusBadge = 'bg-blue-50 text-blue-700 border border-blue-200';
                                elseif ($row['status'] === 'Completed') $statusBadge = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                elseif ($row['status'] === 'Pending') $statusBadge = 'bg-amber-50 text-amber-700 border border-amber-200';
                                elseif ($row['status'] === 'Approved') $statusBadge = 'bg-purple-50 text-purple-700 border border-purple-200';
                                elseif ($row['status'] === 'Rejected' || $row['status'] === 'Cancelled') $statusBadge = 'bg-rose-50 text-rose-700 border border-rose-200';
                            ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-4 px-6 font-mono font-bold text-midnight">#<?= $row['request_id'] ?></td>
                                <td class="py-4 px-6 font-semibold text-midnight"><?= htmlspecialchars($row['product_title']) ?></td>
                                <td class="py-4 px-6"><?= htmlspecialchars($row['renter_name']) ?></td>
                                <td class="py-4 px-6"><?= htmlspecialchars($row['owner_name']) ?></td>
                                <td class="py-4 px-6 font-mono text-[11px] text-slate-500">
                                    <?= date('d M Y', strtotime($row['start_date'])) ?> &rarr; <?= date('d M Y', strtotime($row['end_date'])) ?>
                                </td>
                                <td class="py-4 px-6 text-center font-mono"><?= (int)$row['total_days'] ?> days</td>
                                <td class="py-4 px-6 text-right font-mono">₹<?= number_format((float)$row['rent_per_day'], 2) ?></td>
                                <td class="py-4 px-6 text-right font-mono font-bold text-emerald-600">₹<?= number_format((float)$row['total_amount'], 2) ?></td>
                                <td class="py-4 px-6 text-center">
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
        <div class="bg-white border border-[#E9E7FF] rounded-3xl overflow-hidden shadow-sm">
            <div class="p-6 border-b border-[#E9E7FF] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-[#FAF8F5]">
                <div>
                    <h2 class="text-lg font-bold font-display text-midnight">Financial Transaction Ledger</h2>
                    <p class="text-xs text-slate-500">All customer payments, escrow deposits, and payout settlements.</p>
                </div>
                <span class="text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 px-3.5 py-1 rounded-full font-semibold">
                    <?= count($reportData) ?> transactions recorded
                </span>
            </div>

            <?php if (empty($reportData)): ?>
                <div class="p-12 text-center text-slate-400">
                    <div class="w-16 h-16 mx-auto rounded-full bg-[#FAF8F5] flex items-center justify-center text-2xl text-slate-400 mb-3">
                        <i class="ri-bank-card-line"></i>
                    </div>
                    <p class="font-semibold text-sm text-midnight">No financial transactions recorded yet.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-[#FAF8F5] text-slate-500 uppercase tracking-wider font-semibold border-b border-[#E9E7FF]">
                            <tr>
                                <th class="py-4 px-6">Txn #</th>
                                <th class="py-4 px-6">Req #</th>
                                <th class="py-4 px-6">Payer</th>
                                <th class="py-4 px-6">Product</th>
                                <th class="py-4 px-6 text-right">Rental Fee</th>
                                <th class="py-4 px-6 text-right">Deposit</th>
                                <th class="py-4 px-6 text-center">Deposit Status</th>
                                <th class="py-4 px-6 text-center">Mode</th>
                                <th class="py-4 px-6 text-center">Payment Status</th>
                                <th class="py-4 px-6">Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E9E7FF]">
                            <?php foreach ($reportData as $row): 
                                $payStatusBadge = ($row['payment_status'] === 'Completed') 
                                    ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' 
                                    : 'bg-amber-50 text-amber-700 border border-amber-200';
                                
                                $depBadge = 'bg-slate-50 text-slate-700 border border-slate-200';
                                if ($row['deposit_status'] === 'Held') $depBadge = 'bg-blue-50 text-blue-700 border border-blue-200';
                                elseif ($row['deposit_status'] === 'Refunded') $depBadge = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                elseif ($row['deposit_status'] === 'Deducted') $depBadge = 'bg-rose-50 text-rose-700 border border-rose-200';
                            ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-4 px-6 font-mono font-bold text-midnight">#<?= $row['transaction_id'] ?></td>
                                <td class="py-4 px-6 font-mono text-slate-400">#<?= $row['request_id'] ?></td>
                                <td class="py-4 px-6 font-semibold text-midnight"><?= htmlspecialchars($row['payer_name']) ?></td>
                                <td class="py-4 px-6"><?= htmlspecialchars($row['product_title']) ?></td>
                                <td class="py-4 px-6 text-right font-mono font-bold text-emerald-600">₹<?= number_format((float)$row['rental_amount'], 2) ?></td>
                                <td class="py-4 px-6 text-right font-mono text-blue-600">₹<?= number_format((float)$row['deposit_amount'], 2) ?></td>
                                <td class="py-4 px-6 text-center">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold <?= $depBadge ?>">
                                        <?= htmlspecialchars($row['deposit_status']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center font-mono text-[11px]"><?= htmlspecialchars(str_replace('_', ' ', $row['payment_mode'])) ?></td>
                                <td class="py-4 px-6 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $payStatusBadge ?>">
                                        <?= htmlspecialchars($row['payment_status']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-mono text-[11px] text-slate-400">
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
        <div class="bg-white border border-[#E9E7FF] rounded-3xl overflow-hidden shadow-sm">
            <div class="p-6 border-b border-[#E9E7FF] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 bg-[#FAF8F5]">
                <div>
                    <h2 class="text-lg font-bold font-display text-midnight">Penalties &amp; Deductions Audit</h2>
                    <p class="text-xs text-slate-500">Overdue fees, damage assessments, and deposit deduction history.</p>
                </div>
                <span class="text-xs bg-amber-50 text-amber-700 border border-amber-200 px-3.5 py-1 rounded-full font-semibold">
                    <?= count($reportData) ?> records
                </span>
            </div>

            <?php if (empty($reportData)): ?>
                <div class="p-12 text-center text-slate-400">
                    <div class="w-16 h-16 mx-auto rounded-full bg-[#FAF8F5] flex items-center justify-center text-2xl text-slate-400 mb-3">
                        <i class="ri-shield-check-line text-emerald-500"></i>
                    </div>
                    <p class="font-semibold text-sm text-midnight">Zero penalties or damage assessments issued.</p>
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-left text-xs text-slate-600">
                        <thead class="bg-[#FAF8F5] text-slate-500 uppercase tracking-wider font-semibold border-b border-[#E9E7FF]">
                            <tr>
                                <th class="py-4 px-6">Fine #</th>
                                <th class="py-4 px-6">Req #</th>
                                <th class="py-4 px-6">Renter</th>
                                <th class="py-4 px-6">Product</th>
                                <th class="py-4 px-6">Reason</th>
                                <th class="py-4 px-6 text-center">Late Days</th>
                                <th class="py-4 px-6 text-right">Amount</th>
                                <th class="py-4 px-6 text-center">Status</th>
                                <th class="py-4 px-6">Issued Date</th>
                                <th class="py-4 px-6">Settled Date</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#E9E7FF]">
                            <?php foreach ($reportData as $row): 
                                $statusBadge = 'bg-slate-50 text-slate-700 border border-slate-200';
                                if ($row['status'] === 'Paid') $statusBadge = 'bg-emerald-50 text-emerald-700 border border-emerald-200';
                                elseif ($row['status'] === 'Deducted_From_Deposit') $statusBadge = 'bg-blue-50 text-blue-700 border border-blue-200';
                                elseif ($row['status'] === 'Pending') $statusBadge = 'bg-amber-50 text-amber-700 border border-amber-200';
                                elseif ($row['status'] === 'Waived') $statusBadge = 'bg-purple-50 text-purple-700 border border-purple-200';
                            ?>
                            <tr class="hover:bg-slate-50/70 transition">
                                <td class="py-4 px-6 font-mono font-bold text-midnight">#<?= $row['fine_id'] ?></td>
                                <td class="py-4 px-6 font-mono text-slate-400">#<?= $row['request_id'] ?></td>
                                <td class="py-4 px-6 font-semibold text-midnight"><?= htmlspecialchars($row['renter_name']) ?></td>
                                <td class="py-4 px-6"><?= htmlspecialchars($row['product_title']) ?></td>
                                <td class="py-4 px-6">
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-[#E9E7FF] text-midnight">
                                        <?= htmlspecialchars($row['fine_type']) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 text-center font-mono"><?= (int)$row['late_days'] ?></td>
                                <td class="py-4 px-6 text-right font-mono font-bold text-rose-600">₹<?= number_format((float)$row['amount'], 2) ?></td>
                                <td class="py-4 px-6 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $statusBadge ?>">
                                        <?= str_replace('_', ' ', htmlspecialchars($row['status'])) ?>
                                    </span>
                                </td>
                                <td class="py-4 px-6 font-mono text-[11px] text-slate-400">
                                    <?= date('d M Y', strtotime($row['issue_date'])) ?>
                                </td>
                                <td class="py-4 px-6 font-mono text-[11px] text-slate-400">
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
