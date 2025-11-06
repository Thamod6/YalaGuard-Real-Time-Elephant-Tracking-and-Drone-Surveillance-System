<?php
/**
 * YalaGuard Registration Page
 * 
 * This page provides a user registration form with PHP backend integration.
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

// Handle registration form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Enhanced validation
    $errors = [];
    
    // Check if all fields are filled
    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    if (empty($confirm_password)) $errors[] = 'Password confirmation is required.';
    
    // Validate field lengths and formats
    if (!empty($first_name) && (strlen($first_name) < 2 || strlen($first_name) > 50)) {
        $errors[] = 'First name must be between 2 and 50 characters.';
    }
    
    if (!empty($last_name) && (strlen($last_name) < 2 || strlen($last_name) > 50)) {
        $errors[] = 'Last name must be between 2 and 50 characters.';
    }
    
    if (!empty($username) && (strlen($username) < 3 || strlen($username) > 30)) {
        $errors[] = 'Username must be between 3 and 30 characters.';
    }
    
    if (!empty($username) && !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        $errors[] = 'Username can only contain letters, numbers, and underscores.';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/\d/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }
    }
    
    if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (empty($errors)) {
        try {
            // Include database configuration
            require_once '../config/database.php';
            
            // Get database connection
            $db = getDatabase();
            $usersCollection = $db->selectCollection('users');
            
            // Check if username already exists
            $existingUser = $usersCollection->findOne(['username' => $username]);
            if ($existingUser) {
                $errors[] = 'Username already exists. Please choose a different one.';
            } else {
                // Check if email already exists
                $existingEmail = $usersCollection->findOne(['email' => $email]);
                if ($existingEmail) {
                    $errors[] = 'Email already registered. Please use a different email.';
                } else {
                    // Create new user
                    $full_name = $first_name . ' ' . $last_name;
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    
                    $userDocument = [
                        'username' => $username,
                        'email' => $email,
                        'password' => $hashedPassword,
                        'full_name' => $full_name,
                        'role' => 'user',
                        'created_at' => new MongoDB\BSON\UTCDateTime(),
                        'updated_at' => new MongoDB\BSON\UTCDateTime(),
                        'status' => 'active',
                        'verified' => false, // Add verification status
                        'verification_token' => bin2hex(random_bytes(32)) // Add verification token
                    ];
                    
                    $result = $usersCollection->insertOne($userDocument);
                    
                    if ($result->getInsertedCount() > 0) {
                        // Send verification email
                        $emailResult = sendVerificationEmail($email, $username, $full_name, $userDocument['verification_token']);
                        
                        if ($emailResult['status'] === 'success') {
                            $success_message = 'Registration successful! Please check your email for verification before logging in.';
                        } else {
                            $success_message = 'Registration successful! However, verification email could not be sent. Please contact support.';
                            error_log('Email sending failed: ' . $emailResult['message']);
                        }
                        
                        // Clear form data on success
                        $_POST = [];
                    } else {
                        $errors[] = 'Registration failed. Please try again.';
                    }
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Registration failed. Please try again.';
            error_log('Registration error: ' . $e->getMessage());
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
    <title>YalaGuard - Register</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <div class="auth-container">
        <div class="register-container">
            <div class="auth-logo">🐘 YalaGuard</div>
            <div class="auth-subtitle">Create Your Account</div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-error"><?php echo $error_message; ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
        <?php endif; ?>
        
        <form method="POST" action="" id="registerForm" novalidate>
            <div class="form-row">
                <div class="form-group">
                    <label for="first_name">First Name *</label>
                    <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($_POST['first_name'] ?? ''); ?>" required>
                    <div class="field-error" id="first_name_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name *</label>
                    <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($_POST['last_name'] ?? ''); ?>" required>
                    <div class="field-error" id="last_name_error"></div>
                </div>
            </div>
            
            <div class="form-group full-width">
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" required>
                <div class="field-error" id="username_error"></div>
            </div>
            
            <div class="form-group full-width">
                <label for="email">Email *</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                <div class="field-error" id="email_error"></div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="password">Password *</label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-strength" id="password_strength"></div>
                    <div class="field-error" id="password_error"></div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm Password *</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                    <div class="field-error" id="confirm_password_error"></div>
                </div>
            </div>
            
            <button type="submit" class="register-btn" id="submitBtn">Create Account</button>
        </form>
        
        <div class="links">
            <p>Already have an account? <a href="login.php">Login here</a></p>
            <p><a href="../index.php">Back to Home</a></p>
        </div>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        // Enhanced client-side validation for registration form
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('registerForm');
            const submitBtn = document.getElementById('submitBtn');
            
            // Real-time validation
            const fields = ['first_name', 'last_name', 'username', 'email', 'password', 'confirm_password'];
            
            fields.forEach(fieldName => {
                const field = document.getElementById(fieldName);
                const errorDiv = document.getElementById(fieldName + '_error');
                
                field.addEventListener('blur', () => validateField(fieldName, field.value));
                field.addEventListener('input', () => clearFieldError(fieldName));
            });
            
            // Password strength indicator
            const passwordField = document.getElementById('password');
            const strengthDiv = document.getElementById('password_strength');
            
            passwordField.addEventListener('input', function() {
                const strength = YalaGuard.validatePassword(this.value);
                updatePasswordStrength(strength);
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
                    case 'first_name':
                    case 'last_name':
                        if (!value.trim()) {
                            error = 'This field is required.';
                        } else if (value.length < 2 || value.length > 50) {
                            error = 'Must be between 2 and 50 characters.';
                        }
                        break;
                        
                    case 'username':
                        if (!value.trim()) {
                            error = 'Username is required.';
                        } else if (value.length < 3 || value.length > 30) {
                            error = 'Username must be between 3 and 30 characters.';
                        } else if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                            error = 'Username can only contain letters, numbers, and underscores.';
                        }
                        break;
                        
                    case 'email':
                        if (!value.trim()) {
                            error = 'Email is required.';
                        } else if (!YalaGuard.validateEmail(value)) {
                            error = 'Please enter a valid email address.';
                        }
                        break;
                        
                    case 'password':
                        if (!value) {
                            error = 'Password is required.';
                        } else if (value.length < 8) {
                            error = 'Password must be at least 8 characters.';
                        } else {
                            const strength = YalaGuard.validatePassword(value);
                            if (!strength.isValid) {
                                error = 'Password must contain uppercase, lowercase, and numbers.';
                            }
                        }
                        break;
                        
                    case 'confirm_password':
                        const password = document.getElementById('password').value;
                        if (!value) {
                            error = 'Please confirm your password.';
                        } else if (value !== password) {
                            error = 'Passwords do not match.';
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
            
            function updatePasswordStrength(strength) {
                let html = '<div class="strength-bar">';
                html += '<div class="strength-fill" style="width: ' + (strength.isValid ? '100%' : '25%') + '"></div>';
                html += '</div>';
                html += '<div class="strength-text">';
                
                if (strength.isValid) {
                    html += '<span class="strength-strong">Strong Password</span>';
                } else {
                    html += '<span class="strength-weak">Weak Password</span>';
                }
                
                html += '</div>';
                strengthDiv.innerHTML = html;
            }
        });
    </script>
</body>
</html>
