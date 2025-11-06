<?php
/**
 * YalaGuard - Dashboard
 * 
 * Main dashboard page with overview of system status
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

// Get basic statistics
try {
    require_once '../config/database.php';
    $db = getDatabase();
    
    // Count elephants
    $elephantCollection = $db->selectCollection('elephants');
    $elephantCount = $elephantCollection->countDocuments();
    
    // Count GPS collars
    $collarCollection = $db->selectCollection('gps_collars');
    $collarCount = $collarCollection->countDocuments();
    
    // Count geofences
    $geofenceCollection = $db->selectCollection('geofences');
    $geofenceCount = $geofenceCollection->countDocuments();
    
    // Count active alerts
    $alertCollection = $db->selectCollection('alerts');
    $alertCount = $alertCollection->countDocuments(['status' => 'active']);
    
    // Count authorities
    $authorityCollection = $db->selectCollection('authorities');
    $authorityCount = $authorityCollection->countDocuments(['active' => true]);
    
} catch (Exception $e) {
    $elephantCount = 0;
    $collarCount = 0;
    $geofenceCount = 0;
    $alertCount = 0;
    $authorityCount = 0;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <!-- Simple Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-logo">🐘 YalaGuard</a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php" class="active">Dashboard</a></li>
                <li><a href="elephants.php">Elephants</a></li>
                <li><a href="geofencing.php">Geofencing</a></li>
                <li><a href="gps-collar-management.php">GPS Collars</a></li>
                <li><a href="camera.php">Camera</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle">Alert System</a>
                    <ul class="dropdown-menu">
                        <li><a href="manual-alerts.php">Manual Alert</a></li>
                        <li><a href="authority-management.php">Manage Authorities</a></li>
                        <li><a href="alert-history.php">Alert History</a></li>
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
            <!-- Statistics Overview -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $elephantCount; ?></div>
                    <div class="stat-label">Total Elephants</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $collarCount; ?></div>
                    <div class="stat-label">GPS Collars</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $geofenceCount; ?></div>
                    <div class="stat-label">Active Geofences</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $alertCount; ?></div>
                    <div class="stat-label">Active Alerts</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $authorityCount; ?></div>
                    <div class="stat-label">Alert Authorities</div>
                </div>
            </div>

            <!-- Quick Actions Grid -->
            <div class="dashboard-grid">
                <div class="dashboard-card">
                    <div class="card-icon">🐘</div>
                    <div class="card-title">Manage Elephants</div>
                    <div class="card-description">Add, edit, and monitor elephant information and GPS tracking data.</div>
                    <a href="elephants.php" class="card-btn">Go to Elephants</a>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">🗺️</div>
                    <div class="card-title">Geofencing</div>
                    <div class="card-description">Create and manage virtual boundaries for wildlife protection zones.</div>
                    <a href="geofencing.php" class="card-btn">Go to Geofencing</a>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">📡</div>
                    <div class="card-title">GPS Collars</div>
                    <div class="card-description">Monitor and manage GPS tracking devices attached to elephants.</div>
                    <a href="gps-collar-management.php" class="card-btn">Go to GPS Collars</a>
                </div>
                
                <div class="dashboard-card">
                    <div class="card-icon">📷</div>
                    <div class="card-title">Camera System</div>
                    <div class="card-description">Access and manage wildlife monitoring cameras and recordings.</div>
                    <a href="camera.php" class="card-btn">Go to Cameras</a>
                </div>

                <!-- New Alert System Cards -->
                <div class="dashboard-card alert-card">
                    <div class="card-icon">🚨</div>
                    <div class="card-title">Send Manual Alert</div>
                    <div class="card-description">Send immediate alerts to authorities about elephant incidents or emergencies.</div>
                    <a href="manual-alerts.php" class="card-btn">Send Alert</a>
                </div>
                
                <div class="dashboard-card alert-card">
                    <div class="card-icon">👥</div>
                    <div class="card-title">Manage Authorities</div>
                    <div class="card-description">Add, edit, and configure alert recipients and their notification preferences.</div>
                    <a href="authority-management.php" class="card-btn">Manage Authorities</a>
                </div>
                
                <div class="dashboard-card alert-card">
                    <div class="card-icon">📋</div>
                    <div class="card-title">Alert History</div>
                    <div class="card-description">View and manage all system alerts with detailed filtering and search capabilities.</div>
                    <a href="alert-history.php" class="card-btn">View History</a>
                </div>
            </div>



            <!-- Recent Activity Section -->
            <div class="form-container">
                <div class="form-title">🕒 Recent Activity</div>
                <div class="recent-activity">
                    <div class="activity-item">
                        <span class="activity-time">Just now</span>
                        <span class="activity-text">Dashboard loaded successfully</span>
                    </div>
                    <div class="activity-item">
                        <span class="activity-time">2 minutes ago</span>
                        <span class="activity-text">System status: All systems operational</span>
                    </div>
                    <div class="activity-item">
                        <span class="activity-time">5 minutes ago</span>
                        <span class="activity-text">GPS collar data updated</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function refreshDashboard() {
            location.reload();
        }
        
        // Add some interactivity
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });

            // Add hover effects to dashboard cards
            const dashboardCards = document.querySelectorAll('.dashboard-card');
            dashboardCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 8px 25px rgba(0,0,0,0.15)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = '0 4px 15px rgba(0,0,0,0.1)';
                });
            });
            
            // Ensure dropdown menu works properly
            const dropdowns = document.querySelectorAll('.dropdown');
            dropdowns.forEach(dropdown => {
                const toggle = dropdown.querySelector('.dropdown-toggle');
                const menu = dropdown.querySelector('.dropdown-menu');
                
                // Add click event for mobile/touch devices
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    menu.style.display = menu.style.display === 'block' ? 'none' : 'block';
                });
                
                // Ensure menu is visible on hover for desktop
                dropdown.addEventListener('mouseenter', function() {
                    if (window.innerWidth > 768) {
                        menu.style.display = 'block';
                    }
                });
                
                dropdown.addEventListener('mouseleave', function() {
                    if (window.innerWidth > 768) {
                        menu.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>
