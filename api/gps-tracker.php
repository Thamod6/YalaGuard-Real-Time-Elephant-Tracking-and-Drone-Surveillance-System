<?php
/**
 * YalaGuard GPS Tracker API
 * 
 * This API endpoint receives GPS location data from tracking devices
 * attached to elephants and stores it in the database.
 * 
 * GPS devices send data via HTTP POST requests with location coordinates,
 * device ID, timestamp, and other tracking information.
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests for GPS data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Only POST requests are accepted.',
        'code' => 405
    ]);
    exit();
}

// Include database connection
require_once '../config/database.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        throw new Exception('Invalid JSON input');
    }
    
    // Validate required fields
    $required_fields = ['device_id', 'latitude', 'longitude', 'timestamp'];
    foreach ($required_fields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Validate coordinates
    $lat = (float)$input['latitude'];
    $lng = (float)$input['longitude'];
    
    if ($lat < -90 || $lat > 90) {
        throw new Exception('Invalid latitude value. Must be between -90 and 90.');
    }
    
    if ($lng < -180 || $lng > 180) {
        throw new Exception('Invalid longitude value. Must be between -180 and 180.');
    }
    
    // Validate timestamp
    $timestamp = $input['timestamp'];
    if (!is_numeric($timestamp)) {
        // Try to parse as ISO string
        $timestamp = strtotime($timestamp);
        if ($timestamp === false) {
            throw new Exception('Invalid timestamp format');
        }
    }
    
    // Convert timestamp to datetime
    $datetime = date('Y-m-d H:i:s', $timestamp);
    
    // Get additional optional fields
    $altitude = isset($input['altitude']) ? (float)$input['altitude'] : null;
    $speed = isset($input['speed']) ? (float)$input['speed'] : null;
    $heading = isset($input['heading']) ? (float)$input['heading'] : null;
    $accuracy = isset($input['accuracy']) ? (float)$input['accuracy'] : null;
    $battery_level = isset($input['battery_level']) ? (int)$input['battery_level'] : null;
    $signal_strength = isset($input['signal_strength']) ? (int)$input['signal_strength'] : null;
    $satellites = isset($input['satellites']) ? (int)$input['satellites'] : null;
    $temperature = isset($input['temperature']) ? (float)$input['temperature'] : null;
    $humidity = isset($input['humidity']) ? (float)$input['humidity'] : null;
    
    // Find the elephant associated with this device
    $elephants_collection = getCollection('elephants');
    $elephant = $elephants_collection->findOne([
        'gps_tracking.device_id' => $input['device_id'],
        'gps_tracking.status' => 'active'
    ]);
    
    if (!$elephant) {
        throw new Exception('GPS device not found or not active');
    }
    
    $elephant_id = (string)$elephant['_id'];
    
    // Calculate distance from last location if available
    $distance = 0;
    $speed_calculated = null;
    
    if (isset($elephant['gps_tracking']['last_location'])) {
        $last_lat = $elephant['gps_tracking']['last_location']['latitude'];
        $last_lng = $elephant['gps_tracking']['last_location']['longitude'];
        $last_timestamp = strtotime($elephant['gps_tracking']['last_location']['timestamp']);
        
        // Calculate distance using Haversine formula
        $distance = calculateDistance($last_lat, $last_lng, $lat, $lng);
        
        // Calculate speed if time difference is available
        $time_diff = $timestamp - $last_timestamp;
        if ($time_diff > 0) {
            $speed_calculated = ($distance / 1000) / ($time_diff / 3600); // km/h
        }
    }
    
    // Prepare location data
    $location_data = [
        'elephant_id' => $elephant_id,
        'device_id' => $input['device_id'],
        'latitude' => $lat,
        'longitude' => $lng,
        'altitude' => $altitude,
        'speed' => $speed ?? $speed_calculated,
        'heading' => $heading,
        'accuracy' => $accuracy,
        'timestamp' => $datetime,
        'unix_timestamp' => $timestamp,
        'battery_level' => $battery_level,
        'signal_strength' => $signal_strength,
        'satellites' => $satellites,
        'temperature' => $temperature,
        'humidity' => $humidity,
        'distance_from_last' => $distance,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    // Store location data in GPS locations collection
    $gps_locations_collection = getCollection('gps_locations');
    $result = $gps_locations_collection->insertOne($location_data);
    
    if (!$result->getInsertedCount()) {
        throw new Exception('Failed to store GPS location data');
    }
    
    // Update elephant's GPS tracking information
    $update_data = [
        'gps_tracking.last_location' => [
            'latitude' => $lat,
            'longitude' => $lng,
            'timestamp' => $datetime,
            'unix_timestamp' => $timestamp
        ],
        'gps_tracking.last_update' => $datetime,
        'gps_tracking.current_speed' => $speed ?? $speed_calculated,
        'gps_tracking.battery_level' => $battery_level,
        'gps_tracking.signal_strength' => $signal_strength
    ];
    
    // Update total distance
    if (isset($elephant['gps_tracking']['total_distance'])) {
        $update_data['gps_tracking.total_distance'] = $elephant['gps_tracking']['total_distance'] + $distance;
    } else {
        $update_data['gps_tracking.total_distance'] = $distance;
    }
    
    $elephants_collection->updateOne(
        ['_id' => $elephant['_id']],
        ['$set' => $update_data]
    );
    
    // Check if elephant is near any geofences
    $geofence_alerts = checkGeofenceViolations($elephant_id, $lat, $lng);
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'GPS location data received and stored successfully',
        'data' => [
            'elephant_id' => $elephant_id,
            'elephant_name' => $elephant['name'],
            'location_id' => (string)$result->getInsertedId(),
            'coordinates' => [
                'latitude' => $lat,
                'longitude' => $lng
            ],
            'timestamp' => $datetime,
            'distance_from_last' => round($distance, 2),
            'speed' => $speed ?? $speed_calculated,
            'geofence_alerts' => $geofence_alerts
        ],
        'code' => 200
    ]);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => 400
    ]);
}

/**
 * Calculate distance between two coordinates using Haversine formula
 */
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earth_radius = 6371000; // Earth's radius in meters
    
    $lat1_rad = deg2rad($lat1);
    $lon1_rad = deg2rad($lon1);
    $lat2_rad = deg2rad($lat2);
    $lon2_rad = deg2rad($lon2);
    
    $delta_lat = $lat2_rad - $lat1_rad;
    $delta_lon = $lon2_rad - $lon1_rad;
    
    $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
         cos($lat1_rad) * cos($lat2_rad) *
         sin($delta_lon / 2) * sin($delta_lon / 2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    
    return $earth_radius * $c;
}

/**
 * Check if elephant location violates any geofences
 */
function checkGeofenceViolations($elephant_id, $lat, $lng) {
    try {
        $geofences_collection = getCollection('geofences');
        $geofences = $geofences_collection->find([
            'active' => true,
            'elephant_id' => $elephant_id
        ]);
        
        $alerts = [];
        
        foreach ($geofences as $geofence) {
            $distance = calculateDistance($lat, $lng, $geofence['lat'], $geofence['lng']);
            
            if ($distance <= $geofence['radius']) {
                $alerts[] = [
                    'geofence_id' => $geofence['geofence_id'],
                    'geofence_name' => $geofence['name'],
                    'geofence_type' => $geofence['type'],
                    'status' => 'inside',
                    'distance_to_center' => round($distance, 2),
                    'alert_level' => $geofence['type'] === 'restricted' ? 'high' : 'medium'
                ];
            }
        }
        
        return $alerts;
        
    } catch (Exception $e) {
        // Log error but don't fail the GPS update
        error_log("Error checking geofences: " . $e->getMessage());
        return [];
    }
}
?>
