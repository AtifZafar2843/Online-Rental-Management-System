<?php
/**
 * Online Rental Management System (ORMS)
 * BaseUser Abstract Class
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 4 & Synopsis Section 11.1
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/exceptions/ORMSException.php';
require_once __DIR__ . '/exceptions/UnauthorizedActionException.php';

abstract class BaseUser {
    protected ?int $userID = null;
    protected string $username = '';
    protected string $email = '';
    protected string $phone = '';
    protected string $password = '';
    protected string $address = '';
    protected string $status = 'Active';
    protected float $rating = 0.00;
    protected array $roles = [];

    protected PDO $db;

    public function __construct(
        ?int $userID = null,
        string $username = '',
        string $email = '',
        string $phone = '',
        string $password = '',
        string $address = '',
        string $status = 'Active',
        float $rating = 0.00,
        array $roles = []
    ) {
        $this->userID = $userID;
        $this->username = $username;
        $this->email = $email;
        $this->phone = $phone;
        $this->password = $password;
        $this->address = $address;
        $this->status = $status;
        $this->rating = $rating;
        $this->roles = $roles;

        $this->db = Database::getInstance()->getConnection();
    }

    // Getters & Setters
    public function getUserID(): ?int { return $this->userID; }
    public function setUserID(int $id): void { $this->userID = $id; }

    public function getUsername(): string { return $this->username; }
    public function setUsername(string $username): void { $this->username = $username; }

    public function getEmail(): string { return $this->email; }
    public function setEmail(string $email): void { $this->email = $email; }

    public function getPhone(): string { return $this->phone; }
    public function setPhone(string $phone): void { $this->phone = $phone; }

    public function getPassword(): string { return $this->password; }
    public function setPassword(string $password): void { $this->password = $password; }

    public function getAddress(): string { return $this->address; }
    public function setAddress(string $address): void { $this->address = $address; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): void { $this->status = $status; }

    public function getRating(): float { return $this->rating; }
    public function setRating(float $rating): void { $this->rating = $rating; }

    public function getRoles(): array { return $this->roles; }
    public function setRoles(array $roles): void { $this->roles = $roles; }
    public function hasRole(string $role): bool { return in_array($role, $this->roles, true); }

    /**
     * Authenticate user credentials and initialize a secure session.
     */
    public function login(): bool {
        // Fetch user from USER table by email
        $stmt = $this->db->prepare("
            SELECT user_id, name, email, phone, address, password, rating, status 
            FROM `USER` 
            WHERE email = :email 
            LIMIT 1
        ");
        $stmt->execute(['email' => $this->email]);
        $userData = $stmt->fetch();

        if (!$userData || !password_verify($this->password, $userData['password'])) {
            return false;
        }

        if ($userData['status'] !== 'Active') {
            $this->status = $userData['status'];
            return false;
        }

        // Fetch user roles from USER_ROLES junction table
        $roleStmt = $this->db->prepare("
            SELECT role FROM `USER_ROLES` WHERE user_id = :user_id ORDER BY role
        ");
        $roleStmt->execute(['user_id' => $userData['user_id']]);
        $roles = $roleStmt->fetchAll(PDO::FETCH_COLUMN);

        $this->userID = (int) $userData['user_id'];
        $this->username = $userData['name'];
        $this->phone = $userData['phone'];
        $this->address = $userData['address'];
        $this->status = $userData['status'];
        $this->rating = (float) $userData['rating'];
        $this->roles = $roles;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            session_start();
        }
        if (!headers_sent()) {
            session_regenerate_id(true);
        }

        $_SESSION['user_id'] = $this->userID;
        $_SESSION['name'] = $this->username;
        $_SESSION['email'] = $this->email;
        $_SESSION['roles'] = $this->roles;
        // Default active role to Owner if present, else Renter
        $_SESSION['active_role'] = in_array('Owner', $this->roles, true) ? 'Owner' : ($this->roles[0] ?? 'Renter');

        return true;
    }

    /**
     * Terminate the session and log out the user.
     */
    public function logout(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION = [];
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }
        session_destroy();
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(): bool {
        if (!$this->userID) {
            return false;
        }

        $stmt = $this->db->prepare("
            UPDATE `USER` 
            SET name = :name, phone = :phone, address = :address 
            WHERE user_id = :user_id
        ");
        return $stmt->execute([
            'name' => $this->username,
            'phone' => $this->phone,
            'address' => $this->address,
            'user_id' => $this->userID
        ]);
    }

    /**
     * Reset password using a valid, unexpired reset token.
     */
    public function resetPassword(string $token): bool {
        // Hash the incoming token to match the stored hash (Synopsis Section 7)
        $hashedToken = hash('sha256', $token);

        $stmt = $this->db->prepare("
            SELECT user_id 
            FROM `USER` 
            WHERE reset_token = :token 
              AND reset_token_expiry > NOW() 
            LIMIT 1
        ");
        $stmt->execute(['token' => $hashedToken]);
        $user = $stmt->fetch();

        if (!$user) {
            return false;
        }

        $hashedPassword = password_hash($this->password, PASSWORD_BCRYPT);
        $updateStmt = $this->db->prepare("
            UPDATE `USER` 
            SET password = :password, reset_token = NULL, reset_token_expiry = NULL 
            WHERE user_id = :user_id
        ");
        return $updateStmt->execute([
            'password' => $hashedPassword,
            'user_id' => $user['user_id']
        ]);
    }

    /**
     * Generate and store a secure reset token for this user's email.
     * Returns the plaintext token for delivery, while storing SHA-256 hash in DB.
     */
    public static function initiatePasswordReset(string $email): ?string {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare("SELECT user_id, status FROM `USER` WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch();

        if (!$user || $user['status'] !== 'Active') {
            return null;
        }

        // Cryptographically secure token
        $rawToken = bin2hex(random_bytes(32)); // 64 hex characters
        $hashedToken = hash('sha256', $rawToken);

        $updateStmt = $db->prepare("
            UPDATE `USER` 
            SET reset_token = :token, 
                reset_token_expiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) 
            WHERE user_id = :user_id
        ");
        $updateStmt->execute([
            'token' => $hashedToken,
            'user_id' => $user['user_id']
        ]);

        return $rawToken;
    }
}
