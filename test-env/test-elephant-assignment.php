<?php
/**
 * Test Elephant Assignment to Geofences
 * 
 * This page tests the elephant assignment functionality for geofences.
 */

// Start session
session_start();

// Include database configuration
require_once 'config/database.php';

echo "<h1>🐘 Testing Elephant Assignment to Geofences</h1>";

try {
    // Test 1: List all elephants
    echo "<h2>Test 1: Available Elephants</h2>";
    $elephantCollection = getCollection('elephants');
    $elephants = $elephantCollection->find(['active' => true]);
    
    echo "<ul>";
    foreach ($elephants as $elephant) {
        echo "<li>🐘 {$elephant['name']} (ID: {$elephant['_id']}, Type: {$elephant['type']})</li>";
    }
    echo "</ul>";
    
    // Test 2: List all geofences
    echo "<h2>Test 2: Current Geofences</h2>";
    $geofenceCollection = getCollection('geofences');
    $geofences = $geofenceCollection->find(['active' => true]);
    
    echo "<ul>";
    foreach ($geofences as $geofence) {
        $elephantInfo = "No elephant assigned";
        if (!empty($geofence['elephant_id'])) {
            try {
                $elephant = $elephantCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($geofence['elephant_id'])]);
                if ($elephant) {
                    $elephantInfo = "🐘 {$elephant['name']}";
                }
            } catch (Exception $e) {
                $elephantInfo = "Error finding elephant: " . $e->getMessage();
            }
        }
        
        echo "<li>📍 {$geofence['name']} (ID: {$geofence['geofence_id']}, Type: {$geofence['type']}) - Elephant: {$elephantInfo}</li>";
    }
    echo "</ul>";
    
    // Test 3: Test API endpoint
    echo "<h2>Test 3: API Endpoint Test</h2>";
    echo "<p>Testing geofence list API...</p>";
    
    $apiUrl = 'api/geofence.php?action=list';
    $response = file_get_contents($apiUrl);
    
    if ($response !== false) {
        $data = json_decode($response, true);
        if ($data && $data['status'] === 'success') {
            echo "<p>✅ API Response: " . count($data['data']['geofences']) . " geofences found</p>";
            foreach ($data['data']['geofences'] as $geofence) {
                $elephantDisplay = $geofence['elephant_name'] ? "🐘 {$geofence['elephant_name']}" : "No elephant assigned";
                echo "<p>📍 {$geofence['name']} - Elephant: {$elephantDisplay}</p>";
            }
        } else {
            echo "<p>❌ API Error: " . ($data['message'] ?? 'Unknown error') . "</p>";
        }
    } else {
        echo "<p>❌ Failed to connect to API</p>";
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><a href='pages/geofencing.php'>← Back to Geofencing Management</a></p>";
echo "<p><a href='pages/add-geofence.php'>➕ Add New Geofence</a></p>";
?>
