<?php
/**
 * Debug script to check alert fields and data structure
 */

require_once '../config/database.php';

try {
    $db = getDatabase();
    $alertCollection = $db->selectCollection('alerts');
    
    // Get a sample alert to see the structure
    $sampleAlert = $alertCollection->findOne();
    
    if ($sampleAlert) {
        echo "<h2>Sample Alert Structure:</h2>";
        echo "<pre>";
        print_r($sampleAlert);
        echo "</pre>";
        
        // Check for elephant and collar related fields
        echo "<h3>Field Analysis:</h3>";
        echo "<ul>";
        foreach ($sampleAlert as $key => $value) {
            if (strpos($key, 'elephant') !== false || strpos($key, 'collar') !== false || strpos($key, 'gps') !== false) {
                echo "<li><strong>$key:</strong> " . (is_string($value) ? $value : gettype($value)) . "</li>";
            }
        }
        echo "</ul>";
        
        // Check if elephant_id exists and try to find related data
        if (isset($sampleAlert['elephant_id'])) {
            echo "<h3>Elephant ID Found: " . $sampleAlert['elephant_id'] . "</h3>";
            
            // Try to get elephant data
            $elephantCollection = $db->selectCollection('elephants');
            $elephant = $elephantCollection->findOne(['_id' => new MongoDB\BSON\ObjectId($sampleAlert['elephant_id'])]);
            
            if ($elephant) {
                echo "<h4>Elephant Data:</h4>";
                echo "<pre>";
                print_r($elephant);
                echo "</pre>";
            } else {
                echo "<p>No elephant found with this ID</p>";
            }
            
            // Try to get GPS collar data by elephant_id
            $collarCollection = $db->selectCollection('gps_collars');
            $collar = $collarCollection->findOne(['elephant_id' => $sampleAlert['elephant_id']]);
            
            if ($collar) {
                echo "<h4>GPS Collar Data (by elephant_id):</h4>";
                echo "<pre>";
                print_r($collar);
                echo "</pre>";
            } else {
                echo "<p>No GPS collar found for this elephant</p>";
            }
        }
        
        // Check for other possible collar fields
        echo "<h3>Checking for Collar Fields:</h3>";
        $collarFields = ['collar_id', 'gps_collar_id', 'device_id', 'tracker_id'];
        foreach ($collarFields as $field) {
            if (isset($sampleAlert[$field])) {
                echo "<p><strong>$field:</strong> " . $sampleAlert[$field] . "</p>";
                
                // Try to get collar data
                $collarCollection = $db->selectCollection('gps_collars');
                $collar = $collarCollection->findOne(['collar_id' => $sampleAlert[$field]]);
                
                if ($collar) {
                    echo "<h4>GPS Collar Data (by $field):</h4>";
                    echo "<pre>";
                    print_r($collar);
                    echo "</pre>";
                }
            }
        }
        
    } else {
        echo "<p>No alerts found in database</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>
