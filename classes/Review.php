<?php
/**
 * Online Rental Management System (ORMS)
 * Review Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 3.10, 4, 5 (Rule 10) & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/RentalRequest.php';
require_once __DIR__ . '/Product.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/exceptions/ORMSException.php';
require_once __DIR__ . '/exceptions/UnauthorizedActionException.php';
require_once __DIR__ . '/exceptions/DuplicateReviewException.php';

class Review {
    private ?int $reviewID;
    private int $requestID;
    private int $productID;
    private int $reviewerID;
    private int $rating;
    private ?string $comment;
    private string $reviewDate;

    // Additional display metadata
    private ?string $reviewerName = null;
    private ?string $productTitle = null;

    private PDO $db;

    public function __construct(
        ?int $reviewID = null,
        int $requestID = 0,
        int $productID = 0,
        int $reviewerID = 0,
        int $rating = 5,
        ?string $comment = null,
        string $reviewDate = '',
        ?string $reviewerName = null,
        ?string $productTitle = null
    ) {
        $this->reviewID = $reviewID;
        $this->requestID = $requestID;
        $this->productID = $productID;
        $this->reviewerID = $reviewerID;
        $this->rating = $rating;
        $this->comment = $comment;
        $this->reviewDate = $reviewDate ?: date('Y-m-d H:i:s');
        $this->reviewerName = $reviewerName;
        $this->productTitle = $productTitle;

        $this->db = Database::getInstance()->getConnection();
    }

    // Getters
    public function getReviewID(): ?int { return $this->reviewID; }
    public function getRequestID(): int { return $this->requestID; }
    public function getProductID(): int { return $this->productID; }
    public function getReviewerID(): int { return $this->reviewerID; }
    public function getRating(): int { return $this->rating; }
    public function getComment(): ?string { return $this->comment; }
    public function getReviewDate(): string { return $this->reviewDate; }
    public function getReviewerName(): ?string { return $this->reviewerName; }
    public function getProductTitle(): ?string { return $this->productTitle; }

    // Setters
    public function setRating(int $rating): void { $this->rating = $rating; }
    public function setComment(?string $comment): void { $this->comment = $comment; }

    /**
     * Synopsis Section 11.1 & Rule 10:
     * Validates that the rental request status is 'Completed'
     * and the reviewer is the authentic renter for that request.
     */
    public function validateRentalCompleted(): bool {
        $stmt = $this->db->prepare("
            SELECT request_id, product_id, renter_id, status 
            FROM `RENTAL_REQUEST` 
            WHERE request_id = :req_id 
            LIMIT 1
        ");
        $stmt->execute(['req_id' => $this->requestID]);
        $req = $stmt->fetch();

        if (!$req) {
            throw new ORMSException("Rental request #{$this->requestID} not found.");
        }

        if ($req['status'] !== 'Completed') {
            throw new ORMSException("Reviews can only be submitted for 'Completed' rentals (Current status: {$req['status']}).");
        }

        if ((int) $req['renter_id'] !== $this->reviewerID) {
            throw new UnauthorizedActionException("Only the renter who completed this booking can submit a review.");
        }

        // Set productID if not already set
        if ($this->productID <= 0) {
            $this->productID = (int) $req['product_id'];
        }

        return true;
    }

    /**
     * Synopsis Section 11.1 & Rule 10:
     * Submits a review after completion validation.
     * Enforces unique review per rental request and notifies the owner.
     */
    public function submitReview(): bool {
        $this->validateRentalCompleted();

        if ($this->rating < 1 || $this->rating > 5) {
            throw new ORMSException("Rating must be between 1 and 5 stars.");
        }

        // Check if review already exists for this request
        $existing = self::findByRequest($this->requestID);
        if ($existing !== null) {
            throw new DuplicateReviewException("A review has already been submitted for rental request #{$this->requestID}. Duplicate reviews are not allowed.");
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("
                INSERT INTO `REVIEW` 
                (`request_id`, `product_id`, `reviewer_id`, `rating`, `comment`, `review_date`)
                VALUES 
                (:request_id, :product_id, :reviewer_id, :rating, :comment, :review_date)
            ");

            $success = $stmt->execute([
                'request_id'  => $this->requestID,
                'product_id'  => $this->productID,
                'reviewer_id' => $this->reviewerID,
                'rating'      => $this->rating,
                'comment'     => $this->comment,
                'review_date' => $this->reviewDate
            ]);

            if (!$success) {
                throw new ORMSException("Failed to save review record.");
            }

            $this->reviewID = (int) $this->db->lastInsertId();

            // Fetch Product and Owner details
            $product = Product::findById($this->productID);
            if ($product) {
                $ownerId = $product->getOwnerID();

                // Recalculate Owner's overall user rating snapshot in USER table
                $stmtUpdateRating = $this->db->prepare("
                    UPDATE `USER` 
                    SET rating = (
                        SELECT COALESCE(ROUND(AVG(r.rating), 2), 0.00) 
                        FROM `REVIEW` r 
                        JOIN `PRODUCT` p ON r.product_id = p.product_id 
                        WHERE p.owner_id = :owner_id_sub
                    )
                    WHERE user_id = :owner_id_main
                ");
                $stmtUpdateRating->execute([
                    'owner_id_sub'  => $ownerId,
                    'owner_id_main' => $ownerId
                ]);

                // Notify Owner
                $stars = str_repeat('★', $this->rating);
                Notification::create(
                    $ownerId,
                    "New {$this->rating}-star review ({$stars}) received on '{$product->getTitle()}': \"" . mb_strimwidth($this->comment ?? '', 0, 50, '...') . "\"",
                    'Rental',
                    $this->reviewID
                );
            }

            $this->db->commit();
            return true;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            // Check for MySQL duplicate key error code 1062
            if ($e->getCode() === '23000' || str_contains($e->getMessage(), 'Duplicate entry') || str_contains($e->getMessage(), 'uk_review_request')) {
                throw new DuplicateReviewException("A review has already been submitted for rental request #{$this->requestID}.");
            }
            throw $e;
        } catch (Exception $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Static finder: Find review by Review ID.
     */
    public static function findById(int $id): ?Review {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT r.*, u.name AS reviewer_name, p.title AS product_title
            FROM `REVIEW` r
            JOIN `USER` u ON r.reviewer_id = u.user_id
            JOIN `PRODUCT` p ON r.product_id = p.product_id
            WHERE r.review_id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        if (!$row) return null;

        return new Review(
            (int) $row['review_id'],
            (int) $row['request_id'],
            (int) $row['product_id'],
            (int) $row['reviewer_id'],
            (int) $row['rating'],
            $row['comment'],
            $row['review_date'],
            $row['reviewer_name'] ?? null,
            $row['product_title'] ?? null
        );
    }

    /**
     * Static finder: Find review by Request ID (Rule 10 unique constraint).
     */
    public static function findByRequest(int $requestId): ?Review {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT r.*, u.name AS reviewer_name, p.title AS product_title
            FROM `REVIEW` r
            JOIN `USER` u ON r.reviewer_id = u.user_id
            JOIN `PRODUCT` p ON r.product_id = p.product_id
            WHERE r.request_id = :req_id
            LIMIT 1
        ");
        $stmt->execute(['req_id' => $requestId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        return new Review(
            (int) $row['review_id'],
            (int) $row['request_id'],
            (int) $row['product_id'],
            (int) $row['reviewer_id'],
            (int) $row['rating'],
            $row['comment'],
            $row['review_date'],
            $row['reviewer_name'] ?? null,
            $row['product_title'] ?? null
        );
    }

    /**
     * Static finder: Find all reviews for a specific Product.
     */
    public static function findByProduct(int $productId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT r.*, u.name AS reviewer_name, p.title AS product_title
            FROM `REVIEW` r
            JOIN `USER` u ON r.reviewer_id = u.user_id
            JOIN `PRODUCT` p ON r.product_id = p.product_id
            WHERE r.product_id = :prod_id
            ORDER BY r.review_date DESC
        ");
        $stmt->execute(['prod_id' => $productId]);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $results[] = new Review(
                (int) $row['review_id'],
                (int) $row['request_id'],
                (int) $row['product_id'],
                (int) $row['reviewer_id'],
                (int) $row['rating'],
                $row['comment'],
                $row['review_date'],
                $row['reviewer_name'] ?? null,
                $row['product_title'] ?? null
            );
        }
        return $results;
    }

    /**
     * Rule 10: Product average rating = AVG(rating) from REVIEW WHERE product_id = X
     * Computed dynamically on query, not stored as redundant column.
     */
    public static function getAverageRatingForProduct(int $productId): float {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COALESCE(ROUND(AVG(rating), 2), 0.00) 
            FROM `REVIEW` 
            WHERE product_id = :prod_id
        ");
        $stmt->execute(['prod_id' => $productId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Get review count for a product.
     */
    public static function getReviewCountForProduct(int $productId): int {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT COUNT(*) FROM `REVIEW` WHERE product_id = :prod_id");
        $stmt->execute(['prod_id' => $productId]);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Rule 10: User trust score = AVG(rating) from REVIEW for products owned by that user
     * Computed dynamically on query, not stored as redundant column.
     */
    public static function getUserTrustScore(int $ownerId): float {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT COALESCE(ROUND(AVG(r.rating), 2), 0.00) 
            FROM `REVIEW` r 
            JOIN `PRODUCT` p ON r.product_id = p.product_id 
            WHERE p.owner_id = :owner_id
        ");
        $stmt->execute(['owner_id' => $ownerId]);
        return (float) $stmt->fetchColumn();
    }

    /**
     * Aliases for dynamic rating computations
     */
    public static function calculateProductAverage(int $productId): float {
        return self::getAverageRatingForProduct($productId);
    }

    public static function calculateOwnerTrustScore(int $ownerId): float {
        return self::getUserTrustScore($ownerId);
    }
}
