<?php
/**
 * Online Rental Management System (ORMS)
 * Admin Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/BaseUser.php';

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

    public function manageUsers(): array {
        $stmt = $this->db->query("
            SELECT u.user_id, u.name, u.email, u.phone, u.status, u.rating, u.reg_date,
                   GROUP_CONCAT(ur.role SEPARATOR ', ') AS roles
            FROM `USER` u
            LEFT JOIN `USER_ROLES` ur ON u.user_id = ur.user_id
            GROUP BY u.user_id
            ORDER BY u.user_id DESC
        ");
        return $stmt->fetchAll();
    }

    public function generateReport(string $type): array {
        // Handled in detail in admin/reports.php
        return [];
    }

    public function configureFineRate(float $rate): void {
        require_once __DIR__ . '/../config/fine_config.php';
        set_fine_rate($rate);
    }

    public function resolveDispute(int $disputeId, string $notes): bool {
        $stmt = $this->db->prepare("
            UPDATE `DISPUTE` 
            SET status = 'Resolved', admin_notes = :notes, resolved_date = NOW() 
            WHERE dispute_id = :id
        ");
        return $stmt->execute([
            'notes' => $notes,
            'id' => $disputeId
        ]);
    }
}
