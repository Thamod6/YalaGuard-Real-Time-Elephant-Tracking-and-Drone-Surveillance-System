<?php
/**
 * Debug Elephants Script
 * 
 * This script shows the current elephant data structure to debug assignment issues.
 */

// Start session
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🐘 Debug Elephants Data</h1>";

// Check authentication
if (!isset($_SESSION['user_id'])) {
    echo "❌ Please log in first<br>";
    echo "<a href='pages/login.php'>Go to Login</a>";
    exit();
}

try {
    // Load database configuration
    require_once 'config/database.php';
    
    echo "<h2>Step 1: Database Connection</h2>";
    $collection = getCollection('elephants');
    echo "✅ Connected to elephants collection<br>";
    
    // Count total elephants
    $totalElephants = $collection->countDocuments();
    echo "Total elephants in database: {$totalElephants}<br>";
    
    // Show all elephants with their data structure
    echo "<h2>Step 2: All Elephants Data</h2>";
    $allElephants = $collection->find([]);
    
    if ($totalElephants === 0) {
        echo "❌ No elephants found in database<br>";
        echo "<a href='pages/add-elephant.php'>➕ Add First Elephant</a><br>";
        exit();
    }
    
    $elephantCount = 0;
    foreach ($allElephants as $elephant) {
        $elephantCount++;
        echo "<h3>🐘 Elephant #{$elephantCount}: {$elephant['name']}</h3>";
        echo "<div style='background: #f5f5f5; padding: 10px; margin: 10px 0; border-radius: 5px; font-family: monospace; font-size: 12px;'>";
        echo "<strong>MongoDB ID:</strong> {$elephant['_id']}<br>";
        
        // Check each field
        $fields = ['name', 'type', 'height', 'weight', 'age', 'active', 'tagId', 'health_status', 'created_at'];
        foreach ($fields as $field) {
            if (isset($elephant[$field])) {
                $value = $elephant[$field];
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                echo "<strong>{$field}:</strong> {$value}<br>";
            } else {
                echo "<strong>{$field}:</strong> <span style='color: red;'>NOT SET</span><br>";
            }
        }
        
        // Check if this elephant would show in geofence assignment
        $wouldShow = true;
        $issues = [];
        
        if (!isset($elephant['active']) || $elephant['active'] !== true) {
            $wouldShow = false;
            $issues[] = "active field is not true";
        }
        
        if (!isset($elephant['name'])) {
            $wouldShow = false;
            $issues[] = "name field missing";
        }
        
        if (!isset($elephant['type'])) {
            $wouldShow = false;
            $issues[] = "type field missing";
        }
        
        echo "<br><strong>Would show in geofence assignment:</strong> ";
        if ($wouldShow) {
            echo "<span style='color: green;'>✅ YES</span>";
        } else {
            echo "<span style='color: red;'>❌ NO</span><br>";
            echo "<strong>Issues:</strong> " . implode(', ', $issues);
        }
        
        echo "</div>";
    }
    
    // Test the exact query used in geofence assignment
    echo "<h2>Step 3: Testing Geofence Assignment Query</h2>";
    echo "Query used: <code>find(['active' => true], ['sort' => ['name' => 1]])</code><br><br>";
    
    $activeElephants = $collection->find(['active' => true], ['sort' => ['name' => 1]]);
    $activeCount = 0;
    $activeElephantsList = [];
    
    foreach ($activeElephants as $elephant) {
        $activeCount++;
        $activeElephantsList[] = $elephant;
        echo "✅ Active elephant {$activeCount}: {$elephant['name']}<br>";
    }
    
    if ($activeCount === 0) {
        echo "<br>❌ <strong>No active elephants found!</strong><br>";
        echo "This is why you can't assign elephants to geofences.<br>";
    } else {
        echo "<br>✅ <strong>{$activeCount} active elephants found</strong><br>";
        echo "These should show in geofence assignment dropdowns.<br>";
    }
    
    // Show what would be in the dropdown
    echo "<h2>Step 4: Geofence Assignment Dropdown Data</h2>";
    if ($activeCount > 0) {
        echo "Dropdown would contain:<br>";
        foreach ($activeElephantsList as $elephant) {
            $dropdownData = [
                'id' => (string)$elephant['_id'],
                'name' => $elephant['name'],
                'type' => $elephant['type']
            ];
            echo "<code>" . json_encode($dropdownData) . "</code><br>";
        }
    } else {
        echo "❌ No data for dropdown<br>";
    }
    
    // Fix suggestions
    echo "<h2>Step 5: Fix Suggestions</h2>";
    if ($activeCount === 0) {
        echo "<h3>🔧 Quick Fix Options:</h3>";
        echo "<ol>";
        echo "<li><strong>Update existing elephants:</strong> Set active = true for all elephants</li>";
        echo "<li><strong>Remove active filter:</strong> Show all elephants regardless of active status</li>";
        echo "<li><strong>Check field names:</strong> Ensure elephants have required fields</li>";
        echo "</ol>";
        
        echo "<h3>🚀 Quick Fix Button:</h3>";
        echo "<form method='POST' style='border: 1px solid #ccc; padding: 20px; margin: 20px 0;'>";
        echo "<button type='submit' name='fix_elephants' style='background: #28a745; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;'>🔧 Fix All Elephants (Set active = true)</button>";
        echo "</form>";
        
        // Handle the fix
        if (isset($_POST['fix_elephants'])) {
            echo "<h3>🔧 Applying Fix...</h3>";
            $updateResult = $collection->updateMany(
                ['active' => ['$ne' => true]], // Find elephants where active is not true
                ['$set' => ['active' => true]]
            );
            
            $modifiedCount = $updateResult->getModifiedCount();
            echo "✅ Updated {$modifiedCount} elephants to active = true<br>";
            echo "<script>location.reload();</script>";
        }
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "Database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Navigation</h3>";
echo "<a href='pages/elephants.php'>← Back to Elephants</a><br>";
echo "<a href='pages/add-elephant.php'>➕ Add New Elephant</a><br>";
echo "<a href='pages/geofencing.php'>🗺️ Geofencing</a><br>";
echo "<a href='pages/dashboard.php'>🏠 Dashboard</a>";
?>
