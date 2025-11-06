<?php
/**
 * YalaGuard Delete Elephant Page
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

// Handle deletion confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_delete'])) {
    try {
        require_once '../config/database.php';
        $collection = getCollection('elephants');
        
        // Get elephant details before deletion
        $elephant = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($elephant_id)]);
        
        if (!$elephant) {
            $message = "Elephant not found.";
            $messageType = 'error';
        } else {
            // Delete the elephant
            $result = $collection->deleteOne(['_id' => new MongoDB\BSON\ObjectId($elephant_id)]);
            
            if ($result->getDeletedCount() > 0) {
                $message = "Elephant '{$elephant['name']}' has been successfully deleted from the system.";
                $messageType = 'success';
                
                // Redirect after 3 seconds
                header("refresh:3;url=elephants.php");
            } else {
                $message = "Failed to delete elephant. Please try again.";
                $messageType = 'error';
            }
        }
    } catch (Exception $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = 'error';
    }
}

// Fetch elephant data for confirmation
if (!$elephant) {
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
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Delete Elephant</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .delete-confirmation {
            background: var(--bg-card);
            padding: 3rem;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 2rem;
            border: 1px solid var(--border-primary);
            text-align: center;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }
        
        .warning-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--accent-danger);
        }
        
        .elephant-details {
            background: var(--bg-secondary);
            padding: 1.5rem;
            border-radius: 10px;
            margin: 2rem 0;
            border: 1px solid var(--border-primary);
            text-align: left;
        }
        
        .elephant-photo {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-primary);
            margin: 0 auto 1rem;
            display: block;
        }
        
        .elephant-photo-placeholder {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 3rem;
            border: 3px solid var(--border-primary);
            margin: 0 auto 1rem;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--border-primary);
        }
        
        .detail-row:last-child {
            border-bottom: none;
        }
        
        .detail-label {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .detail-value {
            color: var(--text-secondary);
        }
        
        .delete-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        
        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
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
            <h1 class="page-title">Delete Elephant</h1>
            <p class="page-subtitle">Confirm deletion of elephant from the system</p>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
                <?php if ($messageType === 'success'): ?>
                    <br><small>Redirecting to elephants page...</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- Delete Confirmation -->
        <div class="delete-confirmation">
            <div class="warning-icon">⚠️</div>
            <h2 style="color: var(--accent-danger); margin-bottom: 1rem;">Delete Elephant Confirmation</h2>
            <p style="color: var(--text-secondary); margin-bottom: 2rem;">
                You are about to permanently delete an elephant from the system. This action cannot be undone.
            </p>
            
            <!-- Elephant Details -->
            <div class="elephant-details">
                <h3 style="color: var(--accent-primary); margin-bottom: 1rem; text-align: center;">
                    Elephant Details
                </h3>
                
                <!-- Elephant Photo -->
                <?php if (!empty($elephant['photo']) && file_exists('../' . $elephant['photo'])): ?>
                    <img src="../<?php echo htmlspecialchars($elephant['photo']); ?>" 
                         alt="<?php echo htmlspecialchars($elephant['name']); ?>" 
                         class="elephant-photo">
                <?php else: ?>
                    <div class="elephant-photo-placeholder">🐘</div>
                <?php endif; ?>
                
                <!-- Basic Information -->
                <div class="detail-row">
                    <span class="detail-label">Name:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($elephant['name']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Type:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($elephant['type']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Height:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($elephant['height']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Weight:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($elephant['weight']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Age:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($elephant['age']); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">Health Status:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($elephant['health_status'] ?? 'Unknown'); ?></span>
                </div>
                
                <div class="detail-row">
                    <span class="detail-label">GPS Connected:</span>
                    <span class="detail-value">
                        <?php echo isset($elephant['gps_connected']) && $elephant['gps_connected'] ? 'Yes' : 'No'; ?>
                    </span>
                </div>
                
                <?php if (!empty($elephant['notes'])): ?>
                <div class="detail-row">
                    <span class="detail-label">Notes:</span>
                    <span class="detail-value"><?php echo htmlspecialchars($elephant['notes']); ?></span>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Warning Message -->
            <div style="background: rgba(244, 67, 54, 0.1); border: 1px solid var(--accent-danger); border-radius: 8px; padding: 1rem; margin: 2rem 0;">
                <h4 style="color: var(--accent-danger); margin-bottom: 0.5rem;">⚠️ Important Warning</h4>
                <p style="color: var(--text-secondary); margin: 0; font-size: 0.9rem;">
                    Deleting this elephant will permanently remove all associated data including:
                    <br>• GPS tracking information
                    <br>• Health records
                    <br>• Movement patterns
                    <br>• Drone connection logs
                    <br><strong>This action cannot be undone!</strong>
                </p>
            </div>
            
            <!-- Action Buttons -->
            <form method="POST" style="margin-top: 2rem;">
                <div class="delete-actions">
                    <button type="submit" name="confirm_delete" class="btn btn-danger btn-large" 
                            onclick="return confirm('Are you absolutely sure you want to delete this elephant? This is your final warning!')">
                                                    Delete Permanently
                    </button>
                    <a href="elephants.php" class="btn btn-secondary btn-large">
                        ❌ Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
