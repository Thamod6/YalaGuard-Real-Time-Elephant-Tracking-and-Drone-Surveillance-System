<?php
/**
 * YalaGuard Test Connection Endpoint
 * 
 * This file provides a simple way to test the MongoDB connection
 * and verify the database configuration is working correctly.
 */

// Include database configuration
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET method for testing connection
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Only GET requests are supported.',
        'allowed_methods' => ['GET']
    ]);
    exit();
}

try {
    // Test the MongoDB connection
    $connectionResult = testConnection();
    
    if ($connectionResult['status'] === 'success') {
        // Try to get collection info
        $collection = getCollection();
        $count = $collection->countDocuments();
        
        $response = [
            'status' => 'success',
            'message' => 'MongoDB connection successful',
            'database_info' => [
                'connection' => $connectionResult,
                'collection_count' => $count,
                'timestamp' => date('Y-m-d H:i:s')
            ]
        ];
        
        http_response_code(200);
    } else {
        $response = [
            'status' => 'error',
            'message' => 'MongoDB connection failed',
            'error_details' => $connectionResult,
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        http_response_code(500);
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Connection test failed: ' . $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}
?>
