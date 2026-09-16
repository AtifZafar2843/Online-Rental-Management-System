<?php
/**
 * Online Rental Management System (ORMS)
 * Navigation Bar Component — Brand System Integration
 */

declare(strict_types=1);

require_once __DIR__ . '/functions.php';

$loggedIn = is_logged_in();
$adminUser = is_admin();
$activeRole = current_role();
$roles = user_roles();
$hasDualRole = count($roles) > 1;
?>
<nav class="bg-white/95 backdrop-blur-md border-b border-stone-200/80 text-midnight sticky top-0 z-50 shadow-sm transition-all duration-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between items-center h-20">
            <!-- Brand Logo -->
            <div class="flex items-center space-x-8">
                <a href="<?= base_url('index.php') ?>" class="flex items-center group transition transform hover:scale-[1.02]">
                    <img src="<?= base_url('assets/img/ORMS Logo.png') ?>" alt="ORMS — Rental Marketplace" class="h-10 sm:h-12 w-auto object-contain transition-all duration-200">
                </a>

                <!-- Desktop Navigation Links -->
                <div class="hidden md:flex items-center space-x-1 lg:space-x-2">
                    <a href="<?= base_url('index.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">
                        Home
                    </a>
                    <a href="<?= base_url('renter/search.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">
                        Browse Catalog
                    </a>

                    <?php if ($loggedIn): ?>
                        <?php if ($adminUser): ?>
                            <a href="<?= base_url('admin/dashboard.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Dashboard</a>
                            <a href="<?= base_url('admin/manage_users.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Users</a>
                            <a href="<?= base_url('admin/manage_categories.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Categories</a>
                            <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Disputes</a>
                            <a href="<?= base_url('admin/reports.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Reports</a>
                        <?php elseif ($activeRole === 'Owner'): ?>
                            <a href="<?= base_url('owner/dashboard.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Dashboard</a>
                            <a href="<?= base_url('owner/add_product.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-semibold text-coral bg-coral-50 hover:bg-coral-100 transition">
                                <i class="ri-add-line mr-1"></i> List Item
                            </a>
                            <a href="<?= base_url('owner/manage_requests.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Requests</a>
                            <a href="<?= base_url('owner/earnings.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Earnings</a>
                        <?php else: ?>
                            <a href="<?= base_url('renter/dashboard.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">Dashboard</a>
                            <a href="<?= base_url('renter/my_rentals.php') ?>" class="px-3.5 py-2 rounded-full text-sm font-medium text-stone-700 hover:text-coral hover:bg-stone-50 transition">My Rentals</a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Navigation Items -->
            <div class="flex items-center space-x-3 sm:space-x-4">
                <?php if ($loggedIn): ?>
                    <!-- Dual Role Switcher Pill -->
                    <?php if ($hasDualRole && !$adminUser): ?>
                        <div class="hidden lg:flex items-center bg-stone-100 p-1 rounded-full border border-stone-200/80 text-xs">
                            <span class="text-stone-400 pl-2.5 pr-1.5 font-medium">Role:</span>
                            <a href="<?= base_url('auth/switch_role.php?role=Owner') ?>" 
                               class="px-3 py-1 font-semibold rounded-full transition <?= $activeRole === 'Owner' ? 'bg-white text-midnight shadow-sm border border-stone-200' : 'text-stone-500 hover:text-midnight' ?>">
                               Owner
                            </a>
                            <a href="<?= base_url('auth/switch_role.php?role=Renter') ?>" 
                               class="px-3 py-1 font-semibold rounded-full transition <?= $activeRole === 'Renter' ? 'bg-white text-midnight shadow-sm border border-stone-200' : 'text-stone-500 hover:text-midnight' ?>">
                               Renter
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Notifications Dropdown & Live Badge -->
                    <?php 
                        require_once __DIR__ . '/../classes/Notification.php';
                        $initialUnread = Notification::countUnread((int)current_user_id());
                    ?>
                    <div class="relative" id="notif-container">
                        <button id="notif-btn" 
                                type="button" 
                                title="Notifications" 
                                class="relative p-2.5 text-stone-600 hover:text-coral hover:bg-stone-100 rounded-full transition focus:outline-none">
                            <i class="ri-notification-3-line text-xl"></i>
                            <span id="notif-badge" 
                                  class="<?= $initialUnread > 0 ? '' : 'hidden' ?> absolute top-1 right-1 min-w-[18px] h-[18px] px-1 bg-coral text-white text-[10px] font-extrabold flex items-center justify-center rounded-full ring-2 ring-white shadow-sm">
                                <?= $initialUnread > 99 ? '99+' : $initialUnread ?>
                            </span>
                        </button>

                        <!-- Notification Dropdown Menu -->
                        <div id="notif-dropdown" 
                             class="hidden absolute right-0 mt-3 w-80 sm:w-96 bg-white border border-stone-200 rounded-3xl shadow-xl z-50 overflow-hidden animate-in fade-in slide-in-from-top-2 duration-150">
                            <div class="p-4 border-b border-stone-100 flex items-center justify-between bg-stone-50/70">
                                <div class="flex items-center space-x-2">
                                    <span class="font-display font-bold text-sm text-midnight">Notifications</span>
                                    <span id="notif-dropdown-count" class="text-[11px] bg-coral/10 text-coral font-semibold px-2.5 py-0.5 rounded-full">
                                        <?= $initialUnread ?> unread
                                    </span>
                                </div>
                                <a href="<?= base_url('notifications/view_notifications.php') ?>" class="text-xs text-coral hover:text-coral-600 font-semibold">
                                    View All &rarr;
                                </a>
                            </div>

                            <div id="notif-dropdown-list" class="max-h-80 overflow-y-auto divide-y divide-stone-100">
                                <div class="p-6 text-center text-xs text-stone-400">
                                    Loading updates...
                                </div>
                            </div>

                            <div class="p-3 border-t border-stone-100 bg-stone-50/60 text-center">
                                <a href="<?= base_url('notifications/view_notifications.php') ?>" class="block w-full py-1.5 text-xs text-stone-600 hover:text-coral rounded-xl hover:bg-stone-100 transition font-medium">
                                    Notification Center &rarr;
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- User Identity Pill -->
                    <div class="flex items-center space-x-2.5 pl-1">
                        <div class="w-9 h-9 rounded-full bg-lilac flex items-center justify-center font-display font-bold text-sm text-midnight border border-lilac-300 shadow-sm">
                            <?= strtoupper(substr(current_user_name(), 0, 1)) ?>
                        </div>
                        <div class="hidden xl:block text-left">
                            <div class="text-xs font-semibold text-midnight leading-tight"><?= htmlspecialchars(current_user_name()) ?></div>
                            <div class="text-[10px] text-stone-500 font-medium">
                                <?= htmlspecialchars($adminUser ? 'Administrator' : $activeRole) ?>
                            </div>
                        </div>
                    </div>

                    <!-- Logout Button -->
                    <a href="<?= base_url('auth/logout.php') ?>" 
                       title="Log Out"
                       class="p-2 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-full transition text-lg flex items-center">
                        <i class="ri-logout-box-r-line"></i>
                    </a>

                <?php else: ?>
                    <a href="<?= base_url('auth/login.php') ?>" 
                       class="hidden sm:inline-flex items-center px-5 py-2.5 text-sm font-semibold text-stone-700 hover:text-midnight hover:bg-stone-100 rounded-full transition">
                        Sign In
                    </a>
                    <a href="<?= base_url('auth/register.php') ?>" 
                       class="inline-flex items-center justify-center px-6 py-2.5 text-sm font-semibold text-white bg-coral hover:bg-coral-600 rounded-full shadow-glow-coral hover:shadow-lg transition transform hover:-translate-y-0.5">
                        Get Started <i class="ri-arrow-right-line ml-1.5"></i>
                    </a>
                <?php endif; ?>

                <!-- Mobile Hamburger Toggle -->
                <button type="button" 
                        id="mobile-menu-btn"
                        class="md:hidden p-2.5 rounded-2xl text-stone-600 hover:text-midnight hover:bg-stone-100 transition focus:outline-none"
                        aria-label="Toggle Navigation">
                    <i class="ri-menu-4-line text-2xl" id="mobile-menu-icon"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Mobile Drawer Menu -->
    <div id="mobile-menu" class="hidden md:hidden border-t border-stone-200/80 bg-white/98 backdrop-blur-lg px-4 pt-3 pb-6 space-y-2 shadow-lg">
        <a href="<?= base_url('index.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 hover:text-coral transition">
            <i class="ri-home-5-line mr-2"></i> Home
        </a>
        <a href="<?= base_url('renter/search.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 hover:text-coral transition">
            <i class="ri-search-line mr-2"></i> Browse Catalog
        </a>

        <?php if ($loggedIn): ?>
            <?php if ($adminUser): ?>
                <a href="<?= base_url('admin/dashboard.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-dashboard-line mr-2"></i> Admin Dashboard</a>
                <a href="<?= base_url('admin/manage_users.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-user-settings-line mr-2"></i> Manage Users</a>
                <a href="<?= base_url('admin/manage_categories.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-folder-line mr-2"></i> Categories</a>
                <a href="<?= base_url('admin/resolve_disputes.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-scales-3-line mr-2"></i> Disputes</a>
                <a href="<?= base_url('admin/reports.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-file-chart-line mr-2"></i> Reports</a>
            <?php elseif ($activeRole === 'Owner'): ?>
                <a href="<?= base_url('owner/dashboard.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-dashboard-line mr-2"></i> Owner Dashboard</a>
                <a href="<?= base_url('owner/add_product.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-coral bg-coral-50 transition"><i class="ri-add-circle-line mr-2"></i> List Something +</a>
                <a href="<?= base_url('owner/manage_requests.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-inbox-line mr-2"></i> Rental Requests</a>
                <a href="<?= base_url('owner/earnings.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-money-rupee-circle-line mr-2"></i> Earnings</a>
            <?php else: ?>
                <a href="<?= base_url('renter/dashboard.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-dashboard-line mr-2"></i> Renter Dashboard</a>
                <a href="<?= base_url('renter/my_rentals.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-stone-700 hover:bg-stone-100 transition"><i class="ri-history-line mr-2"></i> My Rentals</a>
            <?php endif; ?>

            <?php if ($hasDualRole && !$adminUser): ?>
                <div class="pt-3 border-t border-stone-200">
                    <p class="px-4 text-xs font-semibold text-stone-400 uppercase tracking-wider mb-2">Switch Active Role</p>
                    <div class="flex space-x-2 px-4">
                        <a href="<?= base_url('auth/switch_role.php?role=Owner') ?>" 
                           class="flex-1 text-center py-2 rounded-xl text-xs font-semibold <?= $activeRole === 'Owner' ? 'bg-coral text-white' : 'bg-stone-100 text-stone-600' ?>">
                            Owner
                        </a>
                        <a href="<?= base_url('auth/switch_role.php?role=Renter') ?>" 
                           class="flex-1 text-center py-2 rounded-xl text-xs font-semibold <?= $activeRole === 'Renter' ? 'bg-coral text-white' : 'bg-stone-100 text-stone-600' ?>">
                            Renter
                        </a>
                    </div>
                </div>
            <?php endif; ?>

            <div class="pt-3 border-t border-stone-200">
                <a href="<?= base_url('auth/logout.php') ?>" class="block px-4 py-2.5 rounded-2xl text-sm font-semibold text-rose-600 hover:bg-rose-50 transition">
                    <i class="ri-logout-box-r-line mr-2"></i> Log Out (<?= htmlspecialchars(current_user_name()) ?>)
                </a>
            </div>
        <?php else: ?>
            <div class="pt-3 border-t border-stone-200 flex flex-col space-y-2">
                <a href="<?= base_url('auth/login.php') ?>" class="w-full text-center py-3 rounded-full text-sm font-semibold border border-stone-300 text-midnight hover:bg-stone-50 transition">
                    Sign In
                </a>
                <a href="<?= base_url('auth/register.php') ?>" class="w-full text-center py-3 rounded-full text-sm font-semibold bg-coral text-white shadow-glow-coral hover:bg-coral-600 transition">
                    Get Started &rarr;
                </a>
            </div>
        <?php endif; ?>
    </div>
</nav>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mobile Drawer Toggle
    const mobileBtn = document.getElementById('mobile-menu-btn');
    const mobileMenu = document.getElementById('mobile-menu');
    const mobileIcon = document.getElementById('mobile-menu-icon');

    if (mobileBtn && mobileMenu) {
        mobileBtn.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
            if (mobileMenu.classList.contains('hidden')) {
                mobileIcon.className = 'ri-menu-4-line text-2xl';
            } else {
                mobileIcon.className = 'ri-close-line text-2xl';
            }
        });
    }

    <?php if ($loggedIn): ?>
    // Notification Dropdown Toggle
    const notifBtn = document.getElementById('notif-btn');
    const notifDropdown = document.getElementById('notif-dropdown');
    const notifBadge = document.getElementById('notif-badge');
    const notifDropdownCount = document.getElementById('notif-dropdown-count');
    const notifDropdownList = document.getElementById('notif-dropdown-list');

    if (notifBtn && notifDropdown) {
        notifBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            notifDropdown.classList.toggle('hidden');
            if (!notifDropdown.classList.contains('hidden')) {
                fetchNotifications(true);
            }
        });

        document.addEventListener('click', function(e) {
            if (!notifDropdown.contains(e.target) && !notifBtn.contains(e.target)) {
                notifDropdown.classList.add('hidden');
            }
        });

        function renderDropdownItems(items) {
            if (!notifDropdownList) return;
            if (!items || items.length === 0) {
                notifDropdownList.innerHTML = `
                    <div class="p-8 text-center text-xs text-stone-400">
                        <i class="ri-notification-off-line text-2xl text-stone-300 block mb-1"></i>
                        No unread notifications
                    </div>
                `;
                return;
            }

            const iconMap = {
                'Rental': 'ri-box-3-line text-blue-500',
                'Payment': 'ri-bank-card-line text-emerald-500',
                'Fine': 'ri-error-warning-line text-amber-500',
                'Dispute': 'ri-scales-3-line text-rose-500',
                'System': 'ri-notification-3-line text-purple-500'
            };

            notifDropdownList.innerHTML = items.map(item => {
                const iconClass = iconMap[item.type] || 'ri-notification-3-line text-stone-400';
                const cleanTarget = item.target_url ? item.target_url.replace(/^\/+/, '') : 'notifications/view_notifications.php';
                const fullUrl = '<?= rtrim(base_url(), "/") ?>/' + cleanTarget;
                return `
                    <a href="${fullUrl}" class="block p-3.5 hover:bg-stone-50 transition group">
                        <div class="flex items-start space-x-3">
                            <span class="p-2 bg-stone-100 rounded-full flex-shrink-0 group-hover:bg-white transition">
                                <i class="${iconClass} text-base leading-none"></i>
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-xs text-stone-700 line-clamp-2 group-hover:text-midnight font-medium leading-relaxed">${item.message}</p>
                                <span class="text-[10px] text-stone-400 mt-1 inline-block font-mono">${item.formatted_time}</span>
                            </div>
                            ${!item.is_read ? '<span class="w-2 h-2 rounded-full bg-coral mt-1.5 flex-shrink-0"></span>' : ''}
                        </div>
                    </a>
                `;
            }).join('');
        }

        function fetchNotifications(renderList = false) {
            fetch('<?= base_url("notifications/poll.php") ?>')
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        const count = data.unread_count || 0;
                        if (count > 0) {
                            notifBadge.textContent = count > 99 ? '99+' : count;
                            notifBadge.classList.remove('hidden');
                            if (notifDropdownCount) notifDropdownCount.textContent = `${count} unread`;
                        } else {
                            notifBadge.classList.add('hidden');
                            if (notifDropdownCount) notifDropdownCount.textContent = `0 unread`;
                        }
                        if (renderList) {
                            renderDropdownItems(data.notifications);
                        }
                    }
                })
                .catch(() => {});
        }

        fetchNotifications(true);
        setInterval(() => fetchNotifications(false), 15000);
    }
    <?php endif; ?>
});
</script>
