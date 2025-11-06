<?php
// Start output buffering immediately
ob_start();

// Suppress errors
error_reporting(0);
ini_set('display_errors', 0);

// Set JSON headers
header('Content-Type: application/json');

// Test 1: Basic response
echo json_encode([
    'status' => 'success',
    'message' => 'Basic test successful',
    'timestamp' => date('Y-m-d H:i:s')
]);

// Check for unexpected output
$output = ob_get_clean();
if (!empty($output)) {
    error_log('Unexpected output detected: ' . $output);
}
?>
