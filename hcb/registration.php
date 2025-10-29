<?php
include '../common/connection.php';
include '../common/randomstrings.php';

// Ensure generate_string function is available
if (!function_exists('generate_string')) {
    function generate_string($input, $strength = 16) {
        $input_length = strlen($input);
        $random_string = '';
        for($i = 0; $i < $strength; $i++) {
            $random_character = $input[mt_rand(0, $input_length - 1)];
            $random_string .= $random_character;
        }
        return $random_string;
    }
}

date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'])) {
    // Sanitize input
    $organization_name = mysqli_real_escape_string($conn, trim($_POST['organization_name']));
    $firstname = mysqli_real_escape_string($conn, trim($_POST['firstname']));
    $lastname = mysqli_real_escape_string($conn, trim($_POST['lastname']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $contact_no = mysqli_real_escape_string($conn, trim($_POST['contact_no']));
    $address = mysqli_real_escape_string($conn, trim($_POST['address']));
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, $_POST['password']);
    $rePassword = mysqli_real_escape_string($conn, $_POST['rePassword']);
    
    $errors = [];
    
    // Validation
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
    
    // Check duplicates
    $check_username = mysqli_query($conn, "SELECT username FROM tbl_useraccount WHERE username = '$username'");
    if (mysqli_num_rows($check_username) > 0) {
        $errors[] = "Username already exists.";
    }
    
    $check_email = mysqli_query($conn, "SELECT email FROM tbl_admin WHERE email = '$email'");
    if (mysqli_num_rows($check_email) > 0) {
        $errors[] = "Email already registered.";
    }
    
    if (empty($errors)) {
        $currentDateTime = date('Y-m-d H:i:s');
        $organization_id = "ORG" . generate_string($specialcasesCHAR, 15);
        $admin_id = "ADM" . generate_string($specialcasesCHAR, 15);
        $useraccount_id = "UA" . generate_string($specialcasesCHAR, 15);
        
        mysqli_autocommit($conn, FALSE);
        
        try {
            // Insert organization
            $insert_org = mysqli_query($conn, "INSERT INTO tbl_organization (organization_id, organization_name, contact_no, email, address, status_id, date_added)
                VALUES ('$organization_id', '$organization_name', '$contact_no', '$email', '$address',
                (SELECT status_id FROM tbl_status WHERE status = 'Active'), '$currentDateTime')");
            
            if (!$insert_org) {
                throw new Exception("Failed to create organization record: " . mysqli_error($conn));
            }
            
            // Insert admin
            $insert_admin = mysqli_query($conn, "INSERT INTO tbl_admin (admin_id, firstname, lastname, email, contact_no, organization_id, status_id, date_added)
                VALUES ('$admin_id', '$firstname', '$lastname', '$email', '$contact_no', '$organization_id',
                (SELECT status_id FROM tbl_status WHERE status = 'Active'), '$currentDateTime')");
            
            if (!$insert_admin) {
                throw new Exception("Failed to create admin record: " . mysqli_error($conn));
            }
            
            // Insert user account
            $insert_account = mysqli_query($conn, "INSERT INTO tbl_useraccount (useraccount_id, username, password, admin_id, usertype_id, status_id, date_added)
                VALUES ('$useraccount_id', '$username', '$password', '$admin_id',
                (SELECT usertype_id FROM tbl_usertype WHERE usertype = 'Admin'),
                (SELECT status_id FROM tbl_status WHERE status = 'Active'), '$currentDateTime')");
            
            if (!$insert_account) {
                throw new Exception("Failed to create user account: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            $success = true;
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Registration failed: " . $e->getMessage();
        }
        
        mysqli_autocommit($conn, TRUE);
        
    } else {
        $error = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifying Body Registration | HalalGuide</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
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
            padding: 40px 20px;
        }
        
        .register-container {
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
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .register-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 35px;
        }
        
        .logo-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 16px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.3);
        }
        
        .logo-icon i {
            font-size: 32px;
            color: white;
        }
        
        .logo-title {
            font-size: 24px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 5px;
        }
        
        .logo-subtitle {
            font-size: 14px;
            color: #718096;
            font-weight: 400;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 600;
            color: #667eea;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
            margin-bottom: 8px;
        }
        
        .required {
            color: #667eea;
        }
        
        .form-control {
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }
        
        textarea.form-control {
            resize: vertical;
        }
        
        .btn-register {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(102, 126, 234, 0.4);
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
            background: #e2e8f0;
        }
        
        .divider span {
            background: white;
            padding: 0 15px;
            position: relative;
            color: #a0aec0;
            font-size: 14px;
        }
        
        .login-link {
            text-align: center;
            font-size: 14px;
            color: #718096;
        }
        
        .login-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .login-link a:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="logo-title">Certifying Body Registration</div>
                <div class="logo-subtitle">Join HalalGuide Certification Network</div>
            </div>
            
            <form method="POST" action="" id="registerForm">
                <!-- Organization Information -->
                <div class="section-title">
                    <i class="fas fa-building"></i>
                    <span>Organization Information</span>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Organization Name<span class="required">*</span></label>
                    <input type="text" name="organization_name" class="form-control" placeholder="Enter organization name" required>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Admin First Name<span class="required">*</span></label>
                        <input type="text" name="firstname" class="form-control" placeholder="Enter first name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Admin Last Name<span class="required">*</span></label>
                        <input type="text" name="lastname" class="form-control" placeholder="Enter last name" required>
                    </div>
                </div>
                
                <!-- Contact Information -->
                <div class="section-title">
                    <i class="fas fa-address-book"></i>
                    <span>Contact Information</span>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Email Address<span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" placeholder="organization@example.com" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Contact Number<span class="required">*</span></label>
                        <input type="text" name="contact_no" class="form-control" placeholder="09123456789" required pattern="[0-9]{11}">
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Address</label>
                    <textarea name="address" class="form-control" rows="3" placeholder="Enter complete address"></textarea>
                </div>
                
                <!-- Account Security -->
                <div class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    <span>Account Security</span>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Username<span class="required">*</span></label>
                    <input type="text" name="username" class="form-control" placeholder="Choose a username" required minlength="4">
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <label class="form-label">Password<span class="required">*</span></label>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Create a password" required minlength="6">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Confirm Password<span class="required">*</span></label>
                        <input type="password" name="rePassword" id="rePassword" class="form-control" placeholder="Confirm your password" required>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-register">
                    <i class="fas fa-user-plus me-2"></i>Create Account
                </button>
            </form>
            
            <div class="divider">
                <span>Already have an account?</span>
            </div>
            
            <div class="login-link">
                <a href="login.php">
                    <i class="fas fa-sign-in-alt me-1"></i>Login to Your Account
                </a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        <?php if (isset($success)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Registration Successful!',
            text: 'Your certifying body account has been created.',
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Login Now'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = 'login.php';
            }
        });
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Registration Failed',
            html: '<?php echo addslashes($error); ?>',
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Try Again'
        });
        <?php endif; ?>
    </script>
</body>
</html>

