<?php
/**
 * Online Rental Management System (ORMS)
 * Comprehensive Notifications Center
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 11) & Section 6 (Step 10: Notification System)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Notification.php';

if (!is_logged_in()) {
    header('Location: ' . base_url('auth/login.php'));
    exit;
}

$userId = (int) current_user_id();
$userRole = current_role();

// Filter parameters
$selectedType = isset($_GET['type']) ? trim($_GET['type']) : 'All';
$validTypes = ['All', 'Rental', 'Payment', 'Fine', 'Dispute', 'System'];
if (!in_array($selectedType, $validTypes, true)) {
    $selectedType = 'All';
}

$unreadOnly = isset($_GET['unread']) && $_GET['unread'] === '1';

// Handle Mark All Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (csrf_verify()) {
        Notification::markAllReadByUser($userId);
        set_flash('success', 'All notifications marked as read.');
    }
    $query = http_build_query(array_filter(['type' => $selectedType !== 'All' ? $selectedType : null, 'unread' => $unreadOnly ? '1' : null]));
    header('Location: ' . base_url('notifications/view_notifications.php' . ($query ? '?' . $query : '')));
    exit;
}

// Handle Mark Single Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_single_read'])) {
    if (csrf_verify()) {
        $notifId = (int) ($_POST['notif_id'] ?? 0);
        if ($notifId > 0) {
            $notif = Notification::findById($notifId);
            if ($notif && $notif->getUserID() === $userId) {
                $notif->markAsRead();
            }
        }
    }
    $query = http_build_query(array_filter(['type' => $selectedType !== 'All' ? $selectedType : null, 'unread' => $unreadOnly ? '1' : null]));
    header('Location: ' . base_url('notifications/view_notifications.php' . ($query ? '?' . $query : '')));
    exit;
}

// Handle Delete Notification
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_notif'])) {
    if (csrf_verify()) {
        $notifId = (int) ($_POST['notif_id'] ?? 0);
        if ($notifId > 0) {
            Notification::delete($notifId, $userId);
            set_flash('success', 'Notification removed.');
        }
    }
    $query = http_build_query(array_filter(['type' => $selectedType !== 'All' ? $selectedType : null, 'unread' => $unreadOnly ? '1' : null]));
    header('Location: ' . base_url('notifications/view_notifications.php' . ($query ? '?' . $query : '')));
    exit;
}

// Fetch filtered notifications
$typeFilter = ($selectedType === 'All') ? null : $selectedType;
$isReadFilter = $unreadOnly ? false : null;
$notifications = Notification::findByUser($userId, $typeFilter, $isReadFilter, 100);
$unreadCount = Notification::countUnread($userId);

$pageTitle = 'Notifications Center — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-stone-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-midnight transition">Home</a></li>
            <li><span>/</span></li>
            <li class="text-stone-700 font-semibold">Notification Center</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="font-display text-2xl sm:text-3xl font-bold text-midnight tracking-tight flex items-center space-x-3">
                <i class="ri-notification-3-line text-coral"></i>
                <span>Notification Center</span>
            </h1>
            <p class="text-sm text-stone-500 mt-1">
                Real-time automated alerts for rental requests, payment confirmations, fines, returns, and disputes.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <?php if ($unreadCount > 0): ?>
                <form action="<?= base_url('notifications/view_notifications.php') . ($selectedType !== 'All' ? '?type=' . urlencode($selectedType) : '') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" 
                            class="px-4 py-2 bg-white hover:bg-stone-50 text-stone-700 text-xs font-semibold rounded-full border border-stone-200/80 transition shadow-soft flex items-center space-x-2">
                        <i class="ri-check-double-line text-emerald-600 text-sm"></i>
                        <span>Mark All as Read (<?= $unreadCount ?>)</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters & Tabs -->
    <div class="bg-white border border-stone-200/80 rounded-2xl p-3 sm:p-4 mb-6 shadow-soft flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <!-- Type Filter Tabs -->
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            <?php 
                $tabs = [
                    'All'      => ['label' => 'All', 'icon' => 'ri-stack-line'],
                    'Rental'   => ['label' => 'Rentals', 'icon' => 'ri-box-3-line'],
                    'Payment'  => ['label' => 'Payments', 'icon' => 'ri-bank-card-line'],
                    'Fine'     => ['label' => 'Fines', 'icon' => 'ri-alert-line'],
                    'Dispute'  => ['label' => 'Disputes', 'icon' => 'ri-scales-3-line'],
                    'System'   => ['label' => 'System', 'icon' => 'ri-notification-3-line']
                ];
                foreach ($tabs as $key => $tab): 
                    $isActive = ($selectedType === $key);
                    $url = base_url('notifications/view_notifications.php?type=' . urlencode($key) . ($unreadOnly ? '&unread=1' : ''));
            ?>
                <a href="<?= $url ?>" 
                   class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition flex items-center space-x-1.5 <?= $isActive ? 'bg-midnight text-white shadow-sm' : 'bg-stone-50 text-stone-600 hover:text-midnight hover:bg-stone-100' ?>">
                    <i class="<?= $tab['icon'] ?>"></i>
                    <span><?= $tab['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Read Status Filter Toggle -->
        <div class="flex items-center space-x-1 bg-[#FAF8F5] p-1 rounded-full border border-stone-200/60 text-xs">
            <a href="<?= base_url('notifications/view_notifications.php?type=' . urlencode($selectedType)) ?>" 
               class="px-3 py-1 rounded-full font-medium transition <?= !$unreadOnly ? 'bg-white text-midnight font-bold shadow-soft' : 'text-stone-500 hover:text-midnight' ?>">
                All
            </a>
            <a href="<?= base_url('notifications/view_notifications.php?type=' . urlencode($selectedType) . '&unread=1') ?>" 
               class="px-3 py-1 rounded-full font-medium transition flex items-center space-x-1.5 <?= $unreadOnly ? 'bg-coral text-white font-bold shadow-glow-coral' : 'text-stone-500 hover:text-midnight' ?>">
                <span>Unread</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="px-1.5 py-0.2 <?= $unreadOnly ? 'bg-white text-coral' : 'bg-coral text-white' ?> text-[10px] font-extrabold rounded-full"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Notifications Feed -->
    <?php if (empty($notifications)): ?>
        <div class="bg-white border border-stone-200/80 rounded-3xl p-12 text-center shadow-soft">
            <div class="w-16 h-16 mx-auto rounded-3xl bg-[#FAF8F5] border border-stone-200/60 flex items-center justify-center text-2xl text-stone-400 mb-4">
                <i class="ri-notification-off-line"></i>
            </div>
            <h3 class="font-display text-base font-bold text-midnight">No notifications found</h3>
            <p class="text-xs text-stone-500 mt-1 max-w-md mx-auto">
                <?= $unreadOnly ? "You have no unread alerts in this category." : "You're all caught up! New alerts regarding bookings, returns, and settlements will appear here automatically." ?>
            </p>
            <?php if ($selectedType !== 'All' || $unreadOnly): ?>
                <div class="mt-4">
                    <a href="<?= base_url('notifications/view_notifications.php') ?>" class="text-xs text-coral hover:text-coral-600 font-semibold inline-flex items-center space-x-1">
                        <span>Reset Filters</span>
                        <i class="ri-arrow-right-line"></i>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($notifications as $n): 
                $isRead = $n->isRead();
                $type = $n->getType();
                $targetUrl = $n->getTargetUrl($userRole);
                
                switch ($type) {
                    case 'Rental':
                        $iconClass = 'ri-box-3-line';
                        $badgeColor = 'text-blue-700 bg-blue-50 border-blue-200/70';
                        break;
                    case 'Payment':
                        $iconClass = 'ri-bank-card-line';
                        $badgeColor = 'text-emerald-700 bg-emerald-50 border-emerald-200/70';
                        break;
                    case 'Fine':
                        $iconClass = 'ri-alert-line';
                        $badgeColor = 'text-amber-800 bg-amber-50 border-amber-200/70';
                        break;
                    case 'Dispute':
                        $iconClass = 'ri-scales-3-line';
                        $badgeColor = 'text-rose-700 bg-rose-50 border-rose-200/70';
                        break;
                    default:
                        $iconClass = 'ri-notification-3-line';
                        $badgeColor = 'text-indigo-700 bg-indigo-50 border-indigo-200/70';
                }
            ?>
                <div class="group p-4 sm:p-5 rounded-3xl border transition flex items-start justify-between gap-4 <?= $isRead ? 'bg-white border-stone-200/80 text-stone-700 hover:border-stone-300' : 'bg-[#FAF8F5] border-coral/30 text-midnight shadow-soft ring-1 ring-coral/10' ?>">
                    <!-- Notification Content -->
                    <div class="flex items-start space-x-3.5 flex-1 min-w-0">
                        <div class="w-10 h-10 rounded-2xl flex-shrink-0 flex items-center justify-center border <?= $badgeColor ?> text-lg shadow-sm">
                            <i class="<?= $iconClass ?>"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2.5 py-0.5 rounded-full border <?= $badgeColor ?>">
                                    <?= htmlspecialchars($type) ?>
                                </span>
                                <?php if (!$isRead): ?>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-coral text-white shadow-sm">
                                        NEW
                                    </span>
                                <?php endif; ?>
                                <span class="text-[11px] text-stone-500 font-mono">
                                    <?= date('M d, Y • h:i A', strtotime($n->getCreatedAt() ?? 'now')) ?>
                                </span>
                                <span class="text-[11px] text-stone-300">&bull;</span>
                                <span class="text-[11px] text-stone-500 font-medium">
                                    <?= $n->getFormattedTime() ?>
                                </span>
                            </div>

                            <p class="text-sm leading-relaxed <?= $isRead ? 'text-stone-600' : 'text-midnight font-medium' ?>">
                                <?= htmlspecialchars($n->getMessage()) ?>
                            </p>

                            <!-- Contextual Action Link -->
                            <?php if ($targetUrl && $targetUrl !== 'notifications/view_notifications.php'): ?>
                                <div class="mt-2.5">
                                    <a href="<?= base_url($targetUrl) ?>" 
                                       class="inline-flex items-center space-x-1.5 text-xs text-coral hover:text-coral-600 font-semibold group-hover:underline">
                                        <span>View Details</span>
                                        <i class="ri-arrow-right-line"></i>
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Individual Item Actions -->
                    <div class="flex items-center space-x-1 flex-shrink-0 self-start sm:self-center">
                        <?php if (!$isRead): ?>
                            <form action="<?= base_url('notifications/view_notifications.php') . ($selectedType !== 'All' ? '?type=' . urlencode($selectedType) : '') . ($unreadOnly ? '&unread=1' : '') ?>" method="POST">
                                <?= csrf_field() ?>
                                <input type="hidden" name="mark_single_read" value="1">
                                <input type="hidden" name="notif_id" value="<?= $n->getNotifID() ?>">
                                <button type="submit" 
                                        title="Mark as read"
                                        class="p-2 text-stone-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-full transition text-xs flex items-center space-x-1">
                                    <i class="ri-check-line text-sm"></i>
                                    <span class="hidden sm:inline text-[11px]">Read</span>
                                </button>
                            </form>
                        <?php endif; ?>

                        <!-- Delete Notification -->
                        <form action="<?= base_url('notifications/view_notifications.php') . ($selectedType !== 'All' ? '?type=' . urlencode($selectedType) : '') . ($unreadOnly ? '&unread=1' : '') ?>" method="POST" onsubmit="return confirm('Remove this notification?');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="delete_notif" value="1">
                            <input type="hidden" name="notif_id" value="<?= $n->getNotifID() ?>">
                            <button type="submit" 
                                    title="Delete notification"
                                    class="p-2 text-stone-400 hover:text-rose-600 hover:bg-rose-50 rounded-full transition text-xs">
                                <i class="ri-delete-bin-line text-sm"></i>
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
