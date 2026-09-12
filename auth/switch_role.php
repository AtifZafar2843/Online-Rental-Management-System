<?php
/**
 * Online Rental Management System (ORMS)
 * Role Switcher Endpoint (Dual-Role Support)
 * 
 * Allows users with multiple roles (Owner & Renter) to toggle their active role
 * seamlessly via $_SESSION['active_role'] without re-authenticating (Synopsis Rule 14).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/functions.php';

require_login();

$targetRole = $_GET['role'] ?? '';

if (in_array($targetRole, ['Owner', 'Renter'], true) && has_role($targetRole)) {
    $_SESSION['active_role'] = $targetRole;
    session_regenerate_id(true); // Secure privilege refresh
    set_flash('success', "Switched active workspace to {$targetRole} view.");

    if ($targetRole === 'Owner') {
        header("Location: " . base_url('owner/dashboard.php'));
    } else {
        header("Location: " . base_url('renter/dashboard.php'));
    }
    exit;
}

set_flash('error', 'Invalid role selection or role not assigned to your account.');
header("Location: " . base_url('index.php'));
exit;
