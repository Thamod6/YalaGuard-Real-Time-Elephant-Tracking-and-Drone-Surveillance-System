<?php
/**
 * YalaGuard Alerts API Endpoint
 * 
 * This file handles alert creation and management for the YalaGuard system.
 * It accepts POST requests with alert data and stores them in MongoDB.
 */

// Include database configuration
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Handle CORS (Cross-Origin Resource Sharing)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST method for creating alerts
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Only POST requests are supported.',
        'allowed_methods' => ['POST']
    ]);
    exit();
}

try {
    // Get JSON input from request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $requiredFields = ['elephant_id', 'location', 'message', 'timestamp'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields: ' . implode(', ', $missingFields),
            'required_fields' => $requiredFields,
            'received_data' => $input
        ]);
        exit();
    }
    
    // Validate timestamp format (should be ISO 8601)
    if (!strtotime($input['timestamp'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid timestamp format. Use ISO 8601 format (e.g., 2024-01-15T10:30:00Z)',
            'received_timestamp' => $input['timestamp']
        ]);
        exit();
    }
    
    // Prepare alert document
    $alertDocument = [
        'elephant_id' => $input['elephant_id'],
        'location' => $input['location'],
        'message' => $input['message'],
        'timestamp' => new MongoDB\BSON\UTCDateTime(strtotime($input['timestamp']) * 1000),
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'status' => 'active'
    ];
    
    // Get collection and insert document
    $collection = getCollection();
    $result = $collection->insertOne($alertDocument);
    
    // Check if insertion was successful
    if ($result->getInsertedCount() > 0) {
        $insertedId = $result->getInsertedId();
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => 'Alert created successfully',
            'alert_id' => (string) $insertedId,
            'created_alert' => [
                'elephant_id' => $alertDocument['elephant_id'],
                'location' => $alertDocument['location'],
                'message' => $alertDocument['message'],
                'timestamp' => $input['timestamp'],
                'status' => $alertDocument['status']
            ]
        ]);
    } else {
        throw new Exception('Failed to insert alert into database');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
