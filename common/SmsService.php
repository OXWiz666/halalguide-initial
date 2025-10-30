<?php
/**
 * SMS Service for sms.iprogtech.com
 * Handles sending SMS messages including verification codes
 */

class SmsService {
    private $apiKey;
    
    public function __construct() {
        // Load environment variables
        $envFile = __DIR__ . '/../.env';
        if (file_exists($envFile)) {
            // Suppress parse_ini_file warnings and handle errors gracefully
            $envVars = @parse_ini_file($envFile);
            if ($envVars === false) {
                // If parse_ini_file fails, try reading file manually
                $envVars = [];
                $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($lines) {
                    foreach ($lines as $line) {
                        // Skip comments
                        if (strpos(trim($line), '#') === 0) {
                            continue;
                        }
                        // Parse KEY=VALUE format
                        if (strpos($line, '=') !== false) {
                            list($key, $value) = explode('=', $line, 2);
                            $envVars[trim($key)] = trim($value);
                        }
                    }
                }
            }
            $this->apiKey = $envVars['SMS_API_KEY'] ?? $envVars['SMS_API_TOKEN'] ?? '';
            $this->senderId = $envVars['SMS_SENDER_ID'] ?? '';
            
            // Debug: Log if API key was loaded (only in development)
            if (empty($this->apiKey)) {
                error_log("SmsService: API key not found in .env file. Available keys: " . implode(', ', array_keys($envVars)));
            }
        } else {
            // Fallback to default (should be set in .env)
            $this->apiKey = getenv('SMS_API_KEY') ?: getenv('SMS_API_TOKEN') ?: '';
            $this->senderId = getenv('SMS_SENDER_ID') ?: '';
            error_log("SmsService: .env file not found at " . $envFile);
        }
        
        // Trim any whitespace from API key
        $this->apiKey = trim($this->apiKey ?? '');
    }
    
    /**
     * Send SMS message using sms.iprogtech.com API v1
     * @param string $phoneNumber Phone number in format (e.g., 09123456789 or 639123456789)
     * @param string $message Message to send
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function sendSms($phoneNumber, $message) {
        // Trim the API key to remove any whitespace
        $this->apiKey = trim($this->apiKey ?? '');
        
        if (empty($this->apiKey) || $this->apiKey === 'your_api_token_here' || strlen($this->apiKey) < 10) {
            return [
                'success' => false,
                'message' => 'SMS service not configured. Please set SMS_API_KEY or SMS_API_TOKEN in .env file with a valid token.',
                'data' => ['debug' => 'API key is empty, placeholder, or too short (must be at least 10 characters)']
            ];
        }
        
        // Format phone number (remove spaces, dashes, etc., convert to international format)
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Convert to international format if starting with 0 (e.g., 09123456789 -> 639123456789)
        if (substr($phoneNumber, 0, 1) == '0') {
            $phoneNumber = '63' . substr($phoneNumber, 1);
        } elseif (substr($phoneNumber, 0, 2) != '63') {
            // If doesn't start with 63, assume it needs country code
            if (strlen($phoneNumber) == 10) {
                $phoneNumber = '63' . $phoneNumber;
            }
        }
        
        // Build API request according to sms.iprogtech.com API v1 documentation
        // Method: POST with JSON body
        $apiUrl = 'https://sms.iprogtech.com/api/v1/sms_messages';
        
        $postData = [
            'api_token' => $this->apiKey, // Already trimmed in validation above
            'message' => $message,
            'phone_number' => $phoneNumber
        ];
        
        // Initialize cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        
        // Execute request
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'message' => 'CURL Error: ' . $error,
                'data' => []
            ];
        }
        
        // Parse response
        $result = json_decode($response, true);
        
        // Handle API response (check for success indicators)
        // Based on actual API response: {"status":200,"message":"...","message_id":"iSms-XXX"}
        if ($httpCode == 200) {
            // Check for message_id in response (indicates success)
            if (isset($result['message_id']) || isset($result['id'])) {
                return [
                    'success' => true,
                    'message' => $result['message'] ?? 'SMS sent successfully',
                    'data' => $result
                ];
            } elseif (isset($result['status']) && $result['status'] == 200) {
                // Status 200 in response body also indicates success
                return [
                    'success' => true,
                    'message' => $result['message'] ?? 'SMS sent successfully',
                    'data' => $result
                ];
            } elseif (isset($result['status']) && ($result['status'] == 'success' || $result['status'] == 'sent')) {
                return [
                    'success' => true,
                    'message' => $result['message'] ?? 'SMS sent successfully',
                    'data' => $result
                ];
            }
        }
        
        // Error response - include debug info
        $errorMessage = $result['message'] ?? $result['error'] ?? 'Failed to send SMS';
        if ($httpCode != 200) {
            $errorMessage .= " (HTTP $httpCode)";
        }
        
        // Check if it's an "Invalid Token" error specifically
        $isInvalidToken = (
            stripos($errorMessage, 'invalid token') !== false ||
            stripos($errorMessage, 'invalid_token') !== false ||
            stripos($errorMessage, 'unauthorized') !== false ||
            (isset($result['status']) && $result['status'] == 401)
        );
        
        if ($isInvalidToken) {
            $errorMessage = "Invalid Token: Your SMS API token is incorrect or expired. " .
                          "Please check your .env file and ensure SMS_API_KEY contains a valid token from sms.iprogtech.com";
        }
        
        return [
            'success' => false,
            'message' => $errorMessage,
            'data' => [
                'response' => $result ?? [],
                'http_code' => $httpCode,
                'raw_response' => $response,
                'phone_formatted' => $phoneNumber,
                'api_key_length' => strlen($this->apiKey ?? ''),
                'api_key_prefix' => substr($this->apiKey ?? '', 0, 5) . '...' . (strlen($this->apiKey ?? '') > 10 ? substr($this->apiKey ?? '', -5) : ''),
                'debug' => $isInvalidToken ? 'Token authentication failed - verify token in .env file' : 'Check API key and phone number format'
            ]
        ];
    }
    
    /**
     * Send verification code SMS
     * @param string $phoneNumber Phone number
     * @param string $code Verification code
     * @return array
     */
    public function sendVerificationCode($phoneNumber, $code) {
        $message = "Your HalalGuide verification code is: {$code}. Valid for 10 minutes. Do not share this code.";
        return $this->sendSms($phoneNumber, $message);
    }
}
