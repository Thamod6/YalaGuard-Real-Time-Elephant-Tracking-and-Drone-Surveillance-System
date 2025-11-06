<?php
/**
 * YalaGuard User Registration API
 * 
 * This endpoint handles user registration for the YalaGuard system.
 * It creates new user accounts with hashed passwords.
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

// Only allow POST method for registration
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
    $requiredFields = ['username', 'email', 'password', 'full_name'];
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
    
    // Enhanced validation
    $username = trim($input['username']);
    $email = trim($input['email']);
    $password = $input['password'];
    $full_name = trim($input['full_name']);
    
    // Validate username format and length
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
    
    // Validate full name length
    if (strlen($full_name) < 2 || strlen($full_name) > 100) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Full name must be between 2 and 100 characters'
        ]);
        exit();
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid email format'
        ]);
        exit();
    }
    
    // Validate password strength (minimum 8 characters with complexity)
    if (strlen($password) < 8) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Password must be at least 8 characters long'
        ]);
        exit();
    }
    
    // Check password complexity
    if (!preg_match('/[A-Z]/', $password)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Password must contain at least one uppercase letter'
        ]);
        exit();
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Password must contain at least one lowercase letter'
        ]);
        exit();
    }
    
    if (!preg_match('/\d/', $password)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Password must contain at least one number'
        ]);
        exit();
    }
    
    // Get database connection
    $db = getDatabase();
    $usersCollection = $db->selectCollection('users');
    
    // Check if username already exists
    $existingUser = $usersCollection->findOne(['username' => $username]);
    if ($existingUser) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Username already exists'
        ]);
        exit();
    }
    
    // Check if email already exists
    $existingEmail = $usersCollection->findOne(['email' => $email]);
    if ($existingEmail) {
        http_response_code(409);
        echo json_encode([
            'status' => 'error',
            'message' => 'Email already registered'
        ]);
        exit();
    }
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    
    // Prepare user document
    $userDocument = [
        'username' => $username,
        'email' => $email,
        'password' => $hashedPassword,
        'full_name' => $full_name,
        'role' => 'user', // Default role
        'created_at' => new MongoDB\BSON\UTCDateTime(),
        'updated_at' => new MongoDB\BSON\UTCDateTime(),
        'status' => 'active',
        'verified' => false, // Add verification status
        'verification_token' => bin2hex(random_bytes(32)) // Add verification token
    ];
    
    // Insert user into database
    $result = $usersCollection->insertOne($userDocument);
    
    if ($result->getInsertedCount() > 0) {
        $insertedId = $result->getInsertedId();
        
        // Send verification email
        require_once '../../includes/email-service.php';
        $emailResult = sendVerificationEmail(
            $userDocument['email'],
            $userDocument['username'],
            $userDocument['full_name'],
            $userDocument['verification_token']
        );
        
        $message = $emailResult['status'] === 'success' 
            ? 'User registered successfully. Please check your email for verification.'
            : 'User registered successfully. However, verification email could not be sent.';
        
        http_response_code(201);
        echo json_encode([
            'status' => 'success',
            'message' => $message,
            'user_id' => (string) $insertedId,
            'email_sent' => $emailResult['status'] === 'success',
            'user' => [
                'username' => $userDocument['username'],
                'email' => $userDocument['email'],
                'full_name' => $userDocument['full_name'],
                'role' => $userDocument['role'],
                'status' => $userDocument['status'],
                'verified' => $userDocument['verified']
            ]
        ]);
    } else {
        throw new Exception('Failed to create user account');
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>
