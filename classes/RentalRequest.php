<?php
/**
 * Online Rental Management System (ORMS)
 * RentalRequest Entity Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4, Section 5 (Rules 5, 6, 7), Section 9 & Synopsis 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/exceptions/ORMSException.php';
require_once __DIR__ . '/exceptions/ProductUnavailableException.php';
require_once __DIR__ . '/exceptions/InvalidDateRangeException.php';
require_once __DIR__ . '/Notification.php';

class RentalRequest {
    private ?int $requestID = null;
    private int $productID;
    private int $renterID;
    private string $startDate;
    private string $endDate;
    private string $status = 'Pending';
    private ?string $message = null;
    private ?string $requestDate = null;
    private ?string $cancellationReason = null;

    // Relational Joined Properties (not stored in RENTAL_REQUEST to maintain 3NF)
    private string $productTitle = '';
    private string $productLocation = '';
    private float $rentPerDay = 0.00;
    private float $securityDeposit = 0.00;
    private int $ownerID = 0;
    private string $ownerName = '';
    private string $renterName = '';
    private string $renterPhone = '';
    private string $renterEmail = '';
    private string $primaryImage = 'assets/img/no-image.svg';

    private PDO $db;

    public function __construct(
        ?int $requestID = null,
        int $productID = 0,
        int $renterID = 0,
        string $startDate = '',
        string $endDate = '',
        string $status = 'Pending',
        ?string $message = null,
        ?string $requestDate = null,
        ?string $cancellationReason = null
    ) {
        $this->requestID = $requestID;
        $this->productID = $productID;
        $this->renterID = $renterID;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->status = $status;
        $this->message = $message;
        $this->requestDate = $requestDate;
        $this->cancellationReason = $cancellationReason;

        $this->db = Database::getInstance()->getConnection();
    }

    // Getters and Setters
    public function getRequestID(): ?int { return $this->requestID; }
    public function getProductID(): int { return $this->productID; }
    public function getRenterID(): int { return $this->renterID; }
    public function getStartDate(): string { return $this->startDate; }
    public function getEndDate(): string { return $this->endDate; }
    public function getStatus(): string { return $this->status; }
    public function getMessage(): ?string { return $this->message; }
    public function getRequestDate(): ?string { return $this->requestDate; }
    public function getCancellationReason(): ?string { return $this->cancellationReason; }

    public function getProductTitle(): string { return $this->productTitle; }
    public function setProductTitle(string $t): void { $this->productTitle = $t; }

    public function getProductLocation(): string { return $this->productLocation; }
    public function setProductLocation(string $l): void { $this->productLocation = $l; }

    public function getRentPerDay(): float { return $this->rentPerDay; }
    public function setRentPerDay(float $r): void { $this->rentPerDay = $r; }

    public function getSecurityDeposit(): float { return $this->securityDeposit; }
    public function setSecurityDeposit(float $s): void { $this->securityDeposit = $s; }

    public function getOwnerID(): int { return $this->ownerID; }
    public function setOwnerID(int $oid): void { $this->ownerID = $oid; }

    public function getOwnerName(): string { return $this->ownerName; }
    public function setOwnerName(string $name): void { $this->ownerName = $name; }

    public function getRenterName(): string { return $this->renterName; }
    public function setRenterName(string $name): void { $this->renterName = $name; }

    public function getRenterPhone(): string { return $this->renterPhone; }
    public function setRenterPhone(string $phone): void { $this->renterPhone = $phone; }

    public function getRenterEmail(): string { return $this->renterEmail; }
    public function setRenterEmail(string $email): void { $this->renterEmail = $email; }

    public function getPrimaryImage(): string { return $this->primaryImage; }
    public function setPrimaryImage(string $img): void { $this->primaryImage = $img; }

    /**
     * Compute total rental days dynamically from start and end dates.
     * (Synopsis Section 11.1 & Prompt Guide Rule 7: Computed at runtime, not stored in DB)
     */
    public function getTotalDays(): int {
        try {
            $start = new DateTime($this->startDate);
            $end = new DateTime($this->endDate);
            $diff = $start->diff($end);
            return max(1, (int) $diff->days);
        } catch (Exception $e) {
            return 1;
        }
    }

    /**
     * Compute total rental amount dynamically from totalDays * rentPerDay.
     * (Synopsis Section 11.1 & Prompt Guide Rule 7: Computed at runtime, not stored in RENTAL_REQUEST)
     */
    public function getTotalAmount(): float {
        return round($this->getTotalDays() * $this->rentPerDay, 2);
    }

    /**
     * Approve rental request (Owner action).
     * Sets status to 'Approved' and triggers notification to renter.
     */
    public function approve(): bool {
        if ($this->status !== 'Pending') {
            throw new ORMSException("Only pending rental requests can be approved. Current status: {$this->status}.");
        }

        $stmt = $this->db->prepare("
            UPDATE `RENTAL_REQUEST` 
            SET status = 'Approved' 
            WHERE request_id = :id AND status = 'Pending'
        ");
        $success = $stmt->execute(['id' => $this->requestID]);

        if ($success && $stmt->rowCount() > 0) {
            $this->status = 'Approved';
            // Notify Renter
            Notification::create(
                $this->renterID,
                "Your rental request for '{$this->productTitle}' has been approved! Please proceed with payment to confirm your booking.",
                'Rental',
                $this->requestID
            );
            return true;
        }
        return false;
    }

    /**
     * Reject rental request with mandatory reason (Owner action).
     */
    public function reject(string $reason): bool {
        if ($this->status !== 'Pending') {
            throw new ORMSException("Only pending requests can be rejected. Current status: {$this->status}.");
        }

        $cleanReason = trim($reason);
        if (empty($cleanReason)) {
            $cleanReason = "Request declined by owner.";
        }

        $stmt = $this->db->prepare("
            UPDATE `RENTAL_REQUEST` 
            SET status = 'Rejected', cancellation_reason = :reason 
            WHERE request_id = :id AND status = 'Pending'
        ");
        $success = $stmt->execute([
            'reason' => $cleanReason,
            'id'     => $this->requestID
        ]);

        if ($success && $stmt->rowCount() > 0) {
            $this->status = 'Rejected';
            $this->cancellationReason = $cleanReason;
            // Notify Renter
            Notification::create(
                $this->renterID,
                "Your rental request for '{$this->productTitle}' was rejected. Reason: {$cleanReason}",
                'Rental',
                $this->requestID
            );
            return true;
        }
        return false;
    }

    /**
     * Cancel rental request (Renter or Owner action per Section 5 Rule 6).
     */
    public function cancel(string $reason = ''): bool {
        $allowedStatuses = ['Pending', 'Approved'];
        if (!in_array($this->status, $allowedStatuses, true)) {
            throw new ORMSException("Cannot cancel rental in '{$this->status}' status.");
        }

        $cleanReason = trim($reason);
        if (empty($cleanReason)) {
            $cleanReason = "Cancelled by renter.";
        }

        $stmt = $this->db->prepare("
            UPDATE `RENTAL_REQUEST` 
            SET status = 'Cancelled', cancellation_reason = :reason 
            WHERE request_id = :id AND status IN ('Pending', 'Approved')
        ");
        $success = $stmt->execute([
            'reason' => $cleanReason,
            'id'     => $this->requestID
        ]);

        if ($success && $stmt->rowCount() > 0) {
            $this->status = 'Cancelled';
            $this->cancellationReason = $cleanReason;

            // Notify Owner
            Notification::create(
                $this->ownerID,
                "Rental request #{$this->requestID} for '{$this->productTitle}' was cancelled. Reason: {$cleanReason}",
                'Rental',
                $this->requestID
            );
            return true;
        }
        return false;
    }

    /**
     * Concurrency-safe atomic booking submission with SELECT ... FOR UPDATE.
     * Prevents race conditions on overlapping booking dates (Section 5 Rule 5 & Section 9).
     */
    public static function createWithLock(
        int $productId,
        int $renterId,
        string $startDate,
        string $endDate,
        ?string $message = null
    ): RentalRequest {
        $db = Database::getInstance()->getConnection();

        // 1. Date Range Validation
        $today = date('Y-m-d');
        if (strtotime($startDate) < strtotime($today)) {
            throw new InvalidDateRangeException("Start date cannot be in the past.");
        }
        if (strtotime($endDate) <= strtotime($startDate)) {
            throw new InvalidDateRangeException("End date must be strictly after the start date.");
        }

        try {
            $db->beginTransaction();

            // 2. Lock Product row with SELECT ... FOR UPDATE (Section 5 Rule 5)
            $prodLockStmt = $db->prepare("
                SELECT product_id, owner_id, title, rent_per_day, security_deposit, location, avail_status 
                FROM `PRODUCT` 
                WHERE product_id = :pid 
                FOR UPDATE
            ");
            $prodLockStmt->execute(['pid' => $productId]);
            $productRow = $prodLockStmt->fetch();

            if (!$productRow) {
                throw new ORMSException("Product not found.");
            }

            if ($productRow['avail_status'] !== 'Available') {
                throw new ProductUnavailableException("This product is currently {$productRow['avail_status']} and cannot be booked.");
            }

            if ((int) $productRow['owner_id'] === $renterId) {
                throw new ORMSException("You cannot rent your own listed product.");
            }

            // 3. Concurrency Check: Overlapping Approved or Active rentals
            $overlapStmt = $db->prepare("
                SELECT request_id 
                FROM `RENTAL_REQUEST` 
                WHERE product_id = :pid 
                  AND status IN ('Approved', 'Active') 
                  AND (start_date <= :end_date AND end_date >= :start_date) 
                FOR UPDATE
            ");
            $overlapStmt->execute([
                'pid'        => $productId,
                'start_date' => $startDate,
                'end_date'   => $endDate
            ]);

            if ($overlapStmt->fetch()) {
                throw new ProductUnavailableException("The product has already been reserved or rented for the selected dates.");
            }

            // 4. Insert RENTAL_REQUEST (3NF Compliant: No total_days or total_amount stored)
            $insertStmt = $db->prepare("
                INSERT INTO `RENTAL_REQUEST` 
                (`product_id`, `renter_id`, `start_date`, `end_date`, `status`, `message`, `request_date`) 
                VALUES 
                (:pid, :rid, :sdate, :edate, 'Pending', :msg, NOW())
            ");
            $insertStmt->execute([
                'pid'   => $productId,
                'rid'   => $renterId,
                'sdate' => $startDate,
                'edate' => $endDate,
                'msg'   => $message ? trim($message) : null
            ]);

            $newRequestId = (int) $db->lastInsertId();

            $db->commit();

            // 5. Notify the Product Owner
            $renterStmt = $db->prepare("SELECT name FROM `USER` WHERE user_id = :uid");
            $renterStmt->execute(['uid' => $renterId]);
            $renterName = (string) $renterStmt->fetchColumn();

            Notification::create(
                (int) $productRow['owner_id'],
                "New booking request received from {$renterName} for '{$productRow['title']}' ({$startDate} to {$endDate}).",
                'Rental',
                $newRequestId
            );

            // 6. Return populated instance
            $instance = new self(
                $newRequestId,
                $productId,
                $renterId,
                $startDate,
                $endDate,
                'Pending',
                $message,
                date('Y-m-d H:i:s')
            );
            $instance->setProductTitle($productRow['title']);
            $instance->setProductLocation($productRow['location']);
            $instance->setRentPerDay((float) $productRow['rent_per_day']);
            $instance->setSecurityDeposit((float) $productRow['security_deposit']);
            $instance->setOwnerID((int) $productRow['owner_id']);
            $instance->setRenterName($renterName);

            return $instance;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Find a RentalRequest by its ID with full joined data.
     */
    public static function findById(int $id): ?RentalRequest {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT rr.*, 
                   p.title AS product_title, p.location AS product_location, p.rent_per_day, p.security_deposit, p.owner_id,
                   ow.name AS owner_name,
                   rt.name AS renter_name, rt.phone AS renter_phone, rt.email AS renter_email,
                   (SELECT image_path FROM `PRODUCT_IMAGES` pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS primary_image
            FROM `RENTAL_REQUEST` rr
            JOIN `PRODUCT` p ON rr.product_id = p.product_id
            JOIN `USER` ow ON p.owner_id = ow.user_id
            JOIN `USER` rt ON rr.renter_id = rt.user_id
            WHERE rr.request_id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) return null;

        $rr = new self(
            (int) $row['request_id'],
            (int) $row['product_id'],
            (int) $row['renter_id'],
            $row['start_date'],
            $row['end_date'],
            $row['status'],
            $row['message'],
            $row['request_date'],
            $row['cancellation_reason']
        );
        $rr->setProductTitle($row['product_title']);
        $rr->setProductLocation($row['product_location']);
        $rr->setRentPerDay((float) $row['rent_per_day']);
        $rr->setSecurityDeposit((float) $row['security_deposit']);
        $rr->setOwnerID((int) $row['owner_id']);
        $rr->setOwnerName($row['owner_name']);
        $rr->setRenterName($row['renter_name']);
        $rr->setRenterPhone($row['renter_phone']);
        $rr->setRenterEmail($row['renter_email']);
        $rr->setPrimaryImage($row['primary_image'] ?: 'assets/img/no-image.svg');

        return $rr;
    }

    /**
     * Find all requests made by a specific renter.
     */
    public static function findByRenter(int $renterId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT rr.*, 
                   p.title AS product_title, p.location AS product_location, p.rent_per_day, p.security_deposit, p.owner_id,
                   ow.name AS owner_name,
                   (SELECT image_path FROM `PRODUCT_IMAGES` pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS primary_image
            FROM `RENTAL_REQUEST` rr
            JOIN `PRODUCT` p ON rr.product_id = p.product_id
            JOIN `USER` ow ON p.owner_id = ow.user_id
            WHERE rr.renter_id = :rid
            ORDER BY rr.request_id DESC
        ");
        $stmt->execute(['rid' => $renterId]);
        $rows = $stmt->fetchAll();

        $list = [];
        foreach ($rows as $row) {
            $rr = new self(
                (int) $row['request_id'],
                (int) $row['product_id'],
                (int) $row['renter_id'],
                $row['start_date'],
                $row['end_date'],
                $row['status'],
                $row['message'],
                $row['request_date'],
                $row['cancellation_reason']
            );
            $rr->setProductTitle($row['product_title']);
            $rr->setProductLocation($row['product_location']);
            $rr->setRentPerDay((float) $row['rent_per_day']);
            $rr->setSecurityDeposit((float) $row['security_deposit']);
            $rr->setOwnerID((int) $row['owner_id']);
            $rr->setOwnerName($row['owner_name']);
            $rr->setPrimaryImage($row['primary_image'] ?: 'assets/img/no-image.svg');
            $list[] = $rr;
        }
        return $list;
    }

    /**
     * Find all requests for products owned by a specific owner.
     */
    public static function findByOwner(int $ownerId, ?string $statusFilter = null): array {
        $db = Database::getInstance()->getConnection();
        $sql = "
            SELECT rr.*, 
                   p.title AS product_title, p.location AS product_location, p.rent_per_day, p.security_deposit, p.owner_id,
                   rt.name AS renter_name, rt.phone AS renter_phone, rt.email AS renter_email, rt.rating AS renter_rating,
                   (SELECT image_path FROM `PRODUCT_IMAGES` pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS primary_image
            FROM `RENTAL_REQUEST` rr
            JOIN `PRODUCT` p ON rr.product_id = p.product_id
            JOIN `USER` rt ON rr.renter_id = rt.user_id
            WHERE p.owner_id = :oid
        ";

        $params = ['oid' => $ownerId];
        if ($statusFilter && $statusFilter !== 'all') {
            $sql .= " AND rr.status = :st";
            $params['st'] = $statusFilter;
        }

        $sql .= " ORDER BY rr.request_id DESC";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $list = [];
        foreach ($rows as $row) {
            $rr = new self(
                (int) $row['request_id'],
                (int) $row['product_id'],
                (int) $row['renter_id'],
                $row['start_date'],
                $row['end_date'],
                $row['status'],
                $row['message'],
                $row['request_date'],
                $row['cancellation_reason']
            );
            $rr->setProductTitle($row['product_title']);
            $rr->setProductLocation($row['product_location']);
            $rr->setRentPerDay((float) $row['rent_per_day']);
            $rr->setSecurityDeposit((float) $row['security_deposit']);
            $rr->setOwnerID((int) $row['owner_id']);
            $rr->setRenterName($row['renter_name']);
            $rr->setRenterPhone($row['renter_phone']);
            $rr->setRenterEmail($row['renter_email']);
            $rr->setPrimaryImage($row['primary_image'] ?: 'assets/img/no-image.svg');
            $list[] = $rr;
        }
        return $list;
    }
}
