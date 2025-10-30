<?php
/**
 * SMS Verification Handler
 * Manages OTP generation, storage, and verification
 */

include 'connection.php';
include 'randomstrings.php';

class SmsVerification {
    private $conn;
    private $smsService;
    private $codeLength = 6;
    private $codeExpiryMinutes = 10;
    private $maxAttempts = 5;
    
    public function __construct($connection) {
        $this->conn = $connection;
        require_once __DIR__ . '/SmsService.php';
        $this->smsService = new SmsService();
    }
    
    /**
     * Generate and send verification code
     * @param string $phoneNumber Phone number to verify
     * @param string $useraccountId Optional user account ID for registration
     * @param string $type Verification type (registration, login, password_reset)
     * @return array ['success' => bool, 'message' => string, 'verification_id' => string, 'code' => string]
     */
    public function generateAndSendCode($phoneNumber, $useraccountId = null, $type = 'registration') {
        // Clean phone number
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Generate verification code
        $code = $this->generateCode();
        
        // Generate verification ID (use $specialcasesCHAR if available, otherwise let function use default)
        global $specialcasesCHAR;
        $charSet = isset($specialcasesCHAR) && !empty($specialcasesCHAR) ? $specialcasesCHAR : null;
        $verification_id = generate_string($charSet, 25);
        
        // Set expiry time
        $expiresAt = date('Y-m-d H:i:s', strtotime("+{$this->codeExpiryMinutes} minutes"));
        
        // Invalidate previous unverified codes for this phone number
        // This is safe even if no records exist - mysqli_query returns false only on error
        $phoneNumberEscaped = mysqli_real_escape_string($this->conn, $phoneNumber);
        @mysqli_query($this->conn, 
            "UPDATE tbl_sms_verification 
             SET is_verified = -1 
             WHERE phone_number = '$phoneNumberEscaped' 
             AND is_verified = 0 
             AND expires_at > NOW()"
        );
        
        // Insert new verification record
        $insertQuery = "INSERT INTO tbl_sms_verification 
            (verification_id, phone_number, verification_code, useraccount_id, verification_type, expires_at, date_added)
            VALUES ('$verification_id', '$phoneNumber', '$code', " . 
            ($useraccountId ? "'$useraccountId'" : "NULL") . ", '$type', '$expiresAt', NOW())";
        
        if (!mysqli_query($this->conn, $insertQuery)) {
            return [
                'success' => false,
                'message' => 'Failed to create verification record: ' . mysqli_error($this->conn),
                'verification_id' => null,
                'code' => null
            ];
        }
        
        // Send SMS
        $smsResult = $this->smsService->sendVerificationCode($phoneNumber, $code);
        
        if (!$smsResult['success']) {
            // SMS failed, but code is stored - user can request resend
            return [
                'success' => false,
                'message' => 'Code generated but SMS sending failed: ' . $smsResult['message'],
                'verification_id' => $verification_id,
                'code' => null // Don't expose code in error response
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Verification code sent successfully',
            'verification_id' => $verification_id,
            'code' => null // Never return actual code for security
        ];
    }
    
    /**
     * Verify code
     * @param string $verificationId Verification ID
     * @param string $code Code to verify
     * @param string $phoneNumber Optional phone number for additional verification
     * @return array ['success' => bool, 'message' => string, 'data' => array]
     */
    public function verifyCode($verificationId, $code, $phoneNumber = null) {
        // Clean inputs
        $verificationId = mysqli_real_escape_string($this->conn, $verificationId);
        $code = mysqli_real_escape_string($this->conn, $code);
        
        // Build query
        $query = "SELECT * FROM tbl_sms_verification 
                  WHERE verification_id = '$verificationId' 
                  AND verification_code = '$code' 
                  AND is_verified = 0";
        
        if ($phoneNumber) {
            $phoneNumber = mysqli_real_escape_string($this->conn, preg_replace('/[^0-9]/', '', $phoneNumber));
            $query .= " AND phone_number = '$phoneNumber'";
        }
        
        $result = mysqli_query($this->conn, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            // Increment attempts if verification exists
            if ($result) {
                mysqli_query($this->conn, 
                    "UPDATE tbl_sms_verification 
                     SET attempts = attempts + 1 
                     WHERE verification_id = '$verificationId'"
                );
            }
            
            return [
                'success' => false,
                'message' => 'Invalid verification code',
                'data' => []
            ];
        }
        
        $row = mysqli_fetch_assoc($result);
        
        // Check if expired
        if (strtotime($row['expires_at']) < time()) {
            return [
                'success' => false,
                'message' => 'Verification code has expired',
                'data' => []
            ];
        }
        
        // Check attempts
        if ($row['attempts'] >= $this->maxAttempts) {
            return [
                'success' => false,
                'message' => 'Maximum verification attempts exceeded. Please request a new code.',
                'data' => []
            ];
        }
        
        // Mark as verified
        $updateQuery = "UPDATE tbl_sms_verification 
                        SET is_verified = 1, 
                            verified_at = NOW() 
                        WHERE verification_id = '$verificationId'";
        
        if (!mysqli_query($this->conn, $updateQuery)) {
            return [
                'success' => false,
                'message' => 'Failed to update verification status: ' . mysqli_error($this->conn),
                'data' => []
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Phone number verified successfully',
            'data' => $row
        ];
    }
    
    /**
     * Resend verification code
     * NOTE: This method is deprecated - use generateAndSendCode() directly instead
     * to avoid confusion and ensure only one code is sent
     * @param string $verificationId Existing verification ID
     * @return array
     */
    public function resendCode($verificationId) {
        // This method is kept for backwards compatibility but should not be used
        // The resend action in verify-phone.php now directly calls generateAndSendCode()
        // to prevent double sending
        $verificationId = mysqli_real_escape_string($this->conn, $verificationId);
        
        $query = "SELECT * FROM tbl_sms_verification WHERE verification_id = '$verificationId'";
        $result = mysqli_query($this->conn, $query);
        
        if (!$result || mysqli_num_rows($result) == 0) {
            return [
                'success' => false,
                'message' => 'Verification record not found'
            ];
        }
        
        $row = mysqli_fetch_assoc($result);
        
        // Generate new code and send
        return $this->generateAndSendCode(
            $row['phone_number'], 
            $row['useraccount_id'], 
            $row['verification_type']
        );
    }
    
    /**
     * Generate random 6-digit code
     * @return string
     */
    private function generateCode() {
        return str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Check if phone number is verified (for registration)
     * @param string $phoneNumber
     * @param string $type Verification type
     * @return bool
     */
    public function isPhoneVerified($phoneNumber, $type = 'registration') {
        $phoneNumber = mysqli_real_escape_string($this->conn, preg_replace('/[^0-9]/', '', $phoneNumber));
        
        $query = "SELECT COUNT(*) as count FROM tbl_sms_verification 
                  WHERE phone_number = '$phoneNumber' 
                  AND verification_type = '$type' 
                  AND is_verified = 1 
                  AND verified_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        
        $result = mysqli_query($this->conn, $query);
        $row = mysqli_fetch_assoc($result);
        
        return ($row['count'] > 0);
    }
}
