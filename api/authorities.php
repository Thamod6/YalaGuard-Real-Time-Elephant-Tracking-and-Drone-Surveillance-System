<?php
/**
 * YalaGuard Authorities Management API
 * 
 * This API handles CRUD operations for authority persons who receive alerts
 */

// Include database configuration
require_once '../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = getDatabase();
    $collection = $db->selectCollection('authorities');
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get all authorities or specific authority by ID
            if (isset($_GET['id'])) {
                $authority = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($_GET['id'])]);
                if ($authority) {
                    $authority['_id'] = (string)$authority['_id'];
                    echo json_encode(['status' => 'success', 'data' => $authority]);
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Authority not found']);
                }
            } else {
                $cursor = $collection->find([], ['sort' => ['name' => 1]]);
                $authorities = [];
                foreach ($cursor as $authority) {
                    $authority['_id'] = (string)$authority['_id'];
                    $authorities[] = $authority;
                }
                echo json_encode(['status' => 'success', 'data' => $authorities]);
            }
            break;
            
        case 'POST':
            // Create new authority
            $input = json_decode(file_get_contents('php://input'), true);
            
            $requiredFields = ['name', 'phone', 'email', 'role', 'organization'];
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
                    'message' => 'Missing required fields: ' . implode(', ', $missingFields)
                ]);
                exit();
            }
            
            $authorityData = [
                'name' => trim($input['name']),
                'phone' => trim($input['phone']),
                'email' => trim($input['email']),
                'role' => trim($input['role']),
                'organization' => trim($input['organization']),
                'department' => trim($input['department'] ?? ''),
                'alert_preferences' => [
                    'sms_enabled' => isset($input['sms_enabled']) ? (bool)$input['sms_enabled'] : true,
                    'email_enabled' => isset($input['email_enabled']) ? (bool)$input['email_enabled'] : true,
                    'alert_levels' => $input['alert_levels'] ?? ['critical', 'high', 'medium']
                ],
                'active' => true,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ];
            
            $result = $collection->insertOne($authorityData);
            
            if ($result->getInsertedCount() > 0) {
                $authorityData['_id'] = (string)$result->getInsertedId();
                http_response_code(201);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Authority created successfully',
                    'data' => $authorityData
                ]);
            } else {
                throw new Exception('Failed to create authority');
            }
            break;
            
        case 'PUT':
            // Update existing authority
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Authority ID required for update']);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            $updateData = [];
            $allowedFields = ['name', 'phone', 'email', 'role', 'organization', 'department', 'alert_preferences', 'active'];
            
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'No valid fields to update']);
                exit();
            }
            
            $updateData['updated_at'] = new MongoDB\BSON\UTCDateTime();
            
            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($_GET['id'])],
                ['$set' => $updateData]
            );
            
            if ($result->getModifiedCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Authority updated successfully'
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'No changes made to authority'
                ]);
            }
            break;
            
        case 'DELETE':
            // Delete authority (soft delete)
            if (!isset($_GET['id'])) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Authority ID required for deletion']);
                exit();
            }
            
            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($_GET['id'])],
                ['$set' => ['active' => false, 'updated_at' => new MongoDB\BSON\UTCDateTime()]]
            );
            
            if ($result->getModifiedCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Authority deactivated successfully'
                ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to deactivate authority'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
