<?php
/**
 * YalaGuard - Alert History
 * 
 * Page to view and manage alert history with filtering and search
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

// Get filter parameters
$status_filter = $_GET['status'] ?? 'all';
$type_filter = $_GET['type'] ?? 'all';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';
$search = $_GET['search'] ?? '';
$page = max(1, intval($_GET['page'] ?? 1));
$per_page = 20;

try {
    require_once '../config/database.php';
    $db = getDatabase();
    
    // Build filter query
    $filter = [];
    
    if ($status_filter !== 'all') {
        $filter['status'] = $status_filter;
    }
    
    if ($type_filter !== 'all') {
        $filter['alert_type'] = $type_filter;
    }
    
    if ($date_from) {
        $filter['created_at'] = ['$gte' => new MongoDB\BSON\UTCDateTime(strtotime($date_from . ' 00:00:00') * 1000)];
    }
    
    if ($date_to) {
        if (isset($filter['created_at'])) {
            $filter['created_at']['$lte'] = new MongoDB\BSON\UTCDateTime(strtotime($date_to . ' 23:59:59') * 1000);
        } else {
            $filter['created_at'] = ['$lte' => new MongoDB\BSON\UTCDateTime(strtotime($date_to . ' 23:59:59') * 1000)];
        }
    }
    
    if ($search) {
        $filter['$or'] = [
            ['message' => ['$regex' => $search, '$options' => 'i']],
            ['alert_type' => ['$regex' => $search, '$options' => 'i']],
            ['elephant_id' => ['$regex' => $search, '$options' => 'i']]
        ];
    }
    
    // Get total count for pagination
    $alertCollection = $db->selectCollection('alerts');
    $total_alerts = $alertCollection->countDocuments($filter);
    $total_pages = ceil($total_alerts / $per_page);
    
    // Get alerts with pagination
    $alerts = $alertCollection->find(
        $filter,
        [
            'sort' => ['created_at' => -1],
            'skip' => ($page - 1) * $per_page,
            'limit' => $per_page
        ]
    )->toArray();
    
    // Get unique alert types for filter dropdown
    $alert_types = $alertCollection->distinct('alert_type');
    
    // Calculate total status counts from entire database
    $total_active_alerts = $alertCollection->countDocuments(['status' => 'active']);
    $total_resolved_alerts = $alertCollection->countDocuments(['status' => 'resolved']);
    $total_pending_alerts = $alertCollection->countDocuments(['status' => 'pending']);
    
    // Calculate status counts for current page (for debug info)
    $activeCount = 0;
    $resolvedCount = 0;
    $pendingCount = 0;
    $unknownCount = 0;
    
    foreach ($alerts as $alert) {
        $status = $alert['status'] ?? 'unknown';
        switch ($status) {
            case 'active':
                $activeCount++;
                break;
            case 'resolved':
                $resolvedCount++;
                break;
            case 'pending':
                $pendingCount++;
                break;
            default:
                $unknownCount++;
                break;
        }
    }
    
} catch (Exception $e) {
    $alerts = [];
    $total_alerts = 0;
    $total_pages = 1;
    $alert_types = [];
    $total_active_alerts = 0;
    $total_resolved_alerts = 0;
    $total_pending_alerts = 0;
    $activeCount = 0;
    $resolvedCount = 0;
    $pendingCount = 0;
    $unknownCount = 0;
    $error_message = 'Error loading alerts: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Alert History</title>
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
                <li><a href="geofencing.php">Geofencing</a></li>
                <li><a href="gps-collar-management.php">GPS Collars</a></li>
                <li><a href="camera.php">Camera</a></li>
                <li class="dropdown">
                    <a href="#" class="dropdown-toggle active">Alert System</a>
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
        <div class="page-header">
            <h1>🚨 Alert History</h1>
            <p>View and manage all system alerts with detailed filtering and search capabilities.</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Summary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_alerts; ?></div>
                <div class="stat-label">Total Alerts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_active_alerts; ?></div>
                <div class="stat-label">Active Alerts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo $total_resolved_alerts; ?></div>
                <div class="stat-label">Resolved Alerts</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($alert_types); ?></div>
                <div class="stat-label">Alert Types</div>
            </div>
        </div>



        <!-- Filters -->
        <div class="form-container compact-filters">
            <div class="form-title">🔍 Filter Alerts</div>
            <form method="GET" action="">
                <div class="form-row">
                    <div class="form-group">
                        <label for="status">Status:</label>
                        <select name="status" id="status">
                            <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="resolved" <?php echo $status_filter === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="type">Alert Type:</label>
                        <select name="type" id="type">
                            <option value="all" <?php echo $type_filter === 'all' ? 'selected' : ''; ?>>All Types</option>
                            <?php foreach ($alert_types as $type): ?>
                                <option value="<?php echo htmlspecialchars($type); ?>" <?php echo $type_filter === $type ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($type); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="date_from">From Date:</label>
                        <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($date_from); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_to">To Date:</label>
                        <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($date_to); ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="search">Search:</label>
                        <input type="text" name="search" id="search" placeholder="Search alerts..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="alert-history.php" class="btn btn-secondary">Clear All</a>
                </div>
            </form>
        </div>

        <!-- Alerts Table -->
        <?php if (empty($alerts)): ?>
            <div class="form-container">
                <div class="form-title">📭 No Alerts Found</div>
                <p style="text-align: center; color: var(--text-muted);">No alerts match the current filters. Try adjusting your search criteria.</p>
            </div>
        <?php else: ?>
            <div class="table-container">
                <div class="table-title">🚨 Alert History</div>
                <table>
                    <thead>
                        <tr>
                            <th>Date/Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Message</th>
                            <th>Elephant ID</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alerts as $alert): ?>
                            <tr>
                                <td>
                                    <?php 
                                    if (isset($alert['created_at'])) {
                                        $date = $alert['created_at'] instanceof MongoDB\BSON\UTCDateTime 
                                            ? $alert['created_at']->toDateTime() 
                                            : new DateTime($alert['created_at']);
                                        echo $date->format('M j, Y g:i A');
                                    } else {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($alert['alert_type'] ?? 'Unknown'); ?></td>
                                <td>
                                    <?php 
                                    $status = $alert['status'] ?? 'unknown';
                                    $statusClass = 'status-' . $status;
                                    $statusText = ucfirst($status);
                                    ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($alert['message'] ?? 'No message'); ?></td>
                                <td><?php echo htmlspecialchars($alert['elephant_id'] ?? 'N/A'); ?></td>
                                <td>
                                    <div class="action-buttons">
                                        <button class="btn-small btn-view" onclick="viewAlertDetails('<?php echo $alert['_id']; ?>')">View</button>
                                        <?php if (($alert['status'] ?? '') === 'active'): ?>
                                            <button class="btn-small btn-edit" onclick="resolveAlert('<?php echo $alert['_id']; ?>')">Resolve</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>" class="btn btn-secondary">&laquo; Previous</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="btn btn-primary"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="btn btn-secondary"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $total_pages): ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>" class="btn btn-secondary">Next &raquo;</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Alert Details Modal -->
    <div id="alertModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>🚨 Alert Details</h2>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be loaded here -->
            </div>
        </div>
    </div>

    <script>
        function viewAlertDetails(alertId) {
            // Show loading state
            document.getElementById('modalBody').innerHTML = '<div style="text-align: center; padding: 20px;"><div class="loading-spinner"></div><p>Loading alert details...</p></div>';
            document.getElementById('alertModal').style.display = 'block';
            
            // Fetch alert details via AJAX
            fetch(`../api/get-alert-details.php?id=${alertId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAlertDetails(data.alert);
                    } else {
                        document.getElementById('modalBody').innerHTML = `
                            <div style="text-align: center; padding: 20px; color: var(--accent-danger);">
                                <h3>Error Loading Alert</h3>
                                <p>${data.message || 'Failed to load alert details'}</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('modalBody').innerHTML = `
                        <div style="text-align: center; padding: 20px; color: var(--accent-danger);">
                            <h3>Network Error</h3>
                            <p>Failed to connect to server. Please try again.</p>
                        </div>
                    `;
                });
        }
        
        function displayAlertDetails(alert) {
            const modalBody = document.getElementById('modalBody');
            
            const statusClass = `status-${alert.status || 'unknown'}`;
            const statusText = (alert.status || 'unknown').charAt(0).toUpperCase() + (alert.status || 'unknown').slice(1);
            
            let dateText = 'Unknown';
            if (alert.created_at) {
                try {
                    if (alert.created_at instanceof Date) {
                        dateText = alert.created_at.toLocaleString();
                    } else if (typeof alert.created_at === 'string') {
                        dateText = new Date(alert.created_at).toLocaleString();
                    } else if (alert.created_at.$date) {
                        dateText = new Date(alert.created_at.$date).toLocaleString();
                    }
                } catch (e) {
                    dateText = 'Invalid Date';
                }
            }
            
            modalBody.innerHTML = `
                <div class="alert-details">
                    <div class="detail-section">
                        <h3>📋 Basic Information</h3>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Alert ID:</label>
                                <span>${alert._id || 'N/A'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Status:</label>
                                <span class="${statusClass}">${statusText}</span>
                            </div>
                            <div class="detail-item">
                                <label>Type:</label>
                                <span>${alert.alert_type || 'Unknown'}</span>
                            </div>
                            <div class="detail-item">
                                <label>Created:</label>
                                <span>${dateText}</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="detail-section">
                        <h3>📝 Message</h3>
                        <div class="message-content">
                            ${alert.message || 'No message provided'}
                        </div>
                    </div>
                    
                                         <div class="detail-section">
                         <h3>🐘 Elephant Information</h3>
                         <div class="detail-grid">
                             <div class="detail-item">
                                 <label>Elephant ID:</label>
                                 <span>${alert.elephant_id || 'N/A'}</span>
                             </div>
                             <div class="detail-item">
                                 <label>Elephant Name:</label>
                                 <span>${alert.elephant_name || 'N/A'}</span>
                             </div>
                             
                             <div class="detail-item">
                                 <label>Age:</label>
                                 <span>${alert.elephant_age || 'N/A'}</span>
                             </div>
                             <div class="detail-item">
                                 <label>Gender:</label>
                                 <span>${alert.elephant_gender || 'N/A'}</span>
                             </div>
                         </div>
                     </div>
                     
                     
                    
                    
                    
                                         <div class="detail-section">
                         <h3>🔧 Actions</h3>
                         <div class="action-buttons">
                             ${alert.status === 'active' ? '<button class="btn btn-primary" onclick="resolveAlert(\'' + alert._id + '\')">Resolve Alert</button>' : ''}
                             <button class="btn btn-secondary" onclick="closeModal()">Close</button>
                         </div>
                     </div>
                     
                     
                </div>
            `;
        }
        
        function closeModal() {
            document.getElementById('alertModal').style.display = 'none';
        }
        
        function resolveAlert(alertId) {
            if (confirm('Are you sure you want to mark this alert as resolved?')) {
                // This would make an AJAX call to update the alert status
                alert('Alert resolved! This would update the database and notify relevant authorities.');
                // Optionally reload the page to show updated status
                // location.reload();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('alertModal');
            if (event.target === modal) {
                closeModal();
            }
        }
        
        // Auto-submit form when filters change (optional)
        document.getElementById('status').addEventListener('change', function() {
            this.form.submit();
        });
        
        document.getElementById('type').addEventListener('change', function() {
            this.form.submit();
        });
    </script>
</body>
</html>
