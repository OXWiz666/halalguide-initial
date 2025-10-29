<?php
session_start();
include '../common/connection.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = mysqli_real_escape_string($conn, trim($_POST['username']));
    $password = mysqli_real_escape_string($conn, trim($_POST['password']));
    
    $sql = "SELECT ua.*, ut.*, a.*, o.* FROM tbl_useraccount ua 
            LEFT JOIN tbl_usertype ut ON ua.usertype_id = ut.usertype_id 
            LEFT JOIN tbl_admin a ON ua.admin_id = a.admin_id
            LEFT JOIN tbl_organization o ON a.organization_id = o.organization_id
            WHERE ua.username = '$username' AND ua.password = '$password' 
            AND ua.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Active')
            AND ut.usertype = 'Admin'";
    $result = $conn->query($sql);
    
    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $_SESSION['user_role'] = $row['usertype'];
        $_SESSION['user_id'] = $row['useraccount_id'];
        $_SESSION['admin_id'] = $row['admin_id'];
        $_SESSION['organization_id'] = $row['organization_id'];
        $_SESSION['organization_name'] = $row['organization_name'];
        header("Location: index.php");
        exit();
    } else {
        $error = "Invalid username or password, or account not authorized.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifying Body Login | HalalGuide</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        .login-container {
            width: 100%;
            max-width: 450px;
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
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .login-card::before {
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
        
        .form-label {
            font-weight: 500;
            color: #4a5568;
            font-size: 14px;
            margin-bottom: 8px;
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
        
        .input-group-text {
            background: white;
            border: 2px solid #e2e8f0;
            border-right: none;
            color: #a0aec0;
        }
        
        .input-group .form-control {
            border-left: none;
        }
        
        .input-group:focus-within .input-group-text {
            border-color: #667eea;
            color: #667eea;
        }
        
        .input-group:focus-within .form-control {
            border-color: #667eea;
        }
        
        .btn-login {
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
        
        .btn-login:hover {
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
        
        .register-link {
            text-align: center;
            font-size: 14px;
            color: #718096;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s;
        }
        
        .register-link a:hover {
            color: #764ba2;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            padding: 12px 16px;
            font-size: 14px;
            margin-bottom: 20px;
        }
        
        .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .forgot-link {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
        }
        
        .forgot-link:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="logo-title">Certifying Body Portal</div>
                <div class="logo-subtitle">Halal Certification Management System</div>
            </div>
            
            <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error; ?>
            </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label class="form-label">Username</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-user"></i>
                        </span>
                        <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
                    </div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="fas fa-lock"></i>
                        </span>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="remember">
                        <label class="form-check-label" for="remember" style="font-size: 14px; color: #718096;">
                            Remember me
                        </label>
                    </div>
                    <a href="#" class="forgot-link">Forgot Password?</a>
                </div>
                
                <button type="submit" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </button>
            </form>
            
            <div class="divider">
                <span>Don't have an account?</span>
            </div>
            
            <div class="register-link">
                <a href="registration.php">
                    <i class="fas fa-user-plus me-1"></i>Register as Certifying Body
                </a>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

