<?php
/**
 * Fix Coordinates Script
 * 
 * This script fixes any existing alerts in the database that have string coordinates
 * by converting them to numbers. This resolves the "Can't extract geo keys" error.
 */

require_once 'config/database.php';

try {
    $db = getDatabase();
    $alertsCollection = $db->selectCollection('alerts');
    
    echo "🔧 Starting coordinate fix process...\n";
    
    // Find alerts with string coordinates
    $alertsWithStringCoords = $alertsCollection->find([
        '$or' => [
            ['location.latitude' => ['$type' => 'string']],
            ['location.longitude' => ['$type' => 'string']]
        ]
    ]);
    
    $fixedCount = 0;
    $totalAlerts = 0;
    
    foreach ($alertsWithStringCoords as $alert) {
        $totalAlerts++;
        $needsUpdate = false;
        $updateData = [];
        
        // Check latitude
        if (isset($alert['location']['latitude']) && is_string($alert['location']['latitude'])) {
            $updateData['location.latitude'] = (float)$alert['location']['latitude'];
            $needsUpdate = true;
            echo "  - Alert {$alert['_id']}: Converting latitude '{$alert['location']['latitude']}' to " . (float)$alert['location']['latitude'] . "\n";
        }
        
        // Check longitude
        if (isset($alert['location']['longitude']) && is_string($alert['location']['longitude'])) {
            $updateData['location.longitude'] = (float)$alert['location']['longitude'];
            $needsUpdate = true;
            echo "  - Alert {$alert['_id']}: Converting longitude '{$alert['location']['longitude']}' to " . (float)$alert['location']['longitude'] . "\n";
        }
        
        // Update if needed
        if ($needsUpdate) {
            $result = $alertsCollection->updateOne(
                ['_id' => $alert['_id']],
                ['$set' => $updateData]
            );
            
            if ($result->getModifiedCount() > 0) {
                $fixedCount++;
                echo "  ✅ Fixed alert {$alert['_id']}\n";
            } else {
                echo "  ❌ Failed to update alert {$alert['_id']}\n";
            }
        }
    }
    
    echo "\n📊 Summary:\n";
    echo "  - Total alerts checked: {$totalAlerts}\n";
    echo "  - Alerts fixed: {$fixedCount}\n";
    echo "  - No action needed: " . ($totalAlerts - $fixedCount) . "\n";
    
    if ($fixedCount > 0) {
        echo "\n✅ Coordinate fix completed successfully!\n";
        echo "The 'Can't extract geo keys' error should now be resolved.\n";
    } else {
        echo "\nℹ️ No alerts with string coordinates found.\n";
        echo "All coordinates are already in the correct numeric format.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "\n🎯 Next steps:\n";
echo "1. Test creating a new manual alert with coordinates\n";
echo "2. Verify that the alert is saved without errors\n";
echo "3. Check that geospatial queries work properly\n";
?>
