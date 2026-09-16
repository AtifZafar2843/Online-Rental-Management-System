<?php
/**
 * Online Rental Management System (ORMS)
 * Main Application Landing Page — Brand Board Design System
 */

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

$pdo = Database::getInstance()->getConnection();

// Fetch categories from DB with live product counts
$catStmt = $pdo->query("
    SELECT c.category_id, c.category_name, c.description, COUNT(p.product_id) as item_count 
    FROM `CATEGORY` c
    LEFT JOIN `PRODUCT` p ON c.category_id = p.category_id AND p.avail_status = 'Available'
    GROUP BY c.category_id 
    ORDER BY item_count DESC, c.category_name ASC
");
$categories = $catStmt->fetchAll();

// Fetch Featured / Popular Listings for "Popular Near You" section
$prodStmt = $pdo->query("
    SELECT 
        p.product_id, p.title, p.rent_per_day, p.security_deposit, p.location, p.avail_status, p.condition,
        c.category_name,
        u.name as owner_name,
        COALESCE(pi.image_path, 'assets/img/no-image.svg') as image_path,
        COALESCE(AVG(r.rating), 4.9) as avg_rating,
        COUNT(r.review_id) as review_count
    FROM `PRODUCT` p
    JOIN `CATEGORY` c ON p.category_id = c.category_id
    JOIN `USER` u ON p.owner_id = u.user_id
    LEFT JOIN `PRODUCT_IMAGES` pi ON p.product_id = pi.product_id AND pi.is_primary = 1
    LEFT JOIN `RENTAL_REQUEST` rr ON p.product_id = rr.product_id
    LEFT JOIN `REVIEW` r ON rr.request_id = r.request_id
    WHERE p.avail_status = 'Available'
    GROUP BY p.product_id
    ORDER BY p.product_id DESC
    LIMIT 6
");
$featuredProducts = $prodStmt->fetchAll();

// Quick platform metrics
$stats = [
    'categories' => count($categories),
    'users'      => (int) $pdo->query("SELECT COUNT(*) FROM `USER` WHERE status = 'Active'")->fetchColumn(),
    'products'   => (int) $pdo->query("SELECT COUNT(*) FROM `PRODUCT` WHERE avail_status = 'Available'")->fetchColumn()
];

$pageTitle = 'ORMS — Own less. Access more.';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Hero Section: Direction 3 — The Access Key -->
<section class="relative overflow-hidden pt-10 sm:pt-16 pb-20 lg:pb-28 bg-[#FAF8F5]">
    <!-- Ambient Organic Coral Glow in background -->
    <div class="absolute top-0 right-0 -mr-48 -mt-48 w-[600px] h-[600px] rounded-full bg-gradient-to-br from-coral-200/40 via-coral-100/20 to-transparent blur-3xl pointer-events-none"></div>
    <div class="absolute bottom-0 left-0 -ml-40 -mb-40 w-[500px] h-[500px] rounded-full bg-gradient-to-tr from-lilac-200/50 via-ivory-200/40 to-transparent blur-3xl pointer-events-none"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12 lg:gap-8 items-center">
            
            <!-- Left Hero Content -->
            <div class="lg:col-span-7 space-y-6 sm:space-y-8 text-left">
                <!-- Pill Badge -->
                <div class="inline-flex items-center space-x-2 px-4 py-1.5 rounded-full bg-coral-50 border border-coral-200 text-coral text-xs font-semibold tracking-wide shadow-sm">
                    <span class="w-2 h-2 rounded-full bg-coral animate-ping"></span>
                    <span>The Access Marketplace</span>
                </div>

                <!-- Main Hero Headline -->
                <h1 class="font-display text-4xl sm:text-6xl lg:text-7xl font-extrabold text-midnight tracking-h1 leading-[1.06]">
                    Own less.<br>
                    <span class="text-coral">Access more.</span>
                </h1>

                <!-- Subheadline -->
                <p class="text-base sm:text-lg lg:text-xl text-stone-600 max-w-xl leading-relaxed font-normal">
                    Rent what you need. Earn from what you have. The smarter way to get and give, only on ORMS.
                </p>

                <!-- Prominent Pill Search Bar -->
                <div class="pt-2">
                    <form action="<?= base_url('renter/search.php') ?>" method="GET" 
                          class="flex flex-col sm:flex-row items-center bg-white p-2 sm:p-2.5 rounded-3xl sm:rounded-full shadow-soft border border-stone-200/90 focus-within:border-coral focus-within:ring-4 focus-within:ring-coral-50 transition max-w-xl">
                        <div class="flex items-center w-full px-3 py-2 sm:py-0">
                            <i class="ri-search-2-line text-stone-400 text-xl flex-shrink-0"></i>
                            <input type="text" name="query" placeholder="Search cameras, bikes, laptops, tools, furniture..." 
                                   class="w-full bg-transparent px-3 py-1.5 text-sm text-midnight placeholder-stone-400 focus:outline-none font-medium">
                        </div>
                        <button type="submit" 
                                class="w-full sm:w-auto px-7 py-3 bg-coral hover:bg-coral-600 text-white font-semibold text-sm rounded-full shadow-glow-coral flex items-center justify-center space-x-2 transition transform hover:scale-[1.02] flex-shrink-0 mt-2 sm:mt-0">
                            <span>Search</span>
                            <i class="ri-arrow-right-line"></i>
                        </button>
                    </form>
                </div>

                <!-- Trust Value Pillars (under search) -->
                <div class="flex flex-wrap items-center gap-y-3 gap-x-6 pt-2 text-xs font-medium text-stone-600">
                    <div class="flex items-center space-x-2">
                        <i class="ri-checkbox-circle-fill text-coral text-base"></i>
                        <span>Wide Selection <span class="text-stone-400 font-normal">Everyday to premium</span></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="ri-shield-check-fill text-coral text-base"></i>
                        <span>Secure Transactions <span class="text-stone-400 font-normal">Safe & reliable</span></span>
                    </div>
                    <div class="flex items-center space-x-2">
                        <i class="ri-user-smile-fill text-coral text-base"></i>
                        <span>Verified Users <span class="text-stone-400 font-normal">Trusted community</span></span>
                    </div>
                </div>

                <!-- Dual Quick Action Buttons -->
                <div class="pt-4 flex flex-wrap gap-4 items-center">
                    <a href="<?= base_url('renter/search.php') ?>" 
                       class="inline-flex items-center justify-center px-8 py-3.5 rounded-full bg-coral hover:bg-coral-600 text-white font-semibold text-sm shadow-glow-coral transition transform hover:-translate-y-0.5">
                        <span>Rent Something</span>
                        <i class="ri-arrow-right-line ml-2"></i>
                    </a>
                    <a href="<?= is_logged_in() ? base_url('owner/add_product.php') : base_url('auth/login.php') ?>" 
                       class="inline-flex items-center justify-center px-8 py-3.5 rounded-full border-2 border-coral text-coral hover:bg-coral-50 font-semibold text-sm transition transform hover:-translate-y-0.5">
                        <i class="ri-add-line mr-1.5 text-base"></i>
                        <span>List Something +</span>
                    </a>
                </div>
            </div>

            <!-- Right Hero Visual: Organic Curved Brand Composition -->
            <div class="lg:col-span-5 relative">
                <!-- Organic Coral SVG Fluid Backdrop -->
                <div class="relative w-full aspect-square max-w-lg mx-auto flex items-center justify-center">
                    <svg viewBox="0 0 500 500" class="w-full h-full drop-shadow-xl" xmlns="http://www.w3.org/2000/svg">
                        <defs>
                            <linearGradient id="coralGrad" x1="0%" y1="0%" x2="100%" y2="100%">
                                <stop offset="0%" stop-color="#FF5A5F" />
                                <stop offset="100%" stop-color="#FF8A8E" />
                            </linearGradient>
                        </defs>
                        <!-- Smooth Organic Curve Blob matching Brand Board -->
                        <path fill="url(#coralGrad)" d="M421.5,313Q382,376,316,423.5Q250,471,180.5,431Q111,391,73.5,320.5Q36,250,68.5,178Q101,106,175.5,67.5Q250,29,328.5,62.5Q407,96,434,173Q461,250,421.5,313Z" />
                    </svg>

                    <!-- Interactive Floating Feature Cards -->
                    <!-- Card 1: Camera Equipment -->
                    <div class="absolute top-4 left-6 sm:left-12 bg-white/95 backdrop-blur-md p-3.5 rounded-3xl shadow-lg border border-white flex items-center space-x-3 transform -rotate-3 hover:rotate-0 transition duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-coral-50 text-coral flex items-center justify-center text-2xl">
                            <i class="ri-camera-lens-fill"></i>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-coral tracking-wider">Electronics</span>
                            <h4 class="font-display font-bold text-xs text-midnight">Sony Alpha A7 III</h4>
                            <p class="text-[11px] font-semibold text-stone-500">₹950 / day</p>
                        </div>
                    </div>

                    <!-- Card 2: Adventure Gear (Tent / Bike) -->
                    <div class="absolute bottom-6 right-4 sm:right-10 bg-white/95 backdrop-blur-md p-3.5 rounded-3xl shadow-lg border border-white flex items-center space-x-3 transform rotate-3 hover:rotate-0 transition duration-300">
                        <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl">
                            <i class="ri-riding-line"></i>
                        </div>
                        <div>
                            <span class="text-[10px] uppercase font-bold text-emerald-600 tracking-wider">Mobility</span>
                            <h4 class="font-display font-bold text-xs text-midnight">Trek Mountain Bike</h4>
                            <p class="text-[11px] font-semibold text-stone-500">₹300 / day</p>
                        </div>
                    </div>

                    <!-- Card 3: Trust & Escrow Guarantee Badge -->
                    <div class="absolute top-1/2 -right-4 sm:right-2 transform -translate-y-1/2 bg-midnight text-white px-4 py-3 rounded-2xl shadow-xl flex items-center space-x-3">
                        <div class="w-9 h-9 rounded-full bg-coral flex items-center justify-center text-white text-lg">
                            <i class="ri-shield-check-line"></i>
                        </div>
                        <div>
                            <div class="flex items-center space-x-1 text-amber-400 text-xs">
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                                <i class="ri-star-fill"></i>
                            </div>
                            <span class="text-xs font-semibold">100% Escrow Protected</span>
                        </div>
                    </div>

                    <!-- Central Access Key Emblem -->
                    <div class="absolute w-24 h-24 rounded-full bg-white/95 shadow-2xl flex items-center justify-center border-4 border-white/80 transform hover:scale-105 transition">
                        <img src="<?= base_url('assets/img/ORMS Icon.png') ?>" alt="ORMS" class="w-16 h-16 object-contain">
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Popular Categories Section -->
<section class="py-16 bg-white border-y border-stone-100">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-coral">Browse by Category</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-midnight tracking-h2 mt-1">
                    Popular Categories
                </h2>
            </div>
            <a href="<?= base_url('renter/search.php') ?>" class="text-sm font-semibold text-coral hover:text-coral-600 flex items-center group">
                <span>View All Categories</span>
                <i class="ri-arrow-right-line ml-1 transform group-hover:translate-x-1 transition"></i>
            </a>
        </div>

        <!-- Categories Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 sm:gap-6">
            <?php 
            $catIcons = [
                'electronics' => 'ri-macbook-line',
                'vehicles'    => 'ri-riding-line',
                'furniture'   => 'ri-sofa-line',
                'sports'      => 'ri-football-line',
                'fashion'     => 'ri-t-shirt-2-line',
                'tools'       => 'ri-tools-line'
            ];

            foreach ($categories as $idx => $cat): 
                $cname = strtolower($cat['category_name']);
                $iconClass = 'ri-box-3-line';
                foreach ($catIcons as $key => $cls) {
                    if (str_contains($cname, $key)) {
                        $iconClass = $cls;
                        break;
                    }
                }
            ?>
            <a href="<?= base_url('renter/search.php?category=' . $cat['category_id']) ?>" 
               class="flex flex-col items-center justify-center p-6 rounded-3xl bg-[#FAF8F5] border border-stone-200/80 hover:border-coral hover:bg-coral-50/50 hover:shadow-soft transition-all duration-200 group text-center">
                <div class="w-14 h-14 rounded-2xl bg-white text-stone-700 group-hover:bg-coral group-hover:text-white shadow-sm flex items-center justify-center text-2xl mb-3.5 transition duration-200">
                    <i class="<?= $iconClass ?>"></i>
                </div>
                <h3 class="font-display font-bold text-sm text-midnight group-hover:text-coral transition">
                    <?= htmlspecialchars($cat['category_name']) ?>
                </h3>
                <span class="text-xs text-stone-400 font-medium mt-1">
                    <?= $cat['item_count'] ?> <?= $cat['item_count'] === 1 ? 'item' : 'items' ?>
                </span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- "Popular Near You" / Featured Listings Section -->
<section class="py-20 bg-[#FAF8F5]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-end justify-between mb-10">
            <div>
                <span class="text-xs font-bold uppercase tracking-wider text-coral">Verified Equipment</span>
                <h2 class="font-display text-2xl sm:text-3xl font-extrabold text-midnight tracking-h2 mt-1">
                    Popular Near You
                </h2>
            </div>
            <a href="<?= base_url('renter/search.php') ?>" class="text-sm font-semibold text-coral hover:text-coral-600 flex items-center group">
                <span>Explore All <?= $stats['products'] ?> Items</span>
                <i class="ri-arrow-right-line ml-1 transform group-hover:translate-x-1 transition"></i>
            </a>
        </div>

        <!-- Product Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-8">
            <?php if (empty($featuredProducts)): ?>
                <div class="col-span-full py-16 text-center text-stone-400">
                    <i class="ri-inbox-line text-4xl mb-2 block"></i>
                    <p class="font-medium">No products currently listed. Be the first to list an item!</p>
                </div>
            <?php else: ?>
                <?php foreach ($featuredProducts as $item): ?>
                    <div class="bg-white rounded-3xl overflow-hidden border border-stone-200/80 hover:border-coral-300 hover:shadow-soft transition duration-300 flex flex-col group">
                        <!-- Image Container -->
                        <div class="relative aspect-video w-full overflow-hidden bg-stone-100">
                            <img src="<?= base_url(htmlspecialchars($item['image_path'])) ?>" 
                                 alt="<?= htmlspecialchars($item['title']) ?>" 
                                 class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                            
                            <!-- Category Badge -->
                            <div class="absolute top-3.5 left-3.5 bg-white/90 backdrop-blur-md px-3 py-1 rounded-full text-xs font-semibold text-midnight shadow-sm">
                                <?= htmlspecialchars($item['category_name']) ?>
                            </div>

                            <!-- Available Pill -->
                            <div class="absolute top-3.5 right-3.5 bg-emerald-500/90 text-white px-2.5 py-0.5 rounded-full text-[11px] font-bold shadow-sm">
                                Available
                            </div>
                        </div>

                        <!-- Card Content -->
                        <div class="p-6 flex-1 flex flex-col justify-between">
                            <div>
                                <!-- Owner & Trust Badge -->
                                <div class="flex items-center justify-between text-xs text-stone-500 mb-2">
                                    <span class="inline-flex items-center text-emerald-600 font-semibold bg-emerald-50 px-2 py-0.5 rounded-full">
                                        <i class="ri-shield-check-line mr-1"></i> Verified Owner
                                    </span>
                                    <span class="flex items-center text-amber-500 font-bold">
                                        <i class="ri-star-fill mr-1"></i> <?= number_format((float)$item['avg_rating'], 1) ?>
                                    </span>
                                </div>

                                <!-- Title -->
                                <h3 class="font-display font-bold text-base text-midnight line-clamp-1 group-hover:text-coral transition">
                                    <a href="<?= base_url('renter/product_details.php?id=' . $item['product_id']) ?>">
                                        <?= htmlspecialchars($item['title']) ?>
                                    </a>
                                </h3>

                                <!-- Location -->
                                <p class="text-xs text-stone-400 mt-1.5 flex items-center">
                                    <i class="ri-map-pin-line text-stone-400 mr-1"></i>
                                    <?= htmlspecialchars($item['location'] ?: 'Bangalore, India') ?>
                                </p>
                            </div>

                            <!-- Pricing & Action Footer -->
                            <div class="pt-5 mt-5 border-t border-stone-100 flex items-center justify-between">
                                <div>
                                    <span class="text-xs text-stone-400 block -mb-0.5">Rental Rate</span>
                                    <span class="font-display text-xl font-bold text-midnight">₹ <?= number_format((float)$item['rent_per_day']) ?></span>
                                    <span class="text-xs text-stone-500 font-normal">/ day</span>
                                </div>

                                <a href="<?= base_url('renter/product_details.php?id=' . $item['product_id']) ?>" 
                                   class="px-5 py-2.5 bg-coral hover:bg-coral-600 text-white text-xs font-semibold rounded-full shadow-sm hover:shadow-glow-coral transition transform hover:-translate-y-0.5">
                                    Rent Now &rarr;
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</section>

<!-- Brand Story & 4 Pillars Section: Direction 3 — The Access Key -->
<section class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="text-center max-w-3xl mx-auto space-y-4 mb-16">
            <span class="text-xs font-bold uppercase tracking-wider text-coral">Brand Philosophy</span>
            <h2 class="font-display text-3xl sm:text-4xl lg:text-5xl font-extrabold text-midnight tracking-h2">
                More access. Less ownership.
            </h2>
            <p class="text-base sm:text-lg text-stone-600 leading-relaxed font-normal pt-2">
                ORMS is a peer-to-peer rental marketplace where people can list what they have and rent what they need. It's not just about renting things — it's about unlocking possibilities. From everyday essentials to special occasion items, ORMS connects people and products, making access simple, affordable, and sustainable.
            </p>
        </div>

        <!-- 4 Pillars Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="p-8 rounded-3xl bg-[#FAF8F5] border border-stone-200/80 hover:border-coral-300 transition duration-300">
                <div class="w-12 h-12 rounded-2xl bg-coral-50 text-coral flex items-center justify-center text-2xl mb-5">
                    <i class="ri-key-2-line"></i>
                </div>
                <h3 class="font-display font-bold text-lg text-midnight mb-2">Open Access</h3>
                <p class="text-xs text-stone-500 leading-relaxed">
                    Unlock premium items on demand without high capital expenses or storage burdens.
                </p>
            </div>

            <div class="p-8 rounded-3xl bg-[#FAF8F5] border border-stone-200/80 hover:border-coral-300 transition duration-300">
                <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-2xl mb-5">
                    <i class="ri-group-line"></i>
                </div>
                <h3 class="font-display font-bold text-lg text-midnight mb-2">Stronger Community</h3>
                <p class="text-xs text-stone-500 leading-relaxed">
                    Connecting verified neighbors in a cooperative sharing economy built on transparent ratings.
                </p>
            </div>

            <div class="p-8 rounded-3xl bg-[#FAF8F5] border border-stone-200/80 hover:border-coral-300 transition duration-300">
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-2xl mb-5">
                    <i class="ri-shield-check-line"></i>
                </div>
                <h3 class="font-display font-bold text-lg text-midnight mb-2">Trusted Transactions</h3>
                <p class="text-xs text-stone-500 leading-relaxed">
                    Deposit-backed escrow, automated condition audits, and impartial dispute adjudication.
                </p>
            </div>

            <div class="p-8 rounded-3xl bg-[#FAF8F5] border border-stone-200/80 hover:border-coral-300 transition duration-300">
                <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-2xl mb-5">
                    <i class="ri-leaf-line"></i>
                </div>
                <h3 class="font-display font-bold text-lg text-midnight mb-2">Sustainable Choices</h3>
                <p class="text-xs text-stone-500 leading-relaxed">
                    Maximize asset utilization, reduce manufacturing waste, and support a greener planet.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Dual Role Banner: Rent Something & List Something -->
<section class="py-16 bg-[#FAF8F5]">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="bg-midnight rounded-3xl p-8 sm:p-14 text-white relative overflow-hidden shadow-2xl">
            <div class="absolute -right-20 -bottom-20 w-96 h-96 rounded-full bg-coral/20 blur-3xl pointer-events-none"></div>
            
            <div class="relative z-10 grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
                <div class="lg:col-span-8 space-y-3 text-left">
                    <span class="text-xs font-bold text-coral uppercase tracking-wider">Start Today</span>
                    <h2 class="font-display text-3xl sm:text-4xl font-extrabold text-white tracking-h2">
                        Ready to access everything you need?
                    </h2>
                    <p class="text-sm text-stone-400 max-w-xl leading-relaxed">
                        Join thousands of verified owners and renters sharing electronics, tools, furniture, and vehicles across the platform.
                    </p>
                </div>
                <div class="lg:col-span-4 flex flex-col sm:flex-row lg:flex-col gap-3.5 justify-center lg:items-end">
                    <a href="<?= base_url('renter/search.php') ?>" 
                       class="px-8 py-3.5 bg-coral hover:bg-coral-600 text-white font-semibold text-sm rounded-full shadow-glow-coral text-center transition">
                        Rent Something &rarr;
                    </a>
                    <a href="<?= is_logged_in() ? base_url('owner/add_product.php') : base_url('auth/login.php') ?>" 
                       class="px-8 py-3.5 bg-white/10 hover:bg-white/20 text-white border border-white/30 font-semibold text-sm rounded-full text-center transition">
                        List Something +
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
