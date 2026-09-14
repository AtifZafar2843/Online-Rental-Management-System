<?php
/**
 * Online Rental Management System (ORMS)
 * Renter Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseUser.php';

class Renter extends BaseUser {
    private array $activeRentals = [];
    private float $totalSpent = 0.00;

    public function __construct(
        ?int $userID = null,
        string $username = '',
        string $email = '',
        string $phone = '',
        string $password = '',
        string $address = '',
        string $status = 'Active',
        float $rating = 0.00,
        array $roles = ['Renter'],
        array $activeRentals = [],
        float $totalSpent = 0.00
    ) {
        parent::__construct(
            $userID,
            $username,
            $email,
            $phone,
            $password,
            $address,
            $status,
            $rating,
            $roles
        );
        $this->activeRentals = $activeRentals;
        $this->totalSpent = $totalSpent;
    }

    public function getActiveRentals(): array { return $this->activeRentals; }
    public function setActiveRentals(array $rentals): void { $this->activeRentals = $rentals; }

    public function getTotalSpent(): float { return $this->totalSpent; }
    public function setTotalSpent(float $spent): void { $this->totalSpent = $spent; }

    /**
     * Compute total money spent on completed rentals by this renter.
     */
    public function calculateTotalSpent(): float {
        if (!$this->userID) return 0.00;

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(rental_amount), 0.00) 
            FROM `TRANSACTION` 
            WHERE payer_id = :payer_id 
              AND payment_status = 'Completed'
        ");
        $stmt->execute(['payer_id' => $this->userID]);
        $this->totalSpent = (float) $stmt->fetchColumn();
        return $this->totalSpent;
    }

    public function sendRequest(int $productId, string $start, string $end, ?string $message = null): RentalRequest {
        if (!$this->userID) {
            throw new UnauthorizedActionException("Renter must be authenticated to submit rental requests.");
        }
        require_once __DIR__ . '/RentalRequest.php';
        $request = RentalRequest::createWithLock($productId, $this->userID, $start, $end, $message);
        $this->activeRentals[] = $request;
        return $request;
    }

    public function makePayment(int $requestId, string $paymentMode = 'UPI'): Transaction {
        if (!$this->userID) {
            throw new UnauthorizedActionException("Renter must be authenticated to process payment.");
        }
        require_once __DIR__ . '/Transaction.php';
        return Transaction::process($requestId, $this->userID, $paymentMode);
    }

    public function cancelRequest(int $requestId, string $reason = ''): bool {
        require_once __DIR__ . '/RentalRequest.php';
        $request = RentalRequest::findById($requestId);
        if (!$request || $request->getRenterID() !== $this->userID) {
            throw new UnauthorizedActionException("You do not have permission to cancel this rental request.");
        }
        return $request->cancel($reason);
    }

    public function submitReview(int $requestId, int $rating, string $comment): mixed {
        // Implemented in Step 9
        return null;
    }

    public function payFine(int $fineId, string $paymentMode = 'UPI'): bool {
        if (!$this->userID) {
            throw new UnauthorizedActionException("Renter must be authenticated to pay fines.");
        }
        require_once __DIR__ . '/Fine.php';
        $fine = Fine::findById($fineId);
        if (!$fine || $fine->getRenterID() !== $this->userID) {
            throw new UnauthorizedActionException("You are not authorized to pay this fine.");
        }
        return $fine->processPay($paymentMode);
    }

    public function fileDispute(int $requestId, string $reason): mixed {
        // Implemented in Step 11
        return null;
    }
}
