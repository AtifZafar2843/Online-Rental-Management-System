<?php
/**
 * Online Rental Management System (ORMS)
 * Product Details Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 5)
 * Access: Public (Guests & Registered Users)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Product.php';

$productId = (int) ($_GET['id'] ?? 0);
if ($productId <= 0) {
    set_flash('error', 'Invalid product requested.');
    header("Location: " . base_url('renter/search.php'));
    exit;
}

$product = Product::findById($productId);
if (!$product) {
    set_flash('error', 'Product not found or has been removed by the owner.');
    header("Location: " . base_url('renter/search.php'));
    exit;
}

$images = $product->getImages();
$primaryImage = $product->getPrimaryImagePath();
$reviews = $product->getReviews();
$avgRating = $product->getAverageRating();
$reviewCount = count($reviews);

$loggedIn = is_logged_in();
$userId = current_user_id();
$isProductOwner = ($loggedIn && $userId === $product->getOwnerID());

$pageTitle = htmlspecialchars($product->getTitle()) . ' — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumbs -->
    <nav class="flex text-xs text-slate-400 mb-6 space-x-2 items-center">
        <a href="<?= base_url('index.php') ?>" class="hover:text-white transition">Home</a>
        <span>/</span>
        <a href="<?= base_url('renter/search.php') ?>" class="hover:text-white transition">Catalog</a>
        <span>/</span>
        <a href="<?= base_url('renter/search.php?category=' . $product->getCategoryID()) ?>" class="hover:text-white transition">
            <?= htmlspecialchars($product->getCategoryName() ?? 'Category') ?>
        </a>
        <span>/</span>
        <span class="text-slate-200 truncate max-w-xs font-medium"><?= htmlspecialchars($product->getTitle()) ?></span>
    </nav>

    <!-- Product Showcase Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <!-- Left Column: Gallery (7 cols) -->
        <div class="lg:col-span-7 space-y-4">
            <!-- Main Featured Image -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl overflow-hidden shadow-2xl h-80 sm:h-[420px] flex items-center justify-center relative">
                <img id="mainProductImage" src="<?= base_url($primaryImage) ?>" 
                     alt="<?= htmlspecialchars($product->getTitle()) ?>" 
                     class="w-full h-full object-contain p-2 transition duration-300">

                <span class="absolute top-4 left-4 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider bg-slate-950/80 backdrop-blur-md text-blue-300 border border-slate-700 shadow">
                    <?= htmlspecialchars($product->getCategoryName() ?? 'Category') ?>
                </span>

                <span class="absolute top-4 right-4 px-2.5 py-1 rounded-md text-xs font-semibold bg-emerald-950/90 text-emerald-400 border border-emerald-700/60 shadow">
                    Condition: <?= htmlspecialchars($product->getCondition()) ?>
                </span>
            </div>

            <!-- Clickable Thumbnails Strip -->
            <?php if (count($images) > 1): ?>
                <div class="flex items-center space-x-3 overflow-x-auto pb-2">
                    <?php foreach ($images as $idx => $img): ?>
                        <button type="button" 
                                onclick="switchMainImage('<?= base_url($img['image_path']) ?>', this)" 
                                class="thumbnail-btn w-20 h-20 rounded-xl overflow-hidden border-2 <?= $idx === 0 ? 'border-blue-500 ring-2 ring-blue-500/40' : 'border-slate-800' ?> flex-shrink-0 bg-slate-950 hover:border-blue-400 transition focus:outline-none">
                            <img src="<?= base_url($img['image_path']) ?>" alt="Thumbnail" class="w-full h-full object-cover">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Detailed Specifications Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-xl mt-8">
                <h2 class="text-lg font-bold text-white tracking-tight mb-4 flex items-center space-x-2">
                    <span>📝</span>
                    <span>Product Description & Guidelines</span>
                </h2>
                <div class="text-sm text-slate-300 leading-relaxed whitespace-pre-line space-y-4">
                    <?= htmlspecialchars($product->getDescription()) ?>
                </div>

                <div class="mt-8 pt-6 border-t border-slate-800 grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-500 uppercase tracking-wider block font-semibold">Location</span>
                        <span class="text-slate-200 font-medium text-sm mt-0.5 block">📍 <?= htmlspecialchars($product->getLocation()) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-500 uppercase tracking-wider block font-semibold">Condition</span>
                        <span class="text-slate-200 font-medium text-sm mt-0.5 block">✨ <?= htmlspecialchars($product->getCondition()) ?></span>
                    </div>
                    <div>
                        <span class="text-slate-500 uppercase tracking-wider block font-semibold">Listed Since</span>
                        <span class="text-slate-200 font-medium text-sm mt-0.5 block">📅 <?= date('M Y', strtotime($product->getListedDate() ?? 'now')) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Booking Card & Owner Info (5 cols) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Pricing & Action Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-8 shadow-2xl relative overflow-hidden">
                <div class="absolute top-0 right-0 w-32 h-32 bg-blue-600/10 rounded-full blur-3xl pointer-events-none"></div>

                <h1 class="text-2xl font-bold text-white tracking-tight leading-snug">
                    <?= htmlspecialchars($product->getTitle()) ?>
                </h1>

                <!-- Reviews Rating Summary Bar -->
                <div class="flex items-center space-x-2 mt-3 text-xs">
                    <div class="flex items-center text-amber-400">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <span><?= ($s <= round($avgRating)) ? '★' : '☆' ?></span>
                        <?php endfor; ?>
                    </div>
                    <span class="text-white font-bold"><?= number_format($avgRating, 1) ?></span>
                    <span class="text-slate-500">&bull;</span>
                    <span class="text-slate-400"><?= $reviewCount ?> verified <?= $reviewCount === 1 ? 'review' : 'reviews' ?></span>
                </div>

                <!-- Price Box -->
                <div class="mt-6 p-5 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Rental Price</span>
                        <div class="text-3xl font-extrabold text-white">
                            ₹<?= number_format($product->getRentPerDay(), 2) ?>
                            <span class="text-xs font-normal text-slate-400">/ day</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Security Deposit</span>
                        <div class="text-lg font-bold text-emerald-400">
                            ₹<?= number_format($product->getSecurityDeposit(), 2) ?>
                        </div>
                        <span class="text-[10px] text-slate-400 block">(100% Refundable)</span>
                    </div>
                </div>

                <!-- Status Pill -->
                <div class="mt-4 flex items-center justify-between text-xs py-2 px-3 rounded-lg bg-slate-950/60 border border-slate-800">
                    <span class="text-slate-400">Availability Status:</span>
                    <?php 
                        $status = $product->getAvailStatus();
                        $badgeStyle = ($status === 'Available') ? 'bg-emerald-950 text-emerald-400 border-emerald-700/60' : 'bg-amber-950 text-amber-400 border-amber-700/60';
                    ?>
                    <span class="px-2.5 py-0.5 rounded-full font-semibold border <?= $badgeStyle ?>">
                        <?= htmlspecialchars($status) ?>
                    </span>
                </div>

                <!-- Booking / Action CTA Button -->
                <div class="mt-6">
                    <?php if (!$loggedIn): ?>
                        <!-- Guest Mode: Prompt Login with Redirect -->
                        <a href="<?= base_url('auth/login.php?redirect=' . urlencode('/orms/renter/product_details.php?id=' . $product->getProductID())) ?>" 
                           class="w-full block text-center py-4 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/25 transition">
                            Sign In to Rent This Item &rarr;
                        </a>
                        <p class="text-[11px] text-slate-400 text-center mt-2">
                            New user? <a href="<?= base_url('auth/register.php') ?>" class="text-blue-400 underline">Create account in 1 minute</a>
                        </p>
                    <?php elseif ($isProductOwner): ?>
                        <!-- Logged In as the Listing Owner -->
                        <div class="p-4 rounded-xl bg-blue-950/40 border border-blue-800 text-center space-y-2">
                            <span class="text-xs text-blue-300 font-semibold block">You own this product listing.</span>
                            <a href="<?= base_url('owner/edit_product.php?id=' . $product->getProductID()) ?>" 
                               class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl transition">
                                Edit Listing Details & Photos
                            </a>
                        </div>
                    <?php elseif ($status !== 'Available'): ?>
                        <button disabled 
                                class="w-full py-4 bg-slate-800 text-slate-500 font-bold text-xs uppercase tracking-wider rounded-xl cursor-not-allowed">
                            Currently <?= htmlspecialchars($status) ?>
                        </button>
                    <?php else: ?>
                        <!-- Logged In as Renter (Ready for Step 6) -->
                        <a href="<?= base_url('renter/request_rental.php?product_id=' . $product->getProductID()) ?>" 
                           class="w-full block text-center py-4 bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/25 transition">
                            Request Rental Booking &rarr;
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Trust Guarantee Badges -->
                <div class="mt-6 pt-6 border-t border-slate-800 space-y-2.5 text-xs text-slate-400">
                    <div class="flex items-center space-x-2">
                        <span class="text-emerald-400 font-bold">🛡️</span>
                        <span>Full security deposit refund upon clean return.</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-blue-400 font-bold">🔒</span>
                        <span>Verified identity documents of both parties.</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="text-indigo-400 font-bold">⚡</span>
                        <span>Concurrency-safe booking locking prevents double booking.</span>
                    </div>
                </div>
            </div>

            <!-- Owner Profile Card -->
            <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider mb-4">Lender Information</h3>
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 text-white font-extrabold text-lg flex items-center justify-center shadow">
                        <?= strtoupper(substr($product->getOwnerName() ?? 'O', 0, 1)) ?>
                    </div>
                    <div>
                        <div class="font-bold text-white text-sm"><?= htmlspecialchars($product->getOwnerName() ?? 'Owner') ?></div>
                        <div class="flex items-center space-x-1.5 text-xs text-slate-400 mt-0.5">
                            <span class="text-amber-400">★</span>
                            <span class="text-slate-200 font-semibold"><?= number_format($product->getOwnerRating(), 2) ?></span>
                            <span>&bull;</span>
                            <span class="text-emerald-400 font-semibold">Verified Owner</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Verified Customer Reviews Section -->
    <div class="mt-16 bg-slate-900 border border-slate-800 rounded-2xl p-6 sm:p-10 shadow-xl">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-slate-800 gap-4">
            <div>
                <h2 class="text-xl font-bold text-white tracking-tight flex items-center space-x-2">
                    <span>⭐</span>
                    <span>Verified Customer Reviews</span>
                </h2>
                <p class="text-xs text-slate-400 mt-0.5">Feedback from renters who completed rentals of this product</p>
            </div>
            <div class="flex items-center space-x-3 bg-slate-950 px-4 py-2 rounded-xl border border-slate-800">
                <div class="text-2xl font-bold text-white"><?= number_format($avgRating, 1) ?></div>
                <div class="text-xs text-slate-400 leading-tight">
                    <div class="text-amber-400 font-bold">★ ★ ★ ★ ★</div>
                    <span>Based on <?= $reviewCount ?> reviews</span>
                </div>
            </div>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="py-12 text-center">
                <span class="text-3xl block mb-2">💬</span>
                <p class="text-sm font-semibold text-slate-300">No reviews yet for this product</p>
                <p class="text-xs text-slate-500 mt-1">Be the first to rent and leave feedback for this item.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-slate-800">
                <?php foreach ($reviews as $rev): ?>
                    <div class="py-6">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-white text-xs"><?= htmlspecialchars($rev['reviewer_name']) ?></span>
                                <span class="px-2 py-0.5 rounded bg-emerald-950 text-emerald-400 text-[10px] font-semibold">Verified Renter</span>
                            </div>
                            <span class="text-[11px] text-slate-500"><?= date('d M Y', strtotime($rev['review_date'])) ?></span>
                        </div>
                        <div class="flex items-center text-amber-400 text-xs mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span><?= ($i <= (int)$rev['rating']) ? '★' : '☆' ?></span>
                            <?php endfor; ?>
                        </div>
                        <p class="text-xs text-slate-300 leading-relaxed"><?= htmlspecialchars($rev['comment'] ?? 'No comment provided.') ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function switchMainImage(src, buttonElement) {
    const mainImg = document.getElementById('mainProductImage');
    if (mainImg) {
        mainImg.style.opacity = '0.5';
        setTimeout(() => {
            mainImg.src = src;
            mainImg.style.opacity = '1';
        }, 150);
    }
    document.querySelectorAll('.thumbnail-btn').forEach(btn => {
        btn.classList.remove('border-blue-500', 'ring-2', 'ring-blue-500/40');
        btn.classList.add('border-slate-800');
    });
    buttonElement.classList.remove('border-slate-800');
    buttonElement.classList.add('border-blue-500', 'ring-2', 'ring-blue-500/40');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
