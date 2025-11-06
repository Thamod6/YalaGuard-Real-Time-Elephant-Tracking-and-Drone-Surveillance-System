<?php
/**
 * MongoDB Connection Test Script
 * 
 * This script tests the MongoDB connection and basic operations
 */

echo "🐘 YalaGuard MongoDB Connection Test\n";
echo "=====================================\n\n";

// Test 1: Check if MongoDB extension is installed
echo "1. Checking MongoDB PHP Extension...\n";
if (extension_loaded('mongodb')) {
    echo "   ✅ MongoDB extension is installed\n";
} else {
    echo "   ❌ MongoDB extension is NOT installed\n";
    echo "   Please install it: composer require mongodb/mongodb\n";
    exit(1);
}

// Test 2: Test database connection
echo "\n2. Testing Database Connection...\n";
try {
    require_once 'config/database.php';
    $connectionTest = testConnection();
    
    if ($connectionTest['status'] === 'success') {
        echo "   ✅ MongoDB connection successful!\n";
        echo "   📊 Available databases: " . implode(', ', $connectionTest['available_databases']) . "\n";
    } else {
        echo "   ❌ MongoDB connection failed: " . $connectionTest['message'] . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Test database and collection access
echo "\n3. Testing Database and Collection Access...\n";
try {
    $db = getDatabase();
    echo "   ✅ Database '" . $_ENV['MONGODB_DATABASE'] . "' accessed successfully\n";
    
    $usersCollection = $db->selectCollection('users');
    echo "   ✅ Users collection accessed successfully\n";
    
    // Count existing users
    $userCount = $usersCollection->countDocuments();
    echo "   👥 Current users in database: " . $userCount . "\n";
    
} catch (Exception $e) {
    echo "   ❌ Error accessing database/collection: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Test user insertion (dry run)
echo "\n4. Testing User Document Creation...\n";
try {
    $testUser = [
        'username' => 'test_user_' . time(),
        'email' => 'test' . time() . '@example.com',
        'password' => password_hash('test123', PASSWORD_DEFAULT),
        'full_name' => 'Test User',
        'role' => 'user',
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
        'status' => 'active'
    ];
    
    echo "   ✅ Test user document created successfully\n";
    echo "   📝 Username: " . $testUser['username'] . "\n";
    echo "   📧 Email: " . $testUser['email'] . "\n";
    
} catch (Exception $e) {
    echo "   ❌ Error creating test user: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 All tests passed! Your MongoDB connection is working correctly.\n";
echo "\n📋 Next steps:\n";
echo "   1. Make sure your .env file has the correct MONGODB_URI\n";
echo "   2. Start your server: php -S localhost:8000\n";
echo "   3. Visit http://localhost:8000 to test the registration\n";
echo "   4. Check your MongoDB 'test' database for the 'users' collection\n";
?>
