<?php
/**
 * Test Edit Elephant Functionality
 * 
 * This script helps debug the elephant edit page issues
 */

echo "🧪 Testing Edit Elephant Functionality\n";
echo "=====================================\n\n";

// Test 1: Check if we can load the database configuration
echo "1. Testing Database Configuration...\n";
try {
    require_once __DIR__ . '/../config/database.php';
    echo "   ✅ Database configuration loaded successfully\n";
} catch (Exception $e) {
    echo "   ❌ Failed to load database configuration: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check if we can connect to MongoDB
echo "\n2. Testing MongoDB Connection...\n";
try {
    $client = getMongoConnection();
    echo "   ✅ MongoDB connection successful\n";
} catch (Exception $e) {
    echo "   ❌ MongoDB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Check if we can access the elephants collection
echo "\n3. Testing Elephants Collection Access...\n";
try {
    $collection = getCollection('elephants');
    echo "   ✅ Elephants collection accessed successfully\n";
    
    // Count documents in collection
    $count = $collection->countDocuments();
    echo "   📊 Total elephants in collection: $count\n";
} catch (Exception $e) {
    echo "   ❌ Failed to access elephants collection: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 4: Check if we can find a sample elephant
echo "\n4. Testing Elephant Data Retrieval...\n";
try {
    $sampleElephant = $collection->findOne();
    
    if ($sampleElephant) {
        echo "   ✅ Sample elephant found\n";
        echo "   📝 Elephant ID: " . $sampleElephant->_id . "\n";
        echo "   📝 Elephant Name: " . ($sampleElephant->name ?? 'N/A') . "\n";
        echo "   📝 Elephant Type: " . ($sampleElephant->type ?? 'N/A') . "\n";
        
        // Test ObjectId validation
        if (preg_match('/^[a-f\d]{24}$/i', (string)$sampleElephant->_id)) {
            echo "   ✅ ObjectId is valid\n";
        } else {
            echo "   ❌ ObjectId is invalid\n";
        }
    } else {
        echo "   ⚠️  No elephants found in collection\n";
    }
} catch (Exception $e) {
    echo "   ❌ Failed to retrieve elephant data: " . $e->getMessage() . "\n";
}

// Test 5: Test form submission simulation
echo "\n5. Testing Form Submission Logic...\n";
try {
    // Simulate POST data
    $_POST = [
        'elephant_name' => 'Test Elephant',
        'elephant_type' => 'Asian Elephant',
        'gender' => 'Male',
        'height' => '2.5',
        'weight' => '4000',
        'age' => '25',
        'health_status' => 'Good',
        'notes' => 'Test elephant for debugging'
    ];
    
    // Simulate elephant data
    $elephant = [
        'name' => 'Test Elephant',
        'type' => 'Asian Elephant',
        'gender' => 'Male',
        'height' => '2.5m',
        'weight' => '4000kg',
        'age' => '25 years',
        'health_status' => 'Good',
        'notes' => 'Test elephant for debugging'
    ];
    
    // Test current_values extraction
    $current_values = [
        'elephant_name' => $elephant['name'] ?? '',
        'elephant_type' => $elephant['type'] ?? '',
        'gender' => $elephant['gender'] ?? '',
        'height' => isset($elephant['height']) ? str_replace('m', '', $elephant['height']) : '',
        'weight' => isset($elephant['weight']) ? str_replace('kg', '', $elephant['weight']) : '',
        'age' => isset($elephant['height']) ? str_replace(' years', '', $elephant['age']) : '',
        'health_status' => $elephant['health_status'] ?? 'Good',
        'notes' => $elephant['notes'] ?? '',
        'gps_connected' => false
    ];
    
    echo "   ✅ Form data simulation successful\n";
    echo "   📝 Current values extracted: " . count($current_values) . " fields\n";
    
} catch (Exception $e) {
    echo "   ❌ Form data simulation failed: " . $e->getMessage() . "\n";
}

echo "\n🎉 Test completed successfully!\n";
echo "\n📋 Next Steps:\n";
echo "   1. Check the browser console for JavaScript errors\n";
echo "   2. Add ?debug=1 to the edit elephant URL to see debug info\n";
echo "   3. Check the server error logs for PHP errors\n";
echo "   4. Verify that the form is actually submitting (check network tab)\n";
echo "\n🔧 Common Issues:\n";
echo "   1. JavaScript errors preventing form submission\n";
echo "   2. PHP errors in the form processing\n";
echo "   3. Database connection issues\n";
echo "   4. Invalid ObjectId format\n";
echo "   5. Missing required fields validation\n";
?>
