<?php
/**
 * YalaGuard Resend Verification Email Page
 * 
 * This page allows users to request a new verification email
 */

// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard if already logged in
    header('Location: dashboard.php');
    exit();
}

// Include email service
require_once '../includes/email-service.php';

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error_message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            // Include database configuration
            require_once '../config/database.php';
            
            // Get database connection
            $db = getDatabase();
            $usersCollection = $db->selectCollection('users');
            
            // Find user by email
            $user = $usersCollection->findOne(['email' => $email]);
            
            // Debug: Log what we found
            error_log('Resend verification - Found user: ' . json_encode($user));
            
            if (!$user) {
                $error_message = 'No account found with this email address.';
            } elseif ($user->verified === true) {
                $error_message = 'This account is already verified. You can <a href="login.php">login here</a>.';
            } else {
                // Generate new verification token
                $newToken = bin2hex(random_bytes(32));
                
                // Update user with new token
                $result = $usersCollection->updateOne(
                    ['_id' => $user->_id],
                    [
                        '$set' => [
                            'verification_token' => $newToken,
                            'updated_at' => new MongoDB\BSON\UTCDateTime()
                        ]
                    ]
                );
                
                if ($result->getModifiedCount() > 0) {
                    // Send new verification email
                    // Make sure we have all required fields
                    $userEmail = $user->email ?? $email;
                    $userUsername = $user->username ?? 'User';
                    $userFullName = $user->full_name ?? 'User';
                    
                    // Debug logging
                    error_log('Resend verification - Email: ' . $userEmail . ', Username: ' . $userUsername . ', FullName: ' . $userFullName . ', Token: ' . $newToken);
                    
                    $emailResult = sendVerificationEmail(
                        $userEmail,
                        $userUsername,
                        $userFullName,
                        $newToken
                    );
                    
                    if ($emailResult['status'] === 'success') {
                        $success_message = 'Verification email sent successfully! Please check your inbox and click the verification link.';
                    } else {
                        $error_message = 'Failed to send verification email: ' . $emailResult['message'];
                        error_log('Email sending failed: ' . $emailResult['message']);
                    }
                } else {
                    $error_message = 'Failed to update verification token. Please try again.';
                }
            }
        } catch (Exception $e) {
            $error_message = 'An error occurred. Please try again later.';
            error_log('Resend verification error: ' . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Resend Verification Email</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="login-container">
            <div class="auth-logo">🐘 YalaGuard</div>
            <div class="auth-subtitle">Resend Verification Email</div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="resendForm">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
            </div>
            
            <button type="submit" class="login-btn">Send Verification Email</button>
        </form>
        
        <div class="links">
            <p>Remember your password? <a href="login.php">Login here</a></p>
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p><a href="../index.php">Back to Home</a></p>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
</body>
</html>
