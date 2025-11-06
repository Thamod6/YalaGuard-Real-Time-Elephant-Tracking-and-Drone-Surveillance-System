<?php
/**
 * YalaGuard Get GPS Locations API
 * Retrieves real-time location data for display on maps
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use GET to retrieve location data.']);
    exit();
}

try {
    // Connect to database
    require_once '../config/database.php';
    $collection = getCollection('gps_locations');
    
    // Get query parameters
    $device_id = $_GET['device_id'] ?? null;
    $elephant_id = $_GET['elephant_id'] ?? null;
    $limit = intval($_GET['limit'] ?? 100); // Default to 100 locations
    $hours = intval($_GET['hours'] ?? 24); // Default to last 24 hours
    
    // Build query
    $query = [];
    
    if ($device_id) {
        $query['device_id'] = $device_id;
    }
    
    if ($elephant_id) {
        $query['elephant_id'] = $elephant_id;
    }
    
    // Filter by time (last X hours)
    $time_threshold = time() - ($hours * 3600);
    $query['unix_timestamp'] = ['$gte' => $time_threshold];
    
    // Get locations, sorted by timestamp (newest first)
    $cursor = $collection->find(
        $query,
        [
            'sort' => ['unix_timestamp' => -1],
            'limit' => $limit
        ]
    );
    
    $locations = [];
    foreach ($cursor as $location) {
        $locations[] = [
            'id' => (string)$location['_id'],
            'device_id' => $location['device_id'],
            'elephant_id' => $location['elephant_id'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'timestamp' => $location['timestamp'],
            'unix_timestamp' => $location['unix_timestamp'],
            'accuracy' => $location['accuracy'],
            'speed' => $location['speed'],
            'heading' => $location['heading'],
            'altitude' => $location['altitude'],
            'battery_level' => $location['battery_level'],
            'signal_strength' => $location['signal_strength'],
            'temperature' => $location['temperature']
        ];
    }
    
    // Get elephant information for the locations
    $elephants_collection = getCollection('elephants');
    $elephant_info = [];
    
    if (!empty($locations)) {
        $device_ids = array_unique(array_column($locations, 'device_id'));
        $elephant_cursor = $elephants_collection->find([
            'gps_tracking.device_id' => ['$in' => $device_ids]
        ]);
        
        foreach ($elephant_cursor as $elephant) {
            if (isset($elephant['gps_tracking']['device_id'])) {
                $elephant_info[$elephant['gps_tracking']['device_id']] = [
                    'id' => (string)$elephant['_id'],
                    'name' => $elephant['name'],
                    'type' => $elephant['type'],
                    'photo' => $elephant['photo'] ?? null
                ];
            }
        }
    }
    
    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'count' => count($locations),
        'locations' => $locations,
        'elephant_info' => $elephant_info,
        'query_params' => [
            'device_id' => $device_id,
            'elephant_id' => $elephant_id,
            'limit' => $limit,
            'hours' => $hours
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
