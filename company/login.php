<?php
  session_start();
  include '../common/connection.php';

  // Handle POST requests with PRG pattern (Post-Redirect-Get)
  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
  
    $sql = "SELECT ua.*, ut.* FROM tbl_useraccount ua 
            LEFT JOIN tbl_usertype ut ON ua.usertype_id = ut.usertype_id 
            WHERE username = '$username' AND password = '$password' 
            AND ua.status_id != '3' 
            AND ua.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Active');";
    $result = $conn->query($sql);
  
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        
        // Only allow Company users to login (Establishment, Accommodation, Tourist Spot, Prayer Facility)
        $company_types = ['Establishment', 'Accommodation', 'Tourist Spot', 'Prayer Facility'];
        if(in_array($row['usertype'], $company_types)){
            $_SESSION['user_role'] = $row['usertype'];
            $_SESSION['user_id'] = $row['useraccount_id'];
            $_SESSION['company_id'] = $row['company_id'];
            // Redirect on success (PRG pattern)
            header("Location: index.php");
            exit();
        } else {
            // Store error in session and redirect (PRG pattern)
            $_SESSION['login_error'] = "This login page is only for company accounts. Please use the tourist login page.";
            header("Location: login.php?error=1");
            exit();
        }
    } else {
        // Store error in session and redirect (PRG pattern)
        $_SESSION['login_error'] = "Invalid username or password.";
        header("Location: login.php?error=1");
        exit();
    }
  }

  // Get error from session if exists
  $error = $_SESSION['login_error'] ?? null;
  // If redirected from registration failure, surface the detailed error
  if (!$error && isset($_SESSION['registration_error'])) {
      $error = $_SESSION['registration_error'];
  }
  // Clear error from session after retrieving
  if (isset($_SESSION['login_error'])) {
      unset($_SESSION['login_error']);
  }
  if (isset($_SESSION['registration_error'])) {
      unset($_SESSION['registration_error']);
  }
  
  // Check if redirected from registration
  $registered = isset($_GET['registered']) && $_GET['registered'] == '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Company Login | HalalGuide - Your Business Portal</title>

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
      position: relative;
      padding: 15px;
      margin: 0;
      display: flex;
      flex-direction: column;
      justify-content: flex-start;
      align-items: center;
      overflow-x: hidden;
    }

    /* Animated Background */
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
      0% {
        transform: translate(0, 0);
      }
      100% {
        transform: translate(50px, 50px);
      }
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

    .login-container {
      position: relative;
      width: 100%;
      max-width: 450px;
      z-index: 1;
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

    .login-card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 30px 35px 25px 35px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .login-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
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
      0%, 100% {
        transform: translateY(0);
      }
      50% {
        transform: translateY(-10px);
      }
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

    .form-group {
      margin-bottom: 16px;
      position: relative;
    }

    .form-label {
      display: block;
      margin-bottom: 6px;
      font-size: 14px;
      font-weight: 500;
      color: #333;
    }

    .input-wrapper {
      position: relative;
    }

    .form-control {
      width: 100%;
      padding: 16px 50px 16px 50px;
      border: 2px solid #e8e8e8;
      border-radius: 14px;
      font-size: 15px;
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      background: #fafafa;
      font-family: 'Poppins', sans-serif;
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

    .input-icon {
      position: absolute;
      left: 18px;
      top: 50%;
      transform: translateY(-50%);
      color: #9ca3af;
      transition: all 0.3s ease;
      font-size: 16px;
      z-index: 1;
    }

    .form-control:focus ~ .input-icon {
      color: #9333EA;
      transform: translateY(-50%) scale(1.1);
    }

    .toggle-password {
      position: absolute;
      right: 18px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #9ca3af;
      transition: all 0.3s ease;
      font-size: 16px;
      padding: 4px;
      border-radius: 6px;
      z-index: 1;
    }

    .toggle-password:hover {
      color: #9333EA;
      background: rgba(147, 51, 234, 0.1);
      transform: translateY(-50%) scale(1.1);
    }

    .remember-forgot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 18px;
      font-size: 14px;
    }

    .remember-me {
      display: flex;
      align-items: center;
      cursor: pointer;
    }

    .remember-me input[type="checkbox"] {
      width: 18px;
      height: 18px;
      margin-right: 8px;
      cursor: pointer;
      accent-color: #9333EA;
    }

    .forgot-link {
      color: #9333EA;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      padding: 4px 8px;
      border-radius: 6px;
    }

    .forgot-link:hover {
      color: #7C3AED;
      background: rgba(147, 51, 234, 0.08);
    }

    .btn-login {
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
      position: relative;
      overflow: hidden;
      font-family: 'Poppins', sans-serif;
      box-shadow: 0 8px 24px rgba(147, 51, 234, 0.35),
                  0 0 0 1px rgba(255, 255, 255, 0.1) inset;
      letter-spacing: 0.3px;
    }

    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.3), transparent);
      transition: left 0.6s ease;
    }

    .btn-login:hover::before {
      left: 100%;
    }

    .btn-login:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px rgba(147, 51, 234, 0.5),
                  0 0 0 1px rgba(255, 255, 255, 0.15) inset;
    }

    .btn-login:active {
      transform: translateY(0);
    }

    .btn-login:disabled {
      opacity: 0.6;
      cursor: not-allowed;
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
      background: white;
      padding: 0 15px;
      position: relative;
      color: #999;
      font-size: 14px;
    }

    .register-links {
      text-align: center;
      margin-top: 5px;
      margin-bottom: 0;
    }

    .register-link {
      display: block;
      margin-bottom: 0;
      font-size: 14px;
      color: #666;
      padding-bottom: 0;
    }

    .register-link a {
      color: #9333EA;
      text-decoration: none;
      font-weight: 600;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .register-link a:hover {
      color: #7C3AED;
      transform: translateX(2px);
    }

    .register-link i {
      color: #9333EA;
      font-size: 16px;
      transition: all 0.3s ease;
    }

    .register-link a:hover i {
      color: #7C3AED;
      transform: scale(1.1);
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

    /* Responsive */
    @media (max-width: 480px) {
      body {
        padding: 15px 10px;
      }
      
      .login-card {
        padding: 30px 20px 25px 20px;
      }

      .logo-text {
        font-size: 28px;
      }

      .welcome-text h3 {
        font-size: 20px;
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

    /* Loading overlay */
    .loading-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 9999;
      align-items: center;
      justify-content: center;
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

    .loading-overlay.active {
      display: flex;
    }

    .loading-spinner {
      width: 50px;
      height: 50px;
      border: 4px solid rgba(255, 255, 255, 0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 1s linear infinite;
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
  <div class="particle" style="width: 50px; height: 50px; left: 15%; top: 60%; animation-delay: 1s;"></div>
  <div class="particle" style="width: 75px; height: 75px; left: 75%; top: 70%; animation-delay: 3s;"></div>

  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
  </div>

  <div class="login-container">
    <div class="login-card">
      <!-- Logo Section -->
      <div class="logo-section">
        <div class="logo-icon">
          <i class="fas fa-building"></i>
        </div>
        <div class="logo-text">HalalGuide</div>
        <div class="logo-subtitle">Company Business Portal</div>
      </div>

      <!-- Welcome Text -->
      <div class="welcome-text">
        <h3>Welcome Back!</h3>
        <p>Please login to your company account to continue</p>
      </div>

      <!-- Login Form -->
      <form method="post" id="loginForm">
        <div class="form-group">
          <label class="form-label" for="username">Username</label>
          <div class="input-wrapper">
            <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required autocomplete="username">
            <i class="fas fa-user input-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <div class="input-wrapper">
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
            <i class="fas fa-lock input-icon"></i>
            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
          </div>
        </div>

        <div class="remember-forgot">
          <label class="remember-me">
            <input type="checkbox" name="remember" value="1">
            <span>Remember me</span>
          </label>
          <a href="#" class="forgot-link">Forgot Password?</a>
        </div>

        <button type="submit" class="btn-login" id="btnLogin">
          <span id="btnText">Login to Your Account</span>
        </button>
      </form>

      <!-- Divider -->
      <div class="divider">
        <span>Don't have an account?</span>
      </div>

      <!-- Register Links -->
      <div class="register-links">
        <div class="register-link">
          <i class="fas fa-building"></i> <a href="../company/company.php">Register Your Company</a>
        </div>
        <div class="register-link" style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
          <div style="font-size: 13px; color: #999; margin-bottom: 8px;">Are you a Certifying Body?</div>
          <i class="fas fa-certificate"></i> <a href="../hcb/registration.php">Register as Certifying Body</a>
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

  // Show success message if redirected from registration
  <?php if ($registered): ?>
  Swal.fire({
    icon: 'success',
    title: 'Registration Successful!',
    text: 'Your company account has been created successfully. Please login with your credentials.',
    confirmButtonColor: '#9333EA',
    timer: 5000,
    timerProgressBar: true
  });
  // Clean URL
  if (window.history.replaceState) {
    window.history.replaceState({}, document.title, window.location.pathname);
  }
  <?php endif; ?>

  // Show error message if login failed
  <?php if (isset($error) && $error): ?>
  Swal.fire({
    icon: 'error',
    title: 'Login Failed',
    text: '<?php echo addslashes($error); ?>',
    confirmButtonColor: '#9333EA'
  });
  // Clean URL
  if (window.history.replaceState) {
    window.history.replaceState({}, document.title, window.location.pathname);
  }
  <?php endif; ?>

  // Form Submit with Loading State
  document.getElementById('loginForm').addEventListener('submit', function(e) {
    const btn = document.getElementById('btnLogin');
    const btnText = document.getElementById('btnText');
    const overlay = document.getElementById('loadingOverlay');
    
    // Show loading state
    btn.disabled = true;
    btnText.innerHTML = '<span class="spinner"></span>Logging in...';
    overlay.classList.add('active');
  });
</script>
</body>
</html>


