<?php
/**
 * Online Rental Management System (ORMS)
 * Rental Due Date Reminder Automation Script
 * 
 * Project: BCSP-064 (IGNOU BCA Final Project)
 * Specification: Prompt Guide Section 5 (Rule 11) & Synopsis Section 13.IX (Page 31)
 * 
 * Usage:
 *   CLI: php scripts/check_due_reminders.php
 *   Web: http://localhost/orms/scripts/check_due_reminders.php (Admin only or CLI)
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Notification.php';

$isCli = (php_sapi_name() === 'cli');

// If accessed via web browser, ensure user is Admin or authenticated
if (!$isCli) {
    if (!is_logged_in() || !is_admin()) {
        http_response_code(403);
        echo "<h2 style='color:red;font-family:sans-serif;'>403 Forbidden: Administrator access or CLI execution required.</h2>";
        exit;
    }
}

$startTime = microtime(true);
$dateTomorrow = date('Y-m-d', strtotime('+1 day'));

if ($isCli) {
    echo "=======================================================\n";
    echo "  ORMS Scheduled Task: Rental Due Date Reminder\n";
    echo "=======================================================\n";
    echo "Scanning active rentals ending on: {$dateTomorrow}...\n";
}

try {
    $sentCount = Notification::sendDueDateReminders();
    $elapsed = round((microtime(true) - $startTime) * 1000, 2);

    if ($isCli) {
        echo "[ SUCCESS ] Processing completed in {$elapsed} ms.\n";
        echo "Reminders Dispatched: {$sentCount} notification(s) created.\n";
        echo "=======================================================\n";
    } else {
        header('Content-Type: text/html; charset=utf-8');
        echo "<!DOCTYPE html><html><head><title>Due Date Reminder Status</title><script src='https://cdn.tailwindcss.com'></script></head>";
        echo "<body class='bg-slate-950 text-slate-100 p-8 font-sans'>";
        echo "<div class='max-w-xl mx-auto bg-slate-900 border border-slate-800 rounded-2xl p-6 shadow-xl'>";
        echo "<h1 class='text-xl font-bold text-white flex items-center gap-2'><span>🔔</span> Due Date Reminders Processed</h1>";
        echo "<p class='text-sm text-slate-400 mt-2'>Scanned active rentals ending tomorrow: <strong class='text-slate-200'>{$dateTomorrow}</strong>.</p>";
        echo "<div class='mt-6 p-4 bg-emerald-950/40 border border-emerald-800/60 rounded-xl text-emerald-400 text-sm'>";
        echo "✅ <strong>{$sentCount}</strong> reminder notification(s) dispatched in {$elapsed} ms.";
        echo "</div>";
        echo "<div class='mt-6'><a href='../admin/dashboard.php' class='text-blue-400 hover:text-blue-300 text-xs font-semibold'>&larr; Return to Admin Dashboard</a></div>";
        echo "</div></body></html>";
    }
} catch (Throwable $e) {
    if ($isCli) {
        echo "[ ERROR ] Failed to send due date reminders: " . $e->getMessage() . "\n";
        exit(1);
    } else {
        http_response_code(500);
        echo "<p style='color:red;'>Error: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
}
