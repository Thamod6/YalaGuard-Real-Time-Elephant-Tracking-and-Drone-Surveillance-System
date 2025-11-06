<?php
/**
 * Test Login API
 */

echo "🧪 Testing Login API\n";
echo "===================\n\n";

// Test data
$testData = [
    'username' => 'testuser', // Use the test user we just created
    'password' => 'password123'
];

echo "📝 Test Data:\n";
echo "Username: " . $testData['username'] . "\n";
echo "Password: " . $testData['password'] . "\n\n";

// Make a POST request to the login API
$url = 'http://localhost:8000/api/auth/login.php';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);

echo "🔌 Making request to: " . $url . "\n\n";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ cURL Error: " . $error . "\n";
} else {
    echo "📡 HTTP Response Code: " . $httpCode . "\n";
    echo "📄 Response Body:\n";
    echo $response . "\n";
    
    // Try to decode JSON
    $data = json_decode($response, true);
    if ($data) {
        echo "\n✅ JSON Response Decoded:\n";
        print_r($data);
    } else {
        echo "\n❌ Response is not valid JSON\n";
    }
}

echo "\n🔍 Test Complete\n";
?>
