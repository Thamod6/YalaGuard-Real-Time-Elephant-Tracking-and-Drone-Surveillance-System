<?php
/**
 * Debug Add Elephant Page - To identify database issues
 */

// Start session
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🐘 Debug Add Elephant</h1>";

// Test 1: Check if session is working
echo "<h2>Test 1: Session Check</h2>";
if (isset($_SESSION['user_id'])) {
    echo "✅ Session working - User ID: " . $_SESSION['user_id'] . "<br>";
} else {
    echo "❌ Session not working<br>";
}

// Test 2: Check if .env file exists
echo "<h2>Test 2: Environment File Check</h2>";
$envFile = dirname(__DIR__) . '/.env';
if (file_exists($envFile)) {
    echo "✅ .env file found at: " . $envFile . "<br>";
    echo "File contents:<br><pre>";
    echo htmlspecialchars(file_get_contents($envFile));
    echo "</pre>";
} else {
    echo "❌ .env file NOT found at: " . $envFile . "<br>";
    echo "Please create a .env file with your MongoDB credentials<br>";
}

// Test 3: Try to load database configuration
echo "<h2>Test 3: Database Configuration Load</h2>";
try {
    require_once 'config/database.php';
    echo "✅ Database configuration loaded successfully<br>";
} catch (Exception $e) {
    echo "❌ Failed to load database configuration: " . $e->getMessage() . "<br>";
}

// Test 4: Try to connect to MongoDB
echo "<h2>Test 4: MongoDB Connection Test</h2>";
try {
    if (function_exists('getMongoConnection')) {
        $client = getMongoConnection();
        echo "✅ MongoDB connection successful<br>";
        
        // Test database access
        $db = getDatabase();
        echo "✅ Database access successful<br>";
        
        // Test collection access
        $collection = getCollection('elephants');
        echo "✅ Elephants collection access successful<br>";
        
        // Count existing elephants
        $count = $collection->countDocuments();
        echo "✅ Current elephant count: " . $count . "<br>";
        
    } else {
        echo "❌ getMongoConnection function not available<br>";
    }
} catch (Exception $e) {
    echo "❌ MongoDB connection failed: " . $e->getMessage() . "<br>";
}

// Test 5: Check if form was submitted
echo "<h2>Test 5: Form Submission Check</h2>";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "✅ Form was submitted via POST<br>";
    echo "POST data received:<br><pre>";
    print_r($_POST);
    echo "</pre>";
    
    // Check required fields
    $required_fields = ['elephant_name', 'elephant_type', 'height', 'weight', 'age'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = $field;
        }
    }
    
    if (empty($missing_fields)) {
        echo "✅ All required fields are present<br>";
        
        // Try to save to database
        try {
            $elephant_data = [
                'name' => trim($_POST['elephant_name']),
                'type' => $_POST['elephant_type'],
                'height' => $_POST['height'] . 'm',
                'weight' => $_POST['weight'] . 'kg',
                'age' => $_POST['age'] . ' years',
                'gps_connected' => isset($_POST['gps_connected']) ? true : false,
                'health_status' => $_POST['health_status'] ?? 'Good',
                'notes' => trim($_POST['notes'] ?? ''),
                'created_by' => $_SESSION['user_id'] ?? 'unknown',
                'created_at' => date('Y-m-d H:i:s'),
                'active' => true
            ];
            
            echo "Elephant data prepared:<br><pre>";
            print_r($elephant_data);
            echo "</pre>";
            
            $collection = getCollection('elephants');
            $result = $collection->insertOne($elephant_data);
            
            if ($result->getInsertedCount() > 0) {
                echo "✅ Elephant saved successfully! ID: " . $result->getInsertedId() . "<br>";
            } else {
                echo "❌ Failed to save elephant<br>";
            }
            
        } catch (Exception $e) {
            echo "❌ Database save error: " . $e->getMessage() . "<br>";
        }
        
    } else {
        echo "❌ Missing required fields: " . implode(', ', $missing_fields) . "<br>";
    }
    
} else {
    echo "❌ No form submission detected<br>";
}

// Test 6: Simple form for testing
echo "<h2>Test 6: Simple Test Form</h2>";
?>
<form method="POST" style="border: 1px solid #ccc; padding: 20px; margin: 20px 0;">
    <h3>Quick Elephant Add Test</h3>
    <p>
        <label>Name: <input type="text" name="elephant_name" value="Test Elephant" required></label>
    </p>
    <p>
        <label>Type: 
            <select name="elephant_type" required>
                <option value="Asian Elephant">Asian Elephant</option>
                <option value="African Bush Elephant">African Bush Elephant</option>
            </select>
        </label>
    </p>
    <p>
        <label>Height (m): <input type="number" name="height" value="2.5" step="0.1" required></label>
    </p>
    <p>
        <label>Weight (kg): <input type="number" name="weight" value="4000" required></label>
    </p>
    <p>
        <label>Age (years): <input type="number" name="age" value="25" required></label>
    </p>
    <p>
        <label>Health: 
            <select name="health_status">
                <option value="Good">Good</option>
                <option value="Excellent">Excellent</option>
            </select>
        </label>
    </p>
    <p>
        <label>Notes: <textarea name="notes">Test elephant added via debug page</textarea></label>
    </p>
    <p>
        <button type="submit">🐘 Add Test Elephant</button>
    </p>
</form>

<p><a href="add-elephant.php">← Back to Full Add Elephant Page</a></p>
