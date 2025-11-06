<?php
/**
 * YalaGuard Assign Elephant to Geofence Page
 * 
 * This page allows quick assignment of elephants to existing geofences.
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

// Get geofence ID from URL parameter
$geofence_id = $_GET['id'] ?? '';

if (empty($geofence_id)) {
    header('Location: geofencing.php');
    exit();
}

// Get geofence data from database
$geofence = null;
$error_message = null;

try {
    require_once '../config/database.php';
    $collection = getCollection('geofences');
    $geofence = $collection->findOne(['geofence_id' => $geofence_id, 'active' => true]);
    
    if (!$geofence) {
        $error_message = "Geofence not found or has been deleted.";
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
}

// Get elephants for dropdown
$elephants = [];
try {
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
    $elephants = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Assign Elephant to Geofence</title>
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
            <h1>🐘 Assign Elephant to Geofence</h1>
            <p>Quickly assign an elephant to monitor within this geofence</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($geofence): ?>
            <!-- Current Geofence Info -->
            <div class="geofence-info-card">
                <h3>📍 Current Geofence: <?php echo htmlspecialchars($geofence['name']); ?></h3>
                <div class="geofence-details">
                    <p><strong>ID:</strong> <?php echo htmlspecialchars($geofence['geofence_id']); ?></p>
                    <p><strong>Type:</strong> <?php echo htmlspecialchars($geofence['type']); ?></p>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($geofence['lat']); ?>, <?php echo htmlspecialchars($geofence['lng']); ?></p>
                    <p><strong>Radius:</strong> <?php echo htmlspecialchars($geofence['radius']); ?>m</p>
                    <?php if (!empty($geofence['elephant_id'])): ?>
                        <p><strong>Currently Assigned:</strong> 
                            <?php 
                            $currentElephant = null;
                            foreach ($elephants as $elephant) {
                                if ($elephant['id'] === $geofence['elephant_id']) {
                                    $currentElephant = $elephant;
                                    break;
                                }
                            }
                            if ($currentElephant) {
                                echo "🐘 " . htmlspecialchars($currentElephant['name']) . " (" . htmlspecialchars($currentElephant['type']) . ")";
                            } else {
                                echo "Unknown elephant (ID: " . htmlspecialchars($geofence['elephant_id']) . ")";
                            }
                            ?>
                        </p>
                    <?php else: ?>
                        <p><strong>Currently Assigned:</strong> <em>No elephant assigned</em></p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assign Elephant Form -->
            <div class="assign-elephant-form">
                <h3>🔄 Change Elephant Assignment</h3>
                <form id="assignElephantForm">
                    <input type="hidden" id="geofence_id" name="geofence_id" value="<?php echo htmlspecialchars($geofence['geofence_id']); ?>">
                    
                    <div class="form-group">
                        <label for="elephant_id">Select Elephant</label>
                        <select id="elephant_id" name="elephant_id" required>
                            <option value="">-- Choose an elephant --</option>
                            <?php foreach ($elephants as $elephant): ?>
                                <option value="<?php echo htmlspecialchars($elephant['id']); ?>" <?php echo (isset($geofence['elephant_id']) && $geofence['elephant_id'] === $elephant['id']) ? 'selected' : ''; ?>>
                                    🐘 <?php echo htmlspecialchars($elephant['name']); ?> (<?php echo htmlspecialchars($elephant['type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Select an elephant to monitor within this geofence. Choose the same elephant to keep current assignment.</small>
                    </div>

                    <div class="form-actions">
                        <a href="geofencing.php" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn btn-primary">Update Assignment</button>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Handle form submission
        document.getElementById('assignElephantForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const formData = new FormData(e.target);
            const elephantId = formData.get('elephant_id');
            
            if (!elephantId) {
                alert('Please select an elephant to assign.');
                return;
            }
            
            const geofenceData = {
                geofence_id: formData.get('geofence_id'),
                lat: <?php echo $geofence['lat']; ?>,
                lng: <?php echo $geofence['lng']; ?>,
                radius: <?php echo $geofence['radius']; ?>,
                name: '<?php echo addslashes($geofence['name']); ?>',
                type: '<?php echo addslashes($geofence['type']); ?>',
                elephant_id: elephantId,
                description: '<?php echo addslashes($geofence['description'] ?? ''); ?>'
            };

            try {
                const response = await fetch('../api/geofence.php?action=edit', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(geofenceData)
                });

                const result = await response.json();
                
                                 if (result.status === 'success') {
                     alert('Elephant assignment updated successfully!');
                     // Redirect back to geofencing management page
                     window.location.href = 'geofencing.php';
                 } else {
                    alert('Error updating elephant assignment: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error updating elephant assignment. Please try again.');
            }
        });
    </script>
</body>
</html>
