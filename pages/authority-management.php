<?php
/**
 * YalaGuard Authority Management Page
 * 
 * This page allows users to manage authority persons who receive alerts
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

// Get authorities for display
$authorities = [];

try {
    require_once '../config/database.php';
    
    $authoritiesCollection = getCollection('authorities');
    $authoritiesCursor = $authoritiesCollection->find([], ['sort' => ['name' => 1]]);
    
    foreach ($authoritiesCursor as $authority) {
        // Convert BSONArray to regular array for alert_levels
        $alertPreferences = $authority['alert_preferences'] ?? [];
        if (isset($alertPreferences['alert_levels']) && is_object($alertPreferences['alert_levels'])) {
            $alertPreferences['alert_levels'] = iterator_to_array($alertPreferences['alert_levels']);
        }
        
        $authorities[] = [
            'id' => (string)$authority['_id'],
            'name' => $authority['name'],
            'role' => $authority['role'],
            'organization' => $authority['organization'],
            'department' => $authority['department'] ?? '',
            'phone' => $authority['phone'],
            'email' => $authority['email'],
            'active' => $authority['active'],
            'alert_preferences' => $alertPreferences,
            'created_at' => $authority['created_at']->toDateTime()->format('Y-m-d H:i:s')
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
    <title>YalaGuard - Authority Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .add-authority-btn {
            background: var(--accent-primary);
            color: var(--bg-primary);
            padding: 15px 30px;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .add-authority-btn:hover {
            background: #4dd4b0;
            transform: translateY(-3px);
            box-shadow: var(--shadow-heavy);
        }
        
        .status-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .status-active {
            background: rgba(76, 175, 80, 0.2);
            color: var(--accent-success);
            border: 1px solid var(--accent-success);
        }
        
        .status-inactive {
            background: rgba(244, 67, 54, 0.2);
            color: var(--accent-danger);
            border: 1px solid var(--accent-danger);
        }
        
        /* Table Styles */
        .authority-table-container {
            margin-top: 20px;
            overflow-x: auto;
            background: var(--bg-card);
            border-radius: 10px;
            box-shadow: var(--shadow-medium);
            border: 1px solid var(--border-primary);
        }
        
        .table-responsive {
            overflow-x: auto;
            min-width: 1200px;
        }
        
        .authority-table {
            width: 100%;
            border-collapse: collapse;
            background: var(--bg-card);
            table-layout: fixed;
        }
        
        .authority-table th,
        .authority-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border-primary);
            vertical-align: top;
            word-wrap: break-word;
        }
        
        /* Set specific column widths */
        .authority-table th:nth-child(1), .authority-table td:nth-child(1) { width: 15%; } /* Name */
        .authority-table th:nth-child(2), .authority-table td:nth-child(2) { width: 12%; } /* Role */
        .authority-table th:nth-child(3), .authority-table td:nth-child(3) { width: 15%; } /* Organization */
        .authority-table th:nth-child(4), .authority-table td:nth-child(4) { width: 12%; } /* Department */
        .authority-table th:nth-child(5), .authority-table td:nth-child(5) { width: 12%; } /* Phone */
        .authority-table th:nth-child(6), .authority-table td:nth-child(6) { width: 15%; } /* Email */
        .authority-table th:nth-child(7), .authority-table td:nth-child(7) { width: 8%; }  /* Status */
        .authority-table th:nth-child(8), .authority-table td:nth-child(8) { width: 12%; } /* Alert Levels */
        .authority-table th:nth-child(9), .authority-table td:nth-child(9) { width: 10%; } /* Added */
        .authority-table th:nth-child(10), .authority-table td:nth-child(10) { width: 15%; } /* Actions */
        
        .authority-table th {
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .authority-table tr:last-child td {
            border-bottom: none;
        }
        
        .authority-table tr:hover {
            background-color: var(--bg-hover);
        }
        
        .authority-name strong {
            color: var(--accent-primary);
        }
        
        .text-muted {
            color: var(--text-muted);
            font-style: italic;
        }
        
        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-start;
            min-width: 120px;
        }
        
        .btn-edit, .btn-delete {
            padding: 8px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            transition: all 0.3s ease;
            white-space: nowrap;
            min-width: 60px;
        }
        
        .btn-edit {
            background: var(--accent-success);
            color: white;
        }
        
        .btn-edit:hover {
            background: #388e3c;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: var(--accent-danger);
            color: white;
        }
        
        .btn-delete:hover {
            background: #d32f2f;
            transform: translateY(-2px);
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.7);
        }
        
        .modal-content {
            background: var(--bg-card);
            margin: 5% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid var(--border-primary);
            box-shadow: var(--shadow-heavy);
        }
        
        .close {
            color: var(--text-muted);
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: var(--accent-primary);
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
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
            padding: 12px;
            border: 2px solid var(--border-primary);
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--bg-input);
            color: var(--text-primary);
        }
        
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(100, 255, 218, 0.1);
            background: var(--bg-hover);
        }
        
        .form-section {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid var(--border-primary);
        }
        
        .form-section h3 {
            margin-top: 0;
            color: var(--accent-primary);
            font-size: 18px;
            margin-bottom: 15px;
        }
        
        .preference-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .alert-levels-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .alert-level-item {
            display: flex;
            align-items: center;
        }
        
        .alert-level-item input[type="checkbox"] {
            margin-right: 10px;
            accent-color: var(--accent-primary);
        }
        
        .level-label {
            font-size: 14px;
            color: var(--text-primary);
            font-weight: 500;
            cursor: pointer;
        }
        
        .level-label.critical {
            color: var(--accent-danger);
        }
        
        .level-label.high {
            color: var(--accent-warning);
        }
        
        .level-label.medium {
            color: var(--accent-info);
        }
        
        .level-label.low {
            color: var(--text-secondary);
        }
        
        .form-actions {
            display: flex;
            justify-content: center;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid var(--border-primary);
        }
        
        .btn {
            display: inline-block;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: var(--accent-primary);
            color: var(--bg-primary);
        }
        
        .btn-primary:hover {
            background: #4dd4b0;
            transform: translateY(-2px);
            box-shadow: var(--shadow-medium);
        }
        
        .btn-large {
            padding: 1rem 2rem;
            font-size: 1.1rem;
            width: 100%;
        }
        
        #modalTitle {
            color: var(--accent-primary);
            margin-bottom: 1.5rem;
            text-align: center;
            font-size: 1.8rem;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .preference-grid,
            .alert-levels-grid {
                grid-template-columns: 1fr;
            }
            
            .modal-content {
                margin: 10% auto;
                padding: 20px;
                width: 95%;
            }
            
            .table-responsive {
                min-width: 800px;
            }
            
            .authority-table th,
            .authority-table td {
                padding: 10px 15px;
                font-size: 14px;
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
                <li><a href="elephants.php">Elephants</a></li>
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
                <span class="user-info">Welcome, <?php echo htmlspecialchars($user['full_name']); ?></span>
                <a href="../api/auth/logout.php" class="logout-btn">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="page-header">
            <h1>👥 Authority Management</h1>
            <p>Manage authority persons who receive alerts and notifications</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Add New Authority Button -->
        <button class="add-authority-btn" onclick="openAddAuthorityModal()">
            ➕ Add New Authority
        </button>

        <!-- Authorities Table -->
        <div class="authority-table-container">
            <div class="table-responsive">
                <table class="authority-table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Role</th>
                            <th>Organization</th>
                            <th>Department</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Alert Levels</th>
                            <th>Added</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($authorities as $authority): ?>
                            <tr>
                                <td>
                                    <div class="authority-name">
                                        <strong><?php echo htmlspecialchars($authority['name']); ?></strong>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($authority['role']); ?></td>
                                <td><?php echo htmlspecialchars($authority['organization']); ?></td>
                                <td>
                                    <?php if (!empty($authority['department'])): ?>
                                        <?php echo htmlspecialchars($authority['department']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($authority['phone']); ?></td>
                                <td><?php echo htmlspecialchars($authority['email']); ?></td>
                                <td>
                                    <span class="status-badge <?php echo $authority['active'] ? 'status-active' : 'status-inactive'; ?>">
                                        <?php echo $authority['active'] ? 'Active' : 'Inactive'; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php 
                                    $levels = $authority['alert_preferences']['alert_levels'] ?? ['critical', 'high', 'medium'];
                                    echo implode(', ', array_map('ucfirst', $levels));
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($authority['created_at']); ?></td>
                                <td>
                                    <div class="table-actions">
                                        <button class="btn-edit" onclick="editAuthority('<?php echo $authority['id']; ?>')">
                                            Edit
                                        </button>
                                        <button class="btn-delete" onclick="deleteAuthority('<?php echo $authority['id']; ?>', '<?php echo htmlspecialchars($authority['name']); ?>')">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add/Edit Authority Modal -->
    <div id="authorityModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAuthorityModal()">&times;</span>
            <h2 id="modalTitle">Add New Authority</h2>
            
            <form id="authorityForm">
                <input type="hidden" id="authority_id" name="authority_id">
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" required placeholder="Enter full name">
                    </div>
                    
                    <div class="form-group">
                        <label for="role">Role/Position *</label>
                        <input type="text" id="role" name="role" required placeholder="Enter role or position">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="organization">Organization *</label>
                        <input type="text" id="organization" name="organization" required placeholder="Enter organization name">
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department</label>
                        <input type="text" id="department" name="department" placeholder="Enter department (optional)">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="phone">Phone Number *</label>
                        <input type="tel" id="phone" name="phone" required placeholder="Enter phone number">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" required placeholder="Enter email address">
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>🔔 Alert Preferences</h3>
                    <div class="preference-grid">
                        <div class="alert-level-item">
                            <input type="checkbox" id="sms_enabled" name="sms_enabled" checked>
                            <label for="sms_enabled">Enable SMS Alerts</label>
                        </div>
                        <div class="alert-level-item">
                            <input type="checkbox" id="email_enabled" name="email_enabled" checked>
                            <label for="email_enabled">Enable Email Alerts</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-section">
                    <h3>📊 Alert Levels to Receive</h3>
                    <div class="alert-levels-grid">
                        <div class="alert-level-item">
                            <input type="checkbox" name="alert_levels[]" value="critical" checked id="level_critical">
                            <label for="level_critical" class="level-label critical">Critical</label>
                        </div>
                        <div class="alert-level-item">
                            <input type="checkbox" name="alert_levels[]" value="high" checked id="level_high">
                            <label for="level_high" class="level-label high">High</label>
                        </div>
                        <div class="alert-level-item">
                            <input type="checkbox" name="alert_levels[]" value="medium" checked id="level_medium">
                            <label for="level_medium" class="level-label medium">Medium</label>
                        </div>
                        <div class="alert-level-item">
                            <input type="checkbox" name="alert_levels[]" value="low" id="level_low">
                            <label for="level_low" class="level-label low">Low</label>
                        </div>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary btn-large">
                        💾 Save Authority
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        function openAddAuthorityModal() {
            document.getElementById('modalTitle').textContent = 'Add New Authority';
            document.getElementById('authorityForm').reset();
            document.getElementById('authority_id').value = '';
            document.getElementById('authorityModal').style.display = 'block';
        }
        
        function closeAuthorityModal() {
            document.getElementById('authorityModal').style.display = 'none';
        }
        
        function editAuthority(authorityId) {
            // Fetch authority data and populate form
            fetch(`../api/authorities.php?id=${authorityId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        const authority = data.data;
                        document.getElementById('modalTitle').textContent = 'Edit Authority';
                        document.getElementById('authority_id').value = authority._id;
                        document.getElementById('name').value = authority.name;
                        document.getElementById('role').value = authority.role;
                        document.getElementById('organization').value = authority.organization;
                        document.getElementById('department').value = authority.department || '';
                        document.getElementById('phone').value = authority.phone;
                        document.getElementById('email').value = authority.email;
                        document.getElementById('sms_enabled').checked = authority.alert_preferences?.sms_enabled ?? true;
                        document.getElementById('email_enabled').checked = authority.alert_preferences?.email_enabled ?? true;
                        
                        // Reset alert levels
                        let alertLevels = authority.alert_preferences?.alert_levels || [];
                        if (alertLevels && typeof alertLevels === 'object' && Array.isArray(alertLevels) === false) {
                            alertLevels = Array.from(alertLevels);
                        }
                        document.querySelectorAll('input[name="alert_levels[]"]').forEach(cb => {
                            cb.checked = alertLevels.includes(cb.value);
                        });
                        
                        document.getElementById('authorityModal').style.display = 'block';
                    } else {
                        alert('Failed to load authority data: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error loading authority data: ' + error.message);
                });
        }
        
        function deleteAuthority(authorityId, authorityName) {
            if (confirm(`Are you sure you want to delete authority "${authorityName}"?`)) {
                fetch(`../api/authorities.php?id=${authorityId}`, {
                    method: 'DELETE'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'success') {
                        alert('Authority deleted successfully');
                        location.reload();
                    } else {
                        alert('Failed to delete authority: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('Error deleting authority: ' + error.message);
                });
            }
        }
        
        // Form submission
        document.getElementById('authorityForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const authorityId = formData.get('authority_id');
            
            // Prepare data for API
            const authorityData = {
                name: formData.get('name'),
                role: formData.get('role'),
                organization: formData.get('organization'),
                department: formData.get('department'),
                phone: formData.get('phone'),
                email: formData.get('email'),
                sms_enabled: formData.get('sms_enabled') === 'on',
                email_enabled: formData.get('email_enabled') === 'on',
                alert_levels: Array.from(formData.getAll('alert_levels[]'))
            };
            
            const method = authorityId ? 'PUT' : 'POST';
            const url = authorityId ? `../api/authorities.php?id=${authorityId}` : '../api/authorities.php';
            
            fetch(url, {
                method: method,
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(authorityData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert(authorityId ? 'Authority updated successfully!' : 'Authority created successfully!');
                    closeAuthorityModal();
                    location.reload();
                } else {
                    alert('Failed to save authority: ' + data.message);
                }
            })
            .catch(error => {
                alert('Error saving authority: ' + error.message);
            });
        });
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('authorityModal');
            if (event.target === modal) {
                closeAuthorityModal();
            }
        }
    </script>
</body>
</html>
