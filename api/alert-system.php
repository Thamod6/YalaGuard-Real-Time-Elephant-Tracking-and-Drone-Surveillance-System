<?php
/**
 * YalaGuard Enhanced Alert System
 * 
 * This system monitors elephants for:
 * 1. Geofence violations (elephant going out of zone)
 * 2. Stationary elephants (speed = 0 for extended periods)
 * 
 * It automatically generates alerts and sends them to authorities
 */

require_once '../config/database.php';
require_once 'sms-service.php';
require_once 'email-service.php';

class AlertSystem {
    private $db;
    private $smsService;
    private $emailService;
    
    public function __construct() {
        $this->db = getDatabase();
        $this->smsService = new SMSService();
        $this->emailService = new EmailService();
    }
    
    /**
     * Main function to check all elephants and generate alerts
     * This should be called every hour via cron job
     */
    public function checkAllElephants() {
        try {
            $results = [
                'timestamp' => date('Y-m-d H:i:s'),
                'elephants_checked' => 0,
                'alerts_generated' => 0,
                'alerts_sent' => 0,
                'errors' => []
            ];
            
            // Get all active elephants with GPS tracking
            $elephantsCollection = $this->db->selectCollection('elephants');
            $elephants = $elephantsCollection->find([
                'active' => true,
                'gps_connected' => true
            ]);
            
            foreach ($elephants as $elephant) {
                $results['elephants_checked']++;
                
                try {
                    $elephantAlerts = $this->checkElephantStatus($elephant);
                    $results['alerts_generated'] += count($elephantAlerts);
                    
                    if (!empty($elephantAlerts)) {
                        $sentCount = $this->sendAlertsToAuthorities($elephantAlerts);
                        $results['alerts_sent'] += $sentCount;
                    }
                    
                } catch (Exception $e) {
                    $results['errors'][] = "Elephant {$elephant['name']}: " . $e->getMessage();
                }
            }
            
            // Log the check results
            $this->logSystemCheck($results);
            
            return $results;
            
        } catch (Exception $e) {
            error_log('Alert System Error: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Check individual elephant status for violations
     * 
     * @param array $elephant
     * @return array Array of generated alerts
     */
    private function checkElephantStatus($elephant) {
        $alerts = [];
        
        // Check geofence violations
        $geofenceAlerts = $this->checkGeofenceViolations($elephant);
        $alerts = array_merge($alerts, $geofenceAlerts);
        
        // Check stationary elephants
        $stationaryAlerts = $this->checkStationaryElephant($elephant);
        $alerts = array_merge($alerts, $stationaryAlerts);
        
        return $alerts;
    }
    
    /**
     * Check if elephant has violated any geofences
     * 
     * @param array $elephant
     * @return array Array of geofence violation alerts
     */
    private function checkGeofenceViolations($elephant) {
        $alerts = [];
        
        // Get elephant's current location
        $currentLocation = $this->getElephantCurrentLocation($elephant['_id']);
        if (!$currentLocation) {
            return $alerts; // No location data available
        }
        
        // Get all geofences assigned to this elephant
        $geofencesCollection = $this->db->selectCollection('geofences');
        $geofences = $geofencesCollection->find([
            'assigned_elephants' => (string)$elephant['_id'],
            'active' => true
        ]);
        
        foreach ($geofences as $geofence) {
            $isInside = $this->isPointInGeofence(
                $currentLocation['latitude'],
                $currentLocation['longitude'],
                $geofence['lat'],
                $geofence['lng'],
                $geofence['radius']
            );
            
            if (!$isInside) {
                // Check if we already sent an alert for this violation recently
                $recentAlert = $this->checkRecentGeofenceAlert($elephant['_id'], $geofence['_id']);
                
                if (!$recentAlert) {
                    $alert = $this->createGeofenceViolationAlert($elephant, $geofence, $currentLocation);
                    $alerts[] = $alert;
                }
            }
        }
        
        return $alerts;
    }
    
    /**
     * Check if elephant has been stationary for too long
     * 
     * @param array $elephant
     * @return array Array of stationary alerts
     */
    private function checkStationaryElephant($elephant) {
        $alerts = [];
        
        // Get elephant's recent movement data
        $movementData = $this->getElephantMovementData($elephant['_id']);
        if (!$movementData) {
            return $alerts;
        }
        
        // Check if elephant has been stationary (speed = 0) for more than 24 hours
        $stationaryDuration = $this->calculateStationaryDuration($movementData);
        
        if ($stationaryDuration > 24) { // 24 hours
            // Check if we already sent an alert for this recently
            $recentAlert = $this->checkRecentStationaryAlert($elephant['_id']);
            
            if (!$recentAlert) {
                $alert = $this->createStationaryAlert($elephant, $movementData, $stationaryDuration);
                $alerts[] = $alert;
            }
        }
        
        return $alerts;
    }
    
    /**
     * Get elephant's current GPS location
     * 
     * @param string $elephantId
     * @return array|null
     */
    private function getElephantCurrentLocation($elephantId) {
        $locationsCollection = $this->db->selectCollection('gps_locations');
        
        $location = $locationsCollection->findOne(
            ['elephant_id' => (string)$elephantId],
            ['sort' => ['timestamp' => -1]]
        );
        
        if ($location) {
            return [
                'latitude' => (float)$location['latitude'],
                'longitude' => (float)$location['longitude'],
                'timestamp' => $location['timestamp'],
                'speed' => (float)($location['speed'] ?? 0)
            ];
        }
        
        return null;
    }
    
    /**
     * Get elephant's recent movement data for stationary detection
     * 
     * @param string $elephantId
     * @return array|null
     */
    private function getElephantMovementData($elephantId) {
        $locationsCollection = $this->db->selectCollection('gps_locations');
        
        // Get locations from the last 48 hours
        $cutoffTime = new MongoDB\BSON\UTCDateTime((time() - 48 * 3600) * 1000);
        
        $locations = $locationsCollection->find(
            [
                'elephant_id' => (string)$elephantId,
                'timestamp' => ['$gte' => $cutoffTime]
            ],
            ['sort' => ['timestamp' => -1]]
        )->toArray();
        
        return $locations;
    }
    
    /**
     * Calculate how long elephant has been stationary
     * 
     * @param array $movementData
     * @return float Duration in hours
     */
    private function calculateStationaryDuration($movementData) {
        if (empty($movementData)) {
            return 0;
        }
        
        $stationaryStart = null;
        $currentTime = time();
        
        // Find the last time elephant was moving (speed > 0)
        foreach ($movementData as $location) {
            $speed = $location['speed'] ?? 0;
            if ($speed > 0) {
                $stationaryStart = $location['timestamp']->toDateTime()->getTimestamp();
                break;
            }
        }
        
        if (!$stationaryStart) {
            // Elephant has been stationary since the beginning of our data
            $stationaryStart = $movementData[count($movementData) - 1]['timestamp']->toDateTime()->getTimestamp();
        }
        
        return ($currentTime - $stationaryStart) / 3600; // Convert to hours
    }
    
    /**
     * Check if point is inside geofence circle
     * 
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @param float $radius
     * @return bool
     */
    private function isPointInGeofence($lat1, $lng1, $lat2, $lng2, $radius) {
        $distance = $this->calculateDistance($lat1, $lng1, $lat2, $lng2);
        return $distance <= $radius;
    }
    
    /**
     * Calculate distance between two points using Haversine formula
     * 
     * @param float $lat1
     * @param float $lng1
     * @param float $lat2
     * @param float $lng2
     * @return float Distance in meters
     */
    private function calculateDistance($lat1, $lng1, $lat2, $lng2) {
        $earthRadius = 6371000; // Earth's radius in meters
        
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        
        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($lngDelta / 2) * sin($lngDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        
        return $earthRadius * $c;
    }
    
    /**
     * Check if we recently sent a geofence violation alert
     * 
     * @param string $elephantId
     * @param string $geofenceId
     * @return bool
     */
    private function checkRecentGeofenceAlert($elephantId, $geofenceId) {
        $alertsCollection = $this->db->selectCollection('alerts');
        
        // Check for alerts in the last 6 hours
        $cutoffTime = new MongoDB\BSON\UTCDateTime((time() - 6 * 3600) * 1000);
        
        $recentAlert = $alertsCollection->findOne([
            'elephant_id' => $elephantId,
            'geofence_id' => $geofenceId,
            'alert_type' => 'geofence_violation',
            'created_at' => ['$gte' => $cutoffTime]
        ]);
        
        return $recentAlert !== null;
    }
    
    /**
     * Check if we recently sent a stationary alert
     * 
     * @param string $elephantId
     * @return bool
     */
    private function checkRecentStationaryAlert($elephantId) {
        $alertsCollection = $this->db->selectCollection('alerts');
        
        // Check for alerts in the last 12 hours
        $cutoffTime = new MongoDB\BSON\UTCDateTime((time() - 12 * 3600) * 1000);
        
        $recentAlert = $alertsCollection->findOne([
            'elephant_id' => $elephantId,
            'alert_type' => 'stationary_elephant',
            'created_at' => ['$gte' => $cutoffTime]
        ]);
        
        return $recentAlert !== null;
    }
    
    /**
     * Create geofence violation alert
     * 
     * @param array $elephant
     * @param array $geofence
     * @param array $location
     * @return array
     */
    private function createGeofenceViolationAlert($elephant, $geofence, $location) {
        $alert = [
            'elephant_id' => (string)$elephant['_id'],
            'elephant_name' => $elephant['name'],
            'geofence_id' => (string)$geofence['_id'],
            'geofence_name' => $geofence['geofence_id'],
            'alert_type' => 'geofence_violation',
            'alert_level' => $geofence['type'] === 'restricted' ? 'critical' : 'high',
            'message' => "Elephant {$elephant['name']} has left the {$geofence['geofence_id']} geofence zone.",
            'location' => [
                'latitude' => $location['latitude'],
                'longitude' => $location['longitude'],
                'timestamp' => $location['timestamp']
            ],
            'geofence_details' => [
                'center_lat' => $geofence['lat'],
                'center_lng' => $geofence['lng'],
                'radius' => $geofence['radius'],
                'type' => $geofence['type']
            ],
            'status' => 'active',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'sent_to_authorities' => false
        ];
        
        // Save alert to database
        $alertsCollection = $this->db->selectCollection('alerts');
        $result = $alertsCollection->insertOne($alert);
        $alert['_id'] = (string)$result->getInsertedId();
        
        return $alert;
    }
    
    /**
     * Create stationary elephant alert
     * 
     * @param array $elephant
     * @param array $movementData
     * @param float $duration
     * @return array
     */
    private function createStationaryAlert($elephant, $movementData, $duration) {
        $currentLocation = $movementData[0];
        
        $alert = [
            'elephant_id' => (string)$elephant['_id'],
            'elephant_name' => $elephant['name'],
            'alert_type' => 'stationary_elephant',
            'alert_level' => 'medium',
            'message' => "Elephant {$elephant['name']} has been stationary for " . round($duration, 1) . " hours.",
            'location' => [
                'latitude' => $currentLocation['latitude'],
                'longitude' => $currentLocation['longitude'],
                'timestamp' => $currentLocation['timestamp']
            ],
            'stationary_details' => [
                'duration_hours' => round($duration, 1),
                'last_movement' => $currentLocation['timestamp'],
                'current_speed' => $currentLocation['speed'] ?? 0
            ],
            'status' => 'active',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'sent_to_authorities' => false
        ];
        
        // Save alert to database
        $alertsCollection = $this->db->selectCollection('alerts');
        $result = $alertsCollection->insertOne($alert);
        $alert['_id'] = (string)$result->getInsertedId();
        
        return $alert;
    }
    
    /**
     * Send alerts to all relevant authorities
     * 
     * @param array $alerts
     * @return int Number of alerts sent
     */
    private function sendAlertsToAuthorities($alerts) {
        $sentCount = 0;
        
        // Get all active authorities
        $authoritiesCollection = $this->db->selectCollection('authorities');
        $authorities = $authoritiesCollection->find(['active' => true])->toArray();
        
        if (empty($authorities)) {
            error_log('No active authorities found to send alerts to');
            return 0;
        }
        
        foreach ($alerts as $alert) {
            try {
                $this->sendAlertToAuthorities($alert, $authorities);
                $sentCount++;
                
                // Mark alert as sent
                $this->markAlertAsSent($alert['_id']);
                
            } catch (Exception $e) {
                error_log("Failed to send alert {$alert['_id']}: " . $e->getMessage());
            }
        }
        
        return $sentCount;
    }
    
    /**
     * Send individual alert to authorities
     * 
     * @param array $alert
     * @param array $authorities
     */
    private function sendAlertToAuthorities($alert, $authorities) {
        // Prepare alert message
        $smsMessage = $this->formatSMSMessage($alert);
        $emailSubject = $this->formatEmailSubject($alert);
        $emailMessage = $this->formatEmailMessage($alert);
        
        // Send SMS alerts
        $smsResults = $this->smsService->sendBulkSMS($authorities, $smsMessage);
        
        // Send email alerts
        $emailResults = $this->emailService->sendBulkEmails($authorities, $emailSubject, $emailMessage);
        
        // Log delivery results
        $this->logAlertDelivery($alert, $smsResults, $emailResults);
    }
    
    /**
     * Format SMS message for alert
     * 
     * @param array $alert
     * @return string
     */
    private function formatSMSMessage($alert) {
        $message = "🚨 YalaGuard Alert: ";
        
        if ($alert['alert_type'] === 'geofence_violation') {
            $message .= "Elephant {$alert['elephant_name']} left {$alert['geofence_name']} zone. ";
        } else {
            $message .= "Elephant {$alert['elephant_name']} stationary for " . round($alert['stationary_details']['duration_hours'], 1) . "h. ";
        }
        
        $message .= "Location: " . round($alert['location']['latitude'], 4) . ", " . round($alert['location']['longitude'], 4);
        
        return $message;
    }
    
    /**
     * Format email subject for alert
     * 
     * @param array $alert
     * @return string
     */
    private function formatEmailSubject($alert) {
        $level = strtoupper($alert['alert_level']);
        
        if ($alert['alert_type'] === 'geofence_violation') {
            return "[{$level}] Geofence Violation - Elephant {$alert['elephant_name']}";
        } else {
            return "[{$level}] Stationary Elephant - {$alert['elephant_name']}";
        }
    }
    
    /**
     * Format email message for alert
     * 
     * @param array $alert
     * @return string
     */
    private function formatEmailMessage($alert) {
        $message = "🚨 YalaGuard Alert System\n\n";
        
        if ($alert['alert_type'] === 'geofence_violation') {
            $message .= "**Geofence Violation Detected**\n\n";
            $message .= "Elephant: {$alert['elephant_name']}\n";
            $message .= "Geofence: {$alert['geofence_name']}\n";
            $message .= "Alert Level: {$alert['alert_level']}\n";
            $message .= "Violation Time: " . $alert['location']['timestamp']->toDateTime()->format('Y-m-d H:i:s') . "\n\n";
            
            $message .= "**Current Location:**\n";
            $message .= "Latitude: " . $alert['location']['latitude'] . "\n";
            $message .= "Longitude: " . $alert['location']['longitude'] . "\n";
            $message .= "Coordinates: " . $alert['location']['latitude'] . ", " . $alert['location']['longitude'] . "\n\n";
            
            $message .= "**Geofence Details:**\n";
            $message .= "Center: " . $alert['geofence_details']['center_lat'] . ", " . $alert['geofence_details']['center_lng'] . "\n";
            $message .= "Radius: " . $alert['geofence_details']['radius'] . " meters\n";
            $message .= "Type: " . ucfirst($alert['geofence_details']['type']) . "\n\n";
            
        } else {
            $message .= "**Stationary Elephant Detected**\n\n";
            $message .= "Elephant: {$alert['elephant_name']}\n";
            $message .= "Alert Level: {$alert['alert_level']}\n";
            $message .= "Stationary Duration: " . round($alert['stationary_details']['duration_hours'], 1) . " hours\n\n";
            
            $message .= "**Current Location:**\n";
            $message .= "Latitude: " . $alert['location']['latitude'] . "\n";
            $message .= "Longitude: " . $alert['location']['longitude'] . "\n";
            $message .= "Coordinates: " . $alert['location']['latitude'] . ", " . $alert['location']['longitude'] . "\n\n";
            
            $message .= "**Movement Details:**\n";
            $message .= "Last Movement: " . $alert['stationary_details']['last_movement']->toDateTime()->format('Y-m-d H:i:s') . "\n";
            $message .= "Current Speed: " . ($alert['stationary_details']['current_speed'] ?? 0) . " km/h\n\n";
        }
        
        $message .= "**Action Required:**\n";
        $message .= "Please investigate this situation immediately and take appropriate action.\n\n";
        
        $message .= "**System Information:**\n";
        $message .= "Alert Generated: " . $alert['created_at']->toDateTime()->format('Y-m-d H:i:s') . "\n";
        $message .= "Alert ID: {$alert['_id']}\n";
        
        return $message;
    }
    
    /**
     * Mark alert as sent to authorities
     * 
     * @param string $alertId
     */
    private function markAlertAsSent($alertId) {
        $alertsCollection = $this->db->selectCollection('alerts');
        $alertsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($alertId)],
            ['$set' => [
                'sent_to_authorities' => true,
                'sent_at' => new MongoDB\BSON\UTCDateTime()
            ]]
        );
    }
    
    /**
     * Log alert delivery results
     * 
     * @param array $alert
     * @param array $smsResults
     * @param array $emailResults
     */
    private function logAlertDelivery($alert, $smsResults, $emailResults) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'alert_id' => $alert['_id'],
            'alert_type' => $alert['alert_type'],
            'elephant_name' => $alert['elephant_name'],
            'sms_results' => $smsResults,
            'email_results' => $emailResults
        ];
        
        error_log('Alert Delivery Log: ' . json_encode($logData));
    }
    
    /**
     * Log system check results
     * 
     * @param array $results
     */
    private function logSystemCheck($results) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'check_results' => $results
        ];
        
        error_log('Alert System Check: ' . json_encode($logData));
    }
}

// Function to run the alert system (for cron job)
function runAlertSystem() {
    try {
        $alertSystem = new AlertSystem();
        $results = $alertSystem->checkAllElephants();
        
        echo json_encode($results, JSON_PRETTY_PRINT);
        return $results;
        
    } catch (Exception $e) {
        error_log('Alert System Run Error: ' . $e->getMessage());
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        return false;
    }
}

// If this file is run directly, execute the alert system
if (php_sapi_name() === 'cli' || basename($_SERVER['SCRIPT_NAME']) === basename(__FILE__)) {
    runAlertSystem();
}
?>
