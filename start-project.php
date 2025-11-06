<?php
/**
 * YalaGuard Project Startup Script
 * 
 * This script helps you get the project running by checking requirements
 * and providing setup instructions.
 */

echo "🐘 YalaGuard Project Startup\n";
echo "============================\n\n";

// Check PHP version
echo "1. Checking PHP Version...\n";
if (version_compare(PHP_VERSION, '8.0.0', '>=')) {
    echo "   ✅ PHP " . PHP_VERSION . " (meets requirement: >= 8.0)\n";
} else {
    echo "   ❌ PHP " . PHP_VERSION . " (requires >= 8.0)\n";
    echo "   Please upgrade your PHP version.\n";
    exit(1);
}

// Check MongoDB extension
echo "\n2. Checking MongoDB Extension...\n";
if (extension_loaded('mongodb')) {
    echo "   ✅ MongoDB extension is installed\n";
} else {
    echo "   ❌ MongoDB extension is NOT installed\n";
    echo "   Please install it: composer require mongodb/mongodb\n";
    exit(1);
}

// Check Composer dependencies
echo "\n3. Checking Composer Dependencies...\n";
if (file_exists('vendor/autoload.php')) {
    echo "   ✅ Composer dependencies are installed\n";
} else {
    echo "   ❌ Composer dependencies are NOT installed\n";
    echo "   Please run: composer install\n";
    exit(1);
}

// Check .env file
echo "\n4. Checking Environment Configuration...\n";
if (file_exists('.env')) {
    echo "   ✅ .env file found\n";
} else {
    echo "   ❌ .env file NOT found\n";
    echo "   Please create a .env file with your MongoDB credentials.\n";
    echo "   See config/env-template.php for an example.\n\n";
    
    echo "   Quick setup:\n";
    echo "   1. Copy config/env-template.php content\n";
    echo "   2. Create a new file called .env in your project root\n";
    echo "   3. Paste the content and modify the MongoDB connection details\n";
    echo "   4. For local MongoDB, use: MONGODB_URI=mongodb://localhost:27017\n";
    echo "   5. For MongoDB Atlas, use your connection string\n\n";
    
    echo "   Example .env content:\n";
    echo "   MONGODB_URI=mongodb://localhost:27017\n";
    echo "   MONGODB_DATABASE=yalaguard\n";
    echo "   MONGODB_COLLECTION=alerts\n";
    echo "   APP_ENV=development\n";
    echo "   APP_DEBUG=true\n";
    echo "   APP_URL=http://localhost:8000\n\n";
    
    echo "   After creating .env, run this script again.\n";
    exit(1);
}

// Test database connection
echo "\n5. Testing Database Connection...\n";
try {
    require_once 'config/database.php';
    $connectionTest = testConnection();
    
    if ($connectionTest['status'] === 'success') {
        echo "   ✅ MongoDB connection successful!\n";
        echo "   📊 Database: " . $connectionTest['database'] . "\n";
    } else {
        echo "   ❌ MongoDB connection failed: " . $connectionTest['message'] . "\n";
        echo "   Please check your .env file configuration.\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
    echo "   Please check your .env file configuration.\n";
    exit(1);
}

// Check if users collection exists and has users
echo "\n6. Checking User Collection...\n";
try {
    $db = getDatabase();
    $usersCollection = $db->selectCollection('users');
    
    $userCount = $usersCollection->countDocuments();
    echo "   👥 Users in database: " . $userCount . "\n";
    
    if ($userCount === 0) {
        echo "   💡 No users found. You can create the first user through registration.\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error accessing user collection: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎉 All checks passed! Your project is ready to run.\n\n";

echo "📋 Next Steps:\n";
echo "   1. Start the development server:\n";
echo "      php -S localhost:8000\n\n";
echo "   2. Open your browser and visit:\n";
echo "      Main page: http://localhost:8000\n";
echo "      Login: http://localhost:8000/pages/login.php\n";
echo "      Register: http://localhost:8000/pages/register.php\n";
echo "      Dashboard: http://localhost:8000/pages/dashboard.php\n\n";

echo "🔧 Testing:\n";
echo "   - Run test-env/test-validation.php to test the validation system\n";
echo "   - Try registering a new user to test the enhanced validation\n";
echo "   - Try logging in with the registered user\n";
echo "   - Check that validation works on both client and server side\n\n";

echo "📚 Features Added:\n";
echo "   ✅ Enhanced client-side validation for all form fields\n";
echo "   ✅ Real-time validation feedback\n";
echo "   ✅ Password strength indicator\n";
echo "   ✅ User verification status checks\n";
echo "   ✅ Improved server-side validation\n";
echo "   ✅ Better error handling and user feedback\n\n";

echo "🚀 Your YalaGuard project is ready to use!\n";
?>
