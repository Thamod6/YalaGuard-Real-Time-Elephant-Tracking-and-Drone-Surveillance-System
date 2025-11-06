<?php
/**
 * YalaGuard Edit Elephant Page
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate required fields
    $required_fields = ['elephant_name', 'elephant_type', 'gender', 'height', 'weight', 'age'];
    $missing_fields = [];
    
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            $missing_fields[] = ucfirst(str_replace('_', ' ', $field));
        }
    }
    
    if (empty($missing_fields)) {
        // Process the form data
        $elephant_data = [
            'name' => trim($_POST['elephant_name']),
            'type' => $_POST['elephant_type'],
            'gender' => $_POST['gender'],
            'height' => $_POST['height'] . 'm',
            'weight' => $_POST['weight'] . 'kg',
            'age' => $_POST['age'] . ' years',
            'gps_connected' => isset($_POST['gps_connected']) ? true : false,
            'health_status' => $_POST['health_status'],
            'notes' => trim($_POST['notes']),
            'updated_by' => $user['user_id'],
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
                 // Add GPS tracking data if enabled
         if (isset($_POST['gps_connected']) && $_POST['gps_connected']) {
             $elephant_data['gps_tracking'] = [
                 'device_id' => trim($_POST['gps_device_id'] ?? ''),
                 'device_type' => $_POST['gps_device_type'] ?? '',
                 'update_frequency' => $_POST['gps_update_frequency'] ?? '',
                 'battery_life' => !empty($_POST['gps_battery_life']) ? (int)$_POST['gps_battery_life'] : null,
                 'network_type' => $_POST['gps_network_type'] ?? '',
                 'installation_date' => $_POST['gps_installation_date'] ?? '',
                 'last_maintenance' => $_POST['gps_last_maintenance'] ?? '',
                 'notes' => trim($_POST['gps_notes'] ?? ''),
                 'status' => 'active'
             ];
             
             // Set GPS collar status for the GPS collar management system
             $elephant_data['has_gps_collar'] = true;
             $elephant_data['gps_collar_id'] = trim($_POST['gps_device_id'] ?? '');
             $elephant_data['gps_collar_name'] = 'GPS Device'; // Default name if not specified
         } else {
             // Clear GPS collar status if GPS is disconnected
             $elephant_data['has_gps_collar'] = false;
             $elephant_data['gps_collar_id'] = null;
             $elephant_data['gps_collar_name'] = null;
             $elephant_data['gps_tracking'] = null;
         }
        
        // Handle photo upload
        if (isset($_FILES['elephant_photo']) && $_FILES['elephant_photo']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = '../assets/images/elephants/';
            
            // Create directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_info = pathinfo($_FILES['elephant_photo']['name']);
            $file_extension = strtolower($file_info['extension']);
            
            // Validate file type
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            if (in_array($file_extension, $allowed_extensions)) {
                $new_filename = 'elephant_' . time() . '_' . uniqid() . '.' . $file_extension;
                $upload_path = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['elephant_photo']['tmp_name'], $upload_path)) {
                    $elephant_data['photo'] = 'assets/images/elephants/' . $new_filename;
                }
            }
        }
        
        // Update in MongoDB
        try {
            require_once '../config/database.php';
            $collection = getCollection('elephants');
            
            // Validate ObjectId
            if (!preg_match('/^[a-f\d]{24}$/i', $elephant_id)) {
                throw new Exception("Invalid elephant ID format");
            }
            
            $result = $collection->updateOne(
                ['_id' => new MongoDB\BSON\ObjectId($elephant_id)],
                ['$set' => $elephant_data]
            );
            
            if ($result->getModifiedCount() > 0) {
                $message = "Elephant '{$elephant_data['name']}' updated successfully!";
                $messageType = 'success';
                
                // Refresh elephant data
                $elephant = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($elephant_id)]);
            } else {
                $message = "No changes were made to the elephant.";
                $messageType = 'info';
            }
        } catch (Exception $e) {
            $message = "Database error: " . $e->getMessage();
            $messageType = 'error';
            error_log("Elephant update error: " . $e->getMessage());
        }
        
    } else {
        $message = "Please fill in all required fields: " . implode(', ', $missing_fields);
        $messageType = 'error';
    }
}

// Fetch elephant data if not already loaded
if (!$elephant) {
    try {
        require_once '../config/database.php';
        $collection = getCollection('elephants');
        
        // Validate ObjectId
        if (!preg_match('/^[a-f\d]{24}$/i', $elephant_id)) {
            $message = "Invalid elephant ID format";
            $messageType = 'error';
        } else {
            $elephant = $collection->findOne(['_id' => new MongoDB\BSON\ObjectId($elephant_id)]);
            
            if (!$elephant) {
                $message = "Elephant not found";
                $messageType = 'error';
            }
        }
    } catch (Exception $e) {
        $message = "Database error: " . $e->getMessage();
        $messageType = 'error';
        error_log("Elephant fetch error: " . $e->getMessage());
    }
}

// Extract values for form
$current_values = [
    'elephant_name' => $elephant['name'] ?? '',
    'elephant_type' => $elephant['type'] ?? '',
    'gender' => $elephant['gender'] ?? '',
    'height' => isset($elephant['height']) ? str_replace('m', '', $elephant['height']) : '',
    'weight' => isset($elephant['weight']) ? str_replace('kg', '', $elephant['weight']) : '',
    'age' => isset($elephant['age']) ? str_replace(' years', '', $elephant['age']) : '',
    'health_status' => $elephant['health_status'] ?? 'Good',
    'notes' => $elephant['notes'] ?? '',
    'gps_connected' => isset($elephant['has_gps_collar']) ? $elephant['has_gps_collar'] : false,
    'gps_device_id' => (isset($elephant['gps_tracking']) && isset($elephant['gps_tracking']['device_id'])) ? $elephant['gps_tracking']['device_id'] : ($elephant['gps_collar_id'] ?? ''),
    'gps_device_type' => (isset($elephant['gps_tracking']) && isset($elephant['gps_tracking']['device_type'])) ? $elephant['gps_tracking']['device_type'] : '',
    'gps_update_frequency' => (isset($elephant['gps_tracking']) && isset($elephant['gps_tracking']['update_frequency'])) ? $elephant['gps_tracking']['update_frequency'] : '',
    'gps_battery_life' => (isset($elephant['gps_tracking']) && isset($elephant['gps_tracking']['battery_life'])) ? $elephant['gps_tracking']['battery_life'] : '',
    'gps_network_type' => (isset($elephant['gps_tracking']) && isset($elephant['gps_tracking']['network_type'])) ? $elephant['gps_tracking']['network_type'] : '',
    'gps_installation_date' => (isset($elephant['gps_tracking']) && isset($elephant['gps_tracking']['installation_date'])) ? $elephant['gps_tracking']['installation_date'] : '',
    'gps_last_maintenance' => (isset($elephant['gps_tracking']) && isset($elephant['gps_tracking']['last_maintenance'])) ? $elephant['gps_tracking']['last_maintenance'] : '',
    'gps_notes' => (isset($elephant['gps_tracking']) && isset($elephant['gps_tracking']['notes'])) ? $elephant['gps_tracking']['notes'] : ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Edit Elephant</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Edit Elephant Specific Styles */
        .edit-elephant-section {
            background: var(--bg-card);
            padding: 2rem;
            border-radius: 15px;
            box-shadow: var(--shadow-medium);
            margin-bottom: 2rem;
            border: 1px solid var(--border-primary);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group.photo-upload {
            grid-column: 1 / -1;
            text-align: center;
        }
        
        .form-actions {
            grid-column: 1 / -1;
        }
        
        .current-photo {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin: 1rem auto;
            display: block;
            border: 3px solid var(--border-primary);
        }
        
        .photo-info {
            background: var(--bg-secondary);
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
            border: 1px solid var(--border-primary);
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
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
            <h1 class="page-title">Edit Elephant</h1>
            <p class="page-subtitle">Modify the details of elephant: <strong><?php echo htmlspecialchars($elephant['name']); ?></strong></p>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        

        
        <!-- Edit Elephant Form -->
        <div class="edit-elephant-section">
            <form method="POST" action="" enctype="multipart/form-data" id="editElephantForm">
                <div class="form-grid">
                    <!-- Current Photo Display -->
                    <div class="form-group photo-upload">
                        <label class="form-label">Current Elephant Photo</label>
                        <?php if (!empty($elephant['photo']) && file_exists('../' . $elephant['photo'])): ?>
                            <img src="../<?php echo htmlspecialchars($elephant['photo']); ?>" 
                                 alt="<?php echo htmlspecialchars($elephant['name']); ?>" 
                                 class="current-photo">
                            <div class="photo-info">
                                <strong>Current Photo:</strong> <?php echo basename($elephant['photo']); ?>
                            </div>
                        <?php else: ?>
                            <div class="elephant-photo-placeholder">🐘</div>
                            <div class="photo-info">
                                <strong>No photo currently uploaded</strong>
                            </div>
                        <?php endif; ?>
                        
                        <label class="form-label">Upload New Photo (Optional)</label>
                        <div class="photo-upload-area" id="photoUploadArea">
                            <div id="uploadText">
                                <div style="font-size: 2rem; margin-bottom: 0.5rem;">📷</div>
                                <div style="font-size: 1rem; margin-bottom: 0.5rem;">Click to upload new photo</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted);">or drag and drop</div>
                                <div style="font-size: 0.7rem; color: var(--text-muted); margin-top: 0.5rem;">JPG, PNG, GIF (Max 5MB)</div>
                            </div>
                            <img id="photoPreview" class="photo-preview" alt="Photo preview">
                        </div>
                        <input type="file" name="elephant_photo" id="elephantPhoto" accept="image/*" style="display: none;">
                        <div class="help-text">Leave empty to keep current photo</div>
                    </div>
                    
                    <!-- Basic Information -->
                    <div class="form-group">
                        <label for="elephant_name" class="form-label">Elephant Name <span class="required">*</span></label>
                        <input type="text" id="elephant_name" name="elephant_name" class="form-input" 
                               value="<?php echo htmlspecialchars($current_values['elephant_name']); ?>" 
                               placeholder="e.g., Raja, Lakshmi" required>
                        <div class="help-text">Enter a unique name for the elephant</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="elephant_type" class="form-label">Elephant Type <span class="required">*</span></label>
                        <select id="elephant_type" name="elephant_type" class="form-select" required>
                            <option value="">Select elephant type</option>
                            <option value="Asian Elephant" <?php echo ($current_values['elephant_type'] === 'Asian Elephant') ? 'selected' : ''; ?>>Asian Elephant</option>
                            <option value="African Bush Elephant" <?php echo ($current_values['elephant_type'] === 'African Bush Elephant') ? 'selected' : ''; ?>>African Bush Elephant</option>
                            <option value="African Forest Elephant" <?php echo ($current_values['elephant_type'] === 'African Forest Elephant') ? 'selected' : ''; ?>>African Forest Elephant</option>
                        </select>
                        <div class="help-text">Choose the species of elephant</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender" class="form-label">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" class="form-select" required>
                            <option value="">Select gender</option>
                            <option value="Male" <?php echo ($current_values['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($current_values['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Unknown" <?php echo ($current_values['gender'] === 'Unknown') ? 'selected' : ''; ?>>Unknown</option>
                        </select>
                        <div class="help-text">Select the gender of the elephant</div>
                    </div>
                    
                    <!-- Physical Measurements -->
                    <div class="form-group">
                        <label for="height" class="form-label">Height (meters) <span class="required">*</span></label>
                        <input type="number" id="height" name="height" class="form-input" 
                               value="<?php echo htmlspecialchars($current_values['height']); ?>" 
                               step="0.1" min="1.5" max="4.0" placeholder="2.8" required>
                        <div class="help-text">Height from ground to shoulder in meters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="weight" class="form-label">Weight (kg) <span class="required">*</span></label>
                        <input type="number" id="weight" name="weight" class="form-input" 
                               value="<?php echo htmlspecialchars($current_values['weight']); ?>" 
                               step="100" min="2000" max="8000" placeholder="4500" required>
                        <div class="help-text">Weight in kilograms</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="age" class="form-label">Age (years) <span class="required">*</span></label>
                        <input type="number" id="age" name="age" class="form-input" 
                               value="<?php echo htmlspecialchars($current_values['age']); ?>" 
                               min="0" max="80" placeholder="25" required>
                        <div class="help-text">Age in years (estimated if exact age unknown)</div>
                    </div>
                    
                    <!-- Status and Location -->
                    <div class="form-group">
                        <label for="health_status" class="form-label">Health Status</label>
                        <select id="health_status" name="health_status" class="form-select">
                            <option value="Excellent" <?php echo ($current_values['health_status'] === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                            <option value="Good" <?php echo ($current_values['health_status'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                            <option value="Fair" <?php echo ($current_values['health_status'] === 'Fair') ? 'selected' : ''; ?>>Fair</option>
                            <option value="Poor" <?php echo ($current_values['health_status'] === 'Poor') ? 'selected' : ''; ?>>Poor</option>
                            <option value="Critical" <?php echo ($current_values['health_status'] === 'Critical') ? 'selected' : ''; ?>>Critical</option>
                        </select>
                        <div class="help-text">Current health condition of the elephant</div>
                    </div>
                    
                    <!-- GPS Tracking Section -->
                    <div class="form-group full-width">
                        <h3 style="color: var(--accent-primary); margin-bottom: 1rem; border-bottom: 2px solid var(--border-primary); padding-bottom: 0.5rem;">
                            📡 GPS Tracking System
                        </h3>
                        
                        <!-- GPS Connection Status -->
                        <div class="form-group">
                            <div class="checkbox-group">
                                <input type="checkbox" id="gps_connected" name="gps_connected" 
                                       <?php echo ($current_values['gps_connected']) ? 'checked' : ''; ?>>
                                <label for="gps_connected" class="form-label">GPS Connected</label>
                            </div>
                            <div class="help-text">Enable GPS tracking for this elephant</div>
                        </div>
                        
                        <!-- GPS Device Information -->
                        <div id="gpsSettings" style="display: <?php echo ($current_values['gps_connected']) ? 'block' : 'none'; ?>; background: var(--bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-primary);">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="gps_device_id" class="form-label">GPS Device <span class="required">*</span></label>
                                    <select id="gps_device_id" name="gps_device_id" class="form-select" required>
                                        <option value="">Select GPS Device</option>
                                        <?php
                                        // Get available GPS collars
                                        try {
                                            require_once '../config/database.php';
                                            $db = getDatabase();
                                            $gpsCollection = $db->selectCollection('gps_collars');
                                            $gpsCollars = $gpsCollection->find(['status' => 'active'], ['sort' => ['collar_name' => 1]]);
                                            
                                            foreach ($gpsCollars as $collar) {
                                                $selected = '';
                                                if (($current_values['gps_device_id'] ?? '') === $collar['collar_id']) {
                                                    $selected = 'selected';
                                                }
                                                echo '<option value="' . htmlspecialchars($collar['collar_id']) . '" ' . $selected . '>' . 
                                                     htmlspecialchars($collar['collar_name']) . ' (' . htmlspecialchars($collar['collar_id']) . ')</option>';
                                            }
                                        } catch (Exception $e) {
                                            echo '<option value="">Error loading GPS collars</option>';
                                        }
                                        ?>
                                    </select>
                                    <div class="help-text">Select the GPS device assigned to this elephant</div>
                                    <div style="margin-top: 0.5rem;">
                                        <a href="gps-collar-management.php" class="btn btn-secondary btn-small" target="_blank">
                                            ➕ Add New GPS Device
                                        </a>
                                        <span style="color: var(--text-muted); font-size: 0.9rem; margin-left: 0.5rem;">
                                            Don't see your device? Add it first in GPS Collars
                                        </span>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="gps_device_type" class="form-label">Device Type</label>
                                    <select id="gps_device_type" name="gps_device_type" class="form-select">
                                        <option value="">Select device type</option>
                                        <option value="GPS Collar" <?php echo ($current_values['gps_device_type'] === 'GPS Collar') ? 'selected' : ''; ?>>GPS Collar</option>
                                        <option value="GPS Tag" <?php echo ($current_values['gps_device_type'] === 'GPS Tag') ? 'selected' : ''; ?>>GPS Tag</option>
                                        <option value="GPS Tracker" <?php echo ($current_values['gps_device_type'] === 'GPS Tracker') ? 'selected' : ''; ?>>GPS Tracker</option>
                                        <option value="Satellite Tracker" <?php echo ($current_values['gps_device_type'] === 'Satellite Tracker') ? 'selected' : ''; ?>>Satellite Tracker</option>
                                    </select>
                                    <div class="help-text">Type of GPS tracking device</div>
                                </div>

                                <div class="form-group">
                                    <label for="gps_update_frequency" class="form-label">Update Frequency</label>
                                    <select id="gps_update_frequency" name="gps_update_frequency" class="form-select">
                                        <option value="">Select frequency</option>
                                        <option value="Every 5 minutes" <?php echo ($current_values['gps_update_frequency'] === 'Every 5 minutes') ? 'selected' : ''; ?>>Every 5 minutes</option>
                                        <option value="Every 15 minutes" <?php echo ($current_values['gps_update_frequency'] === 'Every 15 minutes') ? 'selected' : ''; ?>>Every 15 minutes</option>
                                        <option value="Every 30 minutes" <?php echo ($current_values['gps_update_frequency'] === 'Every 30 minutes') ? 'selected' : ''; ?>>Every 30 minutes</option>
                                        <option value="Every hour" <?php echo ($current_values['gps_update_frequency'] === 'Every hour') ? 'selected' : ''; ?>>Every hour</option>
                                        <option value="Every 6 hours" <?php echo ($current_values['gps_update_frequency'] === 'Every 6 hours') ? 'selected' : ''; ?>>Every 6 hours</option>
                                        <option value="Daily" <?php echo ($current_values['gps_update_frequency'] === 'Daily') ? 'selected' : ''; ?>>Daily</option>
                                    </select>
                                    <div class="help-text">How often the GPS device sends location updates</div>
                                </div>

                                <div class="form-group">
                                    <label for="gps_battery_life" class="form-label">Battery Life (days)</label>
                                    <input type="number" id="gps_battery_life" name="gps_battery_life" class="form-input" 
                                           value="<?php echo htmlspecialchars($current_values['gps_battery_life']); ?>" 
                                           min="1" max="365" placeholder="30">
                                    <div class="help-text">Expected battery life in days</div>
                                </div>

                                <div class="form-group">
                                    <label for="gps_network_type" class="form-label">Network Type</label>
                                    <select id="gps_network_type" name="gps_network_type" class="form-select">
                                        <option value="">Select network type</option>
                                        <option value="Cellular" <?php echo ($current_values['gps_network_type'] === 'Cellular') ? 'selected' : ''; ?>>Cellular</option>
                                        <option value="Satellite" <?php echo ($current_values['gps_network_type'] === 'Satellite') ? 'selected' : ''; ?>>Satellite</option>
                                        <option value="WiFi" <?php echo ($current_values['gps_network_type'] === 'WiFi') ? 'selected' : ''; ?>>WiFi</option>
                                        <option value="Bluetooth" <?php echo ($current_values['gps_network_type'] === 'Bluetooth') ? 'selected' : ''; ?>>Bluetooth</option>
                                    </select>
                                    <div class="help-text">Communication network used by the GPS device</div>
                                </div>
                                

                                

                                

                                

                                
                                <div class="form-group">
                                    <label for="gps_installation_date" class="form-label">GPS Installation Date</label>
                                    <input type="date" id="gps_installation_date" name="gps_installation_date" class="form-input" 
                                           value="<?php echo htmlspecialchars($current_values['gps_installation_date']); ?>">
                                    <div class="help-text">When the GPS device was installed on the elephant</div>
                                </div>

                                <div class="form-group">
                                    <label for="gps_last_maintenance" class="form-label">Last Maintenance Date</label>
                                    <input type="date" id="gps_last_maintenance" name="gps_last_maintenance" class="form-input" 
                                           value="<?php echo htmlspecialchars($current_values['gps_last_maintenance']); ?>">
                                    <div class="help-text">Date of last GPS device maintenance</div>
                                </div>
                                

                                
                                <div class="form-group">
                                    <label for="gps_notes" class="form-label">GPS Notes</label>
                                    <textarea id="gps_notes" name="gps_notes" class="form-textarea" 
                                              placeholder="Any special notes about the GPS installation or elephant-specific settings..."><?php echo htmlspecialchars($current_values['gps_notes']); ?></textarea>
                                    <div class="help-text">Special notes about GPS installation or elephant-specific settings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="form-group full-width">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea id="notes" name="notes" class="form-textarea" 
                                  placeholder="Enter any additional information about the elephant..."><?php echo htmlspecialchars($current_values['notes']); ?></textarea>
                        <div class="help-text">Any special characteristics, behavior patterns, or important notes</div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-large">
                            💾 Save Changes
                        </button>
                        <button type="button" class="btn btn-secondary btn-large" onclick="location.href='elephants.php'">
                            ❌ Cancel
                        </button>
                        <button type="button" class="btn btn-danger btn-large" onclick="deleteElephant('<?php echo $elephant_id; ?>')">
                            Delete Elephant
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Photo upload functionality
        const photoUploadArea = document.getElementById('photoUploadArea');
        const elephantPhoto = document.getElementById('elephantPhoto');
        const photoPreview = document.getElementById('photoPreview');
        const uploadText = document.getElementById('uploadText');
        
        // Click to upload
        photoUploadArea.addEventListener('click', () => {
            elephantPhoto.click();
        });
        
        // File selection
        elephantPhoto.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                // Validate file size (5MB max)
                if (file.size > 5 * 1024 * 1024) {
                    alert('File size must be less than 5MB');
                    elephantPhoto.value = '';
                    return;
                }
                
                // Validate file type
                if (!file.type.startsWith('image/')) {
                    alert('Please select an image file');
                    elephantPhoto.value = '';
                    return;
                }
                
                // Show preview
                const reader = new FileReader();
                reader.onload = (e) => {
                    photoPreview.src = e.target.result;
                    photoPreview.style.display = 'block';
                    uploadText.style.display = 'none';
                };
                reader.readAsDataURL(file);
            }
        });
        
        // Drag and drop functionality
        photoUploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            photoUploadArea.classList.add('dragover');
        });
        
        photoUploadArea.addEventListener('dragleave', () => {
            photoUploadArea.classList.remove('dragover');
        });
        
        photoUploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            photoUploadArea.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                elephantPhoto.files = files;
                elephantPhoto.dispatchEvent(new Event('change'));
            }
        });
        
        // Form validation
        document.getElementById('editElephantForm').addEventListener('submit', function(e) {
            const requiredFields = this.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.style.borderColor = 'var(--accent-danger)';
                    isValid = false;
                } else {
                    field.style.borderColor = 'var(--border-primary)';
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields marked with *');
            }
        });
        
        // Real-time validation
        document.querySelectorAll('.form-input, .form-select').forEach(input => {
            input.addEventListener('blur', function() {
                if (this.hasAttribute('required') && !this.value.trim()) {
                    this.style.borderColor = 'var(--accent-danger)';
                } else {
                    this.style.borderColor = 'var(--border-primary)';
                }
            });
        });
        
        // GPS Settings Toggle
        const gpsConnectedCheckbox = document.getElementById('gps_connected');
        const gpsSettings = document.getElementById('gpsSettings');
        
        function toggleGPSSettings() {
            if (gpsConnectedCheckbox.checked) {
                gpsSettings.style.display = 'block';
                gpsSettings.classList.add('fade-in');
            } else {
                gpsSettings.style.display = 'none';
            }
        }
        
        gpsConnectedCheckbox.addEventListener('change', toggleGPSSettings);
        
        // Initialize GPS settings visibility
        toggleGPSSettings();
        
        // Delete elephant function
        function deleteElephant(elephantId) {
            if (confirm('Are you sure you want to delete this elephant? This action cannot be undone.')) {
                // Redirect to delete confirmation page or handle deletion
                window.location.href = `delete-elephant.php?id=${elephantId}`;
            }
        }
    </script>
</body>
</html>
