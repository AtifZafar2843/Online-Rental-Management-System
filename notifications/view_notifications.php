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
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-white transition">Home</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">Notification Center</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center space-x-3">
                <span>🔔</span>
                <span>Notification Center</span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">
                Real-time automated alerts for rental requests, payment confirmations, fines, returns, and disputes.
            </p>
        </div>

        <div class="flex items-center space-x-3">
            <?php if ($unreadCount > 0): ?>
                <form action="<?= base_url('notifications/view_notifications.php') . ($selectedType !== 'All' ? '?type=' . urlencode($selectedType) : '') ?>" method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="mark_all_read" value="1">
                    <button type="submit" 
                            class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold rounded-xl border border-slate-700 transition shadow flex items-center space-x-2">
                        <span>✓</span>
                        <span>Mark All as Read (<?= $unreadCount ?>)</span>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filters & Tabs -->
    <div class="bg-slate-900 border border-slate-800 rounded-2xl p-4 mb-6 shadow-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
        <!-- Type Filter Tabs -->
        <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
            <?php 
                $tabs = [
                    'All'      => ['label' => 'All', 'icon' => '📁'],
                    'Rental'   => ['label' => 'Rentals', 'icon' => '📦'],
                    'Payment'  => ['label' => 'Payments', 'icon' => '💳'],
                    'Fine'     => ['label' => 'Fines', 'icon' => '⚠️'],
                    'Dispute'  => ['label' => 'Disputes', 'icon' => '⚖️'],
                    'System'   => ['label' => 'System', 'icon' => '🔔']
                ];
                foreach ($tabs as $key => $tab): 
                    $isActive = ($selectedType === $key);
                    $url = base_url('notifications/view_notifications.php?type=' . urlencode($key) . ($unreadOnly ? '&unread=1' : ''));
            ?>
                <a href="<?= $url ?>" 
                   class="px-3 py-1.5 rounded-xl text-xs font-semibold transition flex items-center space-x-1.5 <?= $isActive ? 'bg-blue-600 text-white shadow-md shadow-blue-500/20' : 'bg-slate-800/80 text-slate-400 hover:text-white hover:bg-slate-800' ?>">
                    <span><?= $tab['icon'] ?></span>
                    <span><?= $tab['label'] ?></span>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- Read Status Filter Toggle -->
        <div class="flex items-center space-x-2 text-xs">
            <span class="text-slate-400 font-medium">Show:</span>
            <a href="<?= base_url('notifications/view_notifications.php?type=' . urlencode($selectedType)) ?>" 
               class="px-2.5 py-1 rounded-lg font-medium transition <?= !$unreadOnly ? 'bg-slate-800 text-white' : 'text-slate-400 hover:text-white' ?>">
                All
            </a>
            <a href="<?= base_url('notifications/view_notifications.php?type=' . urlencode($selectedType) . '&unread=1') ?>" 
               class="px-2.5 py-1 rounded-lg font-medium transition flex items-center space-x-1 <?= $unreadOnly ? 'bg-indigo-600 text-white shadow' : 'text-slate-400 hover:text-white' ?>">
                <span>Unread Only</span>
                <?php if ($unreadCount > 0): ?>
                    <span class="px-1.5 py-0.2 bg-rose-500 text-white text-[10px] font-bold rounded-full"><?= $unreadCount ?></span>
                <?php endif; ?>
            </a>
        </div>
    </div>

    <!-- Notifications Feed -->
    <?php if (empty($notifications)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center shadow-xl">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/80 flex items-center justify-center text-2xl text-slate-400 mb-4">
                🔕
            </div>
            <h3 class="text-base font-bold text-white">No notifications found</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                <?= $unreadOnly ? "You have no unread alerts in this category." : "You're all caught up! New alerts regarding bookings, returns, and settlements will appear here automatically." ?>
            </p>
            <?php if ($selectedType !== 'All' || $unreadOnly): ?>
                <div class="mt-4">
                    <a href="<?= base_url('notifications/view_notifications.php') ?>" class="text-xs text-blue-400 hover:text-blue-300 font-semibold">
                        Reset Filters &rarr;
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
                        $icon = '📦';
                        $badgeColor = 'text-blue-400 bg-blue-500/10 border-blue-500/30';
                        break;
                    case 'Payment':
                        $icon = '💳';
                        $badgeColor = 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30';
                        break;
                    case 'Fine':
                        $icon = '⚠️';
                        $badgeColor = 'text-amber-400 bg-amber-500/10 border-amber-500/30';
                        break;
                    case 'Dispute':
                        $icon = '⚖️';
                        $badgeColor = 'text-rose-400 bg-rose-500/10 border-rose-500/30';
                        break;
                    default:
                        $icon = '🔔';
                        $badgeColor = 'text-indigo-400 bg-indigo-500/10 border-indigo-500/30';
                }
            ?>
                <div class="group p-4 sm:p-5 rounded-2xl border transition flex items-start justify-between gap-4 <?= $isRead ? 'bg-slate-900/60 border-slate-800/80 text-slate-300' : 'bg-slate-900 border-indigo-500/40 text-white shadow-lg ring-1 ring-indigo-500/20' ?>">
                    <!-- Notification Content -->
                    <div class="flex items-start space-x-3.5 flex-1 min-w-0">
                        <div class="w-10 h-10 rounded-xl flex-shrink-0 flex items-center justify-center border <?= $badgeColor ?> text-lg">
                            <?= $icon ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 mb-1.5">
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md border <?= $badgeColor ?>">
                                    <?= htmlspecialchars($type) ?>
                                </span>
                                <?php if (!$isRead): ?>
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-blue-500/20 text-blue-400 border border-blue-500/30">
                                        NEW
                                    </span>
                                <?php endif; ?>
                                <span class="text-[11px] text-slate-500 font-mono">
                                    <?= date('M d, Y • h:i A', strtotime($n->getCreatedAt() ?? 'now')) ?>
                                </span>
                                <span class="text-[11px] text-slate-600">&bull;</span>
                                <span class="text-[11px] text-slate-400 font-medium">
                                    <?= $n->getFormattedTime() ?>
                                </span>
                            </div>

                            <p class="text-sm leading-relaxed <?= $isRead ? 'text-slate-300' : 'text-white font-medium' ?>">
                                <?= htmlspecialchars($n->getMessage()) ?>
                            </p>

                            <!-- Contextual Action Link -->
                            <?php if ($targetUrl && $targetUrl !== 'notifications/view_notifications.php'): ?>
                                <div class="mt-2.5">
                                    <a href="<?= base_url($targetUrl) ?>" 
                                       class="inline-flex items-center space-x-1.5 text-xs text-blue-400 hover:text-blue-300 font-semibold group-hover:underline">
                                        <span>View Details</span>
                                        <span>&rarr;</span>
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
                                        class="p-2 text-slate-400 hover:text-emerald-400 hover:bg-slate-800/80 rounded-xl transition text-xs flex items-center space-x-1">
                                    <span>✓</span>
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
                                    class="p-2 text-slate-500 hover:text-rose-400 hover:bg-slate-800/80 rounded-xl transition text-xs">
                                🗑️
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
