<?php
/**
 * YalaGuard Login Page
 * 
 * This page provides a user login form with PHP backend integration.
 */

// Start session
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to dashboard if already logged in
    header('Location: dashboard.php');
    exit();
}

// Handle login form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Enhanced validation
    $errors = [];
    
    if (empty($username)) {
        $errors[] = 'Username is required.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    }
    
    if (empty($errors)) {
        try {
            // Include database configuration
            require_once '../config/database.php';
            
            // Get database connection
            $db = getDatabase();
            $usersCollection = $db->selectCollection('users');
            
            // Find user by username
            $user = $usersCollection->findOne(['username' => $username]);
            
            if (!$user) {
                $errors[] = 'Invalid username or password.';
            } elseif ($user->status !== 'active') {
                $errors[] = 'Account is deactivated. Please contact administrator.';
            } elseif (!password_verify($password, $user->password)) {
                $errors[] = 'Invalid username or password.';
            } else {
                // Check if user is verified (if verification is enabled)
                if (isset($user->verified) && $user->verified === false) {
                    $errors[] = 'Account not verified. Please check your email for verification link or contact administrator.';
                } else {
                    // Login successful
                    // Update last login time
                    $usersCollection->updateOne(
                        ['_id' => $user->_id],
                        ['$set' => ['last_login' => new MongoDB\BSON\UTCDateTime()]]
                    );
                    
                    // Store user data in session
                    $_SESSION['user_id'] = (string) $user->_id;
                    $_SESSION['username'] = $user->username;
                    $_SESSION['email'] = $user->email;
                    $_SESSION['full_name'] = $user->full_name;
                    $_SESSION['role'] = $user->role;
                    $_SESSION['status'] = $user->status;
                    $_SESSION['verified'] = $user->verified ?? true; // Default to true if not set
                    
                    // Redirect to dashboard
                    header('Location: dashboard.php');
                    exit();
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Login failed. Please try again.';
            error_log('Login error: ' . $e->getMessage());
        }
    }
    
    if (!empty($errors)) {
        $error_message = implode('<br>', $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YalaGuard - Login</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="login-container">
            <div class="auth-logo">🐘 YalaGuard</div>
            <div class="auth-subtitle">Elephant Monitoring System</div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="loginForm" novalidate>
            <div class="form-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                <div class="field-error" id="username_error"></div>
            </div>
            
            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>
                <div class="field-error" id="password_error"></div>
            </div>
            
            <button type="submit" class="login-btn" id="submitBtn">Login</button>
        </form>
        
        <div class="links">
            <p>Don't have an account? <a href="register.php">Register here</a></p>
            <p>Need verification email? <a href="resend-verification.php">Resend verification</a></p>
            <p><a href="../index.php">Back to Home</a></p>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Enhanced client-side validation for login form
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('loginForm');
            const submitBtn = document.getElementById('submitBtn');
            
            // Real-time validation
            const fields = ['username', 'password'];
            
            fields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                const errorDiv = document.getElementById(fieldName + '_error');
                
                field.addEventListener('blur', () => validateField(fieldName, field.value));
                field.addEventListener('input', () => clearFieldError(fieldName));
            });
            
            // Form submission
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                if (validateForm()) {
                    this.submit();
                }
            });
            
            function validateField(fieldName, value) {
                let error = '';
                
                switch(fieldName) {
                    case 'username':
                        if (!value.trim()) {
                            error = 'Username is required.';
                        } else if (value.length < 3) {
                            error = 'Username must be at least 3 characters.';
                        }
                        break;
                        
                    case 'password':
                        if (!value) {
                            error = 'Password is required.';
                        } else if (value.length < 1) {
                            error = 'Password is required.';
                        }
                        break;
                }
                
                if (error) {
                    showFieldError(fieldName, error);
                    return false;
                }
                
                clearFieldError(fieldName);
                return true;
            }
            
            function validateForm() {
                let isValid = true;
                
                fields.forEach(fieldName => {
                    const field = document.getElementById(fieldName);
                    if (!validateField(fieldName, field.value)) {
                        isValid = false;
                    }
                });
                
                return isValid;
            }
            
            function showFieldError(fieldName, message) {
                const errorDiv = document.getElementById(fieldName + '_error');
                errorDiv.textContent = message;
                errorDiv.style.display = 'block';
                
                const field = document.getElementById(fieldName);
                field.classList.add('error');
            }
            
            function clearFieldError(fieldName) {
                const errorDiv = document.getElementById(fieldName + '_error');
                errorDiv.style.display = 'none';
                errorDiv.textContent = '';
                
                const field = document.getElementById(fieldName);
                field.classList.remove('error');
            }
        });
    </script>
</body>
</html>
