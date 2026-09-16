<?php
/**
 * Online Rental Management System (ORMS)
 * Admin — Configure Fine Rate & System Policies
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
    if (!csrf_verify()) {
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

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('admin/dashboard.php') ?>" class="hover:text-coral transition">Admin Dashboard</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Configure Fine Rates</li>
        </ol>
    </nav>

    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center space-x-2 text-coral text-xs font-semibold uppercase tracking-wider mb-1">
            <i class="ri-settings-3-line text-sm"></i>
            <span>Policy Governance</span>
        </div>
        <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">Fine Rate & Policy Configuration</h1>
        <p class="text-xs sm:text-sm text-slate-500 mt-1">
            Manage system-wide overdue rental rates, statutory fine caps, and automatic deposit settlement thresholds.
        </p>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-xs">
            <ul class="list-disc list-inside space-y-1">
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <!-- Metric Card 1: Active Daily Rate -->
        <div class="p-6 border border-[#E9E7FF] rounded-3xl bg-white shadow-sm space-y-2">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Current Daily Rate</div>
            <div class="text-3xl font-display font-bold text-amber-600">₹<?= number_format((float) $settings['rate_per_day'], 2) ?></div>
            <div class="text-xs text-slate-400">Per late calendar day</div>
        </div>

        <!-- Metric Card 2: Maximum Fine Cap -->
        <div class="p-6 border border-[#E9E7FF] rounded-3xl bg-white shadow-sm space-y-2">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Statutory Cap</div>
            <div class="text-3xl font-display font-bold text-blue-600">2.0× Deposit</div>
            <div class="text-xs text-slate-400">Escrow liability ceiling</div>
        </div>

        <!-- Metric Card 3: Auto-Refund Timeout -->
        <div class="p-6 border border-[#E9E7FF] rounded-3xl bg-white shadow-sm space-y-2">
            <div class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider">Auto-Refund Timeout</div>
            <div class="text-3xl font-display font-bold text-emerald-600">7 Days</div>
            <div class="text-xs text-slate-400">Post end_date auto-release</div>
        </div>
    </div>

    <!-- Update Form -->
    <form method="POST" action="" class="p-6 sm:p-8 border border-[#E9E7FF] rounded-3xl bg-white shadow-sm space-y-6">
        <?= csrf_field() ?>

        <div>
            <label for="rate_per_day" class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">
                Daily Late Fee Rate (₹ / Day) <span class="text-coral">*</span>
            </label>
            <div class="relative max-w-sm">
                <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 text-sm">₹</span>
                <input type="number" 
                       id="rate_per_day" 
                       name="rate_per_day" 
                       step="0.50" 
                       min="1" 
                       value="<?= htmlspecialchars((string) $settings['rate_per_day']) ?>" 
                       required
                       class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl pl-8 pr-4 py-3 text-sm text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition font-mono">
            </div>
            <p class="text-[11px] text-slate-400 mt-2">
                This rate is applied when calculating <code class="text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded font-mono">late_days × rate_per_day</code> on overdue equipment returns.
            </p>
        </div>

        <div class="p-5 rounded-2xl bg-[#FAF8F5] border border-[#E9E7FF] text-xs text-slate-600 space-y-2">
            <div class="font-bold text-midnight flex items-center space-x-1.5">
                <i class="ri-shield-check-line text-coral text-sm"></i>
                <span>Platform Escrow Rules:</span>
            </div>
            <div>• <strong>Maximum Cap:</strong> Total fine cannot exceed twice the item's security deposit (<code class="text-slate-800 font-mono">2 × security_deposit</code>).</div>
            <div>• <strong>Deposit Waterfall:</strong> Clean returns refund 100%. Fines &le; deposit are automatically deducted. Excess fines are invoiced to renter.</div>
            <div>• <strong>Auto-Refund:</strong> Rentals unconfirmed by owner after 7 days are automatically settled.</div>
        </div>

        <div class="pt-4 border-t border-[#E9E7FF] flex items-center justify-end">
            <button type="submit" 
                    class="px-7 py-3 bg-coral hover:bg-[#e04e53] text-white font-semibold text-xs rounded-full shadow-glow-coral transition flex items-center space-x-1.5">
                <i class="ri-save-line"></i>
                <span>Save Configuration</span>
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

