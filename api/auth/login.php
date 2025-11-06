<?php
/**
 * YalaGuard User Login API
 * 
 * This endpoint handles user authentication for the YalaGuard system.
 * It validates user credentials and returns user information on success.
 */

// Include database configuration
require_once '../../config/database.php';

// Set content type to JSON
header('Content-Type: application/json');

// Handle CORS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST method for login
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Method not allowed. Only POST requests are supported.',
        'allowed_methods' => ['POST']
    ]);
    exit();
}

try {
    // Get JSON input from request body
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $requiredFields = ['username', 'password'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || empty(trim($input[$field]))) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Missing required fields: ' . implode(', ', $missingFields),
            'required_fields' => $requiredFields
        ]);
        exit();
    }
    
    // Additional validation
    $username = trim($input['username']);
    $password = $input['password'];
    
    // Validate username format
    if (strlen($username) < 3 || strlen($username) > 30) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Username must be between 3 and 30 characters'
        ]);
        exit();
    }
    
    // Validate username characters
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Username can only contain letters, numbers, and underscores'
        ]);
        exit();
    }
    
    // Validate password length
    if (strlen($password) < 1) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Password is required'
        ]);
        exit();
    }
    
    // Get database connection
    $db = getDatabase();
    $usersCollection = $db->selectCollection('users');
    
    // Find user by username
    $user = $usersCollection->findOne(['username' => $username]);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid username or password'
        ]);
        exit();
    }
    
    // Check if user is active
    if ($user->status !== 'active') {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Account is deactivated. Please contact administrator.'
        ]);
        exit();
    }
    
    // Check if user is verified (if verification is enabled)
    if (isset($user->verified) && $user->verified === false) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Account not verified. Please check your email for verification link or contact administrator.'
        ]);
        exit();
    }
    
    // Verify password
    if (!password_verify($password, $user->password)) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid username or password'
        ]);
        exit();
    }
    
    // Update last login time
    $usersCollection->updateOne(
        ['_id' => $user->_id],
        ['$set' => ['last_login' => new MongoDB\BSON\UTCDateTime()]]
    );
    
    // Start session and store user data
    session_start();
    $_SESSION['user_id'] = (string) $user->_id;
    $_SESSION['username'] = $user->username;
    $_SESSION['email'] = $user->email;
    $_SESSION['full_name'] = $user->full_name;
    $_SESSION['role'] = $user->role;
    $_SESSION['status'] = $user->status;
    $_SESSION['verified'] = $user->verified ?? true; // Default to true if not set
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Login successful',
        'user' => [
            'user_id' => (string) $user->_id,
            'username' => $user->username,
            'email' => $user->email,
            'full_name' => $user->full_name,
            'role' => $user->role,
            'status' => $user->status,
            'verified' => $user->verified ?? true
        ]
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
