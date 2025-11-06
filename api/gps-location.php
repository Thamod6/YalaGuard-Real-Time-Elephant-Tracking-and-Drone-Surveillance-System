<?php
/**
 * YalaGuard GPS Location API
 * Receives real-time location data from GPS tracking devices
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests for GPS data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed. Use POST to send GPS data.']);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Validate required fields
$required_fields = ['device_id', 'latitude', 'longitude', 'timestamp'];
$missing_fields = [];

foreach ($required_fields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        $missing_fields[] = $field;
    }
}

if (!empty($missing_fields)) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required fields: ' . implode(', ', $missing_fields),
        'required_fields' => $required_fields,
        'received_data' => $data
    ]);
    exit();
}

// Validate coordinates
$lat = floatval($data['latitude']);
$lng = floatval($data['longitude']);

if ($lat < -90 || $lat > 90 || $lng < -180 || $lng > 180) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Invalid coordinates. Latitude must be between -90 and 90, Longitude between -180 and 180.',
        'received_lat' => $lat,
        'received_lng' => $lng
    ]);
    exit();
}

// Validate timestamp
$timestamp = $data['timestamp'];
if (!is_numeric($timestamp)) {
    $timestamp = strtotime($timestamp);
    if ($timestamp === false) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid timestamp format. Use Unix timestamp or ISO 8601 format.']);
        exit();
    }
}

// Prepare location data
$location_data = [
    'device_id' => trim($data['device_id']),
    'elephant_id' => $data['elephant_id'] ?? null, // Optional: link to specific elephant
    'latitude' => $lat,
    'longitude' => $lng,
    'timestamp' => date('Y-m-d H:i:s', $timestamp),
    'unix_timestamp' => $timestamp,
    'accuracy' => $data['accuracy'] ?? null, // GPS accuracy in meters
    'speed' => $data['speed'] ?? null, // Speed in km/h
    'heading' => $data['heading'] ?? null, // Direction in degrees
    'altitude' => $data['altitude'] ?? null, // Altitude in meters
    'battery_level' => $data['battery_level'] ?? null, // Battery percentage
    'signal_strength' => $data['signal_strength'] ?? null, // Signal strength
    'temperature' => $data['temperature'] ?? null, // Device temperature
    'raw_data' => $data, // Store complete raw data
    'created_at' => date('Y-m-d H:i:s')
];

try {
    // Connect to database
    require_once '../config/database.php';
    $collection = getCollection('gps_locations');
    
    // Insert location data
    $result = $collection->insertOne($location_data);
    
    if ($result->getInsertedCount() > 0) {
        // Update elephant's last known location if elephant_id is provided
        if (!empty($data['elephant_id'])) {
            $elephants_collection = getCollection('elephants');
            $elephants_collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($data['elephant_id'])],
                [
                    '$set' => [
                        'last_location' => [
                            'latitude' => $lat,
                            'longitude' => $lng,
                            'timestamp' => date('Y-m-d H:i:s', $timestamp)
                        ],
                        'last_update' => date('Y-m-d H:i:s', $timestamp)
                    ]
                ]
            );
        }
        
        // Success response
        http_response_code(201);
        echo json_encode([
            'success' => true,
            'message' => 'GPS location data received and stored successfully',
            'location_id' => (string)$result->getInsertedId(),
            'device_id' => $location_data['device_id'],
            'coordinates' => [
                'latitude' => $lat,
                'longitude' => $lng
            ],
            'timestamp' => $location_data['timestamp']
        ]);
        
    } else {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to store GPS location data']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'received_data' => $data
    ]);
}
?>
