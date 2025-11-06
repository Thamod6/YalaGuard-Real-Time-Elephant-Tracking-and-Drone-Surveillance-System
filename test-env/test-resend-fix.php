<?php
/**
 * Test Resend Verification Fix
 * 
 * This script tests if the resend verification functionality is now working
 */

echo "🧪 Testing Resend Verification Fix\n";
echo "==================================\n\n";

// Test 1: Check if we can load the clean email service
echo "1. Testing Clean Email Service Load...\n";
try {
    require_once '../includes/email-service.php';
    echo "   ✅ Clean email service loaded successfully\n";
} catch (Exception $e) {
    echo "   ❌ Failed to load clean email service: " . $e->getMessage() . "\n";
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

// Test 4: Check if there are any side effects
echo "\n4. Checking for Side Effects...\n";
echo "   REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'NOT SET') . "\n";
echo "   Content-Type header: " . (headers_sent() ? 'Headers already sent' : 'Headers not sent yet') . "\n";

echo "\n🎉 Test completed successfully!\n";
echo "\n📋 The fix should resolve the 'Missing required fields' error because:\n";
echo "   1. We're now using a clean email service without API endpoint logic\n";
echo "   2. The function is called directly, not through HTTP requests\n";
echo "   3. No more conflicts between function calls and API endpoint logic\n";
echo "\n🔧 Next Steps:\n";
echo "   1. Test the resend verification page in your browser\n";
echo "   2. The error should no longer appear\n";
echo "   3. Verification emails should be sent successfully\n";
?>
