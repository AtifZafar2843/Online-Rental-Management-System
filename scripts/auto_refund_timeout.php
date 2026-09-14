<?php
/**
 * Online Rental Management System (ORMS)
 * Rule 12: Auto-Refund Timeout Script
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 Rule 12
 * 
 * Usage:
 *   CLI: php scripts/auto_refund_timeout.php
 *   Web: http://localhost/orms/scripts/auto_refund_timeout.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../classes/Fine.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    header('Content-Type: application/json; charset=utf-8');
}

try {
    $processed = Fine::checkAndTriggerAutoRefunds();
    $response = [
        'status'    => 'success',
        'timestamp' => date('Y-m-d H:i:s'),
        'rule'      => 'Rule 12 Auto-Refund Timeout (7 days)',
        'processed' => $processed,
        'message'   => "Successfully processed {$processed} overdue rental(s) with automated deposit refunds."
    ];

    if ($isCli) {
        echo "[ORMS Auto-Refund] Processed: {$processed} overdue rentals at " . date('Y-m-d H:i:s') . PHP_EOL;
    } else {
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
} catch (Exception $e) {
    if ($isCli) {
        fwrite(STDERR, "[ORMS Auto-Refund ERROR] " . $e->getMessage() . PHP_EOL);
        exit(1);
    } else {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => $e->getMessage()
        ], JSON_PRETTY_PRINT);
    }
}
