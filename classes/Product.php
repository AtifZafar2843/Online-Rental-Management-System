<?php
/**
 * Online Rental Management System (ORMS)
 * Product Entity Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/exceptions/ORMSException.php';
require_once __DIR__ . '/exceptions/ProductUnavailableException.php';

class Product {
    private ?int $productID = null;
    private int $ownerID;
    private int $categoryID;
    private string $title;
    private string $description;
    private float $rentPerDay;
    private float $securityDeposit;
    private string $availStatus = 'Available';
    private string $condition = 'Good';
    private string $location;
    private ?string $listedDate = null;

    // Additional helper data
    private ?string $categoryName = null;
    private ?string $ownerName = null;
    private float $ownerRating = 0.00;
    private ?string $ownerPhone = null;
    private ?string $ownerEmail = null;
    private array $images = [];

    private PDO $db;

    public function __construct(
        ?int $productID = null,
        int $ownerID = 0,
        int $categoryID = 0,
        string $title = '',
        string $description = '',
        float $rentPerDay = 0.0,
        float $securityDeposit = 0.0,
        string $location = '',
        string $availStatus = 'Available',
        string $condition = 'Good',
        ?string $listedDate = null
    ) {
        $this->productID = $productID;
        $this->ownerID = $ownerID;
        $this->categoryID = $categoryID;
        $this->title = $title;
        $this->description = $description;
        $this->rentPerDay = $rentPerDay;
        $this->securityDeposit = $securityDeposit;
        $this->location = $location;
        $this->availStatus = $availStatus;
        $this->condition = $condition;
        $this->listedDate = $listedDate;

        $this->db = Database::getInstance()->getConnection();
    }

    // Getters and Setters
    public function getProductID(): ?int { return $this->productID; }
    public function setProductID(int $id): void { $this->productID = $id; }

    public function getOwnerID(): int { return $this->ownerID; }
    public function setOwnerID(int $id): void { $this->ownerID = $id; }

    public function getCategoryID(): int { return $this->categoryID; }
    public function setCategoryID(int $id): void { $this->categoryID = $id; }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): void { $this->title = $title; }

    public function getDescription(): string { return $this->description; }
    public function setDescription(string $description): void { $this->description = $description; }

    public function getRentPerDay(): float { return $this->rentPerDay; }
    public function setRentPerDay(float $rent): void { $this->rentPerDay = $rent; }

    public function getSecurityDeposit(): float { return $this->securityDeposit; }
    public function setSecurityDeposit(float $deposit): void { $this->securityDeposit = $deposit; }

    public function getAvailStatus(): string { return $this->availStatus; }
    public function setAvailStatus(string $status): void { $this->availStatus = $status; }

    public function getCondition(): string { return $this->condition; }
    public function setCondition(string $condition): void { $this->condition = $condition; }

    public function getLocation(): string { return $this->location; }
    public function setLocation(string $location): void { $this->location = $location; }

    public function getListedDate(): ?string { return $this->listedDate; }
    public function setListedDate(string $date): void { $this->listedDate = $date; }

    public function getCategoryName(): ?string { return $this->categoryName; }
    public function setCategoryName(string $name): void { $this->categoryName = $name; }

    public function getOwnerName(): ?string { return $this->ownerName; }
    public function setOwnerName(string $name): void { $this->ownerName = $name; }

    public function getOwnerRating(): float { return $this->ownerRating; }
    public function setOwnerRating(float $r): void { $this->ownerRating = $r; }

    public function getOwnerPhone(): ?string { return $this->ownerPhone; }
    public function setOwnerPhone(?string $p): void { $this->ownerPhone = $p; }

    public function getOwnerEmail(): ?string { return $this->ownerEmail; }
    public function setOwnerEmail(?string $e): void { $this->ownerEmail = $e; }

    /**
     * Check if product is available for booking within the requested date range.
     * Validates that status is 'Available' and no active/approved overlapping rentals exist.
     */
    public function checkAvailability(string $start, string $end): bool {
        if ($this->availStatus !== 'Available') {
            return false;
        }

        if (!$this->productID) {
            return true;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM `RENTAL_REQUEST` 
            WHERE product_id = :product_id 
              AND status IN ('Approved', 'Active') 
              AND (start_date <= :end_date AND end_date >= :start_date)
        ");
        $stmt->execute([
            'product_id' => $this->productID,
            'start_date' => $start,
            'end_date'   => $end
        ]);

        return ((int) $stmt->fetchColumn()) === 0;
    }

    /**
     * Update availability status in database ('Available', 'Rented', 'Unavailable').
     */
    public function updateStatus(string $status): void {
        if (!$this->productID) {
            throw new ORMSException("Cannot update status on unpersisted product.");
        }

        $validStatuses = ['Available', 'Rented', 'Unavailable'];
        if (!in_array($status, $validStatuses, true)) {
            throw new ORMSException("Invalid product status: {$status}");
        }

        $stmt = $this->db->prepare("
            UPDATE `PRODUCT` 
            SET avail_status = :status 
            WHERE product_id = :id
        ");
        $stmt->execute([
            'status' => $status,
            'id'     => $this->productID
        ]);

        $this->availStatus = $status;
    }

    /**
     * Retrieve all images associated with this product (primary image first).
     */
    public function getImages(): array {
        if (!$this->productID) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT image_id, product_id, image_path, is_primary, upload_date 
            FROM `PRODUCT_IMAGES` 
            WHERE product_id = :id 
            ORDER BY is_primary DESC, image_id ASC
        ");
        $stmt->execute(['id' => $this->productID]);
        $this->images = $stmt->fetchAll();
        return $this->images;
    }

    /**
     * Retrieve primary image path or a fallback image.
     */
    public function getPrimaryImagePath(): string {
        $imgs = $this->getImages();
        if (!empty($imgs)) {
            return $imgs[0]['image_path'];
        }
        return 'assets/img/no-image.svg';
    }

    /**
     * Retrieve verified reviews submitted for this product.
     */
    public function getReviews(): array {
        if (!$this->productID) return [];

        $stmt = $this->db->prepare("
            SELECT r.*, u.name AS reviewer_name 
            FROM `REVIEW` r 
            JOIN `USER` u ON r.reviewer_id = u.user_id 
            WHERE r.product_id = :pid 
            ORDER BY r.review_date DESC
        ");
        $stmt->execute(['pid' => $this->productID]);
        return $stmt->fetchAll();
    }

    /**
     * Calculate average star rating from verified reviews.
     */
    public function getAverageRating(): float {
        if (!$this->productID) return 0.00;

        $stmt = $this->db->prepare("
            SELECT COALESCE(AVG(rating), 0.00) 
            FROM `REVIEW` 
            WHERE product_id = :pid
        ");
        $stmt->execute(['pid' => $this->productID]);
        return round((float) $stmt->fetchColumn(), 1);
    }

    /**
     * Insert or update product in the database.
     */
    public function save(): bool {
        if ($this->rentPerDay <= 0) {
            throw new ORMSException("Rent per day must be greater than zero.");
        }
        if ($this->securityDeposit < 0) {
            throw new ORMSException("Security deposit cannot be negative.");
        }

        if ($this->productID === null) {
            // INSERT
            $stmt = $this->db->prepare("
                INSERT INTO `PRODUCT` 
                (`owner_id`, `category_id`, `title`, `description`, `rent_per_day`, `security_deposit`, `location`, `avail_status`, `condition`, `listed_date`) 
                VALUES 
                (:owner_id, :category_id, :title, :description, :rent_per_day, :security_deposit, :location, :avail_status, :condition, NOW())
            ");
            $success = $stmt->execute([
                'owner_id'         => $this->ownerID,
                'category_id'      => $this->categoryID,
                'title'            => $this->title,
                'description'      => $this->description,
                'rent_per_day'     => $this->rentPerDay,
                'security_deposit' => $this->securityDeposit,
                'location'         => $this->location,
                'avail_status'     => $this->availStatus,
                'condition'        => $this->condition
            ]);

            if ($success) {
                $this->productID = (int) $this->db->lastInsertId();
            }
            return $success;
        } else {
            // UPDATE
            $stmt = $this->db->prepare("
                UPDATE `PRODUCT` 
                SET category_id = :category_id,
                    title = :title,
                    description = :description,
                    rent_per_day = :rent_per_day,
                    security_deposit = :security_deposit,
                    location = :location,
                    avail_status = :avail_status,
                    `condition` = :condition 
                WHERE product_id = :id AND owner_id = :owner_id
            ");
            return $stmt->execute([
                'category_id'      => $this->categoryID,
                'title'            => $this->title,
                'description'      => $this->description,
                'rent_per_day'     => $this->rentPerDay,
                'security_deposit' => $this->securityDeposit,
                'location'         => $this->location,
                'avail_status'     => $this->availStatus,
                'condition'        => $this->condition,
                'id'               => $this->productID,
                'owner_id'         => $this->ownerID
            ]);
        }
    }

    /**
     * Associate an image with this product.
     */
    public function addImage(string $imagePath, bool $isPrimary = false): int {
        if (!$this->productID) {
            throw new ORMSException("Cannot add image to unpersisted product.");
        }

        // If setting as primary, demote existing primary images
        if ($isPrimary) {
            $demoteStmt = $this->db->prepare("
                UPDATE `PRODUCT_IMAGES` SET is_primary = 0 WHERE product_id = :id
            ");
            $demoteStmt->execute(['id' => $this->productID]);
        }

        $stmt = $this->db->prepare("
            INSERT INTO `PRODUCT_IMAGES` (`product_id`, `image_path`, `is_primary`, `upload_date`) 
            VALUES (:product_id, :image_path, :is_primary, NOW())
        ");
        $stmt->execute([
            'product_id' => $this->productID,
            'image_path' => $imagePath,
            'is_primary' => $isPrimary ? 1 : 0
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Fetch a Product instance by its ID.
     */
    public static function findById(int $id): ?Product {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT p.*, c.category_name, u.name AS owner_name, u.rating AS owner_rating, u.email AS owner_email, u.phone AS owner_phone 
            FROM `PRODUCT` p 
            JOIN `CATEGORY` c ON p.category_id = c.category_id 
            JOIN `USER` u ON p.owner_id = u.user_id 
            WHERE p.product_id = :id 
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) return null;

        $product = new self(
            (int) $row['product_id'],
            (int) $row['owner_id'],
            (int) $row['category_id'],
            $row['title'],
            $row['description'],
            (float) $row['rent_per_day'],
            (float) $row['security_deposit'],
            $row['location'],
            $row['avail_status'],
            $row['condition'],
            $row['listed_date']
        );
        $product->setCategoryName($row['category_name']);
        $product->setOwnerName($row['owner_name']);
        $product->setOwnerRating((float)($row['owner_rating'] ?? 0.00));
        $product->setOwnerEmail($row['owner_email'] ?? null);
        $product->setOwnerPhone($row['owner_phone'] ?? null);

        return $product;
    }

    /**
     * Fetch all products owned by a specific owner ID.
     */
    public static function findByOwner(int $ownerId): array {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("
            SELECT p.*, c.category_name 
            FROM `PRODUCT` p 
            JOIN `CATEGORY` c ON p.category_id = c.category_id 
            WHERE p.owner_id = :owner_id 
            ORDER BY p.product_id DESC
        ");
        $stmt->execute(['owner_id' => $ownerId]);
        $rows = $stmt->fetchAll();

        $products = [];
        foreach ($rows as $row) {
            $prod = new self(
                (int) $row['product_id'],
                (int) $row['owner_id'],
                (int) $row['category_id'],
                $row['title'],
                $row['description'],
                (float) $row['rent_per_day'],
                (float) $row['security_deposit'],
                $row['location'],
                $row['avail_status'],
                $row['condition'],
                $row['listed_date']
            );
            $prod->setCategoryName($row['category_name']);
            $products[] = $prod;
        }
        return $products;
    }

    /**
     * Search and filter products across the public catalog.
     * Supports keyword search, category, min/max price, location, condition, and status.
     *
     * @param array $filters ['query' => '', 'category_id' => int, 'min_price' => float, 'max_price' => float, 'location' => '', 'condition' => '', 'status' => 'Available']
     * @param string $sortBy 'newest' | 'price_asc' | 'price_desc' | 'title_asc'
     */
    public static function search(array $filters = [], string $sortBy = 'newest', int $limit = 20, int $offset = 0): array {
        $db = Database::getInstance()->getConnection();

        $whereClauses = [];
        $params = [];

        // Status Filter (default to Available if not explicitly overridden)
        $status = $filters['status'] ?? 'Available';
        if ($status !== 'all') {
            $whereClauses[] = "p.avail_status = :status";
            $params['status'] = $status;
        }

        // Keyword Search (Search in title or description)
        if (!empty($filters['query'])) {
            $searchTerm = trim((string)$filters['query']);
            $whereClauses[] = "(p.title LIKE :kw1 OR p.description LIKE :kw2)";
            $params['kw1'] = "%{$searchTerm}%";
            $params['kw2'] = "%{$searchTerm}%";
        }

        // Category Filter
        if (!empty($filters['category_id'])) {
            $whereClauses[] = "p.category_id = :cat_id";
            $params['cat_id'] = (int)$filters['category_id'];
        }

        // Min Price
        if (isset($filters['min_price']) && is_numeric($filters['min_price']) && (float)$filters['min_price'] > 0) {
            $whereClauses[] = "p.rent_per_day >= :min_price";
            $params['min_price'] = (float)$filters['min_price'];
        }

        // Max Price
        if (isset($filters['max_price']) && is_numeric($filters['max_price']) && (float)$filters['max_price'] > 0) {
            $whereClauses[] = "p.rent_per_day <= :max_price";
            $params['max_price'] = (float)$filters['max_price'];
        }

        // Location Filter
        if (!empty($filters['location'])) {
            $whereClauses[] = "p.location LIKE :loc";
            $params['loc'] = "%" . trim((string)$filters['location']) . "%";
        }

        // Condition Filter
        if (!empty($filters['condition']) && in_array($filters['condition'], ['New', 'Good', 'Fair', 'Poor'], true)) {
            $whereClauses[] = "p.condition = :condition";
            $params['condition'] = $filters['condition'];
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        // Sorting
        $orderBySql = "ORDER BY p.product_id DESC";
        switch ($sortBy) {
            case 'price_asc':
                $orderBySql = "ORDER BY p.rent_per_day ASC";
                break;
            case 'price_desc':
                $orderBySql = "ORDER BY p.rent_per_day DESC";
                break;
            case 'title_asc':
                $orderBySql = "ORDER BY p.title ASC";
                break;
            case 'newest':
            default:
                $orderBySql = "ORDER BY p.product_id DESC";
                break;
        }

        $limit = max(1, min(100, $limit));
        $offset = max(0, $offset);

        $sql = "
            SELECT p.*, c.category_name, u.name AS owner_name, u.rating AS owner_rating,
                   (SELECT image_path FROM `PRODUCT_IMAGES` pi WHERE pi.product_id = p.product_id ORDER BY pi.is_primary DESC, pi.image_id ASC LIMIT 1) AS primary_image 
            FROM `PRODUCT` p 
            JOIN `CATEGORY` c ON p.category_id = c.category_id 
            JOIN `USER` u ON p.owner_id = u.user_id 
            {$whereSql} 
            {$orderBySql} 
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        $results = [];
        foreach ($rows as $row) {
            $prod = new self(
                (int)$row['product_id'],
                (int)$row['owner_id'],
                (int)$row['category_id'],
                $row['title'],
                $row['description'],
                (float)$row['rent_per_day'],
                (float)$row['security_deposit'],
                $row['location'],
                $row['avail_status'],
                $row['condition'],
                $row['listed_date']
            );
            $prod->setCategoryName($row['category_name']);
            $prod->setOwnerName($row['owner_name']);
            $prod->setOwnerRating((float)($row['owner_rating'] ?? 0.00));

            $results[] = [
                'product'       => $prod,
                'primary_image' => $row['primary_image'] ?: 'assets/img/no-image.svg'
            ];
        }

        return $results;
    }

    /**
     * Count total search results matching criteria (for pagination).
     */
    public static function countSearch(array $filters = []): int {
        $db = Database::getInstance()->getConnection();

        $whereClauses = [];
        $params = [];

        $status = $filters['status'] ?? 'Available';
        if ($status !== 'all') {
            $whereClauses[] = "p.avail_status = :status";
            $params['status'] = $status;
        }

        if (!empty($filters['query'])) {
            $searchTerm = trim((string)$filters['query']);
            $whereClauses[] = "(p.title LIKE :kw1 OR p.description LIKE :kw2)";
            $params['kw1'] = "%{$searchTerm}%";
            $params['kw2'] = "%{$searchTerm}%";
        }

        if (!empty($filters['category_id'])) {
            $whereClauses[] = "p.category_id = :cat_id";
            $params['cat_id'] = (int)$filters['category_id'];
        }

        if (isset($filters['min_price']) && is_numeric($filters['min_price']) && (float)$filters['min_price'] > 0) {
            $whereClauses[] = "p.rent_per_day >= :min_price";
            $params['min_price'] = (float)$filters['min_price'];
        }

        if (isset($filters['max_price']) && is_numeric($filters['max_price']) && (float)$filters['max_price'] > 0) {
            $whereClauses[] = "p.rent_per_day <= :max_price";
            $params['max_price'] = (float)$filters['max_price'];
        }

        if (!empty($filters['location'])) {
            $whereClauses[] = "p.location LIKE :loc";
            $params['loc'] = "%" . trim((string)$filters['location']) . "%";
        }

        if (!empty($filters['condition']) && in_array($filters['condition'], ['New', 'Good', 'Fair', 'Poor'], true)) {
            $whereClauses[] = "p.condition = :condition";
            $params['condition'] = $filters['condition'];
        }

        $whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

        $stmt = $db->prepare("SELECT COUNT(*) FROM `PRODUCT` p {$whereSql}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
