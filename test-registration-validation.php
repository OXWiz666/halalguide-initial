<?php
/**
 * Registration Validation Test Script
 * Tests registration validation for Tourist, Company, and HCB
 */

include 'common/connection.php';
include 'common/randomstrings.php';

// Test data
$test_results = [];

// Helper function to test validation
function testRegistrationValidation($type, $test_data, $conn) {
    $errors = [];
    
    if ($type === 'Tourist') {
        $firstname = mysqli_real_escape_string($conn, trim($test_data['firstname'] ?? ''));
        $lastname = mysqli_real_escape_string($conn, trim($test_data['lastname'] ?? ''));
        $gender = mysqli_real_escape_string($conn, $test_data['gender'] ?? '');
        $contact_no = mysqli_real_escape_string($conn, trim($test_data['contact_no'] ?? ''));
        $email = mysqli_real_escape_string($conn, trim($test_data['email'] ?? ''));
        $username = mysqli_real_escape_string($conn, trim($test_data['username'] ?? ''));
        $password = mysqli_real_escape_string($conn, $test_data['password'] ?? '');
        $rePassword = mysqli_real_escape_string($conn, $test_data['rePassword'] ?? '');
        
        // Validation
        if (empty($firstname) || empty($lastname) || empty($gender) || empty($contact_no) || empty($email) || empty($username) || empty($password)) {
            $errors[] = "All required fields must be filled.";
        }
        
        if ($password !== $rePassword) {
            $errors[] = "Passwords do not match.";
        }
        
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (!empty($contact_no) && !preg_match('/^09[0-9]{9}$/', $contact_no)) {
            $errors[] = "Please enter a valid 11-digit phone number starting with 09.";
        }
        
        // Check duplicates
        $check_username = mysqli_query($conn, "SELECT username FROM tbl_useraccount WHERE username = '$username'");
        if (mysqli_num_rows($check_username) > 0) {
            $errors[] = "Username already exists. Please choose another.";
        }
        
        $check_tourist_email = mysqli_query($conn, "SELECT email FROM tbl_tourist WHERE email = '$email'");
        if (mysqli_num_rows($check_tourist_email) > 0) {
            $errors[] = "Email already registered.";
        }
        
        $check_phone_tourist = mysqli_query($conn, "SELECT contact_no FROM tbl_tourist WHERE contact_no = '$contact_no'");
        if (mysqli_num_rows($check_phone_tourist) > 0) {
            $errors[] = "Phone number already registered.";
        }
        
    } elseif ($type === 'Company') {
        $company_name = mysqli_real_escape_string($conn, trim($test_data['company_name'] ?? ''));
        $email = mysqli_real_escape_string($conn, trim($test_data['email'] ?? ''));
        $cp_firstname = mysqli_real_escape_string($conn, trim($test_data['cp_firstname'] ?? ''));
        $cp_lastname = mysqli_real_escape_string($conn, trim($test_data['cp_lastname'] ?? ''));
        $cp_contact_no = mysqli_real_escape_string($conn, trim($test_data['cp_contact_no'] ?? ''));
        $username = mysqli_real_escape_string($conn, trim($test_data['username'] ?? ''));
        $password = mysqli_real_escape_string($conn, $test_data['password'] ?? '');
        $rePassword = mysqli_real_escape_string($conn, $test_data['rePassword'] ?? '');
        $region_code = $test_data['region_code'] ?? '';
        $province_code = $test_data['province_code'] ?? '';
        $citymun_code = $test_data['citymun_code'] ?? '';
        $brgy_code = $test_data['brgy_code'] ?? '';
        
        if (empty($company_name) || empty($email) || 
            empty($cp_firstname) || empty($cp_lastname) || empty($cp_contact_no) || 
            empty($username) || empty($password)) {
            $errors[] = "All required fields must be filled.";
        }
        
        if (empty($region_code) || empty($province_code) || empty($citymun_code) || empty($brgy_code)) {
            $errors[] = "Please complete the address.";
        }
        
        if ($password !== $rePassword) {
            $errors[] = "Passwords do not match.";
        }
        
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (!empty($cp_contact_no) && !preg_match('/^09[0-9]{9}$/', $cp_contact_no)) {
            $errors[] = "Please enter a valid 11-digit phone number starting with 09.";
        }
        
        $check_username = mysqli_query($conn, "SELECT username FROM tbl_useraccount WHERE username = '$username'");
        if (mysqli_num_rows($check_username) > 0) {
            $errors[] = "Username already exists.";
        }
        
        $check_company_email = mysqli_query($conn, "SELECT email FROM tbl_company WHERE email = '$email'");
        if (mysqli_num_rows($check_company_email) > 0) {
            $errors[] = "Email already registered.";
        }
        
        $check_phone_company = mysqli_query($conn, "SELECT contant_no FROM tbl_company WHERE contant_no = '$cp_contact_no'");
        if (mysqli_num_rows($check_phone_company) > 0) {
            $errors[] = "Phone number already registered.";
        }
        
    } elseif ($type === 'HCB') {
        $organization_name = mysqli_real_escape_string($conn, trim($test_data['organization_name'] ?? ''));
        $firstname = mysqli_real_escape_string($conn, trim($test_data['firstname'] ?? ''));
        $lastname = mysqli_real_escape_string($conn, trim($test_data['lastname'] ?? ''));
        $email = mysqli_real_escape_string($conn, trim($test_data['email'] ?? ''));
        $contact_no = mysqli_real_escape_string($conn, trim($test_data['contact_no'] ?? ''));
        $username = mysqli_real_escape_string($conn, trim($test_data['username'] ?? ''));
        $password = mysqli_real_escape_string($conn, $test_data['password'] ?? '');
        $rePassword = mysqli_real_escape_string($conn, $test_data['rePassword'] ?? '');
        
        if (empty($organization_name) || empty($firstname) || empty($lastname) || empty($email) || empty($contact_no) || empty($username) || empty($password)) {
            $errors[] = "All required fields must be filled.";
        }
        
        if ($password !== $rePassword) {
            $errors[] = "Passwords do not match.";
        }
        
        if (strlen($password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
        
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (!empty($contact_no) && !preg_match('/^09[0-9]{9}$/', $contact_no)) {
            $errors[] = "Please enter a valid 11-digit phone number starting with 09.";
        }
        
        $check_username = mysqli_query($conn, "SELECT username FROM tbl_useraccount WHERE username = '$username'");
        if (mysqli_num_rows($check_username) > 0) {
            $errors[] = "Username already exists.";
        }
        
        $check_admin_email = mysqli_query($conn, "SELECT email FROM tbl_admin WHERE email = '$email'");
        if (mysqli_num_rows($check_admin_email) > 0) {
            $errors[] = "Email already registered.";
        }
        
        $check_phone_admin = mysqli_query($conn, "SELECT contact_no FROM tbl_admin WHERE contact_no = '$contact_no'");
        if (mysqli_num_rows($check_phone_admin) > 0) {
            $errors[] = "Phone number already registered.";
        }
    }
    
    return $errors;
}

echo "========================================\n";
echo "REGISTRATION VALIDATION TEST\n";
echo "========================================\n\n";

// Test 1: Tourist Registration with valid data
echo "TEST 1: Tourist Registration - Valid Data\n";
echo "------------------------------------------\n";
$tourist_data = [
    'firstname' => 'Test',
    'lastname' => 'Tourist',
    'gender' => 'Male',
    'contact_no' => '09123456789',
    'email' => 'testtourist' . time() . '@example.com',
    'username' => 'testtourist' . time(),
    'password' => 'password123',
    'rePassword' => 'password123'
];
$tourist_errors = testRegistrationValidation('Tourist', $tourist_data, $conn);
if (empty($tourist_errors)) {
    echo "✅ PASS: Tourist registration validation passed\n";
} else {
    echo "❌ FAIL: Tourist registration has errors:\n";
    foreach ($tourist_errors as $error) {
        echo "   - $error\n";
    }
}
echo "\n";

// Test 2: Company Registration with valid data
echo "TEST 2: Company Registration - Valid Data\n";
echo "------------------------------------------\n";
$company_data = [
    'company_name' => 'Test Company ' . time(),
    'email' => 'testcompany' . time() . '@example.com',
    'cp_firstname' => 'John',
    'cp_lastname' => 'Doe',
    'cp_contact_no' => '09987654321',
    'username' => 'testcompany' . time(),
    'password' => 'password123',
    'rePassword' => 'password123',
    'region_code' => '01',
    'province_code' => '0128',
    'citymun_code' => '012801',
    'brgy_code' => '012801001'
];
$company_errors = testRegistrationValidation('Company', $company_data, $conn);
if (empty($company_errors)) {
    echo "✅ PASS: Company registration validation passed\n";
} else {
    echo "❌ FAIL: Company registration has errors:\n";
    foreach ($company_errors as $error) {
        echo "   - $error\n";
    }
}
echo "\n";

// Test 3: HCB Registration with valid data
echo "TEST 3: HCB Registration - Valid Data\n";
echo "------------------------------------------\n";
$hcb_data = [
    'organization_name' => 'Test HCB ' . time(),
    'firstname' => 'Admin',
    'lastname' => 'User',
    'email' => 'testhcb' . time() . '@example.com',
    'contact_no' => '09111222333',
    'username' => 'testhcb' . time(),
    'password' => 'password123',
    'rePassword' => 'password123'
];
$hcb_errors = testRegistrationValidation('HCB', $hcb_data, $conn);
if (empty($hcb_errors)) {
    echo "✅ PASS: HCB registration validation passed\n";
} else {
    echo "❌ FAIL: HCB registration has errors:\n";
    foreach ($hcb_errors as $error) {
        echo "   - $error\n";
    }
}
echo "\n";

// Test 4: Invalid phone number format
echo "TEST 4: Invalid Phone Number Format\n";
echo "------------------------------------------\n";
$invalid_phone_data = [
    'firstname' => 'Test',
    'lastname' => 'User',
    'gender' => 'Male',
    'contact_no' => '1234567890', // Invalid format
    'email' => 'invalidphone' . time() . '@example.com',
    'username' => 'invalidphone' . time(),
    'password' => 'password123',
    'rePassword' => 'password123'
];
$invalid_phone_errors = testRegistrationValidation('Tourist', $invalid_phone_data, $conn);
if (in_array("Please enter a valid 11-digit phone number starting with 09.", $invalid_phone_errors)) {
    echo "✅ PASS: Invalid phone number correctly rejected\n";
} else {
    echo "❌ FAIL: Phone number validation not working\n";
}
echo "\n";

// Test 5: Invalid email format
echo "TEST 5: Invalid Email Format\n";
echo "------------------------------------------\n";
$invalid_email_data = [
    'firstname' => 'Test',
    'lastname' => 'User',
    'gender' => 'Male',
    'contact_no' => '09123456789',
    'email' => 'invalid-email', // Invalid format
    'username' => 'invalidemail' . time(),
    'password' => 'password123',
    'rePassword' => 'password123'
];
$invalid_email_errors = testRegistrationValidation('Tourist', $invalid_email_data, $conn);
if (in_array("Please enter a valid email address.", $invalid_email_errors)) {
    echo "✅ PASS: Invalid email correctly rejected\n";
} else {
    echo "❌ FAIL: Email validation not working\n";
}
echo "\n";

// Test 6: Password mismatch
echo "TEST 6: Password Mismatch\n";
echo "------------------------------------------\n";
$password_mismatch_data = [
    'firstname' => 'Test',
    'lastname' => 'User',
    'gender' => 'Male',
    'contact_no' => '09123456789',
    'email' => 'passwordtest' . time() . '@example.com',
    'username' => 'passwordtest' . time(),
    'password' => 'password123',
    'rePassword' => 'password456' // Mismatch
];
$password_mismatch_errors = testRegistrationValidation('Tourist', $password_mismatch_data, $conn);
if (in_array("Passwords do not match.", $password_mismatch_errors)) {
    echo "✅ PASS: Password mismatch correctly detected\n";
} else {
    echo "❌ FAIL: Password validation not working\n";
}
echo "\n";

// Test 7: Company missing address
echo "TEST 7: Company Missing Address\n";
echo "------------------------------------------\n";
$missing_address_data = [
    'company_name' => 'Test Company',
    'email' => 'missingaddress' . time() . '@example.com',
    'cp_firstname' => 'John',
    'cp_lastname' => 'Doe',
    'cp_contact_no' => '09123456789',
    'username' => 'missingaddress' . time(),
    'password' => 'password123',
    'rePassword' => 'password123',
    'region_code' => '', // Missing
    'province_code' => '', // Missing
    'citymun_code' => '', // Missing
    'brgy_code' => '' // Missing
];
$missing_address_errors = testRegistrationValidation('Company', $missing_address_data, $conn);
if (in_array("Please complete the address.", $missing_address_errors)) {
    echo "✅ PASS: Missing address correctly detected\n";
} else {
    echo "❌ FAIL: Address validation not working\n";
}
echo "\n";

echo "========================================\n";
echo "VALIDATION TEST COMPLETE\n";
echo "========================================\n";
echo "\n";
echo "Summary:\n";
echo "- Tourist registration: " . (empty($tourist_errors) ? "✅ Valid" : "❌ Invalid") . "\n";
echo "- Company registration: " . (empty($company_errors) ? "✅ Valid" : "❌ Invalid") . "\n";
echo "- HCB registration: " . (empty($hcb_errors) ? "✅ Valid" : "❌ Invalid") . "\n";
echo "\n";
echo "To actually test account creation, visit:\n";
echo "- Tourist: http://localhost/registration.php\n";
echo "- Company: http://localhost/company/company.php\n";
echo "- HCB: http://localhost/hcb/registration.php\n";

