<?php
/**
 * YalaGuard Add Geofence Page
 * 
 * This page provides a form for adding new geofences.
 */

// Start session and check authentication
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user = [
    'user_id' => $_SESSION['user_id'],
    'username' => $_SESSION['username'],
    'email' => $_SESSION['email'],
    'full_name' => $_SESSION['full_name'],
    'role' => $_SESSION['role'],
    'status' => $_SESSION['status']
];

// Get elephants for dropdown
$elephants = [];
try {
    require_once '../config/database.php';
    $collection = getCollection('elephants');
    $cursor = $collection->find([], ['sort' => ['name' => 1]]); // Show all elephants
    
    foreach ($cursor as $elephant) {
        $elephants[] = [
            'id' => (string)$elephant['_id'],
            'name' => $elephant['name'],
            'type' => $elephant['type']
        ];
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Add New Geofence</title>
    <link rel="stylesheet" href="../assets/css/style.css">

</head>
<body>
    <!-- Simple Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-logo">🐘 YalaGuard</a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="elephants.php">Elephants</a></li>
                <li><a href="geofencing.php" class="active">Geofencing</a></li>
                <li><a href="gps-collar-management.php">GPS Collars</a></li>
                <li><a href="camera.php">Camera</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Alert System</a>
                    <ul class="dropdown-menu">
                        <li><a href="manual-alerts.php">Manual Alert</a></li>
                        <li><a href="authority-management.php">Manage Authorities</a></li>
                        <li><a href="#" onclick="viewAlertHistory()">Alert History</a></li>
                    </ul>
                </li>
            </ul>
            <div class="navbar-user">
                <span class="user-info">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
                <a href="../api/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>➕ Add New Geofence</h1>
            <p>Create virtual boundaries to monitor elephant locations and protect wildlife</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Add New Geofence Form -->
        <div class="geofence-form">
            <form id="geofenceForm">
                
                <div class="coordinate-help">
                    <h4>📍 Yala National Park Geographic Location</h4>
                    <p><strong>Latitude:</strong> 06°16′ – 06°42′ North | <strong>Longitude:</strong> 81°15′ – 81°42′ East</p>
                    <p><strong>Country:</strong> Sri Lanka | <strong>Area:</strong> 979 km² | <strong>Recommended radius:</strong> 500m - 2000m</p>
                </div>

                <div class="form-group">
                    <label for="geofence_id">Geofence ID *</label>
                    <input type="text" id="geofence_id" name="geofence_id" required placeholder="e.g., GF001">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="lat">Latitude *</label>
                        <input type="number" id="lat" name="lat" step="any" required placeholder="e.g., 6.2614">
                        <small>Must be between -90 and 90 degrees</small>
                    </div>
                    <div class="form-group">
                        <label for="lng">Longitude *</label>
                        <input type="number" id="lng" name="lng" step="any" required placeholder="e.g., 81.5167">
                        <small>Must be between -180 and 180 degrees</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="radius">Radius (meters) *</label>
                        <input type="number" id="radius" name="radius" min="1" required placeholder="e.g., 1000">
                        <small style="color: #666; font-size: 0.8rem;">Recommended: 500m - 2000m for Yala Park</small>
                    </div>
                    <div class="form-group">
                        <label for="type">Geofence Type *</label>
                        <select id="type" name="type" required>
                            <option value="restricted">🚨 Restricted Zone</option>
                            <option value="safe">🟢 Safe Zone</option>
                            <option value="monitoring">📊 Monitoring Zone</option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="elephant_id">Assign Elephant (Optional)</label>
                        <select id="elephant_id" name="elephant_id">
                            <option value="">-- Select an elephant --</option>
                            <?php foreach ($elephants as $elephant): ?>
                                <option value="<?php echo htmlspecialchars($elephant['id']); ?>">
                                    🐘 <?php echo htmlspecialchars($elephant['name']); ?> (<?php echo htmlspecialchars($elephant['type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #666; font-size: 0.8rem;">Choose an elephant to monitor within this geofence</small>
                    </div>
                    <div class="form-group">
                        <label for="name">Geofence Name *</label>
                        <input type="text" id="name" name="name" required placeholder="e.g., Yala Restricted Zone">
                    </div>
                </div>

                <div class="form-group full-width">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" placeholder="Describe the purpose of this geofence, what areas it protects, and any special considerations..."></textarea>
                </div>

                <div class="form-actions">
                    <a href="geofencing.php" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Create Geofence</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Handle form submission
        document.getElementById('geofenceForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const geofenceData = {
                geofence_id: formData.get('geofence_id'),
                lat: parseFloat(formData.get('lat')),
                lng: parseFloat(formData.get('lng')),
                radius: parseInt(formData.get('radius')),
                name: formData.get('name'),
                type: formData.get('type'),
                elephant_id: formData.get('elephant_id') || null,
                description: formData.get('description')
            };

            try {
                const response = await fetch('../api/geofence.php?action=add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(geofenceData)
                });

                const result = await response.json();
                
                if (result.status === 'success') {
                    alert('Geofence created successfully!');
                    // Redirect back to geofencing management page
                    window.location.href = 'geofencing.php';
                } else {
                    alert('Error creating geofence: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error creating geofence. Please try again.');
            }
        });

        // Auto-fill Yala coordinates when form loads
        document.addEventListener('DOMContentLoaded', function() {
            // Set default coordinates for Yala National Park
            document.getElementById('lat').value = '6.2614';
            document.getElementById('lng').value = '81.5167';
        });
    </script>
</body>
</html>
