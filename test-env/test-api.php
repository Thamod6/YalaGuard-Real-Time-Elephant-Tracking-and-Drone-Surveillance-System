<?php
/**
 * Test file to debug the manual alert API
 */

// Enable error display temporarily
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Testing Manual Alert API</h2>";

// Test 1: Check if required files exist
echo "<h3>1. Checking Required Files:</h3>";
$requiredFiles = [
    'config/database.php',
    'api/email-service.php',
    'api/manual-alert.php'
];

foreach ($requiredFiles as $file) {
    if (file_exists($file)) {
        echo "✅ {$file} - EXISTS<br>";
    } else {
        echo "❌ {$file} - MISSING<br>";
    }
}

// Test 2: Check if classes exist
echo "<h3>2. Checking Required Classes:</h3>";
try {
    require_once 'config/database.php';
    echo "✅ Database config loaded<br>";
    
    if (function_exists('getDatabase')) {
        echo "✅ getDatabase() function exists<br>";
    } else {
        echo "❌ getDatabase() function missing<br>";
    }
} catch (Exception $e) {
    echo "❌ Database config error: " . $e->getMessage() . "<br>";
}

try {
    require_once 'api/email-service.php';
    echo "✅ Email service loaded<br>";
    
    if (class_exists('EmailService')) {
        echo "✅ EmailService class exists<br>";
    } else {
        echo "❌ EmailService class missing<br>";
    }
} catch (Exception $e) {
    echo "❌ Email service error: " . $e->getMessage() . "<br>";
}

// Test 3: Check MongoDB extension
echo "<h3>3. Checking Extensions:</h3>";
if (extension_loaded('mongodb')) {
    echo "✅ MongoDB extension loaded<br>";
} else {
    echo "❌ MongoDB extension missing<br>";
}

if (extension_loaded('json')) {
    echo "✅ JSON extension loaded<br>";
} else {
    echo "❌ JSON extension missing<br>";
}

// Test 4: Check environment variables
echo "<h3>4. Checking Environment Variables:</h3>";
$envVars = ['MONGODB_URI', 'MONGODB_DATABASE'];
foreach ($envVars as $var) {
    if (isset($_ENV[$var]) && !empty($_ENV[$var])) {
        echo "✅ {$var} - SET<br>";
    } else {
        echo "❌ {$var} - MISSING<br>";
    }
}

// Test 5: Test database connection
echo "<h3>5. Testing Database Connection:</h3>";
try {
    $db = getDatabase();
    echo "✅ Database connection successful<br>";
} catch (Exception $e) {
    echo "❌ Database connection failed: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Next Steps:</h3>";
echo "1. Check the errors above<br>";
echo "2. Fix any missing files or configuration issues<br>";
echo "3. Test the API again<br>";
?>
