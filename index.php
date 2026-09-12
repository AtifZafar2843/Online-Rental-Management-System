<?php
/**
 * Online Rental Management System (ORMS)
 * Main Application Landing Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = Database::getInstance()->getConnection();

// Fetch categories from DB
$catStmt = $pdo->query("SELECT category_id, category_name, description FROM `CATEGORY` ORDER BY category_name ASC");
$categories = $catStmt->fetchAll();

// Quick stats
$stats = [
    'categories' => count($categories),
    'users' => (int) $pdo->query("SELECT COUNT(*) FROM `USER` WHERE status = 'Active'")->fetchColumn(),
    'products' => (int) $pdo->query("SELECT COUNT(*) FROM `PRODUCT` WHERE avail_status = 'Available'")->fetchColumn()
];

$pageTitle = 'ORMS — Peer-to-Peer Online Rental Management System';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section -->
<div class="relative overflow-hidden bg-gradient-to-b from-slate-900 via-slate-950 to-slate-950 py-20 border-b border-slate-800/80">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold bg-blue-900/60 text-blue-300 border border-blue-700/50 mb-6">
            ✨ Peer-to-Peer Rental Economy
        </span>
        <h1 class="text-4xl sm:text-6xl font-extrabold text-white tracking-tight leading-tight max-w-4xl mx-auto">
            Rent Anything. Share Everything. <span class="bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">Smarter.</span>
        </h1>
        <p class="mt-6 text-base sm:text-lg text-slate-400 max-w-2xl mx-auto leading-relaxed">
            The modern digital platform connecting product owners with trusted renters. Earn from idle assets or rent premium electronics, furniture, and vehicles on demand.
        </p>

        <!-- CTA Buttons -->
        <div class="mt-10 flex flex-wrap justify-center gap-4">
            <?php if (!is_logged_in()): ?>
                <a href="<?= base_url('auth/register.php') ?>" 
                   class="px-8 py-3.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm shadow-xl shadow-blue-500/25 transition">
                    Start Renting / Listing &rarr;
                </a>
                <a href="<?= base_url('renter/search.php') ?>" 
                   class="px-8 py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 font-semibold text-sm transition">
                    Explore Catalog
                </a>
            <?php else: ?>
                <?php if (current_role() === 'Owner'): ?>
                    <a href="<?= base_url('owner/add_product.php') ?>" 
                       class="px-8 py-3.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm shadow-xl shadow-blue-500/25 transition">
                        + List a New Product
                    </a>
                    <a href="<?= base_url('owner/dashboard.php') ?>" 
                       class="px-8 py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 font-semibold text-sm transition">
                        Owner Dashboard &rarr;
                    </a>
                <?php elseif (is_admin()): ?>
                    <a href="<?= base_url('admin/dashboard.php') ?>" 
                       class="px-8 py-3.5 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-bold text-sm shadow-xl shadow-indigo-500/25 transition">
                        Open Admin Dashboard &rarr;
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('renter/search.php') ?>" 
                       class="px-8 py-3.5 rounded-xl bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm shadow-xl shadow-blue-500/25 transition">
                        Browse Products &rarr;
                    </a>
                    <a href="<?= base_url('renter/dashboard.php') ?>" 
                       class="px-8 py-3.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 font-semibold text-sm transition">
                        Renter Dashboard
                    </a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Quick Platform Stats -->
        <div class="mt-16 grid grid-cols-3 max-w-lg mx-auto border-t border-slate-800/80 pt-8 text-center">
            <div>
                <div class="text-2xl font-bold text-white"><?= $stats['categories'] ?></div>
                <div class="text-xs text-slate-400 mt-1 uppercase tracking-wider">Categories</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-blue-400"><?= $stats['users'] ?>+</div>
                <div class="text-xs text-slate-400 mt-1 uppercase tracking-wider">Active Users</div>
            </div>
            <div>
                <div class="text-2xl font-bold text-indigo-400"><?= $stats['products'] ?></div>
                <div class="text-xs text-slate-400 mt-1 uppercase tracking-wider">Available Items</div>
            </div>
        </div>
    </div>
</div>

<!-- Categories Showcase Section -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h2 class="text-2xl font-bold text-white tracking-tight">Popular Rental Categories</h2>
            <p class="text-xs text-slate-400 mt-1">Verified equipment and goods ready for instant booking</p>
        </div>
        <a href="<?= base_url('renter/search.php') ?>" class="text-xs font-semibold text-blue-400 hover:text-blue-300">
            View All Catalog &rarr;
        </a>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($categories as $cat): ?>
            <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-2xl p-6 transition duration-200 hover:-translate-y-1">
                <div class="w-12 h-12 rounded-xl bg-blue-950/80 border border-blue-700/50 flex items-center justify-center text-blue-400 font-bold mb-4">
                    <?php 
                        $icon = '📦';
                        if (stripos($cat['category_name'], 'elect') !== false) $icon = '💻';
                        elseif (stripos($cat['category_name'], 'furn') !== false) $icon = '🛋️';
                        elseif (stripos($cat['category_name'], 'vehic') !== false) $icon = '🚗';
                        echo $icon;
                    ?>
                </div>
                <h3 class="text-lg font-bold text-white"><?= htmlspecialchars($cat['category_name']) ?></h3>
                <p class="text-xs text-slate-400 mt-2 line-clamp-2 leading-relaxed">
                    <?= htmlspecialchars($cat['description'] ?? 'Browse verified products listed under this category.') ?>
                </p>
                <div class="mt-6 pt-4 border-t border-slate-800">
                    <a href="<?= base_url('renter/search.php?category=' . $cat['category_id']) ?>" 
                       class="text-xs font-semibold text-blue-400 hover:text-blue-300 flex items-center space-x-1">
                        <span>Browse category</span>
                        <span>&rarr;</span>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- Framework Dual Architecture Preview -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 mb-10">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
        <!-- Owner Framework -->
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 p-8 rounded-2xl border border-slate-800">
            <span class="text-xs font-bold uppercase tracking-wider text-blue-400">For Asset Owners</span>
            <h3 class="text-2xl font-bold text-white mt-2">Monetize Your Idle Items</h3>
            <p class="text-sm text-slate-400 mt-3 leading-relaxed">
                List cameras, laptops, vehicles, or furniture. Set custom daily rates, security deposits, and availability with automated request management and deposit-backed protection.
            </p>
            <ul class="mt-4 space-y-2 text-xs text-slate-300">
                <li class="flex items-center space-x-2">
                    <span class="text-blue-400 font-bold">✓</span>
                    <span>Multi-image uploads with magic-byte verification</span>
                </li>
                <li class="flex items-center space-x-2">
                    <span class="text-blue-400 font-bold">✓</span>
                    <span>Real-time earnings dashboard and request approval</span>
                </li>
                <li class="flex items-center space-x-2">
                    <span class="text-blue-400 font-bold">✓</span>
                    <span>Damage fine & late return protection</span>
                </li>
            </ul>
        </div>

        <!-- Renter Framework -->
        <div class="bg-gradient-to-br from-slate-900 to-slate-950 p-8 rounded-2xl border border-slate-800">
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">For Borrowers</span>
            <h3 class="text-2xl font-bold text-white mt-2">Rent Without the Overhead</h3>
            <p class="text-sm text-slate-400 mt-3 leading-relaxed">
                Access high-value assets whenever you need them. Transparent daily pricing, secure deposit handling, instant booking requests, and post-rental verified reviews.
            </p>
            <ul class="mt-4 space-y-2 text-xs text-slate-300">
                <li class="flex items-center space-x-2">
                    <span class="text-indigo-400 font-bold">✓</span>
                    <span>Concurrency-safe locking preventing double booking</span>
                </li>
                <li class="flex items-center space-x-2">
                    <span class="text-indigo-400 font-bold">✓</span>
                    <span>Automated refund and deposit reconciliation</span>
                </li>
                <li class="flex items-center space-x-2">
                    <span class="text-indigo-400 font-bold">✓</span>
                    <span>Transparent reviews and dispute resolution</span>
                </li>
            </ul>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
