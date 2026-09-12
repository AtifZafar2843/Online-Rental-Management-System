<?php
/**
 * Online Rental Management System (ORMS)
 * Notification Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1
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
     * Mark notification as read.
     */
    public function markAsRead(): void {
        if ($this->notifID) {
            $stmt = $this->db->prepare("UPDATE `NOTIFICATION` SET is_read = 1 WHERE notif_id = :id");
            $stmt->execute(['id' => $this->notifID]);
            $this->isRead = true;
        }
    }

    /**
     * Persist notification into database.
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
        }
        return $success;
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
     * Fetch unread notifications count for a user.
     */
    public static function countUnread(int $userId): int {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM `NOTIFICATION` WHERE user_id = :uid AND is_read = 0");
        $stmt->execute(['uid' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Fetch all notifications for a user.
     */
    public static function findByUser(int $userId, int $limit = 30): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT * FROM `NOTIFICATION` 
            WHERE user_id = :uid 
            ORDER BY notif_id DESC 
            LIMIT {$limit}
        ");
        $stmt->execute(['uid' => $userId]);
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
}
