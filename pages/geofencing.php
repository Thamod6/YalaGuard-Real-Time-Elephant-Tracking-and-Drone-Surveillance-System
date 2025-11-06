<?php
/**
 * YalaGuard Geofencing Management Page
 * 
 * This page provides a user interface for managing geofences.
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

// Get elephants for dropdown
$elephants = [];
try {
    require_once '../config/database.php';
    $collection = getCollection('elephants');
    $cursor = $collection->find([], ['sort' => ['name' => 1]]); // Show all elephants
    
    foreach ($cursor as $elephant) {
        $elephants[] = [
            'id' => (string)$elephant['_id'],
            'name' => $elephant['name'],
            'type' => $elephant['type']
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
    <title>YalaGuard - Geofencing Management</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <!-- Leaflet CSS for maps -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

</head>
<body>
    <!-- Simple Navbar -->
    <nav class="navbar">
        <div class="navbar-container">
            <a href="dashboard.php" class="navbar-logo">🐘 YalaGuard</a>
            <ul class="navbar-nav">
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="elephants.php">Elephants</a></li>
                <li><a href="geofencing.php" class="active">Geofencing</a></li>
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
        <div class="welcome-card">
            <h1 class="welcome-title">🗺️ Geofencing Management</h1>
            <p class="welcome-subtitle">Create virtual boundaries and monitor elephant locations for wildlife protection</p>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <!-- Map and Geofences Display -->
        <div class="map-container">
            <div class="map-header">
                <h2>🗺️ Yala National Park - Geofence Map</h2>
                                 <div class="map-controls">
                     <button class="btn btn-primary" onclick="window.location.href='add-geofence.php'">➕ Add New Geofence</button>
                     <button class="btn btn-secondary" onclick="refreshGeofences()">🔄 Refresh Map</button>
                     <button class="btn btn-info" onclick="loadGeofences()">🔄 Reload Data</button>
                 </div>
            </div>
            
            <div class="map-tip">
                💡 <strong>Tip:</strong> Click anywhere on the map to see coordinates! Use this to easily create geofences at specific locations.
            </div>
            
            <div id="map"></div>
            
            <div class="map-legend">
                <div class="legend-item">
                    <span class="legend-color restricted"></span>
                    <span>Restricted Zones</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color safe"></span>
                    <span>Safe Zones</span>
                </div>
                <div class="legend-item">
                    <span class="legend-color monitoring"></span>
                    <span>Monitoring Zones</span>
                </div>
            </div>
        </div>

        <!-- Geofences List -->
        <div id="geofencesList">
            <h2>🗺️ Active Geofences</h2>
            <div id="geofencesContainer">
                <!-- Geofences will be loaded here -->
            </div>
            
            <!-- Active Zones Table -->
            <div class="zones-table-container">
                <h3>📊 Active Zones Details</h3>
                <div class="table-responsive">
                    <table class="zones-table">
                        <thead>
                            <tr>
                                <th>Zone ID</th>
                                <th>Zone Name</th>
                                <th>Type</th>
                                <th>Assigned Elephant</th>
                                <th>Location</th>
                                <th>Radius</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="zonesTableBody">
                            <!-- Zones will be loaded here -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        let map;
        let geofenceLayers = [];

        // Initialize the map
        function initMap() {
            // Yala National Park actual pinpoint coordinates
            const yalaCenter = [6.2614, 81.5167];
            
            // Create map centered on Yala National Park
            map = L.map('map').setView(yalaCenter, 12);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 18
            }).addTo(map);
            
            // Add click event to show coordinates
            map.on('click', onMapClick);
            
            // Load geofences
            loadGeofences();
        }
        
        // Handle map clicks to show coordinates
        function onMapClick(e) {
            const lat = e.latlng.lat.toFixed(6);
            const lng = e.latlng.lng.toFixed(6);
            
            // Remove previous coordinate popup if exists
            if (window.coordinatePopup) {
                map.removeLayer(window.coordinatePopup);
            }
            
            // Create a popup with coordinates
            window.coordinatePopup = L.popup()
                .setLatLng(e.latlng)
                .setContent(`
                    <div style="text-align: center;">
                        <strong>📍 Clicked Location</strong><br>
                        <strong>Latitude:</strong> ${lat}°N<br>
                        <strong>Longitude:</strong> ${lng}°E<br>
                        <br>
                        <button onclick="copyCoordinates('${lat}', '${lng}')" 
                                style="background: #007bff; color: white; border: none; padding: 5px 10px; border-radius: 3px; cursor: pointer;">
                            Copy Coordinates
                        </button>
                    </div>
                `)
                .openOn(map);
        }
        
        // Copy coordinates to clipboard
        function copyCoordinates(lat, lng) {
            const coordText = `${lat}, ${lng}`;
            navigator.clipboard.writeText(coordText).then(() => {
                // Show success message
                const popup = window.coordinatePopup;
                if (popup) {
                    popup.setContent(`
                        <div style="text-align: center;">
                            <strong>✅ Coordinates Copied!</strong><br>
                            <strong>Latitude:</strong> ${lat}°N<br>
                            <strong>Longitude:</strong> ${lng}°E<br>
                            <br>
                            <small style="color: #28a745;">${coordText} copied to clipboard</small>
                        </div>
                    `);
                }
            }).catch(err => {
                console.error('Failed to copy coordinates:', err);
                alert('Coordinates: ' + coordText);
            });
        }

        // Load geofences from API
        async function loadGeofences() {
            try {
                const response = await fetch('../api/geofence.php?action=list');
                const data = await response.json();
                
                if (data.status === 'success') {
                    displayGeofencesOnMap(data.data.geofences);
                    displayGeofencesInTable(data.data.geofences);
                } else {
                    console.error('Failed to load geofences:', data.message);
                }
            } catch (error) {
                console.error('Error loading geofences:', error);
            }
        }

        // Display geofences on the map
        function displayGeofencesOnMap(geofences) {
            // Clear existing geofence layers
            geofenceLayers.forEach(layer => map.removeLayer(layer));
            geofenceLayers = [];
            
            if (geofences.length === 0) {
                // Show message on map if no geofences
                const noGeofencesDiv = document.createElement('div');
                noGeofencesDiv.innerHTML = `
                    <div class="no-geofences-message">
                        <h3>No Geofences Found</h3>
                        <p>Create your first geofence to start monitoring elephant locations.</p>
                        <button class="btn btn-primary" onclick="window.location.href='add-geofence.php'">Create Geofence</button>
                    </div>
                `;
                
                const noGeofencesControl = L.control({position: 'topright'});
                noGeofencesControl.onAdd = function() {
                    const div = L.DomUtil.create('div', 'info legend');
                    div.innerHTML = noGeofencesDiv.innerHTML;
                    return div;
                };
                noGeofencesControl.addTo(map);
                geofenceLayers.push(noGeofencesControl);
                return;
            }

            // Add each geofence to the map
            geofences.forEach(geofence => {
                const center = [geofence.lat, geofence.lng];
                const radius = geofence.radius;
                
                // Set color based on geofence type
                let color, fillColor;
                switch(geofence.type) {
                    case 'restricted':
                        color = '#f44336';
                        fillColor = '#ffcdd2';
                        break;
                    case 'safe':
                        color = '#2196F3';
                        fillColor = '#bbdefb';
                        break;
                    case 'monitoring':
                        color = '#FF9800';
                        fillColor = '#ffe0b2';
                        break;
                    default:
                        color = '#4CAF50';
                        fillColor = '#c8e6c9';
                }
                
                // Create geofence circle
                const geofenceCircle = L.circle(center, {
                    color: color,
                    fillColor: fillColor,
                    fillOpacity: 0.3,
                    radius: radius
                }).addTo(map);
                
                // Create center marker
                const marker = L.marker(center).addTo(map);
                
                // Create popup content
                const elephantDisplay = geofence.elephant_name ? 
                    `<p><strong>🐘 Assigned Elephant:</strong> ${geofence.elephant_name}</p>` : 
                    '<p><strong>🐘 Assigned Elephant:</strong> <em>No elephant assigned</em></p>';
                
                const popupContent = `
                    <div class="geofence-info">
                        <h4>${geofence.name}</h4>
                        <p><strong>ID:</strong> ${geofence.geofence_id}</p>
                        <p><strong>Type:</strong> ${geofence.type}</p>
                        ${elephantDisplay}
                        <p><strong>Radius:</strong> ${geofence.radius}m</p>
                        <p><strong>Created:</strong> ${geofence.created_at}</p>
                        ${geofence.description ? `<p><strong>Description:</strong> ${geofence.description}</p>` : ''}
                        <div style="margin-top: 1rem;">
                            <button class="btn-table btn-test" onclick="testGeofence('${geofence.geofence_id}', '${geofence.elephant_id}')">Test Location</button>
                            <button class="btn-table btn-assign" onclick="assignElephant('${geofence.geofence_id}')">🐘 Assign</button>
                            <button class="btn-table btn-edit" onclick="editGeofence('${geofence.geofence_id}')">Edit</button>
                            <button class="btn-table btn-delete" onclick="deleteGeofence('${geofence.geofence_id}')">Delete</button>
                        </div>
                    </div>
                `;
                
                marker.bindPopup(popupContent);
                
                // Add layers to tracking array
                geofenceLayers.push(geofenceCircle);
                geofenceLayers.push(marker);
            });
            
            // Fit map to show all geofences
            if (geofences.length > 0) {
                const group = new L.featureGroup(geofenceLayers);
                map.fitBounds(group.getBounds().pad(0.1));
            }
        }

        // Refresh geofences
        function refreshGeofences() {
            loadGeofences();
        }
        
        // Display geofences in the table
        function displayGeofencesInTable(geofences) {
            const tableBody = document.getElementById('zonesTableBody');
            
            if (!tableBody) return;
            
            if (geofences.length === 0) {
                tableBody.innerHTML = `
                    <tr>
                        <td colspan="7" class="no-zones-message">
                            No active geofences found. Create your first geofence to start monitoring elephant locations.
                        </td>
                    </tr>
                `;
                return;
            }
            
            let tableHTML = '';
            
            geofences.forEach(geofence => {
                const typeBadge = `<span class="zone-type-badge ${geofence.type}">${geofence.type}</span>`;
                const location = `${geofence.lat.toFixed(6)}, ${geofence.lng.toFixed(6)}`;
                const createdDate = new Date(geofence.created_at).toLocaleDateString();
                
                const elephantInfo = geofence.elephant_name ? 
                    `<span class="elephant-assigned">🐘 ${geofence.elephant_name}</span>` : 
                    '<span class="elephant-unassigned">No elephant assigned</span>';
                
                tableHTML += `
                    <tr>
                        <td><strong>${geofence.geofence_id}</strong></td>
                        <td>${geofence.name}</td>
                        <td>${typeBadge}</td>
                        <td>${elephantInfo}</td>
                        <td>${location}</td>
                        <td>${geofence.radius}m</td>
                        <td>${createdDate}</td>
                        <td>
                            <div class="zone-actions">
                                <button class="btn-table btn-test" onclick="testGeofence('${geofence.geofence_id}', '${geofence.elephant_id || ''}')">Test</button>
                                <button class="btn-table btn-assign" onclick="assignElephant('${geofence.geofence_id}')">🐘 Assign</button>
                                <button class="btn-table btn-edit" onclick="editGeofence('${geofence.geofence_id}')">Edit</button>
                                <button class="btn-table btn-delete" onclick="deleteGeofence('${geofence.geofence_id}')">Delete</button>
                    </div>
                        </td>
                    </tr>
                `;
            });
            
            tableBody.innerHTML = tableHTML;
        }

        // Test geofence with current location
        async function testGeofence(geofenceId, elephantId) {
            // For demo purposes, use a sample location
            // In a real app, you'd get the current GPS location
            const testLocation = {
                elephant_id: elephantId || 'TEST_ELEPHANT',
                lat: 6.2614, // Actual Yala Park coordinates
                lng: 81.5167
            };

            try {
                const response = await fetch('../api/geofence.php?action=check', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(testLocation)
                });

                const result = await response.json();
                
                if (result.status === 'success') {
                    let message = `Elephant ${elephantId} at location ${testLocation.lat}, ${testLocation.lng}\n\n`;
                    
                    if (result.data.results.length > 0) {
                        result.data.results.forEach(check => {
                            message += `Geofence: ${check.geofence_name}\n`;
                            message += `Status: ${check.status}\n`;
                            message += `Alert Level: ${check.alert_level}\n`;
                            message += `Distance: ${check.distance_to_center}m\n\n`;
                        });
                    } else {
                        message += 'No geofences found for this elephant.';
                    }
                    
                    alert(message);
                } else {
                    alert('Error checking geofence: ' + result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error testing geofence. Please try again.');
            }
        }

        // Edit geofence
        function editGeofence(geofenceId) {
            window.location.href = `edit-geofence.php?id=${geofenceId}`;
        }

        // Delete geofence
        async function deleteGeofence(geofenceId) {
            if (confirm('Are you sure you want to delete this geofence? This action cannot be undone.')) {
                try {
                    const response = await fetch('../api/geofence.php?action=delete', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({ geofence_id: geofenceId })
                    });

                    const result = await response.json();
                    
                    if (result.status === 'success') {
                        alert('Geofence deleted successfully!');
                        // Refresh the map to show updated geofences
                        loadGeofences();
                    } else {
                        alert('Error deleting geofence: ' + result.message);
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('Error deleting geofence. Please try again.');
                }
            }
        }

        // Assign elephant to geofence
        function assignElephant(geofenceId) {
            console.log('Assigning elephant to geofence:', geofenceId);
            if (geofenceId) {
                window.location.href = `assign-elephant.php?id=${geofenceId}`;
            } else {
                console.error('No geofence ID provided');
                alert('Error: No geofence ID provided');
            }
        }

        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            initGPSControls(); // Initialize GPS controls
        });
        
        // Initialize GPS control panel
        function initGPSControls() {
            const updateIntervalSelect = document.getElementById('gpsUpdateInterval');
            const timeRangeSelect = document.getElementById('gpsTimeRange');
            const refreshBtn = document.getElementById('refreshGPSBtn');
            const toggleBtn = document.getElementById('toggleGPSTracking');
            
            let isTrackingActive = true;
            let locationUpdateInterval;
            
            // Start GPS tracking
            startGPSTracking();
            
            // Update interval change
            updateIntervalSelect.addEventListener('change', function() {
                if (isTrackingActive) {
                    clearInterval(locationUpdateInterval);
                    const newInterval = parseInt(this.value);
                    locationUpdateInterval = setInterval(loadGPSLocations, newInterval);
                    updateGPSStatus('GPS tracking interval updated to ' + (newInterval / 1000) + ' seconds');
                }
            });
            
            // Time range change
            timeRangeSelect.addEventListener('change', function() {
                loadGPSLocations();
                updateGPSStatus('GPS time range updated to last ' + this.value + ' hours');
            });
            
            // Manual refresh
            refreshBtn.addEventListener('click', function() {
                loadGPSLocations();
                updateGPSStatus('GPS locations refreshed manually');
            });
            
            // Toggle tracking
            toggleBtn.addEventListener('click', function() {
                if (isTrackingActive) {
                    clearInterval(locationUpdateInterval);
                    isTrackingActive = false;
                    toggleBtn.textContent = '▶️ Resume Updates';
                    toggleBtn.className = 'btn btn-warning';
                    updateGPSStatus('🟡 GPS Updates Paused');
                } else {
                    const interval = parseInt(updateIntervalSelect.value);
                    locationUpdateInterval = setInterval(loadGPSLocations, interval);
                    isTrackingActive = true;
                    toggleBtn.textContent = '⏸️ Pause Updates';
                    toggleBtn.className = 'btn btn-success';
                    updateGPSStatus('🟢 GPS Updates Active');
                }
            });
        }
        
        // Start real-time GPS tracking
        function startGPSTracking() {
            // Load initial GPS locations
            loadGPSLocations();
            
            // Update locations every 30 seconds
            const interval = parseInt(document.getElementById('gpsUpdateInterval').value);
            locationUpdateInterval = setInterval(loadGPSLocations, interval);
        }
        
        // Load GPS locations from API
        async function loadGPSLocations() {
            try {
                const timeRange = document.getElementById('gpsTimeRange').value;
                const response = await fetch(`../api/get-collar-locations.php?hours=${timeRange}&limit=50`);
                const data = await response.json();
                
                if (data.success) {
                    updateGPSMarkersOnMap(data.data.locations, data.data.trails);
                    updateGPSStatus('GPS locations updated successfully');
                } else {
                    console.error('Failed to load GPS locations:', data.error);
                    updateGPSStatus('Failed to load GPS locations');
                }
            } catch (error) {
                console.error('Error loading GPS locations:', error);
                updateGPSStatus('Error loading GPS locations');
            }
        }
        
        // Update GPS markers on the map
        function updateGPSMarkersOnMap(locations, trails) {
            // Clear old GPS markers and trails
            if (window.gpsMarkers) {
                window.gpsMarkers.forEach(marker => {
                    if (map.hasLayer(marker)) {
                        map.removeLayer(marker);
                    }
                });
            }
            if (window.gpsTrails) {
                window.gpsTrails.forEach(trail => {
                    if (map.hasLayer(trail)) {
                        map.removeLayer(trail);
                    }
                });
            }
            
            window.gpsMarkers = [];
            window.gpsTrails = [];
            
            if (!locations || locations.length === 0) {
                updateGPSStatus('No GPS collar locations found');
                return;
            }
            
            locations.forEach(location => {
                if (location.latitude && location.longitude) {
                    // Create elephant marker
                    const marker = L.marker([location.latitude, location.longitude], {
                        icon: createElephantIcon(location)
                    });
                    
                    // Create popup with elephant info
                    const popupContent = createElephantPopup(location);
                    marker.bindPopup(popupContent);
                    
                    // Add marker to map
                    marker.addTo(map);
                    window.gpsMarkers.push(marker);
                    
                    // Add movement trail if available
                    if (trails && trails[location.collar_id]) {
                        const trailCoords = trails[location.collar_id].map(point => [point.latitude, point.longitude]);
                        if (trailCoords.length > 1) {
                            const trail = L.polyline(trailCoords, {
                                color: getElephantPathColor(location),
                                weight: 3,
                                opacity: 0.7
                            });
                            
                            trail.addTo(map);
                            window.gpsTrails.push(trail);
                        }
                    }
                }
            });
            
            updateGPSStatus(`Updated ${window.gpsMarkers.length} GPS collar locations on map`);
        }
        
        // Create custom elephant icon
        function createElephantIcon(location) {
            const batteryLevel = location.battery_level || 100;
            let iconColor = '#64ffda'; // Default green
            
            if (batteryLevel < 20) iconColor = '#f44336'; // Red for low battery
            else if (batteryLevel < 50) iconColor = '#ff9800'; // Orange for medium battery
            
            return L.divIcon({
                html: `<div style="width: 40px; height: 40px; border-radius: 50%; background: ${iconColor}; display: flex; align-items: center; justify-content: center; border: 3px solid white; color: white; font-size: 20px;">🐘</div>`,
                className: 'elephant-marker',
                iconSize: [40, 40],
                iconAnchor: [20, 20]
            });
        }
        
        // Create elephant popup content
        function createElephantPopup(location) {
            const elephantName = location.elephant_name || 'Unknown Elephant';
            const collarName = location.collar_name || 'Unknown Collar';
            const batteryLevel = location.battery_level || 'Unknown';
            const signalStrength = location.signal_strength || 'Unknown';
            const speed = location.speed || 0;
            const lastUpdate = location.timestamp ? new Date(location.timestamp).toLocaleString() : 'Unknown';
            
            return `
                <div style="text-align: center; min-width: 250px;">
                    <h4 style="margin: 0 0 10px 0; color: #64ffda;">🐘 ${elephantName}</h4>
                    <p><strong>Collar:</strong> ${collarName}</p>
                    <p><strong>Location:</strong> ${location.latitude.toFixed(6)}, ${location.longitude.toFixed(6)}</p>
                    <p><strong>Battery:</strong> ${batteryLevel}%</p>
                    <p><strong>Signal:</strong> ${signalStrength} dBm</p>
                    <p><strong>Speed:</strong> ${speed.toFixed(1)} km/h</p>
                    <p><strong>Last Update:</strong> ${lastUpdate}</p>
                    <button onclick="centerMapOnElephant(${location.latitude}, ${location.longitude})" 
                            style="background: #64ffda; color: #0f0f23; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; margin-top: 10px;">
                        🎯 Center Map
                    </button>
                </div>
            `;
        }
        
        // Get elephant path color
        function getElephantPathColor(location) {
            if (!location) return '#64ffda';
            
            // Generate consistent color based on elephant name
            const colors = ['#64ffda', '#4fc3f7', '#ff9800', '#4caf50', '#9c27b0', '#f44336'];
            const nameHash = (location.elephant_name || location.collar_id).split('').reduce((a, b) => a + b.charCodeAt(0), 0);
            return colors[nameHash % colors.length];
        }
        
        // Center map on elephant location
        function centerMapOnElephant(lat, lng) {
            map.setView([lat, lng], 15);
        }
        
        // Update GPS status display
        function updateGPSStatus(message) {
            const statusText = document.getElementById('gpsStatusText');
            const lastUpdateTime = document.getElementById('lastUpdateTime');
            
            if (statusText) statusText.textContent = message;
            if (lastUpdateTime) lastUpdateTime.textContent = 'Last update: ' + new Date().toLocaleTimeString();
        }
    </script>
</body>
</html>
