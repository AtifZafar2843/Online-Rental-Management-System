<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1, 13.I (Page 29)
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseUser.php';
require_once __DIR__ . '/Dispute.php';
require_once __DIR__ . '/Notification.php';
require_once __DIR__ . '/exceptions/ORMSException.php';

class Admin extends BaseUser {
    private int $securityLevel = 1;
    private string $fullName = '';

    public function __construct(
        ?int $adminID = null,
        string $username = '',
        string $email = '',
        string $fullName = '',
        string $password = '',
        string $status = 'Active',
        int $securityLevel = 1
    ) {
        parent::__construct(
            $adminID,
            $username,
            $email,
            '', // phone not in ADMIN table
            $password,
            '', // address not in ADMIN table
            $status,
            0.00,
            ['Admin']
        );
        $this->fullName = $fullName;
        $this->securityLevel = $securityLevel;
    }

    public function getFullName(): string { return $this->fullName; }
    public function setFullName(string $name): void { $this->fullName = $name; }
    public function getSecurityLevel(): int { return $this->securityLevel; }
    public function setSecurityLevel(int $level): void { $this->securityLevel = $level; }

    /**
     * Admin login authenticating against the ADMIN table.
     */
    public function login(): bool {
        $stmt = $this->db->prepare("
            SELECT admin_id, username, password, email, full_name, status 
            FROM `ADMIN` 
            WHERE username = :uname OR email = :uemail 
            LIMIT 1
        ");
        $identifier = $this->email ?: $this->username;
        $stmt->execute(['uname' => $identifier, 'uemail' => $identifier]);
        $adminData = $stmt->fetch();

        if (!$adminData || !password_verify($this->password, $adminData['password'])) {
            return false;
        }

        if ($adminData['status'] !== 'Active') {
            $this->status = $adminData['status'];
            return false;
        }

        $this->userID = (int) $adminData['admin_id'];
        $this->username = $adminData['username'];
        $this->email = $adminData['email'];
        $this->status = $adminData['status'];
        $this->roles = ['Admin'];

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['admin_id'] = $this->userID;
        $_SESSION['name'] = $adminData['full_name'];
        $_SESSION['username'] = $adminData['username'];
        $_SESSION['email'] = $this->email;
        $_SESSION['roles'] = ['Admin'];
        $_SESSION['active_role'] = 'Admin';

        return true;
    }

    public function configSystem(): void {
        // System configuration hook (Section 11.1)
    }

    /**
     * User Management: Fetch user roster with dual roles, product counts, and rental statistics.
     */
    public function manageUsers(): array {
        $stmt = $this->db->query("
            SELECT u.user_id, u.name, u.email, u.phone, u.status, u.rating, u.reg_date, u.id_proof_path,
                   GROUP_CONCAT(DISTINCT ur.role ORDER BY ur.role SEPARATOR ', ') AS roles,
                   (SELECT COUNT(*) FROM `PRODUCT` p WHERE p.owner_id = u.user_id) AS products_count,
                   (SELECT COUNT(*) FROM `RENTAL_REQUEST` r WHERE r.renter_id = u.user_id) AS rentals_count
            FROM `USER` u
            LEFT JOIN `USER_ROLES` ur ON u.user_id = ur.user_id
            GROUP BY u.user_id
            ORDER BY u.user_id DESC
        ");
        return $stmt->fetchAll();
    }

    /**
     * User Management: Update account status (Active, Inactive, Banned).
     */
    public function updateUserStatus(int $userId, string $status): bool {
        $validStatuses = ['Active', 'Inactive', 'Banned'];
        if (!in_array($status, $validStatuses, true)) {
            throw new ORMSException("Invalid account status '{$status}'. Allowed: " . implode(', ', $validStatuses));
        }

        $stmt = $this->db->prepare("UPDATE `USER` SET status = :status WHERE user_id = :uid");
        $success = $stmt->execute(['status' => $status, 'uid' => $userId]);

        if ($success) {
            // Notify user of status update
            $statusMsg = ($status === 'Banned') 
                ? "Your account has been suspended by system administrator for policy violations." 
                : "Your account status has been updated to '{$status}' by system administrator.";
            Notification::create($userId, $statusMsg, 'System');
        }

        return $success;
    }

    /**
     * Category Management: Fetch all categories with hierarchy and product counts.
     */
    public function manageCategories(): array {
        $stmt = $this->db->query("
            SELECT c.*, 
                   parent.category_name AS parent_name,
                   (SELECT COUNT(*) FROM `PRODUCT` p WHERE p.category_id = c.category_id) AS product_count
            FROM `CATEGORY` c
            LEFT JOIN `CATEGORY` parent ON c.parent_category_id = parent.category_id
            ORDER BY COALESCE(c.parent_category_id, c.category_id), c.category_id
        ");
        return $stmt->fetchAll();
    }

    /**
     * Category Management: Create new category or subcategory.
     */
    public function addCategory(string $name, ?string $desc = null, ?int $parentId = null): int {
        $cleanName = trim($name);
        if (empty($cleanName)) {
            throw new ORMSException("Category name is required.");
        }

        $chkStmt = $this->db->prepare("SELECT category_id FROM `CATEGORY` WHERE LOWER(category_name) = LOWER(:name)");
        $chkStmt->execute(['name' => $cleanName]);
        if ($chkStmt->fetch()) {
            throw new ORMSException("A category named '{$cleanName}' already exists.");
        }

        $stmt = $this->db->prepare("
            INSERT INTO `CATEGORY` (`category_name`, `description`, `parent_category_id`)
            VALUES (:name, :desc, :parent)
        ");
        $stmt->execute([
            'name'   => $cleanName,
            'desc'   => $desc ? trim($desc) : null,
            'parent' => $parentId > 0 ? $parentId : null
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Category Management: Edit category.
     */
    public function editCategory(int $id, string $name, ?string $desc = null, ?int $parentId = null): bool {
        $cleanName = trim($name);
        if (empty($cleanName)) {
            throw new ORMSException("Category name is required.");
        }

        if ($parentId === $id) {
            throw new ORMSException("A category cannot be its own parent.");
        }

        $chkStmt = $this->db->prepare("SELECT category_id FROM `CATEGORY` WHERE LOWER(category_name) = LOWER(:name) AND category_id != :id");
        $chkStmt->execute(['name' => $cleanName, 'id' => $id]);
        if ($chkStmt->fetch()) {
            throw new ORMSException("A category named '{$cleanName}' already exists.");
        }

        $stmt = $this->db->prepare("
            UPDATE `CATEGORY` 
            SET category_name = :name, description = :desc, parent_category_id = :parent 
            WHERE category_id = :id
        ");
        return $stmt->execute([
            'name'   => $cleanName,
            'desc'   => $desc ? trim($desc) : null,
            'parent' => $parentId > 0 ? $parentId : null,
            'id'     => $id
        ]);
    }

    /**
     * Category Management: Delete category with product and subcategory safety checks.
     */
    public function deleteCategory(int $id): bool {
        // Check for products
        $prodStmt = $this->db->prepare("SELECT COUNT(*) FROM `PRODUCT` WHERE category_id = :id");
        $prodStmt->execute(['id' => $id]);
        if ((int) $prodStmt->fetchColumn() > 0) {
            throw new ORMSException("Cannot delete category: products are actively listed under this category.");
        }

        // Check for child subcategories
        $childStmt = $this->db->prepare("SELECT COUNT(*) FROM `CATEGORY` WHERE parent_category_id = :id");
        $childStmt->execute(['id' => $id]);
        if ((int) $childStmt->fetchColumn() > 0) {
            throw new ORMSException("Cannot delete category: it has subcategories attached. Please delete or reassign them first.");
        }

        $stmt = $this->db->prepare("DELETE FROM `CATEGORY` WHERE category_id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Audit & Reports Generator (Synopsis Section 11.1 Class Diagram & Section 13.I).
     * Supported types: 'overview', 'rentals', 'revenue', 'fines'.
     */
    public function generateReport(string $type): array {
        switch (strtolower($type)) {
            case 'overview':
                $usersCount = (int) $this->db->query("SELECT COUNT(*) FROM `USER`")->fetchColumn();
                $prodsCount = (int) $this->db->query("SELECT COUNT(*) FROM `PRODUCT`")->fetchColumn();
                $rentalsCount = (int) $this->db->query("SELECT COUNT(*) FROM `RENTAL_REQUEST`")->fetchColumn();
                $activeRentals = (int) $this->db->query("SELECT COUNT(*) FROM `RENTAL_REQUEST` WHERE status = 'Active'")->fetchColumn();
                $grossRevenue = (float) $this->db->query("SELECT COALESCE(SUM(rental_amount), 0.00) FROM `TRANSACTION` WHERE payment_status = 'Completed'")->fetchColumn();
                $depositsHeld = (float) $this->db->query("SELECT COALESCE(SUM(deposit_amount), 0.00) FROM `TRANSACTION` WHERE deposit_status = 'Held'")->fetchColumn();
                $finesCollected = (float) $this->db->query("SELECT COALESCE(SUM(amount), 0.00) FROM `FINE` WHERE status IN ('Paid', 'Deducted_From_Deposit')")->fetchColumn();
                $openDisputes = (int) $this->db->query("SELECT COUNT(*) FROM `DISPUTE` WHERE status IN ('Open', 'Under_Review', 'Escalated')")->fetchColumn();

                return [
                    'total_users'      => $usersCount,
                    'total_products'   => $prodsCount,
                    'total_rentals'    => $rentalsCount,
                    'active_rentals'   => $activeRentals,
                    'gross_revenue'    => $grossRevenue,
                    'deposits_held'    => $depositsHeld,
                    'fines_collected'  => $finesCollected,
                    'open_disputes'    => $openDisputes
                ];

            case 'rentals':
                $stmt = $this->db->query("
                    SELECT r.request_id, r.start_date, r.end_date, r.status, r.request_date,
                           p.title AS product_title, p.rent_per_day,
                           u_renter.name AS renter_name, u_owner.name AS owner_name,
                           DATEDIFF(r.end_date, r.start_date) AS total_days,
                           (DATEDIFF(r.end_date, r.start_date) * p.rent_per_day) AS total_amount
                    FROM `RENTAL_REQUEST` r
                    JOIN `PRODUCT` p ON r.product_id = p.product_id
                    JOIN `USER` u_renter ON r.renter_id = u_renter.user_id
                    JOIN `USER` u_owner ON p.owner_id = u_owner.user_id
                    ORDER BY r.request_id DESC
                    LIMIT 100
                ");
                return $stmt->fetchAll();

            case 'revenue':
                $stmt = $this->db->query("
                    SELECT t.transaction_id, t.request_id, t.rental_amount, t.deposit_amount, 
                           t.deposit_status, t.payment_mode, t.payment_status, t.payment_date,
                           u.name AS payer_name, p.title AS product_title
                    FROM `TRANSACTION` t
                    JOIN `USER` u ON t.payer_id = u.user_id
                    JOIN `RENTAL_REQUEST` r ON t.request_id = r.request_id
                    JOIN `PRODUCT` p ON r.product_id = p.product_id
                    ORDER BY t.transaction_id DESC
                    LIMIT 100
                ");
                return $stmt->fetchAll();

            case 'fines':
                $stmt = $this->db->query("
                    SELECT f.fine_id, f.request_id, f.fine_type, f.amount, f.late_days, 
                           f.issue_date, f.paid_date, f.status,
                           u.name AS renter_name, p.title AS product_title
                    FROM `FINE` f
                    JOIN `USER` u ON f.renter_id = u.user_id
                    JOIN `RENTAL_REQUEST` r ON f.request_id = r.request_id
                    JOIN `PRODUCT` p ON r.product_id = p.product_id
                    ORDER BY f.fine_id DESC
                    LIMIT 100
                ");
                return $stmt->fetchAll();

            default:
                return [];
        }
    }

    /**
     * Configure Fine Rate (Synopsis Section 11.1 Class Diagram).
     */
    public function configureFineRate(float $rate): void {
        require_once __DIR__ . '/../config/fine_config.php';
        set_fine_rate($rate);
    }

    /**
     * Resolve Dispute (Synopsis Section 11.1 Class Diagram & Rule 13).
     */
    public function resolveDispute(int $disputeId, string $notes, bool $waiveFine = false): bool {
        $dispute = Dispute::findById($disputeId);
        if (!$dispute) {
            throw new ORMSException("Dispute #{$disputeId} not found.");
        }
        return $dispute->resolve($notes, $waiveFine);
    }
}
