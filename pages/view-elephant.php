<?php
/**
 * YalaGuard View Elephant Page
 */

// Start session
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

$message = '';
$messageType = '';
$elephant = null;

// Check if elephant ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: elephants.php');
    exit();
}

$elephant_id = $_GET['id'];

// Fetch elephant data
try {
    require_once '../config/database.php';
    $collection = getCollection('elephants');
    $elephant = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($elephant_id)]);
    
    if (!$elephant) {
        header('Location: elephants.php');
        exit();
    }
} catch (Exception $e) {
    $message = "Database error: " . $e->getMessage();
    $messageType = 'error';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - View Elephant</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* View Elephant Specific Styles */
        .view-elephant-section {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 2rem;
            border: 1px solid var(--border-primary);
        }
        
        .elephant-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-primary);
        }
        
        .elephant-photo-large {
            width: 200px;
            height: 200px;
            border-radius: 15px;
            object-fit: cover;
            border: 4px solid var(--border-primary);
            box-shadow: var(--shadow-medium);
        }
        
        .elephant-photo-placeholder-large {
            width: 200px;
            height: 200px;
            border-radius: 15px;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 4rem;
            border: 4px solid var(--border-primary);
            box-shadow: var(--shadow-medium);
        }
        
        .elephant-info-header {
            flex: 1;
        }
        
        .elephant-name {
            font-size: 2.5rem;
            color: var(--accent-primary);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .elephant-type {
            font-size: 1.2rem;
            color: var(--text-secondary);
            margin-bottom: 1rem;
            font-weight: 500;
        }
        
        .elephant-status {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-right: 1rem;
        }
        
        .status-gps-connected {
            background: rgba(76, 175, 80, 0.2);
            color: var(--accent-success);
        }
        
        .status-gps-disconnected {
            background: rgba(244, 67, 54, 0.2);
            color: var(--accent-danger);
        }
        
        .status-health-excellent {
            background: rgba(76, 175, 80, 0.2);
            color: var(--accent-success);
        }
        
        .status-health-good {
            background: rgba(33, 150, 243, 0.2);
            color: var(--accent-primary);
        }
        
        .status-health-fair {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
        }
        
        .status-health-poor {
            background: rgba(255, 152, 0, 0.2);
            color: #ff9800;
        }
        
        .status-health-critical {
            background: rgba(244, 67, 54, 0.2);
            color: var(--accent-danger);
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-top: 2rem;
        }
        
        .info-card {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            border: 1px solid var(--border-primary);
        }
        
        .info-card h3 {
            color: var(--accent-primary);
            margin-bottom: 1rem;
            font-size: 1.2rem;
            border-bottom: 1px solid var(--border-primary);
            padding-bottom: 0.5rem;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .info-value {
            color: var(--text-secondary);
            text-align: right;
        }
        
        .gps-info {
            grid-column: 1 / -1;
        }
        
        .gps-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }
        
        .gps-item {
            background: var(--bg-input);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
        }
        
        .gps-item-label {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-transform: uppercase;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .gps-item-value {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .notes-section {
            grid-column: 1 / -1;
        }
        
        .notes-content {
            background: var(--bg-input);
            padding: 1.5rem;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            min-height: 100px;
            white-space: pre-wrap;
            color: var(--text-primary);
            font-family: inherit;
        }
        
        .no-notes {
            color: var(--text-muted);
            font-style: italic;
            text-align: center;
            padding: 2rem;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            justify-content: center;
        }
        
        @media (max-width: 768px) {
            .elephant-header {
                flex-direction: column;
                text-align: center;
            }
            
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
                align-items: center;
            }
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
                <li><a href="elephants.php" class="active">Elephants</a></li>
                <li><a href="geofencing.php">Geofencing</a></li>
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
                <span class="user-info">Welcome, <?php echo htmlspecialchars($user['full_name'] ?? $user['username']); ?></span>
                <a href="../api/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1 class="page-title">View Elephant Details</h1>
            <p class="page-subtitle">Comprehensive information about elephant: <strong><?php echo htmlspecialchars($elephant['name']); ?></strong></p>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <!-- View Elephant Section -->
        <div class="view-elephant-section">
            <!-- Elephant Header with Photo and Basic Info -->
            <div class="elephant-header">
                <!-- Elephant Photo -->
                <div>
                    <?php if (!empty($elephant['photo']) && file_exists('../' . $elephant['photo'])): ?>
                        <img src="../<?php echo htmlspecialchars($elephant['photo']); ?>" 
                             alt="<?php echo htmlspecialchars($elephant['name']); ?>" 
                             class="elephant-photo-large">
                    <?php else: ?>
                        <div class="elephant-photo-placeholder-large">🐘</div>
                    <?php endif; ?>
                </div>
                
                <!-- Elephant Basic Info -->
                <div class="elephant-info-header">
                    <div class="elephant-name"><?php echo htmlspecialchars($elephant['name']); ?></div>
                    <div class="elephant-type"><?php echo htmlspecialchars($elephant['type']); ?></div>
                    
                    <!-- Status Badges -->
                    <div>
                        <span class="elephant-status status-gps-<?php echo isset($elephant['has_gps_collar']) && $elephant['has_gps_collar'] ? 'connected' : 'disconnected'; ?>">
                            GPS: <?php echo isset($elephant['has_gps_collar']) && $elephant['has_gps_collar'] ? 'Connected' : 'Disconnected'; ?>
                        </span>
                        
                        <?php if (isset($elephant['health_status'])): ?>
                            <span class="elephant-status status-health-<?php echo strtolower($elephant['health_status']); ?>">
                                Health: <?php echo htmlspecialchars($elephant['health_status']); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Detailed Information Grid -->
            <div class="info-grid">
                <!-- Basic Information -->
                <div class="info-card">
                    <h3>📋 Basic Information</h3>
                    <div class="info-row">
                        <span class="info-label">Name:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant['name']); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Type:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant['type']); ?></span>
                    </div>
                    <?php if (isset($elephant['gender'])): ?>
                    <div class="info-row">
                        <span class="info-label">Gender:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant['gender']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Height:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant['height'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Weight:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant['weight'] ?? 'N/A'); ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Age:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant['age'] ?? 'N/A'); ?></span>
                    </div>
                </div>
                
                <!-- System Information -->
                <div class="info-card">
                    <h3>⚙️ System Information</h3>
                    <div class="info-row">
                        <span class="info-label">Elephant ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant_id); ?></span>
                    </div>
                    <?php if (isset($elephant['tagId'])): ?>
                    <div class="info-row">
                        <span class="info-label">Tag ID:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant['tagId']); ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value"><?php echo isset($elephant['active']) && $elephant['active'] ? 'Active' : 'Inactive'; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Created:</span>
                        <span class="info-value"><?php echo isset($elephant['created_at']) ? date('M j, Y', strtotime($elephant['created_at'])) : 'N/A'; ?></span>
                    </div>
                    <?php if (isset($elephant['updated_at'])): ?>
                    <div class="info-row">
                        <span class="info-label">Last Updated:</span>
                        <span class="info-value"><?php echo date('M j, Y', strtotime($elephant['updated_at'])); ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (isset($elephant['created_by'])): ?>
                    <div class="info-row">
                        <span class="info-label">Created By:</span>
                        <span class="info-value"><?php echo htmlspecialchars($elephant['created_by']); ?></span>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- GPS Tracking Information -->
                <div class="info-card gps-info">
                    <h3>📡 GPS Tracking System</h3>
                    <?php if (isset($elephant['has_gps_collar']) && $elephant['has_gps_collar']): ?>
                        <div class="gps-grid">
                            <?php if (isset($elephant['gps_collar_id'])): ?>
                            <div class="gps-item">
                                <div class="gps-item-label">Device ID</div>
                                <div class="gps-item-value"><?php echo htmlspecialchars($elephant['gps_collar_id']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($elephant['gps_tracking']['device_type'])): ?>
                            <div class="gps-item">
                                <div class="gps-item-label">Device Type</div>
                                <div class="gps-item-value"><?php echo htmlspecialchars($elephant['gps_tracking']['device_type']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($elephant['gps_tracking']['update_frequency'])): ?>
                            <div class="gps-item">
                                <div class="gps-item-label">Update Frequency</div>
                                <div class="gps-item-value"><?php echo htmlspecialchars($elephant['gps_tracking']['update_frequency']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($elephant['gps_tracking']['battery_life'])): ?>
                            <div class="gps-item">
                                <div class="gps-item-label">Battery Life</div>
                                <div class="gps-item-value"><?php echo htmlspecialchars($elephant['gps_tracking']['battery_life']); ?> days</div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($elephant['gps_tracking']['network_type'])): ?>
                            <div class="gps-item">
                                <div class="gps-item-label">Network Type</div>
                                <div class="gps-item-value"><?php echo htmlspecialchars($elephant['gps_tracking']['network_type']); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($elephant['gps_tracking']['installation_date'])): ?>
                            <div class="gps-item">
                                <div class="gps-item-label">Installation Date</div>
                                <div class="gps-item-value"><?php echo date('M j, Y', strtotime($elephant['gps_tracking']['installation_date'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($elephant['gps_tracking']['last_maintenance'])): ?>
                            <div class="gps-item">
                                <div class="gps-item-label">Last Maintenance</div>
                                <div class="gps-item-value"><?php echo date('M j, Y', strtotime($elephant['gps_tracking']['last_maintenance'])); ?></div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (isset($elephant['gps_tracking']['status'])): ?>
                            <div class="gps-item">
                                <div class="gps-item-label">Status</div>
                                <div class="gps-item-value"><?php echo htmlspecialchars($elephant['gps_tracking']['status']); ?></div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (isset($elephant['gps_tracking']['notes']) && !empty($elephant['gps_tracking']['notes'])): ?>
                        <div style="margin-top: 1rem;">
                            <div class="gps-item-label">GPS Notes:</div>
                            <div class="gps-item-value" style="margin-top: 0.5rem;"><?php echo htmlspecialchars($elephant['gps_tracking']['notes']); ?></div>
                        </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 2rem; color: var(--text-muted);">
                            <div style="font-size: 3rem; margin-bottom: 1rem;">📡</div>
                            <div>No GPS tracking system connected</div>
                            <div style="font-size: 0.9rem; margin-top: 0.5rem;">This elephant is not currently being tracked</div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Notes Section -->
                <div class="info-card notes-section">
                    <h3>📝 Additional Notes</h3>
                    <?php if (isset($elephant['notes']) && !empty($elephant['notes'])): ?>
                        <div class="notes-content"><?php echo htmlspecialchars($elephant['notes']); ?></div>
                    <?php else: ?>
                        <div class="no-notes">No additional notes available for this elephant</div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <button class="btn btn-success btn-large" onclick="editElephant('<?php echo $elephant_id; ?>')">
                    ✏️ Edit Elephant
                </button>
                <button class="btn btn-secondary btn-large" onclick="location.href='elephants.php'">
                    🔙 Back to Elephants
                </button>
                <button class="btn btn-danger btn-large" onclick="deleteElephant('<?php echo $elephant_id; ?>')">
                    🗑️ Delete Elephant
                </button>
            </div>
        </div>
    </div>

    <script>
        function editElephant(id) {
            window.location.href = `edit-elephant.php?id=${id}`;
        }
        
        function deleteElephant(id) {
            if (confirm(`Are you sure you want to delete this elephant?\n\nThis action cannot be undone.`)) {
                window.location.href = `delete-elephant.php?id=${id}`;
            }
        }
    </script>
</body>
</html>
