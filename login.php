<?php
  session_start();
  include 'common/connection.php';

  if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
  
    $sql = "SELECT ua.*, ut.* FROM tbl_useraccount ua 
            LEFT JOIN tbl_usertype ut ON ua.usertype_id = ut.usertype_id 
            WHERE username = '$username' AND password = '$password' AND ua.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Active');";
    $result = $conn->query($sql);
  
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        
        // Only allow Tourist users to login
        if($row['usertype'] == "Tourist"){
            $_SESSION['user_role'] = $row['usertype'];
            $_SESSION['user_id'] = $row['useraccount_id'];
            header("Location: tourist/");
            exit();
        } else {
            $error = "This login page is only for tourists. Please contact administrator.";
        }
    } else {
        $error = "Invalid username or password.";
    }
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | HalalGuide - Your Trusted Halal Certification Platform</title>

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
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      position: relative;
      padding: 40px 20px;
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
      background: rgba(255, 255, 255, 0.3);
      border-radius: 50%;
      pointer-events: none;
      animation: float 15s infinite;
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
      z-index: 1;
      width: 100%;
      max-width: 450px;
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
      padding: 40px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .login-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 25px 70px rgba(0, 0, 0, 0.4);
    }

    .logo-section {
      text-align: center;
      margin-bottom: 30px;
    }

    .logo-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      border-radius: 20px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 15px;
      box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
      animation: bounce 2s infinite;
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
      font-size: 40px;
      color: white;
    }

    .logo-text {
      font-size: 32px;
      font-weight: 700;
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      margin-bottom: 5px;
    }

    .logo-subtitle {
      font-size: 14px;
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

    .form-group {
      margin-bottom: 20px;
      position: relative;
    }

    .form-label {
      display: block;
      margin-bottom: 8px;
      font-size: 14px;
      font-weight: 500;
      color: #333;
    }

    .input-wrapper {
      position: relative;
    }

    .form-control {
      width: 100%;
      padding: 14px 45px 14px 45px;
      border: 2px solid #e0e0e0;
      border-radius: 12px;
      font-size: 15px;
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
      left: 15px;
      top: 50%;
      transform: translateY(-50%);
      color: #999;
      transition: color 0.3s ease;
    }

    .form-control:focus ~ .input-icon {
      color: #2ECC71;
    }

    .toggle-password {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #999;
      transition: color 0.3s ease;
    }

    .toggle-password:hover {
      color: #2ECC71;
    }

    .remember-forgot {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 25px;
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
      accent-color: #2ECC71;
    }

    .forgot-link {
      color: #2ECC71;
      text-decoration: none;
      font-weight: 500;
      transition: color 0.3s ease;
    }

    .forgot-link:hover {
      color: #27AE60;
    }

    .btn-login {
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
    }

    .btn-login::before {
      content: '';
      position: absolute;
      top: 0;
      left: -100%;
      width: 100%;
      height: 100%;
      background: rgba(255, 255, 255, 0.2);
      transition: left 0.5s ease;
    }

    .btn-login:hover::before {
      left: 100%;
    }

    .btn-login:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
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
      background: white;
      padding: 0 15px;
      position: relative;
      color: #999;
      font-size: 14px;
    }

    .register-links {
      text-align: center;
      margin-top: 20px;
    }

    .register-link {
      display: block;
      margin-bottom: 10px;
      font-size: 14px;
      color: #666;
    }

    .register-link a {
      color: #2ECC71;
      text-decoration: none;
      font-weight: 600;
      transition: color 0.3s ease;
    }

    .register-link a:hover {
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

    /* Responsive */
    @media (max-width: 480px) {
      body {
        padding: 20px 10px;
      }
      
      .login-card {
        padding: 30px 20px;
      }

      .logo-text {
        font-size: 28px;
      }

      .welcome-text h3 {
        font-size: 20px;
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
  <!-- Floating Particles -->
  <div class="particle" style="width: 60px; height: 60px; left: 10%; animation-delay: 0s;"></div>
  <div class="particle" style="width: 40px; height: 40px; left: 30%; animation-delay: 2s;"></div>
  <div class="particle" style="width: 80px; height: 80px; left: 50%; animation-delay: 4s;"></div>
  <div class="particle" style="width: 50px; height: 50px; left: 70%; animation-delay: 6s;"></div>
  <div class="particle" style="width: 70px; height: 70px; left: 90%; animation-delay: 8s;"></div>

  <!-- Loading Overlay -->
  <div class="loading-overlay" id="loadingOverlay">
    <div class="loading-spinner"></div>
  </div>

  <div class="login-container">
    <div class="login-card">
      <!-- Logo Section -->
      <div class="logo-section">
        <div class="logo-icon">
          <i class="fas fa-mosque"></i>
        </div>
        <div class="logo-text">HalalGuide</div>
        <div class="logo-subtitle">Your Trusted Halal Certification Platform</div>
  </div>

      <!-- Welcome Text -->
      <div class="welcome-text">
        <h3>Welcome Back!</h3>
        <p>Please login to your account to continue</p>
            </div>

      <!-- Login Form -->
      <form method="post" id="loginForm">
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="input-wrapper">
            <input type="text" name="username" id="username" class="form-control" placeholder="Enter your username" required autocomplete="username">
            <i class="fas fa-user input-icon"></i>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-wrapper">
            <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required autocomplete="current-password">
            <i class="fas fa-lock input-icon"></i>
            <i class="fas fa-eye toggle-password" id="togglePassword"></i>
            </div>
          </div>

        <div class="remember-forgot">
          <label class="remember-me">
            <input type="checkbox" id="remember" name="remember">
            <span>Remember me</span>
              </label>
          <a href="#" class="forgot-link">Forgot Password?</a>
        </div>

        <button type="submit" name="btnLogin" class="btn-login" id="btnLogin">
          <span id="btnText">Login to Your Account</span>
        </button>
      </form>

      <!-- Divider -->
      <div class="divider">
        <span>New to HalalGuide?</span>
      </div>

      <!-- Register Links -->
      <div class="register-links">
        <div class="register-link">
          <i class="fas fa-user-plus"></i> <a href="registration.php">Register Here</a>
        </div>
          </div>
          </div>

    <!-- Back to Home -->
    <div class="back-home">
      <a href="home.php">
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

  // Show Error Message if Login Failed
  <?php if (isset($error)): ?>
    Swal.fire({
      icon: 'error',
      title: 'Login Failed',
      text: '<?php echo $error; ?>',
      confirmButtonColor: '#2ECC71',
      confirmButtonText: 'Try Again'
    });
  <?php endif; ?>

  // Auto-focus username field on page load
  window.addEventListener('load', function() {
    document.getElementById('username').focus();
  });

  // Prevent multiple form submissions
  let isSubmitting = false;
  document.getElementById('loginForm').addEventListener('submit', function(e) {
    if (isSubmitting) {
      e.preventDefault();
      return false;
    }
    isSubmitting = true;
  });
</script>
</body>
</html>
