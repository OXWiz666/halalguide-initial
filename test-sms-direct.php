<?php
/**
 * Direct SMS API Test
 * Run: php test-sms-direct.php
 */

$apiToken = '8b0f0b63c8ce99f576033ffd5ae0111de11e208c';
$phoneNumber = '09123456789'; // ⚠️ CHANGE THIS TO YOUR ACTUAL PHONE NUMBER
$testCode = rand(100000, 999999);
$message = "Your HalalGuide verification code is: $testCode. Valid for 10 minutes. Do not share this code.";

echo "========================================\n";
echo "SMS Verification API Test\n";
echo "========================================\n\n";

// Format phone number
$originalPhone = $phoneNumber;
$phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
if (substr($phoneNumber, 0, 1) == '0') {
    $phoneNumber = '63' . substr($phoneNumber, 1);
}

echo "API Token: " . substr($apiToken, 0, 10) . "...\n";
echo "Phone Number: $originalPhone → $phoneNumber (international format)\n";
echo "Test Code: $testCode\n";
echo "Message: $message\n\n";

// Try different API endpoint formats based on documentation
$endpoints = [
    'Method 1: GET with api_token' => [
        'url' => 'https://sms.iprogtech.com/api/v1/sms_messages?' . http_build_query([
            'api_token' => $apiToken,
            'message' => $message,
            'phone_number' => $phoneNumber
        ]),
        'method' => 'GET'
    ],
    'Method 2: POST JSON' => [
        'url' => 'https://sms.iprogtech.com/api/v1/sms_messages',
        'method' => 'POST',
        'data' => json_encode([
            'api_token' => $apiToken,
            'message' => $message,
            'phone_number' => $phoneNumber
        ]),
        'content_type' => 'application/json'
    ],
    'Method 3: POST Form Data' => [
        'url' => 'https://sms.iprogtech.com/api/v1/sms_messages',
        'method' => 'POST',
        'data' => http_build_query([
            'api_token' => $apiToken,
            'message' => $message,
            'phone_number' => $phoneNumber
        ]),
        'content_type' => 'application/x-www-form-urlencoded'
    ]
];

$success = false;
foreach ($endpoints as $name => $config) {
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Trying: $name\n";
    echo str_repeat('=', 50) . "\n";
    echo "URL: " . ($config['method'] === 'GET' ? $config['url'] : $config['url'] . ' (POST)') . "\n";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $config['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $headers = ['Accept: application/json'];
    
    if ($config['method'] === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $config['data']);
        $headers[] = 'Content-Type: ' . ($config['content_type'] ?? 'application/x-www-form-urlencoded');
    }
    
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    // Execute
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "HTTP Status: $httpCode\n";
    
    if ($error) {
        echo "❌ cURL Error: $error\n";
        continue;
    }
    
    echo "Response: $response\n";
    
    // Parse response
    $result = json_decode($response, true);
    
    if ($result) {
        echo "Parsed:\n";
        print_r($result);
    }
    
    // Check success (based on documentation, successful response should have status 200 and message_id)
    if ($httpCode == 200) {
        if (isset($result['message_id']) || isset($result['id'])) {
            echo "\n✅ ✅ ✅ SUCCESS with $name! ✅ ✅ ✅\n";
            echo "Check your phone ($originalPhone) for the verification code: $testCode\n";
            $success = true;
            break;
        } elseif (isset($result['status']) && $result['status'] == 'success') {
            echo "\n✅ ✅ ✅ SUCCESS with $name! ✅ ✅ ✅\n";
            echo "Check your phone ($originalPhone) for the verification code: $testCode\n";
            $success = true;
            break;
        }
    }
    
    echo "❌ Failed with this method\n";
}

echo "\n" . str_repeat('=', 50) . "\n";
if ($success) {
    echo "✅ SMS SENT SUCCESSFULLY!\n";
    echo "Please check your phone for the verification code.\n";
} else {
    echo "❌ ALL METHODS FAILED\n";
    echo "\nPossible issues:\n";
    echo "1. API token may be incorrect\n";
    echo "2. Phone number format issue\n";
    echo "3. API endpoint may have changed\n";
    echo "4. Account may not have SMS credits\n";
    echo "5. Please check: https://sms.iprogtech.com/api/v1/documentation\n";
    echo "\nTo check your credits, try:\n";
    echo "curl 'https://sms.iprogtech.com/api/v1/account/sms_credits?api_token=$apiToken'\n";
}
echo str_repeat('=', 50) . "\n";
