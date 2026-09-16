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
require_once __DIR__ . '/Product.php';

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

    public function addProduct(
        int $categoryId,
        string $title,
        string $description,
        float $rentPerDay,
        float $securityDeposit,
        string $location,
        string $condition = 'Good',
        array $imagePaths = []
    ): ?Product {
        if (!$this->userID) {
            throw new UnauthorizedActionException("Owner must be authenticated to add products.");
        }

        $product = new Product(
            null,
            $this->userID,
            $categoryId,
            $title,
            $description,
            $rentPerDay,
            $securityDeposit,
            $location,
            'Available',
            $condition
        );

        if ($product->save()) {
            foreach ($imagePaths as $idx => $path) {
                $product->addImage($path, $idx === 0);
            }
            $this->products[] = $product;
            return $product;
        }

        return null;
    }

    public function editProduct(
        int $id,
        int $categoryId,
        string $title,
        string $description,
        float $rentPerDay,
        float $securityDeposit,
        string $location,
        string $condition,
        string $availStatus = 'Available'
    ): bool {
        $product = Product::findById($id);
        if (!$product || $product->getOwnerID() !== $this->userID) {
            throw new UnauthorizedActionException("You do not have permission to edit this product.");
        }

        $product->setCategoryID($categoryId);
        $product->setTitle($title);
        $product->setDescription($description);
        $product->setRentPerDay($rentPerDay);
        $product->setSecurityDeposit($securityDeposit);
        $product->setLocation($location);
        $product->setCondition($condition);
        $product->setAvailStatus($availStatus);

        return $product->save();
    }

    public function manageRentalRequest(int $id, string $decision, string $reason = ''): bool {
        require_once __DIR__ . '/RentalRequest.php';
        $request = RentalRequest::findById($id);
        if (!$request || $request->getOwnerID() !== $this->userID) {
            throw new UnauthorizedActionException("You do not have permission to manage this rental request.");
        }

        $dec = strtolower(trim($decision));
        if ($dec === 'approve' || $dec === 'approved') {
            return $request->approve();
        } elseif ($dec === 'reject' || $dec === 'rejected') {
            return $request->reject($reason);
        }

        return false;
    }

    public function confirmReturn(int $requestId, ?string $actualReturnDate = null): bool {
        if (!$this->userID) {
            throw new UnauthorizedActionException("Owner must be authenticated to confirm return.");
        }
        require_once __DIR__ . '/Fine.php';
        $result = Fine::processReturnAndFine(
            $requestId,
            $this->userID,
            null,
            0.00,
            'Clean return confirmed by owner',
            $actualReturnDate
        );
        return (bool) ($result['success'] ?? false);
    }

    public function raiseFine(int $requestId, string $type, float $amount, ?string $reason = '', ?string $actualReturnDate = null): array {
        if (!$this->userID) {
            throw new UnauthorizedActionException("Owner must be authenticated to raise fines.");
        }
        require_once __DIR__ . '/Fine.php';
        return Fine::processReturnAndFine(
            $requestId,
            $this->userID,
            $type,
            $amount,
            $reason,
            $actualReturnDate
        );
    }

    public function deleteProduct(int $productId): bool {
        if (!$this->userID) {
            throw new UnauthorizedActionException("Owner must be authenticated to delete products.");
        }
        $product = Product::findById($productId);
        if (!$product || $product->getOwnerID() !== $this->userID) {
            throw new UnauthorizedActionException("You do not have permission to delete this product.");
        }
        return $product->delete();
    }
}
