<?php
/**
 * Online Rental Management System (ORMS)
 * Owner Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseUser.php';

class Owner extends BaseUser {
    private array $products = [];
    private float $totalEarnings = 0.00;

    public function __construct(
        ?int $userID = null,
        string $username = '',
        string $email = '',
        string $phone = '',
        string $password = '',
        string $address = '',
        string $status = 'Active',
        float $rating = 0.00,
        array $roles = ['Owner'],
        array $products = [],
        float $totalEarnings = 0.00
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
        $this->products = $products;
        $this->totalEarnings = $totalEarnings;
    }

    public function getProducts(): array { return $this->products; }
    public function setProducts(array $products): void { $this->products = $products; }

    public function getTotalEarnings(): float { return $this->totalEarnings; }
    public function setTotalEarnings(float $earnings): void { $this->totalEarnings = $earnings; }

    /**
     * Compute total earnings from completed transactions for products owned by this user.
     */
    public function calculateTotalEarnings(): float {
        if (!$this->userID) return 0.00;

        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(t.rental_amount), 0.00) AS earnings 
            FROM `TRANSACTION` t 
            JOIN `RENTAL_REQUEST` rr ON t.request_id = rr.request_id 
            JOIN `PRODUCT` p ON rr.product_id = p.product_id 
            WHERE p.owner_id = :owner_id 
              AND t.payment_status = 'Completed'
        ");
        $stmt->execute(['owner_id' => $this->userID]);
        $this->totalEarnings = (float) $stmt->fetchColumn();
        return $this->totalEarnings;
    }

    public function addProduct(): bool {
        // Implemented fully in Step 4 (Product Listing Module)
        return true;
    }

    public function editProduct(int $id): bool {
        // Implemented fully in Step 4
        return true;
    }

    public function manageRentalRequest(int $id, string $decision): bool {
        // Implemented in Step 6 (Rental Request Module)
        return true;
    }

    public function confirmReturn(int $requestId): bool {
        // Implemented in Step 7 / 8
        return true;
    }

    public function raiseFine(int $requestId, string $type, float $amount): mixed {
        // Implemented in Step 8 (Fine Module)
        return null;
    }
}
