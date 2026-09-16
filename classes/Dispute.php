<?php
/**
 * Online Rental Management System (ORMS)
 * Dispute Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.12, 4, 5 (Rule 13) & Synopsis Section 11.1, 12.13, 13.X (Page 31)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/RentalRequest.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/exceptions/ORMSException.php';
require_once __DIR__ . '/exceptions/UnauthorizedActionException.php';

class Dispute {
    private ?int $disputeID = null;
    private int $requestID;
    private int $raisedByID;
    private int $againstID;
    private string $reason;
    private string $status; // 'Open', 'Under_Review', 'Resolved', 'Escalated'
    private ?string $adminNotes = null;
    private string $createdDate;
    private ?string $resolvedDate = null;

    // Display metadata
    private ?string $raisedByName = null;
    private ?string $againstName = null;
    private ?string $productTitle = null;

    private PDO $db;

    public function __construct(
        ?int $disputeID = null,
        int $requestID = 0,
        int $raisedByID = 0,
        int $againstID = 0,
        string $reason = '',
        string $status = 'Open',
        ?string $adminNotes = null,
        string $createdDate = '',
        ?string $resolvedDate = null,
        ?string $raisedByName = null,
        ?string $againstName = null,
        ?string $productTitle = null
    ) {
        $this->disputeID = $disputeID;
        $this->requestID = $requestID;
        $this->raisedByID = $raisedByID;
        $this->againstID = $againstID;
        $this->reason = $reason;
        $this->status = $status ?: 'Open';
        $this->adminNotes = $adminNotes;
        $this->createdDate = $createdDate ?: date('Y-m-d H:i:s');
        $this->resolvedDate = $resolvedDate;
        $this->raisedByName = $raisedByName;
        $this->againstName = $againstName;
        $this->productTitle = $productTitle;

        $this->db = Database::getInstance()->getConnection();
    }

    // Getters
    public function getDisputeID(): ?int { return $this->disputeID; }
    public function getRequestID(): int { return $this->requestID; }
    public function getRaisedByID(): int { return $this->raisedByID; }
    public function getAgainstID(): int { return $this->againstID; }
    public function getReason(): string { return $this->reason; }
    public function getStatus(): string { return $this->status; }
    public function getAdminNotes(): ?string { return $this->adminNotes; }
    public function getCreatedDate(): string { return $this->createdDate; }
    public function getResolvedDate(): ?string { return $this->resolvedDate; }
    public function getRaisedByName(): ?string { return $this->raisedByName; }
    public function getAgainstName(): ?string { return $this->againstName; }
    public function getProductTitle(): ?string { return $this->productTitle; }

    /**
     * Rule 13 & Synopsis Section 13.X:
     * Either Owner or Renter can file a dispute against the other for any Completed or Active rental.
     * Auto-detects opposing party and dispatches notifications to Admin and Opposing user.
     */
    public static function fileDispute(int $requestId, int $raisedBy, string $reason): self {
        $db = Database::getInstance()->getConnection();

        $cleanReason = trim($reason);
        if (empty($cleanReason)) {
            throw new ORMSException("A detailed reason is required to file a dispute.");
        }

        // 1. Fetch Rental Request and Product Details
        $stmtReq = $db->prepare("
            SELECT r.request_id, r.product_id, r.renter_id, r.status, p.owner_id, p.title AS product_title,
                   u_renter.name AS renter_name, u_owner.name AS owner_name
            FROM `RENTAL_REQUEST` r
            JOIN `PRODUCT` p ON r.product_id = p.product_id
            JOIN `USER` u_renter ON r.renter_id = u_renter.user_id
            JOIN `USER` u_owner ON p.owner_id = u_owner.user_id
            WHERE r.request_id = :rid
            LIMIT 1
        ");
        $stmtReq->execute(['rid' => $requestId]);
        $req = $stmtReq->fetch();

        if (!$req) {
            throw new ORMSException("Rental request #{$requestId} does not exist.");
        }

        // Rule 13: Must be 'Active' or 'Completed'
        if (!in_array($req['status'], ['Active', 'Completed'], true)) {
            throw new ORMSException("Disputes can only be filed against Active or Completed rentals. Current status: {$req['status']}.");
        }

        // 2. Validate raiser and determine opposing party (against)
        $renterId = (int) $req['renter_id'];
        $ownerId = (int) $req['owner_id'];

        if ($raisedBy === $renterId) {
            $againstId = $ownerId;
            $raiserName = $req['renter_name'];
            $againstName = $req['owner_name'];
        } elseif ($raisedBy === $ownerId) {
            $againstId = $renterId;
            $raiserName = $req['owner_name'];
            $againstName = $req['renter_name'];
        } else {
            throw new UnauthorizedActionException("You are not a recognized party (Owner or Renter) for this rental request.");
        }

        // 3. Check for existing open/pending disputes by this user for this request
        $stmtChk = $db->prepare("
            SELECT dispute_id FROM `DISPUTE` 
            WHERE request_id = :rid AND raised_by = :raised_by AND status IN ('Open', 'Under_Review', 'Escalated')
            LIMIT 1
        ");
        $stmtChk->execute(['rid' => $requestId, 'raised_by' => $raisedBy]);
        if ($stmtChk->fetch()) {
            throw new ORMSException("An active dispute is already open for rental request #{$requestId}. Please wait for administrator review.");
        }

        // 4. Insert DISPUTE record
        $stmtInsert = $db->prepare("
            INSERT INTO `DISPUTE` (`request_id`, `raised_by`, `against`, `reason`, `status`, `created_date`) 
            VALUES (:rid, :raised_by, :against, :reason, 'Open', NOW())
        ");
        $stmtInsert->execute([
            'rid'       => $requestId,
            'raised_by' => $raisedBy,
            'against'   => $againstId,
            'reason'    => $cleanReason
        ]);

        $disputeId = (int) $db->lastInsertId();

        // 5. Multi-Party Notifications per Synopsis 13.IX (Page 31):
        // Trigger: Dispute Filed -> Recipient: Admin + Opposing User -> Type: Dispute
        
        // Notify Opposing User
        $opposingMsg = "A formal dispute has been raised against you by {$raiserName} for Rental #{$requestId} ('{$req['product_title']}'): \"" . mb_strimwidth($cleanReason, 0, 70, '...') . "\". An administrator has been assigned to review.";
        Notification::create($againstId, $opposingMsg, 'Dispute', $disputeId);

        // Notify Admins
        $stmtAdmin = $db->query("SELECT admin_id FROM `ADMIN` WHERE status = 'Active'");
        $adminIds = $stmtAdmin->fetchAll(PDO::FETCH_COLUMN);
        foreach ($adminIds as $admId) {
            // Note: Notification table user_id FK references USER. If admins have a mirror user or general notification,
            // we check if admin_id maps or record in system log.
            // For user-facing notification, ensure user exists.
            $adminUserStmt = $db->prepare("SELECT user_id FROM `USER` WHERE email = (SELECT email FROM `ADMIN` WHERE admin_id = :aid)");
            $adminUserStmt->execute(['aid' => $admId]);
            $adminUserId = $adminUserStmt->fetchColumn();
            if ($adminUserId) {
                Notification::create((int) $adminUserId, "New Dispute #{$disputeId} filed on Rental #{$requestId} by {$raiserName}: '{$req['product_title']}'", 'Dispute', $disputeId);
            }
        }

        return new self(
            $disputeId,
            $requestId,
            $raisedBy,
            $againstId,
            $cleanReason,
            'Open',
            null,
            date('Y-m-d H:i:s'),
            null,
            $raiserName,
            $againstName,
            $req['product_title']
        );
    }

    /**
     * Rule 13 & Synopsis Section 13.X:
     * Admin resolves dispute with mandatory admin_notes.
     * Status updated to 'Resolved'; both parties notified.
     * Optionally waives fine if related to a fine dispute.
     */
    public function resolve(string $adminNotes, bool $waiveFine = false): bool {
        $cleanNotes = trim($adminNotes);
        if (empty($cleanNotes)) {
            throw new ORMSException("Administrator notes are mandatory when resolving a dispute.");
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                UPDATE `DISPUTE` 
                SET status = 'Resolved', admin_notes = :notes, resolved_date = NOW() 
                WHERE dispute_id = :id
            ");
            $success = $stmt->execute([
                'notes' => $cleanNotes,
                'id'    => $this->disputeID
            ]);

            if (!$success) {
                throw new ORMSException("Failed to update dispute record.");
            }

            // Optional fine waiver
            if ($waiveFine) {
                $stmtWaive = $this->db->prepare("
                    UPDATE `FINE` 
                    SET status = 'Waived' 
                    WHERE request_id = :rid AND status IN ('Unpaid', 'Pending', 'Deducted_From_Deposit')
                ");
                $stmtWaive->execute(['rid' => $this->requestID]);
            }

            $this->status = 'Resolved';
            $this->adminNotes = $cleanNotes;
            $this->resolvedDate = date('Y-m-d H:i:s');

            // Notify both parties per Synopsis 13.IX (Page 31)
            $resolutionMsg = "Dispute #{$this->disputeID} (Rental #{$this->requestID}) has been resolved by Administrator. Resolution notes: \"{$cleanNotes}\"" . ($waiveFine ? " Any pending fines have been waived." : "");
            
            Notification::create($this->raisedByID, $resolutionMsg, 'Dispute', $this->disputeID);
            Notification::create($this->againstID, $resolutionMsg, 'Dispute', $this->disputeID);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Mark dispute as Under Review.
     */
    public function underReview(?string $notes = null): bool {
        $stmt = $this->db->prepare("
            UPDATE `DISPUTE` 
            SET status = 'Under_Review', admin_notes = COALESCE(:notes, admin_notes)
            WHERE dispute_id = :id
        ");
        $success = $stmt->execute([
            'notes' => $notes ? trim($notes) : null,
            'id'    => $this->disputeID
        ]);
        if ($success) {
            $this->status = 'Under_Review';
            if ($notes) $this->adminNotes = trim($notes);
        }
        return $success;
    }

    /**
     * Escalate dispute (Synopsis Section 11.1 Class Diagram).
     */
    public function escalate(?string $notes = null): bool {
        $stmt = $this->db->prepare("
            UPDATE `DISPUTE` 
            SET status = 'Escalated', admin_notes = COALESCE(:notes, admin_notes)
            WHERE dispute_id = :id
        ");
        $success = $stmt->execute([
            'notes' => $notes ? trim($notes) : null,
            'id'    => $this->disputeID
        ]);
        if ($success) {
            $this->status = 'Escalated';
            if ($notes) $this->adminNotes = trim($notes);
        }
        return $success;
    }

    /**
     * Find dispute by ID.
     */
    public static function findById(int $id): ?self {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT d.*, 
                   u_raised.name AS raised_by_name, 
                   u_against.name AS against_name,
                   p.title AS product_title
            FROM `DISPUTE` d
            JOIN `USER` u_raised ON d.raised_by = u_raised.user_id
            JOIN `USER` u_against ON d.against = u_against.user_id
            JOIN `RENTAL_REQUEST` r ON d.request_id = r.request_id
            JOIN `PRODUCT` p ON r.product_id = p.product_id
            WHERE d.dispute_id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        return new self(
            (int) $row['dispute_id'],
            (int) $row['request_id'],
            (int) $row['raised_by'],
            (int) $row['against'],
            $row['reason'],
            $row['status'],
            $row['admin_notes'],
            $row['created_date'],
            $row['resolved_date'],
            $row['raised_by_name'],
            $row['against_name'],
            $row['product_title']
        );
    }

    /**
     * Find disputes for a rental request.
     */
    public static function findByRequest(int $requestId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT d.*, 
                   u_raised.name AS raised_by_name, 
                   u_against.name AS against_name,
                   p.title AS product_title
            FROM `DISPUTE` d
            JOIN `USER` u_raised ON d.raised_by = u_raised.user_id
            JOIN `USER` u_against ON d.against = u_against.user_id
            JOIN `RENTAL_REQUEST` r ON d.request_id = r.request_id
            JOIN `PRODUCT` p ON r.product_id = p.product_id
            WHERE d.request_id = :rid
            ORDER BY d.dispute_id DESC
        ");
        $stmt->execute(['rid' => $requestId]);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new self(
                (int) $row['dispute_id'],
                (int) $row['request_id'],
                (int) $row['raised_by'],
                (int) $row['against'],
                $row['reason'],
                $row['status'],
                $row['admin_notes'],
                $row['created_date'],
                $row['resolved_date'],
                $row['raised_by_name'],
                $row['against_name'],
                $row['product_title']
            );
        }
        return $results;
    }

    /**
     * Find all disputes raised by or against a user.
     */
    public static function findByUser(int $userId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT d.*, 
                   u_raised.name AS raised_by_name, 
                   u_against.name AS against_name,
                   p.title AS product_title
            FROM `DISPUTE` d
            JOIN `USER` u_raised ON d.raised_by = u_raised.user_id
            JOIN `USER` u_against ON d.against = u_against.user_id
            JOIN `RENTAL_REQUEST` r ON d.request_id = r.request_id
            JOIN `PRODUCT` p ON r.product_id = p.product_id
            WHERE d.raised_by = :uid OR d.against = :uid
            ORDER BY d.dispute_id DESC
        ");
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new self(
                (int) $row['dispute_id'],
                (int) $row['request_id'],
                (int) $row['raised_by'],
                (int) $row['against'],
                $row['reason'],
                $row['status'],
                $row['admin_notes'],
                $row['created_date'],
                $row['resolved_date'],
                $row['raised_by_name'],
                $row['against_name'],
                $row['product_title']
            );
        }
        return $results;
    }

    /**
     * Find all disputes with optional status filter (Admin portal).
     */
    public static function findAll(?string $status = null): array {
        $db = Database::getInstance()->getConnection();
        $sql = "
            SELECT d.*, 
                   u_raised.name AS raised_by_name, 
                   u_against.name AS against_name,
                   p.title AS product_title
            FROM `DISPUTE` d
            JOIN `USER` u_raised ON d.raised_by = u_raised.user_id
            JOIN `USER` u_against ON d.against = u_against.user_id
            JOIN `RENTAL_REQUEST` r ON d.request_id = r.request_id
            JOIN `PRODUCT` p ON r.product_id = p.product_id
        ";
        $params = [];
        if ($status !== null && $status !== 'All') {
            $sql .= " WHERE d.status = :st";
            $params['st'] = $status;
        }
        $sql .= " ORDER BY d.dispute_id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new self(
                (int) $row['dispute_id'],
                (int) $row['request_id'],
                (int) $row['raised_by'],
                (int) $row['against'],
                $row['reason'],
                $row['status'],
                $row['admin_notes'],
                $row['created_date'],
                $row['resolved_date'],
                $row['raised_by_name'],
                $row['against_name'],
                $row['product_title']
            );
        }
        return $results;
    }

    /**
     * Count disputes grouped by status.
     */
    public static function countByStatus(): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->query("
            SELECT status, COUNT(*) as count 
            FROM `DISPUTE` 
            GROUP BY status
        ");
        $counts = [
            'All'          => 0,
            'Open'         => 0,
            'Under_Review' => 0,
            'Escalated'    => 0,
            'Resolved'     => 0
        ];
        foreach ($stmt->fetchAll() as $row) {
            $counts[$row['status']] = (int) $row['count'];
            $counts['All'] += (int) $row['count'];
        }
        return $counts;
    }
}
