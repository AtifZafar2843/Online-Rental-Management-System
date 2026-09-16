<?php
/**
 * Online Rental Management System (ORMS)
 * Real-time Notifications Polling Endpoint
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 1 & Section 6 (Step 10: Polling-based notification system)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Notification.php';

header('Content-Type: application/json; charset=utf-8');

if (!is_logged_in()) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error'   => 'Unauthorized. Please log in.'
    ]);
    exit;
}

$userId = (int) current_user_id();
$userRole = current_role();
$sinceId = isset($_GET['since_id']) ? (int) $_GET['since_id'] : null;
$unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === '1';

try {
    $unreadCount = Notification::countUnread($userId);
    $items = Notification::findByUser($userId, null, $unreadOnly ? false : null, 10, $sinceId);

    $formattedList = [];
    foreach ($items as $item) {
        $formattedList[] = $item->toArray($userRole);
    }

    echo json_encode([
        'success'       => true,
        'unread_count'  => $unreadCount,
        'notifications' => $formattedList,
        'timestamp'     => time()
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => 'Failed to retrieve notifications: ' . $e->getMessage()
    ]);
}
