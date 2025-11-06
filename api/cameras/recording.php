<?php
/**
 * YalaGuard Camera Recording API
 * 
 * This endpoint handles camera recording functionality including starting,
 * stopping, and managing recorded video files.
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
    $recordingsCollection = $db->selectCollection('recordings');
    
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            // Get recordings list or specific recording
            $recordingId = $_GET['id'] ?? null;
            $cameraId = $_GET['camera_id'] ?? null;
            
            if ($recordingId) {
                // Get specific recording
                $recording = $recordingsCollection->findOne(['_id' => $recordingId]);
                if (!$recording) {
                    http_response_code(404);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Recording not found'
                    ]);
                    exit();
                }
                
                echo json_encode([
                    'status' => 'success',
                    'recording' => $recording
                ]);
            } else {
                // Get recordings list with optional camera filter
                $filter = [];
                if ($cameraId) {
                    $filter['camera_id'] = $cameraId;
                }
                
                $recordings = $recordingsCollection->find($filter, [
                    'sort' => ['created_at' => -1],
                    'limit' => 100
                ])->toArray();
                
                echo json_encode([
                    'status' => 'success',
                    'recordings' => $recordings,
                    'count' => count($recordings)
                ]);
            }
            break;
            
        case 'POST':
            // Start recording
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($input['action'])) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Action required (start/stop)'
                ]);
                exit();
            }
            
            switch ($input['action']) {
                case 'start':
                    // Start recording
                    if (!isset($input['camera_id'])) {
                        http_response_code(400);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Camera ID required to start recording'
                        ]);
                        exit();
                    }
                    
                    // Check if camera exists
                    $camerasCollection = $db->selectCollection('cameras');
                    $camera = $camerasCollection->findOne(['_id' => $input['camera_id']]);
                    if (!$camera) {
                        http_response_code(404);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Camera not found'
                        ]);
                        exit();
                    }
                    
                    // Check if already recording
                    $activeRecording = $recordingsCollection->findOne([
                        'camera_id' => $input['camera_id'],
                        'status' => 'recording'
                    ]);
                    
                    if ($activeRecording) {
                        http_response_code(409);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Camera is already recording'
                        ]);
                        exit();
                    }
                    
                    // Create recording document
                    $recordingDocument = [
                        'camera_id' => $input['camera_id'],
                        'camera_name' => $camera['name'],
                        'camera_location' => $camera['location'],
                        'status' => 'recording',
                        'started_at' => new MongoDB\BSON\UTCDateTime(),
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'created_by' => $_SESSION['user_id'],
                        'file_path' => null,
                        'file_size' => null,
                        'duration' => null
                    ];
                    
                    $result = $recordingsCollection->insertOne($recordingDocument);
                    
                    if ($result->getInsertedCount() > 0) {
                        $recordingDocument['_id'] = $result->getInsertedId();
                        
                        // In real implementation, you would start the actual recording here
                        // This could involve:
                        // 1. Starting MediaRecorder API on frontend
                        // 2. Starting server-side recording process
                        // 3. Connecting to camera stream and recording
                        
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Recording started successfully',
                            'recording_id' => (string) $result->getInsertedId(),
                            'recording' => $recordingDocument
                        ]);
                    } else {
                        http_response_code(500);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Failed to start recording'
                        ]);
                    }
                    break;
                    
                case 'stop':
                    // Stop recording
                    if (!isset($input['recording_id'])) {
                        http_response_code(400);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Recording ID required to stop recording'
                        ]);
                        exit();
                    }
                    
                    // Find the recording
                    $recording = $recordingsCollection->findOne(['_id' => $input['recording_id']]);
                    if (!$recording) {
                        http_response_code(404);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Recording not found'
                        ]);
                        exit();
                    }
                    
                    if ($recording->status !== 'recording') {
                        http_response_code(400);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Recording is not active'
                        ]);
                        exit();
                    }
                    
                    // Calculate duration
                    $startedAt = $recording->started_at->toDateTime();
                    $now = new DateTime();
                    $duration = $now->getTimestamp() - $startedAt->getTimestamp();
                    
                    // Update recording status
                    $updateData = [
                        'status' => 'completed',
                        'stopped_at' => new MongoDB\BSON\UTCDateTime(),
                        'duration' => $duration,
                        'file_path' => $input['file_path'] ?? null,
                        'file_size' => $input['file_size'] ?? null,
                        'updated_at' => new MongoDB\BSON\UTCDateTime()
                    ];
                    
                    $result = $recordingsCollection->updateOne(
                        ['_id' => $input['recording_id']],
                        ['$set' => $updateData]
                    );
                    
                    if ($result->getModifiedCount() > 0) {
                        echo json_encode([
                            'status' => 'success',
                            'message' => 'Recording stopped successfully',
                            'duration' => $duration,
                            'recording_id' => $input['recording_id']
                        ]);
                    } else {
                        http_response_code(500);
                        echo json_encode([
                            'status' => 'error',
                            'message' => 'Failed to stop recording'
                        ]);
                    }
                    break;
                    
                default:
                    http_response_code(400);
                    echo json_encode([
                        'status' => 'error',
                        'message' => 'Invalid action. Use "start" or "stop"'
                    ]);
                    break;
            }
            break;
            
        case 'DELETE':
            // Delete recording
            $recordingId = $_GET['id'] ?? null;
            if (!$recordingId) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Recording ID required for deletion'
                ]);
                exit();
            }
            
            // Check if recording exists
            $recording = $recordingsCollection->findOne(['_id' => $recordingId]);
            if (!$recording) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Recording not found'
                ]);
                exit();
            }
            
            // In real implementation, you would also delete the actual video file here
            
            $result = $recordingsCollection->deleteOne(['_id' => $recordingId]);
            
            if ($result->getDeletedCount() > 0) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Recording deleted successfully',
                    'deleted_count' => $result->getDeletedCount()
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Failed to delete recording'
                ]);
            }
            break;
            
        default:
            http_response_code(405);
            echo json_encode([
                'status' => 'error',
                'message' => 'Method not allowed',
                'allowed_methods' => ['GET', 'POST', 'DELETE']
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
