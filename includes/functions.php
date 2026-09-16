<?php
/**
 * Online Rental Management System (ORMS)
 * Shared Core Helper Functions & Security Guards
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Tech Stack: PHP 8.1+ OOP, PDO, Session Security, CSRF, Magic-Byte MIME Validation
 */

declare(strict_types=1);

// Set default timezone to match system / project region (Asia/Kolkata)
date_default_timezone_set('Asia/Kolkata');

// Ensure session is started with secure cookie parameters
if (session_status() === PHP_SESSION_NONE) {
    // Configure session cookie params for security
    ini_set('session.cookie_httponly', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_start();
}

/**
 * Returns the project base URL path.
 */
function base_url(string $path = ''): string {
    // Standard relative web root path for localhost/orms/
    $base = '/orms';
    $cleanPath = ltrim($path, '/');
    return $cleanPath ? "{$base}/{$cleanPath}" : $base;
}

/**
 * Redirect to a relative path within the application and terminate execution.
 */
function redirect(string $path): void {
    header("Location: " . base_url($path));
    exit;
}

/**
 * Generate a cryptographically secure CSRF token and store it in session.
 */
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Render a hidden CSRF input field for forms.
 */
function csrf_field(): string {
    $token = generate_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Verify submitted CSRF token against session token.
 */
function verify_csrf_token(?string $token): bool {
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Convenience helper to verify CSRF token from POST request or passed parameter.
 */
function csrf_verify(?string $token = null): bool {
    $tokenToVerify = $token ?? ($_POST['csrf_token'] ?? null);
    return verify_csrf_token($tokenToVerify);
}

/**
 * Alias for csrf_verify.
 */
function verify_csrf(?string $token = null): bool {
    return csrf_verify($token);
}

/**
 * Set a user flash message for display on next request.
 * Types: 'success', 'error', 'info', 'warning'
 */
function set_flash(string $type, string $message): void {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Retrieve and clear queued flash messages.
 * If $type is specified, returns the first message string for that type (or null).
 * If $type is null, returns all messages array for universal display (e.g. in header.php).
 */
function get_flash(?string $type = null): array|string|null {
    if (!isset($_SESSION['flash_messages']) || !is_array($_SESSION['flash_messages'])) {
        return $type === null ? [] : null;
    }

    if ($type !== null) {
        foreach ($_SESSION['flash_messages'] as $key => $flash) {
            if (isset($flash['type'], $flash['message']) && $flash['type'] === $type) {
                $msg = (string) $flash['message'];
                unset($_SESSION['flash_messages'][$key]);
                $_SESSION['flash_messages'] = array_values($_SESSION['flash_messages']);
                return $msg;
            }
        }
        return null;
    }

    $messages = $_SESSION['flash_messages'];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Sanitize string input to prevent XSS.
 */
function sanitize_input(string $data): string {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Auth Check: Is a user or admin currently logged in?
 */
function is_logged_in(): bool {
    return !empty($_SESSION['user_id']) || !empty($_SESSION['admin_id']);
}

/**
 * Check if the currently logged in user is an Admin.
 */
function is_admin(): bool {
    return !empty($_SESSION['admin_id']) || (!empty($_SESSION['active_role']) && $_SESSION['active_role'] === 'Admin');
}

/**
 * Retrieve the active role in current session ('Owner', 'Renter', or 'Admin').
 */
function current_role(): string {
    return $_SESSION['active_role'] ?? '';
}

/**
 * Retrieve all roles assigned to current user.
 */
function user_roles(): array {
    return $_SESSION['roles'] ?? [];
}

/**
 * Check if current user possesses a specific role.
 */
function has_role(string $role): bool {
    return in_array($role, user_roles(), true);
}

/**
 * Retrieve current user ID (or admin ID).
 */
function current_user_id(): ?int {
    return $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
}

/**
 * Retrieve current user's display name.
 */
function current_user_name(): string {
    return $_SESSION['name'] ?? 'User';
}

/**
 * Retrieve current user's email address.
 */
function current_user_email(): string {
    return $_SESSION['email'] ?? '';
}

/**
 * Guard: Require logged-in status. Redirects to login if unauthenticated.
 */
function require_login(string $redirectAfter = ''): void {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to access this page.');
        $loginUrl = base_url('auth/login.php');
        if ($redirectAfter) {
            $loginUrl .= '?redirect=' . urlencode($redirectAfter);
        }
        header("Location: {$loginUrl}");
        exit;
    }
}

/**
 * Guard: Require guest (unauthenticated) status. Redirects to dashboard if logged in.
 */
function require_guest(): void {
    if (is_logged_in()) {
        if (is_admin()) {
            header("Location: " . base_url('admin/dashboard.php'));
        } elseif (current_role() === 'Owner') {
            header("Location: " . base_url('owner/dashboard.php'));
        } else {
            header("Location: " . base_url('renter/dashboard.php'));
        }
        exit;
    }
}

/**
 * Guard: Require specific active role.
 */
function require_role(string $requiredRole): void {
    require_login();

    // Check if user has this role
    if (!has_role($requiredRole) && !is_admin()) {
        require_once __DIR__ . '/../classes/exceptions/UnauthorizedActionException.php';
        set_flash('error', "Access Denied: You need the '{$requiredRole}' role to access this section.");
        header("Location: " . base_url('index.php'));
        exit;
    }

    // If user has the role but active_role is set to something else, switch it automatically or warn
    if (current_role() !== $requiredRole && has_role($requiredRole)) {
        $_SESSION['active_role'] = $requiredRole;
    }
}

/**
 * Guard: Require Admin access.
 */
function require_admin(): void {
    require_login();
    if (!is_admin()) {
        set_flash('error', 'Access Denied: Administrator privileges required.');
        header("Location: " . base_url('index.php'));
        exit;
    }
}

/**
 * Secure file upload validator using finfo_file magic-byte inspection.
 * (Synopsis Section 5 & Prompt Guide Section 7: never trust Content-Type or extension alone)
 * 
 * @param array $file $_FILES['input_name'] entry
 * @param array $allowedMimes Array of valid MIME types (e.g. ['image/jpeg', 'image/png', 'application/pdf'])
 * @param int $maxBytes Maximum file size in bytes (default 5MB)
 * @return array ['valid' => bool, 'error' => ?string, 'mime' => ?string, 'extension' => ?string]
 */
function validate_file_upload(array $file, array $allowedMimes, int $maxBytes = 5242880): array {
    if (!isset($file['error']) || is_array($file['error'])) {
        return ['valid' => false, 'error' => 'Invalid file parameter.', 'mime' => null, 'extension' => null];
    }

    // Check PHP upload error code
    switch ($file['error']) {
        case UPLOAD_ERR_OK:
            break;
        case UPLOAD_ERR_NO_FILE:
            return ['valid' => false, 'error' => 'No file was uploaded.', 'mime' => null, 'extension' => null];
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return ['valid' => false, 'error' => 'Uploaded file exceeds maximum allowed file size.', 'mime' => null, 'extension' => null];
        default:
            return ['valid' => false, 'error' => 'Unknown file upload error occurred.', 'mime' => null, 'extension' => null];
    }

    // Check file size
    if ($file['size'] > $maxBytes) {
        $maxMb = round($maxBytes / (1024 * 1024), 1);
        return ['valid' => false, 'error' => "File size exceeds the {$maxMb}MB limit.", 'mime' => null, 'extension' => null];
    }

    // Verify MIME type using finfo_file (magic bytes verification)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $allowedMimes, true)) {
        return [
            'valid' => false, 
            'error' => "Invalid file format detected: {$mimeType}. Allowed formats: " . implode(', ', $allowedMimes),
            'mime' => $mimeType,
            'extension' => null
        ];
    }

    // Map MIME type to safe extension
    $mimeToExt = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf'
    ];
    $ext = $mimeToExt[$mimeType] ?? 'bin';

    return [
        'valid' => true,
        'error' => null,
        'mime' => $mimeType,
        'extension' => $ext
    ];
}
