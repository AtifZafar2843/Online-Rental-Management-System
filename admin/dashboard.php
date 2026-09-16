<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Dashboard & Command Center
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Welcome Banner -->
    <div class="bg-gradient-to-br from-midnight via-[#131b33] to-midnight rounded-3xl p-6 sm:p-10 shadow-sm text-white mb-8 border border-[#E9E7FF] relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <span class="inline-flex items-center space-x-1 px-3 py-1 bg-white/10 rounded-full text-xs font-semibold tracking-wide uppercase border border-white/20 text-coral">
                    <i class="ri-shield-star-line text-sm"></i>
                    <span>System Administration &bull; Level <?= $admin->getSecurityLevel() ?></span>
                </span>
                <h1 class="text-2xl sm:text-3xl font-display font-bold mt-3">Admin Portal &bull; <?= htmlspecialchars(current_user_name()) ?></h1>
                <p class="text-slate-300 text-xs sm:text-sm mt-1 max-w-2xl">
                    Platform management, dispute adjudication, user governance, category taxonomy, and business intelligence reports.
                </p>
            </div>
            <div class="flex items-center space-x-3 flex-wrap gap-y-2">
                <a href="<?= base_url('admin/reports.php') ?>" class="px-5 py-2.5 bg-white text-midnight font-semibold text-xs rounded-full shadow-sm hover:bg-slate-50 transition flex items-center space-x-1.5">
                    <i class="ri-bar-chart-2-line text-coral text-sm"></i>
                    <span>Audit Reports</span>
                </a>
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="px-5 py-2.5 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs rounded-full shadow-glow-coral transition flex items-center space-x-1.5">
                    <i class="ri-scales-3-line text-sm"></i>
                    <span>Dispute Center <?= $openDisputesCount > 0 ? "({$openDisputesCount})" : '' ?></span>
                </a>
            </div>
        </div>
    </div>

    <!-- Dispute Alert Banner if any pending -->
    <?php if ($openDisputesCount > 0): ?>
    <div class="mb-8 p-5 bg-rose-50 border border-rose-200 rounded-3xl flex flex-col sm:flex-row sm:items-center justify-between gap-4 shadow-sm">
        <div class="flex items-center space-x-3">
            <div class="w-10 h-10 rounded-2xl bg-rose-100 text-rose-600 flex items-center justify-center text-xl flex-shrink-0">
                <i class="ri-alarm-warning-line"></i>
            </div>
            <div>
                <p class="text-sm font-bold text-rose-900 font-display">
                    <?= $openDisputesCount ?> Active Dispute Case<?= $openDisputesCount > 1 ? 's' : '' ?> Awaiting Adjudication
                </p>
                <p class="text-xs text-rose-700 mt-0.5">Parties have filed formal claims requiring review, admin findings, and settlement resolution.</p>
            </div>
        </div>
        <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="px-5 py-2 bg-rose-600 hover:bg-rose-500 text-white text-xs font-semibold rounded-full transition shadow-sm self-start sm:self-auto whitespace-nowrap">
            Adjudicate Now &rarr;
        </a>
    </div>
    <?php endif; ?>

    <!-- Metric Counters Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-6 mb-8">
        <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Registered Users</span>
                <i class="ri-group-line text-lg text-slate-400"></i>
            </div>
            <div class="text-3xl font-display font-bold text-midnight"><?= $userCount ?></div>
            <a href="<?= base_url('admin/manage_users.php') ?>" class="text-xs text-coral hover:underline mt-2 inline-block font-medium">
                Manage Directory &rarr;
            </a>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Taxonomy</span>
                <i class="ri-price-tag-3-line text-lg text-slate-400"></i>
            </div>
            <div class="text-3xl font-display font-bold text-midnight"><?= $catCount ?></div>
            <a href="<?= base_url('admin/manage_categories.php') ?>" class="text-xs text-coral hover:underline mt-2 inline-block font-medium">
                Categories &rarr;
            </a>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Inventory</span>
                <i class="ri-box-3-line text-lg text-slate-400"></i>
            </div>
            <div class="text-3xl font-display font-bold text-midnight"><?= $productCount ?></div>
            <span class="text-xs text-slate-400 mt-2 block">Across all owners</span>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Active Rentals</span>
                <i class="ri-time-line text-lg text-blue-500"></i>
            </div>
            <div class="text-3xl font-display font-bold text-blue-600"><?= $activeRentals ?></div>
            <a href="<?= base_url('admin/reports.php?type=rentals') ?>" class="text-xs text-blue-600 hover:underline mt-2 inline-block font-medium">
                Live Bookings &rarr;
            </a>
        </div>

        <div class="bg-white border border-[#E9E7FF] p-6 rounded-3xl shadow-sm hover:shadow-md transition">
            <div class="flex items-center justify-between mb-2">
                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wider">Gross Revenue</span>
                <i class="ri-money-rupee-circle-line text-lg text-emerald-500"></i>
            </div>
            <div class="text-2xl font-display font-bold text-emerald-600">₹<?= number_format($grossRevenue, 2) ?></div>
            <a href="<?= base_url('admin/reports.php?type=revenue') ?>" class="text-xs text-emerald-600 hover:underline mt-2 inline-block font-medium">
                Financial Audit &rarr;
            </a>
        </div>
    </div>

    <!-- Quick Tools & Recent Disputes -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-8">
        <!-- Administrative Actions Command Cards (1 col) -->
        <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-7 shadow-sm h-fit">
            <h2 class="text-base font-bold font-display text-midnight mb-4 flex items-center space-x-2">
                <i class="ri-flashlight-line text-coral text-lg"></i>
                <span>Platform Controls</span>
            </h2>
            <div class="space-y-3">
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="p-4 bg-[#FAF8F5] hover:bg-slate-100/80 rounded-2xl border border-[#E9E7FF] flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-coral/10 text-coral flex items-center justify-center text-lg">
                            <i class="ri-scales-3-line"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-midnight group-hover:text-coral transition">Dispute Adjudication</div>
                            <div class="text-[11px] text-slate-500">Hear claims, waive fines, arbitrate cases</div>
                        </div>
                    </div>
                    <?php if ($openDisputesCount > 0): ?>
                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">
                            <?= $openDisputesCount ?>
                        </span>
                    <?php endif; ?>
                </a>

                <a href="<?= base_url('admin/manage_users.php') ?>" class="p-4 bg-[#FAF8F5] hover:bg-slate-100/80 rounded-2xl border border-[#E9E7FF] flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-lg">
                            <i class="ri-user-settings-line"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-midnight group-hover:text-coral transition">User Governance</div>
                            <div class="text-[11px] text-slate-500">Manage accounts, toggle Active / Inactive</div>
                        </div>
                    </div>
                    <i class="ri-arrow-right-s-line text-slate-400 group-hover:text-midnight transition"></i>
                </a>

                <a href="<?= base_url('admin/manage_categories.php') ?>" class="p-4 bg-[#FAF8F5] hover:bg-slate-100/80 rounded-2xl border border-[#E9E7FF] flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-lg">
                            <i class="ri-folder-settings-line"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-midnight group-hover:text-coral transition">Category Taxonomy</div>
                            <div class="text-[11px] text-slate-500">Add, edit, or remove catalog categories</div>
                        </div>
                    </div>
                    <i class="ri-arrow-right-s-line text-slate-400 group-hover:text-midnight transition"></i>
                </a>

                <a href="<?= base_url('admin/reports.php') ?>" class="p-4 bg-[#FAF8F5] hover:bg-slate-100/80 rounded-2xl border border-[#E9E7FF] flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-lg">
                            <i class="ri-file-chart-line"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-midnight group-hover:text-coral transition">Audit &amp; Intelligence</div>
                            <div class="text-[11px] text-slate-500">Rentals, revenue, inventory, print/export</div>
                        </div>
                    </div>
                    <i class="ri-arrow-right-s-line text-slate-400 group-hover:text-midnight transition"></i>
                </a>

                <a href="<?= base_url('admin/configure_fine_rate.php') ?>" class="p-4 bg-[#FAF8F5] hover:bg-slate-100/80 rounded-2xl border border-[#E9E7FF] flex items-center justify-between group transition">
                    <div class="flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-lg">
                            <i class="ri-settings-3-line"></i>
                        </div>
                        <div>
                            <div class="text-xs font-bold text-midnight group-hover:text-coral transition">Configure Fine Rate</div>
                            <div class="text-[11px] text-slate-500">Adjust daily late-return penalty rate</div>
                        </div>
                    </div>
                    <i class="ri-arrow-right-s-line text-slate-400 group-hover:text-midnight transition"></i>
                </a>
            </div>
        </div>

        <!-- Recent Open Disputes Triage (2 cols) -->
        <div class="lg:col-span-2 bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-7 shadow-sm">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h2 class="text-base font-bold font-display text-midnight flex items-center space-x-2">
                        <i class="ri-scales-3-line text-coral text-lg"></i>
                        <span>Pending Disputes for Triage</span>
                    </h2>
                    <p class="text-xs text-slate-500">Recent claim submissions requiring administrator arbitration.</p>
                </div>
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="text-xs text-coral hover:underline font-semibold">
                    View All Cases &rarr;
                </a>
            </div>

            <?php if (empty($recentDisputes)): ?>
                <div class="p-12 text-center text-slate-400 border border-[#E9E7FF] rounded-2xl bg-[#FAF8F5]">
                    <div class="w-14 h-14 mx-auto rounded-full bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mb-3">
                        <i class="ri-checkbox-circle-line"></i>
                    </div>
                    <p class="text-sm font-semibold text-midnight">All disputes resolved!</p>
                    <p class="text-xs text-slate-400 mt-1">There are currently zero pending dispute claims requiring triage.</p>
                </div>
            <?php else: ?>
                <div class="space-y-3">
                    <?php foreach ($recentDisputes as $disp): ?>
                        <div class="p-4 bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 hover:shadow-sm transition">
                            <div>
                                <div class="flex items-center space-x-2 mb-1">
                                    <span class="font-mono text-xs font-bold text-midnight">#DISP-<?= $disp['dispute_id'] ?></span>
                                    <span class="text-xs text-slate-400">&bull; Req #<?= $disp['request_id'] ?></span>
                                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold <?= $disp['status'] === 'Escalated' ? 'bg-rose-50 text-rose-700 border border-rose-200' : ($disp['status'] === 'Under_Review' ? 'bg-amber-50 text-amber-700 border border-amber-200' : 'bg-red-50 text-red-700 border border-red-200') ?>">
                                        <?= str_replace('_', ' ', htmlspecialchars($disp['status'])) ?>
                                    </span>
                                </div>
                                <p class="text-xs font-semibold text-midnight">
                                    <span class="text-blue-600"><?= htmlspecialchars($disp['raised_by_name']) ?></span> vs. <span class="text-purple-600"><?= htmlspecialchars($disp['against_name']) ?></span>
                                    <span class="text-slate-400 font-normal">on <?= htmlspecialchars($disp['product_title']) ?></span>
                                </p>
                                <p class="text-xs text-slate-500 mt-1 line-clamp-1 italic">
                                    &ldquo;<?= htmlspecialchars($disp['reason']) ?>&rdquo;
                                </p>
                            </div>
                            <div class="flex sm:flex-col items-end justify-between sm:justify-center gap-2 flex-shrink-0">
                                <span class="text-[10px] text-slate-400 font-mono">
                                    <?= date('d M, H:i', strtotime($disp['created_date'])) ?>
                                </span>
                                <a href="<?= base_url('admin/resolve_disputes.php?dispute_id=' . $disp['dispute_id']) ?>" class="px-4 py-1.5 bg-coral hover:bg-[#e04e53] text-white text-xs font-semibold rounded-full transition shadow-sm">
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

