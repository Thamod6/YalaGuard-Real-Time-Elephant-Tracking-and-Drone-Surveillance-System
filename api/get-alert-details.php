<?php
/**
 * YalaGuard - Get Alert Details API
 * 
 * API endpoint to fetch detailed information about a specific alert
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Start session and check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

// Check if alert ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Alert ID is required'
    ]);
    exit();
}

$alert_id = $_GET['id'];

try {
    require_once '../config/database.php';
    $db = getDatabase();
    
    // Get alert collection
    $alertCollection = $db->selectCollection('alerts');
    
    // Convert string ID to MongoDB ObjectId if needed
    if (is_string($alert_id)) {
        try {
            $alert_id = new MongoDB\BSON\ObjectId($alert_id);
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Invalid alert ID format'
            ]);
            exit();
        }
    }
    
    // Find the alert by ID
    $alert = $alertCollection->findOne(['_id' => $alert_id]);
    
    if (!$alert) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Alert not found'
        ]);
        exit();
    }
    
    // Get elephant information if elephant_id exists
    if (isset($alert['elephant_id']) && !empty($alert['elephant_id'])) {
        try {
            $elephantCollection = $db->selectCollection('elephants');
            $elephant = $elephantCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($alert['elephant_id'])]);
            
            if ($elephant) {
                $alert['elephant_name'] = $elephant['name'] ?? $elephant['elephant_name'] ?? 'Unknown';
                $alert['elephant_species'] = $elephant['species'] ?? 'Unknown';
                $alert['elephant_age'] = $elephant['age'] ?? 'Unknown';
                $alert['elephant_gender'] = $elephant['gender'] ?? 'Unknown';
            }
        } catch (Exception $e) {
            // Log error but don't fail the request
            error_log('Error fetching elephant data: ' . $e->getMessage());
        }
    }
    
    // Get GPS collar information - check multiple possible field names
    $collarFound = false;
    $collarFields = ['collar_id', 'gps_collar_id', 'device_id', 'tracker_id', 'gps_device_id'];
    
    foreach ($collarFields as $field) {
        if (isset($alert[$field]) && !empty($alert[$field])) {
            try {
                $collarCollection = $db->selectCollection('gps_collars');
                
                // Try to find collar by different field names
                $collar = $collarCollection->findOne([
                    '$or' => [
                        ['collar_id' => $alert[$field]],
                        ['device_id' => $alert[$field]],
                        ['tracker_id' => $alert[$field]],
                        ['gps_device_id' => $alert[$field]]
                    ]
                ]);
                
                if ($collar) {
                    $alert['collar_id'] = $collar['collar_id'] ?? $collar['device_id'] ?? $collar['tracker_id'] ?? $alert[$field];
                    $alert['collar_name'] = $collar['collar_name'] ?? $collar['device_name'] ?? 'Unknown';
                    $alert['collar_model'] = $collar['model'] ?? 'Unknown';
                    $alert['collar_provider'] = $collar['provider'] ?? 'Unknown';
                    $alert['collar_status'] = $collar['status'] ?? 'Unknown';
                    $alert['collar_battery'] = $collar['battery_level'] ?? 'Unknown';
                    $collarFound = true;
                    break;
                }
            } catch (Exception $e) {
                error_log('Error fetching GPS collar data by ' . $field . ': ' . $e->getMessage());
            }
        }
    }
    
    // If no collar found by direct fields, try to get collar from elephant
    if (!$collarFound && isset($alert['elephant_id']) && !empty($alert['elephant_id'])) {
        try {
            $collarCollection = $db->selectCollection('gps_collars');
            $collar = $collarCollection->findOne(['elephant_id' => $alert['elephant_id']]);
            
            if ($collar) {
                $alert['collar_id'] = $collar['collar_id'] ?? $collar['device_id'] ?? $collar['tracker_id'] ?? 'Unknown';
                $alert['collar_name'] = $collar['collar_name'] ?? $collar['device_name'] ?? 'Unknown';
                $alert['collar_model'] = $collar['model'] ?? 'Unknown';
                $alert['collar_provider'] = $collar['provider'] ?? 'Unknown';
                $alert['collar_status'] = $collar['status'] ?? 'Unknown';
                $alert['collar_battery'] = $collar['battery_level'] ?? 'Unknown';
            }
        } catch (Exception $e) {
            error_log('Error fetching GPS collar data by elephant: ' . $e->getMessage());
        }
    }
    
    // Convert MongoDB ObjectId to string for JSON response
    $alert['_id'] = (string) $alert['_id'];
    
    // Convert MongoDB dates to ISO strings
    if (isset($alert['created_at']) && $alert['created_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $alert['created_at'] = $alert['created_at']->toDateTime()->format('c');
    }
    
    if (isset($alert['updated_at']) && $alert['updated_at'] instanceof MongoDB\BSON\UTCDateTime) {
        $alert['updated_at'] = $alert['updated_at']->toDateTime()->format('c');
    }
    
    // Add debug information to help troubleshoot
    $alert['debug_info'] = [
        'elephant_id_exists' => isset($alert['elephant_id']) && !empty($alert['elephant_id']),
        'collar_fields_checked' => $collarFields,
        'collar_found' => $collarFound,
        'elephant_data_found' => isset($alert['elephant_name']) && $alert['elephant_name'] !== 'Unknown',
        'collar_data_found' => isset($alert['collar_name']) && $alert['collar_name'] !== 'Unknown'
    ];
    
    // Return success response with enhanced alert data
    echo json_encode([
        'success' => true,
        'alert' => $alert
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
