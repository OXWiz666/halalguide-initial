<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

// Check login and access
check_login();
check_access('Super Admin');

// Logout handler
if (isset($_GET['logout'])) {
    logout();
}

$superadmin_id = $_SESSION['superadmin_id'] ?? $_SESSION['user_id'];
$superadmin_name = $_SESSION['superadmin_name'] ?? 'Super Admin';

// Get dashboard statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM tbl_company) as total_companies,
    (SELECT COUNT(*) FROM tbl_organization) as total_organizations,
    (SELECT COUNT(*) FROM tbl_organization WHERE status_id = (SELECT status_id FROM tbl_status WHERE status = 'Active' LIMIT 1)) as active_organizations,
    (SELECT COUNT(*) FROM tbl_organization WHERE status_id = (SELECT status_id FROM tbl_status WHERE status = 'Inactive' LIMIT 1)) as pending_hcb_registrations";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$current_page = 'index.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Super Admin Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h2>Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($superadmin_name); ?>!</p>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-building text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['total_companies'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Total Companies</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-certificate text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['total_organizations'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Certifying Bodies</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-user-clock text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['pending_hcb_registrations'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Pending HCB Registrations</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-building text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['active_organizations'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Active Certifying Bodies</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="content-card">
            <h3 class="mb-4" style="font-size: 20px; font-weight: 700; color: #1a202c;">Quick Actions</h3>
            <div class="row">
                <div class="col-md-4 mb-3">
                    <a href="hcb-registrations.php" class="text-decoration-none">
                        <div style="padding: 20px; background: #f7fafc; border-radius: 12px; border: 2px solid #e2e8f0; transition: all 0.3s;" onmouseover="this.style.borderColor='#667eea'; this.style.background='#edf2f7';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f7fafc';">
                            <i class="fas fa-user-plus" style="font-size: 32px; color: #667eea; margin-bottom: 10px;"></i>
                            <div style="font-weight: 600; color: #2d3748;">Review HCB Registrations</div>
                            <div style="font-size: 12px; color: #718096; margin-top: 5px;">Approve or reject Certifying Body registration applications</div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4 mb-3">
                    <a href="organizations.php" class="text-decoration-none">
                        <div style="padding: 20px; background: #f7fafc; border-radius: 12px; border: 2px solid #e2e8f0; transition: all 0.3s;" onmouseover="this.style.borderColor='#667eea'; this.style.background='#edf2f7';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f7fafc';">
                            <i class="fas fa-building" style="font-size: 32px; color: #667eea; margin-bottom: 10px;"></i>
                            <div style="font-weight: 600; color: #2d3748;">Manage Certifying Bodies</div>
                            <div style="font-size: 12px; color: #718096; margin-top: 5px;">View and manage all HCB organizations</div>
                        </div>
                    </a>
                </div>
                
                <div class="col-md-4 mb-3">
                    <a href="certifications.php" class="text-decoration-none">
                        <div style="padding: 20px; background: #f7fafc; border-radius: 12px; border: 2px solid #e2e8f0; transition: all 0.3s;" onmouseover="this.style.borderColor='#667eea'; this.style.background='#edf2f7';" onmouseout="this.style.borderColor='#e2e8f0'; this.style.background='#f7fafc';">
                            <i class="fas fa-certificate" style="font-size: 32px; color: #667eea; margin-bottom: 10px;"></i>
                            <div style="font-weight: 600; color: #2d3748;">View All Certifications</div>
                            <div style="font-size: 12px; color: #718096; margin-top: 5px;">All approved halal certifications across all organizations</div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

