<?php
/**
 * GPS Device Test Script
 * This simulates how a GPS device would send location data to your API
 */

// Simulate GPS device data
$gps_data = [
    'device_id' => 'GPS_001',
    'elephant_id' => '65f1234567890abcdef12345', // Replace with actual elephant ID from your database
    'latitude' => 6.2614, // Yala National Park coordinates
    'longitude' => 81.5167,
    'timestamp' => time(),
    'accuracy' => 5.2, // GPS accuracy in meters
    'speed' => 2.5, // Speed in km/h
    'heading' => 180, // Direction in degrees
    'altitude' => 45, // Altitude in meters
    'battery_level' => 85, // Battery percentage
    'signal_strength' => -65, // Signal strength in dBm
    'temperature' => 28.5 // Device temperature in Celsius
];

// Send data to GPS API
// CHANGE THIS TO YOUR ACTUAL WEBSITE DOMAIN:
$api_url = 'http://localhost:8000/api/gps-location.php';
// For local testing, use: 'http://localhost:8000/api/gps-location.php'

// Create cURL request
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($gps_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen(json_encode($gps_data))
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// Execute request
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Display results
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GPS Device Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #17a2b8; }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
        }
        .gps-info {
            background: #e9ecef;
            padding: 15px;
            border-radius: 5px;
            margin: 20px 0;
        }
        .gps-info h3 {
            margin-top: 0;
            color: #495057;
        }
        .gps-info p {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🛰️ GPS Device Test Script</h1>
        <p>This script simulates how a GPS tracking device would send location data to your YalaGuard API.</p>
        
        <div class="gps-info">
            <h3>📡 GPS Data Being Sent:</h3>
            <p><strong>Device ID:</strong> <?php echo htmlspecialchars($gps_data['device_id']); ?></p>
            <p><strong>Elephant ID:</strong> <?php echo htmlspecialchars($gps_data['elephant_id']); ?></p>
            <p><strong>Location:</strong> <?php echo $gps_data['latitude']; ?>, <?php echo $gps_data['longitude']; ?> (Yala National Park)</p>
            <p><strong>Timestamp:</strong> <?php echo date('Y-m-d H:i:s', $gps_data['timestamp']); ?></p>
            <p><strong>Speed:</strong> <?php echo $gps_data['speed']; ?> km/h</p>
            <p><strong>Battery:</strong> <?php echo $gps_data['battery_level']; ?>%</p>
        </div>
        
        <h3>📤 API Response:</h3>
        
        <?php if ($error): ?>
            <p class="error"><strong>❌ cURL Error:</strong> <?php echo htmlspecialchars($error); ?></p>
        <?php else: ?>
            <p class="info"><strong>🌐 HTTP Status Code:</strong> <?php echo $http_code; ?></p>
            
            <?php if ($http_code >= 200 && $http_code < 300): ?>
                <p class="success"><strong>✅ Success!</strong> GPS data was sent successfully.</p>
            <?php else: ?>
                <p class="error"><strong>❌ Error:</strong> HTTP <?php echo $http_code; ?></p>
            <?php endif; ?>
            
            <p><strong>📥 Response:</strong></p>
            <pre><?php echo htmlspecialchars($response); ?></pre>
        <?php endif; ?>
        
        <h3>🔧 How to Use This in Real GPS Devices:</h3>
        <div class="gps-info">
            <h4>1. GPS Device Configuration:</h4>
            <p>Configure your GPS tracker to send HTTP POST requests to:</p>
            <pre>http://your-domain.com/api/gps-location.php</pre>
            
            <h4>2. Data Format:</h4>
            <p>Send JSON data with these required fields:</p>
            <pre>{
    "device_id": "GPS_001",
    "latitude": 6.2614,
    "longitude": 81.5167,
    "timestamp": 1703123456
}</pre>
            
            <h4>3. Optional Fields:</h4>
            <p>You can also include:</p>
            <ul>
                <li><code>elephant_id</code> - Link to specific elephant</li>
                <li><code>accuracy</code> - GPS accuracy in meters</li>
                <li><code>speed</code> - Speed in km/h</li>
                <li><code>battery_level</code> - Battery percentage</li>
                <li><code>signal_strength</code> - Signal strength</li>
            </ul>
            
            <h4>4. Update Frequency:</h4>
            <p>Set your device to send data every 5-30 minutes depending on your needs.</p>
        </div>
        
        <h3>📱 Real GPS Device Examples:</h3>
        <div class="gps-info">
            <h4>Popular GPS Trackers:</h4>
            <ul>
                <li><strong>GSM Trackers:</strong> Send data via cellular network</li>
                <li><strong>Satellite Trackers:</strong> Work in remote areas</li>
                <li><strong>LoRa Trackers:</strong> Long-range, low-power</li>
                <li><strong>Bluetooth Trackers:</strong> Short-range tracking</li>
            </ul>
            
            <h4>Configuration Steps:</h4>
            <ol>
                <li>Insert SIM card (for GSM trackers)</li>
                <li>Set server URL to your API endpoint</li>
                <li>Configure update frequency</li>
                <li>Set device ID to match elephant records</li>
                <li>Test connection</li>
            </ol>
        </div>
        
        <h3>🔄 Test Again:</h3>
        <p>Click the button below to send another test GPS location:</p>
        <form method="POST">
            <button type="submit" style="background: #007bff; color: white; border: none; padding: 10px 20px; border-radius: 5px; cursor: pointer;">
                🚀 Send Test GPS Data
            </button>
        </form>
        
        <p style="margin-top: 20px; color: #6c757d; font-size: 0.9em;">
            <strong>Note:</strong> This is a test script. In production, GPS devices would automatically send data at regular intervals.
        </p>
    </div>
</body>
</html>
