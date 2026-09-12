<?php
/**
 * Online Rental Management System (ORMS)
 * Cancel Rental Request Endpoint (POST)
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 6), Section 6 (Step 6) & Section 7
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Renter.php';
require_once __DIR__ . '/../classes/exceptions/ORMSException.php';
require_once __DIR__ . '/../classes/exceptions/UnauthorizedActionException.php';

require_role('Renter');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

if (!csrf_verify()) {
    set_flash('error', 'Security token expired. Please reload and try again.');
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

$requestId = (int) ($_POST['request_id'] ?? 0);
$reason = trim($_POST['cancellation_reason'] ?? '');

if ($requestId <= 0) {
    set_flash('error', 'Invalid rental request specified.');
    header('Location: ' . base_url('renter/my_rentals.php'));
    exit;
}

$renterId = (int) current_user_id();
$renter = new Renter($renterId);

try {
    if ($renter->cancelRequest($requestId, $reason)) {
        set_flash('success', "Rental request #{$requestId} was successfully cancelled.");
    } else {
        set_flash('error', "Failed to cancel request #{$requestId}. Please check the current status.");
    }
} catch (UnauthorizedActionException $e) {
    set_flash('error', $e->getMessage());
} catch (ORMSException $e) {
    set_flash('error', $e->getMessage());
} catch (Exception $e) {
    set_flash('error', 'An error occurred while cancelling: ' . $e->getMessage());
}

header('Location: ' . base_url('renter/my_rentals.php'));
exit;
