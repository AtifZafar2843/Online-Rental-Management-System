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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
    <!-- Breadcrumbs -->
    <nav class="flex text-xs text-stone-500 mb-8 space-x-2 items-center">
        <a href="<?= base_url('index.php') ?>" class="hover:text-coral transition">Home</a>
        <span>/</span>
        <a href="<?= base_url('renter/search.php') ?>" class="hover:text-coral transition">Catalog</a>
        <span>/</span>
        <a href="<?= base_url('renter/search.php?category=' . $product->getCategoryID()) ?>" class="hover:text-coral transition">
            <?= htmlspecialchars($product->getCategoryName() ?? 'Category') ?>
        </a>
        <span>/</span>
        <span class="text-midnight truncate max-w-xs font-semibold"><?= htmlspecialchars($product->getTitle()) ?></span>
    </nav>

    <!-- Product Showcase Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
        <!-- Left Column: Gallery & Description (7 cols) -->
        <div class="lg:col-span-7 space-y-6">
            <!-- Main Featured Image -->
            <div class="bg-white border border-stone-200/90 rounded-3xl overflow-hidden shadow-soft h-80 sm:h-[420px] flex items-center justify-center relative p-4">
                <img id="mainProductImage" src="<?= base_url($primaryImage) ?>" 
                     alt="<?= htmlspecialchars($product->getTitle()) ?>" 
                     class="w-full h-full object-contain transition duration-300">

                <span class="absolute top-4 left-4 px-3.5 py-1 rounded-full text-xs font-semibold bg-white/95 backdrop-blur-md text-midnight border border-stone-200 shadow-sm">
                    <?= htmlspecialchars($product->getCategoryName() ?? 'Category') ?>
                </span>

                <span class="absolute top-4 right-4 px-3 py-1 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 shadow-sm">
                    Condition: <?= htmlspecialchars($product->getCondition()) ?>
                </span>
            </div>

            <!-- Clickable Thumbnails Strip -->
            <?php if (count($images) > 1): ?>
                <div class="flex items-center space-x-3 overflow-x-auto pb-2">
                    <?php foreach ($images as $idx => $img): ?>
                        <button type="button" 
                                onclick="switchMainImage('<?= base_url($img['image_path']) ?>', this)" 
                                class="thumbnail-btn w-20 h-20 rounded-2xl overflow-hidden border-2 <?= $idx === 0 ? 'border-coral ring-2 ring-coral/30' : 'border-stone-200' ?> flex-shrink-0 bg-white hover:border-coral transition focus:outline-none p-1">
                            <img src="<?= base_url($img['image_path']) ?>" alt="Thumbnail" class="w-full h-full object-cover rounded-xl">
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Detailed Specifications Card -->
            <div class="bg-white border border-stone-200/90 rounded-3xl p-6 sm:p-8 shadow-soft">
                <h2 class="font-display text-lg font-bold text-midnight tracking-tight mb-4 flex items-center space-x-2">
                    <i class="ri-file-list-3-line text-coral text-xl"></i>
                    <span>Product Description & Guidelines</span>
                </h2>
                <div class="text-sm text-stone-600 leading-relaxed whitespace-pre-line space-y-4">
                    <?= htmlspecialchars($product->getDescription()) ?>
                </div>

                <div class="mt-8 pt-6 border-t border-stone-100 grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-stone-400 uppercase tracking-wider block font-semibold text-[11px]">Location</span>
                        <span class="text-midnight font-medium text-sm mt-0.5 block flex items-center">
                            <i class="ri-map-pin-line text-coral mr-1"></i>
                            <?= htmlspecialchars($product->getLocation()) ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-stone-400 uppercase tracking-wider block font-semibold text-[11px]">Condition</span>
                        <span class="text-midnight font-medium text-sm mt-0.5 block flex items-center">
                            <i class="ri-sparkling-fill text-amber-400 mr-1"></i>
                            <?= htmlspecialchars($product->getCondition()) ?>
                        </span>
                    </div>
                    <div>
                        <span class="text-stone-400 uppercase tracking-wider block font-semibold text-[11px]">Listed Since</span>
                        <span class="text-midnight font-medium text-sm mt-0.5 block flex items-center">
                            <i class="ri-calendar-line text-stone-400 mr-1"></i>
                            <?= date('M Y', strtotime($product->getListedDate() ?? 'now')) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Booking Card & Owner Info (5 cols) -->
        <div class="lg:col-span-5 space-y-6">
            <!-- Pricing & Action Card -->
            <div class="bg-white border border-stone-200/90 rounded-3xl p-6 sm:p-8 shadow-soft relative overflow-hidden">
                <h1 class="font-display text-2xl sm:text-3xl font-bold text-midnight tracking-tight leading-snug">
                    <?= htmlspecialchars($product->getTitle()) ?>
                </h1>

                <!-- Reviews Rating Summary Bar -->
                <div class="flex items-center space-x-2 mt-3 text-xs">
                    <div class="flex items-center text-amber-400">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i class="<?= ($s <= round($avgRating)) ? 'ri-star-fill' : 'ri-star-line text-stone-300' ?> text-sm"></i>
                        <?php endfor; ?>
                    </div>
                    <span class="text-midnight font-bold text-sm"><?= number_format($avgRating, 1) ?></span>
                    <span class="text-stone-300">&bull;</span>
                    <span class="text-stone-500 font-medium"><?= $reviewCount ?> verified <?= $reviewCount === 1 ? 'review' : 'reviews' ?></span>
                </div>

                <!-- Price Box -->
                <div class="mt-6 p-5 rounded-2xl bg-[#FAF8F5] border border-stone-200/80 flex items-center justify-between">
                    <div>
                        <span class="text-[10px] uppercase font-bold text-stone-400 tracking-wider block">Rental Rate</span>
                        <div class="text-3xl font-display font-extrabold text-midnight">
                            ₹<?= number_format($product->getRentPerDay(), 2) ?>
                            <span class="text-xs font-normal text-stone-500">/ day</span>
                        </div>
                    </div>
                    <div class="text-right">
                        <span class="text-[10px] uppercase font-bold text-stone-400 tracking-wider block">Security Deposit</span>
                        <div class="text-lg font-bold text-emerald-600">
                            ₹<?= number_format($product->getSecurityDeposit(), 2) ?>
                        </div>
                        <span class="text-[10px] text-stone-400 block font-medium">(100% Refundable)</span>
                    </div>
                </div>

                <!-- Status Pill -->
                <div class="mt-4 flex items-center justify-between text-xs py-2.5 px-4 rounded-2xl bg-[#FAF8F5] border border-stone-200/80">
                    <span class="text-stone-500 font-medium">Availability Status:</span>
                    <?php 
                        $status = $product->getAvailStatus();
                        $badgeStyle = ($status === 'Available') ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-amber-50 text-amber-700 border-amber-200';
                    ?>
                    <span class="px-3 py-1 rounded-full font-semibold text-xs border <?= $badgeStyle ?>">
                        <?= htmlspecialchars($status) ?>
                    </span>
                </div>

                <!-- Booking / Action CTA Button -->
                <div class="mt-6">
                    <?php if (!$loggedIn): ?>
                        <!-- Guest Mode: Prompt Login with Redirect -->
                        <a href="<?= base_url('auth/login.php?redirect=' . urlencode('/orms/renter/product_details.php?id=' . $product->getProductID())) ?>" 
                           class="w-full block text-center py-4 bg-coral hover:bg-coral-600 text-white font-semibold text-sm rounded-full shadow-glow-coral transition transform hover:-translate-y-0.5">
                            Sign In to Rent This Item &rarr;
                        </a>
                        <p class="text-xs text-stone-400 text-center mt-2.5">
                            New user? <a href="<?= base_url('auth/register.php') ?>" class="text-coral hover:underline font-semibold">Create account in 1 minute</a>
                        </p>
                    <?php elseif ($isProductOwner): ?>
                        <!-- Logged In as the Listing Owner -->
                        <div class="p-4 rounded-2xl bg-coral-50 border border-coral-200 text-center space-y-2">
                            <span class="text-xs text-coral font-semibold block">You own this product listing.</span>
                            <a href="<?= base_url('owner/edit_product.php?id=' . $product->getProductID()) ?>" 
                               class="inline-block px-5 py-2.5 bg-coral hover:bg-coral-600 text-white text-xs font-semibold rounded-full shadow-sm transition">
                                Edit Listing Details & Photos
                            </a>
                        </div>
                    <?php elseif ($status !== 'Available'): ?>
                        <button disabled 
                                class="w-full py-4 bg-stone-200 text-stone-400 font-semibold text-sm rounded-full cursor-not-allowed">
                            Currently <?= htmlspecialchars($status) ?>
                        </button>
                    <?php else: ?>
                        <!-- Logged In as Renter -->
                        <a href="<?= base_url('renter/request_rental.php?product_id=' . $product->getProductID()) ?>" 
                           class="w-full block text-center py-4 bg-coral hover:bg-coral-600 text-white font-semibold text-sm rounded-full shadow-glow-coral transition transform hover:-translate-y-0.5">
                            Request Rental Booking &rarr;
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Trust Guarantee Badges -->
                <div class="mt-6 pt-6 border-t border-stone-100 space-y-2.5 text-xs text-stone-500">
                    <div class="flex items-center space-x-2">
                        <i class="ri-shield-check-fill text-emerald-500 text-base"></i>
                        <span>Full security deposit refund upon clean return.</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="ri-user-star-fill text-coral text-base"></i>
                        <span>Verified identity documents of both parties.</span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="ri-lock-2-fill text-blue-500 text-base"></i>
                        <span>Protected escrow accounting and dispute resolution.</span>
                    </div>
                </div>
            </div>

            <!-- Owner Profile Card -->
            <div class="bg-white border border-stone-200/90 rounded-3xl p-6 shadow-soft">
                <h3 class="text-xs font-bold text-stone-400 uppercase tracking-wider mb-4">Lender Information</h3>
                <div class="flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-2xl bg-lilac text-midnight font-display font-bold text-lg flex items-center justify-center border border-lilac-300 shadow-sm">
                        <?= strtoupper(substr($product->getOwnerName() ?? 'O', 0, 1)) ?>
                    </div>
                    <div>
                        <div class="font-display font-bold text-midnight text-base"><?= htmlspecialchars($product->getOwnerName() ?? 'Owner') ?></div>
                        <div class="flex items-center space-x-2 text-xs text-stone-500 mt-0.5">
                            <span class="flex items-center text-amber-500 font-bold">
                                <i class="ri-star-fill mr-1"></i> <?= number_format($product->getOwnerRating(), 1) ?>
                            </span>
                            <span>&bull;</span>
                            <span class="text-emerald-600 font-semibold flex items-center">
                                <i class="ri-shield-check-line mr-1"></i> Verified Owner
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Verified Customer Reviews Section -->
    <div class="mt-16 bg-white border border-stone-200/90 rounded-3xl p-6 sm:p-10 shadow-soft">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between pb-6 border-b border-stone-100 gap-4">
            <div>
                <h2 class="font-display text-xl font-bold text-midnight tracking-tight flex items-center space-x-2">
                    <i class="ri-star-smile-line text-coral text-2xl"></i>
                    <span>Verified Customer Reviews</span>
                </h2>
                <p class="text-xs text-stone-500 mt-0.5">Feedback from renters who completed rentals of this product</p>
            </div>
            <div class="flex items-center space-x-3 bg-[#FAF8F5] px-4 py-2.5 rounded-2xl border border-stone-200">
                <div class="text-2xl font-display font-bold text-midnight"><?= number_format($avgRating, 1) ?></div>
                <div class="text-xs text-stone-500 leading-tight">
                    <div class="text-amber-400 text-sm">
                        <?php for ($s = 1; $s <= 5; $s++): ?>
                            <i class="<?= ($s <= round($avgRating)) ? 'ri-star-fill' : 'ri-star-line text-stone-300' ?>"></i>
                        <?php endfor; ?>
                    </div>
                    <span>Based on <?= $reviewCount ?> reviews</span>
                </div>
            </div>
        </div>

        <?php if (empty($reviews)): ?>
            <div class="py-12 text-center">
                <i class="ri-chat-smile-2-line text-4xl text-stone-300 block mb-2"></i>
                <p class="text-sm font-semibold text-stone-600">No reviews yet for this product</p>
                <p class="text-xs text-stone-400 mt-1">Be the first to rent and leave feedback for this item.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-stone-100">
                <?php foreach ($reviews as $rev): ?>
                    <div class="py-6">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center space-x-2">
                                <span class="font-bold text-midnight text-xs"><?= htmlspecialchars($rev['reviewer_name']) ?></span>
                                <span class="px-2.5 py-0.5 rounded-full bg-emerald-50 text-emerald-700 text-[10px] font-semibold border border-emerald-200">Verified Renter</span>
                            </div>
                            <span class="text-[11px] text-stone-400"><?= date('d M Y', strtotime($rev['review_date'])) ?></span>
                        </div>
                        <div class="flex items-center text-amber-400 text-xs mb-2">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="<?= ($i <= (int)$rev['rating']) ? 'ri-star-fill' : 'ri-star-line text-stone-300' ?>"></i>
                            <?php endfor; ?>
                        </div>
                        <p class="text-xs text-stone-600 leading-relaxed"><?= htmlspecialchars($rev['comment'] ?? 'No comment provided.') ?></p>
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
        btn.classList.remove('border-coral', 'ring-2', 'ring-coral/30');
        btn.classList.add('border-stone-200');
    });
    buttonElement.classList.remove('border-stone-200');
    buttonElement.classList.add('border-coral', 'ring-2', 'ring-coral/30');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
