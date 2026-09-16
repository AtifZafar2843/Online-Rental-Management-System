<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Dashboard & Command Center
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1, 13.I (Page 29)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/Dispute.php';

require_admin();

$pdo = Database::getInstance()->getConnection();
$admin = new Admin(
    (int) $_SESSION['admin_id'],
    $_SESSION['username'] ?? 'admin',
    $_SESSION['email'] ?? '',
    $_SESSION['name'] ?? 'Administrator'
);

// Metrics
$userCount = (int) $pdo->query("SELECT COUNT(*) FROM `USER`")->fetchColumn();
$catCount = (int) $pdo->query("SELECT COUNT(*) FROM `CATEGORY`")->fetchColumn();
$productCount = (int) $pdo->query("SELECT COUNT(*) FROM `PRODUCT`")->fetchColumn();
$activeRentals = (int) $pdo->query("SELECT COUNT(*) FROM `RENTAL_REQUEST` WHERE status = 'Active'")->fetchColumn();
$grossRevenue = (float) $pdo->query("SELECT COALESCE(SUM(rental_amount), 0.00) FROM `TRANSACTION` WHERE payment_status = 'Completed'")->fetchColumn();
$openDisputesCount = (int) $pdo->query("SELECT COUNT(*) FROM `DISPUTE` WHERE status IN ('Open', 'Under_Review', 'Escalated')")->fetchColumn();

// Fetch recent open disputes for quick triage
$stmt = $pdo->prepare("
    SELECT d.dispute_id, d.request_id, d.reason, d.status, d.created_date,
           u_from.name AS raised_by_name, u_against.name AS against_name,
           p.title AS product_title
    FROM `DISPUTE` d
    JOIN `USER` u_from ON d.raised_by = u_from.user_id
    JOIN `USER` u_against ON d.against = u_against.user_id
    JOIN `RENTAL_REQUEST` r ON d.request_id = r.request_id
    JOIN `PRODUCT` p ON r.product_id = p.product_id
    WHERE d.status IN ('Open', 'Under_Review', 'Escalated')
    ORDER BY d.dispute_id DESC
    LIMIT 5
");
$stmt->execute();
$recentDisputes = $stmt->fetchAll();

$pageTitle = 'Administrator Command Center — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-r from-red-700 via-rose-700 to-slate-900 rounded-2xl p-8 shadow-xl text-white mb-8 border border-rose-600/40 relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
            <div>
                <span class="px-3 py-1 bg-red-500/30 rounded-full text-xs font-semibold tracking-wide uppercase border border-red-400/30">
                    System Administration &bull; Level <?= $admin->getSecurityLevel() ?>
                </span>
                <h1 class="text-3xl font-extrabold mt-3">Admin Portal &bull; <?= htmlspecialchars(current_user_name()) ?></h1>
                <p class="text-rose-200 text-sm mt-1">Platform management, dispute adjudication, user governance, category taxonomy, and audit reports.</p>
            </div>
            <div class="flex items-center space-x-3">
                <a href="<?= base_url('admin/reports.php') ?>" class="px-4 py-2.5 bg-white text-slate-900 font-bold text-xs rounded-xl shadow hover:bg-slate-100 transition flex items-center space-x-2">
                    <span>📊</span>
                    <span>View Audit Reports</span>
                </a>
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="px-4 py-2.5 bg-red-950/60 text-white font-bold text-xs rounded-xl border border-red-400/40 hover:bg-red-900/60 transition flex items-center space-x-2">
                    <span>⚖️</span>
                    <span>Dispute Center <?= $openDisputesCount > 0 ? "({$openDisputesCount})" : '' ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Dispute Alert Banner if any pending -->
    <?php if ($openDisputesCount > 0): ?>
    <div class="mb-8 p-4 bg-rose-950/60 border border-rose-500/40 rounded-2xl flex items-center justify-between shadow-lg">
        <div class="flex items-center space-x-3">
            <span class="text-2xl animate-pulse">⚠️</span>
            <div>
                <p class="text-sm font-bold text-rose-200">
                    <?= $openDisputesCount ?> Active Dispute Case<?= $openDisputesCount > 1 ? 's' : '' ?> Awaiting Adjudication
                </p>
                <p class="text-xs text-rose-300/80">Parties have filed formal claims requiring review, admin findings, and settlement resolution.</p>
            </div>
        </div>
        <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="px-4 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-bold rounded-xl transition shadow">
            Adjudicate Now &rarr;
        </a>
    </div>
    <?php endif; ?>

    <!-- Metric Counters Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Registered Users</span>
            <div class="text-3xl font-bold text-white mt-2"><?= $userCount ?></div>
            <a href="<?= base_url('admin/manage_users.php') ?>" class="text-xs text-blue-400 hover:text-blue-300 mt-2 block font-medium">
                Manage Directory &rarr;
            </a>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Catalog Taxonomy</span>
            <div class="text-3xl font-bold text-white mt-2"><?= $catCount ?></div>
            <a href="<?= base_url('admin/manage_categories.php') ?>" class="text-xs text-emerald-400 hover:text-emerald-300 mt-2 block font-medium">
                Manage Categories &rarr;
            </a>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Inventory Items</span>
            <div class="text-3xl font-bold text-white mt-2"><?= $productCount ?></div>
            <span class="text-xs text-slate-500 mt-2 block">Across all owners</span>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Rentals</span>
            <div class="text-3xl font-bold text-indigo-400 mt-2"><?= $activeRentals ?></div>
            <a href="<?= base_url('admin/reports.php?type=rentals') ?>" class="text-xs text-indigo-400 hover:text-indigo-300 mt-2 block font-medium">
                View Rentals &rarr;
            </a>
        </div>

        <div class="bg-slate-900 border border-slate-800 p-6 rounded-2xl shadow-lg">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Gross Volume</span>
            <div class="text-2xl font-bold text-emerald-400 mt-2">₹<?= number_format($grossRevenue, 2) ?></div>
            <a href="<?= base_url('admin/reports.php?type=revenue') ?>" class="text-xs text-emerald-400 hover:text-emerald-300 mt-2 block font-medium">
                Financial Audit &rarr;
            </a>
        </div>
    </div>

    <!-- Quick Tools & Recent Disputes -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Administrative Actions Command Cards (1 col) -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl h-fit">
            <h2 class="text-base font-bold text-white mb-4 flex items-center space-x-2">
                <span>⚡</span>
                <span>Administrative Actions</span>
            </h2>
            <div class="space-y-3">
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="p-3.5 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700/60 flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <span class="text-xl">⚖️</span>
                        <div>
                            <div class="text-xs font-bold text-white group-hover:text-red-400 transition">Dispute Adjudication</div>
                            <div class="text-[11px] text-slate-400">Hear claims, waive fines, resolve cases</div>
                        </div>
                    </div>
                    <?php if ($openDisputesCount > 0): ?>
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-rose-500/20 text-rose-300 border border-rose-500/30">
                            <?= $openDisputesCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="<?= base_url('admin/manage_users.php') ?>" class="p-3.5 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700/60 flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <span class="text-xl">👥</span>
                        <div>
                            <div class="text-xs font-bold text-white group-hover:text-blue-400 transition">User Governance</div>
                            <div class="text-[11px] text-slate-400">View roster, toggle Active / Inactive / Banned</div>
                        </div>
                    </div>
                    <span class="text-slate-500 text-xs">&rarr;</span>
                </a>

                <a href="<?= base_url('admin/manage_categories.php') ?>" class="p-3.5 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700/60 flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <span class="text-xl">🏷️</span>
                        <div>
                            <div class="text-xs font-bold text-white group-hover:text-emerald-400 transition">Category Taxonomy</div>
                            <div class="text-[11px] text-slate-400">Manage categories, subcategories, counts</div>
                        </div>
                    </div>
                    <span class="text-slate-500 text-xs">&rarr;</span>
                </a>

                <a href="<?= base_url('admin/reports.php') ?>" class="p-3.5 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700/60 flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <span class="text-xl">📊</span>
                        <div>
                            <div class="text-xs font-bold text-white group-hover:text-indigo-400 transition">Reports &amp; Audits</div>
                            <div class="text-[11px] text-slate-400">Rentals, transactions, fine ledgers, print</div>
                        </div>
                    </div>
                    <span class="text-slate-500 text-xs">&rarr;</span>
                </a>

                <a href="<?= base_url('admin/configure_fine_rate.php') ?>" class="p-3.5 bg-slate-800/60 hover:bg-slate-800 rounded-xl border border-slate-700/60 flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <span class="text-xl">⚙️</span>
                        <div>
                            <div class="text-xs font-bold text-white group-hover:text-amber-400 transition">Configure Fine Rate</div>
                            <div class="text-[11px] text-slate-400">Adjust late-return penalty rate per day</div>
                        </div>
                    </div>
                    <span class="text-slate-500 text-xs">&rarr;</span>
                </a>
            </div>
        </div>

        <!-- Recent Open Disputes Triage (2 cols) -->
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-base font-bold text-white flex items-center space-x-2">
                        <span>⚖️</span>
                        <span>Pending Disputes for Triage</span>
                    </h2>
                    <p class="text-xs text-slate-400">Most recently filed cases requiring administrator intervention.</p>
                </div>
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="text-xs text-red-400 hover:text-red-300 font-semibold">
                    View All Cases &rarr;
                </a>
            </div>

            <?php if (empty($recentDisputes)): ?>
                <div class="p-12 text-center text-slate-500 border border-slate-800/80 rounded-xl bg-slate-950/40">
                    <span class="text-4xl block mb-2">🎉</span>
                    <p class="text-sm font-semibold text-slate-300">All disputes resolved!</p>
                    <p class="text-xs text-slate-500 mt-1">There are no pending dispute claims in the system.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recentDisputes as $disp): ?>
                        <div class="p-4 bg-slate-950/50 border border-slate-800 rounded-xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hover:border-slate-700 transition">
                            <div>
                                <div class="flex items-center space-x-2 mb-1">
                                    <span class="font-mono text-xs font-bold text-white">#DISP-<?= $disp['dispute_id'] ?></span>
                                    <span class="text-xs text-slate-400">&bull; Req #<?= $disp['request_id'] ?></span>
                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold <?= $disp['status'] === 'Escalated' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : ($disp['status'] === 'Under_Review' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-red-500/20 text-red-300 border border-red-500/30') ?>">
                                        <?= str_replace('_', ' ', htmlspecialchars($disp['status'])) ?>
                                    </span>
                                </div>
                                <p class="text-xs font-semibold text-slate-200">
                                    <span class="text-blue-400"><?= htmlspecialchars($disp['raised_by_name']) ?></span> vs. <span class="text-indigo-400"><?= htmlspecialchars($disp['against_name']) ?></span>
                                    <span class="text-slate-500 font-normal">on <?= htmlspecialchars($disp['product_title']) ?></span>
                                </p>
                                <p class="text-xs text-slate-400 mt-1 line-clamp-1 italic">
                                    &ldquo;<?= htmlspecialchars($disp['reason']) ?>&rdquo;
                                </p>
                            </div>
                            <div class="flex sm:flex-col items-end justify-between sm:justify-center gap-2 flex-shrink-0">
                                <span class="text-[10px] text-slate-500 font-mono">
                                    <?= date('d M, H:i', strtotime($disp['created_date'])) ?>
                                </span>
                                <a href="<?= base_url('admin/resolve_disputes.php?dispute_id=' . $disp['dispute_id']) ?>" class="px-3 py-1.5 bg-red-600 hover:bg-red-500 text-white text-xs font-bold rounded-lg transition shadow">
                                    Adjudicate &rarr;
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
