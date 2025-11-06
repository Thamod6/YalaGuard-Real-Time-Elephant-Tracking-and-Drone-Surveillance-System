<?php
/**
 * YalaGuard Manual Alerts Page
 * 
 * This page allows users to manually send alerts to authorities
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

// Initialize empty arrays
$elephants = [];
$authorities = [];

// Only try to load data if we can safely do so
try {
    // Check if MongoDB extension is available first
    if (extension_loaded('mongodb')) {
        require_once '../config/database.php';
        
        // Get elephants
        $elephantsCollection = getCollection('elephants');
        $elephantsCursor = $elephantsCollection->find(['active' => true], ['sort' => ['name' => 1]]);
        foreach ($elephantsCursor as $elephant) {
            $elephants[] = [
                'id' => (string)$elephant['_id'],
                'name' => $elephant['name'],
                'type' => $elephant['type']
            ];
        }
        
        // Get authorities
        $authoritiesCollection = getCollection('authorities');
        $authoritiesCursor = $authoritiesCollection->find(['active' => true], ['sort' => ['name' => 1]]);
        foreach ($authoritiesCursor as $authority) {
            $authorities[] = [
                'id' => (string)$authority['_id'],
                'name' => $authority['name'],
                'role' => $authority['role'],
                'organization' => $authority['organization'],
                'phone' => $authority['phone'],
                'email' => $authority['email']
            ];
        }
    } else {
        $warning_message = "MongoDB extension not available. Some features may be limited.";
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
    <title>YalaGuard - Manual Alerts</title>
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
            <h1>🚨 Manual Alert System</h1>
            <p>Send immediate email alerts to authorities for urgent situations</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>
        
        <?php if (isset($warning_message)): ?>
            <div class="alert alert-warning"><?php echo htmlspecialchars($warning_message); ?></div>
        <?php endif; ?>

        <!-- Manual Alert Form -->
        <div class="form-container">
            <form id="manualAlertForm">
                <!-- Alert Type Selection -->
                <div class="form-section">
                    <h3>📋 Alert Type</h3>
                    <div class="form-group">
                        <label for="alert_type">Select Alert Type *</label>
                        <select id="alert_type" name="alert_type" required>
                            <option value="">Choose alert type...</option>
                            <option value="emergency">🚨 Emergency Situation</option>
                            <option value="wildlife_conflict">🐘 Human-Wildlife Conflict</option>
                            <option value="poaching_alert">⚠️ Poaching Alert</option>
                            <option value="health_emergency">🏥 Health Emergency</option>
                            <option value="weather_alert">🌦️ Weather Alert</option>
                            <option value="custom">✏️ Custom Message</option>
                        </select>
                    </div>
                    
                    <div class="form-group" id="custom_message_group" style="display: none;">
                        <label for="custom_message">Custom Alert Message *</label>
                        <textarea id="custom_message" name="custom_message" rows="4" placeholder="Enter your custom alert message..."></textarea>
                    </div>
                </div>

                <!-- Elephant Selection -->
                <div class="form-section">
                    <h3>🐘 Elephant Information</h3>
                    <div class="form-group">
                        <label for="elephant_id">Select Elephant (Optional)</label>
                        <select id="elephant_id" name="elephant_id">
                            <option value="">No specific elephant</option>
                            <?php foreach ($elephants as $elephant): ?>
                                <option value="<?php echo htmlspecialchars($elephant['id']); ?>">
                                    <?php echo htmlspecialchars($elephant['name']); ?> (<?php echo htmlspecialchars($elephant['type']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="location_lat">Location Latitude</label>
                        <input type="number" id="location_lat" name="location_lat" step="any" placeholder="e.g., 6.2614">
                    </div>
                    
                    <div class="form-group">
                        <label for="location_lng">Location Longitude</label>
                        <input type="number" id="location_lng" name="location_lng" step="any" placeholder="e.g., 81.5256">
                    </div>
                    
                    <div id="coordinates_display" class="form-container" style="display: none;">
                        <strong>Coordinates:</strong> <span id="coordinates_text"></span>
                    </div>
                </div>

                <!-- Authority Selection -->
                <div class="form-section">
                    <h3>👥 Select Recipients</h3>
                    <div class="form-group">
                        <label>
                            <input type="checkbox" id="select_all_authorities"> Select All Authorities
                        </label>
                    </div>
                    
                    <div class="recipient-grid">
                        <?php foreach ($authorities as $authority): ?>
                            <div class="recipient-card">
                                <label>
                                    <input type="checkbox" name="authorities[]" value="<?php echo htmlspecialchars($authority['id']); ?>" class="authority-checkbox">
                                    <strong><?php echo htmlspecialchars($authority['name']); ?></strong><br>
                                    <small><?php echo htmlspecialchars($authority['role']); ?> at <?php echo htmlspecialchars($authority['organization']); ?></small><br>
                                    <small>📧 <?php echo htmlspecialchars($authority['email']); ?></small>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Alert Preview -->
                <div class="alert-preview" id="alert_preview" style="display: none;">
                    <h4>📋 Alert Preview</h4>
                    <div id="preview_content"></div>
                </div>

                <!-- Send Button -->
                <div class="form-group">
                    <button type="submit" class="send-button" id="send_alert_btn">
                        📧 Send Email Alert to Authorities
                    </button>
                </div>
            </form>
        </div>

        <!-- Status Messages -->
        <div id="status_message" class="status-message"></div>
    </div>

    <script>
        // Alert type change handler
        document.getElementById('alert_type').addEventListener('change', function() {
            const customGroup = document.getElementById('custom_message_group');
            if (this.value === 'custom') {
                customGroup.style.display = 'block';
                document.getElementById('custom_message').required = true;
            } else {
                customGroup.style.display = 'none';
                document.getElementById('custom_message').required = false;
            }
            updatePreview();
        });

        // Location coordinates change handler
        document.getElementById('location_lat').addEventListener('input', updateCoordinatesDisplay);
        document.getElementById('location_lng').addEventListener('input', updateCoordinatesDisplay);

        // Select all authorities handler
        document.getElementById('select_all_authorities').addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('.authority-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updatePreview();
        });

        // Individual authority checkbox handler
        document.querySelectorAll('.authority-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updatePreview);
        });

        // Form submission handler
        document.getElementById('manualAlertForm').addEventListener('submit', function(e) {
            e.preventDefault();
            sendManualAlert();
        });

        function updateCoordinatesDisplay() {
            const lat = document.getElementById('location_lat').value;
            const lng = document.getElementById('location_lng').value;
            const display = document.getElementById('coordinates_display');
            const text = document.getElementById('coordinates_text');
            
            if (lat && lng) {
                text.textContent = `${lat}, ${lng}`;
                display.style.display = 'block';
            } else {
                display.style.display = 'none';
            }
            updatePreview();
        }

        function updatePreview() {
            const alertType = document.getElementById('alert_type').value;
            const elephantId = document.getElementById('elephant_id').value;
            const customMessage = document.getElementById('custom_message').value;
            const lat = document.getElementById('location_lat').value;
            const lng = document.getElementById('location_lng').value;
            const selectedAuthorities = document.querySelectorAll('.authority-checkbox:checked');
            
            const preview = document.getElementById('alert_preview');
            const content = document.getElementById('preview_content');
            
            if (!alertType || selectedAuthorities.length === 0) {
                preview.style.display = 'none';
                return;
            }
            
            let previewText = `<strong>Alert Type:</strong> ${getAlertTypeName(alertType)}<br>`;
            
            if (elephantId) {
                const elephantSelect = document.getElementById('elephant_id');
                const elephantName = elephantSelect.options[elephantSelect.selectedIndex].text;
                previewText += `<strong>Elephant:</strong> ${elephantName}<br>`;
            }
            
            if (lat && lng) {
                previewText += `<strong>Location:</strong> ${lat}, ${lng}<br>`;
            }
            
            if (alertType === 'custom' && customMessage) {
                previewText += `<strong>Message:</strong> ${customMessage}<br>`;
            }
            
            previewText += `<strong>Recipients:</strong> ${selectedAuthorities.length} authority(ies) will receive email alert<br>`;
            
            content.innerHTML = previewText;
            preview.style.display = 'block';
        }

        function getAlertTypeName(type) {
            const types = {
                'emergency': '🚨 Emergency Situation',
                'wildlife_conflict': '🐘 Human-Wildlife Conflict',
                'poaching_alert': '⚠️ Poaching Alert',
                'health_emergency': '🏥 Health Emergency',
                'weather_alert': '🌦️ Weather Alert',
                'custom': '✏️ Custom Message'
            };
            return types[type] || type;
        }

        function sendManualAlert() {
            const form = document.getElementById('manualAlertForm');
            const formData = new FormData(form);
            
            // Validate form
            if (!formData.get('alert_type')) {
                showStatus('Please select an alert type', 'error');
                return;
            }
            
            const selectedAuthorities = document.querySelectorAll('.authority-checkbox:checked');
            if (selectedAuthorities.length === 0) {
                showStatus('Please select at least one authority', 'error');
                return;
            }
            
            if (formData.get('alert_type') === 'custom' && !formData.get('custom_message').trim()) {
                showStatus('Please enter a custom message', 'error');
                return;
            }
            
            // Disable send button
            const sendBtn = document.getElementById('send_alert_btn');
            sendBtn.disabled = true;
            sendBtn.textContent = '📤 Sending Email Alert...';
            
            // Process coordinates - ensure they are valid numbers
            let latitude = null;
            let longitude = null;
            
            const latInput = formData.get('location_lat');
            const lngInput = formData.get('location_lng');
            
            if (latInput && lngInput) {
                latitude = parseFloat(latInput);
                longitude = parseFloat(lngInput);
                
                // Validate coordinates
                if (isNaN(latitude) || isNaN(longitude)) {
                    showStatus('Invalid coordinates. Please enter valid numbers.', 'error');
                    return;
                }
                
                if (latitude < -90 || latitude > 90 || longitude < -180 || longitude > 180) {
                    showStatus('Coordinates out of range. Latitude: -90 to 90, Longitude: -180 to 180', 'error');
                    return;
                }
            }
            
            // Prepare data for API
            const alertData = {
                alert_type: formData.get('alert_type'),
                custom_message: formData.get('custom_message'),
                elephant_id: formData.get('elephant_id') || null,
                location: {
                    latitude: latitude,
                    longitude: longitude
                },
                authorities: Array.from(selectedAuthorities).map(cb => cb.value),
                sent_by: '<?php echo $user['user_id']; ?>',
                sent_by_name: '<?php echo htmlspecialchars($user['full_name']); ?>'
            };
            
            // Send to API
            fetch('../api/manual-alert.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(alertData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    showStatus('Email alert sent successfully!', 'success');
                    form.reset();
                    document.getElementById('alert_preview').style.display = 'none';
                } else {
                    showStatus('Failed to send email alert: ' + data.message, 'error');
                }
            })
            .catch(error => {
                showStatus('Error sending email alert: ' + error.message, 'error');
            })
            .finally(() => {
                // Re-enable send button
                sendBtn.disabled = false;
                sendBtn.textContent = '📧 Send Email Alert to Authorities';
            });
        }

        function showStatus(message, type) {
            const statusDiv = document.getElementById('status_message');
            statusDiv.textContent = message;
            statusDiv.className = `status-message ${type}`;
            statusDiv.style.display = 'block';
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 5000);
        }
    </script>
</body>
</html>
