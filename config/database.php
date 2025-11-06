<?php
/**
 * YalaGuard Database Configuration
 * 
 * This file loads database configuration from environment variables.
 * Make sure your .env file has the correct MongoDB Atlas connection details.
 */

// Include Composer autoloader for MongoDB classes
require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables from .env file
function loadEnv() {
    $envFile = dirname(__DIR__) . '/.env';
    
    if (!file_exists($envFile)) {
        throw new Exception('.env file not found. Please create one with your MongoDB credentials.');
    }
    
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

/**
 * Get MongoDB connection
 * 
 * @return MongoDB\Client
 * @throws Exception
 */
function getMongoConnection() {
    // Load environment variables only when needed
    loadEnv();
    
    // Validate required environment variables
    $requiredEnvVars = ['MONGODB_URI', 'MONGODB_DATABASE'];
    foreach ($requiredEnvVars as $var) {
        if (!isset($_ENV[$var]) || empty($_ENV[$var])) {
            throw new Exception("Missing required environment variable: $var");
        }
    }
    
    try {
        $uri = $_ENV['MONGODB_URI'];
        $options = [
            'retryWrites' => true,
            'w' => 'majority'
        ];
        
        // Create MongoDB client
        $client = new MongoDB\Client($uri, $options);
        
        // Test the connection
        $client->listDatabases();
        
        return $client;
    } catch (Exception $e) {
        throw new Exception('Failed to connect to MongoDB: ' . $e->getMessage());
    }
}

/**
 * Get database instance
 * 
 * @return MongoDB\Database
 */
function getDatabase() {
    $client = getMongoConnection();
    return $client->selectDatabase($_ENV['MONGODB_DATABASE']);
}

/**
 * Get collection instance
 * 
 * @param string $collectionName
 * @return MongoDB\Collection
 */
function getCollection($collectionName = null) {
    $db = getDatabase();
    $collection = $collectionName ?? ($_ENV['MONGODB_COLLECTION'] ?? 'alerts');
    return $db->selectCollection($collection);
}

/**
 * Test database connection
 * 
 * @return array
 */
function testConnection() {
    try {
        $client = getMongoConnection();
        $databases = $client->listDatabases();
        $dbNames = [];
        foreach ($databases as $database) {
            $dbNames[] = $database->getName();
        }
        
        return [
            'status' => 'success',
            'message' => 'MongoDB connection successful',
            'database' => $_ENV['MONGODB_DATABASE'],
            'available_databases' => $dbNames
        ];
    } catch (Exception $e) {
        return [
            'status' => 'error',
            'message' => 'MongoDB connection failed: ' . $e->getMessage()
        ];
    }
}
