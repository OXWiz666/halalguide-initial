<?php
/**
 * Example: How to integrate SMS Verification into Registration
 * 
 * This is a reference implementation showing how to add SMS verification
 * to your registration.php file.
 */

// ============================================
// PART 1: Include Required Files (Top of registration.php)
// ============================================
include 'common/connection.php';
include 'common/randomstrings.php';
include 'common/SmsVerification.php';

// Initialize SMS Verification
$smsVerification = new SmsVerification($conn);
$sms_verification_id = $_SESSION['sms_verification_id'] ?? null;
$phone_verified = $_SESSION['phone_verified'] ?? false;

// ============================================
// PART 2: Handle SMS Verification Requests
// ============================================

// Step 1: Send Verification Code
if (isset($_POST['send_verification_code'])) {
    $phoneNumber = mysqli_real_escape_string($conn, trim($_POST['contact_no']));
    
    // Validate phone number format
    if (strlen($phoneNumber) != 11 || !preg_match('/^09[0-9]{9}$/', $phoneNumber)) {
        $sms_error = "Invalid phone number format. Please use 11 digits starting with 09.";
    } else {
        $result = $smsVerification->generateAndSendCode($phoneNumber, null, 'registration');
        
        if ($result['success']) {
            $_SESSION['sms_verification_id'] = $result['verification_id'];
            $_SESSION['sms_phone_number'] = $phoneNumber;
            $sms_verification_id = $result['verification_id'];
            $sms_success = "Verification code sent to " . $phoneNumber;
        } else {
            $sms_error = $result['message'];
        }
    }
}

// Step 2: Verify Code
if (isset($_POST['verify_code'])) {
    $code = mysqli_real_escape_string($conn, trim($_POST['verification_code']));
    $verificationId = $_SESSION['sms_verification_id'] ?? '';
    $phoneNumber = $_SESSION['sms_phone_number'] ?? '';
    
    if (empty($code) || strlen($code) != 6) {
        $verify_error = "Please enter a valid 6-digit verification code.";
    } else {
        $result = $smsVerification->verifyCode($verificationId, $code, $phoneNumber);
        
        if ($result['success']) {
            $_SESSION['phone_verified'] = true;
            $phone_verified = true;
            $verify_success = "Phone number verified successfully!";
        } else {
            $verify_error = $result['message'];
        }
    }
}

// Step 3: Resend Code
if (isset($_POST['resend_code'])) {
    $verificationId = $_SESSION['sms_verification_id'] ?? '';
    
    if (empty($verificationId)) {
        $resend_error = "No verification session found. Please request a new code.";
    } else {
        $result = $smsVerification->resendCode($verificationId);
        
        if ($result['success']) {
            $_SESSION['sms_verification_id'] = $result['verification_id'];
            $sms_verification_id = $result['verification_id'];
            $resend_success = "New verification code sent!";
        } else {
            $resend_error = $result['message'];
        }
    }
}

// ============================================
// PART 3: Modify Registration Logic
// ============================================

// In your registration submission handler, add phone verification check:
if (isset($_POST['btnRegister'])) {
    // ... existing validation ...
    
    // NEW: Check if phone is verified
    if (!$phone_verified) {
        $errors[] = "Please verify your phone number before completing registration.";
    }
    
    if (empty($errors)) {
        // Proceed with registration only if phone is verified
        // ... existing registration code ...
        
        // After successful registration, clear verification session
        unset($_SESSION['sms_verification_id']);
        unset($_SESSION['phone_verified']);
        unset($_SESSION['sms_phone_number']);
    }
}

// ============================================
// PART 4: Add to Registration Form HTML
// ============================================
?>

<!-- Add this section after your contact number input field -->

<div class="form-group">
    <label class="form-label">Phone Verification</label>
    
    <?php if (!$phone_verified): ?>
        <!-- Phone not verified yet -->
        <div class="phone-verification-section">
            <div class="input-group mb-2">
                <input type="text" 
                       name="contact_no" 
                       id="contact_no" 
                       class="form-control" 
                       placeholder="09123456789" 
                       required 
                       pattern="[0-9]{11}"
                       value="<?php echo isset($_POST['contact_no']) ? htmlspecialchars($_POST['contact_no']) : ''; ?>">
                <button type="submit" 
                        name="send_verification_code" 
                        class="btn btn-primary"
                        id="btnSendCode">
                    <i class="fas fa-paper-plane"></i> Send Code
                </button>
            </div>
            
            <?php if (isset($sms_success)): ?>
                <div class="alert alert-success"><?php echo $sms_success; ?></div>
            <?php endif; ?>
            <?php if (isset($sms_error)): ?>
                <div class="alert alert-danger"><?php echo $sms_error; ?></div>
            <?php endif; ?>
            
            <!-- Code verification input (show after code sent) -->
            <?php if ($sms_verification_id): ?>
                <div class="verification-code-section mt-3">
                    <label>Enter Verification Code</label>
                    <div class="input-group">
                        <input type="text" 
                               name="verification_code" 
                               class="form-control" 
                               placeholder="000000" 
                               maxlength="6"
                               pattern="[0-9]{6}">
                        <button type="submit" 
                                name="verify_code" 
                                class="btn btn-success">
                            <i class="fas fa-check"></i> Verify
                        </button>
                        <button type="submit" 
                                name="resend_code" 
                                class="btn btn-outline-secondary">
                            <i class="fas fa-redo"></i> Resend
                        </button>
                    </div>
                    <small class="text-muted">Code expires in 10 minutes</small>
                    
                    <?php if (isset($verify_success)): ?>
                        <div class="alert alert-success mt-2"><?php echo $verify_success; ?></div>
                    <?php endif; ?>
                    <?php if (isset($verify_error)): ?>
                        <div class="alert alert-danger mt-2"><?php echo $verify_error; ?></div>
                    <?php endif; ?>
                    <?php if (isset($resend_success)): ?>
                        <div class="alert alert-success mt-2"><?php echo $resend_success; ?></div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Phone verified -->
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> 
            Phone number verified: <?php echo htmlspecialchars($_SESSION['sms_phone_number'] ?? ''); ?>
        </div>
    <?php endif; ?>
</div>

<!-- Add CSS for better styling -->
<style>
.phone-verification-section {
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 8px;
    background: #f9f9f9;
}

.verification-code-section {
    padding: 10px;
    background: white;
    border-radius: 5px;
}

.input-group .btn {
    white-space: nowrap;
}
</style>

<!-- Add JavaScript for auto-submit on 6 digits -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const codeInput = document.querySelector('input[name="verification_code"]');
    if (codeInput) {
        codeInput.addEventListener('input', function(e) {
            // Allow only numbers
            this.value = this.value.replace(/[^0-9]/g, '');
            
            // Auto-submit when 6 digits entered (optional)
            // if (this.value.length === 6) {
            //     document.querySelector('button[name="verify_code"]').click();
            // }
        });
    }
});
</script>
