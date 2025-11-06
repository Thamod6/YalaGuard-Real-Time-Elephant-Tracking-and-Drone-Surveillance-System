#!/usr/bin/env php
<?php
/**
 * YalaGuard Elephant Status Check Cron Job
 * 
 * This script should be run every hour via cron to check elephant status
 * and generate alerts for violations and stationary elephants.
 * 
 * Cron setup example:
 * 0 * * * * /usr/bin/php /path/to/yalaguard/cron/check-elephants.php >> /var/log/yalaguard-cron.log 2>&1
 * 
 * Or for Windows Task Scheduler, run this script every hour
 */

// Set timezone
date_default_timezone_set('Asia/Colombo');

// Set unlimited execution time for cron jobs
set_time_limit(0);
ini_set('memory_limit', '512M');

// Include the alert system
require_once __DIR__ . '/../api/alert-system.php';

// Log start of cron job
$startTime = microtime(true);
$logMessage = "[" . date('Y-m-d H:i:s') . "] Starting elephant status check cron job\n";
echo $logMessage;
error_log($logMessage);

try {
    // Run the alert system
    $results = runAlertSystem();
    
    // Calculate execution time
    $executionTime = round((microtime(true) - $startTime) * 1000, 2);
    
    // Log results
    $summaryMessage = "[" . date('Y-m-d H:i:s') . "] Cron job completed in {$executionTime}ms\n";
    $summaryMessage .= "Elephants checked: {$results['elephants_checked']}\n";
    $summaryMessage .= "Alerts generated: {$results['alerts_generated']}\n";
    $summaryMessage .= "Alerts sent: {$results['alerts_sent']}\n";
    
    if (!empty($results['errors'])) {
        $summaryMessage .= "Errors encountered: " . count($results['errors']) . "\n";
        foreach ($results['errors'] as $error) {
            $summaryMessage .= "  - {$error}\n";
        }
    }
    
    echo $summaryMessage;
    error_log($summaryMessage);
    
    // Exit with success code
    exit(0);
    
} catch (Exception $e) {
    $errorMessage = "[" . date('Y-m-d H:i:s') . "] Cron job failed: " . $e->getMessage() . "\n";
    echo $errorMessage;
    error_log($errorMessage);
    
    // Exit with error code
    exit(1);
}
?>
