<?php
/**
 * YalaGuard Email System Test
 * 
 * This script tests the email verification system
 */

echo "📧 YalaGuard Email System Test\n";
echo "==============================\n\n";

// Test 1: Check if .env file exists and has email configuration
echo "1. Checking Email Configuration...\n";
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    echo "   ❌ .env file not found\n";
    exit(1);
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

// Check required email fields
$requiredFields = ['MAIL_HOST', 'MAIL_PORT', 'MAIL_USERNAME', 'MAIL_PASSWORD'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($_ENV[$field]) || empty($_ENV[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    echo "   ❌ Missing email configuration: " . implode(', ', $missingFields) . "\n";
    echo "   Please add these fields to your .env file\n";
    exit(1);
}

echo "   ✅ Email configuration found\n";
echo "   📧 Host: " . $_ENV['MAIL_HOST'] . "\n";
echo "   📧 Port: " . $_ENV['MAIL_PORT'] . "\n";
echo "   📧 Username: " . $_ENV['MAIL_USERNAME'] . "\n";
echo "   📧 Password: " . (strlen($_ENV['MAIL_PASSWORD']) > 4 ? substr($_ENV['MAIL_PASSWORD'], 0, 4) . '****' : '****') . "\n";

// Test 2: Check if PHPMailer is available
echo "\n2. Checking PHPMailer...\n";
if (!file_exists(__DIR__ . '/../vendor/autoload.php')) {
    echo "   ❌ Composer autoload not found\n";
    exit(1);
}

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $mailerExists = class_exists('PHPMailer\PHPMailer\PHPMailer');
    if ($mailerExists) {
        echo "   ✅ PHPMailer is available\n";
    } else {
        echo "   ❌ PHPMailer class not found\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "   ❌ Error loading PHPMailer: " . $e->getMessage() . "\n";
    exit(1);
}

// Test 3: Test email verification service
echo "\n3. Testing Email Verification Service...\n";
try {
    require_once '../api/email-verification.php';
    
    // Test with sample data
    $testEmail = $_ENV['MAIL_USERNAME']; // Send to yourself for testing
    $testUsername = 'testuser_' . time();
    $testFullName = 'Test User';
    $testToken = bin2hex(random_bytes(32));
    
    echo "   📧 Testing email to: $testEmail\n";
    
    $result = sendVerificationEmail($testEmail, $testUsername, $testFullName, $testToken);
    
    if ($result['status'] === 'success') {
        echo "   ✅ Email sent successfully!\n";
        echo "   📝 Check your inbox for the verification email\n";
    } else {
        echo "   ❌ Email sending failed: " . $result['message'] . "\n";
        
        // Provide troubleshooting tips
        echo "\n🔧 Email Troubleshooting:\n";
        echo "   1. Check your Gmail app password (not regular password)\n";
        echo "   2. Enable 2-factor authentication on Gmail\n";
        echo "   3. Generate an app password: Google Account → Security → App passwords\n";
        echo "   4. Make sure your IP is not blocked by Gmail\n";
        echo "   5. Check Gmail's 'Less secure app access' settings\n";
    }
    
} catch (Exception $e) {
    echo "   ❌ Error testing email service: " . $e->getMessage() . "\n";
}

// Test 4: Check verification endpoint
echo "\n4. Testing Verification Endpoint...\n";
$verificationFile = __DIR__ . '/../api/verify-email.php';
if (file_exists($verificationFile)) {
    echo "   ✅ Verification endpoint exists\n";
} else {
    echo "   ❌ Verification endpoint not found\n";
}

echo "\n🎉 Email system test completed!\n";
echo "\n📋 Next Steps:\n";
echo "   1. Check your email inbox for the test verification email\n";
echo "   2. If email received, click the verification link\n";
echo "   3. If email not received, check spam folder\n";
echo "   4. Verify your Gmail app password is correct\n";
echo "   5. Test user registration to see the full flow\n";
echo "\n🔧 Gmail App Password Setup:\n";
echo "   1. Go to Google Account settings\n";
echo "   2. Security → 2-Step Verification → App passwords\n";
echo "   3. Generate password for 'Mail'\n";
echo "   4. Use this password in your .env file\n";
?>
