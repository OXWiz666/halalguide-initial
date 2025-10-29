<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

// Check login and access
check_login();
check_access('Admin');

// Logout handler
if (isset($_GET['logout'])) {
    logout();
}


$admin_id = $_SESSION['admin_id'];

$organization_query = mysqli_query($conn, "SELECT * FROM tbl_admin LEFT JOIN tbl_organization ON tbl_admin.organization_id = tbl_organization.organization_id WHERE admin_id = '$admin_id'");
$organization_row = mysqli_fetch_assoc($organization_query);

$organization_id = $organization_row['organization_id'];
$organization_name = $organization_row['organization_name'];

// Get dashboard statistics
$stats_query = "SELECT 
    (SELECT COUNT(*) FROM tbl_company) as total_companies,
    (SELECT COUNT(*) FROM tbl_company) as total_companies,
    (SELECT COUNT(*) FROM tbl_company) as total_companies,
    (SELECT COUNT(*) FROM tbl_company) as total_companies";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard | Certifying Body Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', sans-serif;
            background: #f7fafc;
            color: #2d3748;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: white;
            border-right: 1px solid #e2e8f0;
            padding: 25px 0;
            z-index: 1000;
            transition: all 0.3s;
        }
        
        .sidebar-logo {
            padding: 0 25px 25px;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 25px;
        }
        
        .logo-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
        }
        
        .logo-icon i {
            color: white;
            font-size: 20px;
        }
        
        .logo-text {
            display: inline-block;
            vertical-align: middle;
        }
        
        .logo-text h4 {
            font-size: 18px;
            font-weight: 700;
            margin: 0;
            color: #1a202c;
        }
        
        .logo-text p {
            font-size: 11px;
            color: #a0aec0;
            margin: 0;
        }
        
        .sidebar-menu {
            padding: 0 15px;
        }
        
        .menu-item {
            display: block;
            padding: 12px 15px;
            margin-bottom: 5px;
            border-radius: 10px;
            color: #4a5568;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            font-weight: 500;
        }
        
        .menu-item i {
            width: 20px;
            margin-right: 12px;
        }
        
        .menu-item:hover {
            background: #f7fafc;
            color: #667eea;
        }
        
        .menu-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .menu-item.active:hover {
            color: white;
        }
        
        .menu-label {
            padding: 15px 15px 10px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #a0aec0;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 25px;
            min-height: 100vh;
        }
        
        /* Top Bar */
        .top-bar {
            background: white;
            border-radius: 15px;
            padding: 20px 25px;
            margin-bottom: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title h2 {
            font-size: 24px;
            font-weight: 700;
            margin: 0;
            color: #1a202c;
        }
        
        .page-title p {
            font-size: 14px;
            color: #718096;
            margin: 5px 0 0;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notification-btn {
            position: relative;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            background: #f7fafc;
            border: none;
            color: #4a5568;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }
        
        .notification-btn:hover {
            background: #e2e8f0;
        }
        
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            width: 18px;
            height: 18px;
            background: #f56565;
            border-radius: 50%;
            font-size: 10px;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }
        
        .user-dropdown {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 8px 15px;
            border-radius: 10px;
            background: #f7fafc;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .user-dropdown:hover {
            background: #e2e8f0;
        }
        
        .user-avatar {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        
        .user-info h6 {
            font-size: 14px;
            font-weight: 600;
            margin: 0;
            color: #2d3748;
        }
        
        .user-info p {
            font-size: 12px;
            color: #a0aec0;
            margin: 0;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 15px;
        }
        
        .stat-card.primary .stat-icon {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
            color: #667eea;
        }
        
        .stat-card.success .stat-icon {
            background: rgba(72, 187, 120, 0.1);
            color: #48bb78;
        }
        
        .stat-card.warning .stat-icon {
            background: rgba(237, 137, 54, 0.1);
            color: #ed8936;
        }
        
        .stat-card.danger .stat-icon {
            background: rgba(245, 101, 101, 0.1);
            color: #f56565;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }
        
        /* Content Card */
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1a202c;
            margin: 0;
        }
        
        .card-actions .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .action-btn {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            text-decoration: none;
            transition: all 0.3s;
            display: block;
        }
        
        .action-btn:hover {
            border-color: #667eea;
            background: #f7fafc;
            transform: translateY(-3px);
        }
        
        .action-btn i {
            font-size: 28px;
            color: #667eea;
            margin-bottom: 10px;
        }
        
        .action-btn span {
            display: block;
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .sidebar .logo-text,
            .sidebar .menu-label {
                display: none;
            }
            
            .menu-item {
                text-align: center;
                padding: 15px 10px;
            }
            
            .menu-item i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-logo">
            <div class="logo-icon">
                <i class="fas fa-certificate"></i>
            </div>
            <div class="logo-text">
                <h4>HalalGuide</h4>
                <p>Certifying Body</p>
            </div>
        </div>
        
        <div class="sidebar-menu">
            <a href="index.php" class="menu-item active">
                <i class="fas fa-th-large"></i>
                <span>Dashboard</span>
            </a>
            <a href="companies.php" class="menu-item">
                <i class="fas fa-building"></i>
                <span>Companies</span>
            </a>
            <a href="certifications.php" class="menu-item">
                <i class="fas fa-certificate"></i>
                <span>Certifications</span>
            </a>
            <a href="applications.php" class="menu-item">
                <i class="fas fa-file-alt"></i>
                <span>Applications</span>
            </a>
            
            <div class="menu-label">Management</div>
            <a href="reports.php" class="menu-item">
                <i class="fas fa-chart-line"></i>
                <span>Reports</span>
            </a>
            <a href="settings.php" class="menu-item">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="logout.php" class="menu-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h2>Dashboard</h2>
                <p>Welcome back, <?php echo htmlspecialchars($organization_name); ?>!</p>
            </div>
            
            <div class="user-menu">
                <button class="notification-btn">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </button>
                
                <div class="user-dropdown">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($organization_name, 0, 2)); ?>
                    </div>
                    <div class="user-info">
                        <h6><?php echo htmlspecialchars($organization_name); ?></h6>
                        <p>Admin</p>
                    </div>
                    <i class="fas fa-chevron-down" style="color: #a0aec0; font-size: 12px;"></i>
                </div>
            </div>
        </div>
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-value"><?php echo $stats['total_companies'] ?? 0; ?></div>
                <div class="stat-label">Total Companies</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['active_certifications'] ?? 0; ?></div>
                <div class="stat-label">Active Certifications</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['expiring_soon'] ?? 0; ?></div>
                <div class="stat-label">Expiring Soon (30 days)</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value"><?php echo $stats['expired_certifications'] ?? 0; ?></div>
                <div class="stat-label">Expired Certifications</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Quick Actions</h3>
            </div>
            
            <div class="quick-actions">
                <a href="certifications.php?action=new" class="action-btn">
                    <i class="fas fa-plus-circle"></i>
                    <span>Issue Certification</span>
                </a>
                <a href="applications.php" class="action-btn">
                    <i class="fas fa-inbox"></i>
                    <span>Review Applications</span>
                </a>
                <a href="companies.php?action=add" class="action-btn">
                    <i class="fas fa-building"></i>
                    <span>Add Company</span>
                </a>
                <a href="reports.php" class="action-btn">
                    <i class="fas fa-file-download"></i>
                    <span>Generate Report</span>
                </a>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Recent Activity</h3>
                <div class="card-actions">
                    <a href="activity.php" class="btn btn-sm btn-outline-secondary">View All</a>
                </div>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Activity</th>
                            <th>Company</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><small class="text-muted">No recent activity</small></td>
                            <td colspan="3">
                                <small class="text-muted">Activity log will appear here once you start managing certifications.</small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

