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

$pageTitle = 'Browse Rental Catalog — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Header Banner -->
    <div class="mb-8">
        <h1 class="text-3xl font-extrabold text-white tracking-tight">Explore Rental Marketplace</h1>
        <p class="mt-1 text-sm text-slate-400">Discover verified electronics, furniture, and vehicles available for short & long-term rent.</p>
    </div>

    <!-- Search & Filter Controls Card -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl mb-10">
        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET" class="space-y-4">
            <!-- Search Bar Row -->
            <div class="flex flex-col sm:flex-row gap-3">
                <div class="relative flex-grow">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center text-slate-500">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </span>
                    <input type="text" name="query" value="<?= htmlspecialchars($query) ?>" 
                           placeholder="Search cameras, laptops, mountain bikes, sofas..." 
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-10 pr-4 py-3 text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs uppercase tracking-wider rounded-xl shadow-lg shadow-blue-500/20 transition flex items-center justify-center space-x-2">
                    <span>Search</span>
                </button>
                <?php if (!empty($query) || $categoryId > 0 || $minPrice !== null || $maxPrice !== null || !empty($location) || !empty($condition)): ?>
                    <a href="<?= base_url('renter/search.php') ?>" class="px-4 py-3 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-xl text-xs font-semibold flex items-center justify-center transition">
                        Reset
                    </a>
                <?php endif; ?>
            </div>

            <!-- Detailed Filters Grid -->
            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3 pt-3 border-t border-slate-800/80 text-xs">
                <!-- Category -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Category</label>
                    <select name="category" onchange="this.form.submit()" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-blue-500">
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
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Min Rent (₹)</label>
                    <input type="number" name="min_price" step="50" min="0" value="<?= $minPrice !== null ? htmlspecialchars((string)$minPrice) : '' ?>" placeholder="Min ₹" 
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-blue-500">
                </div>

                <!-- Price Max -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Max Rent (₹)</label>
                    <input type="number" name="max_price" step="50" min="0" value="<?= $maxPrice !== null ? htmlspecialchars((string)$maxPrice) : '' ?>" placeholder="Max ₹" 
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-blue-500">
                </div>

                <!-- Location -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Location / Area</label>
                    <input type="text" name="location" value="<?= htmlspecialchars($location) ?>" placeholder="e.g. Indiranagar" 
                           class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-blue-500">
                </div>

                <!-- Sort By -->
                <div>
                    <label class="block text-[11px] font-semibold text-slate-400 uppercase tracking-wider mb-1">Sort By</label>
                    <select name="sort" onchange="this.form.submit()" class="w-full bg-slate-950 border border-slate-800 rounded-xl p-2.5 text-white focus:outline-none focus:border-blue-500">
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
        <p class="text-xs text-slate-400">
            Showing <strong class="text-white"><?= count($searchResults) ?></strong> of <strong class="text-white"><?= $totalProducts ?></strong> available products
        </p>
    </div>

    <!-- Product Grid -->
    <?php if (empty($searchResults)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center">
            <div class="w-16 h-16 rounded-2xl bg-slate-800 mx-auto flex items-center justify-center text-3xl mb-4">
                🔍
            </div>
            <h3 class="text-lg font-bold text-white">No products found matching your criteria</h3>
            <p class="text-xs text-slate-400 mt-1 mb-6 max-w-md mx-auto">
                Try loosening your filters, checking different categories, or searching with broader keywords.
            </p>
            <a href="<?= base_url('renter/search.php') ?>" class="inline-block px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition">
                Clear All Filters
            </a>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($searchResults as $item): 
                $prod = $item['product'];
                $imagePath = $item['primary_image'];
            ?>
                <div class="bg-slate-900 border border-slate-800 hover:border-slate-700 rounded-2xl overflow-hidden shadow-lg hover:shadow-2xl transition duration-200 flex flex-col group">
                    <!-- Product Image Thumbnail -->
                    <div class="relative h-48 bg-slate-950 overflow-hidden">
                        <img src="<?= base_url($imagePath) ?>" alt="<?= htmlspecialchars($prod->getTitle()) ?>" 
                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300">
                        
                        <!-- Badges -->
                        <span class="absolute top-3 left-3 px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-slate-900/80 backdrop-blur-md text-blue-300 border border-slate-700 shadow">
                            <?= htmlspecialchars($prod->getCategoryName() ?? 'Category') ?>
                        </span>

                        <span class="absolute top-3 right-3 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-950/90 text-emerald-400 border border-emerald-700/60 shadow">
                            <?= htmlspecialchars($prod->getCondition()) ?>
                        </span>
                    </div>

                    <!-- Card Body -->
                    <div class="p-5 flex-grow flex flex-col justify-between">
                        <div>
                            <h3 class="font-bold text-white text-base line-clamp-1 group-hover:text-blue-400 transition">
                                <a href="<?= base_url('renter/product_details.php?id=' . $prod->getProductID()) ?>">
                                    <?= htmlspecialchars($prod->getTitle()) ?>
                                </a>
                            </h3>

                            <p class="text-xs text-slate-400 mt-1 line-clamp-2 leading-relaxed">
                                <?= htmlspecialchars($prod->getDescription()) ?>
                            </p>

                            <div class="mt-3 flex items-center justify-between text-xs text-slate-400">
                                <span class="flex items-center space-x-1">
                                    <span>📍</span>
                                    <span class="line-clamp-1"><?= htmlspecialchars($prod->getLocation()) ?></span>
                                </span>
                                <span class="flex items-center space-x-1">
                                    <span class="text-amber-400">★</span>
                                    <span class="text-slate-300 font-semibold"><?= number_format($prod->getOwnerRating(), 1) ?></span>
                                </span>
                            </div>
                        </div>

                        <!-- Card Footer -->
                        <div class="mt-5 pt-4 border-t border-slate-800 flex items-center justify-between">
                            <div>
                                <span class="text-[10px] uppercase tracking-wider text-slate-500 block">Daily Rent</span>
                                <div class="text-lg font-extrabold text-white">
                                    ₹<?= number_format($prod->getRentPerDay(), 0) ?><span class="text-xs font-normal text-slate-400">/day</span>
                                </div>
                            </div>
                            <a href="<?= base_url('renter/product_details.php?id=' . $prod->getProductID()) ?>" 
                               class="px-4 py-2 bg-blue-600/90 hover:bg-blue-600 text-white text-xs font-semibold rounded-xl shadow transition">
                                View Details &rarr;
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
                       class="px-3.5 py-2 rounded-xl text-xs font-bold transition <?= $i === $page ? 'bg-blue-600 text-white' : 'bg-slate-900 border border-slate-800 text-slate-400 hover:text-white' ?>">
                        <?= $i ?>
                    </a>
                <?php endfor; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
