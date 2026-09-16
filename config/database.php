<?php
/**
 * Online Rental Management System (ORMS)
 * Database Connection Configuration (Singleton Pattern)
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Tech Stack: PHP 8.1+ OOP, PDO, MySQL 8.0 / InnoDB
 * 
 * Implements a thread-safe singleton wrapper around PHP PDO to guarantee
 * a single shared database connection instance per request lifecycle.
 */

declare(strict_types=1);

// Set default timezone to match system / project region (Asia/Kolkata)
date_default_timezone_set('Asia/Kolkata');

class Database {
    private static ?Database $instance = null;
    private ?PDO $connection = null;

    // Database connection credentials (default to standard XAMPP settings)
    private string $host = '127.0.0.1';
    private string $dbName = 'orms_db';
    private string $username = 'root';
    private string $password = '';
    private int $port = 3306;
    private string $charset = 'utf8mb4';

    /**
     * Private constructor to prevent direct instantiation from outside.
     */
    private function __construct() {
        // Allow overriding via environment variables or constants if defined
        if (defined('DB_HOST')) {
            $this->host = (string) DB_HOST;
        } elseif (getenv('DB_HOST')) {
            $this->host = (string) getenv('DB_HOST');
        }

        if (defined('DB_NAME')) {
            $this->dbName = (string) DB_NAME;
        } elseif (getenv('DB_NAME')) {
            $this->dbName = (string) getenv('DB_NAME');
        }

        if (defined('DB_USER')) {
            $this->username = (string) DB_USER;
        } elseif (getenv('DB_USER')) {
            $this->username = (string) getenv('DB_USER');
        }

        if (defined('DB_PASS')) {
            $this->password = (string) DB_PASS;
        } elseif (getenv('DB_PASS') !== false) {
            $this->password = (string) getenv('DB_PASS');
        }

        if (defined('DB_PORT')) {
            $this->port = (int) DB_PORT;
        } elseif (getenv('DB_PORT')) {
            $this->port = (int) getenv('DB_PORT');
        }

        $dsn = "mysql:host={$this->host};port={$this->port};dbname={$this->dbName};charset={$this->charset}";

        $options = [
            // Throw PDOException on errors for robust try-catch handling
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Fetch rows as associative arrays by default
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Turn off emulation to ensure genuine prepared statements on MySQL server
            PDO::ATTR_EMULATE_PREPARES   => false,
            // Enforce connection-level charset and collation
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES '{$this->charset}' COLLATE 'utf8mb4_unicode_ci'"
        ];

        try {
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            // Log error securely without exposing sensitive database credentials in production
            error_log("ORMS Database Connection Error: " . $e->getMessage());
            throw new PDOException("Database connection error: Unable to connect to ORMS database ({$this->dbName}). Please ensure MySQL is running.");
        }
    }

    /**
     * Retrieves the single Database instance (Singleton).
     */
    public static function getInstance(): Database {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Retrieves the active PDO connection instance.
     */
    public function getConnection(): PDO {
        return $this->connection;
    }

    /**
     * Prevent cloning of the singleton instance.
     */
    private function __clone() {}

    /**
     * Prevent unserializing of the singleton instance.
     */
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton Database instance.");
    }
}
