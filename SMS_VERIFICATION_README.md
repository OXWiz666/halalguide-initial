# SMS Verification Setup Guide

This guide will help you implement SMS verification using `sms.iprogtech.com` for the HalalGuide application.

## 📋 Table of Contents

1. [Prerequisites](#prerequisites)
2. [Database Setup](#database-setup)
3. [Environment Configuration](#environment-configuration)
4. [Integration Steps](#integration-steps)
5. [API Usage Examples](#api-usage-examples)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## 🔑 Prerequisites

1. **SMS API Account**: Sign up at [sms.iprogtech.com](https://sms.iprogtech.com) to get your:
   - API Key
   - Sender ID
   - API URL (usually `https://sms.iprogtech.com/api/send`)

2. **Database Access**: Ensure you have database admin access to create tables

3. **PHP cURL Extension**: Verify cURL is enabled in your PHP installation

---

## 🗄️ Database Setup

### Step 1: Create Verification Table

Run the SQL script to create the verification table:

```bash
# Option 1: Via MySQL command line
mysql -u your_username -p your_database < database/create_sms_verification_table.sql

# Option 2: Via phpMyAdmin
# Import the file: database/create_sms_verification_table.sql
```

### Step 2: Verify Table Creation

Check that the table was created successfully:

```sql
SHOW TABLES LIKE 'tbl_sms_verification';
DESCRIBE tbl_sms_verification;
```

---

## ⚙️ Environment Configuration

### Step 1: Create `.env` File

1. Copy the example environment file:

```bash
# If .env.example exists:
cp .env.example .env

# OR copy from env.example.txt:
cp env.example.txt .env
```

2. Open `.env` file and configure your SMS credentials:

```env
# SMS Verification Configuration
SMS_API_URL=https://sms.iprogtech.com/api/send
SMS_API_KEY=your_actual_api_key_here
SMS_SENDER_ID=your_actual_sender_id_here
```

### Step 2: Get Your Credentials

1. **Log in** to [sms.iprogtech.com](https://sms.iprogtech.com)
2. Navigate to **Dashboard** → **API Settings** (or similar)
3. Copy your:
   - **API Key**: Usually a long alphanumeric string
   - **Sender ID**: Your approved SMS sender name
   - **API URL**: May vary, confirm from documentation

### Step 3: Update `.env` File

Replace the placeholder values:

```env
SMS_API_URL=https://sms.iprogtech.com/api/send
SMS_API_KEY=abc123xyz789yourkeyhere
SMS_SENDER_ID=HALALGUIDE
```

### Step 4: Secure Your `.env` File

**IMPORTANT**: Add `.env` to `.gitignore` to prevent committing secrets:

```bash
echo ".env" >> .gitignore
```

---

## 🔌 Integration Steps

### 1. Files Added

The following files have been created:

```
common/
├── SmsService.php          # SMS sending service
└── SmsVerification.php     # Verification code manager

database/
└── create_sms_verification_table.sql

.env.example                # Environment template
SMS_VERIFICATION_README.md  # This file
```

### 2. Include in Your Registration Page

Add SMS verification to `registration.php`:

```php
<?php
// At the top of registration.php, after other includes
include 'common/SmsVerification.php';

// Initialize SMS verification
$smsVerification = new SmsVerification($conn);
?>

<!-- In your registration form, add verification step -->
```

### 3. Frontend Integration

Add verification UI to your registration form (see example in next section).

---

## 💻 API Usage Examples

### Example 1: Send Verification Code During Registration

```php
<?php
include 'common/SmsVerification.php';

$smsVerification = new SmsVerification($conn);

// When user submits phone number
if (isset($_POST['send_verification'])) {
    $phoneNumber = $_POST['contact_no'];
    
    $result = $smsVerification->generateAndSendCode(
        $phoneNumber,
        null,  // useraccount_id (null during registration)
        'registration'
    );
    
    if ($result['success']) {
        // Store verification_id in session for later verification
        $_SESSION['sms_verification_id'] = $result['verification_id'];
        echo "Verification code sent to " . $phoneNumber;
    } else {
        echo "Error: " . $result['message'];
    }
}
?>
```

### Example 2: Verify Code

```php
<?php
// Verify the code user entered
if (isset($_POST['verify_code'])) {
    $verificationId = $_SESSION['sms_verification_id'];
    $code = $_POST['verification_code'];
    $phoneNumber = $_POST['contact_no'];
    
    $result = $smsVerification->verifyCode($verificationId, $code, $phoneNumber);
    
    if ($result['success']) {
        // Phone verified, proceed with registration
        $_SESSION['phone_verified'] = true;
        echo "Phone number verified!";
    } else {
        echo "Error: " . $result['message'];
    }
}
?>
```

### Example 3: Resend Code

```php
<?php
// Resend verification code
if (isset($_POST['resend_code'])) {
    $verificationId = $_SESSION['sms_verification_id'];
    
    $result = $smsVerification->resendCode($verificationId);
    
    if ($result['success']) {
        $_SESSION['sms_verification_id'] = $result['verification_id'];
        echo "New verification code sent!";
    } else {
        echo "Error: " . $result['message'];
    }
}
?>
```

### Example 4: Check if Phone is Verified

```php
<?php
// Before completing registration, check verification
if ($smsVerification->isPhoneVerified($phoneNumber, 'registration')) {
    // Proceed with registration
} else {
    // Require verification first
    echo "Please verify your phone number first";
}
?>
```

---

## 🧪 Testing

### Test 1: Verify Environment Variables

Create a test file `test_sms_config.php`:

```php
<?php
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $vars = parse_ini_file($envFile);
    echo "SMS_API_URL: " . ($vars['SMS_API_URL'] ?? 'NOT SET') . "\n";
    echo "SMS_API_KEY: " . (empty($vars['SMS_API_KEY']) ? 'NOT SET' : 'SET') . "\n";
    echo "SMS_SENDER_ID: " . ($vars['SMS_SENDER_ID'] ?? 'NOT SET') . "\n";
} else {
    echo ".env file not found!\n";
}
?>
```

### Test 2: Test SMS Sending

```php
<?php
include 'common/SmsService.php';

$sms = new SmsService();
$result = $sms->sendSms('09123456789', 'Test message from HalalGuide');

var_dump($result);
?>
```

### Test 3: Full Verification Flow

1. Request verification code
2. Check SMS received
3. Enter code and verify
4. Check database: `SELECT * FROM tbl_sms_verification WHERE phone_number = '09123456789'`

---

## 🐛 Troubleshooting

### Issue: "SMS service not configured"

**Solution**: Check your `.env` file exists and contains valid credentials.

```bash
# Verify .env file
cat .env | grep SMS
```

### Issue: "CURL Error"

**Solution**: 
1. Check PHP cURL extension is enabled:
   ```php
   <?php var_dump(extension_loaded('curl')); ?>
   ```
2. Check internet connectivity
3. Verify API URL is correct

### Issue: "Failed to send SMS"

**Possible Causes**:
1. Invalid API credentials
2. Insufficient SMS credits
3. Invalid phone number format
4. Sender ID not approved

**Solutions**:
1. Verify credentials at sms.iprogtech.com dashboard
2. Check account balance
3. Ensure phone number is in format: `09XXXXXXXXX` (11 digits, no spaces)
4. Contact sms.iprogtech.com support to verify sender ID

### Issue: "Verification code has expired"

**Solution**: Codes expire after 10 minutes. Request a new code.

### Issue: "Maximum verification attempts exceeded"

**Solution**: User has exceeded 5 attempts. They must request a new code.

---

## 🔒 Security Best Practices

1. **Never expose verification codes** in API responses
2. **Set expiration times** (default: 10 minutes)
3. **Limit verification attempts** (default: 5 attempts)
4. **Use HTTPS** for all API communications
5. **Sanitize phone numbers** before database operations
6. **Store only hashed codes** (optional, current implementation stores plain codes - consider hashing for production)

---

## 📞 Support

- **SMS API Support**: Contact support@iprogtech.com or visit sms.iprogtech.com
- **Integration Issues**: Check the troubleshooting section above

---

## 📝 Changelog

### Version 1.0.0
- Initial SMS verification implementation
- Support for registration verification
- Code expiration (10 minutes)
- Attempt limiting (5 attempts)
- Resend functionality

---

## ⚠️ Important Notes

1. **Development Mode**: For testing without sending real SMS, you can:
   - Mock the SmsService class
   - Use test API credentials provided by sms.iprogtech.com
   - Log codes to a file for testing

2. **Production Checklist**:
   - [ ] `.env` file secured (not in git)
   - [ ] Valid API credentials configured
   - [ ] Database table created
   - [ ] Error logging enabled
   - [ ] Rate limiting implemented
   - [ ] User-friendly error messages

3. **Cost Considerations**:
   - Each verification code sends 1 SMS
   - Monitor your SMS credits in sms.iprogtech.com dashboard
   - Set up alerts for low balance

---

## 🚀 Next Steps

After setup:

1. Integrate SMS verification into registration flow
2. Add verification step in UI
3. Test with real phone numbers
4. Monitor SMS delivery rates
5. Implement rate limiting for production

---

**Last Updated**: 2024

**Maintainer**: HalalGuide Development Team
