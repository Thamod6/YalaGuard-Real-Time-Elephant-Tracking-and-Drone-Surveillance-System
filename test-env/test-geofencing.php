<?php
/**
 * YalaGuard Geofencing API Test File
 * 
 * This file demonstrates how to use the geofencing API endpoints.
 * Run this file to test the geofencing functionality.
 */

// Base URL for the API
$baseUrl = 'http://localhost:8000/api/geofence.php';

echo "🚁 YalaGuard Geofencing API Test\n";
echo "================================\n\n";

/**
 * Test function to make HTTP requests
 */
function makeRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return [
        'code' => $httpCode,
        'response' => json_decode($response, true)
    ];
}

/**
 * Test 1: Add a new geofence
 */
echo "1️⃣ Testing: Add Geofence\n";
echo "------------------------\n";

$geofenceData = [
    'geofence_id' => 'GF001',
    'elephant_id' => 'ELE001',
    'lat' => 6.9271,  // Yala National Park coordinates
    'lng' => 81.5172,
    'radius' => 1000, // 1km radius
    'name' => 'Yala Restricted Zone',
    'type' => 'restricted',
    'description' => 'Protected area - no human access allowed'
];

$result = makeRequest($baseUrl . '?action=add', 'POST', $geofenceData);

if ($result['code'] === 201) {
    echo "✅ Geofence added successfully!\n";
    echo "   ID: " . $result['response']['data']['geofence_id'] . "\n";
    echo "   MongoDB ID: " . $result['response']['data']['inserted_id'] . "\n\n";
} else {
    echo "❌ Failed to add geofence\n";
    echo "   HTTP Code: " . $result['code'] . "\n";
    echo "   Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n\n";
}

/**
 * Test 2: Add another geofence (safe zone)
 */
echo "2️⃣ Testing: Add Safe Zone Geofence\n";
echo "----------------------------------\n";

$safeZoneData = [
    'geofence_id' => 'GF002',
    'elephant_id' => 'ELE001',
    'lat' => 6.9271,
    'lng' => 81.5172,
    'radius' => 500, // 500m radius
    'name' => 'Yala Safe Zone',
    'type' => 'safe',
    'description' => 'Designated safe area for elephants'
];

$result = makeRequest($baseUrl . '?action=add', 'POST', $safeZoneData);

if ($result['code'] === 201) {
    echo "✅ Safe zone geofence added successfully!\n";
    echo "   ID: " . $result['response']['data']['geofence_id'] . "\n\n";
} else {
    echo "❌ Failed to add safe zone geofence\n";
    echo "   HTTP Code: " . $result['code'] . "\n";
    echo "   Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n\n";
}

/**
 * Test 3: List all geofences
 */
echo "3️⃣ Testing: List All Geofences\n";
echo "------------------------------\n";

$result = makeRequest($baseUrl . '?action=list');

if ($result['code'] === 200) {
    echo "✅ Geofences retrieved successfully!\n";
    echo "   Total count: " . $result['response']['data']['count'] . "\n\n";
    
    foreach ($result['response']['data']['geofences'] as $geofence) {
        echo "   🗺️  " . $geofence['name'] . " (ID: " . $geofence['geofence_id'] . ")\n";
        echo "      Type: " . $geofence['type'] . "\n";
        echo "      Location: " . $geofence['lat'] . ", " . $geofence['lng'] . "\n";
        echo "      Radius: " . $geofence['radius'] . "m\n";
        echo "      Elephant: " . $geofence['elephant_id'] . "\n\n";
    }
} else {
    echo "❌ Failed to retrieve geofences\n";
    echo "   HTTP Code: " . $result['code'] . "\n";
    echo "   Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n\n";
}

/**
 * Test 4: Check geofence status (elephant inside)
 */
echo "4️⃣ Testing: Check Geofence Status (Inside)\n";
echo "------------------------------------------\n";

$locationData = [
    'elephant_id' => 'ELE001',
    'lat' => 6.9271,  // Same as geofence center
    'lng' => 81.5172
];

$result = makeRequest($baseUrl . '?action=check', 'POST', $locationData);

if ($result['code'] === 200) {
    echo "✅ Geofence check completed!\n";
    echo "   Elephant ID: " . $result['response']['data']['elephant_id'] . "\n";
    echo "   Current Location: " . $result['response']['data']['current_location']['lat'] . ", " . $result['response']['data']['current_location']['lng'] . "\n";
    echo "   Geofences checked: " . $result['response']['data']['geofences_checked'] . "\n\n";
    
    foreach ($result['response']['data']['results'] as $check) {
        echo "   🚨 " . $check['geofence_name'] . "\n";
        echo "      Status: " . $check['status'] . "\n";
        echo "      Alert Level: " . $check['alert_level'] . "\n";
        echo "      Distance to center: " . $check['distance_to_center'] . "m\n";
        echo "      Geofence radius: " . $check['geofence_radius'] . "m\n\n";
    }
} else {
    echo "❌ Failed to check geofence status\n";
    echo "   HTTP Code: " . $result['code'] . "\n";
    echo "   Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n\n";
}

/**
 * Test 5: Check geofence status (elephant outside)
 */
echo "5️⃣ Testing: Check Geofence Status (Outside)\n";
echo "--------------------------------------------\n";

$outsideLocationData = [
    'elephant_id' => 'ELE001',
    'lat' => 6.9200,  // Slightly outside the geofence
    'lng' => 81.5100
];

$result = makeRequest($baseUrl . '?action=check', 'POST', $outsideLocationData);

if ($result['code'] === 200) {
    echo "✅ Geofence check completed!\n";
    echo "   Elephant ID: " . $result['response']['data']['elephant_id'] . "\n";
    echo "   Current Location: " . $result['response']['data']['current_location']['lat'] . ", " . $result['response']['data']['current_location']['lng'] . "\n";
    echo "   Geofences checked: " . $result['response']['data']['geofences_checked'] . "\n\n";
    
    foreach ($result['response']['data']['results'] as $check) {
        echo "   🟢 " . $check['geofence_name'] . "\n";
        echo "      Status: " . $check['status'] . "\n";
        echo "      Alert Level: " . $check['alert_level'] . "\n";
        echo "      Distance to center: " . $check['distance_to_center'] . "m\n";
        echo "      Geofence radius: " . $check['geofence_radius'] . "m\n\n";
    }
} else {
    echo "❌ Failed to check geofence status\n";
    echo "   HTTP Code: " . $result['code'] . "\n";
    echo "   Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n\n";
}

/**
 * Test 6: Test invalid coordinates
 */
echo "6️⃣ Testing: Invalid Coordinates\n";
echo "--------------------------------\n";

$invalidData = [
    'elephant_id' => 'ELE001',
    'lat' => 100,  // Invalid latitude (> 90)
    'lng' => 81.5172
];

$result = makeRequest($baseUrl . '?action=check', 'POST', $invalidData);

if ($result['code'] === 400) {
    echo "✅ Validation working correctly!\n";
    echo "   HTTP Code: " . $result['code'] . "\n";
    echo "   Error: " . ($result['response']['message'] ?? 'Unknown error') . "\n\n";
} else {
    echo "❌ Validation failed\n";
    echo "   HTTP Code: " . $result['code'] . "\n";
    echo "   Response: " . json_encode($result['response']) . "\n\n";
}

echo "🏁 Geofencing API Test Complete!\n";
echo "===============================\n\n";

echo "📚 API Usage Examples:\n";
echo "----------------------\n";
echo "• Add geofence: POST " . $baseUrl . "?action=add\n";
echo "• List geofences: GET " . $baseUrl . "?action=list\n";
echo "• Check status: POST " . $baseUrl . "?action=check\n\n";

echo "🔧 Next Steps:\n";
echo "--------------\n";
echo "1. Integrate with your elephant tracking system\n";
echo "2. Set up automated geofence monitoring\n";
echo "3. Configure alert notifications\n";
echo "4. Add geofence management UI\n\n";
?>
