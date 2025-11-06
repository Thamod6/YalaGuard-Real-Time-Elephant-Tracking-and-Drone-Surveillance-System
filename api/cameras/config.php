<?php
/**
 * YalaGuard Camera Configuration API
 * 
 * This endpoint manages camera configurations including adding, updating,
 * and removing cameras from the system.
 */

// Include database configuration
require_once '../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session to check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required'
    ]);
    exit();
}

try {
    $db = getDatabase();
    $camerasCollection = $db->selectCollection('cameras');
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get all cameras or specific camera
            $cameraId = $_GET['id'] ?? null;
            
            if ($cameraId) {
                $camera = $camerasCollection->findOne(['_id' => $cameraId]);
                if (!$camera) {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Camera not found'
                    ]);
                    exit();
                }
                
                echo json_encode([
                    'status' => 'success',
                    'camera' => $camera
                ]);
            } else {
                $cameras = $camerasCollection->find([])->toArray();
                echo json_encode([
                    'status' => 'success',
                    'cameras' => $cameras,
                    'count' => count($cameras)
                ]);
            }
            break;
            
        case 'POST':
            // Add new camera
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate required fields
            $requiredFields = ['name', 'type', 'ip', 'port', 'username', 'password', 'location'];
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
                    'required_fields' => $requiredFields
                ]);
                exit();
            }
            
            // Check if camera with same IP already exists
            $existingCamera = $camerasCollection->findOne(['ip' => $input['ip']]);
            if ($existingCamera) {
                http_response_code(409);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Camera with this IP address already exists'
                ]);
                exit();
            }
            
            // Create camera document
            $cameraDocument = [
                'name' => $input['name'],
                'type' => $input['type'], // 'drone' or 'ip'
                'ip' => $input['ip'],
                'port' => (int) $input['port'],
                'username' => $input['username'],
                'password' => $input['password'],
                'location' => $input['location'],
                'rtsp_url' => $input['rtsp_url'] ?? null,
                'http_url' => $input['http_url'] ?? null,
                'status' => 'offline', // Default to offline until tested
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => $_SESSION['user_id']
            ];
            
            $result = $camerasCollection->insertOne($cameraDocument);
            
            if ($result->getInsertedCount() > 0) {
                $cameraDocument['_id'] = $result->getInsertedId();
                
                http_response_code(201);
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Camera added successfully',
                    'camera' => $cameraDocument
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to add camera'
                ]);
            }
            break;
            
        case 'PUT':
            // Update existing camera
            $cameraId = $_GET['id'] ?? null;
            if (!$cameraId) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Camera ID required for updates'
                ]);
                exit();
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Check if camera exists
            $existingCamera = $camerasCollection->findOne(['_id' => $cameraId]);
            if (!$existingCamera) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Camera not found'
                ]);
                exit();
            }
            
            // Update fields
            $updateData = [];
            $updatableFields = ['name', 'type', 'ip', 'port', 'username', 'password', 'location', 'rtsp_url', 'http_url', 'status'];
            
            foreach ($updatableFields as $field) {
                if (isset($input[$field])) {
                    $updateData[$field] = $input[$field];
                }
            }
            
            if (empty($updateData)) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'No fields to update'
                ]);
                exit();
            }
            
            $updateData['updated_at'] = new MongoDB\BSON\UTCDateTime();
            
            $result = $camerasCollection->updateOne(
                ['_id' => $cameraId],
                ['$set' => $updateData]
            );
            
            if ($result->getModifiedCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Camera updated successfully',
                    'modified_count' => $result->getModifiedCount()
                ]);
            } else {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'No changes made to camera'
                ]);
            }
            break;
            
        case 'DELETE':
            // Remove camera
            $cameraId = $_GET['id'] ?? null;
            if (!$cameraId) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Camera ID required for deletion'
                ]);
                exit();
            }
            
            // Check if camera exists
            $existingCamera = $camerasCollection->findOne(['_id' => $cameraId]);
            if (!$existingCamera) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Camera not found'
                ]);
                exit();
            }
            
            $result = $camerasCollection->deleteOne(['_id' => $cameraId]);
            
            if ($result->getDeletedCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Camera removed successfully',
                    'deleted_count' => $result->getDeletedCount()
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to remove camera'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed',
                'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE']
            ]);
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
