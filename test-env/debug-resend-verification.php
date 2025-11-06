<?php
/**
 * Debug Resend Verification Email
 * 
 * This script helps debug the resend verification email issue
 */

echo "🔍 Debug Resend Verification Email\n";
echo "==================================\n\n";

// Test 1: Check if we can load the email verification service
echo "1. Testing Email Verification Service Load...\n";
try {
    require_once __DIR__ . '/../api/email-verification.php';
    echo "   ✅ Email verification service loaded successfully\n";
} catch (Exception $e) {
    echo "   ❌ Failed to load email verification service: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 2: Check if the function exists
echo "\n2. Testing Function Availability...\n";
if (function_exists('sendVerificationEmail')) {
    echo "   ✅ sendVerificationEmail function exists\n";
} else {
    echo "   ❌ sendVerificationEmail function not found\n";
    exit(1);
}

// Test 3: Test the function with sample data
echo "\n3. Testing sendVerificationEmail Function...\n";
try {
    $testEmail = 'test@example.com';
    $testUsername = 'testuser';
    $testFullName = 'Test User';
    $testToken = 'test_token_123';
    
    echo "   📧 Testing with: Email=$testEmail, Username=$testUsername, FullName=$testFullName, Token=$testToken\n";
    
    $result = sendVerificationEmail($testEmail, $testUsername, $testFullName, $testToken);
    
    echo "   📤 Function result: " . json_encode($result) . "\n";
    
    if ($result['status'] === 'success') {
        echo "   ✅ Function call successful\n";
    } else {
        echo "   ❌ Function call failed: " . $result['message'] . "\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Function call error: " . $e->getMessage() . "\n";
}

// Test 4: Check if there are any global variables or side effects
echo "\n4. Checking for Side Effects...\n";
echo "   REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET') . "\n";
echo "   Content-Type header: " . (headers_sent() ? 'Headers already sent' : 'Headers not sent yet') . "\n";

// Test 5: Check if the API endpoint logic is being triggered
echo "\n5. Checking API Endpoint Logic...\n";
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "   ⚠️  WARNING: This script is being treated as a POST request!\n";
    echo "   This means the API endpoint logic is running instead of just the function.\n";
} else {
    echo "   ✅ Not a POST request, API endpoint logic should not run\n";
}

echo "\n🎯 Debug Summary:\n";
echo "================\n";
echo "The 'Missing required fields' error suggests that the API endpoint logic\n";
echo "is running instead of just the sendVerificationEmail function.\n";
echo "\nThis can happen if:\n";
echo "1. The page is being accessed via POST request\n";
echo "2. There's a redirect or include issue\n";
echo "3. The function is being called through the API endpoint\n";
echo "\nTo fix this, make sure you're calling the function directly,\n";
echo "not making an HTTP request to the API endpoint.\n";
?>
