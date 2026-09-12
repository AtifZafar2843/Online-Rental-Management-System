<?php
/**
 * Online Rental Management System (ORMS)
 * User Notifications View Page
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 11) & Section 6 (Step 10 Preview / Step 6 Integration)
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

// Handle Mark All Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_all_read'])) {
    if (csrf_verify()) {
        Notification::markAllReadByUser($userId);
        set_flash('success', 'All notifications marked as read.');
    }
    header('Location: ' . base_url('notifications/view_notifications.php'));
    exit;
}

// Handle Mark Single Read
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mark_single_read'])) {
    if (csrf_verify()) {
        $notifId = (int) ($_POST['notif_id'] ?? 0);
        if ($notifId > 0) {
            $notif = new Notification($notifId, $userId);
            $notif->markAsRead();
        }
    }
    header('Location: ' . base_url('notifications/view_notifications.php'));
    exit;
}

$notifications = Notification::findByUser($userId, 50);
$unreadCount = Notification::countUnread($userId);

$flashSuccess = get_flash('success');

$pageTitle = 'Notifications — ORMS';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
    <!-- Breadcrumb -->
    <nav class="flex text-xs text-slate-400 mb-6" aria-label="Breadcrumb">
        <ol class="inline-flex items-center space-x-2">
            <li><a href="<?= base_url('index.php') ?>" class="hover:text-white">Home</a></li>
            <li><span>/</span></li>
            <li class="text-slate-200 font-semibold">Notifications</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-8">
        <div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight flex items-center space-x-3">
                <span>🔔</span>
                <span>Your Notifications</span>
            </h1>
            <p class="text-sm text-slate-400 mt-1">Updates on your rental bookings, approval decisions, and payments.</p>
        </div>

        <?php if ($unreadCount > 0): ?>
            <form action="<?= base_url('notifications/view_notifications.php') ?>" method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="mark_all_read" value="1">
                <button type="submit" 
                        class="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white text-xs font-semibold rounded-xl border border-slate-700 transition">
                    ✓ Mark All as Read (<?= $unreadCount ?>)
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($flashSuccess): ?>
        <div class="mb-6 p-4 rounded-xl bg-emerald-950/60 border border-emerald-800/80 text-emerald-300 text-sm flex items-center space-x-2">
            <span>✅</span>
            <span><?= htmlspecialchars($flashSuccess) ?></span>
        </div>
    <?php endif; ?>

    <!-- Notifications Feed -->
    <?php if (empty($notifications)): ?>
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-12 text-center shadow-xl">
            <div class="w-16 h-16 mx-auto rounded-2xl bg-slate-800/80 flex items-center justify-center text-2xl text-slate-400 mb-4">
                🔕
            </div>
            <h3 class="text-base font-bold text-white">No notifications yet</h3>
            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                You're all caught up! You will receive updates here whenever a booking is created, approved, paid, or cancelled.
            </p>
        </div>
    <?php else: ?>
        <div class="space-y-3">
            <?php foreach ($notifications as $n): 
                $isRead = $n->isRead();
                $type = $n->getType();
                
                switch ($type) {
                    case 'Rental':
                        $icon = '📦';
                        $colorClass = 'text-blue-400 bg-blue-500/10 border-blue-500/30';
                        break;
                    case 'Payment':
                        $icon = '💳';
                        $colorClass = 'text-emerald-400 bg-emerald-500/10 border-emerald-500/30';
                        break;
                    case 'Fine':
                        $icon = '⚠️';
                        $colorClass = 'text-amber-400 bg-amber-500/10 border-amber-500/30';
                        break;
                    case 'Dispute':
                        $icon = '⚖️';
                        $colorClass = 'text-rose-400 bg-rose-500/10 border-rose-500/30';
                        break;
                    default:
                        $icon = '🔔';
                        $colorClass = 'text-slate-400 bg-slate-500/10 border-slate-500/30';
                }
            ?>
                <div class="p-4 sm:p-5 rounded-2xl border transition flex items-start justify-between gap-4 <?= $isRead ? 'bg-slate-900/60 border-slate-800/70 text-slate-300' : 'bg-slate-900 border-indigo-500/40 text-white shadow-lg' ?>">
                    <div class="flex items-start space-x-3.5 flex-1 min-w-0">
                        <div class="w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center border <?= $colorClass ?> text-base">
                            <?= $icon ?>
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center space-x-2 mb-1">
                                <span class="text-[10px] uppercase font-bold tracking-wider px-2 py-0.5 rounded-md border <?= $colorClass ?>">
                                    <?= htmlspecialchars($type) ?>
                                </span>
                                <?php if (!$isRead): ?>
                                    <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse" title="Unread"></span>
                                <?php endif; ?>
                                <span class="text-[11px] text-slate-500">
                                    <?= date('M d, Y h:i A', strtotime($n->getCreatedAt() ?? 'now')) ?>
                                </span>
                            </div>
                            <p class="text-sm leading-relaxed <?= $isRead ? 'text-slate-300' : 'text-white font-medium' ?>">
                                <?= htmlspecialchars($n->getMessage()) ?>
                            </p>
                        </div>
                    </div>

                    <?php if (!$isRead): ?>
                        <form action="<?= base_url('notifications/view_notifications.php') ?>" method="POST" class="flex-shrink-0">
                            <?= csrf_field() ?>
                            <input type="hidden" name="mark_single_read" value="1">
                            <input type="hidden" name="notif_id" value="<?= $n->getNotifID() ?>">
                            <button type="submit" 
                                    title="Mark as read"
                                    class="p-1.5 text-slate-500 hover:text-slate-300 hover:bg-slate-800 rounded-lg transition text-xs">
                                ✓
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
