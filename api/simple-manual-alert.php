<?php
/**
 * Simple Manual Alert API - For testing and debugging
 */

// Enable error reporting temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
    $rawInput = file_get_contents('php://input');
    
    if (empty($rawInput)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'No input data received'
        ]);
        exit();
    }
    
    $input = json_decode($rawInput, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid JSON: ' . json_last_error_msg()
        ]);
        exit();
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
    
    // Process location data if provided
    $location = null;
    if (!empty($input['location']['latitude']) && !empty($input['location']['longitude'])) {
        $latitude = (float)$input['location']['latitude'];
        $longitude = (float)$input['location']['longitude'];
        
        // Validate coordinate ranges
        if ($latitude < -90 || $latitude > 90) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid latitude: must be between -90 and 90'
            ]);
            exit();
        }
        
        if ($longitude < -180 || $longitude > 180) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid longitude: must be between -180 and 180'
            ]);
            exit();
        }
        
        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
    }
    
    // Prepare alert message
    $alertMessage = prepareAlertMessage($input);
    
    // Create alert record (simplified - no database for now)
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
        'created_at' => date('Y-m-d H:i:s'),
        'sent_to_authorities' => false
    ];
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Manual alert created successfully (test mode)',
        'alert_id' => 'test_' . time(),
        'recipients_count' => count($input['authorities']),
        'alert_data' => [
            'type' => $alertData['alert_type'],
            'level' => $alertData['alert_level'],
            'message' => $alertData['message']
        ],
        'note' => 'This is a test response - no actual emails sent'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
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
