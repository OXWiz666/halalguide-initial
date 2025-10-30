<?php
// Reusable Sidebar Component for Company Pages
$current_page = $current_page ?? basename($_SERVER['PHP_SELF']);

// Variables should be set before including this file:
// $company_row, $company_user_row, $user_fullname
if (!isset($user_fullname)) {
    $user_fullname = 'Company User';
}
if (!isset($company_row)) {
    $company_row = ['usertype' => 'Company'];
}
?>
<!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-logo">
        <div class="logo-icon">
            <i class="fas fa-building"></i>
        </div>
        <div class="logo-text">
            <h4>HalalGuide</h4>
            <p>Company Portal</p>
        </div>
    </div>
    
    <div class="sidebar-menu">
        <a href="index.php" class="menu-item <?php echo $current_page == 'index.php' ? 'active' : ''; ?>">
            <i class="fas fa-th-large"></i>
            <span>Dashboard</span>
        </a>
        <a href="halal-certification.php" class="menu-item <?php echo $current_page == 'halal-certification.php' ? 'active' : ''; ?>">
            <i class="fas fa-certificate"></i>
            <span>Halal Certification</span>
        </a>
        <a href="cms.php" class="menu-item <?php echo $current_page == 'cms.php' ? 'active' : ''; ?>">
            <i class="fas fa-file-alt"></i>
            <span>CMS</span>
        </a>
        
        <div class="menu-label">Management</div>
        <a href="company-users.php" class="menu-item <?php echo $current_page == 'company-users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>User Management</span>
        </a>
        <a href="profile.php" class="menu-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-circle"></i>
            <span>Company Profile</span>
        </a>
        <a href="?logout=1" class="menu-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
    
    <div class="sidebar-footer">
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($user_fullname, 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="name"><?php echo htmlspecialchars($user_fullname); ?></div>
                <div class="role"><?php echo htmlspecialchars($company_row['usertype'] ?? 'Company'); ?></div>
            </div>
        </div>
    </div>
</div>

