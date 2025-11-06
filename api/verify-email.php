<?php
/**
 * YalaGuard Email Verification Endpoint
 * 
 * This endpoint handles email verification when users click the verification link
 */

// Include database configuration
require_once '../config/database.php';

// Set content type to HTML for user-friendly display
header('Content-Type: text/html; charset=UTF-8');

// Get verification token from URL
$token = $_GET['token'] ?? '';

if (empty($token)) {
    showError('Invalid verification link. Please check your email for the correct link.');
    exit();
}

try {
    // Get database connection
    $db = getDatabase();
    $usersCollection = $db->selectCollection('users');
    
    // Find user with this verification token
    $user = $usersCollection->findOne(['verification_token' => $token]);
    
    if (!$user) {
        showError('Invalid or expired verification link. Please request a new verification email.');
        exit();
    }
    
    // Check if user is already verified
    if ($user->verified === true) {
        showSuccess('Your email is already verified! You can now <a href="../pages/login.php">login to your account</a>.');
        exit();
    }
    
    // Check if token is expired (24 hours)
    $tokenCreated = $user->created_at->toDateTime();
    $now = new DateTime();
    $diff = $now->diff($tokenCreated);
    
    if ($diff->days > 0 || $diff->h > 24) {
        showError('Verification link has expired. Please register again or contact support.');
        exit();
    }
    
    // Verify the user
    $result = $usersCollection->updateOne(
        ['_id' => $user->_id],
        [
            '$set' => [
                'verified' => true,
                'email_verified_at' => new MongoDB\BSON\UTCDateTime(),
                'updated_at' => new MongoDB\BSON\UTCDateTime()
            ],
            '$unset' => ['verification_token' => '']
        ]
    );
    
    if ($result->getModifiedCount() > 0) {
        showSuccess('🎉 Email verification successful! Your account is now verified. You can <a href="../pages/login.php">login to your account</a>.');
    } else {
        showError('Verification failed. Please try again or contact support.');
    }
    
} catch (Exception $e) {
    error_log('Email verification error: ' . $e->getMessage());
    showError('Verification failed due to a system error. Please try again later.');
}

/**
 * Display error message
 */
function showError($message) {
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>YalaGuard - Email Verification</title>
        <style>
            body { font-family: Arial, sans-serif; background: #0f0f23; color: white; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 50px auto; background: #1a1a2e; padding: 40px; border-radius: 15px; text-align: center; }
            .error { background: rgba(244, 67, 54, 0.1); border: 1px solid #f44336; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .logo { font-size: 2.5rem; color: #64ffda; margin-bottom: 10px; }
            .btn { background: #64ffda; color: #1a1a2e; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 20px 10px; font-weight: bold; }
            .btn:hover { background: #4dd4b0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">🐘 YalaGuard</div>
            <h1>Email Verification Failed</h1>
            <div class="error">' . htmlspecialchars($message) . '</div>
            <a href="../pages/login.php" class="btn">Go to Login</a>
            <a href="../pages/register.php" class="btn">Register Again</a>
        </div>
    </body>
    </html>';
}

/**
 * Display success message
 */
function showSuccess($message) {
    echo '
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>YalaGuard - Email Verification</title>
        <style>
            body { font-family: Arial, sans-serif; background: #0f0f23; color: white; margin: 0; padding: 20px; }
            .container { max-width: 600px; margin: 50px auto; background: #1a1a2e; padding: 40px; border-radius: 15px; text-align: center; }
            .success { background: rgba(76, 175, 80, 0.1); border: 1px solid #4caf50; padding: 20px; border-radius: 10px; margin: 20px 0; }
            .logo { font-size: 2.5rem; color: #64ffda; margin-bottom: 10px; }
            .btn { background: #64ffda; color: #1a1a2e; padding: 12px 24px; text-decoration: none; border-radius: 8px; display: inline-block; margin: 20px 10px; font-weight: bold; }
            .btn:hover { background: #4dd4b0; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="logo">🐘 YalaGuard</div>
            <h1>Email Verification Successful!</h1>
            <div class="success">' . $message . '</div>
            <a href="../pages/login.php" class="btn">Login to Your Account</a>
            <a href="../index.php" class="btn">Go to Home</a>
        </div>
    </body>
    </html>';
}
?>
