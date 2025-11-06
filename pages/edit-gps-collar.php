<?php
/**
 * YalaGuard - Edit GPS Collar
 * 
 * This page allows editing of existing GPS collar settings and configurations
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

$success_message = '';
$error_message = '';
$collar = null;
$elephants = [];

// Get collar ID from URL
$collar_id = $_GET['id'] ?? '';

if (empty($collar_id)) {
    header('Location: gps-collar-management.php');
    exit();
}

// Load collar data
try {
    require_once '../config/database.php';
    $db = getDatabase();
    $collarCollection = $db->selectCollection('gps_collars');
    
    $collar = $collarCollection->findOne(['collar_id' => $collar_id]);
    
    if (!$collar) {
        $error_message = 'GPS collar not found.';
    } else {
        // Load elephants for dropdown
        $elephantCollection = $db->selectCollection('elephants');
        $elephants = $elephantCollection->find([
            '$or' => [
                ['status' => 'active'],
                ['active' => true],
                ['status' => 'Active']
            ]
        ])->toArray();
        
        // If no elephants found, try without status filter
        if (empty($elephants)) {
            $elephants = $elephantCollection->find([], ['limit' => 10])->toArray();
        }
    }
} catch (Exception $e) {
    $error_message = 'Database error: ' . $e->getMessage();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $collar) {
    $collar_name = trim($_POST['collar_name'] ?? '');
    $provider = trim($_POST['provider'] ?? '');
    $elephant_id = trim($_POST['elephant_id'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $status = trim($_POST['status'] ?? '');
    $transmission_power = trim($_POST['transmission_power'] ?? '');
    $sleep_mode = isset($_POST['sleep_mode']) ? true : false;
    $update_frequency = trim($_POST['update_frequency'] ?? '');
    
    if (empty($collar_name)) {
        $error_message = 'Collar name is required.';
    } elseif (empty($provider)) {
        $error_message = 'GPS provider is required.';
    } elseif (empty($status)) {
        $error_message = 'Status is required.';
    } else {
        try {
            $elephantCollection = $db->selectCollection('elephants');
            
            // Get elephant name if elephant is selected
            $elephant_name = null;
            if (!empty($elephant_id)) {
                try {
                    $objectId = new MongoDB\BSON\ObjectId($elephant_id);
                    $elephant = $elephantCollection->findOne(['_id' => $objectId]);
                } catch (Exception $e) {
                    $elephant = $elephantCollection->findOne(['elephant_id' => $elephant_id]);
                }
                
                if ($elephant) {
                    $elephant_name = $elephant['elephant_name'] ?? $elephant['name'] ?? 'Unknown Elephant';
                }
            }
            
            // Update GPS collar
            $updateData = [
                'collar_name' => $collar_name,
                'provider' => $provider,
                'serial_number' => $serial_number,
                'model' => $model,
                'frequency' => $frequency,
                'elephant_id' => $elephant_id,
                'elephant_name' => $elephant_name,
                'description' => $description,
                'status' => $status,
                'updated_at' => new MongoDB\BSON\UTCDateTime(),
                'settings' => [
                    'update_frequency' => $update_frequency,
                    'transmission_power' => $transmission_power,
                    'sleep_mode' => $sleep_mode
                ]
            ];
            
            // Update elephant with GPS collar info if changed
            if ($elephant_id !== ($collar['elephant_id'] ?? '')) {
                // Remove GPS collar from previous elephant
                if (!empty($collar['elephant_id'])) {
                    try {
                        $oldObjectId = new MongoDB\BSON\ObjectId($collar['elephant_id']);
                        $elephantCollection->updateOne(
                            ['_id' => $oldObjectId],
                            [
                                '$unset' => [
                                    'gps_collar_id' => '',
                                    'gps_collar_name' => '',
                                    'has_gps_collar' => '',
                                    'last_gps_update' => ''
                                ]
                            ]
                        );
                    } catch (Exception $e) {
                        error_log('Failed to remove GPS collar from previous elephant: ' . $e->getMessage());
                    }
                }
                
                // Add GPS collar to new elephant
                if (!empty($elephant_id)) {
                    try {
                        $newObjectId = new MongoDB\BSON\ObjectId($elephant_id);
                        $elephantCollection->updateOne(
                            ['_id' => $newObjectId],
                            [
                                '$set' => [
                                    'gps_collar_id' => $collar_id,
                                    'gps_collar_name' => $collar_name,
                                    'has_gps_collar' => true,
                                    'last_gps_update' => new MongoDB\BSON\UTCDateTime()
                                ]
                            ]
                        );
                    } catch (Exception $e) {
                        error_log('Failed to update elephant with GPS collar: ' . $e->getMessage());
                    }
                }
            }
            
            $result = $collarCollection->updateOne(
                ['collar_id' => $collar_id],
                ['$set' => $updateData]
            );
            
            if ($result->getModifiedCount() > 0 || $result->getMatchedCount() > 0) {
                $success_message = "GPS collar '$collar_name' updated successfully!";
                
                // Reload collar data
                $collar = $collarCollection->findOne(['collar_id' => $collar_id]);
            } else {
                $error_message = 'No changes were made to the GPS collar.';
            }
            
        } catch (Exception $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Edit GPS Collar</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .edit-form {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 10px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 2rem;
            border: 1px solid var(--border-primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 1rem;
        }
        
        .form-group {
            margin-bottom: 1rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid var(--border-primary);
            border-radius: 5px;
            font-size: 1rem;
            transition: border-color 0.3s;
            background: var(--bg-input);
            color: var(--text-primary);
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(100, 255, 218, 0.1);
        }
        
        .collar-info {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 1px solid var(--border-primary);
        }
        
        .collar-info h3 {
            color: var(--accent-primary);
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--border-primary);
            padding-bottom: 0.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            background: var(--bg-card);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-primary);
        }
        
        .info-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 0.5rem;
        }
        
        .info-value {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .status-badge.active {
            background: rgba(76, 175, 80, 0.2);
            color: var(--accent-success);
        }
        
        .status-badge.inactive {
            background: rgba(244, 67, 54, 0.2);
            color: var(--accent-danger);
        }
        
        .status-badge.maintenance {
            background: rgba(255, 152, 0, 0.2);
            color: var(--accent-warning);
        }
        
        .health-metrics {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            border: 1px solid var(--border-primary);
        }
        
        .health-metrics h3 {
            color: var(--accent-primary);
            margin-bottom: 1rem;
        }
        
        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
        }
        
        .metric-item {
            text-align: center;
            background: var(--bg-card);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-primary);
        }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: 0.5rem;
        }
        
        .metric-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }
        
        .battery-low {
            color: var(--accent-danger);
        }
        
        .battery-medium {
            color: var(--accent-warning);
        }
        
        .battery-good {
            color: var(--accent-success);
        }
        
        .signal-weak {
            color: var(--accent-danger);
        }
        
        .signal-medium {
            color: var(--accent-warning);
        }
        
        .signal-strong {
            color: var(--accent-success);
        }
        
        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid var(--border-primary);
        }
        
        .btn-danger {
            background: var(--accent-danger);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: all 0.3s;
        }
        
        .btn-danger:hover {
            background: #d32f2f;
            transform: translateY(-1px);
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
        
        .checkbox-group label {
            margin: 0;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <!-- Simple Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-logo">🐘 YalaGuard</a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="elephants.php">Elephants</a></li>
                <li><a href="geofencing.php">Geofencing</a></li>
                <li><a href="gps-collar-management.php" class="active">GPS Collars</a></li>
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
        <div class="welcome-card">
            <h1 class="welcome-title">Edit GPS Collar</h1>
            <p class="welcome-subtitle">Modify GPS collar settings and configurations</p>
        </div>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($collar): ?>
            <!-- Current Collar Information -->
            <div class="collar-info">
                <h3>📡 Current Collar Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">Collar ID</div>
                        <div class="info-value"><?php echo htmlspecialchars($collar['collar_id']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Current Status</div>
                        <div class="info-value">
                            <span class="status-badge <?php echo $collar['status']; ?>">
                                <?php echo ucfirst($collar['status']); ?>
                            </span>
                        </div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Provider</div>
                        <div class="info-value"><?php echo ucfirst(htmlspecialchars($collar['provider'] ?? 'Unknown')); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Model</div>
                        <div class="info-value"><?php echo htmlspecialchars($collar['model'] ?? 'Unknown'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Attached Elephant</div>
                        <div class="info-value"><?php echo htmlspecialchars($collar['elephant_name'] ?? 'Not assigned'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">Deployment Date</div>
                        <div class="info-value">
                            <?php echo isset($collar['deployment_date']) ? date('Y-m-d', strtotime($collar['deployment_date'])) : 'Unknown'; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Health Metrics -->
            <div class="health-metrics">
                <h3>💚 Collar Health Status</h3>
                <div class="metrics-grid">
                    <div class="metric-item">
                        <div class="metric-value <?php 
                            $battery = $collar['battery_level'] ?? 100;
                            if ($battery < 20) echo 'battery-low';
                            elseif ($battery < 50) echo 'battery-medium';
                            else echo 'battery-good';
                        ?>">
                            <?php echo $battery; ?>%
                        </div>
                        <div class="metric-label">Battery Level</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value <?php 
                            $signal = $collar['signal_strength'] ?? -50;
                            if ($signal < -80) echo 'signal-weak';
                            elseif ($signal < -60) echo 'signal-medium';
                            else echo 'signal-strong';
                        ?>">
                            <?php echo $signal; ?> dBm
                        </div>
                        <div class="metric-label">Signal Strength</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value">
                            <?php echo $collar['is_online'] ? '🟢' : '🔴'; ?>
                        </div>
                        <div class="metric-label">Online Status</div>
                    </div>
                    <div class="metric-item">
                        <div class="metric-value">
                            <?php echo isset($collar['last_location_update']) ? date('H:i', strtotime($collar['last_location_update'])) : 'Never'; ?>
                        </div>
                        <div class="metric-label">Last Update</div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="edit-form">
                <h2>Edit GPS Collar Settings</h2>
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="collar_name">Collar Name *</label>
                            <input type="text" id="collar_name" name="collar_name" 
                                   value="<?php echo htmlspecialchars($collar['collar_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="provider">GPS Provider *</label>
                            <select id="provider" name="provider" required>
                                <option value="vectronic" <?php echo ($collar['provider'] ?? '') === 'vectronic' ? 'selected' : ''; ?>>Vectronic Aerospace</option>
                                <option value="lotek" <?php echo ($collar['provider'] ?? '') === 'lotek' ? 'selected' : ''; ?>>Lotek</option>
                                <option value="telonics" <?php echo ($collar['provider'] ?? '') === 'telonics' ? 'selected' : ''; ?>>Telonics</option>
                                <option value="followit" <?php echo ($collar['provider'] ?? '') === 'followit' ? 'selected' : ''; ?>>Followit</option>
                                <option value="other" <?php echo ($collar['provider'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="serial_number">Serial Number</label>
                            <input type="text" id="serial_number" name="serial_number" 
                                   value="<?php echo htmlspecialchars($collar['serial_number'] ?? ''); ?>" 
                                   placeholder="e.g., VEC001234, LOT567890">
                        </div>
                        
                        <div class="form-group">
                            <label for="model">Collar Model</label>
                            <input type="text" id="model" name="model" 
                                   value="<?php echo htmlspecialchars($collar['model'] ?? ''); ?>" 
                                   placeholder="e.g., Vectronic Vertex, Lotek Argos">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="frequency">Update Frequency</label>
                            <select id="frequency" name="frequency">
                                <option value="">Select frequency</option>
                                <option value="15min" <?php echo ($collar['frequency'] ?? '') === '15min' ? 'selected' : ''; ?>>Every 15 minutes</option>
                                <option value="30min" <?php echo ($collar['frequency'] ?? '') === '30min' ? 'selected' : ''; ?>>Every 30 minutes</option>
                                <option value="1hour" <?php echo ($collar['frequency'] ?? '') === '1hour' ? 'selected' : ''; ?>>Every hour</option>
                                <option value="4hours" <?php echo ($collar['frequency'] ?? '') === '4hours' ? 'selected' : ''; ?>>Every 4 hours</option>
                                <option value="daily" <?php echo ($collar['frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="status">Status *</label>
                            <select id="status" name="status" required>
                                <option value="active" <?php echo ($collar['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="inactive" <?php echo ($collar['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                <option value="maintenance" <?php echo ($collar['status'] ?? '') === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="elephant_id">Attach to Elephant</label>
                            <select id="elephant_id" name="elephant_id">
                                <option value="">No elephant assigned</option>
                                <?php foreach ($elephants as $elephant): ?>
                                    <?php 
                                    $elephantId = $elephant['_id'] ?? $elephant['elephant_id'] ?? '';
                                    $elephantName = $elephant['elephant_name'] ?? $elephant['name'] ?? 'Unknown';
                                    $isSelected = $elephantId === ($collar['elephant_id'] ?? '');
                                    ?>
                                    <option value="<?php echo htmlspecialchars($elephantId); ?>" <?php echo $isSelected ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($elephantName); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="transmission_power">Transmission Power</label>
                            <select id="transmission_power" name="transmission_power">
                                <option value="low" <?php echo ($collar['settings']['transmission_power'] ?? '') === 'low' ? 'selected' : ''; ?>>Low (Battery Saver)</option>
                                <option value="normal" <?php echo ($collar['settings']['transmission_power'] ?? '') === 'normal' ? 'selected' : ''; ?>>Normal</option>
                                <option value="high" <?php echo ($collar['settings']['transmission_power'] ?? '') === 'high' ? 'selected' : ''; ?>>High (Extended Range)</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="update_frequency">Data Update Frequency</label>
                            <select id="update_frequency" name="update_frequency">
                                <option value="15min" <?php echo ($collar['settings']['update_frequency'] ?? '') === '15min' ? 'selected' : ''; ?>>Every 15 minutes</option>
                                <option value="30min" <?php echo ($collar['settings']['update_frequency'] ?? '') === '30min' ? 'selected' : ''; ?>>Every 30 minutes</option>
                                <option value="1hour" <?php echo ($collar['settings']['update_frequency'] ?? '') === '1hour' ? 'selected' : ''; ?>>Every hour</option>
                                <option value="4hours" <?php echo ($collar['settings']['update_frequency'] ?? '') === '4hours' ? 'selected' : ''; ?>>Every 4 hours</option>
                                <option value="daily" <?php echo ($collar['settings']['update_frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="sleep_mode" name="sleep_mode" 
                                       <?php echo ($collar['settings']['sleep_mode'] ?? false) ? 'checked' : ''; ?>>
                                <label for="sleep_mode">Enable Sleep Mode (Battery Saver)</label>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" rows="3" 
                                  placeholder="Optional: Describe the collar, its purpose, deployment location, or any special notes"><?php echo htmlspecialchars($collar['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                        <a href="gps-collar-management.php" class="btn btn-secondary">← Back to GPS Collars</a>
                        <button type="button" class="btn btn-danger" onclick="deleteCollar()">Delete Collar</button>
                    </div>
                </form>
            </div>

        <?php else: ?>
            <div class="alert alert-error">
                GPS collar not found or you don't have permission to edit it.
                <a href="gps-collar-management.php">← Back to GPS Collars</a>
            </div>
        <?php endif; ?>
        </div>
    </div>

    <script>
        // Delete collar confirmation
        function deleteCollar() {
            if (confirm('Are you sure you want to delete this GPS collar? This action cannot be undone and will remove all location history.')) {
                // Redirect to delete page or show delete form
                if (confirm('This will permanently delete the GPS collar. Continue?')) {
                    window.location.href = `delete-gps-collar.php?id=<?php echo htmlspecialchars($collar_id); ?>`;
                }
            }
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const statusSelect = document.getElementById('status');
            const elephantSelect = document.getElementById('elephant_id');
            
            // Update elephant selection based on status
            statusSelect.addEventListener('change', function() {
                if (this.value === 'inactive') {
                    elephantSelect.value = '';
                    elephantSelect.disabled = true;
                } else {
                    elephantSelect.disabled = false;
                }
            });
            
            // Form submission validation
            form.addEventListener('submit', function(e) {
                const collarName = document.getElementById('collar_name').value.trim();
                const provider = document.getElementById('provider').value;
                const status = document.getElementById('status').value;
                
                if (!collarName) {
                    e.preventDefault();
                    alert('Please enter a collar name.');
                    return false;
                }
                
                if (!provider) {
                    e.preventDefault();
                    alert('Please select a GPS provider.');
                    return false;
                }
                
                if (!status) {
                    e.preventDefault();
                    alert('Please select a status.');
                    return false;
                }
                
                // Confirm elephant detachment if changing
                const currentElephant = '<?php echo $collar['elephant_id'] ?? ''; ?>';
                const newElephant = elephantSelect.value;
                
                if (currentElephant && !newElephant) {
                    if (!confirm('You are removing this GPS collar from an elephant. Are you sure?')) {
                        e.preventDefault();
                        return false;
                    }
                }
            });
        });
    </script>
</body>
</html>
