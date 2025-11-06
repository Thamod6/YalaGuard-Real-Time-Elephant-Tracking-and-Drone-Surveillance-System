<?php
/**
 * YalaGuard Email Verification Service
 * 
 * This service handles sending verification emails to newly registered users
 */

// Include PHPMailer
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

/**
 * Send verification email to user
 * 
 * @param string $email User's email address
 * @param string $username User's username
 * @param string $fullName User's full name
 * @param string $verificationToken Verification token
 * @return array Result of email sending operation
 */
function sendVerificationEmail($email, $username, $fullName, $verificationToken) {
    try {
        // Load environment variables
        $envFile = dirname(__DIR__) . '/.env';
        if (!file_exists($envFile)) {
            throw new Exception('.env file not found');
        }
        
        $envContent = file_get_contents($envFile);
        $envLines = explode("\n", $envContent);
        
        foreach ($envLines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '#') === 0) {
                continue;
            }
            
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $_ENV[trim($key)] = trim($value);
            }
        }
        
        // Get email configuration from environment
        $mailHost = $_ENV['MAIL_HOST'] ?? 'smtp.gmail.com';
        $mailPort = $_ENV['MAIL_PORT'] ?? 587;
        $mailUsername = $_ENV['MAIL_USERNAME'] ?? '';
        $mailPassword = $_ENV['MAIL_PASSWORD'] ?? '';
        $mailFromEmail = $_ENV['MAIL_FROM_EMAIL'] ?? 'noreply@yalaguard.com';
        $mailFromName = $_ENV['MAIL_FROM_NAME'] ?? 'YalaGuard';
        $appUrl = $_ENV['APP_URL'] ?? 'http://localhost:8000';
        
        if (empty($mailUsername) || empty($mailPassword)) {
            throw new Exception('Email configuration incomplete. Please check MAIL_USERNAME and MAIL_PASSWORD in .env file.');
        }
        
        // Create PHPMailer instance
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $mailHost;
        $mail->SMTPAuth = true;
        $mail->Username = $mailUsername;
        $mail->Password = $mailPassword;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $mailPort;
        
        // Recipients
        $mail->setFrom($mailFromEmail, $mailFromName);
        $mail->addAddress($email, $fullName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'YalaGuard - Verify Your Email Address';
        
        // Create verification link
        $verificationLink = $appUrl . '/api/verify-email.php?token=' . $verificationToken;
        
        // Email body
        $mail->Body = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <div style='background: #1a1a2e; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
                <h1 style='margin: 0; color: #64ffda;'>🐘 YalaGuard</h1>
                <p style='margin: 10px 0 0 0;'>Elephant Monitoring System</p>
            </div>
            
            <div style='background: white; padding: 30px; border-radius: 0 0 10px 10px;'>
                <h2 style='color: #1a1a2e; margin-top: 0;'>Welcome to YalaGuard!</h2>
                
                <p>Hello <strong>$fullName</strong>,</p>
                
                <p>Thank you for registering with YalaGuard! To complete your registration and access your account, please verify your email address by clicking the button below:</p>
                
                <div style='text-align: center; margin: 30px 0;'>
                    <a href='$verificationLink' style='background: #64ffda; color: #1a1a2e; padding: 15px 30px; text-decoration: none; border-radius: 8px; font-weight: bold; display: inline-block;'>Verify Email Address</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <p style='background: #f5f5f5; padding: 15px; border-radius: 5px; word-break: break-all;'>$verificationLink</p>
                
                <p><strong>Important:</strong> This verification link will expire in 24 hours for security reasons.</p>
                
                <p>If you didn't create this account, please ignore this email.</p>
                
                <hr style='border: none; border-top: 1px solid #eee; margin: 30px 0;'>
                
                <p style='color: #666; font-size: 14px;'>
                    Best regards,<br>
                    The YalaGuard Team<br>
                    <a href='$appUrl' style='color: #64ffda;'>$appUrl</a>
                </p>
            </div>
        </div>";
        
        // Plain text version
        $mail->AltBody = "
        Welcome to YalaGuard!
        
        Hello $fullName,
        
        Thank you for registering with YalaGuard! To complete your registration and access your account, please verify your email address by visiting this link:
        
        $verificationLink
        
        Important: This verification link will expire in 24 hours for security reasons.
        
        If you didn't create this account, please ignore this email.
        
        Best regards,
        The YalaGuard Team
        $appUrl";
        
        // Send email
        $mail->send();
        
        return [
            'status' => 'success',
            'message' => 'Verification email sent successfully'
        ];
        
    } catch (Exception $e) {
        error_log('Email verification error: ' . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Failed to send verification email: ' . $e->getMessage()
        ];
    }
}

/**
 * Send verification email via API endpoint
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['email']) || !isset($input['username']) || !isset($input['full_name']) || !isset($input['verification_token'])) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Missing required fields'
            ]);
            exit();
        }
        
        $result = sendVerificationEmail(
            $input['email'],
            $input['username'],
            $input['full_name'],
            $input['verification_token']
        );
        
        if ($result['status'] === 'success') {
            http_response_code(200);
            echo json_encode($result);
        } else {
            http_response_code(500);
            echo json_encode($result);
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Internal server error: ' . $e->getMessage()
        ]);
    }
}
?>
