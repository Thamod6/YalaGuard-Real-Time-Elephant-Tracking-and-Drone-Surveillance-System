<?php
/**
 * YalaGuard Manual Alert API - Email Only
 * 
 * This API handles manual alert requests and sends them to authorities via email
 */

// Start output buffering immediately to catch any unexpected output
ob_start();

// Suppress error display to prevent HTML in JSON response
error_reporting(0);
ini_set('display_errors', 0);

// Load PHPMailer classes
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Clear any output buffer content that might have been generated
ob_clean();

// Set content type to JSON
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Only POST requests are supported.'
    ]);
    exit();
}

try {
    // Include required files with proper path resolution
    $configPath = __DIR__ . '/../config/database.php';
    
    if (!file_exists($configPath)) {
        throw new Exception('Database config file not found: ' . $configPath);
    }
    
    require_once $configPath;
    
    // Get JSON input from request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON: ' . json_last_error_msg());
    }
    
    // Validate required fields
    $requiredFields = ['alert_type', 'authorities'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty($input[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields: ' . implode(', ', $missingFields)
        ]);
        exit();
    }
    
    // Validate authorities array
    if (!is_array($input['authorities']) || empty($input['authorities'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'At least one authority must be selected'
        ]);
        exit();
    }
    
    // Get database connection
    try {
        $db = getDatabase();
    } catch (Exception $e) {
        throw new Exception('Database connection failed: ' . $e->getMessage());
    }
    
    // Get selected authorities
    try {
        $authoritiesCollection = $db->selectCollection('authorities');
        $authorities = [];
        
        foreach ($input['authorities'] as $authorityId) {
            try {
                $authority = $authoritiesCollection->findOne([
                    '_id' => new MongoDB\BSON\ObjectId($authorityId),
                    'active' => true
                ]);
                
                if ($authority) {
                    $authority['_id'] = (string)$authority['_id'];
                    $authorities[] = $authority;
                }
            } catch (Exception $e) {
                // Log the error but continue with other authorities
                error_log("Failed to fetch authority {$authorityId}: " . $e->getMessage());
            }
        }
        
        if (empty($authorities)) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'No valid authorities found'
            ]);
            exit();
        }
    } catch (Exception $e) {
        throw new Exception('Failed to fetch authorities: ' . $e->getMessage());
    }
    
    // Prepare alert message
    $alertMessage = prepareAlertMessage($input, $db);
    
    // Process location data - ensure coordinates are numbers
    $location = null;
    if (!empty($input['location']['latitude']) && !empty($input['location']['longitude'])) {
        $latitude = (float)$input['location']['latitude'];
        $longitude = (float)$input['location']['longitude'];
        
        // Validate coordinate ranges
        if ($latitude < -90 || $latitude > 90) {
            throw new Exception('Invalid latitude: must be between -90 and 90');
        }
        
        if ($longitude < -180 || $longitude > 180) {
            throw new Exception('Invalid longitude: must be between -180 and 180');
        }
        
        $location = [
            'latitude' => $latitude,
            'longitude' => $longitude
        ];
    }
    
    // Create alert record in database
    try {
        $alertsCollection = $db->selectCollection('alerts');
        $alertData = [
            'alert_type' => $input['alert_type'],
            'alert_level' => determineAlertLevel($input['alert_type']),
            'message' => $alertMessage,
            'elephant_id' => $input['elephant_id'] ?? null,
            'location' => $location,
            'authorities' => $input['authorities'],
            'sent_by' => $input['sent_by'] ?? null,
            'sent_by_name' => $input['sent_by_name'] ?? 'System',
            'status' => 'active',
            'created_at' => new MongoDB\BSON\UTCDateTime(),
            'sent_to_authorities' => false
        ];
        
        $result = $alertsCollection->insertOne($alertData);
        
        if (!$result->getInsertedCount()) {
            throw new Exception('Failed to save alert to database');
        }
        
        $alertData['_id'] = (string)$result->getInsertedId();
    } catch (Exception $e) {
        throw new Exception('Database insert failed: ' . $e->getMessage());
    }
    
    // Send alerts to authorities via email using your PHPMailer settings
    $emailResults = [];
    $emailSentCount = 0;
    
    try {
        // Send email alerts to each authority
        foreach ($authorities as $authority) {
            if (!empty($authority['email'])) {
                try {
                    $mail = new PHPMailer(true);
                    
                    // Server settings - using your .env settings
                    $mail->isSMTP();
                    $mail->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
                    $mail->SMTPAuth = true;
                    $mail->Username = $_ENV['MAIL_USERNAME'] ?? '';
                    $mail->Password = $_ENV['MAIL_PASSWORD'] ?? '';
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = $_ENV['MAIL_PORT'] ?? 587;
                    
                    // Recipients
                    $mail->setFrom($_ENV['MAIL_FROM_EMAIL'] ?? 'alerts@yalaguard.com', $_ENV['MAIL_FROM_NAME'] ?? 'YalaGuard Alert System');
                    $mail->addAddress($authority['email'], $authority['name']);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = formatEmailSubject($input['alert_type']);
                    $mail->Body = formatEmailBody($alertMessage);
                    $mail->AltBody = strip_tags($alertMessage);
                    
                    // Send email
                    $mail->send();
                    
                    $emailResults[] = [
                        'authority_id' => $authority['_id'],
                        'authority_name' => $authority['name'],
                        'email' => $authority['email'],
                        'result' => ['status' => 'success', 'message' => 'Email sent successfully']
                    ];
                    
                    $emailSentCount++;
                    
                } catch (Exception $e) {
                    error_log("Failed to send email to {$authority['email']}: " . $e->getMessage());
                    $emailResults[] = [
                        'authority_id' => $authority['_id'],
                        'authority_name' => $authority['name'],
                        'email' => $authority['email'],
                        'result' => ['status' => 'error', 'message' => 'Failed to send email: ' . $e->getMessage()]
                    ];
                }
            }
        }
        
    } catch (Exception $e) {
        error_log('PHPMailer Error: ' . $e->getMessage());
        $emailResults = [['error' => 'Email service unavailable: ' . $e->getMessage()]];
    }
    
    // Mark alert as sent
    try {
        $alertsCollection->updateOne(
            ['_id' => new MongoDB\BSON\ObjectId($alertData['_id'])],
            ['$set' => [
                'sent_to_authorities' => true,
                'sent_at' => new MongoDB\BSON\UTCDateTime(),
                'email_results' => $emailResults
            ]]
        );
    } catch (Exception $e) {
        error_log('Failed to update alert status: ' . $e->getMessage());
        // Don't throw here as the alert was already sent
    }
    
    // Log the manual alert
    try {
        logManualAlert($alertData, $emailResults);
    } catch (Exception $e) {
        error_log('Failed to log manual alert: ' . $e->getMessage());
        // Don't throw here as the alert was already sent
    }
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Manual alert sent via email successfully',
        'alert_id' => $alertData['_id'],
        'recipients_count' => count($authorities),
        'email_sent' => $emailSentCount,
        'alert_data' => [
            'type' => $alertData['alert_type'],
            'level' => $alertData['alert_level'],
            'message' => $alertData['message']
        ]
    ]);
    exit();
    
} catch (Exception $e) {
    // Clear any output buffer content
    ob_clean();
    
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
    exit();
}

// End output buffering
ob_end_flush();

/**
 * Prepare alert message based on alert type and input data
 */
function prepareAlertMessage($input, $db) {
    $message = '';
    
    switch ($input['alert_type']) {
        case 'emergency':
            $message = "🚨 EMERGENCY ALERT: Urgent situation requiring immediate attention.";
            break;
            
        case 'wildlife_conflict':
            $message = "🐘 WILDLIFE CONFLICT ALERT: Human-elephant conflict situation detected.";
            break;
            
        case 'poaching_alert':
            $message = "⚠️ POACHING ALERT: Potential poaching activity detected in the area.";
            break;
            
        case 'health_emergency':
            $message = "🏥 HEALTH EMERGENCY: Wildlife health issue requiring veterinary attention.";
            break;
            
        case 'weather_alert':
            $message = "🌦️ WEATHER ALERT: Severe weather conditions affecting wildlife safety.";
            break;
            
        case 'custom':
            $message .= "📢 CUSTOM ALERT: " . ($input['custom_message'] ?? 'Custom alert message');
            break;
            
        default:
            $message = "📢 ALERT: " . ucfirst($input['alert_type']) . " situation detected.";
    }
    
    // Add elephant information if specified
    if (!empty($input['elephant_id'])) {
        try {
            $elephantsCollection = $db->selectCollection('elephants');
            $elephant = $elephantsCollection->findOne([
                '_id' => new MongoDB\BSON\ObjectId($input['elephant_id']),
                'active' => true
            ]);
            
            if ($elephant) {
                $elephantName = $elephant['name'] ?? 'Unknown Elephant';
                $elephantType = $elephant['type'] ?? 'Unknown Type';
                $message .= " Elephant: " . $elephantName . " (" . $elephantType . ")";
            } else {
                $message .= " Elephant ID: " . $input['elephant_id'] . " (not found)";
            }
        } catch (Exception $e) {
            // If we can't fetch elephant details, fall back to ID
            $message .= " Elephant ID: " . $input['elephant_id'];
            error_log("Failed to fetch elephant details: " . $e->getMessage());
        }
    }
    
    // Add location information if specified
    if (!empty($input['location']['latitude']) && !empty($input['location']['longitude'])) {
        $message .= " Location: " . $input['location']['latitude'] . ", " . $input['location']['longitude'];
    }
    
    $message .= " Time: " . date('Y-m-d H:i:s');
    
    return $message;
}

/**
 * Determine alert level based on alert type
 */
function determineAlertLevel($alertType) {
    switch ($alertType) {
        case 'emergency':
        case 'poaching_alert':
            return 'critical';
            
        case 'wildlife_conflict':
        case 'health_emergency':
            return 'high';
            
        case 'weather_alert':
            return 'medium';
            
        default:
            return 'medium';
    }
}

/**
 * Format email subject for alert type
 */
function formatEmailSubject($alertType) {
    $level = strtoupper(determineAlertLevel($alertType));
    
    switch ($alertType) {
        case 'emergency':
            return "[{$level}] Emergency Alert - Immediate Action Required";
            
        case 'wildlife_conflict':
            return "[{$level}] Wildlife Conflict Alert";
            
        case 'poaching_alert':
            return "[{$level}] Poaching Alert - Security Threat";
            
        case 'health_emergency':
            return "[{$level}] Health Emergency Alert";
            
        case 'weather_alert':
            return "[{$level}] Weather Alert - Wildlife Safety";
            
        case 'custom':
            return "[{$level}] Custom Alert - Manual Notification";
            
        default:
            return "[{$level}] Alert Notification";
    }
}

/**
 * Format email body with HTML template
 */
function formatEmailBody($message) {
    $htmlTemplate = '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>YalaGuard Alert</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #2c5aa0; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 5px 5px; }
            .alert-box { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 15px 0; }
            .footer { text-align: center; margin-top: 20px; color: #666; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h1>🐘 YalaGuard Alert System</h1>
                <p>Wildlife Conservation & Monitoring</p>
            </div>
            <div class="content">
                <div class="alert-box">
                    <h2>🚨 Important Alert</h2>
                    ' . nl2br(htmlspecialchars($message)) . '
                </div>
                <div class="footer">
                    <p>This is an automated alert from the YalaGuard system.</p>
                    <p>Please do not reply to this email.</p>
                </div>
            </div>
        </div>
    </body>
    </html>';
    
    return $htmlTemplate;
}

/**
 * Log manual alert for monitoring
 */
function logManualAlert($alertData, $emailResults) {
    $logData = [
        'timestamp' => date('Y-m-d H:i:s'),
        'alert_id' => $alertData['_id'],
        'alert_type' => $alertData['alert_type'],
        'alert_level' => $alertData['alert_level'],
        'sent_by' => $alertData['sent_by_name'],
        'recipients_count' => count($alertData['authorities']),
        'email_results' => $emailResults
    ];
    
    error_log('Manual Alert Log: ' . json_encode($logData));
}
