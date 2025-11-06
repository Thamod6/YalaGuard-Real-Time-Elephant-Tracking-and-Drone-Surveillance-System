<?php
/**
 * Create Test User Script
 */

echo "👤 Creating Test User\n";
echo "====================\n\n";

// Include database configuration
require_once 'config/database.php';

try {
    $db = getDatabase();
    $usersCollection = $db->selectCollection('users');
    
    // Check existing users
    echo "📊 Existing users in database:\n";
    $users = $usersCollection->find([]);
    foreach ($users as $user) {
        echo "   - Username: " . $user->username . " | Email: " . $user->email . " | Status: " . $user->status . "\n";
    }
    
    echo "\n";
    
    // Create a test user with known credentials
    $testUser = [
        'username' => 'testuser',
        'email' => 'test@yalaguard.com',
        'password' => password_hash('password123', PASSWORD_DEFAULT),
        'full_name' => 'Test User',
        'role' => 'user',
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
        'status' => 'active'
    ];
    
    // Check if user already exists
    $existingUser = $usersCollection->findOne(['username' => 'testuser']);
    if ($existingUser) {
        echo "✅ Test user 'testuser' already exists\n";
        echo "📝 Username: testuser\n";
        echo "🔑 Password: password123\n";
    } else {
        // Insert new test user
        $result = $usersCollection->insertOne($testUser);
        if ($result->getInsertedCount() > 0) {
            echo "✅ Test user created successfully!\n";
            echo "📝 Username: testuser\n";
            echo "🔑 Password: password123\n";
            echo "🆔 User ID: " . $result->getInsertedId() . "\n";
        } else {
            echo "❌ Failed to create test user\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n🔍 Script Complete\n";
?>
