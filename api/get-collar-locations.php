<?php
/**
 * Get GPS Collar Locations API
 * 
 * Returns real-time location data for all GPS collars
 * Used by the geofencing map to display live elephant positions
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once '../config/database.php';

try {
    $db = getDatabase();
    $collarCollection = $db->selectCollection('gps_collars');
    $locationCollection = $db->selectCollection('gps_locations');
    
    // Get query parameters
    $hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
    $collar_id = isset($_GET['collar_id']) ? $_GET['collar_id'] : null;
    
    // Build query for locations
    $query = [];
    if ($collar_id) {
        $query['collar_id'] = $collar_id;
    }
    
    // Filter by time if specified
    if ($hours > 0) {
        $cutoffTime = new MongoDB\BSON\UTCDateTime(strtotime("-{$hours} hours") * 1000);
        $query['timestamp'] = ['$gte' => $cutoffTime];
    }
    
    // Get latest location for each collar
    $pipeline = [];
    
    if (!empty($query)) {
        $pipeline[] = ['$match' => $query];
    }
    
    $pipeline[] = [
        '$sort' => ['timestamp' => -1]
    ];
    
    $pipeline[] = [
        '$group' => [
            '_id' => '$collar_id',
            'latest_location' => ['$first' => '$$ROOT']
        ]
    ];
    
    $pipeline[] = [
        '$replaceRoot' => ['newRoot' => '$latest_location']
    ];
    
    $pipeline[] = [
        '$limit' => $limit
    ];
    
    $locations = $locationCollection->aggregate($pipeline)->toArray();
    
    // Get additional collar information
    $collarIds = array_column($locations, 'collar_id');
    $collars = [];
    if (!empty($collarIds)) {
        $collarCursor = $collarCollection->find(['collar_id' => ['$in' => $collarIds]]);
        foreach ($collarCursor as $collar) {
            $collars[$collar['collar_id']] = $collar;
        }
    }
    
    // Enhance location data with collar information
    $enhancedLocations = [];
    foreach ($locations as $location) {
        $collar = $collars[$location['collar_id']] ?? null;
        
        $enhancedLocation = [
            'collar_id' => $location['collar_id'],
            'elephant_id' => $location['elephant_id'],
            'elephant_name' => $location['elephant_name'],
            'latitude' => $location['latitude'],
            'longitude' => $location['longitude'],
            'timestamp' => $location['timestamp'],
            'battery_level' => $location['battery_level'] ?? 100,
            'signal_strength' => $location['signal_strength'] ?? -50,
            'accuracy' => $location['accuracy'] ?? 5.0,
            'speed' => $location['speed'] ?? 0.0,
            'heading' => $location['heading'] ?? 0.0,
            'altitude' => $location['altitude'] ?? 0.0,
            'provider' => $location['provider'] ?? 'unknown',
            'collar_name' => $collar['collar_name'] ?? 'Unknown Collar',
            'collar_model' => $collar['model'] ?? 'Unknown Model',
            'status' => $collar['status'] ?? 'unknown',
            'is_online' => $collar['is_online'] ?? false,
            'deployment_date' => $collar['deployment_date'] ?? null
        ];
        
        $enhancedLocations[] = $enhancedLocation;
    }
    
    // Get movement trails for each collar (last 10 locations)
    $trails = [];
    foreach ($collarIds as $collarId) {
        $trailQuery = ['collar_id' => $collarId];
        if ($hours > 0) {
            $trailQuery['timestamp'] = ['$gte' => $cutoffTime];
        }
        
        $trailLocations = $locationCollection->find(
            $trailQuery,
            [
                'sort' => ['timestamp' => -1],
                'limit' => 10,
                'projection' => [
                    'latitude' => 1,
                    'longitude' => 1,
                    'timestamp' => 1,
                    'speed' => 1
                ]
            ]
        )->toArray();
        
        if (!empty($trailLocations)) {
            $trails[$collarId] = array_reverse($trailLocations); // Oldest to newest
        }
    }
    
    // Get statistics
    $totalCollars = $collarCollection->countDocuments(['status' => 'active']);
    $onlineCollars = $collarCollection->countDocuments(['status' => 'active', 'is_online' => true]);
    $offlineCollars = $totalCollars - $onlineCollars;
    
    echo json_encode([
        'success' => true,
        'data' => [
            'locations' => $enhancedLocations,
            'trails' => $trails,
            'statistics' => [
                'total_collars' => $totalCollars,
                'online_collars' => $onlineCollars,
                'offline_collars' => $offlineCollars,
                'locations_returned' => count($enhancedLocations)
            ],
            'timestamp' => date('Y-m-d H:i:s'),
            'query_params' => [
                'hours' => $hours,
                'limit' => $limit,
                'collar_id' => $collar_id
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log('GPS Location Fetch Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
