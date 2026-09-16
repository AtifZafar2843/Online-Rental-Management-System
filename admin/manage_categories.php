<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Category Taxonomy Management
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.4 & Synopsis Section 11.1, 13.I (Page 29)
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_category'])) {
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
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_category'])) {
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

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('admin/dashboard.php') ?>" class="hover:text-white transition">Admin Dashboard</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">Category Management</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-slate-800 pb-6 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center space-x-3">
                <span>🏷️</span>
                <span>Category Taxonomy Management</span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Manage item categories, subcategories, catalog browsing hierarchy, and product allocations.
            </p>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="mb-6 p-4 rounded-xl bg-rose-950/60 border border-rose-800 text-rose-300 text-sm flex items-center space-x-3 shadow-lg">
            <span class="text-xl flex-shrink-0">⚠️</span>
            <span><?= htmlspecialchars($error) ?></span>
        </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Add Category Form -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl h-fit">
            <h2 class="text-base font-bold text-white mb-4 flex items-center space-x-2">
                <span>➕</span>
                <span>Add New Category</span>
            </h2>
            <form action="<?= base_url('admin/manage_categories.php') ?>" method="POST" class="space-y-4">
                <?= csrf_field() ?>
                <input type="hidden" name="add_category" value="1">

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Category Name <span class="text-rose-400">*</span></label>
                    <input type="text" name="category_name" required placeholder="e.g., Photography & Video" 
                           class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Parent Category (Optional)</label>
                    <select name="parent_category_id" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="0">None (Top-Level Category)</option>
                        <?php foreach ($topLevelCategories as $tCat): ?>
                            <option value="<?= $tCat['category_id'] ?>">
                                <?= htmlspecialchars($tCat['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="text-[11px] text-slate-500 mt-1 block">Leave empty to establish as a primary department.</span>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-300 mb-1">Description (Optional)</label>
                    <textarea name="description" rows="3" placeholder="Summary of items eligible for this category..." 
                              class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>

                <button type="submit" class="w-full py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold rounded-xl shadow-md shadow-blue-600/30 transition">
                    + Create Category
                </button>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="lg:col-span-2 bg-slate-900 border border-slate-800 rounded-2xl shadow-xl overflow-hidden">
            <div class="p-4 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                <span class="text-xs font-bold text-slate-300 uppercase tracking-wider">Existing Taxonomy (<?= count($categories) ?>)</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-300">
                    <thead class="bg-slate-950 text-slate-400 font-bold uppercase tracking-wider text-[11px] border-b border-slate-800">
                        <tr>
                            <th class="p-4">Category</th>
                            <th class="p-4">Parent Level</th>
                            <th class="p-4">Products</th>
                            <th class="p-4 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <?php if (empty($categories)): ?>
                            <tr>
                                <td colspan="4" class="p-8 text-center text-slate-500">No categories found.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($categories as $cat): 
                                $isSub = !empty($cat['parent_category_id']);
                            ?>
                                <tr class="hover:bg-slate-800/40 transition">
                                    <td class="p-4">
                                        <div class="font-bold text-white flex items-center space-x-2">
                                            <?php if ($isSub): ?>
                                                <span class="text-slate-500 font-mono">&boxur;</span>
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($cat['category_name']) ?></span>
                                        </div>
                                        <?php if (!empty($cat['description'])): ?>
                                            <p class="text-[11px] text-slate-400 mt-0.5 line-clamp-1"><?= htmlspecialchars($cat['description']) ?></p>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <?php if ($isSub): ?>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-semibold bg-indigo-950/80 text-indigo-300 border border-indigo-800">
                                                <?= htmlspecialchars($cat['parent_name'] ?? 'Parent') ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-slate-500 text-[11px] font-medium">Top-Level</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 rounded-full text-[11px] font-bold <?= $cat['product_count'] > 0 ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : 'bg-slate-800 text-slate-400' ?>">
                                            <?= $cat['product_count'] ?> item(s)
                                        </span>
                                    </td>
                                    <td class="p-4 text-right">
                                        <div class="inline-flex items-center space-x-2">
                                            <!-- Delete Button -->
                                            <?php if ($cat['product_count'] == 0): ?>
                                                <form action="<?= base_url('admin/manage_categories.php') ?>" method="POST" onsubmit="return confirm('Permanently delete category \'<?= htmlspecialchars(addslashes($cat['category_name'])) ?>\'?');">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                                                    <button type="submit" name="delete_category" value="1" title="Delete category"
                                                            class="p-1.5 text-slate-500 hover:text-rose-400 hover:bg-slate-800 rounded-lg transition">
                                                        🗑️
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span title="Cannot delete category containing active products" class="text-slate-600 text-xs cursor-not-allowed">
                                                    🔒
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
