<?php
/**
 * YalaGuard Email Service
 * 
 * This service handles sending email alerts using PHPMailer
 */

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load autoloader with proper path resolution
$autoloadPath = __DIR__ . '/../vendor/autoload.php';
if (!file_exists($autoloadPath)) {
    throw new Exception('Vendor autoload file not found. Please run: composer install');
}
require_once $autoloadPath;

class EmailService {
    private $mailer;
    private $fromEmail;
    private $fromName;
    
    public function __construct() {
        $this->mailer = new PHPMailer(true);
        $this->fromEmail = $_ENV['MAIL_FROM_EMAIL'] ?? 'alerts@yalaguard.com';
        $this->fromName = $_ENV['MAIL_FROM_NAME'] ?? 'YalaGuard Alert System';
        
        $this->configureMailer();
    }
    
    /**
     * Configure PHPMailer settings
     */
    private function configureMailer() {
        try {
            // Server settings
            $this->mailer->isSMTP();
            $this->mailer->Host = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $_ENV['MAIL_USERNAME'] ?? '';
            $this->mailer->Password = $_ENV['MAIL_PASSWORD'] ?? '';
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $this->mailer->Port = $_ENV['MAIL_PORT'] ?? 587;
            
            // Default settings
            $this->mailer->setFrom($this->fromEmail, $this->fromName);
            $this->mailer->isHTML(true);
            $this->mailer->CharSet = 'UTF-8';
            
        } catch (Exception $e) {
            error_log('Email Service Configuration Error: ' . $e->getMessage());
            throw new Exception('Failed to configure email service: ' . $e->getMessage());
        }
    }
    
    /**
     * Send email alert
     * 
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $message
     * @param array $attachments
     * @return array
     */
    public function sendEmail($toEmail, $toName, $subject, $message, $attachments = []) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->clearAttachments();
            
            // Add recipient
            $this->mailer->addAddress($toEmail, $toName);
            
            // Set subject and body
            $this->mailer->Subject = $subject;
            $this->mailer->Body = $this->formatEmailBody($message);
            $this->mailer->AltBody = strip_tags($message);
            
            // Add attachments if any
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    if (file_exists($attachment['path'])) {
                        $this->mailer->addAttachment($attachment['path'], $attachment['name'] ?? '');
                    }
                }
            }
            
            // Send email
            $this->mailer->send();
            
            // Log successful email
            $this->logEmail($toEmail, $subject, 'success');
            
            return [
                'status' => 'success',
                'message' => 'Email sent successfully',
                'to' => $toEmail,
                'subject' => $subject
            ];
            
        } catch (Exception $e) {
            $errorMessage = $e->getMessage();
            error_log('Email Service Error: ' . $errorMessage);
            
            // Log failed email
            $this->logEmail($toEmail, $subject, 'failed', $errorMessage);
            
            return [
                'status' => 'error',
                'message' => 'Failed to send email: ' . $errorMessage,
                'to' => $toEmail,
                'subject' => $subject
            ];
        }
    }
    
    /**
     * Send bulk emails to multiple authorities
     * 
     * @param array $authorities
     * @param string $subject
     * @param string $message
     * @param array $attachments
     * @return array
     */
    public function sendBulkEmails($authorities, $subject, $message, $attachments = []) {
        $results = [];
        
        foreach ($authorities as $authority) {
            if ($authority['alert_preferences']['email_enabled'] && !empty($authority['email'])) {
                $result = $this->sendEmail(
                    $authority['email'],
                    $authority['name'],
                    $subject,
                    $message,
                    $attachments
                );
                
                $results[] = [
                    'authority_id' => $authority['_id'],
                    'authority_name' => $authority['name'],
                    'email' => $authority['email'],
                    'result' => $result
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Format email body with HTML template
     * 
     * @param string $message
     * @return string
     */
    private function formatEmailBody($message) {
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
                .coordinates { background: #e8f4fd; padding: 10px; border-radius: 5px; margin: 10px 0; font-family: monospace; }
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
     * Log email attempts for monitoring
     * 
     * @param string $toEmail
     * @param string $subject
     * @param string $status
     * @param string $error
     */
    private function logEmail($toEmail, $subject, $status, $error = '') {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'to_email' => $toEmail,
            'subject' => $subject,
            'status' => $status,
            'error' => $error,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ];
        
        error_log('Email Log: ' . json_encode($logData));
    }
    
    /**
     * Test email service configuration
     * 
     * @return array
     */
    public function testConnection() {
        try {
            // Test SMTP connection
            $this->mailer->smtpConnect();
            
            return [
                'status' => 'success',
                'message' => 'Email service connection successful',
                'smtp_host' => $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com',
                'smtp_port' => $_ENV['MAIL_PORT'] ?? 587,
                'from_email' => $this->fromEmail,
                'from_name' => $this->fromName
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Email service connection failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send test email to verify configuration
     * 
     * @param string $testEmail
     * @return array
     */
    public function sendTestEmail($testEmail) {
        $subject = 'YalaGuard Email Service Test';
        $message = 'This is a test email from the YalaGuard alert system. If you receive this, the email service is working correctly.';
        
        return $this->sendEmail($testEmail, 'Test User', $subject, $message);
    }
}

// Fallback email service using PHP mail() function
class FallbackEmailService {
    
    public static function sendEmail($toEmail, $toName, $subject, $message) {
        try {
            $headers = [
                'From: ' . ($_ENV['MAIL_FROM_EMAIL'] ?? 'alerts@yalaguard.com'),
                'Reply-To: ' . ($_ENV['MAIL_FROM_EMAIL'] ?? 'alerts@yalaguard.com'),
                'Content-Type: text/html; charset=UTF-8',
                'X-Mailer: YalaGuard Alert System'
            ];
            
            $result = mail($toEmail, $subject, $message, implode("\r\n", $headers));
            
            if ($result) {
                return [
                    'status' => 'success',
                    'message' => 'Email sent successfully using fallback service',
                    'method' => 'php_mail'
                ];
            } else {
                return [
                    'status' => 'error',
                    'message' => 'Failed to send email using fallback service',
                    'method' => 'php_mail'
                ];
            }
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Fallback email service error: ' . $e->getMessage(),
                'method' => 'php_mail'
            ];
        }
    }
}
