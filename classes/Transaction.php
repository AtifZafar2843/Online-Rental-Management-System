<?php
/**
 * Online Rental Management System (ORMS)
 * Transaction Entity Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.8, Section 4, Section 5 (Rules 7, 8, 11) & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/exceptions/ORMSException.php';
require_once __DIR__ . '/exceptions/PaymentFailedException.php';
require_once __DIR__ . '/exceptions/UnauthorizedActionException.php';

class Transaction {
    private ?int $transactionID = null;
    private int $requestID;
    private int $payerID;
    private float $rentalAmount;
    private float $depositAmount;
    private string $depositStatus = 'Held'; // 'Held', 'Refunded', 'Partially_Refunded', 'Forfeited'
    private string $paymentMode; // 'UPI', 'Debit_Card', 'Credit_Card', 'Net_Banking', 'COD'
    private ?string $paymentDate = null;
    private string $paymentStatus = 'Pending'; // 'Pending', 'Completed', 'Failed', 'Refunded'

    // Joined Relational Context
    private string $productTitle = '';
    private int $productID = 0;
    private int $ownerID = 0;
    private string $ownerName = '';
    private string $ownerPhone = '';
    private string $ownerEmail = '';
    private string $payerName = '';
    private string $payerEmail = '';
    private string $payerPhone = '';
    private string $startDate = '';
    private string $endDate = '';
    private int $totalDays = 0;
    private float $rentPerDay = 0.00;
    private string $primaryImage = 'assets/img/no-image.svg';

    private PDO $db;

    public function __construct(
        ?int $transactionID = null,
        int $requestID = 0,
        int $payerID = 0,
        float $rentalAmount = 0.00,
        float $depositAmount = 0.00,
        string $depositStatus = 'Held',
        string $paymentMode = 'UPI',
        ?string $paymentDate = null,
        string $paymentStatus = 'Pending'
    ) {
        $this->transactionID = $transactionID;
        $this->requestID = $requestID;
        $this->payerID = $payerID;
        $this->rentalAmount = $rentalAmount;
        $this->depositAmount = $depositAmount;
        $this->depositStatus = $depositStatus;
        $this->paymentMode = $paymentMode;
        $this->paymentDate = $paymentDate;
        $this->paymentStatus = $paymentStatus;

        $this->db = Database::getInstance()->getConnection();
    }

    // Getters
    public function getTransactionID(): ?int { return $this->transactionID; }
    public function getRequestID(): int { return $this->requestID; }
    public function getPayerID(): int { return $this->payerID; }
    public function getRentalAmount(): float { return $this->rentalAmount; }
    public function getDepositAmount(): float { return $this->depositAmount; }
    public function getTotalPaid(): float { return round($this->rentalAmount + $this->depositAmount, 2); }
    public function getDepositStatus(): string { return $this->depositStatus; }
    public function getPaymentMode(): string { return $this->paymentMode; }
    public function getPaymentDate(): ?string { return $this->paymentDate; }
    public function getPaymentStatus(): string { return $this->paymentStatus; }

    // Joined Context Getters / Setters
    public function getProductTitle(): string { return $this->productTitle; }
    public function setProductTitle(string $t): void { $this->productTitle = $t; }
    public function getProductID(): int { return $this->productID; }
    public function setProductID(int $pid): void { $this->productID = $pid; }
    public function getOwnerID(): int { return $this->ownerID; }
    public function setOwnerID(int $oid): void { $this->ownerID = $oid; }
    public function getOwnerName(): string { return $this->ownerName; }
    public function setOwnerName(string $name): void { $this->ownerName = $name; }
    public function getOwnerPhone(): string { return $this->ownerPhone; }
    public function setOwnerPhone(string $phone): void { $this->ownerPhone = $phone; }
    public function getOwnerEmail(): string { return $this->ownerEmail; }
    public function setOwnerEmail(string $email): void { $this->ownerEmail = $email; }
    public function getPayerName(): string { return $this->payerName; }
    public function setPayerName(string $name): void { $this->payerName = $name; }
    public function getPayerEmail(): string { return $this->payerEmail; }
    public function setPayerEmail(string $email): void { $this->payerEmail = $email; }
    public function getPayerPhone(): string { return $this->payerPhone; }
    public function setPayerPhone(string $phone): void { $this->payerPhone = $phone; }
    public function getStartDate(): string { return $this->startDate; }
    public function setStartDate(string $s): void { $this->startDate = $s; }
    public function getEndDate(): string { return $this->endDate; }
    public function setEndDate(string $e): void { $this->endDate = $e; }
    public function getTotalDays(): int { return $this->totalDays; }
    public function setTotalDays(int $d): void { $this->totalDays = $d; }
    public function getRentPerDay(): float { return $this->rentPerDay; }
    public function setRentPerDay(float $r): void { $this->rentPerDay = $r; }
    public function getPrimaryImage(): string { return $this->primaryImage; }
    public function setPrimaryImage(string $img): void { $this->primaryImage = $img; }

    /**
     * Process payment atomically.
     * Transitions RENTAL_REQUEST to 'Active', PRODUCT to 'Rented', stores snapshot in TRANSACTION,
     * and triggers database notifications to both Owner and Renter (Rules 7, 8, 11).
     */
    public static function process(int $requestId, int $payerId, string $paymentMode): self {
        $db = Database::getInstance()->getConnection();

        // Sanitize payment mode
        $validModes = ['UPI', 'Debit_Card', 'Credit_Card', 'Net_Banking', 'COD'];
        if (!in_array($paymentMode, $validModes, true)) {
            $paymentMode = 'UPI';
        }

        try {
            $db->beginTransaction();

            // 1. Lock RENTAL_REQUEST and join PRODUCT row
            $stmt = $db->prepare("
                SELECT rr.request_id, rr.product_id, rr.renter_id, rr.start_date, rr.end_date, rr.status AS request_status,
                       p.owner_id, p.title AS product_title, p.rent_per_day, p.security_deposit, p.avail_status,
                       u_renter.name AS renter_name, u_renter.email AS renter_email,
                       u_owner.name AS owner_name
                FROM `RENTAL_REQUEST` rr
                JOIN `PRODUCT` p ON rr.product_id = p.product_id
                JOIN `USER` u_renter ON rr.renter_id = u_renter.user_id
                JOIN `USER` u_owner ON p.owner_id = u_owner.user_id
                WHERE rr.request_id = :rid
                FOR UPDATE
            ");
            $stmt->execute(['rid' => $requestId]);
            $row = $stmt->fetch();

            if (!$row) {
                throw new PaymentFailedException("Rental request #{$requestId} not found.");
            }

            // Verify authorized payer
            if ((int) $row['renter_id'] !== $payerId) {
                throw new UnauthorizedActionException("You are not authorized to pay for rental request #{$requestId}.");
            }

            // Verify status is Approved
            if ($row['request_status'] === 'Active') {
                throw new PaymentFailedException("Payment has already been completed for this request. The rental is currently Active.");
            }

            if ($row['request_status'] !== 'Approved') {
                throw new PaymentFailedException("Only Approved rental requests can be paid for. Current status: {$row['request_status']}.");
            }

            // Verify product is not already rented out to someone else
            if ($row['avail_status'] === 'Rented') {
                throw new PaymentFailedException("This product has already been marked as Rented.");
            }

            // 2. Compute dynamic duration & snapshot amounts (Section 5 Rule 7)
            $start = new DateTime($row['start_date']);
            $end = new DateTime($row['end_date']);
            $diff = $start->diff($end);
            $totalDays = max(1, (int) $diff->days);

            $rentPerDay = (float) $row['rent_per_day'];
            $rentalAmountSnapshot = round($totalDays * $rentPerDay, 2);
            $depositAmountSnapshot = (float) $row['security_deposit'];

            // 3. Insert into TRANSACTION table
            $insStmt = $db->prepare("
                INSERT INTO `TRANSACTION` 
                (`request_id`, `payer_id`, `rental_amount`, `deposit_amount`, `deposit_status`, `payment_mode`, `payment_date`, `payment_status`) 
                VALUES 
                (:rid, :pid, :ramount, :damount, 'Held', :mode, NOW(), 'Completed')
            ");
            $insStmt->execute([
                'rid'     => $requestId,
                'pid'     => $payerId,
                'ramount' => $rentalAmountSnapshot,
                'damount' => $depositAmountSnapshot,
                'mode'    => $paymentMode
            ]);

            $newTxId = (int) $db->lastInsertId();

            // 4. Update RENTAL_REQUEST status to 'Active'
            $updReqStmt = $db->prepare("UPDATE `RENTAL_REQUEST` SET `status` = 'Active' WHERE `request_id` = :rid");
            $updReqStmt->execute(['rid' => $requestId]);

            // 5. Update PRODUCT availability status to 'Rented'
            $updProdStmt = $db->prepare("UPDATE `PRODUCT` SET `avail_status` = 'Rented' WHERE `product_id` = :pid");
            $updProdStmt->execute(['pid' => (int) $row['product_id']]);

            $db->commit();

            // 6. Trigger Event Notifications (Rule 11)
            $totalPaidFormatted = number_format($rentalAmountSnapshot + $depositAmountSnapshot, 2);

            // Notify Owner
            Notification::create(
                (int) $row['owner_id'],
                "Payment received! ₹{$totalPaidFormatted} (Rent: ₹" . number_format($rentalAmountSnapshot, 2) . ", Deposit: ₹" . number_format($depositAmountSnapshot, 2) . ") paid by {$row['renter_name']} for '{$row['product_title']}'. Booking #{$requestId} is now ACTIVE.",
                'Payment',
                $newTxId
            );

            // Notify Renter
            Notification::create(
                $payerId,
                "Payment of ₹{$totalPaidFormatted} successful for '{$row['product_title']}'! Your booking is now Active. Receipt #ORMS-REC-" . str_pad((string)$newTxId, 6, '0', STR_PAD_LEFT) . " generated.",
                'Payment',
                $newTxId
            );

            // 7. Return populated entity
            $tx = new self(
                $newTxId,
                $requestId,
                $payerId,
                $rentalAmountSnapshot,
                $depositAmountSnapshot,
                'Held',
                $paymentMode,
                date('Y-m-d H:i:s'),
                'Completed'
            );
            $tx->setProductTitle($row['product_title']);
            $tx->setProductID((int) $row['product_id']);
            $tx->setOwnerID((int) $row['owner_id']);
            $tx->setOwnerName($row['owner_name']);
            $tx->setPayerName($row['renter_name']);
            $tx->setPayerEmail($row['renter_email']);
            $tx->setStartDate($row['start_date']);
            $tx->setEndDate($row['end_date']);
            $tx->setTotalDays($totalDays);
            $tx->setRentPerDay($rentPerDay);

            return $tx;

        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            if ($e instanceof ORMSException) {
                throw $e;
            }
            throw new PaymentFailedException("Transaction failed: " . $e->getMessage(), (int)$e->getCode(), $e);
        }
    }

    /**
     * Handle security deposit refund logic per Section 5 Rule 8.
     * Clean return -> full refund, deposit_status = 'Refunded'
     * Fine exists but <= deposit -> deduct fine, refund remainder, deposit_status = 'Partially_Refunded'
     * Fine exceeds deposit -> deposit_status = 'Forfeited'
     */
    public function refundDeposit(float $fineDeduction = 0.00): bool {
        if ($this->depositStatus !== 'Held') {
            throw new ORMSException("Deposit is already in status '{$this->depositStatus}' and cannot be processed again.");
        }

        $cleanFine = max(0.00, round($fineDeduction, 2));

        if ($cleanFine <= 0.00) {
            $newStatus = 'Refunded';
        } elseif ($cleanFine < $this->depositAmount) {
            $newStatus = 'Partially_Refunded';
        } else {
            $newStatus = 'Forfeited';
        }

        $stmt = $this->db->prepare("
            UPDATE `TRANSACTION` 
            SET `deposit_status` = :st 
            WHERE `transaction_id` = :txid AND `deposit_status` = 'Held'
        ");
        $success = $stmt->execute([
            'st'   => $newStatus,
            'txid' => $this->transactionID
        ]);

        if ($success && $stmt->rowCount() > 0) {
            $this->depositStatus = $newStatus;

            // Notify Renter
            $refundAmount = max(0.00, $this->depositAmount - $cleanFine);
            $msg = "Security deposit update for Transaction #{$this->transactionID}: Status is '{$newStatus}'. ";
            if ($newStatus === 'Refunded') {
                $msg .= "Full refund of ₹" . number_format($this->depositAmount, 2) . " processed.";
            } elseif ($newStatus === 'Partially_Refunded') {
                $msg .= "Fine deduction of ₹" . number_format($cleanFine, 2) . ". Net refund: ₹" . number_format($refundAmount, 2) . ".";
            } else {
                $msg .= "Deposit of ₹" . number_format($this->depositAmount, 2) . " was forfeited to cover damages/fines.";
            }

            Notification::create($this->payerID, $msg, 'Payment', $this->transactionID);
            return true;
        }

        return false;
    }

    /**
     * Generate structured tax & rental receipt metadata for invoice generation (Synopsis 11.1).
     */
    public function getReceipt(): array {
        $receiptNo = 'ORMS-REC-' . str_pad((string)($this->transactionID ?? 0), 6, '0', STR_PAD_LEFT);
        $totalPaid = $this->getTotalPaid();

        return [
            'receipt_number' => $receiptNo,
            'transaction_id' => $this->transactionID,
            'request_id'     => $this->requestID,
            'payment_date'   => $this->paymentDate ?? date('Y-m-d H:i:s'),
            'payment_mode'   => $this->paymentMode,
            'payment_status' => $this->paymentStatus,
            'rental_amount'  => $this->rentalAmount,
            'deposit_amount' => $this->depositAmount,
            'total_paid'     => $totalPaid,
            'deposit_status' => $this->depositStatus,
            'product_id'     => $this->productID,
            'product_title'  => $this->productTitle,
            'rent_per_day'   => $this->rentPerDay,
            'total_days'     => $this->totalDays,
            'start_date'     => $this->startDate,
            'end_date'       => $this->endDate,
            'payer_id'       => $this->payerID,
            'payer_name'     => $this->payerName,
            'payer_email'    => $this->payerEmail,
            'payer_phone'    => $this->payerPhone,
            'owner_id'       => $this->ownerID,
            'owner_name'     => $this->ownerName,
            'owner_phone'    => $this->ownerPhone,
            'owner_email'    => $this->ownerEmail,
        ];
    }

    /**
     * Find Transaction by ID with full joined product and user details.
     */
    public static function findById(int $id): ?self {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT t.*, 
                   rr.start_date, rr.end_date, rr.product_id,
                   p.title AS product_title, p.rent_per_day, p.owner_id,
                   u_owner.name AS owner_name, u_owner.phone AS owner_phone, u_owner.email AS owner_email,
                   u_payer.name AS payer_name, u_payer.email AS payer_email, u_payer.phone AS payer_phone,
                   (SELECT image_path FROM `PRODUCT_IMAGES` pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS primary_image
            FROM `TRANSACTION` t
            JOIN `RENTAL_REQUEST` rr ON t.request_id = rr.request_id
            JOIN `PRODUCT` p ON rr.product_id = p.product_id
            JOIN `USER` u_owner ON p.owner_id = u_owner.user_id
            JOIN `USER` u_payer ON t.payer_id = u_payer.user_id
            WHERE t.transaction_id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) return null;

        return self::populateFromRow($row);
    }

    /**
     * Find Transaction by Request ID.
     */
    public static function findByRequest(int $requestId): ?self {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT t.*, 
                   rr.start_date, rr.end_date, rr.product_id,
                   p.title AS product_title, p.rent_per_day, p.owner_id,
                   u_owner.name AS owner_name, u_owner.phone AS owner_phone, u_owner.email AS owner_email,
                   u_payer.name AS payer_name, u_payer.email AS payer_email, u_payer.phone AS payer_phone,
                   (SELECT image_path FROM `PRODUCT_IMAGES` pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS primary_image
            FROM `TRANSACTION` t
            JOIN `RENTAL_REQUEST` rr ON t.request_id = rr.request_id
            JOIN `PRODUCT` p ON rr.product_id = p.product_id
            JOIN `USER` u_owner ON p.owner_id = u_owner.user_id
            JOIN `USER` u_payer ON t.payer_id = u_payer.user_id
            WHERE t.request_id = :rid
            ORDER BY t.transaction_id DESC
            LIMIT 1
        ");
        $stmt->execute(['rid' => $requestId]);
        $row = $stmt->fetch();

        if (!$row) return null;

        return self::populateFromRow($row);
    }

    /**
     * Find all Transactions for a specific payer (Renter).
     */
    public static function findByPayer(int $payerId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT t.*, 
                   rr.start_date, rr.end_date, rr.product_id,
                   p.title AS product_title, p.rent_per_day, p.owner_id,
                   u_owner.name AS owner_name, u_owner.phone AS owner_phone, u_owner.email AS owner_email,
                   u_payer.name AS payer_name, u_payer.email AS payer_email, u_payer.phone AS payer_phone,
                   (SELECT image_path FROM `PRODUCT_IMAGES` pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS primary_image
            FROM `TRANSACTION` t
            JOIN `RENTAL_REQUEST` rr ON t.request_id = rr.request_id
            JOIN `PRODUCT` p ON rr.product_id = p.product_id
            JOIN `USER` u_owner ON p.owner_id = u_owner.user_id
            JOIN `USER` u_payer ON t.payer_id = u_payer.user_id
            WHERE t.payer_id = :pid
            ORDER BY t.transaction_id DESC
        ");
        $stmt->execute(['pid' => $payerId]);
        $rows = $stmt->fetchAll();

        $list = [];
        foreach ($rows as $r) {
            $list[] = self::populateFromRow($r);
        }
        return $list;
    }

    /**
     * Find all Transactions for products owned by a specific owner.
     */
    public static function findByOwner(int $ownerId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT t.*, 
                   rr.start_date, rr.end_date, rr.product_id,
                   p.title AS product_title, p.rent_per_day, p.owner_id,
                   u_owner.name AS owner_name, u_owner.phone AS owner_phone, u_owner.email AS owner_email,
                   u_payer.name AS payer_name, u_payer.email AS payer_email, u_payer.phone AS payer_phone,
                   (SELECT image_path FROM `PRODUCT_IMAGES` pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS primary_image
            FROM `TRANSACTION` t
            JOIN `RENTAL_REQUEST` rr ON t.request_id = rr.request_id
            JOIN `PRODUCT` p ON rr.product_id = p.product_id
            JOIN `USER` u_owner ON p.owner_id = u_owner.user_id
            JOIN `USER` u_payer ON t.payer_id = u_payer.user_id
            WHERE p.owner_id = :oid
            ORDER BY t.transaction_id DESC
        ");
        $stmt->execute(['oid' => $ownerId]);
        $rows = $stmt->fetchAll();

        $list = [];
        foreach ($rows as $r) {
            $list[] = self::populateFromRow($r);
        }
        return $list;
    }

    /**
     * Helper to map DB row into Transaction entity.
     */
    private static function populateFromRow(array $row): self {
        $tx = new self(
            (int) $row['transaction_id'],
            (int) $row['request_id'],
            (int) $row['payer_id'],
            (float) $row['rental_amount'],
            (float) $row['deposit_amount'],
            $row['deposit_status'],
            $row['payment_mode'],
            $row['payment_date'],
            $row['payment_status']
        );

        $tx->setProductTitle($row['product_title'] ?? '');
        $tx->setProductID((int) ($row['product_id'] ?? 0));
        $tx->setOwnerID((int) ($row['owner_id'] ?? 0));
        $tx->setOwnerName($row['owner_name'] ?? '');
        $tx->setOwnerPhone($row['owner_phone'] ?? '');
        $tx->setOwnerEmail($row['owner_email'] ?? '');
        $tx->setPayerName($row['payer_name'] ?? '');
        $tx->setPayerEmail($row['payer_email'] ?? '');
        $tx->setPayerPhone($row['payer_phone'] ?? '');
        $tx->setStartDate($row['start_date'] ?? '');
        $tx->setEndDate($row['end_date'] ?? '');
        $tx->setRentPerDay((float) ($row['rent_per_day'] ?? 0.00));
        $tx->setPrimaryImage($row['primary_image'] ?: 'assets/img/no-image.svg');

        if (!empty($row['start_date']) && !empty($row['end_date'])) {
            try {
                $start = new DateTime($row['start_date']);
                $end = new DateTime($row['end_date']);
                $diff = $start->diff($end);
                $tx->setTotalDays(max(1, (int) $diff->days));
            } catch (Exception $e) {
                $tx->setTotalDays(1);
            }
        }

        return $tx;
    }
}
