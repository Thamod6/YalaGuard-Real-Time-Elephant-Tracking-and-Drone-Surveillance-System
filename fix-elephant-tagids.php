<?php
/**
 * Fix Elephant TagIds Script
 * 
 * This script fixes elephants with null tagId values by generating unique tagIds.
 */

// Start session
session_start();

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Fix Elephant TagIds</h1>";

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
    
    // Find elephants with null tagId
    echo "<h2>Step 2: Finding Elephants with Null TagId</h2>";
    $nullTagIdElephants = $collection->find(['tagId' => null]);
    $nullCount = 0;
    $elephantsToFix = [];
    
    foreach ($nullTagIdElephants as $elephant) {
        $nullCount++;
        $elephantsToFix[] = $elephant;
        echo "Found elephant: {$elephant['name']} (ID: {$elephant['_id']})<br>";
    }
    
    echo "Total elephants with null tagId: {$nullCount}<br>";
    
    if ($nullCount === 0) {
        echo "<h2>✅ No Fixes Needed</h2>";
        echo "All elephants already have valid tagIds!<br>";
        echo "<a href='pages/elephants.php'>← Back to Elephants</a>";
        exit();
    }
    
    // Fix elephants with null tagId
    echo "<h2>Step 3: Fixing Elephants</h2>";
    $fixedCount = 0;
    
    foreach ($elephantsToFix as $elephant) {
        $newTagId = 'ELEPHANT_' . time() . '_' . uniqid();
        
        try {
            $result = $collection->updateOne(
                ['_id' => $elephant['_id']],
                ['$set' => ['tagId' => $newTagId]]
            );
            
            if ($result->getModifiedCount() > 0) {
                $fixedCount++;
                echo "✅ Fixed elephant: {$elephant['name']} → TagId: {$newTagId}<br>";
            } else {
                echo "❌ Failed to fix elephant: {$elephant['name']}<br>";
            }
        } catch (Exception $e) {
            echo "❌ Error fixing elephant {$elephant['name']}: " . $e->getMessage() . "<br>";
        }
        
        // Small delay to ensure unique tagIds
        usleep(100000); // 0.1 second
    }
    
    echo "<h2>Step 4: Summary</h2>";
    echo "Elephants found with null tagId: {$nullCount}<br>";
    echo "Elephants successfully fixed: {$fixedCount}<br>";
    
    if ($fixedCount > 0) {
        echo "<h2>🎉 Fix Complete!</h2>";
        echo "All elephants now have unique tagIds. You can now add new elephants!<br>";
    }
    
    // Show current elephant count
    $totalElephants = $collection->countDocuments();
    echo "<h2>Current Status</h2>";
    echo "Total elephants in database: {$totalElephants}<br>";
    
    // Show sample elephants
    echo "<h2>Sample Elephants</h2>";
    $sampleElephants = $collection->find([], ['limit' => 5]);
    foreach ($sampleElephants as $elephant) {
        $tagId = $elephant['tagId'] ?? 'NO_TAG_ID';
        echo "🐘 {$elephant['name']} - TagId: {$tagId}<br>";
    }
    
} catch (Exception $e) {
    echo "<h2>❌ Error</h2>";
    echo "Database error: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<h3>Navigation</h3>";
echo "<a href='pages/elephants.php'>← Back to Elephants</a><br>";
echo "<a href='pages/add-elephant.php'>➕ Add New Elephant</a><br>";
echo "<a href='pages/dashboard.php'>🏠 Dashboard</a>";
?>
