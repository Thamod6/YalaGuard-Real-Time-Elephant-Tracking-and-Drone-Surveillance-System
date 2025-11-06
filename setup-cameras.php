<?php
/**
 * YalaGuard Camera System Setup Script
 * 
 * This script initializes the camera system by creating the necessary
 * collections and adding sample camera configurations.
 */

echo "🚁 Setting up YalaGuard Camera System\n";
echo "=====================================\n\n";

// Include database configuration
require_once 'config/database.php';

try {
    $db = getDatabase();
    
    // Create cameras collection
    $camerasCollection = $db->selectCollection('cameras');
    echo "✅ Cameras collection ready\n";
    
    // Create recordings collection
    $recordingsCollection = $db->selectCollection('recordings');
    echo "✅ Recordings collection ready\n";
    
    // Check if cameras already exist
    $existingCameras = $camerasCollection->countDocuments([]);
    
    if ($existingCameras > 0) {
        echo "📊 Found {$existingCameras} existing cameras\n";
        
        echo "\n📋 Current cameras:\n";
        $cameras = $camerasCollection->find([]);
        foreach ($cameras as $camera) {
            echo "   - {$camera->name} ({$camera->type}) - {$camera->location} - {$camera->status}\n";
        }
    } else {
        echo "📝 No cameras found. Adding sample cameras...\n\n";
        
        // Sample camera configurations
        $sampleCameras = [
            [
                'name' => 'Drone Camera 1',
                'type' => 'drone',
                'ip' => '192.168.1.100',
                'port' => 8080,
                'username' => 'admin',
                'password' => 'password123',
                'rtsp_url' => 'rtsp://admin:password123@192.168.1.100:8080/stream1',
                'http_url' => 'http://192.168.1.100:8080/snapshot.cgi',
                'status' => 'offline',
                'location' => 'North Zone',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => 'system'
            ],
            [
                'name' => 'IP Camera 1',
                'type' => 'ip',
                'ip' => '192.168.1.101',
                'port' => 80,
                'username' => 'admin',
                'password' => 'password123',
                'rtsp_url' => 'rtsp://admin:password123@192.168.1.101:554/stream1',
                'http_url' => 'http://192.168.1.101/snapshot.jpg',
                'status' => 'offline',
                'location' => 'South Zone',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => 'system'
            ],
            [
                'name' => 'Drone Camera 2',
                'type' => 'drone',
                'ip' => '192.168.1.102',
                'port' => 8080,
                'username' => 'admin',
                'password' => 'password123',
                'rtsp_url' => 'rtsp://admin:password123@192.168.1.102:8080/stream1',
                'http_url' => 'http://192.168.1.102:8080/snapshot.cgi',
                'status' => 'offline',
                'location' => 'East Zone',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => 'system'
            ]
        ];
        
        // Insert sample cameras
        foreach ($sampleCameras as $camera) {
            $result = $camerasCollection->insertOne($camera);
            if ($result->getInsertedCount() > 0) {
                echo "✅ Added: {$camera['name']} ({$camera['type']}) at {$camera['ip']}:{$camera['port']}\n";
            } else {
                echo "❌ Failed to add: {$camera['name']}\n";
            }
        }
        
        echo "\n🎯 Sample cameras added successfully!\n";
    }
    
    // Check recordings collection
    $existingRecordings = $recordingsCollection->countDocuments([]);
    echo "\n📹 Recordings collection: {$existingRecordings} recordings found\n";
    
    echo "\n🚀 Camera system setup complete!\n";
    echo "\n📖 Next steps:\n";
    echo "   1. Update camera IP addresses in the database to match your actual cameras\n";
    echo "   2. Update usernames and passwords for your cameras\n";
    echo "   3. Test camera connections from the camera.php page\n";
    echo "   4. Configure RTSP and HTTP URLs for your specific camera models\n";
    echo "\n🔗 Access your camera system at: http://localhost:8000/pages/camera.php\n";
    
} catch (Exception $e) {
    echo "❌ Error setting up camera system: " . $e->getMessage() . "\n";
}

echo "\n🔍 Setup script complete\n";
?>
