<?php
/**
 * YalaGuard - Twilio SMS Webhook
 * 
 * This endpoint receives SMS messages from Twilio
 * and forwards them to the GPS receiver API
 */

require_once '../config/database.php';

// Twilio webhook verification
function verifyTwilioRequest() {
    $twilioSignature = $_SERVER['HTTP_X_TWILIO_SIGNATURE'] ?? '';
    $url = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $params = $_POST;
    
    // Remove signature from params for verification
    unset($params['Body']);
    
    // Sort parameters alphabetically
    ksort($params);
    
    // Build query string
    $queryString = '';
    foreach ($params as $key => $value) {
        $queryString .= $key . $value;
    }
    
    // Your Twilio auth token (set in environment)
    $authToken = $_ENV['TWILIO_AUTH_TOKEN'] ?? 'your_auth_token_here';
    
    // Create signature
    $expectedSignature = base64_encode(hash_hmac('sha1', $url . $queryString, $authToken, true));
    
    return hash_equals($expectedSignature, $twilioSignature);
}

// Process incoming SMS from Twilio
function processTwilioSMS() {
    try {
        // Get SMS data from Twilio
        $from = $_POST['From'] ?? '';
        $body = $_POST['Body'] ?? '';
        $messageSid = $_POST['MessageSid'] ?? '';
        $timestamp = $_POST['Timestamp'] ?? '';
        
        if (empty($from) || empty($body)) {
            error_log('Missing required SMS data from Twilio');
            return false;
        }
        
        // Clean phone number (remove + if present)
        $phoneNumber = ltrim($from, '+');
        
        // Forward to GPS receiver API
        $gpsData = [
            'phone_number' => $phoneNumber,
            'message' => $body,
            'twilio_message_sid' => $messageSid,
            'twilio_timestamp' => $timestamp
        ];
        
        // Send to internal GPS receiver
        $response = forwardToGPSReceiver($gpsData);
        
        if ($response && $response['success']) {
            error_log("Successfully processed SMS from $phoneNumber: $body");
            return true;
        } else {
            error_log("Failed to process SMS from $phoneNumber: " . ($response['message'] ?? 'Unknown error'));
            return false;
        }
        
    } catch (Exception $e) {
        error_log('Error processing Twilio SMS: ' . $e->getMessage());
        return false;
    }
}

// Forward SMS data to GPS receiver API
function forwardToGPSReceiver($smsData) {
    try {
        $gpsReceiverUrl = 'http://' . $_SERVER['HTTP_HOST'] . '/api/sms-gps-receiver.php';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $gpsReceiverUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($smsData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'User-Agent: YalaGuard-Twilio-Webhook/1.0'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            return json_decode($response, true);
        } else {
            error_log("GPS receiver API returned HTTP $httpCode: $response");
            return ['success' => false, 'message' => "HTTP $httpCode from GPS receiver"];
        }
        
    } catch (Exception $e) {
        error_log('Error forwarding to GPS receiver: ' . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}

// Log Twilio webhook
function logTwilioWebhook($data, $success) {
    try {
        $db = getDatabase();
        $webhookCollection = $db->selectCollection('twilio_webhooks');
        
        $webhookLog = [
            'twilio_message_sid' => $data['MessageSid'] ?? '',
            'from_number' => $data['From'] ?? '',
            'to_number' => $data['To'] ?? '',
            'message_body' => $data['Body'] ?? '',
            'timestamp' => new MongoDB\BSON\UTCDateTime(),
            'success' => $success,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ];
        
        $webhookCollection->insertOne($webhookLog);
        
    } catch (Exception $e) {
        error_log('Error logging Twilio webhook: ' . $e->getMessage());
    }
}

// Handle incoming webhook
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Verify this is a legitimate Twilio request (optional but recommended)
        if (!verifyTwilioRequest()) {
            error_log('Invalid Twilio signature');
            http_response_code(403);
            echo 'Forbidden';
            exit;
        }
        
        // Process the SMS
        $success = processTwilioSMS();
        
        // Log the webhook
        logTwilioWebhook($_POST, $success);
        
        // Return TwiML response
        header('Content-Type: text/xml');
        if ($success) {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<Response><Message>GPS data received successfully</Message></Response>';
        } else {
            echo '<?xml version="1.0" encoding="UTF-8"?>';
            echo '<Response><Message>Error processing GPS data</Message></Response>';
        }
        
    } catch (Exception $e) {
        error_log('Twilio webhook error: ' . $e->getMessage());
        http_response_code(500);
        header('Content-Type: text/xml');
        echo '<?xml version="1.0" encoding="UTF-8"?>';
        echo '<Response><Message>Internal server error</Message></Response>';
    }
    
} else {
    // GET request - show webhook info
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'message' => 'YalaGuard Twilio SMS Webhook',
        'usage' => [
            'method' => 'POST',
            'endpoint' => '/api/twilio-webhook.php',
            'description' => 'Receives SMS messages from Twilio and forwards to GPS receiver',
            'twilio_webhook_url' => 'https://' . $_SERVER['HTTP_HOST'] . '/api/twilio-webhook.php'
        ],
        'setup_instructions' => [
            '1. Configure Twilio phone number webhook to point to this URL',
            '2. Set TWILIO_AUTH_TOKEN environment variable',
            '3. GPS devices will automatically update when SMS is received'
        ]
    ]);
}
?>
