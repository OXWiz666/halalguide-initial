<?php
// Reusable Sidebar Component for Super Admin Pages
$current_page = $current_page ?? basename($_SERVER['PHP_SELF']);
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">
            <i class="fas fa-user-shield"></i>
        </div>
        <div class="logo-text">
            <h4>HalalGuide</h4>
            <p>Super Admin</p>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <a href="index.php" class="menu-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="hcb-registrations.php" class="menu-item <?php echo $current_page == 'hcb-registrations.php' || strpos($current_page, 'registration') !== false ? 'active' : ''; ?>">
            <i class="fas fa-user-plus"></i>
            <span>HCB Registrations</span>
        </a>
        <!-- Certifying Bodies Accreditation (with submodules) -->
        <div class="menu-label">Certifying Bodies Accreditation</div>
        <a href="certifications.php" class="menu-item <?php echo $current_page == 'certifications.php' ? 'active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>All HCB Applications</span>
        </a>
        <a href="organizations.php" class="menu-item <?php echo $current_page == 'organizations.php' ? 'active' : ''; ?>">
            <i class="fas fa-building"></i>
            <span>Certifying Body Listing</span>
        </a>
        <a href="companies.php" class="menu-item <?php echo $current_page == 'companies.php' ? 'active' : ''; ?>">
            <i class="fas fa-store"></i>
            <span>Company Listing</span>
        </a>
        
        <div class="menu-label">Management</div>
        <a href="reports.php" class="menu-item <?php echo $current_page == 'reports.php' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>Reports</span>
        </a>
        <a href="settings.php" class="menu-item <?php echo $current_page == 'settings.php' ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="logout.php" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</div>

<style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
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
        
        /* Content Card */
        .content-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            margin-bottom: 25px;
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

