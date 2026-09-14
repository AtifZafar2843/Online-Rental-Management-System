<?php
/**
 * Online Rental Management System (ORMS)
 * Fine Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.9, 4, 5 (Rule 8, 9, 11, 12) & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/fine_config.php';
require_once __DIR__ . '/RentalRequest.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/Transaction.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/exceptions/ORMSException.php';
require_once __DIR__ . '/exceptions/UnauthorizedActionException.php';

class Fine {
    private ?int $fineID;
    private int $requestID;
    private int $renterID;
    private int $transactionID;
    private string $fineType; // 'Late_Return', 'Damage', 'Lost'
    private float $amount;
    private ?int $lateDays;
    private ?float $ratePerDay;
    private string $issueDate;
    private ?string $paidDate;
    private string $status; // 'Unpaid', 'Paid', 'Waived', 'Deducted_From_Deposit'

    private PDO $db;

    public function __construct(
        ?int $fineID = null,
        int $requestID = 0,
        int $renterID = 0,
        int $transactionID = 0,
        string $fineType = 'Late_Return',
        float $amount = 0.00,
        ?int $lateDays = null,
        ?float $ratePerDay = null,
        string $issueDate = '',
        ?string $paidDate = null,
        string $status = 'Unpaid'
    ) {
        $this->fineID = $fineID;
        $this->requestID = $requestID;
        $this->renterID = $renterID;
        $this->transactionID = $transactionID;
        $this->fineType = $fineType;
        $this->amount = $amount;
        $this->lateDays = $lateDays;
        $this->ratePerDay = $ratePerDay;
        $this->issueDate = $issueDate ?: date('Y-m-d H:i:s');
        $this->paidDate = $paidDate;
        $this->status = $status;

        $this->db = Database::getInstance()->getConnection();
    }

    // Getters
    public function getFineID(): ?int { return $this->fineID; }
    public function getRequestID(): int { return $this->requestID; }
    public function getRenterID(): int { return $this->renterID; }
    public function getTransactionID(): int { return $this->transactionID; }
    public function getFineType(): string { return $this->fineType; }
    public function getAmount(): float { return $this->amount; }
    public function getLateDays(): ?int { return $this->lateDays; }
    public function getRatePerDay(): ?float { return $this->ratePerDay; }
    public function getIssueDate(): string { return $this->issueDate; }
    public function getPaidDate(): ?string { return $this->paidDate; }
    public function getStatus(): string { return $this->status; }

    // Setters
    public function setStatus(string $status): void { $this->status = $status; }
    public function setPaidDate(?string $paidDate): void { $this->paidDate = $paidDate; }
    public function setAmount(float $amount): void { $this->amount = $amount; }

    /**
     * Synopsis 11.1 & Prompt Guide Rule 9:
     * Late fine formula: amount = days * ratePerDay
     */
    public function calcLateReturnAmount(int $days, float $ratePerDay): float {
        if ($days <= 0 || $ratePerDay <= 0) {
            return 0.00;
        }
        return round($days * $ratePerDay, 2);
    }

    /**
     * Save fine to database (Insert or Update).
     */
    public function save(): bool {
        if ($this->fineID === null) {
            $stmt = $this->db->prepare("
                INSERT INTO `FINE` 
                (`request_id`, `renter_id`, `transaction_id`, `fine_type`, `amount`, `late_days`, `rate_per_day`, `issue_date`, `paid_date`, `status`)
                VALUES 
                (:request_id, :renter_id, :transaction_id, :fine_type, :amount, :late_days, :rate_per_day, :issue_date, :paid_date, :status)
            ");
            $success = $stmt->execute([
                'request_id'     => $this->requestID,
                'renter_id'      => $this->renterID,
                'transaction_id' => $this->transactionID,
                'fine_type'      => $this->fineType,
                'amount'         => $this->amount,
                'late_days'      => $this->lateDays,
                'rate_per_day'   => $this->ratePerDay,
                'issue_date'     => $this->issueDate,
                'paid_date'      => $this->paidDate,
                'status'         => $this->status
            ]);
            if ($success) {
                $this->fineID = (int) $this->db->lastInsertId();
            }
            return $success;
        } else {
            $stmt = $this->db->prepare("
                UPDATE `FINE` SET
                    `fine_type`      = :fine_type,
                    `amount`         = :amount,
                    `late_days`      = :late_days,
                    `rate_per_day`   = :rate_per_day,
                    `paid_date`      = :paid_date,
                    `status`         = :status
                WHERE `fine_id` = :fine_id
            ");
            return $stmt->execute([
                'fine_type'      => $this->fineType,
                'amount'         => $this->amount,
                'late_days'      => $this->lateDays,
                'rate_per_day'   => $this->ratePerDay,
                'paid_date'      => $this->paidDate,
                'status'         => $this->status,
                'fine_id'        => $this->fineID
            ]);
        }
    }

    /**
     * Synopsis 11.1 / Section 4:
     * Process payment for an Unpaid fine.
     */
    public function processPay(string $paymentMode = 'UPI'): bool {
        if ($this->status === 'Paid' || $this->status === 'Deducted_From_Deposit' || $this->status === 'Waived') {
            return true;
        }

        $this->db->beginTransaction();
        try {
            $this->status = 'Paid';
            $this->paidDate = date('Y-m-d H:i:s');
            $this->save();

            // Notify Renter
            Notification::create(
                $this->renterID,
                "Payment of ₹" . number_format($this->amount, 2) . " for fine #{$this->fineID} ({$this->fineType}) completed via {$paymentMode}.",
                'Fine',
                $this->fineID
            );

            // Fetch owner to notify
            $req = RentalRequest::findById($this->requestID);
            if ($req) {
                Notification::create(
                    $req->getOwnerID(),
                    "Outstanding fine of ₹" . number_format($this->amount, 2) . " on request #{$this->requestID} has been paid by renter.",
                    'Fine',
                    $this->fineID
                );
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            error_log("Fine::processPay failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Auto-detect late return details for a given RentalRequest.
     * Returns null if not late, or an array with details.
     */
    public static function autoDetectLateReturn(RentalRequest $req, ?string $actualReturnDate = null): ?array {
        $actualReturnDate = $actualReturnDate ?: date('Y-m-d');
        $endDate = $req->getEndDate();

        $returnTime = strtotime($actualReturnDate);
        $endTime = strtotime($endDate);

        if ($returnTime <= $endTime) {
            return null; // Not late
        }

        $lateDays = (int) max(1, ceil(($returnTime - $endTime) / 86400));
        $ratePerDay = get_fine_rate();
        $rawFine = round($lateDays * $ratePerDay, 2);

        // Rule 9: Capped at 2 * security_deposit
        $product = Product::findById($req->getProductID());
        $securityDeposit = $product ? $product->getSecurityDeposit() : 0.00;
        $maxCap = round(2 * $securityDeposit, 2);

        $finalFine = ($maxCap > 0 && $rawFine > $maxCap) ? $maxCap : $rawFine;
        $isCapped = ($maxCap > 0 && $rawFine > $maxCap);

        return [
            'is_late'         => true,
            'late_days'       => $lateDays,
            'rate_per_day'    => $ratePerDay,
            'raw_amount'      => $rawFine,
            'amount'          => $finalFine,
            'security_deposit'=> $securityDeposit,
            'max_cap'         => $maxCap,
            'is_capped'       => $isCapped,
            'actual_return'   => $actualReturnDate,
            'scheduled_end'   => $endDate
        ];
    }

    /**
     * Core Lifecycle Return Engine:
     * Owner confirms return, handles clean return, late fines, damage fines,
     * and executes Rule 8 deposit deductions and status transitions atomically.
     */
    public static function processReturnAndFine(
        int $requestId,
        int $ownerId,
        ?string $fineType = null,
        float $fineAmount = 0.00,
        ?string $notes = '',
        ?string $actualReturnDate = null
    ): array {
        $db = Database::getInstance()->getConnection();
        $db->beginTransaction();

        try {
            // Lock and verify RentalRequest
            $stmtReq = $db->prepare("
                SELECT rr.*, p.owner_id, p.title as product_title, p.security_deposit, p.rent_per_day
                FROM `RENTAL_REQUEST` rr
                JOIN `PRODUCT` p ON rr.product_id = p.product_id
                WHERE rr.request_id = :id
                FOR UPDATE
            ");
            $stmtReq->execute(['id' => $requestId]);
            $reqData = $stmtReq->fetch();

            if (!$reqData) {
                throw new ORMSException("Rental request #{$requestId} not found.");
            }

            if ((int) $reqData['owner_id'] !== $ownerId) {
                throw new UnauthorizedActionException("You are not authorized to confirm return for this product.");
            }

            if ($reqData['status'] !== 'Active') {
                throw new ORMSException("Return can only be confirmed for 'Active' rentals (Current: {$reqData['status']}).");
            }

            // Lock and fetch Transaction
            $stmtTx = $db->prepare("
                SELECT * FROM `TRANSACTION` 
                WHERE request_id = :req_id AND payment_status = 'Completed'
                LIMIT 1
                FOR UPDATE
            ");
            $stmtTx->execute(['req_id' => $requestId]);
            $txData = $stmtTx->fetch();

            if (!$txData) {
                throw new ORMSException("No completed transaction record found for request #{$requestId}.");
            }

            $transactionId = (int) $txData['transaction_id'];
            $depositHeld = (float) $txData['deposit_amount'];
            $renterId = (int) $reqData['renter_id'];
            $productId = (int) $reqData['product_id'];
            $productTitle = $reqData['product_title'];

            // 1. Calculate Late Return Fine
            $reqObj = RentalRequest::findById($requestId);
            $lateInfo = self::autoDetectLateReturn($reqObj, $actualReturnDate);
            $lateAmount = $lateInfo ? (float) $lateInfo['amount'] : 0.00;

            // 2. Damage / Lost fine amount (entered by Owner)
            $damageAmount = ($fineType && in_array($fineType, ['Damage', 'Lost']) && $fineAmount > 0)
                ? round($fineAmount, 2)
                : 0.00;

            $totalFine = round($lateAmount + $damageAmount, 2);

            $fineRecordsCreated = [];

            // 3. Deposit Refund & Deduction Logic (Rule 8)
            if ($totalFine == 0.00) {
                // --- CASE 1: Clean Return (100% Refund) ---
                $stmtTxUp = $db->prepare("UPDATE `TRANSACTION` SET deposit_status = 'Refunded' WHERE transaction_id = :tid");
                $stmtTxUp->execute(['tid' => $transactionId]);

                $netRefund = $depositHeld;
                $deductedAmount = 0.00;
                $unpaidFine = 0.00;

                // Notify Renter
                Notification::create(
                    $renterId,
                    "Your return of '{$productTitle}' has been confirmed. Full security deposit of ₹" . number_format($depositHeld, 2) . " has been refunded.",
                    'Rental',
                    $requestId
                );

                // Notify Owner
                Notification::create(
                    $ownerId,
                    "Return confirmed for '{$productTitle}' (Request #{$requestId}). Full deposit refunded to renter.",
                    'Rental',
                    $requestId
                );

            } elseif ($totalFine <= $depositHeld) {
                // --- CASE 2: Fine <= Deposit (Deduct from Deposit, Partial Refund) ---
                $stmtTxUp = $db->prepare("UPDATE `TRANSACTION` SET deposit_status = 'Partially_Refunded' WHERE transaction_id = :tid");
                $stmtTxUp->execute(['tid' => $transactionId]);

                $deductedAmount = $totalFine;
                $netRefund = round($depositHeld - $totalFine, 2);
                $unpaidFine = 0.00;

                // Create Late Fine Record if applicable
                if ($lateAmount > 0 && $lateInfo) {
                    $fine1 = new Fine(
                        null,
                        $requestId,
                        $renterId,
                        $transactionId,
                        'Late_Return',
                        $lateAmount,
                        $lateInfo['late_days'],
                        $lateInfo['rate_per_day'],
                        date('Y-m-d H:i:s'),
                        date('Y-m-d H:i:s'),
                        'Deducted_From_Deposit'
                    );
                    $fine1->save();
                    $fineRecordsCreated[] = $fine1;
                }

                // Create Damage / Lost Fine Record if applicable
                if ($damageAmount > 0 && $fineType) {
                    $fine2 = new Fine(
                        null,
                        $requestId,
                        $renterId,
                        $transactionId,
                        $fineType,
                        $damageAmount,
                        null,
                        null,
                        date('Y-m-d H:i:s'),
                        date('Y-m-d H:i:s'),
                        'Deducted_From_Deposit'
                    );
                    $fine2->save();
                    $fineRecordsCreated[] = $fine2;
                }

                // Notify Renter
                Notification::create(
                    $renterId,
                    "Return of '{$productTitle}' confirmed. Total fine of ₹" . number_format($totalFine, 2) . " deducted from deposit. Remaining refund: ₹" . number_format($netRefund, 2) . ".",
                    'Fine',
                    $requestId
                );

                // Notify Owner
                Notification::create(
                    $ownerId,
                    "Return confirmed for '{$productTitle}'. Fine of ₹" . number_format($totalFine, 2) . " deducted from security deposit.",
                    'Fine',
                    $requestId
                );

            } else {
                // --- CASE 3: Fine > Deposit (Deposit Forfeited, Remaining Fine Unpaid) ---
                $stmtTxUp = $db->prepare("UPDATE `TRANSACTION` SET deposit_status = 'Forfeited' WHERE transaction_id = :tid");
                $stmtTxUp->execute(['tid' => $transactionId]);

                $deductedAmount = $depositHeld;
                $netRefund = 0.00;
                $unpaidFine = round($totalFine - $depositHeld, 2);

                // Portion covered by deposit vs outstanding unpaid
                // If single late fine
                if ($lateAmount > 0 && $damageAmount == 0) {
                    $fine = new Fine(
                        null,
                        $requestId,
                        $renterId,
                        $transactionId,
                        'Late_Return',
                        $unpaidFine,
                        $lateInfo['late_days'],
                        $lateInfo['rate_per_day'],
                        date('Y-m-d H:i:s'),
                        null,
                        'Unpaid'
                    );
                    $fine->save();
                    $fineRecordsCreated[] = $fine;
                } elseif ($damageAmount > 0 && $lateAmount == 0) {
                    $fine = new Fine(
                        null,
                        $requestId,
                        $renterId,
                        $transactionId,
                        $fineType,
                        $unpaidFine,
                        null,
                        null,
                        date('Y-m-d H:i:s'),
                        null,
                        'Unpaid'
                    );
                    $fine->save();
                    $fineRecordsCreated[] = $fine;
                } else {
                    // Both present: create late fine deducted from deposit, remaining damage fine unpaid
                    $fine1 = new Fine(
                        null,
                        $requestId,
                        $renterId,
                        $transactionId,
                        'Late_Return',
                        $lateAmount,
                        $lateInfo['late_days'],
                        $lateInfo['rate_per_day'],
                        date('Y-m-d H:i:s'),
                        date('Y-m-d H:i:s'),
                        'Deducted_From_Deposit'
                    );
                    $fine1->save();
                    $fineRecordsCreated[] = $fine1;

                    $fine2 = new Fine(
                        null,
                        $requestId,
                        $renterId,
                        $transactionId,
                        $fineType,
                        $unpaidFine,
                        null,
                        null,
                        date('Y-m-d H:i:s'),
                        null,
                        'Unpaid'
                    );
                    $fine2->save();
                    $fineRecordsCreated[] = $fine2;
                }

                // Notify Renter to pay outstanding balance
                Notification::create(
                    $renterId,
                    "Return processed for '{$productTitle}'. Fines (₹" . number_format($totalFine, 2) . ") exceeded your deposit. Outstanding balance of ₹" . number_format($unpaidFine, 2) . " is now due.",
                    'Fine',
                    $requestId
                );

                // Notify Owner
                Notification::create(
                    $ownerId,
                    "Return confirmed for '{$productTitle}'. Deposit of ₹" . number_format($depositHeld, 2) . " forfeited. Outstanding renter fine: ₹" . number_format($unpaidFine, 2) . ".",
                    'Fine',
                    $requestId
                );
            }

            // 4. Update Status Transitions:
            // RENTAL_REQUEST -> 'Completed'
            $stmtReqUp = $db->prepare("UPDATE `RENTAL_REQUEST` SET status = 'Completed' WHERE request_id = :id");
            $stmtReqUp->execute(['id' => $requestId]);

            // PRODUCT -> 'Available'
            $stmtProdUp = $db->prepare("UPDATE `PRODUCT` SET avail_status = 'Available' WHERE product_id = :id");
            $stmtProdUp->execute(['id' => $productId]);

            $db->commit();

            return [
                'success'         => true,
                'request_id'      => $requestId,
                'total_fine'      => $totalFine,
                'late_fine'       => $lateAmount,
                'damage_fine'     => $damageAmount,
                'deposit_held'    => $depositHeld,
                'deducted_amount' => $deductedAmount,
                'net_refund'      => $netRefund,
                'unpaid_fine'     => $unpaidFine,
                'fines'           => $fineRecordsCreated
            ];

        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Rule 12: Auto-Refund Timeout Engine
     * If Owner doesn't confirm return within 7 days of end_date, deposit auto-refunds.
     */
    public static function checkAndTriggerAutoRefunds(): int {
        $db = Database::getInstance()->getConnection();
        $settings = get_fine_settings();
        $timeoutDays = (int) ($settings['auto_refund_days'] ?? 7);

        // Find Active requests where end_date < NOW() - 7 days
        $stmt = $db->prepare("
            SELECT rr.request_id, p.owner_id, rr.end_date
            FROM `RENTAL_REQUEST` rr
            JOIN `PRODUCT` p ON rr.product_id = p.product_id
            JOIN `TRANSACTION` t ON rr.request_id = t.request_id
            WHERE rr.status = 'Active'
              AND t.payment_status = 'Completed'
              AND t.deposit_status = 'Held'
              AND DATEDIFF(CURRENT_DATE(), rr.end_date) >= :timeout_days
        ");
        $stmt->execute(['timeout_days' => $timeoutDays]);
        $overdueRentals = $stmt->fetchAll();

        $processedCount = 0;
        foreach ($overdueRentals as $rental) {
            try {
                // Execute clean return with full refund (using scheduled end_date so renter is not penalized for owner inactivity)
                self::processReturnAndFine(
                    (int) $rental['request_id'],
                    (int) $rental['owner_id'],
                    null,
                    0.00,
                    "System auto-refund triggered: Owner return confirmation timeout reached ({$timeoutDays} days).",
                    $rental['end_date']
                );
                $processedCount++;
            } catch (Exception $e) {
                error_log("Auto-refund error for request #{$rental['request_id']}: " . $e->getMessage());
            }
        }

        return $processedCount;
    }

    /**
     * Static finder: Find fine by ID.
     */
    public static function findById(int $id): ?Fine {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM `FINE` WHERE fine_id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        return new Fine(
            (int) $row['fine_id'],
            (int) $row['request_id'],
            (int) $row['renter_id'],
            (int) $row['transaction_id'],
            $row['fine_type'],
            (float) $row['amount'],
            $row['late_days'] !== null ? (int) $row['late_days'] : null,
            $row['rate_per_day'] !== null ? (float) $row['rate_per_day'] : null,
            $row['issue_date'],
            $row['paid_date'],
            $row['status']
        );
    }

    /**
     * Static finder: Find fines by Request ID.
     */
    public static function findByRequest(int $requestId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM `FINE` WHERE request_id = :req_id ORDER BY fine_id ASC");
        $stmt->execute(['req_id' => $requestId]);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new Fine(
                (int) $row['fine_id'],
                (int) $row['request_id'],
                (int) $row['renter_id'],
                (int) $row['transaction_id'],
                $row['fine_type'],
                (float) $row['amount'],
                $row['late_days'] !== null ? (int) $row['late_days'] : null,
                $row['rate_per_day'] !== null ? (float) $row['rate_per_day'] : null,
                $row['issue_date'],
                $row['paid_date'],
                $row['status']
            );
        }
        return $results;
    }

    /**
     * Static finder: Find fines by Renter ID.
     */
    public static function findByRenter(int $renterId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT * FROM `FINE` WHERE renter_id = :renter_id ORDER BY fine_id DESC");
        $stmt->execute(['renter_id' => $renterId]);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new Fine(
                (int) $row['fine_id'],
                (int) $row['request_id'],
                (int) $row['renter_id'],
                (int) $row['transaction_id'],
                $row['fine_type'],
                (float) $row['amount'],
                $row['late_days'] !== null ? (int) $row['late_days'] : null,
                $row['rate_per_day'] !== null ? (float) $row['rate_per_day'] : null,
                $row['issue_date'],
                $row['paid_date'],
                $row['status']
            );
        }
        return $results;
    }
}
