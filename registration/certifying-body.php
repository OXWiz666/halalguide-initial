<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registration | HalalGuide</title>

  <!-- external style -->
  <?php require '../common/headerlinks.php'; ?>
  
  <style>
    /* Custom Green Theme Override */
    body {
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      min-height: 100vh;
    }
    
    .card {
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      border: none;
    }
    
    .btn-primary {
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      border: none;
      border-radius: 12px;
      padding: 12px;
      font-weight: 600;
      transition: all 0.3s ease;
    }
    
    .btn-primary:hover {
      background: linear-gradient(135deg, #27AE60 0%, #229954 100%);
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(46, 204, 113, 0.4);
    }
    
    .form-control:focus {
      border-color: #2ECC71;
      box-shadow: 0 0 0 4px rgba(46, 204, 113, 0.1);
    }
    
    .input-group-text {
      background: #f8f9fa;
      border-color: #e0e0e0;
    }
    
    .input-group:focus-within .input-group-text {
      border-color: #2ECC71;
      background: rgba(46, 204, 113, 0.1);
    }
    
    .h1 {
      color: white;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
    }
    
    .h1 b {
      background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }
    
    .login-box-msg {
      color: #333;
      font-weight: 600;
    }
    
    .container {
      padding-top: 40px;
    }
  </style>
</head>
<body class="hold-transition register-page">

<div class="container mt-5">
  <div class="row justify-content-center">
    <div class="col-12 col-md-8"> <!-- 8 columns wide on medium+ screens, full on mobile -->
      
      <div class="text-center mb-3">
        <a href="../../index2.html" class="h1"><b>Halal</b>Guide</a>
      </div>

<div class="card">
  <div class="card-body">
    <p class="login-box-msg h4"><b>Certifying Body Registration</b></p>

    <form action="../../index.html" method="post">
      <!-- Organization Name (full width) -->
      <div class="input-group mb-3">
        <input type="text" class="form-control" name="certifying_body" placeholder="Organization Name" required>
        <div class="input-group-append">
          <div class="input-group-text">
            <span class="fas fa-building-columns"></span>
          </div>
        </div>
      </div>

      <!-- Email, Contact, Tel in one row -->
      <div class="row">
        <div class="col-md-4 mb-3">
          <div class="input-group">
            <input type="email" class="form-control" name="email" placeholder="Email" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-envelope"></span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="input-group">
            <input type="text" class="form-control" name="contact_no" placeholder="Contact Number" required>
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-mobile"></span>
              </div>
            </div>
          </div>
        </div>
        <div class="col-md-4 mb-3">
          <div class="input-group">
            <input type="text" class="form-control" name="tel_no" placeholder="Telephone Number">
            <div class="input-group-append">
              <div class="input-group-text">
                <span class="fas fa-phone"></span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Password (full width) -->
      <div class="input-group mb-3">
        <input type="password" class="form-control" placeholder="Password" required>
        <div class="input-group-append">
          <div class="input-group-text">
            <span class="fas fa-lock"></span>
          </div>
        </div>
      </div>

      <!-- Retype Password (full width) -->
      <div class="input-group mb-3">
        <input type="password" class="form-control" placeholder="Retype password" required>
        <div class="input-group-append">
          <div class="input-group-text">
            <span class="fas fa-lock"></span>
          </div>
        </div>
      </div>

      <!-- Submit Button -->
      <div class="row">
        <div class="col-12">
          <button type="submit" class="btn btn-primary btn-block">Register</button>
        </div>
      </div>
    </form>
  </div><!-- /.card-body -->
</div><!-- /.card -->


    </div><!-- /.col-8 -->
  </div><!-- /.row -->
</div><!-- /.container -->

<!-- jQuery -->
<?php require '../common/footerlinks.php'; ?>
<!-- AdminLTE App -->
<script src="../../dist/js/adminlte.min.js"></script>
</body>
</html>
