<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Category Taxonomy Management
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Admin.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';

require_admin();

$admin = new Admin((int) current_user_id(), current_user_name(), current_user_email());
$error = '';

// Handle Add Category
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['add_category'])) {
    if (!csrf_verify()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $name = trim((string) ($_POST['category_name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $parentId = (int) ($_POST['parent_category_id'] ?? 0);
        try {
            $admin->addCategory($name, $desc ?: null, $parentId ?: null);
            set_flash('success', "Category '{$name}' created successfully.");
            header('Location: ' . base_url('admin/manage_categories.php'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

// Handle Edit Category
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['edit_category'])) {
    if (!csrf_verify()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $id = (int) ($_POST['category_id'] ?? 0);
        $name = trim((string) ($_POST['category_name'] ?? ''));
        $desc = trim((string) ($_POST['description'] ?? ''));
        $parentId = (int) ($_POST['parent_category_id'] ?? 0);
        try {
            $admin->editCategory($id, $name, $desc ?: null, $parentId ?: null);
            set_flash('success', "Category '{$name}' updated successfully.");
            header('Location: ' . base_url('admin/manage_categories.php'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

// Handle Delete Category
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['delete_category'])) {
    if (!csrf_verify()) {
        $error = 'Security token expired. Please try again.';
    } else {
        $id = (int) ($_POST['category_id'] ?? 0);
        try {
            $admin->deleteCategory($id);
            set_flash('success', "Category #{$id} deleted successfully.");
            header('Location: ' . base_url('admin/manage_categories.php'));
            exit;
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }
}

$categories = $admin->manageCategories();
$topLevelCategories = array_filter($categories, fn($c) => empty($c['parent_category_id']));

$pageTitle = 'Category Management — ORMS Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8 sm:py-12">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-500 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('admin/dashboard.php') ?>" class="hover:text-coral transition">Admin Dashboard</a></li>
            <li><span>/</span></li>
            <li class="text-midnight font-semibold">Category Management</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[#E9E7FF] pb-6 mb-8">
        <div>
            <div class="flex items-center space-x-2 text-coral text-xs font-semibold uppercase tracking-wider mb-1">
                <i class="ri-price-tag-3-line text-sm"></i>
                <span>Taxonomy Architecture</span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-display font-bold text-midnight tracking-tight">Category Taxonomy</h1>
            <p class="text-sm text-slate-500 mt-1">
                Manage rental departments, subcategories, catalog browsing hierarchy, and item distribution.
            </p>
        </div>
        <a href="<?= base_url('admin/dashboard.php') ?>" 
           class="inline-flex items-center space-x-1.5 px-5 py-2.5 bg-white hover:bg-slate-50 text-slate-600 hover:text-midnight text-xs font-semibold rounded-full border border-[#E9E7FF] transition self-start sm:self-auto shadow-sm">
            <i class="ri-arrow-left-line"></i>
            <span>Back to Dashboard</span>
        </a>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-2xl bg-rose-50 border border-rose-200 text-rose-700 text-sm flex items-center space-x-3 shadow-sm">
            <i class="ri-error-warning-line text-lg text-rose-600 flex-shrink-0"></i>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add Category Form -->
        <div class="bg-white border border-[#E9E7FF] rounded-3xl p-6 sm:p-7 shadow-sm h-fit">
            <h2 class="text-base font-bold font-display text-midnight mb-4 flex items-center space-x-2">
                <i class="ri-add-circle-line text-coral text-lg"></i>
                <span>Create New Category</span>
            </h2>
            <form action="<?= base_url('admin/manage_categories.php') ?>" method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="add_category" value="1">

                <div>
                    <label class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">Category Name <span class="text-coral">*</span></label>
                    <input type="text" name="category_name" required placeholder="e.g., Photography & Video" 
                           class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-xs text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">Parent Department (Optional)</label>
                    <select name="parent_category_id" class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-xs text-midnight focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition">
                        <option value="0">None (Primary Department)</option>
                        <?php foreach ($topLevelCategories as $tCat): ?>
                            <option value="<?= $tCat['category_id'] ?>">
                                <?= htmlspecialchars($tCat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-[11px] text-slate-400 mt-1.5 block">Leave empty to establish as a top-level department.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-midnight uppercase tracking-wider mb-2">Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Summary of equipment eligible for this category..." 
                              class="w-full bg-[#FAF8F5] border border-[#E9E7FF] rounded-2xl px-4 py-3 text-xs text-midnight placeholder-slate-400 focus:outline-none focus:border-coral focus:ring-1 focus:ring-coral transition"></textarea>
                </div>

                <button type="submit" class="w-full py-3 bg-coral hover:bg-[#e04e53] text-white text-xs font-semibold rounded-full shadow-glow-coral transition flex items-center justify-center space-x-1.5">
                    <i class="ri-check-line"></i>
                    <span>Create Category</span>
                </button>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="lg:col-span-2 bg-white border border-[#E9E7FF] rounded-3xl shadow-sm overflow-hidden">
            <div class="p-5 border-b border-[#E9E7FF] flex items-center justify-between bg-[#FAF8F5]">
                <span class="text-xs font-semibold text-midnight uppercase tracking-wider">Existing Taxonomy (<?= count($categories) ?>)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-600">
                    <thead class="bg-[#FAF8F5] text-slate-500 font-semibold uppercase tracking-wider text-[11px] border-b border-[#E9E7FF]">
                        <tr>
                            <th class="py-4 px-6">Category</th>
                            <th class="py-4 px-6">Hierarchy</th>
                            <th class="py-4 px-6">Catalog Count</th>
                            <th class="py-4 px-6 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#E9E7FF]">
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" class="p-12 text-center text-slate-400">No categories created yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): 
                                $isSub = !empty($cat['parent_category_id']);
                            ?>
                                <tr class="hover:bg-slate-50/70 transition">
                                    <td class="py-4 px-6">
                                        <div class="font-bold font-display text-midnight text-sm flex items-center space-x-2">
                                            <?php if ($isSub): ?>
                                                <i class="ri-corner-down-right-line text-slate-400"></i>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($cat['category_name']) ?></span>
                                        </div>
                                        <?php if (!empty($cat['description'])): ?>
                                            <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($cat['description']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <?php if ($isSub): ?>
                                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-semibold bg-[#E9E7FF] text-midnight border border-[#d8d5ff]">
                                                <?= htmlspecialchars($cat['parent_name'] ?? 'Parent') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-400 text-[11px] font-medium">Top-Level</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-6">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $cat['product_count'] > 0 ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-500' ?>">
                                            <?= $cat['product_count'] ?> item(s)
                                        </span>
                                    </td>
                                    <td class="py-4 px-6 text-right">
                                        <div class="inline-flex items-center space-x-2">
                                            <!-- Delete Button -->
                                            <?php if ($cat['product_count'] == 0): ?>
                                                <form action="<?= base_url('admin/manage_categories.php') ?>" method="POST" onsubmit="return confirm('Permanently delete category \'<?= htmlspecialchars(addslashes($cat['category_name'])) ?>\'?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                                                    <button type="submit" name="delete_category" value="1" title="Delete category"
                                                            class="w-8 h-8 rounded-full bg-rose-50 hover:bg-rose-100 text-rose-600 flex items-center justify-center transition">
                                                        <i class="ri-delete-bin-line"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span title="Cannot delete category containing active products" class="w-8 h-8 rounded-full bg-slate-100 text-slate-400 flex items-center justify-center cursor-not-allowed">
                                                    <i class="ri-lock-line"></i>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
