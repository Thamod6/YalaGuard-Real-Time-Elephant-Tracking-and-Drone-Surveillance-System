<?php
/**
 * Simple MongoDB Connection Test
 */

echo "🧪 Simple MongoDB Test\n";
echo "=====================\n\n";

// Load .env file manually
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    $envLines = explode("\n", $envContent);
    
    foreach ($envLines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $_ENV[trim($key)] = trim($value);
        }
    }
}

$uri = $_ENV['MONGODB_URI'] ?? 'NOT SET';
echo "URI: " . $uri . "\n\n";

// Include Composer autoloader
require_once __DIR__ . '/vendor/autoload.php';

try {
    echo "🔌 Attempting connection...\n";
    
    // Create client with explicit options
    $client = new MongoDB\Client($uri, [
        'retryWrites' => true,
        'w' => 'majority',
        'serverSelectionTimeoutMS' => 5000,
        'connectTimeoutMS' => 5000
    ]);
    
    echo "✅ Client created successfully\n";
    
    // Try to ping the server
    echo "🏓 Pinging server...\n";
    $client->selectDatabase('admin')->command(['ping' => 1]);
    echo "✅ Ping successful\n";
    
    // List databases
    echo "📊 Listing databases...\n";
    $databases = $client->listDatabases();
    foreach ($databases as $db) {
        echo "   - " . $db->getName() . "\n";
    }
    
    echo "\n🎉 All tests passed! Your connection is working.\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "Error type: " . get_class($e) . "\n";
    
    // Check if it's an authentication error
    if (strpos($e->getMessage(), 'bad auth') !== false) {
        echo "\n🔑 This is an authentication error. Possible causes:\n";
        echo "   1. Wrong username/password\n";
        echo "   2. User doesn't exist\n";
        echo "   3. User doesn't have permissions\n";
        echo "   4. Special characters in password need encoding\n";
    }
}
?>
