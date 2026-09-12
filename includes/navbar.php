<?php
/**
 * Online Rental Management System (ORMS)
 * Navigation Bar Component
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$loggedIn = is_logged_in();
$adminUser = is_admin();
$activeRole = current_role();
$roles = user_roles();
$hasDualRole = count($roles) > 1;
?>
<nav class="bg-slate-900 border-b border-slate-800 text-slate-200 sticky top-0 z-50 shadow-md">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- Brand Logo -->
            <div class="flex items-center">
                <a href="<?= base_url('index.php') ?>" class="flex items-center space-x-3 group">
                    <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-blue-600 to-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-500/20 group-hover:scale-105 transition">
                        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                    </div>
                    <div>
                        <span class="text-xl font-extrabold tracking-tight bg-gradient-to-r from-white via-slate-200 to-blue-400 bg-clip-text text-transparent">ORMS</span>
                        <span class="block text-[10px] text-slate-400 -mt-1 font-medium tracking-wider uppercase">Rental Platform</span>
                    </div>
                </a>

                <!-- Left Navigation Links -->
                <div class="hidden md:flex ml-10 space-x-4">
                    <a href="<?= base_url('index.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Home</a>
                    <a href="<?= base_url('renter/search.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Browse Catalog</a>

                    <?php if ($loggedIn): ?>
                        <?php if ($adminUser): ?>
                            <a href="<?= base_url('admin/dashboard.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Admin Panel</a>
                            <a href="<?= base_url('admin/manage_users.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Users</a>
                            <a href="<?= base_url('admin/manage_categories.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Categories</a>
                        <?php elseif ($activeRole === 'Owner'): ?>
                            <a href="<?= base_url('owner/dashboard.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Owner Dashboard</a>
                            <a href="<?= base_url('owner/add_product.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">+ List Product</a>
                            <a href="<?= base_url('owner/manage_requests.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Requests</a>
                            <a href="<?= base_url('owner/earnings.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Earnings</a>
                        <?php else: ?>
                            <a href="<?= base_url('renter/dashboard.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">Renter Dashboard</a>
                            <a href="<?= base_url('renter/my_rentals.php') ?>" class="px-3 py-2 rounded-lg text-sm font-medium hover:text-white hover:bg-slate-800 transition">My Rentals</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Navigation Items -->
            <div class="flex items-center space-x-3">
                <?php if ($loggedIn): ?>
                    <!-- Dual Role Switcher Pill -->
                    <?php if ($hasDualRole && !$adminUser): ?>
                        <div class="hidden sm:flex items-center bg-slate-800/90 p-1 rounded-xl border border-slate-700">
                            <span class="text-xs text-slate-400 px-2 font-medium">Active Role:</span>
                            <a href="<?= base_url('auth/switch_role.php?role=Owner') ?>" 
                               class="px-2.5 py-1 text-xs font-semibold rounded-lg transition <?= $activeRole === 'Owner' ? 'bg-blue-600 text-white shadow' : 'text-slate-400 hover:text-white' ?>">
                               Owner
                            </a>
                            <a href="<?= base_url('auth/switch_role.php?role=Renter') ?>" 
                               class="px-2.5 py-1 text-xs font-semibold rounded-lg transition <?= $activeRole === 'Renter' ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' ?>">
                               Renter
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- User Identity Badge -->
                    <div class="flex items-center space-x-2 pl-2">
                        <div class="w-8 h-8 rounded-lg bg-slate-750 border border-slate-700 flex items-center justify-center font-bold text-xs text-indigo-400 bg-slate-800">
                            <?= strtoupper(substr(current_user_name(), 0, 1)) ?>
                        </div>
                        <div class="hidden lg:block text-left">
                            <div class="text-xs font-semibold text-white leading-tight"><?= htmlspecialchars(current_user_name()) ?></div>
                            <div class="text-[10px] text-slate-400 font-medium leading-none">
                                <?= htmlspecialchars($adminUser ? 'Administrator' : $activeRole) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Notifications Link -->
                    <a href="<?= base_url('notifications/view_notifications.php') ?>" title="Notifications" class="p-2 text-slate-400 hover:text-white hover:bg-slate-800 rounded-lg transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                        </svg>
                    </a>

                    <!-- Logout Button -->
                    <a href="<?= base_url('auth/logout.php') ?>" class="px-3 py-1.5 text-xs font-semibold text-rose-300 bg-rose-950/40 hover:bg-rose-900/60 border border-rose-800/50 rounded-lg transition">
                        Logout
                    </a>
                <?php else: ?>
                    <a href="<?= base_url('auth/login.php') ?>" class="px-4 py-2 text-sm font-medium text-slate-300 hover:text-white hover:bg-slate-800 rounded-lg transition">
                        Sign In
                    </a>
                    <a href="<?= base_url('auth/register.php') ?>" class="px-4 py-2 text-sm font-semibold text-white bg-blue-600 hover:bg-blue-500 rounded-lg shadow-md shadow-blue-500/20 transition">
                        Get Started
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
