<?php
/**
 * Online Rental Management System (ORMS)
 * Admin — Configure Fine Rate & System Policies
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.9, 4 & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/fine_config.php';
require_once __DIR__ . '/../classes/Admin.php';

require_role('Admin');

$settings = get_fine_settings();
$errors = [];
$successMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = "Security validation failed. Please try again.";
    }

    $rate = (float) ($_POST['rate_per_day'] ?? 0);
    if ($rate <= 0) {
        $errors[] = "Daily fine rate must be a positive number greater than ₹0.00.";
    }

    if (empty($errors)) {
        try {
            $admin = new Admin((int) $_SESSION['admin_id']);
            $admin->configureFineRate($rate);
            set_flash('success', "Daily fine rate successfully updated to ₹" . number_format($rate, 2) . " / day.");
            redirect('admin/configure_fine_rate.php');
        } catch (Exception $e) {
            $errors[] = "Failed to update configuration: " . $e->getMessage();
        }
    }
}

$page_title = "Configure Fine Rate & Return Policies";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-3xl mx-auto px-4 py-8">
    <!-- Breadcrumb -->
    <div class="mb-6 flex items-center space-x-2 text-xs text-slate-400">
        <a href="<?= base_url('admin/dashboard.php') ?>" class="hover:text-white transition">Admin Dashboard</a>
        <span>&rsaquo;</span>
        <span class="text-slate-200">Configure Fine Rates</span>
    </div>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-3">
            <span class="p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-400 text-xl">⚙️</span>
            <div>
                <h1 class="text-2xl font-bold text-white tracking-tight">Fine Rate & Policy Configuration</h1>
                <p class="text-xs text-slate-400 mt-1">Manage global system late return daily rates, statutory caps, and auto-refund timeouts.</p>
            </div>
        </div>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800/80 text-rose-300 text-xs">
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Metric Card 1: Active Daily Rate -->
        <div class="card p-5 border border-slate-800 rounded-2xl bg-slate-900/60 space-y-2">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Current Daily Rate</div>
            <div class="text-2xl font-black text-amber-400 font-mono">₹<?= number_format((float) $settings['rate_per_day'], 2) ?></div>
            <div class="text-[10px] text-slate-500">Per late calendar day</div>
        </div>

        <!-- Metric Card 2: Maximum Fine Cap (Rule 9) -->
        <div class="card p-5 border border-slate-800 rounded-2xl bg-slate-900/60 space-y-2">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Statutory Fine Cap</div>
            <div class="text-2xl font-black text-blue-400 font-mono">2.0× Deposit</div>
            <div class="text-[10px] text-slate-500">Section 5 Rule 9 ceiling</div>
        </div>

        <!-- Metric Card 3: Auto-Refund Timeout (Rule 12) -->
        <div class="card p-5 border border-slate-800 rounded-2xl bg-slate-900/60 space-y-2">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Auto-Refund Timeout</div>
            <div class="text-2xl font-black text-emerald-400 font-mono">7 Days</div>
            <div class="text-[10px] text-slate-500">Post end_date auto-release</div>
        </div>
    </div>

    <!-- Update Form -->
    <form method="POST" action="" class="card p-6 border border-slate-800 rounded-2xl bg-slate-900/60 space-y-6">
        <?= csrf_field() ?>

        <div>
            <label for="rate_per_day" class="block text-xs font-semibold text-slate-300 mb-2">
                Daily Late Fee Rate (₹ / Day) <span class="text-rose-400">*</span>
            </label>
            <div class="relative max-w-sm">
                <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 text-sm">₹</span>
                <input type="number" 
                       id="rate_per_day" 
                       name="rate_per_day" 
                       step="0.50" 
                       min="1" 
                       value="<?= htmlspecialchars((string) $settings['rate_per_day']) ?>" 
                       required
                       class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-8 pr-4 py-2.5 text-sm text-white focus:outline-none focus:border-blue-500 transition font-mono">
            </div>
            <p class="text-[11px] text-slate-400 mt-2">
                This rate is applied when calculating <code class="text-amber-300 font-mono">late_days × rate_per_day</code> on overdue rental returns. The rate is snapshotted into each <code class="text-blue-300 font-mono">FINE.rate_per_day</code> record upon creation.
            </p>
        </div>

        <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 text-xs text-slate-400 space-y-2">
            <div class="font-bold text-slate-200">Statutory Core Business Logic Constraints (Immutable):</div>
            <div>• <strong>Rule 9 Maximum Cap:</strong> Total fine cannot exceed twice the item's security deposit (<code class="text-slate-300">2 × security_deposit</code>).</div>
            <div>• <strong>Rule 8 Deposit Waterfall:</strong> Clean returns refund 100%. Fines $\le$ deposit are automatically deducted. Excess fines are billed to renter.</div>
            <div>• <strong>Rule 12 Auto-Refund:</strong> Rentals unconfirmed by owner after 7 days are automatically settled.</div>
        </div>

        <div class="pt-4 border-t border-slate-800 flex items-center justify-end">
            <button type="submit" 
                    class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-600/20 transition flex items-center space-x-2">
                <span>💾</span>
                <span>Save Configuration</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
