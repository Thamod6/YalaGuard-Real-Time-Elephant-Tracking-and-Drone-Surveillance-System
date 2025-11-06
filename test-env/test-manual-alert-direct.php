<?php
/**
 * Test Manual Alert API directly
 */

// Simulate POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['CONTENT_TYPE'] = 'application/json';

// Simulate JSON input
$testData = [
    'alert_type' => 'emergency',
    'authorities' => ['68af87581d1bea4ae207decc'],
    'elephant_id' => null,
    'location' => [
        'latitude' => 6.363525,
        'longitude' => 81.33646
    ],
    'sent_by' => '68addb98a34ebb414c091ad6',
    'sent_by_name' => 'Test User'
];

// Set the input stream
file_put_contents('php://temp', json_encode($testData));
rewind(fopen('php://temp', 'r'));

// Include and test the API
echo "Testing Manual Alert API...\n";
echo "================================\n";

// Capture output
ob_start();

try {
    // Include the API file
    include 'api/manual-alert.php';
    
    // Get the output
    $output = ob_get_clean();
    
    echo "API Output:\n";
    echo $output;
    
} catch (Exception $e) {
    $output = ob_get_clean();
    echo "Exception caught: " . $e->getMessage() . "\n";
    echo "Output buffer content: " . $output . "\n";
} catch (Error $e) {
    $output = ob_get_clean();
    echo "Error caught: " . $e->getMessage() . "\n";
    echo "Output buffer content: " . $output . "\n";
}

echo "\n================================\n";
echo "Test completed.\n";
?>
