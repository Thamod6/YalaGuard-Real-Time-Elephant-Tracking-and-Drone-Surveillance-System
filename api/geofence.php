<?php
/**
 * YalaGuard Geofencing API
 * 
 * This API provides geofencing functionality for elephant tracking and wildlife management.
 * Uses MongoDB Atlas for data storage and Haversine formula for distance calculations.
 * 
 * Endpoints:
 * - POST /api/geofence/add → Add a new geofence
 * - GET /api/geofence/list → List all geofences
 * - POST /api/geofence/check → Check if elephant is inside/outside geofence
 */

// Set headers for JSON API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Include database configuration
require_once '../config/database.php';

/**
 * Calculate distance between two coordinates using Haversine formula
 * 
 * @param float $lat1 Latitude of first point
 * @param float $lng1 Longitude of first point
 * @param float $lat2 Latitude of second point
 * @param float $lng2 Longitude of second point
 * @return float Distance in meters
 */
function calculateDistance($lat1, $lng1, $lat2, $lng2) {
    // Convert degrees to radians
    $lat1 = deg2rad($lat1);
    $lng1 = deg2rad($lng1);
    $lat2 = deg2rad($lat2);
    $lng2 = deg2rad($lng2);
    
    // Haversine formula
    $dlat = $lat2 - $lat1;
    $dlng = $lng2 - $lng1;
    
    $a = sin($dlat/2) * sin($dlat/2) + cos($lat1) * cos($lat2) * sin($dlng/2) * sin($dlng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    // Earth's radius in meters
    $earthRadius = 6371000;
    
    return $earthRadius * $c;
}

/**
 * Add a new geofence to the database
 * 
 * @param array $data Geofence data
 * @return array Response with status and message
 */
function addGeofence($data) {
    try {
        // Validate required fields
        $required_fields = ['geofence_id', 'lat', 'lng', 'radius'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return [
                    'status' => 'error',
                    'message' => "Missing required field: $field",
                    'code' => 400
                ];
            }
        }
        
        // Validate coordinate ranges
        if ($data['lat'] < -90 || $data['lat'] > 90) {
            return [
                'status' => 'error',
                'message' => 'Latitude must be between -90 and 90 degrees',
                'code' => 400
            ];
        }
        
        if ($data['lng'] < -180 || $data['lng'] > 180) {
            return [
                'status' => 'error',
                'message' => 'Longitude must be between -180 and 180 degrees',
                'code' => 400
            ];
        }
        
        if ($data['radius'] <= 0) {
            return [
                'status' => 'error',
                'message' => 'Radius must be greater than 0',
                'code' => 400
            ];
        }
        
        // Get collection
        $collection = getCollection('geofences');
        
        // Check if geofence_id already exists
        $existing = $collection->findOne(['geofence_id' => $data['geofence_id']]);
        if ($existing) {
            return [
                'status' => 'error',
                'message' => 'Geofence ID already exists',
                'code' => 409
            ];
        }
        
        // Prepare geofence document
        $geofence = [
            'geofence_id' => $data['geofence_id'],
            'elephant_id' => $data['elephant_id'] ?? null, // Optional field
            'lat' => (float)$data['lat'],
            'lng' => (float)$data['lng'],
            'radius' => (float)$data['radius'],
            'name' => $data['name'] ?? 'Unnamed Geofence',
            'type' => $data['type'] ?? 'restricted', // restricted, safe, monitoring
            'description' => $data['description'] ?? '',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'active' => true
        ];
        
        // Insert into database
        $result = $collection->insertOne($geofence);
        
        if ($result->getInsertedCount() > 0) {
            return [
                'status' => 'success',
                'message' => 'Geofence added successfully',
                'data' => [
                    'geofence_id' => $geofence['geofence_id'],
                    'inserted_id' => (string)$result->getInsertedId()
                ],
                'code' => 201
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to add geofence',
                'code' => 500
            ];
        }
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'code' => 500
        ];
    }
}

/**
 * List all geofences from the database
 * 
 * @return array Response with geofences list
 */
function listGeofences() {
    try {
        $collection = getCollection('geofences');
        
        // Get all active geofences, sorted by creation date
        $cursor = $collection->find(['active' => true], ['sort' => ['created_at' => -1]]);
        
        $geofences = [];
        foreach ($cursor as $geofence) {
            // Get elephant information if assigned
            $elephant_name = null;
            if (!empty($geofence['elephant_id'])) {
                try {
                    $elephantCollection = getCollection('elephants');
                    $elephant = $elephantCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($geofence['elephant_id'])]);
                    if ($elephant) {
                        $elephant_name = $elephant['name'];
                    }
                } catch (Exception $e) {
                    // If elephant lookup fails, continue without elephant name
                }
            }
            
            $geofences[] = [
                'geofence_id' => $geofence['geofence_id'],
                'elephant_id' => $geofence['elephant_id'],
                'elephant_name' => $elephant_name,
                'lat' => $geofence['lat'],
                'lng' => $geofence['lng'],
                'radius' => $geofence['radius'],
                'name' => $geofence['name'],
                'type' => $geofence['type'],
                'description' => $geofence['description'],
                'created_at' => $geofence['created_at'],
                'updated_at' => $geofence['updated_at']
            ];
        }
        
        return [
            'status' => 'success',
            'message' => 'Geofences retrieved successfully',
            'data' => [
                'count' => count($geofences),
                'geofences' => $geofences
            ],
            'code' => 200
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'code' => 500
        ];
    }
}

/**
 * Edit an existing geofence
 * 
 * @param array $data Geofence data to update
 * @return array Response with status and message
 */
function editGeofence($data) {
    try {
        // Validate required fields
        $required_fields = ['geofence_id', 'lat', 'lng', 'radius'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return [
                    'status' => 'error',
                    'message' => "Missing required field: $field",
                    'code' => 400
                ];
            }
        }
        
        // Validate coordinate ranges
        if ($data['lat'] < -90 || $data['lat'] > 90) {
            return [
                'status' => 'error',
                'message' => 'Latitude must be between -90 and 90 degrees',
                'code' => 400
            ];
        }
        
        if ($data['lng'] < -180 || $data['lng'] > 180) {
            return [
                'status' => 'error',
                'message' => 'Longitude must be between -180 and 180 degrees',
                'code' => 400
            ];
        }
        
        if ($data['radius'] <= 0) {
            return [
                'status' => 'error',
                'message' => 'Radius must be greater than 0',
                'code' => 400
            ];
        }
        
        $collection = getCollection('geofences');
        
        // Check if geofence exists
        $existing = $collection->findOne(['geofence_id' => $data['geofence_id']]);
        if (!$existing) {
            return [
                'status' => 'error',
                'message' => 'Geofence not found',
                'code' => 404
            ];
        }
        
        // Prepare update data
        $updateData = [
            'lat' => (float)$data['lat'],
            'lng' => (float)$data['lng'],
            'radius' => (float)$data['radius'],
            'name' => $data['name'] ?? $existing['name'],
            'type' => $data['type'] ?? $existing['type'],
            'elephant_id' => $data['elephant_id'] ?? $existing['elephant_id'],
            'description' => $data['description'] ?? $existing['description'],
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        // Update the geofence
        $result = $collection->updateOne(
            ['geofence_id' => $data['geofence_id']],
            ['$set' => $updateData]
        );
        
        if ($result->getModifiedCount() > 0) {
            return [
                'status' => 'success',
                'message' => 'Geofence updated successfully',
                'data' => [
                    'geofence_id' => $data['geofence_id'],
                    'modified_count' => $result->getModifiedCount()
                ],
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'No changes made to geofence',
                'code' => 400
            ];
        }
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'code' => 500
        ];
    }
}

/**
 * Delete a geofence (soft delete by setting active to false)
 * 
 * @param string $geofenceId ID of the geofence to delete
 * @return array Response with status and message
 */
function deleteGeofence($geofenceId) {
    try {
        if (empty($geofenceId)) {
            return [
                'status' => 'error',
                'message' => 'Geofence ID is required',
                'code' => 400
            ];
        }
        
        $collection = getCollection('geofences');
        
        // Check if geofence exists
        $existing = $collection->findOne(['geofence_id' => $geofenceId]);
        if (!$existing) {
            return [
                'status' => 'error',
                'message' => 'Geofence not found',
                'code' => 404
            ];
        }
        
        // Soft delete by setting active to false
        $result = $collection->updateOne(
            ['geofence_id' => $geofenceId],
            ['$set' => [
                'active' => false,
                'updated_at' => date('Y-m-d H:i:s')
            ]]
        );
        
        if ($result->getModifiedCount() > 0) {
            return [
                'status' => 'success',
                'message' => 'Geofence deleted successfully',
                'data' => [
                    'geofence_id' => $geofenceId,
                    'modified_count' => $result->getModifiedCount()
                ],
                'code' => 200
            ];
        } else {
            return [
                'status' => 'error',
                'message' => 'Failed to delete geofence',
                'code' => 500
            ];
        }
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'code' => 500
        ];
    }
}

/**
 * Check if an elephant is inside or outside a geofence
 * 
 * @param array $data Elephant location data
 * @return array Response with geofence status
 */
function checkGeofence($data) {
    try {
        // Validate required fields
        $required_fields = ['elephant_id', 'lat', 'lng'];
        foreach ($required_fields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return [
                    'status' => 'error',
                    'message' => "Missing required field: $field",
                    'code' => 400
                ];
            }
        }
        
        // Validate coordinate ranges
        if ($data['lat'] < -90 || $data['lat'] > 90) {
            return [
                'status' => 'error',
                'message' => 'Latitude must be between -90 and 90 degrees',
                'code' => 400
            ];
        }
        
        if ($data['lng'] < -180 || $data['lng'] > 180) {
            return [
                'status' => 'error',
                'message' => 'Longitude must be between -180 and 180 degrees',
                'code' => 400
            ];
        }
        
        $collection = getCollection('geofences');
        
        // Get all active geofences (since elephant_id is now optional)
        $cursor = $collection->find([
            'active' => true
        ]);
        
        $results = [];
        $elephantLat = (float)$data['lat'];
        $elephantLng = (float)$data['lng'];
        
        foreach ($cursor as $geofence) {
            // Calculate distance between elephant and geofence center
            $distance = calculateDistance(
                $elephantLat, 
                $elephantLng, 
                $geofence['lat'], 
                $geofence['lng']
            );
            
            // Check if elephant is inside geofence
            $isInside = $distance <= $geofence['radius'];
            
            $results[] = [
                'geofence_id' => $geofence['geofence_id'],
                'geofence_name' => $geofence['name'],
                'geofence_type' => $geofence['type'],
                'elephant_lat' => $elephantLat,
                'elephant_lng' => $elephantLng,
                'geofence_lat' => $geofence['lat'],
                'geofence_lng' => $geofence['lng'],
                'geofence_radius' => $geofence['radius'],
                'distance_to_center' => round($distance, 2), // in meters
                'status' => $isInside ? 'inside' : 'outside',
                'alert_level' => $isInside ? ($geofence['type'] === 'restricted' ? 'high' : 'low') : 'none'
            ];
        }
        
        if (empty($results)) {
            return [
                'status' => 'success',
                'message' => 'No geofences found for this elephant',
                'data' => [
                    'elephant_id' => $data['elephant_id'],
                    'current_location' => [
                        'lat' => $elephantLat,
                        'lng' => $elephantLng
                    ],
                    'geofences_checked' => 0,
                    'results' => []
                ],
                'code' => 200
            ];
        }
        
        return [
            'status' => 'success',
            'message' => 'Geofence check completed',
            'data' => [
                'elephant_id' => $data['elephant_id'],
                'current_location' => [
                    'lat' => $elephantLat,
                    'lng' => $elephantLng
                ],
                'geofences_checked' => count($results),
                'results' => $results
            ],
            'code' => 200
        ];
        
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'Database error: ' . $e->getMessage(),
            'code' => 500
        ];
    }
}

// Route the request based on HTTP method and endpoint
$method = $_SERVER['REQUEST_METHOD'];
$endpoint = $_GET['action'] ?? '';

try {
    switch ($method) {
        case 'POST':
            // Get JSON input
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid JSON input');
            }
            
                                switch ($endpoint) {
                        case 'add':
                            $response = addGeofence($input);
                            break;
                            
                        case 'edit':
                            $response = editGeofence($input);
                            break;
                            
                        case 'delete':
                            $response = deleteGeofence($input['geofence_id'] ?? '');
                            break;
                            
                        case 'check':
                            $response = checkGeofence($input);
                            break;
                            
                        default:
                            $response = [
                                'status' => 'error',
                                'message' => 'Invalid endpoint. Use: add, edit, delete, check, or list',
                                'code' => 400
                            ];
                    }
            break;
            
        case 'GET':
            switch ($endpoint) {
                case 'list':
                    $response = listGeofences();
                    break;
                    
                default:
                    $response = [
                        'status' => 'error',
                        'message' => 'Invalid endpoint. Use: add, check, or list',
                        'code' => 400
                    ];
            }
            break;
            
        default:
            $response = [
                'status' => 'error',
                'message' => 'Method not allowed. Use GET or POST',
                'code' => 405
            ];
    }
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => 'Server error: ' . $e->getMessage(),
        'code' => 500
    ];
}

// Set HTTP response code
http_response_code($response['code']);

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT);
?>
