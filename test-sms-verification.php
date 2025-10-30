<?php
/**
 * Test SMS Verification System
 * Tests the SMS API integration with actual credentials
 */

// Load environment or use test token
$apiToken = '8b0f0b63c8ce99f576033ffd5ae0111de11e208c';
$testPhoneNumber = isset($_GET['phone']) ? $_GET['phone'] : '09123456789'; // Change this to your test number

// Function to send SMS using sms.iprogtech.com API v1
function testSendSms($apiToken, $phoneNumber, $message) {
    // Format phone number (convert to international format)
    $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
    
    // Convert to international format if starting with 0
    if (substr($phoneNumber, 0, 1) == '0') {
        $phoneNumber = '63' . substr($phoneNumber, 1);
    } elseif (substr($phoneNumber, 0, 2) != '63') {
        if (strlen($phoneNumber) == 10) {
            $phoneNumber = '63' . $phoneNumber;
        }
    }
    
    // Build API URL according to sms.iprogtech.com API v1
    $apiUrl = 'https://sms.iprogtech.com/api/v1/sms_messages';
    $params = [
        'api_token' => $apiToken,
        'message' => $message,
        'phone_number' => $phoneNumber
    ];
    
    $url = $apiUrl . '?' . http_build_query($params);
    
    echo "<h3>📤 Sending SMS Request</h3>";
    echo "<p><strong>API URL:</strong> <code>" . htmlspecialchars($url) . "</code></p>";
    echo "<p><strong>Phone Number:</strong> " . htmlspecialchars($phoneNumber) . "</p>";
    echo "<p><strong>Message:</strong> " . htmlspecialchars($message) . "</p>";
    echo "<hr>";
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/json'
    ]);
    
    // Execute request
    echo "<p><strong>⏳ Executing cURL request...</strong></p>";
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    echo "<h3>📥 Response Received</h3>";
    echo "<p><strong>HTTP Status Code:</strong> <code>$httpCode</code></p>";
    
    if ($error) {
        echo "<p style='color: red;'><strong>❌ cURL Error:</strong> $error</p>";
        return ['success' => false, 'message' => 'CURL Error: ' . $error, 'raw' => null];
    }
    
    echo "<p><strong>Raw Response:</strong></p>";
    echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    echo htmlspecialchars($response);
    echo "</pre>";
    
    // Parse response
    $result = json_decode($response, true);
    
    echo "<p><strong>Parsed Response:</strong></p>";
    echo "<pre style='background: #f4f4f4; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
    print_r($result);
    echo "</pre>";
    
    // Check for success
    $success = false;
    if ($httpCode == 200 || $httpCode == 201) {
        if (isset($result['status']) && ($result['status'] == 'success' || $result['status'] == 'sent')) {
            $success = true;
        } elseif (isset($result['success']) && $result['success'] == true) {
            $success = true;
        } elseif (isset($result['id']) || isset($result['message_id'])) {
            $success = true;
        }
    }
    
    if ($success) {
        echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4 style='margin: 0;'>✅ <strong>SUCCESS!</strong> SMS Sent Successfully</h4>";
        echo "</div>";
    } else {
        echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
        echo "<h4 style='margin: 0;'>❌ <strong>FAILED!</strong> SMS Not Sent</h4>";
        echo "<p style='margin: 10px 0 0 0;'>Error: " . ($result['message'] ?? $result['error'] ?? 'Unknown error') . "</p>";
        echo "</div>";
    }
    
    return [
        'success' => $success,
        'message' => $result['message'] ?? $result['error'] ?? ($success ? 'SMS sent successfully' : 'Failed to send SMS'),
        'http_code' => $httpCode,
        'raw_response' => $response,
        'parsed_response' => $result
    ];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMS Verification Test | HalalGuide</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            text-align: center;
        }
        .subtitle {
            color: #666;
            text-align: center;
            margin-bottom: 30px;
        }
        .test-form {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
            color: #333;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 16px;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .info-box {
            background: #e7f3ff;
            border-left: 4px solid #667eea;
            padding: 15px;
            margin: 20px 0;
            border-radius: 5px;
        }
        .warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        code {
            background: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        hr {
            margin: 20px 0;
            border: none;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 SMS Verification Test</h1>
        <p class="subtitle">Testing SMS API Integration with sms.iprogtech.com</p>
        
        <div class="info-box warning">
            <strong>⚠️ Important:</strong> Enter a valid phone number that you have access to. The SMS will be sent to this number.
        </div>
        
        <form method="GET" class="test-form">
            <div class="form-group">
                <label for="phone">Phone Number</label>
                <input type="text" id="phone" name="phone" placeholder="09123456789" value="<?php echo htmlspecialchars($testPhoneNumber); ?>" required>
                <small style="color: #666; margin-top: 5px; display: block;">Enter your phone number (e.g., 09123456789)</small>
            </div>
            <button type="submit">🚀 Send Test SMS</button>
        </form>
        
        <?php if (isset($_GET['phone']) && !empty($_GET['phone'])): ?>
            <div style="margin-top: 30px;">
                <?php
                $testMessage = "Hello! This is a test SMS from HalalGuide SMS Verification System. Your test verification code is: " . rand(100000, 999999) . ". This code expires in 10 minutes.";
                $result = testSendSms($apiToken, $testPhoneNumber, $testMessage);
                ?>
            </div>
        <?php else: ?>
            <div class="info-box">
                <strong>ℹ️ Instructions:</strong>
                <ol style="margin-left: 20px; margin-top: 10px;">
                    <li>Enter your phone number in the field above</li>
                    <li>Click "Send Test SMS"</li>
                    <li>Check your phone for the SMS message</li>
                    <li>Review the response details below</li>
                </ol>
            </div>
        <?php endif; ?>
        
        <hr>
        
        <div style="margin-top: 30px;">
            <h3>🔧 Configuration</h3>
            <p><strong>API Token:</strong> <code><?php echo substr($apiToken, 0, 10) . '...' . substr($apiToken, -10); ?></code></p>
            <p><strong>API Endpoint:</strong> <code>https://sms.iprogtech.com/api/v1/sms_messages</code></p>
            <p><strong>API Method:</strong> GET with query parameters</p>
        </div>
    </div>
</body>
</html>

