<?php
/**
 * YalaGuard Add New Elephant Page
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

// Handle form submission
$message = '';
$messageType = '';

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
             'tagId' => 'ELEPHANT_' . time() . '_' . uniqid(), // Generate unique tagId
             'name' => trim($_POST['elephant_name']),
             'type' => $_POST['elephant_type'],
             'gender' => $_POST['gender'],
             'height' => $_POST['height'] . 'm',
             'weight' => $_POST['weight'] . 'kg',
             'age' => $_POST['age'] . ' years',
             'gps_connected' => isset($_POST['gps_connected']) ? true : false,
             'health_status' => $_POST['health_status'],
             'notes' => trim($_POST['notes']),
             'created_by' => $user['user_id'],
             'created_at' => date('Y-m-d H:i:s'),
             'active' => true
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
                 'status' => 'active',
                 'last_location' => null,
                 'last_update' => null,
                 'total_distance' => 0,
                 'current_speed' => 0
             ];
             
             // Set GPS collar status for the GPS collar management system
             $elephant_data['has_gps_collar'] = true;
             $elephant_data['gps_collar_id'] = trim($_POST['gps_device_id'] ?? '');
             $elephant_data['gps_collar_name'] = 'GPS Device'; // Default name if not specified
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
        
                 // Save to MongoDB
         try {
             require_once '../config/database.php';
             $collection = getCollection('elephants');
             
             $result = $collection->insertOne($elephant_data);
             
             if ($result->getInsertedCount() > 0) {
                 $message = "Elephant '{$elephant_data['name']}' added successfully to database!";
                 $messageType = 'success';
                 $_POST = array(); // Clear form data
             } else {
                 $message = "Failed to save elephant to database.";
                 $messageType = 'error';
             }
         } catch (Exception $e) {
             $message = "Database error: " . $e->getMessage();
             $messageType = 'error';
         }
        
    } else {
        $message = "Please fill in all required fields: " . implode(', ', $missing_fields);
        $messageType = 'error';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Add New Elephant</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* Add Elephant Specific Styles */
        .add-elephant-section {
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
            <h1 class="page-title">🐘 Add New Elephant</h1>
            <p class="page-subtitle">Enter the details of the new elephant to add to the system</p>
        </div>
        
        <!-- Message Display -->
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        

        
        <!-- Add Elephant Form -->
        <div class="add-elephant-section">
            <form method="POST" enctype="multipart/form-data" id="addElephantForm">
                <div class="form-grid">
                    <!-- Photo Upload -->
                    <div class="form-group photo-upload">
                        <label class="form-label">Elephant Photo <span class="required">*</span></label>
                        <div class="photo-upload-area" id="photoUploadArea">
                            <div id="uploadText">
                                <div style="font-size: 3rem; margin-bottom: 1rem;">📷</div>
                                <div style="font-size: 1.1rem; margin-bottom: 0.5rem;">Click to upload photo</div>
                                <div style="font-size: 0.9rem; color: var(--text-muted);">or drag and drop</div>
                                <div style="font-size: 0.8rem; color: var(--text-muted); margin-top: 0.5rem;">JPG, PNG, GIF (Max 5MB)</div>
                            </div>
                            <img id="photoPreview" class="photo-preview" alt="Photo preview">
                        </div>
                        <input type="file" name="elephant_photo" id="elephantPhoto" accept="image/*" style="display: none;">
                        <div class="help-text">Upload a clear photo of the elephant for identification</div>
                    </div>
                    
                    <!-- Basic Information -->
                    <div class="form-group">
                        <label for="elephant_name" class="form-label">Elephant Name <span class="required">*</span></label>
                        <input type="text" id="elephant_name" name="elephant_name" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['elephant_name'] ?? ''); ?>" 
                               placeholder="e.g., Raja, Lakshmi" required>
                        <div class="help-text">Enter a unique name for the elephant</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="elephant_type" class="form-label">Elephant Type <span class="required">*</span></label>
                        <select id="elephant_type" name="elephant_type" class="form-select" required>
                            <option value="">Select elephant type</option>
                            <option value="Asian Elephant" <?php echo (isset($_POST['elephant_type']) && $_POST['elephant_type'] === 'Asian Elephant') ? 'selected' : ''; ?>>Asian Elephant</option>
                            <option value="African Bush Elephant" <?php echo (isset($_POST['elephant_type']) && $_POST['elephant_type'] === 'African Bush Elephant') ? 'selected' : ''; ?>>African Bush Elephant</option>
                            <option value="African Forest Elephant" <?php echo (isset($_POST['elephant_type']) && $_POST['elephant_type'] === 'African Forest Elephant') ? 'selected' : ''; ?>>African Forest Elephant</option>
                        </select>
                        <div class="help-text">Choose the species of elephant</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="gender" class="form-label">Gender <span class="required">*</span></label>
                        <select id="gender" name="gender" class="form-select" required>
                            <option value="">Select gender</option>
                            <option value="Male" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Unknown" <?php echo (isset($_POST['gender']) && $_POST['gender'] === 'Unknown') ? 'selected' : ''; ?>>Unknown</option>
                        </select>
                        <div class="help-text">Select the gender of the elephant</div>
                    </div>
                    
                    <!-- Physical Measurements -->
                    <div class="form-group">
                        <label for="height" class="form-label">Height (meters) <span class="required">*</span></label>
                        <input type="number" id="height" name="height" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['height'] ?? ''); ?>" 
                               step="0.1" min="1.5" max="4.0" placeholder="2.8" required>
                        <div class="help-text">Height from ground to shoulder in meters</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="weight" class="form-label">Weight (kg) <span class="required">*</span></label>
                        <input type="number" id="weight" name="weight" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['weight'] ?? ''); ?>" 
                               step="100" min="2000" max="8000" placeholder="4500" required>
                        <div class="help-text">Weight in kilograms</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="age" class="form-label">Age (years) <span class="required">*</span></label>
                        <input type="number" id="age" name="age" class="form-input" 
                               value="<?php echo htmlspecialchars($_POST['age'] ?? ''); ?>" 
                               min="0" max="80" placeholder="25" required>
                        <div class="help-text">Age in years (estimated if exact age unknown)</div>
                    </div>
                    
                    
                    
                    <!-- Status and Location -->
                    <div class="form-group">
                        <label for="health_status" class="form-label">Health Status</label>
                        <select id="health_status" name="health_status" class="form-select">
                            <option value="Excellent" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'Excellent') ? 'selected' : ''; ?>>Excellent</option>
                            <option value="Good" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'Good') ? 'selected' : ''; ?>>Good</option>
                            <option value="Fair" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'Fair') ? 'selected' : ''; ?>>Fair</option>
                            <option value="Poor" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'Poor') ? 'selected' : ''; ?>>Poor</option>
                            <option value="Critical" <?php echo (isset($_POST['health_status']) && $_POST['health_status'] === 'Critical') ? 'selected' : ''; ?>>Critical</option>
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
                                       <?php echo (isset($_POST['gps_connected'])) ? 'checked' : ''; ?>>
                                <label for="gps_connected" class="form-label">GPS Connected</label>
                            </div>
                            <div class="help-text">Enable GPS tracking for this elephant</div>
                        </div>
                        
                        <!-- GPS Device Information -->
                        <div id="gpsSettings" style="display: none; background: var(--bg-secondary); padding: 1rem; border-radius: 8px; border: 1px solid var(--border-primary);">
                            <div class="form-grid">
                                <div class="form-group">
                                    <label for="gps_device_id" class="form-label">GPS Device</label>
                                    <select id="gps_device_id" name="gps_device_id" class="form-select">
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
                                                if (isset($_POST['gps_device_id']) && $_POST['gps_device_id'] === $collar['collar_id']) {
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
                                    <label for="gps_installation_date" class="form-label">GPS Installation Date</label>
                                    <input type="date" id="gps_installation_date" name="gps_installation_date" class="form-input" 
                                           value="<?php echo htmlspecialchars($_POST['gps_installation_date'] ?? ''); ?>">
                                    <div class="help-text">When the GPS device was installed on the elephant</div>
                                </div>
                                
                                <div class="form-group">
                                    <label for="gps_notes" class="form-label">GPS Notes</label>
                                    <textarea id="gps_notes" name="gps_notes" class="form-textarea" 
                                              placeholder="Any special notes about the GPS installation or elephant-specific settings..."><?php echo htmlspecialchars($_POST['gps_notes'] ?? ''); ?></textarea>
                                    <div class="help-text">Special notes about GPS installation or elephant-specific settings</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Notes -->
                    <div class="form-group full-width">
                        <label for="notes" class="form-label">Additional Notes</label>
                        <textarea id="notes" name="notes" class="form-textarea" 
                                  placeholder="Enter any additional information about the elephant..."><?php echo htmlspecialchars($_POST['notes'] ?? ''); ?></textarea>
                        <div class="help-text">Any special characteristics, behavior patterns, or important notes</div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-large">
                            🐘 Add Elephant
                        </button>
                        <button type="button" class="btn btn-secondary btn-large" onclick="location.href='elephants.php'">
                            ❌ Cancel
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
        document.getElementById('addElephantForm').addEventListener('submit', function(e) {
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
    </script>
</body>
</html>
