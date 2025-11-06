<?php
/**
 * YalaGuard Elephant Management System
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

 // Fetch elephants from MongoDB database
 try {
     require_once '../config/database.php';
     $collection = getCollection('elephants');
     
     // Get all elephants, sorted by creation date (newest first)
     $cursor = $collection->find([], ['sort' => ['created_at' => -1]]);
     $elephants = [];
     
     foreach ($cursor as $elephant) {
         $elephants[] = [
             'id' => (string)$elephant['_id'], // Convert ObjectId to string
             'photo' => isset($elephant['photo']) ? '../' . $elephant['photo'] : '',
             'name' => $elephant['name'],
             'type' => $elephant['type'],
             'height' => $elephant['height'],
             'weight' => $elephant['weight'],
             'gps_connected' => isset($elephant['gps_connected']) ? $elephant['gps_connected'] : false,
             'age' => $elephant['age'],
             'health_status' => isset($elephant['health_status']) ? $elephant['health_status'] : 'Unknown',
             'notes' => isset($elephant['notes']) ? $elephant['notes'] : ''
         ];
     }
 } catch (Exception $e) {
     $elephants = [];
     $error_message = "Database error: " . $e->getMessage();
 }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Elephant Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Elephant Page Specific Styles */
        .elephant-section {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 2rem;
            border: 1px solid var(--border-primary);
        }
        
        .elephant-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: var(--bg-card);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: var(--shadow-medium);
        }
        
        .elephant-table th {
            background: var(--bg-secondary);
            color: var(--accent-primary);
            padding: 15px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        .elephant-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-primary);
            vertical-align: middle;
            color: var(--text-secondary);
        }
        
        .elephant-table tr:hover {
            background: var(--bg-hover);
        }
        
        .elephant-photo {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--border-primary);
        }
        
        .elephant-photo-placeholder {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--bg-secondary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 2rem;
            border: 3px solid var(--border-primary);
        }
        
        .drone-status {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .drone-connected {
            background: rgba(76, 175, 80, 0.2);
            color: var(--accent-success);
        }
        
        .drone-disconnected {
            background: rgba(244, 67, 54, 0.2);
            color: var(--accent-danger);
        }
        
        .elephant-actions {
            display: flex;
            gap: 8px;
        }
        
        .speed-function {
            background: var(--bg-secondary);
            border: 1px solid var(--border-primary);
            border-radius: 10px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        
        .speed-function h4 {
            color: var(--text-primary);
            margin-bottom: 1rem;
        }
        
        .speed-function p {
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }
        
        .speed-function .file-info {
            background: var(--bg-input);
            padding: 10px;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.9rem;
            color: var(--text-primary);
        }
        
        .add-elephant-btn {
            margin-bottom: 1rem;
        }
        
        .search-filter {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            align-items: center;
        }
        
        .search-input {
            padding: 10px;
            border: 2px solid var(--border-primary);
            border-radius: 6px;
            font-size: 0.9rem;
            min-width: 200px;
            background: var(--bg-input);
            color: var(--text-primary);
        }
        
        .filter-select {
            padding: 10px;
            border: 2px solid var(--border-primary);
            border-radius: 6px;
            font-size: 0.9rem;
            background: var(--bg-input);
            color: var(--text-primary);
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
        <h1 class="page-title">🐘 Elephant Management System</h1>
        
        <!-- Elephant Management Section -->
        <div class="elephant-section">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h2>Elephant Database</h2>
                <button class="btn btn-primary add-elephant-btn" onclick="location.href='add-elephant.php'">
                    ➕ Add New Elephant
                </button>
            </div>
            
            <!-- Search and Filter -->
            <div class="search-filter">
                <input 
                    type="text" 
                    id="searchInput" 
                    class="search-input" 
                    placeholder="Search elephants..."
                    onkeyup="filterElephants()"
                >
                <select id="typeFilter" class="filter-select" onchange="filterElephants()">
                    <option value="">All Types</option>
                    <option value="Asian Elephant">Asian Elephant</option>
                    <option value="African Bush Elephant">African Bush Elephant</option>
                    <option value="African Forest Elephant">African Forest Elephant</option>
                </select>
                                 <select id="gpsFilter" class="filter-select" onchange="filterElephants()">
                     <option value="">All GPS Status</option>
                     <option value="connected">Connected</option>
                     <option value="disconnected">Disconnected</option>
                 </select>
            </div>
            
            <!-- Elephant Table -->
            <div style="overflow-x: auto;">
                <table class="elephant-table" id="elephantTable">
                    <thead>
                                                 <tr>
                             <th>Photo</th>
                             <th>Name</th>
                             <th>Type</th>
                             <th>Height</th>
                             <th>Weight</th>
                             <th>GPS Status</th>
                             <th>Age</th>
                             <th>Health</th>
                             <th>Actions</th>
                         </tr>
                    </thead>
                    <tbody>
                                                 <?php if (empty($elephants)): ?>
                             <tr>
                                                                 <td colspan="9" style="text-align: center; padding: 2rem; color: var(--text-muted);">
                                    <?php if (isset($error_message)): ?>
                                        <div style="color: var(--accent-danger);">⚠️ <?php echo htmlspecialchars($error_message); ?></div>
                                    <?php else: ?>
                                        <div>🐘 No elephants found in database</div>
                                        <div style="font-size: 0.9rem; margin-top: 0.5rem;">Add your first elephant using the "Add New Elephant" button above</div>
                                    <?php endif; ?>
                                </td>
                             </tr>
                         <?php else: ?>
                             <?php foreach ($elephants as $elephant): ?>
                             <tr data-type="<?php echo htmlspecialchars($elephant['type']); ?>" 
                                 data-gps="<?php echo $elephant['gps_connected'] ? 'connected' : 'disconnected'; ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($elephant['name'])); ?>">
                                 <td>
                                     <?php if (!empty($elephant['photo']) && file_exists($elephant['photo'])): ?>
                                         <img src="<?php echo htmlspecialchars($elephant['photo']); ?>" 
                                              alt="<?php echo htmlspecialchars($elephant['name']); ?>" 
                                              class="elephant-photo">
                                     <?php else: ?>
                                         <div class="elephant-photo-placeholder">🐘</div>
                                     <?php endif; ?>
                                 </td>
                                 <td><strong><?php echo htmlspecialchars($elephant['name']); ?></strong></td>
                                 <td><?php echo htmlspecialchars($elephant['type']); ?></td>
                                 <td><?php echo htmlspecialchars($elephant['height']); ?></td>
                                 <td><?php echo htmlspecialchars($elephant['weight']); ?></td>
                                 <td>
                                     <span class="drone-status <?php echo $elephant['gps_connected'] ? 'drone-connected' : 'drone-disconnected'; ?>">
                                         <?php echo $elephant['gps_connected'] ? 'Connected' : 'Disconnected'; ?>
                                     </span>
                                 </td>
                                 <td><?php echo htmlspecialchars($elephant['age']); ?></td>
                                 <td><?php echo htmlspecialchars($elephant['health_status']); ?></td>
                                 <td>
                                     <div class="elephant-actions">
                                         <button class="btn btn-primary btn-small" onclick="viewElephant('<?php echo $elephant['id']; ?>')">
                                             View
                                         </button>
                                         <button class="btn btn-success btn-small" onclick="editElephant('<?php echo $elephant['id']; ?>')">
                                             Edit
                                         </button>
                                         <button class="btn btn-danger btn-small" onclick="deleteElephant('<?php echo $elephant['id']; ?>')">
                                             Delete
                                         </button>
                                         <button class="btn btn-info btn-small" onclick="viewNotes('<?php echo htmlspecialchars($elephant['name']); ?>', '<?php echo htmlspecialchars($elephant['notes']); ?>')">
                                             Notes
                                         </button>
                                     </div>
                                 </td>
                             </tr>
                             <?php endforeach; ?>
                         <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Speed Function Section -->
        <div class="elephant-section">
            <div class="speed-function">
                <h4>🚀 Elephant Speed Calculation Function</h4>
                <p>This function calculates elephant speed based on various factors like age, weight, terrain, and health status.</p>
                <p><strong>Status:</strong> Currently implemented as a text file. Will be developed into a full function later.</p>
                <div class="file-info">
                    📁 File: <code>elephant_speed_calculator.txt</code><br>
                    📍 Location: <code>functions/</code> directory<br>
                    🔧 Type: Text file (placeholder for future development)
                </div>
                <button class="btn btn-warning" onclick="viewSpeedFunction()">
                    📄 View Speed Function File
                </button>
            </div>
        </div>
    </div>

    <script>
                 function filterElephants() {
             const searchTerm = document.getElementById('searchInput').value.toLowerCase();
             const typeFilter = document.getElementById('typeFilter').value;
             const gpsFilter = document.getElementById('gpsFilter').value;
             
             const rows = document.querySelectorAll('#elephantTable tbody tr');
             
             rows.forEach(row => {
                 const name = row.getAttribute('data-name');
                 const type = row.getAttribute('data-type');
                 const gps = row.getAttribute('data-gps');
                 
                 let showRow = true;
                 
                 // Name search filter
                 if (searchTerm && !name.includes(searchTerm)) {
                     showRow = false;
                 }
                 
                 // Type filter
                 if (typeFilter && type !== typeFilter) {
                     showRow = false;
                 }
                 
                 // GPS status filter
                 if (gpsFilter && gps !== gpsFilter) {
                     showRow = false;
                 }
                 
                 row.style.display = showRow ? '' : 'none';
             });
         }
        

        
        function viewElephant(id) {
            window.location.href = `view-elephant.php?id=${id}`;
        }
        
        function editElephant(id) {
            window.location.href = `edit-elephant.php?id=${id}`;
        }
        
        function deleteElephant(id) {
            if (confirm(`Are you sure you want to delete Elephant ${id}?\n\nThis action cannot be undone.`)) {
                window.location.href = `delete-elephant.php?id=${id}`;
            }
        }
        
                 function viewNotes(elephantName, notes) {
             if (notes && notes.trim()) {
                 alert(`Notes for ${elephantName}:\n\n${notes}`);
             } else {
                 alert(`No notes available for ${elephantName}`);
             }
         }
         
         function viewSpeedFunction() {
             alert('Speed Function File Viewer\n\nFile: elephant_speed_calculator.txt\n\nThis file contains:\n• Speed calculation algorithms\n• Terrain factors\n• Age-weight relationships\n• Health impact calculations\n\nCurrently a placeholder - will be developed into a full function.');
         }
        
        // Initialize search functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Add sample elephant photos if they don't exist
            const photoPlaceholders = document.querySelectorAll('.elephant-photo-placeholder');
            photoPlaceholders.forEach(placeholder => {
                placeholder.innerHTML = '🐘';
            });
        });
    </script>
</body>
</html>
