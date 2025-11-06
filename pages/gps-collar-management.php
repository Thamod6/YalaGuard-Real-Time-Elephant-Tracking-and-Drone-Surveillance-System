<?php
/**
 * YalaGuard - GPS Collar Management
 * 
 * This page manages professional GPS collars for elephant tracking
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $collar_id = trim($_POST['collar_id'] ?? '');
    $collar_name = trim($_POST['collar_name'] ?? '');
    $provider = trim($_POST['provider'] ?? '');
    $elephant_id = trim($_POST['elephant_id'] ?? '');
    $serial_number = trim($_POST['serial_number'] ?? '');
    $model = trim($_POST['model'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (empty($collar_id)) {
        $error_message = 'Collar ID is required.';
    } elseif (empty($collar_name)) {
        $error_message = 'Collar name is required.';
    } elseif (empty($provider)) {
        $error_message = 'GPS provider is required.';
    } elseif (empty($elephant_id)) {
        $error_message = 'Please select an elephant to attach the collar to.';
    } else {
        try {
            require_once '../config/database.php';
            $db = getDatabase();
            $collarCollection = $db->selectCollection('gps_collars');
            $elephantCollection = $db->selectCollection('elephants');
            
            // Check if elephant exists
            $elephant = null;
            try {
                $objectId = new MongoDB\BSON\ObjectId($elephant_id);
                $elephant = $elephantCollection->findOne(['_id' => $objectId]);
            } catch (Exception $e) {
                $elephant = $elephantCollection->findOne(['elephant_id' => $elephant_id]);
            }
            
            if (!$elephant) {
                $error_message = 'Selected elephant not found. ID: ' . $elephant_id;
            } else {
                // Check if collar ID already exists
                $existingCollar = $collarCollection->findOne(['collar_id' => $collar_id]);
                if ($existingCollar) {
                    $error_message = 'GPS Collar with this ID already exists.';
                } else {
                    // Create GPS collar document
                    $gpsCollar = [
                        'collar_id' => $collar_id,
                        'collar_name' => $collar_name,
                        'provider' => $provider,
                        'serial_number' => $serial_number,
                        'model' => $model,
                        'frequency' => $frequency,
                        'elephant_id' => $elephant_id,
                        'elephant_name' => $elephant['elephant_name'] ?? $elephant['name'] ?? 'Unknown Elephant',
                        'status' => 'active',
                        'deployment_date' => new MongoDB\BSON\UTCDateTime(),
                        'created_by' => $_SESSION['user_id'],
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime(),
                        'last_location_update' => new MongoDB\BSON\UTCDateTime(),
                        'battery_level' => 100,
                        'signal_strength' => -50,
                        'is_online' => true,
                        'location_history' => [],
                        'health_metrics' => [
                            'temperature' => null,
                            'movement_status' => 'unknown',
                            'activity_level' => 'unknown'
                        ],
                        'settings' => [
                            'update_frequency' => $frequency,
                            'transmission_power' => 'normal',
                            'sleep_mode' => false
                        ]
                    ];
                    
                    $result = $collarCollection->insertOne($gpsCollar);
                    
                    if ($result->getInsertedCount() > 0) {
                        // Update elephant with GPS collar info
                        try {
                            $objectId = new MongoDB\BSON\ObjectId($elephant_id);
                            $updateResult = $elephantCollection->updateOne(
                                ['_id' => $objectId],
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
                            error_log('Failed to update elephant: ' . $e->getMessage());
                        }
                        
                        $elephantName = $elephant['elephant_name'] ?? $elephant['name'] ?? 'Unknown Elephant';
                        $success_message = "GPS Collar '$collar_name' added successfully and attached to elephant '$elephantName' with ID: $collar_id";
                        
                        // Clear form data
                        $_POST = [];
                    } else {
                        $error_message = 'Failed to add GPS collar. Please try again.';
                    }
                }
            }
            
        } catch (Exception $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get list of elephants for dropdown
try {
    require_once '../config/database.php';
    $db = getDatabase();
    $elephantCollection = $db->selectCollection('elephants');
    
    // Try different status fields that might exist
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
    
} catch (Exception $e) {
    $elephants = [];
    $error_message = 'Failed to load elephants: ' . $e->getMessage();
}

// Get existing GPS collars
try {
    $collarCollection = $db->selectCollection('gps_collars');
    $existingCollars = $collarCollection->find([], ['sort' => ['created_at' => -1]])->toArray();
} catch (Exception $e) {
    $existingCollars = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - GPS Collar Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .collar-form {
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
        
        .collar-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        
        .collar-card {
            background: var(--bg-card);
            border-radius: 10px;
            padding: 1.5rem;
            box-shadow: var(--shadow-medium);
            border-left: 4px solid var(--accent-primary);
            border: 1px solid var(--border-primary);
            position: relative;
        }
        
        .collar-card.active {
            border-left-color: var(--accent-success);
        }
        
        .collar-card.inactive {
            border-left-color: var(--accent-danger);
        }
        
        .collar-card.maintenance {
            border-left-color: var(--accent-warning);
        }
        
        .collar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .collar-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .collar-status {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .collar-status.active {
            background: rgba(76, 175, 80, 0.2);
            color: var(--accent-success);
        }
        
        .collar-status.inactive {
            background: rgba(244, 67, 54, 0.2);
            color: var(--accent-danger);
        }
        
        .collar-status.maintenance {
            background: rgba(255, 152, 0, 0.2);
            color: var(--accent-warning);
        }
        
        .collar-details {
            margin-bottom: 1rem;
        }
        
        .collar-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }
        
        .collar-detail .label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .collar-detail .value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .collar-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .btn-small {
            padding: 0.5rem 1rem;
            font-size: 0.8rem;
            border-radius: 5px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
            transition: all 0.3s;
            border: none;
            cursor: pointer;
        }
        
        .btn-edit {
            background: var(--accent-info);
            color: white;
        }
        
        .btn-edit:hover {
            background: #1976d2;
            transform: translateY(-1px);
        }
        
        .btn-delete {
            background: var(--accent-danger);
            color: white;
        }
        
        .btn-delete:hover {
            background: #d32f2f;
            transform: translateY(-1px);
        }
        
        .btn-test {
            background: var(--accent-success);
            color: white;
        }
        
        .btn-test:hover {
            background: #388e3c;
            transform: translateY(-1px);
        }
        
        .provider-info {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1rem 0;
            border: 1px solid var(--border-primary);
        }
        
        .provider-info h4 {
            color: var(--accent-primary);
            margin-bottom: 1rem;
        }
        
        .provider-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
        }
        
        .provider-card {
            background: var(--bg-card);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-primary);
        }
        
        .provider-name {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .provider-features {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .elephant-selection {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 1rem 0;
            border: 1px solid var(--border-primary);
        }
        
        .elephant-selection h3 {
            color: var(--accent-primary);
            margin-bottom: 1rem;
            border-bottom: 2px solid var(--border-primary);
            padding-bottom: 0.5rem;
        }
        
        .elephant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        
        .elephant-card {
            background: var(--bg-card);
            padding: 1rem;
            border-radius: 8px;
            border: 2px solid var(--border-primary);
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .elephant-card:hover {
            border-color: var(--accent-primary);
            transform: translateY(-2px);
        }
        
        .elephant-card.selected {
            border-color: var(--accent-success);
            background: rgba(76, 175, 80, 0.1);
            box-shadow: 0 0 20px rgba(76, 175, 80, 0.3);
            transform: scale(1.02);
        }
        
        .elephant-card.selected::before {
            content: '✓';
            position: absolute;
            top: -10px;
            right: -10px;
            background: var(--accent-success);
            color: white;
            width: 25px;
            height: 25px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }
        
        .elephant-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
        }
        
        .elephant-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .elephant-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 12px;
            font-size: 0.8rem;
            font-weight: 600;
            margin-top: 0.5rem;
        }
        
        .elephant-status.active {
            background: rgba(76, 175, 80, 0.2);
            color: var(--accent-success);
        }
        
        .elephant-status.inactive {
            background: rgba(158, 158, 158, 0.2);
            color: var(--text-muted);
        }
        
        .elephant-status.has-collar {
            background: rgba(33, 150, 243, 0.2);
            color: var(--accent-info);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: var(--bg-card);
            padding: 1.5rem;
            border-radius: 10px;
            text-align: center;
            border: 1px solid var(--border-primary);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-primary);
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
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
            <h1 class="welcome-title">🐘 GPS Collar Management</h1>
            <p class="welcome-subtitle">Manage professional GPS collars for elephant tracking and monitoring</p>
        </div>

        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($existingCollars); ?></div>
                <div class="stat-label">Active GPS Collars</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($existingCollars, function($c) { return $c['status'] === 'active'; })); ?></div>
                <div class="stat-label">Online Collars</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($existingCollars, function($c) { return isset($c['elephant_id']); })); ?></div>
                <div class="stat-label">Attached to Elephants</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($elephants); ?></div>
                <div class="stat-label">Total Elephants</div>
            </div>
        </div>

        <!-- GPS Provider Information -->
        <div class="provider-info">
            <h4>📡 Supported GPS Providers</h4>
            <div class="provider-grid">
                <div class="provider-card">
                    <div class="provider-name">Vectronic Aerospace</div>
                    <div class="provider-features">
                        • Professional wildlife tracking<br>
                        • Satellite coverage<br>
                        • 1-3 year battery life<br>
                        • High accuracy (3-5m)
                    </div>
                </div>
                <div class="provider-card">
                    <div class="provider-name">Lotek</div>
                    <div class="provider-features">
                        • Wildlife research focused<br>
                        • Multiple frequency options<br>
                        • Rugged construction<br>
                        • Long battery life
                    </div>
                </div>
                <div class="provider-card">
                    <div class="provider-name">Telonics</div>
                    <div class="provider-features">
                        • Advanced telemetry<br>
                        • Configurable updates<br>
                        • Multiple sensors<br>
                        • Professional grade
                    </div>
                </div>
                <div class="provider-card">
                    <div class="provider-name">Followit</div>
                    <div class="provider-features">
                        • Cellular-based tracking<br>
                        • Cost-effective solution<br>
                        • Easy integration<br>
                        • Good coverage
                    </div>
                </div>
            </div>
        </div>

        <!-- Add New GPS Collar Form -->
        <div class="collar-form">
            <h2>➕ Add New GPS Collar</h2>
            <form method="POST" action="" id="gpsCollarForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="collar_id">Collar ID *</label>
                        <input type="text" id="collar_id" name="collar_id" 
                               value="<?php echo htmlspecialchars($_POST['collar_id'] ?? ''); ?>" 
                               placeholder="e.g., COLLAR_001, GPS_001" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="collar_name">Collar Name *</label>
                        <input type="text" id="collar_name" name="collar_name" 
                               value="<?php echo htmlspecialchars($_POST['collar_name'] ?? ''); ?>" 
                               placeholder="e.g., Raja's Collar, Alpha GPS" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="provider">GPS Provider *</label>
                        <select id="provider" name="provider" required>
                            <option value="">Select GPS provider</option>
                            <option value="vectronic" <?php echo ($_POST['provider'] ?? '') === 'vectronic' ? 'selected' : ''; ?>>Vectronic Aerospace</option>
                            <option value="lotek" <?php echo ($_POST['provider'] ?? '') === 'lotek' ? 'selected' : ''; ?>>Lotek</option>
                            <option value="telonics" <?php echo ($_POST['provider'] ?? '') === 'telonics' ? 'selected' : ''; ?>>Telonics</option>
                            <option value="followit" <?php echo ($_POST['provider'] ?? '') === 'followit' ? 'selected' : ''; ?>>Followit</option>
                            <option value="other" <?php echo ($_POST['provider'] ?? '') === 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="serial_number">Serial Number</label>
                        <input type="text" id="serial_number" name="serial_number" 
                               value="<?php echo htmlspecialchars($_POST['serial_number'] ?? ''); ?>" 
                               placeholder="e.g., VEC001234, LOT567890">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="model">Collar Model</label>
                        <input type="text" id="model" name="model" 
                               value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>" 
                               placeholder="e.g., Vectronic Vertex, Lotek Argos">
                    </div>
                    
                    <div class="form-group">
                        <label for="frequency">Update Frequency</label>
                        <select id="frequency" name="frequency">
                            <option value="">Select frequency</option>
                            <option value="15min" <?php echo ($_POST['frequency'] ?? '') === '15min' ? 'selected' : ''; ?>>Every 15 minutes</option>
                            <option value="30min" <?php echo ($_POST['frequency'] ?? '') === '30min' ? 'selected' : ''; ?>>Every 30 minutes</option>
                            <option value="1hour" <?php echo ($_POST['frequency'] ?? '') === '1hour' ? 'selected' : ''; ?>>Every hour</option>
                            <option value="4hours" <?php echo ($_POST['frequency'] ?? '') === '4hours' ? 'selected' : ''; ?>>Every 4 hours</option>
                            <option value="daily" <?php echo ($_POST['frequency'] ?? '') === 'daily' ? 'selected' : ''; ?>>Daily</option>
                        </select>
                    </div>
                </div>

                <!-- Elephant Selection -->
                <div class="elephant-selection">
                    <h3>🐘 Select Elephant to Attach GPS Collar</h3>
                    <p>Choose which elephant this GPS collar will track:</p>
                    
                    <?php if (empty($elephants)): ?>
                        <div class="no-elephants" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            <h4>🐘 No Elephants Found</h4>
                            <p>No elephants are available in the database.</p>
                        </div>
                    <?php else: ?>
                        <div class="elephant-grid">
                            <?php foreach ($elephants as $elephant): ?>
                                <div class="elephant-card" data-elephant-id="<?php echo htmlspecialchars($elephant['_id'] ?? $elephant['elephant_id'] ?? ''); ?>">
                                    <div class="elephant-name"><?php echo htmlspecialchars($elephant['elephant_name'] ?? $elephant['name'] ?? 'Unknown'); ?></div>
                                    <div class="elephant-details">
                                        ID: <?php echo htmlspecialchars($elephant['_id'] ?? $elephant['elephant_id'] ?? 'Unknown'); ?><br>
                                        Age: <?php echo $elephant['age'] ?? 'Unknown'; ?> years<br>
                                        Gender: <?php echo ucfirst($elephant['gender'] ?? 'Unknown'); ?>
                                    </div>
                                    <div class="elephant-status <?php echo isset($elephant['has_gps_collar']) && $elephant['has_gps_collar'] ? 'has-collar' : 'active'; ?>">
                                        <?php echo isset($elephant['has_gps_collar']) && $elephant['has_gps_collar'] ? 'Has GPS Collar' : 'Available'; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <input type="hidden" id="elephant_id" name="elephant_id" value="" required>
                    <div id="selection-debug" style="margin-top: 1rem; padding: 0.5rem; background: var(--bg-input); border-radius: 5px; font-size: 0.9rem; color: var(--text-secondary);">
                        <strong>Debug:</strong> No elephant selected yet
                    </div>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea id="description" name="description" rows="3" 
                              placeholder="Optional: Describe the collar, its purpose, deployment location, or any special notes"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">🐘 Add GPS Collar & Attach to Elephant</button>
                    <a href="geofencing.php" class="btn btn-secondary">← Back to Geofencing</a>
                </div>
            </form>
        </div>

        <!-- Existing GPS Collars -->
        <div id="existingCollars">
            <h2>📡 Active GPS Collars</h2>
            <div id="collarsContainer">
                <?php if (empty($existingCollars)): ?>
                    <div class="empty-state" style="text-align: center; padding: 3rem; color: var(--text-muted);">
                        <h3>📡 No GPS Collars Found</h3>
                        <p>Add your first GPS collar to start tracking elephants.</p>
                    </div>
                <?php else: ?>
                    <div class="collar-grid">
                        <?php foreach ($existingCollars as $collar): ?>
                            <div class="collar-card <?php echo $collar['status']; ?>">
                                <div class="collar-header">
                                    <div class="collar-name"><?php echo htmlspecialchars($collar['collar_name']); ?></div>
                                    <div class="collar-status <?php echo $collar['status']; ?>"><?php echo ucfirst($collar['status']); ?></div>
                                </div>
                                
                                <div class="collar-details">
                                    <div class="collar-detail">
                                        <span class="label">Collar ID:</span>
                                        <span class="value"><?php echo htmlspecialchars($collar['collar_id']); ?></span>
                                    </div>
                                    <div class="collar-detail">
                                        <span class="label">Provider:</span>
                                        <span class="value"><?php echo ucfirst(htmlspecialchars($collar['provider'])); ?></span>
                                    </div>
                                    <div class="collar-detail">
                                        <span class="label">Elephant:</span>
                                        <span class="value"><?php echo htmlspecialchars($collar['elephant_name'] ?? 'Not assigned'); ?></span>
                                    </div>
                                    <div class="collar-detail">
                                        <span class="label">Model:</span>
                                        <span class="value"><?php echo htmlspecialchars($collar['model'] ?? 'Unknown'); ?></span>
                                    </div>
                                    <div class="collar-detail">
                                        <span class="label">Frequency:</span>
                                        <span class="value"><?php echo htmlspecialchars($collar['frequency'] ?? 'Unknown'); ?></span>
                                    </div>
                                    <div class="collar-detail">
                                        <span class="label">Battery:</span>
                                        <span class="value"><?php echo ($collar['battery_level'] ?? 'Unknown'); ?>%</span>
                                    </div>
                                    <div class="collar-detail">
                                        <span class="label">Last Update:</span>
                                        <span class="value"><?php echo isset($collar['last_location_update']) ? date('Y-m-d H:i', strtotime($collar['last_location_update'])) : 'Never'; ?></span>
                                    </div>
                                </div>
                                
                                <div class="collar-actions">
                                    <button class="btn-small btn-test" onclick="testCollar('<?php echo $collar['collar_id']; ?>')">Test</button>
                                    <a href="edit-gps-collar.php?id=<?php echo $collar['collar_id']; ?>" class="btn-small btn-edit">Edit</a>
                                    <button class="btn-small btn-delete" onclick="deleteCollar('<?php echo $collar['collar_id']; ?>')">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        </div>
    </div>

    <script>
        // Elephant selection
        document.addEventListener('DOMContentLoaded', function() {
            const elephantCards = document.querySelectorAll('.elephant-card');
            const hiddenInput = document.getElementById('elephant_id');
            
            elephantCards.forEach(card => {
                card.addEventListener('click', function() {
                    // Remove previous selection
                    document.querySelectorAll('.elephant-card').forEach(c => c.classList.remove('selected'));
                    
                    // Select this card
                    this.classList.add('selected');
                    
                    // Set the hidden input value
                    const elephantId = this.dataset.elephantId;
                    if (hiddenInput) {
                        hiddenInput.value = elephantId;
                        updateElephantSelection();
                    }
                });
            });
        });
        
        // Form validation
        document.getElementById('gpsCollarForm').addEventListener('submit', function(e) {
            const elephantId = document.getElementById('elephant_id').value;
            const selectedCard = document.querySelector('.elephant-card.selected');
            
            if (!elephantId) {
                e.preventDefault();
                alert('Please select an elephant to attach the GPS collar to.');
                return false;
            }
            
            // Show confirmation
            if (selectedCard) {
                const elephantName = selectedCard.querySelector('.elephant-name').textContent;
                if (!confirm(`Are you sure you want to attach this GPS collar to elephant: ${elephantName}?`)) {
                    e.preventDefault();
                    return false;
                }
            }
        });
        
        // Add visual feedback when elephant is selected
        function updateElephantSelection() {
            const selectedCard = document.querySelector('.elephant-card.selected');
            const submitBtn = document.querySelector('button[type="submit"]');
            const debugDiv = document.getElementById('selection-debug');
            
            if (selectedCard && submitBtn) {
                const elephantName = selectedCard.querySelector('.elephant-name').textContent;
                const elephantId = selectedCard.dataset.elephantId;
                submitBtn.textContent = `🐘 Add GPS Collar & Attach to ${elephantName}`;
                submitBtn.disabled = false;
                
                // Update debug display
                if (debugDiv) {
                    debugDiv.innerHTML = `<strong>Selected:</strong> ${elephantName} (ID: ${elephantId})`;
                    debugDiv.style.background = 'rgba(76, 175, 80, 0.1)';
                    debugDiv.style.color = 'var(--accent-success)';
                }
            } else {
                submitBtn.textContent = '🐘 Add GPS Collar & Attach to Elephant';
                submitBtn.disabled = true;
                
                // Update debug display
                if (debugDiv) {
                    debugDiv.innerHTML = '<strong>Debug:</strong> No elephant selected yet';
                    debugDiv.style.background = 'var(--bg-input)';
                    debugDiv.style.color = 'var(--text-secondary)';
                }
            }
        }
        
        // Update button text when elephant is selected
        document.addEventListener('click', function(e) {
            if (e.target.closest('.elephant-card')) {
                setTimeout(updateElephantSelection, 100);
            }
        });
        
        // GPS Collar management functions
        function testCollar(collarId) {
            alert(`Testing GPS collar: ${collarId}\n\nThis would test the collar's connection and get current location.`);
        }
        
        function deleteCollar(collarId) {
            if (confirm(`Are you sure you want to delete GPS collar: ${collarId}?\n\nThis action cannot be undone.`)) {
                alert(`GPS collar ${collarId} would be deleted.\n\nIn a real implementation, this would make an API call to remove the collar.`);
            }
        }
    </script>
</body>
</html>
