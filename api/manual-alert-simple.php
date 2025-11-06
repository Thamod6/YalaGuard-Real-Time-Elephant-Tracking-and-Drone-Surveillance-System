<?php
/**
 * YalaGuard Manual Alert API - Simplified Version (No Email)
 * 
 * This API handles manual alert requests and saves them to database only
 */

// Start output buffering immediately to catch any unexpected output
ob_start();

// Suppress error display to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Include required files with proper path resolution
$configPath = __DIR__ . '/../config/database.php';

if (!file_exists($configPath)) {
    throw new Exception('Database config file not found: ' . $configPath);
}

require_once $configPath;

// Clear any output buffer content that might have been generated
ob_clean();

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Only POST requests are supported.'
    ]);
    exit();
}

try {
    // Get JSON input from request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    // Validate required fields
    $requiredFields = ['alert_type', 'authorities'];
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
    
    // Validate authorities array
    if (!is_array($input['authorities']) || empty($input['authorities'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'At least one authority must be selected'
        ]);
        exit();
    }
    
    // Get database connection
    try {
        $db = getDatabase();
    } catch (Exception $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
    
    // Get selected authorities
    try {
        $authoritiesCollection = $db->selectCollection('authorities');
        $authorities = [];
        
        foreach ($input['authorities'] as $authorityId) {
            try {
                $authority = $authoritiesCollection->findOne([
                    '_id' => new MongoDB\BSON\ObjectId($authorityId),
                    'active' => true
                ]);
                
                if ($authority) {
                    $authority['_id'] = (string)$authority['_id'];
                    $authorities[] = $authority;
                }
            } catch (Exception $e) {
                // Log the error but continue with other authorities
                error_log("Failed to fetch authority {$authorityId}: " . $e->getMessage());
            }
        }
        
        if (empty($authorities)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'No valid authorities found'
            ]);
            exit();
        }
    } catch (Exception $e) {
        throw new Exception('Failed to fetch authorities: ' . $e->getMessage());
    }
    
    // Prepare alert message
    $alertMessage = prepareAlertMessage($input);
    
    // Process location data - ensure coordinates are numbers
    $location = null;
    if (!empty($input['location']['latitude']) && !empty($input['location']['longitude'])) {
        $latitude = (float)$input['location']['latitude'];
        $longitude = (float)$input['location']['longitude'];
        
        // Validate coordinate ranges
        if ($latitude < -90 || $latitude > 90) {
            throw new Exception('Invalid latitude: must be between -90 and 90');
        }
        
        if ($longitude < -180 || $longitude > 180) {
            throw new Exception('Invalid longitude: must be between -180 and 180');
        }
        
        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
    }
    
    // Create alert record in database
    try {
        $alertsCollection = $db->selectCollection('alerts');
        $alertData = [
            'alert_type' => $input['alert_type'],
            'alert_level' => determineAlertLevel($input['alert_type']),
            'message' => $alertMessage,
            'elephant_id' => $input['elephant_id'] ?? null,
            'location' => $location,
            'authorities' => $input['authorities'],
            'sent_by' => $input['sent_by'] ?? null,
            'sent_by_name' => $input['sent_by_name'] ?? 'System',
            'status' => 'active',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'sent_to_authorities' => false
        ];
        
        $result = $alertsCollection->insertOne($alertData);
        
        if (!$result->getInsertedCount()) {
            throw new Exception('Failed to save alert to database');
        }
        
        $alertData['_id'] = (string)$result->getInsertedId();
    } catch (Exception $e) {
        throw new Exception('Database insert failed: ' . $e->getMessage());
    }
    
    // Return success response (no email sending for now)
    echo json_encode([
        'status' => 'success',
        'message' => 'Manual alert created successfully (database only)',
        'alert_id' => $alertData['_id'],
        'recipients_count' => count($authorities),
        'alert_data' => [
            'type' => $alertData['alert_type'],
            'level' => $alertData['alert_level'],
            'message' => $alertData['message']
        ],
        'note' => 'Alert saved to database. Email service not enabled in this version.'
    ]);
    
} catch (Exception $e) {
    // Clear any output buffer content
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}

// Clean output buffer and end
$output = ob_get_clean();
if (!empty($output) && !headers_sent()) {
    // If there was unexpected output, log it and return clean JSON
    error_log('Manual Alert API unexpected output: ' . $output);
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: Unexpected output detected'
    ]);
}

/**
 * Prepare alert message based on alert type and input data
 */
function prepareAlertMessage($input) {
    $message = '';
    
    switch ($input['alert_type']) {
        case 'emergency':
            $message = "🚨 EMERGENCY ALERT: Urgent situation requiring immediate attention.";
            break;
            
        case 'wildlife_conflict':
            $message = "🐘 WILDLIFE CONFLICT ALERT: Human-elephant conflict situation detected.";
            break;
            
        case 'poaching_alert':
            $message = "⚠️ POACHING ALERT: Potential poaching activity detected in the area.";
            break;
            
        case 'health_emergency':
            $message = "🏥 HEALTH EMERGENCY: Wildlife health issue requiring veterinary attention.";
            break;
            
        case 'weather_alert':
            $message = "🌦️ WEATHER ALERT: Severe weather conditions affecting wildlife safety.";
            break;
            
        case 'custom':
            $message = "📢 CUSTOM ALERT: " . ($input['custom_message'] ?? 'Custom alert message');
            break;
            
        default:
            $message = "📢 ALERT: " . ucfirst($input['alert_type']) . " situation detected.";
    }
    
    // Add elephant information if specified
    if (!empty($input['elephant_id'])) {
        $message .= " Elephant ID: " . $input['elephant_id'];
    }
    
    // Add location information if specified
    if (!empty($input['location']['latitude']) && !empty($input['location']['longitude'])) {
        $message .= " Location: " . $input['location']['latitude'] . ", " . $input['location']['longitude'];
    }
    
    $message .= " Time: " . date('Y-m-d H:i:s');
    
    return $message;
}

/**
 * Determine alert level based on alert type
 */
function determineAlertLevel($alertType) {
    switch ($alertType) {
        case 'emergency':
        case 'poaching_alert':
            return 'critical';
            
        case 'wildlife_conflict':
        case 'health_emergency':
            return 'high';
            
        case 'weather_alert':
            return 'medium';
            
        default:
            return 'medium';
    }
}
?>
