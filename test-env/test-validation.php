<?php
/**
 * YalaGuard Validation Test Script
 * 
 * This script tests the enhanced user validation and verification system
 */

echo "🐘 YalaGuard Validation System Test\n";
echo "====================================\n\n";

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
    require_once '../config/database.php';
    $connectionTest = testConnection();
    
    if ($connectionTest['status'] === 'success') {
        echo "   ✅ MongoDB connection successful!\n";
        echo "   📊 Database: " . $connectionTest['database'] . "\n";
    } else {
        echo "   ❌ MongoDB connection failed: " . $connectionTest['message'] . "\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Test user collection and verification field
echo "\n3. Testing User Collection and Verification...\n";
try {
    $db = getDatabase();
    $usersCollection = $db->selectCollection('users');
    
    // Count existing users
    $userCount = $usersCollection->countDocuments();
    echo "   👥 Current users in database: " . $userCount . "\n";
    
    // Check if verification field exists in any user
    $userWithVerification = $usersCollection->findOne(['verified' => ['$exists' => true]]);
    if ($userWithVerification) {
        echo "   ✅ Verification field found in user collection\n";
        echo "   📝 Sample user verification status: " . ($userWithVerification->verified ? 'true' : 'false') . "\n";
    } else {
        echo "   ⚠️  No users with verification field found\n";
        echo "   💡 This is normal for existing databases - new users will have verification\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error accessing user collection: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Test validation functions
echo "\n4. Testing Validation Functions...\n";

// Test email validation
$testEmails = [
    'test@example.com' => true,
    'invalid-email' => false,
    'test.email@domain.co.uk' => true,
    'test@.com' => false,
    'test@domain' => false
];

echo "   📧 Testing email validation:\n";
foreach ($testEmails as $email => $expected) {
    $result = filter_var($email, FILTER_VALIDATE_EMAIL);
    $isValid = $result !== false;
    $status = $isValid === $expected ? '✅' : '❌';
    echo "      $status $email: " . ($isValid ? 'valid' : 'invalid') . "\n";
}

// Test password validation
$testPasswords = [
    'weak' => false,
    'Strong123' => true,
    'password' => false,
    'PASS123' => false,
    'pass123' => false,
    'StrongPass123' => true
];

echo "   🔐 Testing password validation:\n";
foreach ($testPasswords as $password => $expected) {
    $hasLength = strlen($password) >= 8;
    $hasUpper = preg_match('/[A-Z]/', $password);
    $hasLower = preg_match('/[a-z]/', $password);
    $hasNumber = preg_match('/\d/', $password);
    $isValid = $hasLength && $hasUpper && $hasLower && $hasNumber;
    
    $status = $isValid === $expected ? '✅' : '❌';
    echo "      $status '$password': " . ($isValid ? 'strong' : 'weak') . "\n";
}

// Test username validation
$testUsernames = [
    'user123' => true,
    'user_name' => true,
    'user' => false, // too short
    'very_long_username_that_exceeds_limit' => false, // too long
    'user@name' => false, // invalid characters
    'user-name' => false, // invalid characters
    '123user' => true
];

echo "   👤 Testing username validation:\n";
foreach ($testUsernames as $username => $expected) {
    $hasLength = strlen($username) >= 3 && strlen($username) <= 30;
    $hasValidChars = preg_match('/^[a-zA-Z0-9_]+$/', $username);
    $isValid = $hasLength && $hasValidChars;
    
    $status = $isValid === $expected ? '✅' : '❌';
    echo "      $status '$username': " . ($isValid ? 'valid' : 'invalid') . "\n";
}

echo "\n🎉 Validation system test completed!\n";
echo "\n📋 Next steps:\n";
echo "   1. Start your server: php -S localhost:8000\n";
echo "   2. Visit http://localhost:8000/pages/register.php to test registration\n";
echo "   3. Visit http://localhost:8000/pages/login.php to test login\n";
echo "   4. Check that validation works on both client and server side\n";
echo "   5. Verify that new users get verification status in database\n";
?>
