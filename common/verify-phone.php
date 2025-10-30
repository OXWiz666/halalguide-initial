<?php
/**
 * Phone Verification Page
 * Used after registration to verify phone number via SMS
 */

session_start();
include 'connection.php';
include 'randomstrings.php';
require_once 'SmsVerification.php';

// Check if we have registration data in session
if (!isset($_SESSION['pending_registration'])) {
    // No pending registration, redirect based on where they might have come from
    // Try to redirect to appropriate registration page based on referrer or default to tourist
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, '/hcb/') !== false) {
        header('Location: ../hcb/registration.php');
    } elseif (strpos($referer, '/company/') !== false) {
        header('Location: ../company/company.php');
    } else {
        header('Location: ../registration.php');
    }
    exit;
}

$registration_data = $_SESSION['pending_registration'];
$smsVerification = new SmsVerification($conn);

// Get user type and phone number based on user type
$user_type = $registration_data['user_type'] ?? 'Tourist';

// For Company registration, use contact person's phone (cp_contact_no)
// For Tourist and HCB, use contact_no
if ($user_type === 'Company') {
    $phoneNumber = $registration_data['cp_contact_no'] ?? '';
} else {
    $phoneNumber = $registration_data['contact_no'] ?? '';
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        if ($action === 'send_code') {
            // Send verification code
            if (empty($phoneNumber)) {
                echo json_encode(['success' => false, 'message' => 'Phone number not found']);
                exit;
            }
            
            $result = $smsVerification->generateAndSendCode($phoneNumber, null, 'registration');
            
            if ($result['success']) {
                $_SESSION['sms_verification_id'] = $result['verification_id'];
                $_SESSION['sms_sent'] = true;
                // Clear any previous errors
                if (isset($_SESSION['sms_error'])) {
                    unset($_SESSION['sms_error']);
                }
            } else {
                // Store error
                $_SESSION['sms_error'] = $result['message'] ?? 'Failed to send verification code';
            }
            
            echo json_encode($result);
            exit;
            
        } elseif ($action === 'verify_code') {
            // Verify code
            $code = mysqli_real_escape_string($conn, trim($_POST['code'] ?? ''));
            $verificationId = $_SESSION['sms_verification_id'] ?? '';
            
            if (empty($code) || empty($verificationId)) {
                echo json_encode(['success' => false, 'message' => 'Invalid request']);
                exit;
            }
            
            if (empty($phoneNumber)) {
                echo json_encode(['success' => false, 'message' => 'Phone number not found']);
                exit;
            }
            
            $result = $smsVerification->verifyCode($verificationId, $code, $phoneNumber);
            
            if ($result['success']) {
                // Phone verified, complete registration
                $_SESSION['phone_verified'] = true;
            }
            
            echo json_encode($result);
            exit;
            
        } elseif ($action === 'resend_code') {
            // Resend code - prevent double sending
            if (empty($phoneNumber)) {
                echo json_encode(['success' => false, 'message' => 'Phone number not found']);
                exit;
            }
            
            // Always generate a NEW code when resending (don't reuse old verification ID)
            // This prevents double sending and creates a fresh code
            $result = $smsVerification->generateAndSendCode($phoneNumber, null, 'registration');
            
            if ($result['success']) {
                // Update session with new verification ID
                $_SESSION['sms_verification_id'] = $result['verification_id'];
                $_SESSION['sms_sent'] = true;
                // Clear any previous errors
                if (isset($_SESSION['sms_error'])) {
                    unset($_SESSION['sms_error']);
                }
            } else {
                // Keep old verification_id if resend fails so user can try again
                // But store the error
                $_SESSION['sms_error'] = $result['message'] ?? 'Failed to resend verification code';
            }
            
            echo json_encode($result);
            exit;
        }
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

// Don't auto-send - user must click button to send code
$smsError = null;
$codeSent = isset($_SESSION['sms_verification_id']) && isset($_SESSION['sms_sent']);

// Check if there was a previous error
if (isset($_SESSION['sms_error'])) {
    $smsError = $_SESSION['sms_error'];
}

// If phone number is missing, show error
if (empty($phoneNumber)) {
    $smsError = 'Phone number not found in registration data';
    $_SESSION['sms_error'] = $smsError;
}

// Get masked phone number for display
if (!empty($phoneNumber) && strlen($phoneNumber) > 7) {
    $phone_display = substr($phoneNumber, 0, 3) . '****' . substr($phoneNumber, -4);
} else {
    $phone_display = 'N/A';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Phone Number | HalalGuide</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .verification-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .verification-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            color: white;
            font-size: 36px;
        }
        
        h2 {
            text-align: center;
            color: #333;
            margin-bottom: 10px;
            font-weight: 600;
        }
        
        .subtitle {
            text-align: center;
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .phone-display {
            text-align: center;
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-size: 18px;
            font-weight: 600;
            color: #333;
        }
        
        .otp-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .otp-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 600;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn-verify {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
        }
        
        .btn-verify:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-verify:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .resend-section {
            text-align: center;
        }
        
        .resend-btn {
            background: none;
            border: none;
            color: #667eea;
            cursor: pointer;
            font-weight: 600;
            text-decoration: underline;
            padding: 0;
        }
        
        .resend-btn:hover {
            color: #764ba2;
        }
        
        .resend-btn:disabled {
            color: #999;
            cursor: not-allowed;
            text-decoration: none;
        }
        
        .countdown {
            color: #666;
            font-size: 14px;
            margin-top: 10px;
        }
        
        .error-message {
            color: #e74c3c;
            text-align: center;
            font-size: 14px;
            margin-top: 10px;
            display: none;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-icon">
            <i class="fas fa-mobile-alt"></i>
        </div>
        
        <h2>Verify Your Phone Number</h2>
        <p class="subtitle" id="subtitleText">
            <?php if ($codeSent): ?>
                We've sent a verification code to
            <?php else: ?>
                Click the button below to receive a verification code via SMS
            <?php endif; ?>
        </p>
        
        <div class="phone-display">
            <i class="fas fa-phone"></i> <?php echo htmlspecialchars($phone_display); ?>
        </div>
        
        <?php if ($smsError && !$codeSent): ?>
        <div class="alert alert-warning" style="margin: 20px 0; padding: 15px; border-radius: 10px; background: #fff3cd; border: 1px solid #ffc107; color: #856404;">
            <strong><i class="fas fa-exclamation-triangle"></i> Error:</strong><br>
            <?php echo htmlspecialchars($smsError); ?>
        </div>
        <?php endif; ?>
        
        <!-- Send Code Button (shown initially or if code not sent) -->
        <?php if (!$codeSent): ?>
        <div id="sendCodeSection">
            <button class="btn-verify" id="sendCodeBtn" style="margin-bottom: 20px;">
                <i class="fas fa-paper-plane"></i> Send Verification Code
            </button>
            <div class="error-message" id="sendErrorMessage"></div>
        </div>
        <?php endif; ?>
        
        <!-- OTP Verification Form (hidden until code is sent) -->
        <form id="verifyForm" style="display: <?php echo $codeSent ? 'block' : 'none'; ?>;">
            <div class="otp-inputs">
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
                <input type="text" class="otp-input" maxlength="1" pattern="[0-9]" inputmode="numeric" required>
            </div>
            
            <div class="error-message" id="errorMessage"></div>
            
            <button type="submit" class="btn-verify" id="verifyBtn">
                <i class="fas fa-check-circle"></i> Verify Code
            </button>
        </form>
        
        <!-- Resend Section (only shown after code is sent) -->
        <?php if ($codeSent): ?>
        <div class="resend-section" id="resendSection">
            <p class="subtitle">Didn't receive the code?</p>
            <button class="resend-btn" id="resendBtn" disabled>
                Resend Code
            </button>
            <div class="countdown" id="countdown">Resend available in 60 seconds</div>
        </div>
        <?php endif; ?>
    </div>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Show error alert if SMS failed to send
        <?php if ($smsError): ?>
        Swal.fire({
            icon: 'warning',
            title: 'SMS Not Sent',
            html: '<?php echo addslashes($smsError); ?><br><br><small>Please check your .env file and ensure SMS_API_KEY is configured correctly.</small>',
            confirmButtonColor: '#667eea',
            confirmButtonText: 'OK'
        });
        <?php endif; ?>
        
        const otpInputs = document.querySelectorAll('.otp-input');
        const verifyBtn = document.getElementById('verifyBtn');
        const resendBtn = document.getElementById('resendBtn');
        const countdownEl = document.getElementById('countdown');
        const errorMessage = document.getElementById('errorMessage');
        const verifyForm = document.getElementById('verifyForm');
        
        let countdown = 60;
        let countdownInterval;
        
        // Auto-focus and move between inputs
        otpInputs.forEach((input, index) => {
            input.addEventListener('input', (e) => {
                e.target.value = e.target.value.replace(/[^0-9]/g, '');
                
                if (e.target.value && index < otpInputs.length - 1) {
                    otpInputs[index + 1].focus();
                }
                
                hideError();
            });
            
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Backspace' && !e.target.value && index > 0) {
                    otpInputs[index - 1].focus();
                }
            });
            
            input.addEventListener('paste', (e) => {
                e.preventDefault();
                const paste = (e.clipboardData || window.clipboardData).getData('text');
                const digits = paste.replace(/[^0-9]/g, '').slice(0, 6);
                
                digits.split('').forEach((digit, i) => {
                    if (otpInputs[i]) {
                        otpInputs[i].value = digit;
                    }
                });
                
                if (otpInputs[digits.length - 1]) {
                    otpInputs[digits.length - 1].focus();
                }
            });
        });
        
        // Send Code Button (for first time sending)
        const sendCodeBtn = document.getElementById('sendCodeBtn');
        const sendCodeSection = document.getElementById('sendCodeSection');
        const sendErrorMessage = document.getElementById('sendErrorMessage');
        const subtitleText = document.getElementById('subtitleText');
        
        if (sendCodeBtn) {
            sendCodeBtn.addEventListener('click', async () => {
                if (sendCodeBtn.disabled) return;
                
                sendCodeBtn.disabled = true;
                sendCodeBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Sending...';
                hideSendError();
                
                try {
                    const response = await fetch('', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'send_code'
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Show success message
                        Swal.fire({
                            icon: 'success',
                            title: 'Code Sent!',
                            text: 'A verification code has been sent to your phone.',
                            confirmButtonColor: '#667eea',
                            timer: 2000,
                            showConfirmButton: false
                        });
                        
                        // Hide send button section and show verification form
                        sendCodeSection.style.display = 'none';
                        verifyForm.style.display = 'block';
                        subtitleText.textContent = "We've sent a verification code to";
                        
                        // Create and show resend section dynamically
                        let resendSection = document.getElementById('resendSection');
                        if (!resendSection) {
                            // Create resend section if it doesn't exist
                            resendSection = document.createElement('div');
                            resendSection.className = 'resend-section';
                            resendSection.id = 'resendSection';
                            resendSection.innerHTML = `
                                <p class="subtitle">Didn't receive the code?</p>
                                <button class="resend-btn" id="resendBtn" disabled>Resend Code</button>
                                <div class="countdown" id="countdown">Resend available in 60 seconds</div>
                            `;
                            verifyForm.parentNode.insertBefore(resendSection, verifyForm.nextSibling);
                            // The event listener will be attached below
                        }
                        resendSection.style.display = 'block';
                        attachResendListener();
                        startCountdown();
                        
                        // Focus first OTP input
                        otpInputs[0].focus();
                    } else {
                        showSendError(result.message || 'Failed to send verification code');
                        sendCodeBtn.disabled = false;
                        sendCodeBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
                    }
                } catch (error) {
                    console.error('Send code error:', error);
                    showSendError('An error occurred. Please try again.');
                    sendCodeBtn.disabled = false;
                    sendCodeBtn.innerHTML = '<i class="fas fa-paper-plane"></i> Send Verification Code';
                }
            });
        }
        
        function showSendError(message) {
            sendErrorMessage.textContent = message;
            sendErrorMessage.style.display = 'block';
        }
        
        function hideSendError() {
            if (sendErrorMessage) {
                sendErrorMessage.style.display = 'none';
                sendErrorMessage.textContent = '';
            }
        }
        
        // Countdown timer
        function startCountdown() {
            if (!resendBtn || !countdownEl) return;
            
            countdown = 60;
            resendBtn.disabled = true;
            
            if (countdownInterval) {
                clearInterval(countdownInterval);
            }
            
            countdownInterval = setInterval(() => {
                countdown--;
                countdownEl.textContent = `Resend available in ${countdown} seconds`;
                
                if (countdown <= 0) {
                    clearInterval(countdownInterval);
                    resendBtn.disabled = false;
                    countdownEl.textContent = 'You can resend the code now';
                }
            }, 1000);
        }
        
        // Start countdown if code already sent and attach listener
        <?php if ($codeSent): ?>
        if (resendBtn) {
            attachResendListener();
        }
        startCountdown();
        <?php endif; ?>
        
        // Verify code
        verifyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const code = Array.from(otpInputs).map(input => input.value).join('');
            
            if (code.length !== 6) {
                showError('Please enter the complete 6-digit code');
                return;
            }
            
            verifyBtn.disabled = true;
            verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Verifying...';
            hideError();
            
            try {
                const response = await fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'verify_code',
                        code: code
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Determine user type for appropriate redirect message
                    const userType = '<?php echo $user_type ?? "Tourist"; ?>';
                    let redirectMessage = 'Your phone number has been verified successfully.';
                    
                    if (userType === 'Company') {
                        redirectMessage = 'Your phone number has been verified successfully. You will be redirected to the company login page.';
                    } else if (userType === 'HCB') {
                        redirectMessage = 'Your phone number has been verified successfully. You will be redirected to the HCB login page.';
                    } else {
                        redirectMessage = 'Your phone number has been verified successfully. You will be redirected to the login page.';
                    }
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Phone Verified!',
                        text: redirectMessage,
                        confirmButtonColor: '#667eea',
                        confirmButtonText: 'Continue',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        timer: 2000,
                        timerProgressBar: true
                    }).then(() => {
                        // Redirect to complete registration (which will save data and redirect to appropriate login)
                        window.location.href = 'complete-registration.php';
                    });
                } else {
                    showError(result.message || 'Invalid verification code');
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify Code';
                    
                    // Clear inputs on error
                    otpInputs.forEach(input => input.value = '');
                    otpInputs[0].focus();
                }
            } catch (error) {
                console.error('Verification error:', error);
                showError('An error occurred. Please try again.');
                verifyBtn.disabled = false;
                verifyBtn.innerHTML = '<i class="fas fa-check-circle"></i> Verify Code';
            }
        });
        
        // Resend code - prevent double clicking and double sending
        let isResending = false;
        
        function attachResendListener() {
            if (resendBtn && !resendBtn.hasAttribute('data-listener-attached')) {
                resendBtn.setAttribute('data-listener-attached', 'true');
                resendBtn.addEventListener('click', async () => {
                    if (resendBtn.disabled || isResending) return;
                    
                    // Prevent double click/rapid clicking
                    isResending = true;
                    resendBtn.disabled = true;
                    resendBtn.textContent = 'Sending...';
                    
                    try {
                        const response = await fetch('', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/x-www-form-urlencoded',
                            },
                            body: new URLSearchParams({
                                action: 'resend_code'
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Code Sent!',
                                text: 'A new verification code has been sent to your phone.',
                                confirmButtonColor: '#667eea',
                                timer: 2000,
                                showConfirmButton: false
                            });
                            
                            // Clear inputs and reset countdown
                            otpInputs.forEach(input => input.value = '');
                            otpInputs[0].focus();
                            startCountdown();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed to Resend',
                                text: result.message || 'Please try again later.',
                                confirmButtonColor: '#667eea'
                            });
                            resendBtn.disabled = false;
                            resendBtn.textContent = 'Resend Code';
                        }
                    } catch (error) {
                        console.error('Resend error:', error);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error',
                            text: 'An error occurred. Please try again.',
                            confirmButtonColor: '#667eea'
                        });
                        resendBtn.disabled = false;
                        resendBtn.textContent = 'Resend Code';
                    } finally {
                        // Reset resending flag after a delay to prevent rapid clicking
                        setTimeout(() => {
                            isResending = false;
                        }, 2000);
                    }
                });
            }
        }
        
        // Attach listener if resend button already exists
        if (resendBtn) {
            attachResendListener();
        }
        
        function showError(message) {
            errorMessage.textContent = message;
            errorMessage.style.display = 'block';
        }
        
        function hideError() {
            errorMessage.style.display = 'none';
        }
        
        // Focus first input on load only if form is visible
        <?php if ($codeSent): ?>
        otpInputs[0].focus();
        <?php endif; ?>
    </script>
</body>
</html>

