<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

include '../common/connection.php';
include '../common/randomstrings.php';

// Test database connection
if (!$conn) {
    die("Database connection failed: " . mysqli_connect_error());
}

date_default_timezone_set('Asia/Manila');

// Check if form is submitted
if (isset($_POST['btnRegister']) || (isset($_POST['username']) && isset($_POST['password']))) {
    
    // Sanitize input data
    $company_name = mysqli_real_escape_string($conn, trim($_POST['company_name']));
    $company_description = mysqli_real_escape_string($conn, trim($_POST['company_description']));
    // Use contact person's contact number as the only contact number
    $contact_no = mysqli_real_escape_string($conn, trim($_POST['cp_contact_no']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    
    // Address components from Philippine Address Selector
    $address_line = mysqli_real_escape_string($conn, trim($_POST['address_line'] ?? '')); // Street address
    $region_code = mysqli_real_escape_string($conn, trim($_POST['region_code'] ?? ''));
    $province_code = mysqli_real_escape_string($conn, trim($_POST['province_code'] ?? ''));
    $citymun_code = mysqli_real_escape_string($conn, trim($_POST['citymun_code'] ?? ''));
    $brgy_code = mysqli_real_escape_string($conn, trim($_POST['brgy_code'] ?? ''));
    
    $has_prayer_faci = isset($_POST['has_prayer_faci']) ? 1 : 0;
    $usertype_id = mysqli_real_escape_string($conn, $_POST['usertype_id']);
    
    // Contact Person Information
    $cp_firstname = mysqli_real_escape_string($conn, trim($_POST['cp_firstname']));
    $cp_middlename = mysqli_real_escape_string($conn, trim($_POST['cp_middlename']));
    $cp_lastname = mysqli_real_escape_string($conn, trim($_POST['cp_lastname']));
    $cp_gender = mysqli_real_escape_string($conn, $_POST['cp_gender']);
    $cp_contact_no = mysqli_real_escape_string($conn, trim($_POST['cp_contact_no']));
    $cp_email = mysqli_real_escape_string($conn, trim($_POST['cp_email']));
    
    // Account Information
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $rePassword = mysqli_real_escape_string($conn, $_POST['rePassword']);

    // Basic validation
    $errors = [];
    
    if (empty($company_name) || empty($email) || 
        empty($cp_firstname) || empty($cp_lastname) || empty($cp_contact_no) || 
        empty($username) || empty($password)) {
        $errors[] = "All required fields must be filled.";
    }
    
    // Validate address components
    if (empty($region_code) || empty($province_code) || empty($citymun_code) || empty($brgy_code)) {
        $errors[] = "Please complete the address by selecting Region, Province, City/Municipality, and Barangay.";
    }
    
    if ($password !== $rePassword) {
        $errors[] = "Passwords do not match.";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid company email address.";
    }
    
    if (!empty($cp_email) && !filter_var($cp_email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid contact person email address.";
    }
    
    // Validate phone number format (should be 11 digits starting with 09)
    if (!empty($cp_contact_no) && !preg_match('/^09[0-9]{9}$/', $cp_contact_no)) {
        $errors[] = "Please enter a valid 11-digit phone number starting with 09.";
    }
    
    // Check if username already exists
    $check_username = mysqli_query($conn, "SELECT username FROM tbl_useraccount WHERE username = '$username'");
    if (mysqli_num_rows($check_username) > 0) {
        $errors[] = "Username already exists. Please choose another.";
    }
    
    // Check if company email already exists in multiple tables
    $check_company_email = mysqli_query($conn, "SELECT email FROM tbl_company WHERE email = '$email'");
    if (mysqli_num_rows($check_company_email) > 0) {
        $errors[] = "Company email already registered. Please use another email.";
    }
    
    $check_tourist_email = mysqli_query($conn, "SELECT email FROM tbl_tourist WHERE email = '$email'");
    if (mysqli_num_rows($check_tourist_email) > 0) {
        $errors[] = "This email is already registered. Please use another email.";
    }
    
    $check_admin_email = mysqli_query($conn, "SELECT email FROM tbl_admin WHERE email = '$email'");
    if (mysqli_num_rows($check_admin_email) > 0) {
        $errors[] = "This email is already registered. Please use another email.";
    }
    
    $check_company_user_email = mysqli_query($conn, "SELECT email FROM tbl_company_user WHERE email = '$email' AND email IS NOT NULL AND email != ''");
    if (mysqli_num_rows($check_company_user_email) > 0) {
        $errors[] = "This email is already registered. Please use another email.";
    }
    
    // Check if contact person email already exists (if provided)
    if (!empty($cp_email)) {
        $check_cp_email_company = mysqli_query($conn, "SELECT email FROM tbl_company WHERE email = '$cp_email'");
        if (mysqli_num_rows($check_cp_email_company) > 0) {
            $errors[] = "Contact person email is already registered. Please use another email.";
        }
        
        $check_cp_email_tourist = mysqli_query($conn, "SELECT email FROM tbl_tourist WHERE email = '$cp_email'");
        if (mysqli_num_rows($check_cp_email_tourist) > 0) {
            $errors[] = "Contact person email is already registered. Please use another email.";
        }
        
        $check_cp_email_admin = mysqli_query($conn, "SELECT email FROM tbl_admin WHERE email = '$cp_email'");
        if (mysqli_num_rows($check_cp_email_admin) > 0) {
            $errors[] = "Contact person email is already registered. Please use another email.";
        }
        
        $check_cp_email_company_user = mysqli_query($conn, "SELECT email FROM tbl_company_user WHERE email = '$cp_email' AND email IS NOT NULL AND email != ''");
        if (mysqli_num_rows($check_cp_email_company_user) > 0) {
            $errors[] = "Contact person email is already registered. Please use another email.";
        }
    }
    
    // Check if phone number already exists in multiple tables
    $check_phone_company = mysqli_query($conn, "SELECT contant_no FROM tbl_company WHERE contant_no = '$cp_contact_no'");
    if (mysqli_num_rows($check_phone_company) > 0) {
        $errors[] = "This phone number is already registered. Please use another phone number.";
    }
    
    $check_phone_company_user = mysqli_query($conn, "SELECT contact_no FROM tbl_company_user WHERE contact_no = '$cp_contact_no'");
    if (mysqli_num_rows($check_phone_company_user) > 0) {
        $errors[] = "This phone number is already registered. Please use another phone number.";
    }
    
    $check_phone_company_person = mysqli_query($conn, "SELECT contact_no FROM tbl_company_person WHERE contact_no = '$cp_contact_no'");
    if (mysqli_num_rows($check_phone_company_person) > 0) {
        $errors[] = "This phone number is already registered. Please use another phone number.";
    }
    
    $check_phone_tourist = mysqli_query($conn, "SELECT contact_no FROM tbl_tourist WHERE contact_no = '$cp_contact_no'");
    if (mysqli_num_rows($check_phone_tourist) > 0) {
        $errors[] = "This phone number is already registered. Please use another phone number.";
    }
    
    $check_phone_admin = mysqli_query($conn, "SELECT contact_no FROM tbl_admin WHERE contact_no = '$cp_contact_no'");
    if (mysqli_num_rows($check_phone_admin) > 0) {
        $errors[] = "This phone number is already registered. Please use another phone number.";
    }

    if (empty($errors)) {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Generate IDs
        $currentDateTime = date('Y-m-d H:i:s');
        $company_id = "COMP" . generate_string($specialcasesCHAR, 15);
        $useraccount_id = "UA" . generate_string($specialcasesCHAR, 15);
        $address_id = "ADDR" . generate_string($specialcasesCHAR, 15);
        $company_person_id = "CP" . generate_string($specialcasesCHAR, 15);
        $company_user_id = "CU" . generate_string($specialcasesCHAR, 15);

        // Store registration data in session for verification
        $_SESSION['pending_registration'] = [
            'user_type' => 'Company',
            'company_id' => $company_id,
            'useraccount_id' => $useraccount_id,
            'address_id' => $address_id,
            'company_person_id' => $company_person_id,
            'company_user_id' => $company_user_id,
            'company_name' => $company_name,
            'company_description' => $company_description,
            'contact_no' => $contact_no, // Same as cp_contact_no now
            'email' => $email,
            'address_line' => $address_line,
            'region_code' => $region_code,
            'province_code' => $province_code,
            'citymun_code' => $citymun_code,
            'brgy_code' => $brgy_code,
            'usertype_id' => $usertype_id,
            'has_prayer_faci' => $has_prayer_faci,
            'cp_firstname' => $cp_firstname,
            'cp_middlename' => $cp_middlename,
            'cp_lastname' => $cp_lastname,
            'cp_gender' => $cp_gender,
            'cp_contact_no' => $cp_contact_no,
            'cp_email' => $cp_email,
            'username' => $username,
            'password' => $password
        ];
        
        // Redirect to phone verification
        header('Location: ../common/verify-phone.php');
        exit;
        
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Company Registration | HalalGuide</title>

  <!-- Google Fonts -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      background: linear-gradient(135deg, #9333EA 0%, #7C3AED 50%, #6D28D9 100%);
      padding: 15px;
      margin: 0;
      position: relative;
      overflow-x: hidden;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: center;
    }

    body::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background: radial-gradient(circle, rgba(255,255,255,0.1) 1px, transparent 1px);
      background-size: 50px 50px;
      animation: backgroundMove 20s linear infinite;
      pointer-events: none;
    }

    @keyframes backgroundMove {
      0% { transform: translate(0, 0); }
      100% { transform: translate(50px, 50px); }
    }

    /* Floating particles */
    .particle {
      position: absolute;
      background: radial-gradient(circle, rgba(255, 255, 255, 0.4) 0%, rgba(255, 255, 255, 0) 70%);
      border-radius: 50%;
      pointer-events: none;
      animation: float 15s infinite;
      filter: blur(1px);
    }
    
    .particle::before {
      content: '';
      position: absolute;
      width: 100%;
      height: 100%;
      background: radial-gradient(circle, rgba(147, 51, 234, 0.2) 0%, transparent 70%);
      border-radius: 50%;
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { transform: scale(1); opacity: 0.6; }
      50% { transform: scale(1.2); opacity: 1; }
    }

    @keyframes float {
      0%, 100% {
        transform: translateY(0) translateX(0) scale(1);
        opacity: 0;
      }
      50% {
        opacity: 0.6;
      }
      100% {
        transform: translateY(-100vh) translateX(100px) scale(0);
      }
    }

    .register-container {
      position: relative;
      display: flex;
      flex-direction: column;
      gap: 0;
      width: 100%;
      max-width: 900px;
      z-index: 1;
      margin: 0 auto;
      animation: slideUp 0.6s ease-out;
    }

    .register-card {
      margin-bottom: 0;
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

    .register-card {
      background: rgba(255, 255, 255, 0.98);
      backdrop-filter: blur(20px);
      border-radius: 24px;
      padding: 40px 45px 35px 45px;
      box-shadow: 0 25px 80px rgba(147, 51, 234, 0.25), 
                  0 0 0 1px rgba(255, 255, 255, 0.3) inset,
                  0 0 60px rgba(147, 51, 234, 0.1);
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(255, 255, 255, 0.2);
      margin-bottom: 0;
    }

    .logo-section {
      text-align: center;
      margin-bottom: 20px;
    }

    .logo-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #9333EA 0%, #7C3AED 50%, #6D28D9 100%);
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 15px;
      box-shadow: 0 15px 40px rgba(147, 51, 234, 0.5),
                  0 0 0 4px rgba(255, 255, 255, 0.1) inset;
      animation: bounce 2s infinite, glow 3s ease-in-out infinite;
      position: relative;
    }

    @keyframes glow {
      0%, 100% {
        box-shadow: 0 15px 40px rgba(147, 51, 234, 0.5),
                    0 0 0 4px rgba(255, 255, 255, 0.1) inset;
      }
      50% {
        box-shadow: 0 15px 50px rgba(147, 51, 234, 0.7),
                    0 0 0 6px rgba(255, 255, 255, 0.15) inset;
      }
    }

    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    .logo-icon i {
      font-size: 38px;
      color: white;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
    }

    .logo-text {
      font-size: 28px;
      font-weight: 700;
      background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 3px;
    }

    .logo-subtitle {
      font-size: 13px;
      color: #666;
      font-weight: 400;
    }

    .welcome-text {
      text-align: center;
      margin-bottom: 20px;
    }

    .welcome-text h3 {
      font-size: 24px;
      font-weight: 700;
      color: #1f2937;
      margin-bottom: 6px;
      letter-spacing: -0.5px;
    }

    .welcome-text p {
      font-size: 15px;
      color: #6b7280;
      font-weight: 400;
    }

    .form-section-title {
      display: flex;
      align-items: center;
      gap: 12px;
      margin: 28px 0 16px 0;
      padding: 12px 0 10px 0;
      border-bottom: 3px solid #f3f4f6;
      background: linear-gradient(to right, #9333EA, transparent);
      background-size: 4px 100%;
      background-repeat: no-repeat;
      background-position: left center;
      padding-left: 16px;
      color: #9333EA;
      font-weight: 700;
      font-size: 17px;
      letter-spacing: -0.3px;
      transition: all 0.3s ease;
    }

    .form-section-title:first-of-type {
      margin-top: 0;
    }

    .form-section-title i {
      font-size: 20px;
      background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      filter: drop-shadow(0 2px 4px rgba(147, 51, 234, 0.2));
    }

    .form-group {
      margin-bottom: 16px;
    }

    .form-label {
      display: block;
      margin-bottom: 8px;
      color: #374151;
      font-weight: 600;
      font-size: 14px;
      letter-spacing: -0.2px;
    }

    .required {
      color: #e74c3c;
      margin-left: 3px;
    }

    .form-control {
      width: 100%;
      padding: 14px 18px;
      border: 2px solid #e8e8e8;
      border-radius: 12px;
      font-size: 15px;
      font-family: 'Poppins', sans-serif;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      background: #fafafa;
      box-shadow: 0 2px 4px rgba(0, 0, 0, 0.02);
    }

    .form-control:hover {
      border-color: #d1d5db;
      background: #fff;
    }

    .form-control:focus {
      outline: none;
      border-color: #9333EA;
      background: #fff;
      box-shadow: 0 0 0 4px rgba(147, 51, 234, 0.12),
                  0 4px 12px rgba(147, 51, 234, 0.08);
      transform: translateY(-1px);
    }

    textarea.form-control {
      resize: vertical;
      min-height: 120px;
      font-family: 'Poppins', sans-serif;
      line-height: 1.6;
    }

    .row {
      display: flex;
      flex-wrap: wrap;
      margin: 0 -10px;
    }

    .col-md-6 {
      flex: 0 0 50%;
      max-width: 50%;
      padding: 0 10px;
    }

    .col-md-4 {
      flex: 0 0 33.333333%;
      max-width: 33.333333%;
      padding: 0 10px;
    }

    .checkbox-group {
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .checkbox-group input[type="checkbox"] {
      width: 20px;
      height: 20px;
      cursor: pointer;
      accent-color: #9333EA;
    }

    .checkbox-group label {
      margin: 0;
      cursor: pointer;
      font-weight: 500;
      color: #333;
    }

    .btn-register {
      width: 100%;
      padding: 16px;
      background: linear-gradient(135deg, #9333EA 0%, #7C3AED 50%, #6D28D9 100%);
      color: white;
      border: none;
      border-radius: 14px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
      font-family: 'Poppins', sans-serif;
      margin-top: 24px;
      position: relative;
      overflow: hidden;
      box-shadow: 0 8px 24px rgba(147, 51, 234, 0.35),
                  0 0 0 1px rgba(255, 255, 255, 0.1) inset;
      letter-spacing: 0.3px;
    }

    .btn-register::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.6s ease;
    }

    .btn-register:hover::before {
      left: 100%;
    }

    /* Address Selector Styling */
    #region, #province, #city, #barangay {
      transition: all 0.3s ease;
    }
    
    #region:disabled, #province:disabled, #city:disabled, #barangay:disabled {
      background-color: #f3f4f6;
      cursor: not-allowed;
      opacity: 0.6;
    }

    .btn-register:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(147, 51, 234, 0.5),
                  0 0 0 1px rgba(255, 255, 255, 0.15) inset;
    }

    .btn-register:active {
      transform: translateY(0);
    }

    .btn-register:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .divider {
      text-align: center;
      margin: 15px 0 10px 0;
      position: relative;
    }

    .divider::before {
      content: '';
      position: absolute;
      left: 0;
      top: 50%;
      width: 100%;
      height: 1px;
      background: #e0e0e0;
    }

    .divider span {
      background: rgba(255, 255, 255, 0.95);
      padding: 0 15px;
      position: relative;
      color: #999;
      font-size: 14px;
    }

    .links {
      text-align: center;
      margin-top: 5px;
      margin-bottom: 0;
    }

    .link-item {
      display: block;
      margin-bottom: 0;
      font-size: 14px;
      color: #666;
      padding-bottom: 0;
    }

    .link-item a {
      color: #9333EA;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .link-item a:hover {
      color: #7C3AED;
    }

    .back-home {
      text-align: center;
      margin-top: 20px;
      margin-bottom: 0;
      padding-top: 0;
    }

    .back-home a {
      color: white;
      text-decoration: none;
      font-size: 14px;
      font-weight: 500;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 20px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 25px;
      transition: all 0.3s ease;
    }

    .back-home a:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateX(-5px);
    }

    /* Back to Tourist Login Button */
    .back-to-tourist-login {
      position: fixed;
      top: 20px;
      left: 20px;
      z-index: 1000;
    }

    .back-to-tourist-login a {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 12px 20px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      color: #9333EA;
      text-decoration: none;
      font-size: 14px;
      font-weight: 600;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(147, 51, 234, 0.25),
                  0 0 0 1px rgba(255, 255, 255, 0.2) inset;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(147, 51, 234, 0.1);
    }

    .back-to-tourist-login a:hover {
      background: rgba(255, 255, 255, 1);
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(147, 51, 234, 0.35),
                  0 0 0 1px rgba(255, 255, 255, 0.3) inset;
      color: #7C3AED;
    }

    .back-to-tourist-login a i {
      font-size: 16px;
      transition: transform 0.3s ease;
    }

    .back-to-tourist-login a:hover i {
      transform: translateX(-3px);
    }

    @media (max-width: 768px) {
      body {
        padding: 15px 10px;
      }

      .register-card {
        padding: 30px 20px 25px 20px;
      }

      .col-md-4, .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
      }

      .back-to-tourist-login {
        top: 15px;
        left: 15px;
      }

      .back-to-tourist-login a {
        padding: 10px 16px;
        font-size: 13px;
      }

      .back-to-tourist-login a span {
        display: none;
      }
    }
  </style>
</head>
<body>
  <!-- Back to Tourist Login Button -->
  <div class="back-to-tourist-login">
    <a href="../login.php">
      <i class="fas fa-arrow-left"></i>
      <span>Tourist Login</span>
    </a>
  </div>

  <!-- Floating Particles -->
  <div class="particle" style="width: 80px; height: 80px; left: 5%; top: 10%; animation-delay: 0s;"></div>
  <div class="particle" style="width: 60px; height: 60px; left: 25%; top: 20%; animation-delay: 2s;"></div>
  <div class="particle" style="width: 100px; height: 100px; left: 45%; top: 15%; animation-delay: 4s;"></div>
  <div class="particle" style="width: 70px; height: 70px; left: 65%; top: 25%; animation-delay: 6s;"></div>
  <div class="particle" style="width: 90px; height: 90px; left: 85%; top: 12%; animation-delay: 8s;"></div>
  
  <div class="register-container">
    <div class="register-card">
      <!-- Logo Section -->
      <div class="logo-section">
        <div class="logo-icon">
          <i class="fas fa-building"></i>
        </div>
        <div class="logo-text">HalalGuide</div>
        <div class="logo-subtitle">Company Registration Portal</div>
      </div>

      <!-- Welcome Text -->
      <div class="welcome-text">
        <h3>Register Your Company</h3>
        <p>Join HalalGuide as a halal-certified establishment</p>
      </div>

      <!-- Registration Form -->
      <form method="post" id="registerForm">
        
        <!-- Company Information -->
        <div class="form-section-title">
          <i class="fas fa-building"></i>
          <span>Company Information</span>
        </div>

        <div class="form-group">
          <label class="form-label">Company Name<span class="required">*</span></label>
          <input type="text" name="company_name" class="form-control" placeholder="Enter company name" required>
        </div>

        <div class="form-group">
          <label class="form-label">Company Description</label>
          <textarea name="company_description" class="form-control" placeholder="Describe your company, services, and specialties..."></textarea>
        </div>


        <div class="form-group">
          <label class="form-label">Company Email<span class="required">*</span></label>
          <input type="email" name="email" class="form-control" placeholder="company@example.com" required>
        </div>

        <!-- Philippine Address Selector -->
        <div class="form-section-title" style="margin-top: 20px;">
          <i class="fas fa-map-marker-alt"></i>
          <span>Company Address<span class="required">*</span></span>
        </div>

        <!-- Street Address -->
        <div class="form-group">
          <label class="form-label">Street Address / Building Name</label>
          <input type="text" name="address_line" id="address-line" class="form-control" placeholder="Building name, Street name, House/Unit number">
        </div>

        <!-- Region -->
        <div class="form-group">
          <label class="form-label">Region<span class="required">*</span></label>
          <select name="region_code" id="region" class="form-control" required>
            <option value="" selected disabled>Select Region</option>
          </select>
          <input type="hidden" name="region_text" id="region-text">
        </div>

        <!-- Province -->
        <div class="form-group">
          <label class="form-label">Province<span class="required">*</span></label>
          <select name="province_code" id="province" class="form-control" required disabled>
            <option value="" selected disabled>Select Province</option>
          </select>
          <input type="hidden" name="province_text" id="province-text">
        </div>

        <!-- City/Municipality -->
        <div class="form-group">
          <label class="form-label">City/Municipality<span class="required">*</span></label>
          <select name="citymun_code" id="city" class="form-control" required disabled>
            <option value="" selected disabled>Select City/Municipality</option>
          </select>
          <input type="hidden" name="city_text" id="city-text">
        </div>

        <!-- Barangay -->
        <div class="form-group">
          <label class="form-label">Barangay<span class="required">*</span></label>
          <select name="brgy_code" id="barangay" class="form-control" required disabled>
            <option value="" selected disabled>Select Barangay</option>
          </select>
          <input type="hidden" name="barangay_text" id="barangay-text">
        </div>

        <div class="form-group">
          <label class="form-label">Company Type<span class="required">*</span></label>
          <select name="usertype_id" class="form-control" required>
            <option value="" selected disabled>Select Company Type</option>
            <option value="3">Halal Establishment (Restaurant, Cafe, Food Business)</option>
            <option value="4">Accommodation (Hotel, Resort, Lodge)</option>
            <option value="5">Tourist Spot (Destination, Attraction)</option>
            <option value="6">Prayer Facility (Mosque, Prayer Room)</option>
          </select>
        </div>

        <div class="checkbox-group">
          <input type="checkbox" id="has_prayer_faci" name="has_prayer_faci" value="1">
          <label for="has_prayer_faci">Our establishment has prayer facilities</label>
        </div>

        <!-- Contact Person Information -->
        <div class="form-section-title">
          <i class="fas fa-user-tie"></i>
          <span>Contact Person Information</span>
        </div>

        <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">First Name<span class="required">*</span></label>
              <input type="text" name="cp_firstname" class="form-control" placeholder="Juan" required>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Middle Name</label>
              <input type="text" name="cp_middlename" class="form-control" placeholder="Dela">
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Last Name<span class="required">*</span></label>
              <input type="text" name="cp_lastname" class="form-control" placeholder="Cruz" required>
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Gender<span class="required">*</span></label>
              <select name="cp_gender" class="form-control" required>
                <option value="" selected disabled>Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Contact Number<span class="required">*</span></label>
              <input type="text" name="cp_contact_no" class="form-control" placeholder="09123456789" required pattern="^09[0-9]{9}$" title="Please enter a valid 11-digit phone number starting with 09">
              <small class="form-text text-muted">This will be used for SMS verification and company contact</small>
            </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="cp_email" class="form-control" placeholder="contact@example.com">
        </div>

        <!-- Account Security -->
        <div class="form-section-title">
          <i class="fas fa-shield-alt"></i>
          <span>Account Security</span>
        </div>

        <div class="form-group">
          <label class="form-label">Username<span class="required">*</span></label>
          <input type="text" name="username" class="form-control" placeholder="Choose a username" required minlength="4">
        </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Password<span class="required">*</span></label>
              <input type="password" name="password" class="form-control" placeholder="Create a password" required minlength="6">
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Confirm Password<span class="required">*</span></label>
              <input type="password" name="rePassword" class="form-control" placeholder="Confirm password" required minlength="6">
            </div>
          </div>
        </div>

        <button type="submit" name="btnRegister" class="btn-register" id="btnRegister">
          <span id="btnText">Register Company</span>
        </button>
      </form>

      <!-- Divider -->
      <div class="divider">
        <span>Already have an account?</span>
      </div>

      <!-- Links -->
      <div class="links">
        <div class="link-item">
          <i class="fas fa-sign-in-alt"></i> <a href="../company/login.php">Login to Your Account</a>
        </div>
      </div>
    </div>

    <!-- Back to Home -->
    <div class="back-home">
      <a href="../home.php">
        <i class="fas fa-arrow-left"></i>
        <span>Back to Homepage</span>
      </a>
    </div>
  </div>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<!-- Philippine Address Selector -->
<script src="../assets2/js/ph-address-selector.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  <?php if (isset($success) && $success): ?>
    Swal.fire({
      icon: 'success',
      title: 'Registration Successful!',
      text: 'Your company has been registered successfully. Please wait while we redirect you...',
      showConfirmButton: false,
      timer: 3000
    }).then(() => {
      window.location.href = '../company/login.php';
    });
  <?php endif; ?>

  <?php if (isset($error)): ?>
    Swal.fire({
      icon: 'error',
      title: 'Registration Failed',
      html: '<?php echo addslashes($error); ?>',
      confirmButtonColor: '#9333EA'
    });
  <?php endif; ?>

  // Form submission loading
  document.getElementById('registerForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnRegister');
    const btnText = document.getElementById('btnText');
    
    btn.disabled = true;
    btnText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Registering...';
  });

  // Password match validation
  document.querySelector('input[name="rePassword"]').addEventListener('input', function() {
    const password = document.querySelector('input[name="password"]').value;
    const rePassword = this.value;
    
    if (rePassword && password !== rePassword) {
      this.setCustomValidity('Passwords do not match');
    } else {
      this.setCustomValidity('');
    }
  });
  
  // Address validation before form submission
  document.getElementById('registerForm').addEventListener('submit', function(e) {
    const region = document.getElementById('region').value;
    const province = document.getElementById('province').value;
    const city = document.getElementById('city').value;
    const barangay = document.getElementById('barangay').value;
    
    if (!region || !province || !city || !barangay) {
      e.preventDefault();
      Swal.fire({
        icon: 'warning',
        title: 'Incomplete Address',
        text: 'Please complete the address by selecting Region, Province, City/Municipality, and Barangay.',
        confirmButtonColor: '#9333EA'
      });
      return false;
    }
  });
</script>
</body>
</html>

