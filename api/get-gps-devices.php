<?php
/**
 * YalaGuard - Get GPS Devices API
 * 
 * This endpoint returns all GPS devices attached to elephants
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    require_once '../config/database.php';
    $db = getDatabase();
    $gpsCollection = $db->selectCollection('gps_devices');
    
    // Get all active GPS devices
    $devices = $gpsCollection->find([
        'status' => 'active'
    ], [
        'projection' => [
            'device_id' => 1,
            'device_name' => 1,
            'device_type' => 1,
            'elephant_id' => 1,
            'elephant_name' => 1,
            'latitude' => 1,
            'longitude' => 1,
            'status' => 1,
            'last_location_update' => 1,
            'battery_level' => 1,
            'signal_strength' => 1,
            'is_real_time' => 1,
            'update_frequency' => 1
        ]
    ])->toArray();
    
    // Convert MongoDB objects to arrays for JSON encoding
    $devicesArray = [];
    foreach ($devices as $device) {
        $deviceData = [
            'device_id' => $device['device_id'],
            'device_name' => $device['device_name'],
            'device_type' => $device['device_type'],
            'elephant_id' => $device['elephant_id'],
            'elephant_name' => $device['elephant_name'],
            'latitude' => $device['latitude'],
            'longitude' => $device['longitude'],
            'status' => $device['status'],
            'last_location_update' => $device['last_location_update'] ? $device['last_location_update']->toDateTime()->format('c') : null,
            'battery_level' => $device['battery_level'] ?? 0,
            'signal_strength' => $device['signal_strength'] ?? 0,
            'is_real_time' => $device['is_real_time'] ?? false,
            'update_frequency' => $device['update_frequency'] ?? 'Unknown'
        ];
        $devicesArray[] = $deviceData;
    }
    
    echo json_encode([
        'success' => true,
        'devices' => $devicesArray,
        'count' => count($devicesArray),
        'timestamp' => date('c')
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage(),
        'timestamp' => date('c')
    ]);
}
?>
