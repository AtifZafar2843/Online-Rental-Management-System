<?php
/**
 * Online Rental Management System (ORMS)
 * Notification Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.11, 4, 5 (Rule 11) & Synopsis Section 11.1, 13.IX (Page 31)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

class Notification {
    private ?int $notifID = null;
    private int $userID;
    private string $message;
    private string $type; // 'Rental', 'Payment', 'Fine', 'Dispute', 'System'
    private ?int $relatedID = null;
    private bool $isRead = false;
    private ?string $createdAt = null;

    private PDO $db;

    public function __construct(
        ?int $notifID = null,
        int $userID = 0,
        string $message = '',
        string $type = 'System',
        ?int $relatedID = null,
        bool $isRead = false,
        ?string $createdAt = null
    ) {
        $this->notifID = $notifID;
        $this->userID = $userID;
        $this->message = $message;
        $this->type = $type;
        $this->relatedID = $relatedID;
        $this->isRead = $isRead;
        $this->createdAt = $createdAt;

        $this->db = Database::getInstance()->getConnection();
    }

    // Getters
    public function getNotifID(): ?int { return $this->notifID; }
    public function getUserID(): int { return $this->userID; }
    public function getMessage(): string { return $this->message; }
    public function getType(): string { return $this->type; }
    public function getRelatedID(): ?int { return $this->relatedID; }
    public function isRead(): bool { return $this->isRead; }
    public function getCreatedAt(): ?string { return $this->createdAt; }

    /**
     * Mark notification as read (Synopsis Section 11.1).
     */
    public function markAsRead(): void {
        if ($this->notifID) {
            $stmt = $this->db->prepare("UPDATE `NOTIFICATION` SET is_read = 1 WHERE notif_id = :id");
            $stmt->execute(['id' => $this->notifID]);
            $this->isRead = true;
        }
    }

    /**
     * Persist notification into database (Synopsis Section 11.1: send()).
     */
    public function send(): bool {
        $stmt = $this->db->prepare("
            INSERT INTO `NOTIFICATION` (`user_id`, `message`, `type`, `related_id`, `is_read`, `created_at`) 
            VALUES (:user_id, :message, :type, :related_id, :is_read, NOW())
        ");
        $success = $stmt->execute([
            'user_id'    => $this->userID,
            'message'    => $this->message,
            'type'       => $this->type,
            'related_id' => $this->relatedID,
            'is_read'    => $this->isRead ? 1 : 0
        ]);
        if ($success) {
            $this->notifID = (int) $this->db->lastInsertId();
            $this->createdAt = date('Y-m-d H:i:s');
        }
        return $success;
    }

    /**
     * Delete a single notification with user isolation.
     */
    public static function delete(int $notifId, int $userId): bool {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("DELETE FROM `NOTIFICATION` WHERE notif_id = :nid AND user_id = :uid");
        return $stmt->execute(['nid' => $notifId, 'uid' => $userId]);
    }

    /**
     * Static helper to quickly send a notification to a user.
     */
    public static function create(int $userId, string $message, string $type = 'System', ?int $relatedId = null): ?Notification {
        $notif = new self(null, $userId, $message, $type, $relatedId, false);
        if ($notif->send()) {
            return $notif;
        }
        return null;
    }

    /**
     * Find a notification by its ID.
     */
    public static function findById(int $id): ?Notification {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM `NOTIFICATION` WHERE notif_id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        return new self(
            (int) $row['notif_id'],
            (int) $row['user_id'],
            $row['message'],
            $row['type'],
            $row['related_id'] ? (int) $row['related_id'] : null,
            (bool) $row['is_read'],
            $row['created_at']
        );
    }

    /**
     * Fetch unread notifications count for a user.
     */
    public static function countUnread(int $userId): int {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM `NOTIFICATION` WHERE user_id = :uid AND is_read = 0");
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Fetch notifications for a user with optional type and read-status filtering.
     */
    public static function findByUser(int $userId, ?string $type = null, ?bool $isRead = null, int $limit = 50, ?int $sinceId = null): array {
        $db = Database::getInstance()->getConnection();
        
        $sql = "SELECT * FROM `NOTIFICATION` WHERE user_id = :uid";
        $params = ['uid' => $userId];

        if ($type !== null && $type !== 'All' && in_array($type, ['Rental', 'Payment', 'Fine', 'Dispute', 'System'], true)) {
            $sql .= " AND type = :type";
            $params['type'] = $type;
        }

        if ($isRead !== null) {
            $sql .= " AND is_read = :is_read";
            $params['is_read'] = $isRead ? 1 : 0;
        }

        if ($sinceId !== null && $sinceId > 0) {
            $sql .= " AND notif_id > :since_id";
            $params['since_id'] = $sinceId;
        }

        $sql .= " ORDER BY notif_id DESC LIMIT " . (int) $limit;

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $notifications = [];
        foreach ($rows as $row) {
            $notifications[] = new self(
                (int) $row['notif_id'],
                (int) $row['user_id'],
                $row['message'],
                $row['type'],
                $row['related_id'] ? (int) $row['related_id'] : null,
                (bool) $row['is_read'],
                $row['created_at']
            );
        }
        return $notifications;
    }

    /**
     * Mark all notifications as read for a user.
     */
    public static function markAllReadByUser(int $userId): bool {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("UPDATE `NOTIFICATION` SET is_read = 1 WHERE user_id = :uid AND is_read = 0");
        return $stmt->execute(['uid' => $userId]);
    }

    /**
     * Rule 11 & Synopsis Section 13.IX:
     * Automated Rental Due Date Reminder (1 day before scheduled end_date).
     * Scans for Active rentals ending tomorrow, sends reminder to Renter if not already sent.
     */
    public static function sendDueDateReminders(): int {
        $db = Database::getInstance()->getConnection();
        
        // Find Active rentals ending tomorrow
        $stmt = $db->prepare("
            SELECT r.request_id, r.renter_id, r.end_date, p.title AS product_title
            FROM `RENTAL_REQUEST` r
            JOIN `PRODUCT` p ON r.product_id = p.product_id
            WHERE r.status = 'Active' 
              AND r.end_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY)
        ");
        $stmt->execute();
        $dueRentals = $stmt->fetchAll();

        $sentCount = 0;
        foreach ($dueRentals as $rental) {
            $reqId = (int) $rental['request_id'];
            $renterId = (int) $rental['renter_id'];
            $prodTitle = $rental['product_title'];
            $endDate = date('M d, Y', strtotime($rental['end_date']));

            // Idempotency: Verify if reminder was already dispatched for this request
            $chkStmt = $db->prepare("
                SELECT COUNT(*) FROM `NOTIFICATION` 
                WHERE user_id = :uid 
                  AND type = 'Rental' 
                  AND related_id = :req_id 
                  AND message LIKE '%due tomorrow%'
            ");
            $chkStmt->execute(['uid' => $renterId, 'req_id' => $reqId]);
            if ((int) $chkStmt->fetchColumn() === 0) {
                $msg = "Reminder: Your rental for '{$prodTitle}' (Request #{$reqId}) is due tomorrow on {$endDate}. Please ensure the item is inspected and returned on time.";
                self::create($renterId, $msg, 'Rental', $reqId);
                $sentCount++;
            }
        }

        return $sentCount;
    }

    /**
     * Format created_at to human-readable relative time (e.g. "Just now", "10m ago", "2h ago", "Yesterday").
     */
    public function getFormattedTime(): string {
        if (!$this->createdAt) return 'Just now';
        
        $timestamp = strtotime($this->createdAt);
        $diff = time() - $timestamp;

        if ($diff < 60) {
            return 'Just now';
        } elseif ($diff < 3600) {
            $mins = max(1, (int) floor($diff / 60));
            return "{$mins}m ago";
        } elseif ($diff < 86400) {
            $hours = (int) floor($diff / 3600);
            return "{$hours}h ago";
        } elseif ($diff < 172800) {
            return 'Yesterday';
        } else {
            return date('M d, Y', $timestamp);
        }
    }

    /**
     * Compute contextual target URL based on notification type and current user role.
     */
    public function getTargetUrl(?string $userRole = null): string {
        $role = $userRole ?: current_role();
        switch ($this->type) {
            case 'Rental':
                return ($role === 'Owner') ? 'owner/manage_requests.php' : 'renter/my_rentals.php';
            case 'Payment':
                if ($role === 'Admin') {
                    return 'admin/reports.php?type=revenue';
                }
                // If notification indicates payment received by owner or user is in owner mode
                if ($role === 'Owner' || stripos($this->message, 'received') !== false) {
                    return 'owner/manage_requests.php';
                }
                return 'renter/my_rentals.php';
            case 'Fine':
                if ($role === 'Admin') {
                    return 'admin/reports.php?type=fines';
                }
                return ($role === 'Owner') ? 'owner/manage_requests.php' : 'renter/my_rentals.php';
            case 'Dispute':
                if ($role === 'Admin') {
                    return 'admin/resolve_disputes.php';
                }
                return ($role === 'Owner') ? 'owner/manage_requests.php' : 'renter/my_rentals.php';
            default:
                if ($role === 'Admin') {
                    return 'admin/dashboard.php';
                }
                return 'notifications/view_notifications.php';
        }
    }

    /**
     * Convert notification object to array representation for JSON API serialization.
     */
    public function toArray(?string $userRole = null): array {
        return [
            'notif_id'        => $this->notifID,
            'user_id'         => $this->userID,
            'message'         => $this->message,
            'type'            => $this->type,
            'related_id'      => $this->relatedID,
            'is_read'         => $this->isRead,
            'created_at'      => $this->createdAt,
            'formatted_time'  => $this->getFormattedTime(),
            'target_url'      => $this->getTargetUrl($userRole)
        ];
    }
}
