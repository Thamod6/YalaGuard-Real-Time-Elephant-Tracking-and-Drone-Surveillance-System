<?php
/**
 * Simple MongoDB Connection Test
 * 
 * This script tests basic MongoDB connection without the complex configuration
 */

echo "🔍 Simple MongoDB Connection Test\n";
echo "================================\n\n";

// Load environment variables manually
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    echo "❌ .env file not found at: $envFile\n";
    exit(1);
}

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

// Get MongoDB connection details
$uri = $_ENV['MONGODB_URI'] ?? 'NOT SET';
$database = $_ENV['MONGODB_DATABASE'] ?? 'NOT SET';

echo "📝 Connection Details:\n";
echo "   URI: " . (strpos($uri, 'mongodb') === 0 ? '✅ Valid MongoDB URI' : '❌ Invalid URI') . "\n";
echo "   Database: $database\n\n";

// Test basic connection
echo "🔌 Testing Basic Connection...\n";
try {
    // Include MongoDB library
    require_once '../vendor/autoload.php';
    
    // Create MongoDB client
    $client = new MongoDB\Client($uri);
    
    // Test connection by listing databases
    $databases = $client->listDatabases();
    $dbNames = [];
    foreach ($databases as $db) {
        $dbNames[] = $db->getName();
    }
    
    echo "   ✅ Connection successful!\n";
    echo "   📊 Available databases: " . implode(', ', $dbNames) . "\n";
    
    // Test specific database access
    if (in_array($database, $dbNames)) {
        echo "   ✅ Target database '$database' found\n";
        
        // Test collection access
        $db = $client->selectDatabase($database);
        $collections = $db->listCollections();
        $collectionNames = [];
        foreach ($collections as $collection) {
            $collectionNames[] = $collection->getName();
        }
        
        echo "   📁 Collections in '$database': " . implode(', ', $collectionNames) . "\n";
        
        // Test users collection
        if (in_array('users', $collectionNames)) {
            $usersCollection = $db->selectCollection('users');
            $userCount = $usersCollection->countDocuments();
            echo "   👥 Users collection has $userCount documents\n";
        } else {
            echo "   💡 Users collection not found - will be created automatically\n";
        }
        
    } else {
        echo "   ⚠️  Target database '$database' not found - will be created automatically\n";
    }
    
} catch (MongoDB\Driver\Exception\AuthenticationException $e) {
    echo "   ❌ Authentication failed: " . $e->getMessage() . "\n";
    echo "\n🔧 Authentication Fix:\n";
    echo "   1. Check username/password in MongoDB Atlas\n";
    echo "   2. Reset user password if needed\n";
    echo "   3. Ensure user has 'readWrite' permissions\n";
    
} catch (MongoDB\Driver\Exception\ConnectionTimeoutException $e) {
    echo "   ❌ Connection timeout: " . $e->getMessage() . "\n";
    echo "\n🔧 Connection Fix:\n";
    echo "   1. Check your internet connection\n";
    echo "   2. Verify MongoDB Atlas cluster is running\n";
    echo "   3. Check if your IP is whitelisted\n";
    
} catch (Exception $e) {
    echo "   ❌ Connection error: " . $e->getMessage() . "\n";
    echo "\n🔧 General Fix:\n";
    echo "   1. Verify connection string format\n";
    echo "   2. Check MongoDB Atlas status\n";
    echo "   3. Ensure all required fields are set\n";
}

echo "\n💡 Quick Fix Steps:\n";
echo "   1. Go to MongoDB Atlas → Database Access\n";
echo "   2. Reset password for user 'thamoduthpala'\n";
echo "   3. Go to Network Access → Add your IP\n";
echo "   4. Copy fresh connection string from Connect button\n";
echo "   5. Update your .env file\n";
?>
