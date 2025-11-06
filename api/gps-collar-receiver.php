<?php
/**
 * Enhanced GPS Collar Receiver API
 * 
 * Receives real-time location data from professional GPS collars
 * Supports multiple GPS providers (Vectronic, Lotek, Telonics, Followit)
 * Handles real GPS collar protocols and data formats
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Device-ID, X-Provider');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';

// GPS Provider specific data parsers
class GPSDataParser {
    
    // Vectronic Aerospace format
    public static function parseVectronic($data) {
        return [
            'collar_id' => $data['device_id'] ?? $data['collar_id'],
            'latitude' => (float)($data['lat'] ?? $data['latitude']),
            'longitude' => (float)($data['lon'] ?? $data['longitude']),
            'timestamp' => isset($data['timestamp']) ? strtotime($data['timestamp']) : time(),
            'battery_level' => (int)($data['battery'] ?? $data['battery_level'] ?? 100),
            'signal_strength' => (int)($data['signal'] ?? $data['signal_strength'] ?? -50),
            'accuracy' => (float)($data['accuracy'] ?? 5.0),
            'speed' => (float)($data['speed'] ?? 0.0),
            'heading' => (float)($data['heading'] ?? 0.0),
            'altitude' => (float)($data['altitude'] ?? 0.0),
            'temperature' => isset($data['temp']) ? (float)$data['temp'] : null,
            'activity' => $data['activity'] ?? 'unknown',
            'provider' => 'vectronic'
        ];
    }
    
    // Lotek format
    public static function parseLotek($data) {
        return [
            'collar_id' => $data['device_id'] ?? $data['collar_id'],
            'latitude' => (float)($data['lat'] ?? $data['latitude']),
            'longitude' => (float)($data['lon'] ?? $data['longitude']),
            'timestamp' => isset($data['timestamp']) ? strtotime($data['timestamp']) : time(),
            'battery_level' => (int)($data['battery'] ?? $data['battery_level'] ?? 100),
            'signal_strength' => (int)($data['signal'] ?? $data['signal_strength'] ?? -50),
            'accuracy' => (float)($data['accuracy'] ?? 5.0),
            'speed' => (float)($data['speed'] ?? 0.0),
            'heading' => (float)($data['heading'] ?? 0.0),
            'altitude' => (float)($data['altitude'] ?? 0.0),
            'temperature' => isset($data['temp']) ? (float)$data['temp'] : null,
            'activity' => $data['activity'] ?? 'unknown',
            'provider' => 'lotek'
        ];
    }
    
    // Telonics format
    public static function parseTelonics($data) {
        return [
            'collar_id' => $data['device_id'] ?? $data['collar_id'],
            'latitude' => (float)($data['lat'] ?? $data['latitude']),
            'longitude' => (float)($data['lon'] ?? $data['longitude']),
            'timestamp' => isset($data['timestamp']) ? strtotime($data['timestamp']) : time(),
            'battery_level' => (int)($data['battery'] ?? $data['battery_level'] ?? 100),
            'signal_strength' => (int)($data['signal'] ?? $data['signal_strength'] ?? -50),
            'accuracy' => (float)($data['accuracy'] ?? 5.0),
            'speed' => (float)($data['speed'] ?? 0.0),
            'heading' => (float)($data['heading'] ?? 0.0),
            'altitude' => (float)($data['altitude'] ?? 0.0),
            'temperature' => isset($data['temp']) ? (float)$data['temp'] : null,
            'activity' => $data['activity'] ?? 'unknown',
            'provider' => 'telonics'
        ];
    }
    
    // Followit format
    public static function parseFollowit($data) {
        return [
            'collar_id' => $data['device_id'] ?? $data['collar_id'],
            'latitude' => (float)($data['lat'] ?? $data['latitude']),
            'longitude' => (float)($data['lon'] ?? $data['longitude']),
            'timestamp' => isset($data['timestamp']) ? strtotime($data['timestamp']) : time(),
            'battery_level' => (int)($data['battery'] ?? $data['battery_level'] ?? 100),
            'signal_strength' => (int)($data['signal'] ?? $data['signal_strength'] ?? -50),
            'accuracy' => (float)($data['accuracy'] ?? 5.0),
            'speed' => (float)($data['speed'] ?? 0.0),
            'heading' => (float)($data['heading'] ?? 0.0),
            'altitude' => (float)($data['altitude'] ?? 0.0),
            'temperature' => isset($data['temp']) ? (float)$data['temp'] : null,
            'activity' => $data['activity'] ?? 'unknown',
            'provider' => 'followit'
        ];
    }
    
    // Generic format for unknown providers
    public static function parseGeneric($data) {
        return [
            'collar_id' => $data['device_id'] ?? $data['collar_id'] ?? $data['id'],
            'latitude' => (float)($data['lat'] ?? $data['latitude'] ?? $data['y'] ?? 0),
            'longitude' => (float)($data['lon'] ?? $data['longitude'] ?? $data['x'] ?? 0),
            'timestamp' => isset($data['timestamp']) ? strtotime($data['timestamp']) : time(),
            'battery_level' => (int)($data['battery'] ?? $data['battery_level'] ?? $data['batt'] ?? 100),
            'signal_strength' => (int)($data['signal'] ?? $data['signal_strength'] ?? $data['rssi'] ?? -50),
            'accuracy' => (float)($data['accuracy'] ?? $data['acc'] ?? 5.0),
            'speed' => (float)($data['speed'] ?? $data['spd'] ?? 0.0),
            'heading' => (float)($data['heading'] ?? $data['hdg'] ?? 0.0),
            'altitude' => (float)($data['altitude'] ?? $data['alt'] ?? 0.0),
            'temperature' => isset($data['temp']) ? (float)$data['temp'] : null,
            'activity' => $data['activity'] ?? $data['act'] ?? 'unknown',
            'provider' => 'generic'
        ];
    }
}

// Function to detect GPS provider from data or headers
function detectGPSProvider($data, $headers) {
    // Check headers first
    if (isset($headers['X-Provider'])) {
        return strtolower($headers['X-Provider']);
    }
    
    // Check data for provider indicators
    if (isset($data['provider'])) {
        return strtolower($data['provider']);
    }
    
    // Check for provider-specific data patterns
    if (isset($data['vectronic_id']) || isset($data['vec_id'])) {
        return 'vectronic';
    }
    
    if (isset($data['lotek_id']) || isset($data['lot_id'])) {
        return 'lotek';
    }
    
    if (isset($data['telonics_id']) || isset($data['tel_id'])) {
        return 'telonics';
    }
    
    if (isset($data['followit_id']) || isset($data['fol_id'])) {
        return 'followit';
    }
    
    // Default to generic
    return 'generic';
}

// Function to save GPS location data with enhanced validation
function saveGPSLocation($data, $provider = 'generic') {
    try {
        $db = getDatabase();
        $locationCollection = $db->selectCollection('gps_locations');
        $collarCollection = $db->selectCollection('gps_collars');
        $alertCollection = $db->selectCollection('alerts');
        
        // Validate collar exists
        $collar = $collarCollection->findOne(['collar_id' => $data['collar_id']]);
        if (!$collar) {
            // Log unknown collar for security monitoring
            error_log('Unknown GPS collar attempted to send data: ' . $data['collar_id']);
            return ['success' => false, 'error' => 'GPS collar not found: ' . $data['collar_id']];
        }
        
        // Validate coordinates
        if ($data['latitude'] < -90 || $data['latitude'] > 90 || 
            $data['longitude'] < -180 || $data['longitude'] > 180) {
            return ['success' => false, 'error' => 'Invalid coordinates'];
        }
        
        // Create location document
        $locationDoc = [
            'collar_id' => $data['collar_id'],
            'elephant_id' => $collar['elephant_id'] ?? null,
            'elephant_name' => $collar['elephant_name'] ?? null,
            'latitude' => (float)$data['latitude'],
            'longitude' => (float)$data['longitude'],
            'timestamp' => new MongoDB\BSON\UTCDateTime($data['timestamp'] * 1000),
            'battery_level' => (int)($data['battery_level'] ?? 100),
            'signal_strength' => (int)($data['signal_strength'] ?? -50),
            'accuracy' => (float)($data['accuracy'] ?? 5.0),
            'speed' => (float)($data['speed'] ?? 0.0),
            'heading' => (float)($data['heading'] ?? 0.0),
            'altitude' => (float)($data['altitude'] ?? 0.0),
            'temperature' => $data['temperature'],
            'activity' => $data['activity'],
            'provider' => $provider,
            'raw_data' => $data, // Store original data for debugging
            'received_at' => new MongoDB\BSON\UTCDateTime()
        ];
        
        // Insert location
        $result = $locationCollection->insertOne($locationDoc);
        
        if ($result->getInsertedCount() > 0) {
            // Update collar's last known location and health
            $updateData = [
                'last_location_update' => new MongoDB\BSON\UTCDateTime(),
                'current_latitude' => (float)$data['latitude'],
                'current_longitude' => (float)$data['longitude'],
                'battery_level' => (int)($data['battery_level'] ?? 100),
                'signal_strength' => (int)($data['signal_strength'] ?? -50),
                'is_online' => true,
                'last_activity' => $data['activity'] ?? 'unknown',
                'last_temperature' => $data['temperature'],
                'health_status' => 'healthy'
            ];
            
            // Check for health issues
            if (($data['battery_level'] ?? 100) < 20) {
                $updateData['health_status'] = 'low_battery';
                createHealthAlert($collar, 'low_battery', $data);
            }
            
            if (($data['signal_strength'] ?? -50) < -80) {
                $updateData['health_status'] = 'weak_signal';
                createHealthAlert($collar, 'weak_signal', $data);
            }
            
            $collarCollection->updateOne(
                ['collar_id' => $data['collar_id']],
                ['$set' => $updateData]
            );
            
            // Check geofence violations
            checkGeofenceViolations($locationDoc);
            
            return ['success' => true, 'message' => 'Location saved successfully'];
        } else {
            return ['success' => false, 'error' => 'Failed to save location'];
        }
        
    } catch (Exception $e) {
        error_log('GPS Location Save Error: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Database error: ' . $e->getMessage()];
    }
}

// Function to create health alerts
function createHealthAlert($collar, $alertType, $data) {
    try {
        $db = getDatabase();
        $alertCollection = $db->selectCollection('alerts');
        
        $alertMessages = [
            'low_battery' => 'GPS collar battery level is low',
            'weak_signal' => 'GPS collar signal strength is weak',
            'offline' => 'GPS collar has gone offline',
            'error' => 'GPS collar has reported an error'
        ];
        
        $alert = [
            'type' => 'collar_health',
            'level' => $alertType === 'low_battery' ? 'warning' : 'info',
            'collar_id' => $collar['collar_id'],
            'collar_name' => $collar['collar_name'],
            'elephant_id' => $collar['elephant_id'] ?? null,
            'elephant_name' => $collar['elephant_name'] ?? null,
            'alert_type' => $alertType,
            'message' => $alertMessages[$alertType] ?? 'GPS collar health issue detected',
            'data' => $data,
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'status' => 'active',
            'acknowledged' => false
        ];
        
        $alertCollection->insertOne($alert);
        
    } catch (Exception $e) {
        error_log('Health Alert Creation Error: ' . $e->getMessage());
    }
}

// Function to check geofence violations
function checkGeofenceViolations($location) {
    try {
        $db = getDatabase();
        $geofenceCollection = $db->selectCollection('geofences');
        $alertCollection = $db->selectCollection('alerts');
        
        // Get all active geofences
        $geofences = $geofenceCollection->find(['status' => 'active'])->toArray();
        
        foreach ($geofences as $geofence) {
            // Calculate distance from geofence center
            $distance = calculateDistance(
                $location['latitude'], 
                $location['longitude'],
                $geofence['lat'], 
                $geofence['lng']
            );
            
            // Check if elephant is inside geofence
            if ($distance <= $geofence['radius']) {
                // Elephant is inside geofence
                $alertLevel = 'warning';
                if ($geofence['type'] === 'restricted') {
                    $alertLevel = 'critical';
                }
                
                // Create alert
                $alert = [
                    'type' => 'geofence_violation',
                    'level' => $alertLevel,
                    'elephant_id' => $location['elephant_id'],
                    'elephant_name' => $location['elephant_name'],
                    'collar_id' => $location['collar_id'],
                    'geofence_id' => $geofence['geofence_id'],
                    'geofence_name' => $geofence['name'],
                    'geofence_type' => $geofence['type'],
                    'location' => [
                        'latitude' => $location['latitude'],
                        'longitude' => $location['longitude']
                    ],
                    'distance_to_center' => $distance,
                    'timestamp' => new MongoDB\BSON\UTCDateTime(),
                    'status' => 'active'
                ];
                
                $alertCollection->insertOne($alert);
            }
        }
        
    } catch (Exception $e) {
        error_log('Geofence Check Error: ' . $e->getMessage());
    }
}

// Function to calculate distance between two points
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $theta = $lon1 - $lon2;
    $dist = sin(deg2rad($lat1)) * sin(deg2rad($lat2)) + 
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));
    $dist = acos($dist);
    $dist = rad2deg($dist);
    $miles = $dist * 60 * 1.1515;
    return $miles * 1609.344; // Convert to meters
}

// Handle incoming GPS data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // If JSON parsing failed, try POST data
    if (!$data) {
        $data = $_POST;
    }
    
    // Get headers for provider detection
    $headers = getallheaders();
    
    // Detect GPS provider
    $provider = detectGPSProvider($data, $headers);
    
    // Parse data based on provider
    switch ($provider) {
        case 'vectronic':
            $parsedData = GPSDataParser::parseVectronic($data);
            break;
        case 'lotek':
            $parsedData = GPSDataParser::parseLotek($data);
            break;
        case 'telonics':
            $parsedData = GPSDataParser::parseTelonics($data);
            break;
        case 'followit':
            $parsedData = GPSDataParser::parseFollowit($data);
            break;
        default:
            $parsedData = GPSDataParser::parseGeneric($data);
    }
    
    // Validate required fields
    if (empty($parsedData['collar_id']) || !isset($parsedData['latitude']) || !isset($parsedData['longitude'])) {
        echo json_encode([
            'success' => false, 
            'error' => 'Missing required fields: collar_id, latitude, longitude',
            'received_data' => $data,
            'parsed_data' => $parsedData
        ]);
        exit;
    }
    
    // Save location data
    $result = saveGPSLocation($parsedData, $provider);
    $result['provider_detected'] = $provider;
    echo json_encode($result);
    
} else {
    // GET request - return last known locations
    try {
        $db = getDatabase();
        $locationCollection = $db->selectCollection('gps_locations');
        
        // Get latest location for each collar
        $pipeline = [
            [
                '$sort' => ['timestamp' => -1]
            ],
            [
                '$group' => [
                    '_id' => '$collar_id',
                    'latest_location' => ['$first' => '$$ROOT']
                ]
            ]
        ];
        
        $locations = $locationCollection->aggregate($pipeline)->toArray();
        
        $result = [];
        foreach ($locations as $loc) {
            $result[] = $loc['latest_location'];
        }
        
        echo json_encode([
            'success' => true,
            'locations' => $result,
            'count' => count($result),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'Database error: ' . $e->getMessage()
        ]);
    }
}
?>
