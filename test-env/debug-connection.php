<?php
/**
 * MongoDB Connection Debug Script
 * 
 * This script helps debug MongoDB connection issues
 */

echo "🔍 MongoDB Connection Debug\n";
echo "==========================\n\n";

// Load environment variables
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    $envLines = explode("\n", $envContent);
    
    foreach ($envLines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

echo "📋 Environment Variables:\n";
echo "MONGODB_URI: " . ($_ENV['MONGODB_URI'] ?? 'NOT SET') . "\n";
echo "MONGODB_DATABASE: " . ($_ENV['MONGODB_DATABASE'] ?? 'NOT SET') . "\n";
echo "MONGODB_COLLECTION: " . ($_ENV['MONGODB_COLLECTION'] ?? 'NOT SET') . "\n\n";

// Check if MongoDB extension is loaded
if (!extension_loaded('mongodb')) {
    echo "❌ MongoDB extension not loaded\n";
    exit(1);
}

echo "✅ MongoDB extension loaded\n\n";

// Include Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

// Test 1: Basic connection without database selection
echo "🧪 Test 1: Basic Connection Test\n";
try {
    $uri = $_ENV['MONGODB_URI'];
    echo "Connecting to: " . $uri . "\n";
    
    $client = new MongoDB\Client($uri);
    echo "✅ Basic connection successful\n";
    
    // List databases to test authentication
    $databases = $client->listDatabases();
    echo "✅ Authentication successful\n";
    echo "📊 Available databases:\n";
    foreach ($databases as $db) {
        echo "   - " . $db->getName() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Basic connection failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 2: Try to access specific database
echo "🧪 Test 2: Database Access Test\n";
try {
    $client = new MongoDB\Client($uri);
    $database = $client->selectDatabase($_ENV['MONGODB_DATABASE']);
    echo "✅ Database '" . $_ENV['MONGODB_DATABASE'] . "' accessed successfully\n";
    
    // Try to list collections
    $collections = $database->listCollections();
    echo "✅ Collections access successful\n";
    echo "📁 Collections in database:\n";
    foreach ($collections as $collection) {
        echo "   - " . $collection->getName() . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Database access failed: " . $e->getMessage() . "\n";
}

echo "\n";

// Test 3: Try to create a test collection
echo "🧪 Test 3: Collection Creation Test\n";
try {
    $client = new MongoDB\Client($uri);
    $database = $client->selectDatabase($_ENV['MONGODB_DATABASE']);
    $collection = $database->selectCollection('test_connection');
    
    // Try to insert a test document
    $result = $collection->insertOne(['test' => true, 'timestamp' => new MongoDB\BSON\UTCDateTime()]);
    echo "✅ Test document inserted successfully\n";
    echo "📝 Document ID: " . $result->getInsertedId() . "\n";
    
    // Clean up - delete the test document
    $collection->deleteOne(['_id' => $result->getInsertedId()]);
    echo "✅ Test document cleaned up\n";
    
} catch (Exception $e) {
    echo "❌ Collection test failed: " . $e->getMessage() . "\n";
}

echo "\n🔍 Debug Complete\n";
?>
