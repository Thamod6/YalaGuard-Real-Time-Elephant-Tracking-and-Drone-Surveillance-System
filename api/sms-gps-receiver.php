<?php
/**
 * YalaGuard - SMS GPS Receiver API
 * 
 * This endpoint receives SMS messages from GPS tracker devices
 * and updates the database with new coordinates
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

require_once '../config/database.php';

// Function to parse GPS coordinates from SMS message
function parseGPSSMS($message) {
    // Common GPS SMS formats
    $patterns = [
        // Format: "GPS: 6.243334,80.055588,2024-01-15 14:30:25"
        '/GPS:\s*([-\d.]+),([-\d.]+),([\d\-\s:]+)/i',
        
        // Format: "Location: 6.243334,80.055588,15/01/2024 14:30:25"
        '/Location:\s*([-\d.]+),([-\d.]+),([\d\/\-\s:]+)/i',
        
        // Format: "6.243334,80.055588,2024-01-15 14:30:25"
        '/^([-\d.]+),([-\d.]+),([\d\-\s:]+)$/',
        
        // Format: "Lat:6.243334 Lon:80.055588 Time:2024-01-15 14:30:25"
        '/Lat:([-\d.]+)\s+Lon:([-\d.]+)\s+Time:([\d\-\s:]+)/i',
        
        // Format: "6.243334,80.055588" (just coordinates)
        '/^([-\d.]+),([-\d.]+)$/'
    ];
    
    foreach ($patterns as $pattern) {
        if (preg_match($pattern, $message, $matches)) {
            $latitude = floatval($matches[1]);
            $longitude = floatval($matches[2]);
            $timestamp = isset($matches[3]) ? $matches[3] : null;
            
            // Validate coordinates
            if ($latitude >= -90 && $latitude <= 90 && 
                $longitude >= -180 && $longitude <= 180) {
                
                return [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timestamp' => $timestamp,
                    'raw_message' => $message
                ];
            }
        }
    }
    
    return null;
}

// Function to find GPS device by phone number
function findGPSDeviceByPhone($phoneNumber) {
    try {
        $db = getDatabase();
        $gpsCollection = $db->selectCollection('gps_devices');
        
        // Try to find device by phone number
        $device = $gpsCollection->findOne([
            'phone_number' => $phoneNumber
        ]);
        
        if (!$device) {
            // Try alternative phone number fields
            $device = $gpsCollection->findOne([
                '$or' => [
                    ['sim_number' => $phoneNumber],
                    ['mobile_number' => $phoneNumber],
                    ['contact_number' => $phoneNumber]
                ]
            ]);
        }
        
        return $device;
    } catch (Exception $e) {
        error_log('Error finding GPS device: ' . $e->getMessage());
        return null;
    }
}

// Function to update GPS device location
function updateGPSLocation($deviceId, $latitude, $longitude, $timestamp = null) {
    try {
        $db = getDatabase();
        $gpsCollection = $db->selectCollection('gps_devices');
        
        $updateData = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'last_location_update' => new MongoDB\BSON\UTCDateTime(),
            'updated_at' => new MongoDB\BSON\UTCDateTime(),
            'is_online' => true,
            'last_sms_received' => new MongoDB\BSON\UTCDateTime()
        ];
        
        // Add timestamp if provided
        if ($timestamp) {
            try {
                $updateData['gps_timestamp'] = new MongoDB\BSON\UTCDateTime(strtotime($timestamp) * 1000);
            } catch (Exception $e) {
                error_log('Invalid timestamp format: ' . $timestamp);
            }
        }
        
        $result = $gpsCollection->updateOne(
            ['device_id' => $deviceId],
            ['$set' => $updateData]
        );
        
        if ($result->getModifiedCount() > 0) {
            error_log("Updated GPS device $deviceId with new location: $latitude, $longitude");
            return true;
        } else {
            error_log("No changes made to GPS device $deviceId");
            return false;
        }
        
    } catch (Exception $e) {
        error_log('Error updating GPS location: ' . $e->getMessage());
        return false;
    }
}

// Function to log SMS message
function logSMSMessage($phoneNumber, $message, $parsedData, $success) {
    try {
        $db = getDatabase();
        $smsCollection = $db->selectCollection('sms_logs');
        
        $smsLog = [
            'phone_number' => $phoneNumber,
            'message' => $message,
            'received_at' => new MongoDB\BSON\UTCDateTime(),
            'parsed_data' => $parsedData,
            'success' => $success,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        $smsCollection->insertOne($smsLog);
        
    } catch (Exception $e) {
        error_log('Error logging SMS: ' . $e->getMessage());
    }
}

// Main SMS processing function
function processSMS($phoneNumber, $message) {
    error_log("Processing SMS from $phoneNumber: $message");
    
    // Parse GPS coordinates from SMS
    $gpsData = parseGPSSMS($message);
    
    if (!$gpsData) {
        error_log("Could not parse GPS data from SMS: $message");
        return [
            'success' => false,
            'message' => 'Could not parse GPS coordinates from SMS message',
            'parsed_message' => $message
        ];
    }
    
    // Find GPS device by phone number
    $device = findGPSDeviceByPhone($phoneNumber);
    
    if (!$device) {
        error_log("No GPS device found for phone number: $phoneNumber");
        return [
            'success' => false,
            'message' => 'No GPS device registered for this phone number',
            'phone_number' => $phoneNumber
        ];
    }
    
    // Update GPS device location
    $updateSuccess = updateGPSLocation(
        $device['device_id'],
        $gpsData['latitude'],
        $gpsData['longitude'],
        $gpsData['timestamp']
    );
    
    if ($updateSuccess) {
        return [
            'success' => true,
            'message' => 'GPS location updated successfully',
            'device_id' => $device['device_id'],
            'device_name' => $device['device_name'],
            'coordinates' => [
                'latitude' => $gpsData['latitude'],
                'longitude' => $gpsData['longitude']
            ],
            'timestamp' => $gpsData['timestamp']
        ];
    } else {
        return [
            'success' => false,
            'message' => 'Failed to update GPS location in database',
            'device_id' => $device['device_id']
        ];
    }
}

// Handle incoming SMS (POST request)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get SMS data from request
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            // Try form data
            $input = $_POST;
        }
        
        // Required fields
        $phoneNumber = $input['phone_number'] ?? $input['from'] ?? null;
        $message = $input['message'] ?? $input['body'] ?? null;
        
        if (!$phoneNumber || !$message) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Missing required fields: phone_number and message',
                'received_data' => $input
            ]);
            exit;
        }
        
        // Process the SMS
        $result = processSMS($phoneNumber, $message);
        
        // Log the SMS message
        logSMSMessage($phoneNumber, $message, $result, $result['success']);
        
        // Return result
        if ($result['success']) {
            http_response_code(200);
        } else {
            http_response_code(400);
        }
        
        echo json_encode($result);
        
    } catch (Exception $e) {
        error_log('SMS processing error: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'message' => 'Internal server error: ' . $e->getMessage()
        ]);
    }
    
} else {
    // GET request - show API info
    echo json_encode([
        'success' => true,
        'message' => 'YalaGuard SMS GPS Receiver API',
        'usage' => [
            'method' => 'POST',
            'endpoint' => '/api/sms-gps-receiver.php',
            'required_fields' => [
                'phone_number' => 'Phone number sending the SMS',
                'message' => 'SMS message content with GPS coordinates'
            ],
            'example' => [
                'phone_number' => '+94771234567',
                'message' => 'GPS: 6.243334,80.055588,2024-01-15 14:30:25'
            ]
        ],
        'supported_formats' => [
            'GPS: lat,lon,timestamp',
            'Location: lat,lon,timestamp',
            'lat,lon,timestamp',
            'Lat:lat Lon:lon Time:timestamp'
        ]
    ]);
}
?>
