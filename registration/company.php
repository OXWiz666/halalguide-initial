<?php
include '../common/connection.php';
include '../common/randomstrings.php';

date_default_timezone_set('Asia/Manila');

if (isset($_POST['btnRegister'])) {
    $firstname = $_POST['firstname'];
    $middlename = $_POST['middlename'];
    $lastname = $_POST['lastname'];
    $gender = $_POST['gender'];
    $contact_no = $_POST['contact_no'];
    $email = $_POST['email'];
    $username = $_POST['username'];
    $password = $_POST['password'];

    $usertype = $_POST['usertype'];
    $establishment_name = $_POST['establishment_name'];
    $com_email = $_POST['com_email'];
    $com_contact_no = $_POST['com_contact_no'];
    $com_tel_no = $_POST['tel_no'];
    $regCode = $_POST['regCode'];
    $provCode = $_POST['provCode'];
    $citymunCode = $_POST['citymunCode'];
    $brgyCode = $_POST['brgyCode'];
    $other = $_POST['other'];

    $currentDateTime = date('Y-m-d H:i:s');

    $company_type_id = "";
    $company_user_id = "CU" . generate_string($specialcasesCHAR, 15);
    $company_id = "COM" . generate_string($specialcasesCHAR, 15);
    $address_id = "AD" . generate_string($specialcasesCHAR, 15);
    $useraccount_id = "UA" . generate_string($specialcasesCHAR, 15);

    $result_address = mysqli_query($conn, "INSERT INTO tbl_address (address_id, brgyCode, other, date_added)
    VALUES ('$address_id', '$brgyCode', '$other', '$currentDateTime')") or die("Address Error: " . mysqli_error($conn));

    if ($result_address) {
        if($usertype === "Accommodation"){
            $company_type_id = "ACC" . generate_string($specialcasesCHAR, 15);
            // Add accommodation logic here
        } elseif($usertype === "Establishment"){
            $company_type_id = "EST" . generate_string($specialcasesCHAR, 15);

            $establishment_query = mysqli_query($conn, 
            "INSERT INTO tbl_establishment (establishment_id, company_id, establishment_name, contact_no, address_id, status_id, date_added) 
            VALUES (
                '$company_type_id', '$company_id', '$establishment_name', '$com_contact_no', '$address_id',
                (SELECT status_id FROM tbl_status WHERE status = 'Active'), 
                '$currentDateTime'
            );") or die("Establishment Error: " . mysqli_error($conn));

            if($establishment_query){
                $company_user_query = mysqli_query($conn, "INSERT INTO tbl_company_user (
                    company_user_id, company_id, firstname, middlename, lastname, gender, contact_no, email, usertype_id, date_added
                ) VALUES (
                    '$company_user_id', '$company_id', '$firstname', '$middlename', '$lastname', '$gender', '$contact_no', '$email', 
                    (SELECT usertype_id FROM tbl_usertype WHERE usertype = '$usertype'), '$currentDateTime'
                )") or die("User Error: " . mysqli_error($conn));

                if($company_user_query){
                    $company_useraccount = mysqli_query($conn, "INSERT INTO tbl_useraccount (useraccount_id, username, password, company_user_id, usertype_id, status_id, date_added)
                        VALUES ('$useraccount_id', '$username', '$password', '$company_user_id', (SELECT usertype_id FROM tbl_usertype WHERE usertype = '$usertype'), (SELECT status_id FROM tbl_status WHERE status = 'Active'), '$currentDateTime');") or die(mysqli_connect_error());

                    if ($company_useraccount) {
                        $success = true;
                    } else {
                        mysqli_query($conn, "DELETE FROM tbl_establishment WHERE establishment_id = '$company_type_id'");
                        mysqli_query($conn, "DELETE FROM tbl_company_user WHERE company_user_id = '$company_user_id'");
                        mysqli_query($conn, "DELETE FROM tbl_address WHERE address_id = '$address_id'");
                        $error = "Failed to register. Please try again.";
                    }
                }
            }
        } elseif($usertype === "Tourist Spot"){
            $company_type_id = "TS" . generate_string($specialcasesCHAR, 15);
            // Add tourist spot logic here
        }
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
  <link rel="stylesheet" href="../plugins/fontawesome-free/css/all.min.css">
  <!-- SweetAlert2 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.6.0/css/bootstrap.min.css">
  
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      padding: 40px 20px;
      position: relative;
      overflow-x: hidden;
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

    .register-container {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 800px;
      margin: 0 auto;
      animation: slideUp 0.6s ease-out;
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
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    }

    .logo-section {
      text-align: center;
      margin-bottom: 30px;
    }

    .logo-icon {
      width: 70px;
      height: 70px;
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      border-radius: 18px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 15px;
      box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
      animation: bounce 2s infinite;
    }

    @keyframes bounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-10px); }
    }

    .logo-icon i {
      font-size: 35px;
      color: white;
    }

    .logo-text {
      font-size: 28px;
      font-weight: 700;
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 5px;
    }

    .logo-subtitle {
      font-size: 13px;
      color: #666;
      font-weight: 400;
    }

    .welcome-text {
      text-align: center;
      margin-bottom: 30px;
    }

    .welcome-text h3 {
      font-size: 24px;
      font-weight: 600;
      color: #333;
      margin-bottom: 5px;
    }

    .welcome-text p {
      font-size: 14px;
      color: #666;
    }

    .form-section-title {
      font-size: 16px;
      font-weight: 600;
      color: #2ECC71;
      margin-bottom: 15px;
      padding-bottom: 10px;
      border-bottom: 2px solid #FFE5CC;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .form-section-title i {
      font-size: 18px;
    }

    .form-group {
      margin-bottom: 20px;
    }

    .form-label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      font-weight: 500;
      color: #333;
    }

    .required {
      color: #27AE60;
      margin-left: 3px;
    }

    .input-wrapper {
      position: relative;
    }

    .form-control {
      width: 100%;
      padding: 12px 40px 12px 15px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 14px;
      transition: all 0.3s ease;
      background: white;
      font-family: 'Poppins', sans-serif;
    }

    .form-control:focus {
      outline: none;
      border-color: #2ECC71;
      box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.1);
    }

    .input-icon {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      transition: color 0.3s ease;
    }

    .form-control:focus ~ .input-icon {
      color: #2ECC71;
    }

    .toggle-password {
      cursor: pointer;
    }

    .toggle-password:hover {
      color: #27AE60;
    }

    select.form-control {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23999' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 15px center;
      padding-right: 40px;
    }

    .password-rules {
      display: none;
      margin-top: 10px;
      padding: 12px;
      background: #f8f9fa;
      border-radius: 8px;
      border-left: 3px solid #2ECC71;
    }

    .password-rules.show {
      display: block;
    }

    .password-rules li {
      list-style: none;
      font-size: 13px;
      margin-bottom: 5px;
      transition: all 0.3s ease;
    }

    .password-rules li.valid {
      color: #28a745;
    }

    .password-rules li.invalid {
      color: #dc3545;
    }

    .match-msg {
      display: none;
      margin-top: 8px;
      font-size: 13px;
      font-weight: 500;
    }

    .match-msg.show {
      display: block;
    }

    .match-msg.valid {
      color: #28a745;
    }

    .match-msg.invalid {
      color: #dc3545;
    }

    .btn-register {
      width: 100%;
      padding: 14px;
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      position: relative;
      overflow: hidden;
      font-family: 'Poppins', sans-serif;
      margin-top: 10px;
    }

    .btn-register::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.2);
      transition: left 0.5s ease;
    }

    .btn-register:hover::before {
      left: 100%;
    }

    .btn-register:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
    }

    .btn-register:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .divider {
      text-align: center;
      margin: 25px 0;
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
      margin-top: 20px;
    }

    .link-item {
      display: block;
      margin-bottom: 10px;
      font-size: 14px;
      color: #666;
    }

    .link-item a {
      color: #2ECC71;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .link-item a:hover {
      color: #27AE60;
    }

    .back-home {
      text-align: center;
      margin-top: 20px;
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

    .spinner {
      display: inline-block;
      width: 16px;
      height: 16px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
      margin-right: 8px;
    }

    @keyframes spin {
      to { transform: rotate(360deg); }
    }

    /* Row styling */
    .row {
      display: flex;
      flex-wrap: wrap;
      margin-left: -10px;
      margin-right: -10px;
    }

    .col-md-4, .col-md-6 {
      padding-left: 10px;
      padding-right: 10px;
    }

    @media (min-width: 768px) {
      .col-md-4 {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
      }

      .col-md-6 {
        flex: 0 0 50%;
        max-width: 50%;
      }
    }

    @media (max-width: 768px) {
      body {
        padding: 20px 10px;
      }

      .register-card {
        padding: 30px 20px;
      }

      .logo-text {
        font-size: 24px;
      }

      .welcome-text h3 {
        font-size: 20px;
      }

      .col-md-4, .col-md-6 {
        flex: 0 0 100%;
        max-width: 100%;
      }
    }
  </style>
</head>
<body>

  <div class="register-container">
    <div class="register-card">
      <!-- Logo Section -->
      <div class="logo-section">
        <div class="logo-icon">
          <i class="fas fa-building"></i>
        </div>
        <div class="logo-text">HalalGuide</div>
        <div class="logo-subtitle">Your Trusted Halal Certification Platform</div>
      </div>

      <!-- Welcome Text -->
      <div class="welcome-text">
        <h3>Register Your Company</h3>
        <p>Join HalalGuide and get your business Halal certified</p>
      </div>

      <!-- Registration Form -->
      <form method="post" id="registerForm">
        
        <!-- Personal Information -->
        <div class="form-section-title">
          <i class="fas fa-user"></i>
          <span>Contact Person Information</span>
        </div>

            <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">First Name<span class="required">*</span></label>
              <div class="input-wrapper">
                <input type="text" name="firstname" class="form-control" placeholder="Juan" required>
                <i class="fas fa-user input-icon"></i>
              </div>
            </div>
                </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Middle Name</label>
              <div class="input-wrapper">
                <input type="text" name="middlename" class="form-control" placeholder="Dela">
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Last Name<span class="required">*</span></label>
              <div class="input-wrapper">
                <input type="text" name="lastname" class="form-control" placeholder="Cruz" required>
              </div>
                </div>
              </div>
            </div>

        <div class="form-group">
          <label class="form-label">Gender<span class="required">*</span></label>
          <select name="gender" class="form-control" required>
            <option value="" selected disabled>Select Gender</option>
                <option value="Male">Male</option>
                <option value="Female">Female</option>
              </select>
            </div>

      <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Email Address<span class="required">*</span></label>
              <div class="input-wrapper">
                <input type="email" name="email" class="form-control" placeholder="juan@example.com" required>
                <i class="fas fa-envelope input-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Contact Number<span class="required">*</span></label>
              <div class="input-wrapper">
                <input type="text" name="contact_no" class="form-control" placeholder="09123456789" required pattern="[0-9]{11}" title="Please enter a valid 11-digit phone number">
                <i class="fas fa-phone input-icon"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Company Information -->
        <div class="form-section-title">
          <i class="fas fa-building"></i>
          <span>Company Information</span>
      </div>

        <div class="form-group">
          <label class="form-label">Company Type<span class="required">*</span></label>
          <select name="usertype" class="form-control" required>
            <option value="" selected disabled>Select Company Type</option>
            <option value="Accommodation">Accommodation (e.g. Hotel)</option>
            <option value="Establishment">Establishment (e.g. Restaurant)</option>
            <option value="Tourist Spot">Tourist Spot</option>
        </select>
      </div>

        <div class="form-group">
          <label class="form-label">Company Name<span class="required">*</span></label>
          <div class="input-wrapper">
            <input type="text" name="establishment_name" class="form-control" placeholder="Your Company Name" required>
            <i class="fas fa-building input-icon"></i>
        </div>
      </div>

      <div class="row">
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Company Email</label>
              <div class="input-wrapper">
                <input type="email" name="com_email" class="form-control" placeholder="company@example.com">
                <i class="fas fa-envelope input-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Company Contact<span class="required">*</span></label>
              <div class="input-wrapper">
                <input type="text" name="com_contact_no" class="form-control" placeholder="09123456789" required pattern="[0-9]{11}">
                <i class="fas fa-mobile input-icon"></i>
              </div>
            </div>
          </div>
          <div class="col-md-4">
            <div class="form-group">
              <label class="form-label">Company Telephone</label>
              <div class="input-wrapper">
                <input type="text" name="tel_no" class="form-control" placeholder="(02) 1234-5678">
                <i class="fas fa-phone-alt input-icon"></i>
              </div>
            </div>
          </div>
        </div>

        <!-- Company Address -->
        <div class="form-section-title">
          <i class="fas fa-map-marker-alt"></i>
          <span>Company Address</span>
      </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Region<span class="required">*</span></label>
              <select name="regCode" id="region" class="form-control" required>
                <option value="" selected disabled>Select Region</option>
                            <?php
                                $region_query = mysqli_query($conn, "SELECT * FROM refregion ORDER BY regDesc ASC");
                                while($region_row = mysqli_fetch_assoc($region_query)){
                            ?>
                <option value="<?= $region_row['regCode']; ?>"><?= $region_row['regDesc']; ?></option>
                            <?php } ?>
        </select>
      </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Province<span class="required">*</span></label>
              <select name="provCode" id="province" class="form-control" required>
                <option value="" selected disabled>Select Province</option>
        </select>
            </div>
          </div>
      </div>

        <div class="row">
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">City/Municipality<span class="required">*</span></label>
              <select name="citymunCode" id="citymun" class="form-control" required>
                <option value="" selected disabled>Select City/Municipality</option>
        </select>
      </div>
          </div>
          <div class="col-md-6">
            <div class="form-group">
              <label class="form-label">Barangay<span class="required">*</span></label>
              <select name="brgyCode" id="barangay" class="form-control" required>
                <option value="" selected disabled>Select Barangay</option>
        </select>
      </div>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Street/Block/Lot/Building<span class="required">*</span></label>
          <div class="input-wrapper">
            <input type="text" name="other" class="form-control" placeholder="123 Main Street, Building A" required>
            <i class="fas fa-map-marked-alt input-icon"></i>
        </div>
      </div>

        <!-- Account Security -->
        <div class="form-section-title">
          <i class="fas fa-shield-alt"></i>
          <span>Account Security</span>
            </div>

        <div class="form-group">
          <label class="form-label">Username<span class="required">*</span></label>
          <div class="input-wrapper">
            <input type="text" name="username" id="username" class="form-control" placeholder="Choose a username" required minlength="4">
            <i class="fas fa-at input-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password<span class="required">*</span></label>
          <div class="input-wrapper">
            <input type="password" name="password" id="password" class="form-control" placeholder="Create a strong password" required>
            <i class="fas fa-eye toggle-password input-icon" id="togglePassword"></i>
          </div>
          <ul id="passwordRules" class="password-rules">
            <li id="length">✖ At least 8 characters</li>
            <li id="uppercase">✖ At least 1 uppercase letter</li>
            <li id="lowercase">✖ At least 1 lowercase letter</li>
            <li id="number">✖ At least 1 number</li>
            <li id="special">✖ At least 1 special character</li>
          </ul>
      </div>

        <div class="form-group">
          <label class="form-label">Confirm Password<span class="required">*</span></label>
          <div class="input-wrapper">
            <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter your password" required>
            <i class="fas fa-eye toggle-password input-icon" id="toggleRePassword"></i>
          </div>
          <div id="matchMsg" class="match-msg"></div>
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
          <i class="fas fa-sign-in-alt"></i> <a href="../login.php">Login to Your Account</a>
        </div>
        <div class="link-item">
          <i class="fas fa-user"></i> <a href="tourist.php">Register as Tourist</a>
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
<script src="../plugins/jquery/jquery.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
  // Toggle Password Visibility
  document.getElementById('togglePassword').addEventListener('click', function() {
    const passwordInput = document.getElementById('password');
    const icon = this;
    
    if (passwordInput.type === 'password') {
      passwordInput.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      passwordInput.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  });

  document.getElementById('toggleRePassword').addEventListener('click', function() {
    const rePasswordInput = document.getElementById('confirm_password');
    const icon = this;
    
    if (rePasswordInput.type === 'password') {
      rePasswordInput.type = 'text';
      icon.classList.remove('fa-eye');
      icon.classList.add('fa-eye-slash');
    } else {
      rePasswordInput.type = 'password';
      icon.classList.remove('fa-eye-slash');
      icon.classList.add('fa-eye');
    }
  });

  // Password Validation
  const password = document.getElementById('password');
  const passwordRules = document.getElementById('passwordRules');
  const rules = {
    length: document.getElementById('length'),
    uppercase: document.getElementById('uppercase'),
    lowercase: document.getElementById('lowercase'),
    number: document.getElementById('number'),
    special: document.getElementById('special')
  };

  password.addEventListener('focus', function() {
    passwordRules.classList.add('show');
  });

  password.addEventListener('input', function() {
    const val = password.value;

    // Length check
    if (val.length >= 8) {
      rules.length.classList.add('valid');
      rules.length.classList.remove('invalid');
      rules.length.textContent = '✔ At least 8 characters';
    } else {
      rules.length.classList.add('invalid');
      rules.length.classList.remove('valid');
      rules.length.textContent = '✖ At least 8 characters';
    }

    // Uppercase check
    if (/[A-Z]/.test(val)) {
      rules.uppercase.classList.add('valid');
      rules.uppercase.classList.remove('invalid');
      rules.uppercase.textContent = '✔ At least 1 uppercase letter';
    } else {
      rules.uppercase.classList.add('invalid');
      rules.uppercase.classList.remove('valid');
      rules.uppercase.textContent = '✖ At least 1 uppercase letter';
    }

    // Lowercase check
    if (/[a-z]/.test(val)) {
      rules.lowercase.classList.add('valid');
      rules.lowercase.classList.remove('invalid');
      rules.lowercase.textContent = '✔ At least 1 lowercase letter';
    } else {
      rules.lowercase.classList.add('invalid');
      rules.lowercase.classList.remove('valid');
      rules.lowercase.textContent = '✖ At least 1 lowercase letter';
    }

    // Number check
    if (/[0-9]/.test(val)) {
      rules.number.classList.add('valid');
      rules.number.classList.remove('invalid');
      rules.number.textContent = '✔ At least 1 number';
    } else {
      rules.number.classList.add('invalid');
      rules.number.classList.remove('valid');
      rules.number.textContent = '✖ At least 1 number';
    }

    // Special character check
    if (/[^A-Za-z0-9]/.test(val)) {
      rules.special.classList.add('valid');
      rules.special.classList.remove('invalid');
      rules.special.textContent = '✔ At least 1 special character';
    } else {
      rules.special.classList.add('invalid');
      rules.special.classList.remove('valid');
      rules.special.textContent = '✖ At least 1 special character';
    }
  });

  // Confirm Password Match
  const confirmPassword = document.getElementById('confirm_password');
  const matchMsg = document.getElementById('matchMsg');

  confirmPassword.addEventListener('input', function() {
    if (confirmPassword.value !== password.value) {
      matchMsg.classList.add('show', 'invalid');
      matchMsg.classList.remove('valid');
      matchMsg.textContent = '✖ Passwords do not match';
    } else if (confirmPassword.value === password.value && confirmPassword.value !== '') {
      matchMsg.classList.add('show', 'valid');
      matchMsg.classList.remove('invalid');
      matchMsg.textContent = '✔ Passwords match';
    } else {
      matchMsg.classList.remove('show');
    }
  });

  // Address Dropdowns
$(document).ready(function() {
    $('#region').change(function() {
        var regCode = $(this).val();
        $('#province').html('<option disabled selected>Loading...</option>');
        $('#citymun').html('<option disabled selected>City/Municipality</option>');
        $('#barangay').html('<option disabled selected>Barangay</option>');

        $.get('../ncmf/get_add_provinces.php', { regCode: regCode }, function(data) {
            var provinces = JSON.parse(data);
            $('#province').html('<option disabled selected>Select Province</option>');
            provinces.forEach(function(province) {
                $('#province').append(`<option value="${province.provCode}">${province.provDesc}</option>`);
            });
        });
    });

    $('#province').change(function() {
        var provCode = $(this).val();
        $('#citymun').html('<option disabled selected>Loading...</option>');
        $('#barangay').html('<option disabled selected>Barangay</option>');

        $.get('../ncmf/get_add_cities.php', { provCode: provCode }, function(data) {
            var cities = JSON.parse(data);
            $('#citymun').html('<option disabled selected>Select City/Municipality</option>');
            cities.forEach(function(city) {
                $('#citymun').append(`<option value="${city.citymunCode}">${city.citymunDesc}</option>`);
            });
        });
    });

    $('#citymun').change(function() {
        var citymunCode = $(this).val();
        $('#barangay').html('<option disabled selected>Loading...</option>');

        $.get('../ncmf/get_add_barangays.php', { citymunCode: citymunCode }, function(data) {
            var barangays = JSON.parse(data);
            $('#barangay').html('<option disabled selected>Select Barangay</option>');
            barangays.forEach(function(brgy) {
                $('#barangay').append(`<option value="${brgy.brgyCode}">${brgy.brgyDesc}</option>`);
            });
        });
    });
});

  // Form Submit with Loading
  document.getElementById('registerForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnRegister');
    const btnText = document.getElementById('btnText');
    
    btn.disabled = true;
    btnText.innerHTML = '<span class="spinner"></span>Registering Company...';
  });

  // Show Success/Error Message
  <?php if (isset($success)): ?>
    Swal.fire({
      icon: 'success',
      title: 'Registration Successful!',
      text: 'Your company has been registered. You can now login.',
      confirmButtonColor: '#2ECC71',
      confirmButtonText: 'Login Now'
    }).then((result) => {
      if (result.isConfirmed) {
        window.location.href = '../login.php';
      }
    });
  <?php endif; ?>

  <?php if (isset($error)): ?>
								Swal.fire({
      icon: 'error',
      title: 'Registration Failed',
      text: '<?php echo $error; ?>',
      confirmButtonColor: '#2ECC71',
      confirmButtonText: 'Try Again'
    });
  <?php endif; ?>

  // Auto-focus first name field
  window.addEventListener('load', function() {
    document.querySelector('input[name="firstname"]').focus();
								});
							</script>
</body>
</html>

		}
    } 
?>