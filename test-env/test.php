<?php
/**
 * YalaGuard Test Script
 * 
 * Run this script to test your PHP setup and MongoDB connection:
 * php test.php
 */

echo "🐘 YalaGuard PHP Backend Test Script\n";
echo "=====================================\n\n";

// Test 1: PHP Version
echo "1. Testing PHP Version...\n";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo "   ✅ PHP " . PHP_VERSION . " (8.0+ required)\n";
} else {
    echo "   ❌ PHP " . PHP_VERSION . " (8.0+ required)\n";
    exit(1);
}

// Test 2: MongoDB Extension
echo "\n2. Testing MongoDB Extension...\n";
if (extension_loaded('mongodb')) {
    echo "   ✅ MongoDB extension is loaded\n";
} else {
    echo "   ❌ MongoDB extension is not loaded\n";
    echo "   Install with: pecl install mongodb\n";
    exit(1);
}

// Test 3: Composer Autoloader
echo "\n3. Testing Composer Autoloader...\n";
if (file_exists('vendor/autoload.php')) {
    echo "   ✅ Composer autoloader found\n";
    require_once 'vendor/autoload.php';
} else {
    echo "   ❌ Composer autoloader not found\n";
    echo "   Run: composer install\n";
    exit(1);
}

// Test 4: Database Configuration
echo "\n4. Testing Database Configuration...\n";
if (file_exists('config/db.php')) {
    echo "   ✅ Database configuration file found\n";
    require_once 'config/db.php';
} else {
    echo "   ❌ Database configuration file not found\n";
    exit(1);
}

// Test 5: MongoDB Connection
echo "\n5. Testing MongoDB Connection...\n";
try {
    $connectionResult = testConnection();
    if ($connectionResult['status'] === 'success') {
        echo "   ✅ MongoDB connection successful\n";
        echo "   📊 Available databases: " . implode(', ', $connectionResult['available_databases']) . "\n";
    } else {
        echo "   ❌ MongoDB connection failed: " . $connectionResult['message'] . "\n";
        echo "   💡 Check your connection string in config/db.php\n";
    }
} catch (Exception $e) {
    echo "   ❌ MongoDB connection error: " . $e->getMessage() . "\n";
    echo "   💡 Check your connection string in config/db.php\n";
}

// Test 6: API Files
echo "\n6. Testing API Files...\n";
$apiFiles = ['api/alerts.php', 'api/test-connection.php'];
foreach ($apiFiles as $file) {
    if (file_exists($file)) {
        echo "   ✅ $file found\n";
    } else {
        echo "   ❌ $file not found\n";
    }
}

echo "\n=====================================\n";
echo "🎉 Test completed!\n\n";

echo "Next steps:\n";
echo "1. Update MongoDB credentials in config/db.php\n";
echo "2. Start the server: php -S localhost:8000\n";
echo "3. Test the API: http://localhost:8000\n";
echo "4. Test alerts endpoint: POST http://localhost:8000/api/alerts\n";
?>
