<?php
/**
 * Online Rental Management System (ORMS)
 * Public Product Catalog & Search/Discovery Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 6 (Step 5)
 * Access: Public (Guests & Registered Users)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Product.php';

$pdo = Database::getInstance()->getConnection();

// Fetch categories for filter dropdown
$catStmt = $pdo->query("SELECT category_id, category_name FROM `CATEGORY` ORDER BY category_name ASC");
$categories = $catStmt->fetchAll();

// Extract search and filter parameters from GET
$query = trim($_GET['query'] ?? '');
$categoryId = (int) ($_GET['category'] ?? 0);
$minPrice = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$maxPrice = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;
$location = trim($_GET['location'] ?? '');
$condition = trim($_GET['condition'] ?? '');
$sort = trim($_GET['sort'] ?? 'newest');
$page = max(1, (int)($_GET['page'] ?? 1));
$limit = 12;
$offset = ($page - 1) * $limit;

$filters = [
    'query'       => $query,
    'category_id' => $categoryId,
    'min_price'   => $minPrice,
    'max_price'   => $maxPrice,
    'location'    => $location,
    'condition'   => $condition,
    'status'      => 'Available' // Default to available items for rent
];

// Perform search
$searchResults = Product::search($filters, $sort, $limit, $offset);
$totalProducts = Product::countSearch($filters);
$totalPages = ceil($totalProducts / $limit);

$pageTitle = 'Explore Marketplace — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-14">
    <!-- Header Banner -->
    <div class="mb-8">
        <span class="text-xs font-bold uppercase tracking-wider text-coral">Browse & Rent</span>
        <h1 class="font-display text-3xl sm:text-4xl font-extrabold text-midnight tracking-h2 mt-1">
            Explore Rental Marketplace
        </h1>
        <p class="mt-1 text-sm text-stone-500">Discover verified electronics, vehicles, furniture, and tools available for flexible rental terms.</p>
    </div>

    <!-- Search & Filter Controls Card -->
    <div class="bg-white border border-stone-200/80 rounded-3xl p-6 sm:p-7 shadow-soft mb-10">
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET" class="space-y-4">
            <!-- Search Bar Row -->
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-grow">
                    <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-stone-400">
                        <i class="ri-search-2-line text-lg"></i>
                    </span>
                    <input type="text" name="query" value="<?= htmlspecialchars($query) ?>" 
                           placeholder="Search cameras, laptops, bikes, camping gear, tools, furniture..." 
                           class="w-full bg-[#FAF8F5] border border-stone-200/80 rounded-full pl-11 pr-4 py-3.5 text-sm text-midnight placeholder-stone-400 focus:outline-none focus:border-coral focus:ring-4 focus:ring-coral-50 transition font-medium">
                </div>
                <button type="submit" class="px-7 py-3.5 bg-coral hover:bg-coral-600 text-white font-semibold text-sm rounded-full shadow-glow-coral transition flex items-center justify-center space-x-2 flex-shrink-0">
                    <i class="ri-search-line"></i>
                    <span>Search</span>
                </button>
                <?php if (!empty($query) || $categoryId > 0 || $minPrice !== null || $maxPrice !== null || !empty($location) || !empty($condition)): ?>
                    <a href="<?= base_url('renter/search.php') ?>" class="px-5 py-3.5 bg-stone-100 hover:bg-stone-200 text-stone-600 rounded-full text-xs font-semibold flex items-center justify-center transition flex-shrink-0">
                        <i class="ri-refresh-line mr-1"></i> Reset
                    </a>
                <?php endif; ?>
            </div>

            <!-- Detailed Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 pt-4 border-t border-stone-100 text-xs">
                <!-- Category -->
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 uppercase tracking-wider mb-1.5">Category</label>
                    <select name="category" onchange="this.form.submit()" class="w-full bg-[#FAF8F5] border border-stone-200/80 rounded-2xl p-2.5 text-midnight font-medium focus:outline-none focus:border-coral">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['category_id'] ?>" <?= ($categoryId === (int)$cat['category_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Price Min -->
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 uppercase tracking-wider mb-1.5">Min Rent (₹)</label>
                    <input type="number" name="min_price" step="50" min="0" value="<?= $minPrice !== null ? htmlspecialchars((string)$minPrice) : '' ?>" placeholder="Min ₹" 
                           class="w-full bg-[#FAF8F5] border border-stone-200/80 rounded-2xl p-2.5 text-midnight font-medium focus:outline-none focus:border-coral">
                </div>

                <!-- Price Max -->
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 uppercase tracking-wider mb-1.5">Max Rent (₹)</label>
                    <input type="number" name="max_price" step="50" min="0" value="<?= $maxPrice !== null ? htmlspecialchars((string)$maxPrice) : '' ?>" placeholder="Max ₹" 
                           class="w-full bg-[#FAF8F5] border border-stone-200/80 rounded-2xl p-2.5 text-midnight font-medium focus:outline-none focus:border-coral">
                </div>

                <!-- Location -->
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 uppercase tracking-wider mb-1.5">Location / Area</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($location) ?>" placeholder="e.g. Indiranagar, Whitefield" 
                           class="w-full bg-[#FAF8F5] border border-stone-200/80 rounded-2xl p-2.5 text-midnight font-medium focus:outline-none focus:border-coral">
                </div>

                <!-- Sort By -->
                <div>
                    <label class="block text-[11px] font-semibold text-stone-600 uppercase tracking-wider mb-1.5">Sort By</label>
                    <select name="sort" onchange="this.form.submit()" class="w-full bg-[#FAF8F5] border border-stone-200/80 rounded-2xl p-2.5 text-midnight font-medium focus:outline-none focus:border-coral">
                        <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest First</option>
                        <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Rent: Low to High</option>
                        <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Rent: High to Low</option>
                        <option value="title_asc" <?= $sort === 'title_asc' ? 'selected' : '' ?>>Title: A to Z</option>
                    </select>
                </div>
            </div>
        </form>
    </div>

    <!-- Results Header -->
    <div class="flex items-center justify-between mb-6">
        <p class="text-xs text-stone-500 font-medium">
            Showing <strong class="text-midnight"><?= count($searchResults) ?></strong> of <strong class="text-midnight"><?= $totalProducts ?></strong> available products
        </p>
    </div>

    <!-- Product Grid -->
    <?php if (empty($searchResults)): ?>
        <div class="bg-white border border-stone-200/80 rounded-3xl p-14 text-center shadow-soft">
            <div class="w-16 h-16 rounded-2xl bg-stone-100 text-stone-400 mx-auto flex items-center justify-center text-3xl mb-4">
                <i class="ri-search-line"></i>
            </div>
            <h3 class="font-display text-lg font-bold text-midnight">No products found matching your criteria</h3>
            <p class="text-xs text-stone-500 mt-1 mb-6 max-w-md mx-auto">
                Try loosening your filters, selecting a different category, or searching with broader keywords.
            </p>
            <a href="<?= base_url('renter/search.php') ?>" class="inline-flex items-center px-6 py-3 bg-coral hover:bg-coral-600 text-white rounded-full text-xs font-semibold shadow-glow-coral transition">
                Clear All Filters
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($searchResults as $item): 
                $prod = $item['product'];
                $imagePath = $item['primary_image'];
            ?>
                <div class="bg-white border border-stone-200/80 hover:border-coral-300 rounded-3xl overflow-hidden shadow-sm hover:shadow-soft transition duration-300 flex flex-col group">
                    <!-- Product Image Thumbnail -->
                    <div class="relative h-48 bg-stone-100 overflow-hidden">
                        <img src="<?= base_url($imagePath) ?>" alt="<?= htmlspecialchars($prod->getTitle()) ?>" 
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
                        
                        <!-- Category Badge -->
                        <span class="absolute top-3 left-3 px-3 py-1 rounded-full text-[10px] font-semibold bg-white/90 backdrop-blur-md text-midnight shadow-sm">
                            <?= htmlspecialchars($prod->getCategoryName() ?? 'Category') ?>
                        </span>

                        <span class="absolute top-3 right-3 px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-emerald-500/90 text-white shadow-sm">
                            <?= htmlspecialchars($prod->getCondition()) ?>
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="p-5 flex-grow flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between text-xs text-stone-500 mb-1.5">
                                <span class="flex items-center text-amber-500 font-bold">
                                    <i class="ri-star-fill mr-1"></i> <?= number_format($prod->getOwnerRating(), 1) ?>
                                </span>
                                <span class="inline-flex items-center text-emerald-600 font-semibold text-[11px]">
                                    <i class="ri-shield-check-line mr-1"></i> Verified
                                </span>
                            </div>

                            <h3 class="font-display font-bold text-midnight text-base line-clamp-1 group-hover:text-coral transition">
                                <a href="<?= base_url('renter/product_details.php?id=' . $prod->getProductID()) ?>">
                                    <?= htmlspecialchars($prod->getTitle()) ?>
                                </a>
                            </h3>

                            <p class="text-xs text-stone-500 mt-1 line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($prod->getDescription()) ?>
                            </p>

                            <div class="mt-3 flex items-center text-xs text-stone-400">
                                <i class="ri-map-pin-line mr-1"></i>
                                <span class="line-clamp-1"><?= htmlspecialchars($prod->getLocation()) ?></span>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="mt-5 pt-4 border-t border-stone-100 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] uppercase tracking-wider text-stone-400 block -mb-0.5">Rent</span>
                                <div class="text-lg font-display font-bold text-midnight">
                                    ₹<?= number_format($prod->getRentPerDay(), 0) ?><span class="text-xs font-normal text-stone-500">/day</span>
                                </div>
                            </div>
                            <a href="<?= base_url('renter/product_details.php?id=' . $prod->getProductID()) ?>" 
                               class="px-4 py-2 bg-coral hover:bg-coral-600 text-white text-xs font-semibold rounded-full shadow-sm hover:shadow-glow-coral transition transform hover:-translate-y-0.5">
                                Rent Now &rarr;
                            </a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
            <div class="mt-12 flex justify-center space-x-2">
                <?php for ($i = 1; $i <= $totalPages; $i++): 
                    $params = $_GET;
                    $params['page'] = $i;
                    $pageUrl = '?' . http_build_query($params);
                ?>
                    <a href="<?= htmlspecialchars($pageUrl) ?>" 
                       class="w-10 h-10 rounded-full text-xs font-bold flex items-center justify-center transition <?= $i === $page ? 'bg-coral text-white shadow-glow-coral' : 'bg-white border border-stone-200 text-stone-600 hover:bg-stone-100' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
