<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

// Check login - allow all company user types
check_login();

// Check if user is a company type
$company_types = ['Establishment', 'Accommodation', 'Tourist Spot', 'Prayer Facility'];
if (!in_array($_SESSION['user_role'], $company_types)) {
    header("Location: ../login.php");
    exit();
}

// Logout handler
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$useraccount_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];

// Get company information
$company_query = mysqli_query($conn, 
    "SELECT c.*, ut.usertype, s.status, a.other as address_line,
     b.brgyDesc, cm.citymunDesc, p.provDesc, r.regDesc
     FROM tbl_useraccount ua
     LEFT JOIN tbl_company c ON ua.company_id = c.company_id
     LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
     LEFT JOIN tbl_status s ON c.status_id = s.status_id
     LEFT JOIN tbl_address a ON c.address_id = a.address_id
     LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
     LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
     LEFT JOIN refprovince p ON cm.provCode = p.provCode
     LEFT JOIN refregion r ON p.regCode = r.regCode
     WHERE ua.useraccount_id = '$useraccount_id'");
$company_row = mysqli_fetch_assoc($company_query);

// Get company user information
$company_user_query = mysqli_query($conn,
    "SELECT cu.* FROM tbl_useraccount ua
     LEFT JOIN tbl_company_user cu ON ua.company_user_id = cu.company_user_id
     WHERE ua.useraccount_id = '$useraccount_id'");
$company_user_row = mysqli_fetch_assoc($company_user_query);

// Get dashboard statistics
$total_users = mysqli_query($conn, "SELECT COUNT(*) as count FROM tbl_useraccount WHERE company_id = '$company_id'");
$total_users_row = mysqli_fetch_assoc($total_users);

// Get halal certification status
$cert_status = $company_row['status'] ?? 'Not-Certified';
$is_halal_certified = ($cert_status == 'Halal-Certified' || $cert_status == '4' || $cert_status == 4);

// Get notifications from database
$notifications = [];
$has_unread_notifications = false;

// Fetch application notifications for this company
$notif_query = "SELECT n.*, ca.application_number 
                FROM tbl_application_notifications n
                LEFT JOIN tbl_certification_application ca ON n.application_id = ca.application_id
                WHERE n.recipient_type = 'Company' 
                AND n.recipient_id = '$company_id'
                ORDER BY n.date_added DESC
                LIMIT 20";
$notif_result = mysqli_query($conn, $notif_query);

$read_notification_ids = isset($_SESSION['read_notification_ids']) ? $_SESSION['read_notification_ids'] : [];

while ($notif_row = mysqli_fetch_assoc($notif_result)) {
    $is_read = in_array($notif_row['notification_id'], $read_notification_ids) || $notif_row['is_read'] == 1;
    
    // Determine notification type and icon based on notification_type
    $type = 'info';
    $icon = 'bell';
    
    if (strpos($notif_row['notification_type'], 'Approved') !== false || strpos($notif_row['subject'], 'Approved') !== false) {
        $type = 'success';
        $icon = 'check-circle';
    } elseif (strpos($notif_row['notification_type'], 'Rejected') !== false || strpos($notif_row['subject'], 'Rejected') !== false) {
        $type = 'danger';
        $icon = 'times-circle';
    } elseif (strpos($notif_row['notification_type'], 'Document') !== false) {
        if (strpos($notif_row['subject'], 'Approved') !== false) {
            $type = 'success';
            $icon = 'check-circle';
        } elseif (strpos($notif_row['subject'], 'Rejected') !== false) {
            $type = 'danger';
            $icon = 'times-circle';
        } else {
            $type = 'warning';
            $icon = 'file-alt';
        }
    } elseif (strpos($notif_row['notification_type'], 'Visit') !== false) {
        $type = 'info';
        $icon = 'calendar-check';
    } elseif (strpos($notif_row['notification_type'], 'Status') !== false) {
        $type = 'warning';
        $icon = 'info-circle';
    }
    
    $notifications[] = [
        'id' => $notif_row['notification_id'],
        'type' => $type,
        'icon' => $icon,
        'title' => $notif_row['subject'],
        'message' => $notif_row['message'],
        'time' => date('M d, Y g:i A', strtotime($notif_row['date_added'])),
        'read' => $is_read
    ];
    
    if (!$is_read) {
        $has_unread_notifications = true;
    }
}

// Add legacy status-based notifications as fallback
if (empty($notifications)) {
    if ($cert_status == 'Halal-Certified' || $cert_status == '4' || $cert_status == 4) {
        $notifications[] = [
            'id' => 'legacy_approved',
            'type' => 'success',
            'icon' => 'check-circle',
            'title' => 'Halal Certification Approved!',
            'message' => 'Congratulations! Your halal certification application has been approved. Your company is now halal-certified.',
            'time' => 'Just now',
            'read' => isset($_SESSION['notification_read_approved']) ? $_SESSION['notification_read_approved'] : false
        ];
        if (!isset($_SESSION['notification_read_approved'])) {
            $has_unread_notifications = true;
        }
    } else if ($cert_status == 'Rejected' || $cert_status == 'Rejected Application') {
        $notifications[] = [
            'id' => 'legacy_rejected',
            'type' => 'danger',
            'icon' => 'times-circle',
            'title' => 'Halal Application Rejected',
            'message' => 'Your halal certification application has been rejected. Please review the requirements and submit a new application.',
            'time' => 'Recently',
            'read' => isset($_SESSION['notification_read_rejected']) ? $_SESSION['notification_read_rejected'] : false
        ];
        if (!isset($_SESSION['notification_read_rejected'])) {
            $has_unread_notifications = true;
        }
    } else if ($cert_status == 'Pending') {
        $notifications[] = [
            'id' => 'legacy_pending',
            'type' => 'warning',
            'icon' => 'clock',
            'title' => 'Application Under Review',
            'message' => 'Your halal certification application is currently under review by the certifying body. You will be notified once a decision is made.',
            'time' => 'Recently',
            'read' => isset($_SESSION['notification_read_pending']) ? $_SESSION['notification_read_pending'] : false
        ];
    }
}

// Handle notification read status
if (isset($_GET['mark_read'])) {
    if ($_GET['mark_read'] == 'all') {
        // Mark all database notifications as read
        if (!empty($notifications)) {
            $notification_ids = array_filter(array_column($notifications, 'id'), function($id) {
                return strpos($id, 'legacy_') === false; // Only get non-legacy IDs
            });
            
            if (!empty($notification_ids)) {
                $ids_escaped = array_map(function($id) use ($conn) {
                    return "'" . mysqli_real_escape_string($conn, $id) . "'";
                }, $notification_ids);
                $ids_string = implode(',', $ids_escaped);
                mysqli_query($conn, "UPDATE tbl_application_notifications SET is_read = 1, read_date = NOW() WHERE notification_id IN ($ids_string)");
            }
        }
        
        // Legacy session-based notifications
        $_SESSION['notification_read_approved'] = true;
        $_SESSION['notification_read_rejected'] = true;
        $_SESSION['notification_read_pending'] = true;
        
        // Store read IDs in session
        if (!isset($_SESSION['read_notification_ids'])) {
            $_SESSION['read_notification_ids'] = [];
        }
        foreach ($notifications as $notif) {
            if (strpos($notif['id'], 'legacy_') === false) { // Only store non-legacy IDs
                $_SESSION['read_notification_ids'][] = $notif['id'];
            }
        }
    } else if (isset($_GET['notif_id'])) {
        // Mark specific notification as read
        $notif_id = mysqli_real_escape_string($conn, $_GET['notif_id']);
        mysqli_query($conn, "UPDATE tbl_application_notifications SET is_read = 1, read_date = NOW() WHERE notification_id = '$notif_id'");
        
        if (!isset($_SESSION['read_notification_ids'])) {
            $_SESSION['read_notification_ids'] = [];
        }
        if (!in_array($notif_id, $_SESSION['read_notification_ids'])) {
            $_SESSION['read_notification_ids'][] = $notif_id;
        }
    } else {
        // Legacy session-based
        $read_type = $_GET['mark_read'];
        if ($read_type == 'approved') {
            $_SESSION['notification_read_approved'] = true;
        } else if ($read_type == 'rejected') {
            $_SESSION['notification_read_rejected'] = true;
        } else if ($read_type == 'pending') {
            $_SESSION['notification_read_pending'] = true;
        }
    }
    header("Location: index.php");
    exit();
}

$company_name = $company_row['company_name'] ?? 'Company Name';
$company_type = $company_row['usertype'] ?? 'Company';
$user_fullname = trim(($company_user_row['firstname'] ?? '') . ' ' . ($company_user_row['middlename'] ?? '') . ' ' . ($company_user_row['lastname'] ?? ''));
$user_fullname = $user_fullname ?: 'Company User';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Dashboard | HalalGuide</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
    <link rel="stylesheet" href="css/company-common.css?v=<?php echo time(); ?>">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: #f8fafc;
            color: #1f2937;
            overflow-x: hidden;
        }
        
        /* Sidebar styles are in company-common.css - removed inline styles to use external CSS */
        
        /* Top Bar */
        .top-bar {
            background: white;
            padding: 20px 30px;
            border-radius: 16px;
            margin-bottom: 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .page-title {
            font-size: 28px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        
        .page-subtitle {
            font-size: 14px;
            color: #6b7280;
            margin-top: 4px;
        }
        
        .top-bar-actions {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        /* Halal Certified Badge - Premium Style */
        .halal-certified-badge-premium {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            animation: pulse-glow 2s ease-in-out infinite;
            text-transform: uppercase;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .halal-certified-badge-premium i {
            font-size: 16px;
            animation: rotate-certificate 3s linear infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            }
            50% {
                box-shadow: 0 4px 25px rgba(16, 185, 129, 0.6);
            }
        }
        
        @keyframes rotate-certificate {
            0%, 100% {
                transform: rotate(0deg);
            }
            25% {
                transform: rotate(-5deg);
            }
            75% {
                transform: rotate(5deg);
            }
        }
        
        .notification-btn {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: #f3f4f6;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .notification-btn:hover {
            background: #e5e7eb;
            color: #9333EA;
        }
        
        .notification-badge {
            position: absolute;
            top: 8px;
            right: 8px;
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            border: 2px solid white;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            transition: all 0.3s ease;
            border: 1px solid #f3f4f6;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px rgba(147, 51, 234, 0.15);
        }
        
        .stat-card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
        }
        
        .stat-icon.gradient-purple {
            background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%);
        }
        
        .stat-icon.gradient-blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }
        
        .stat-icon.gradient-green {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
        }
        
        .stat-icon.gradient-orange {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            color: #6b7280;
            font-weight: 500;
        }
        
        .stat-change {
            font-size: 12px;
            margin-top: 8px;
            display: flex;
            align-items: center;
            gap: 4px;
        }
        
        .stat-change.positive {
            color: #10b981;
        }
        
        .stat-change.negative {
            color: #ef4444;
        }
        
        /* Notification Dropdown */
        .notification-wrapper {
            position: relative;
        }
        
        .notification-dropdown {
            position: absolute;
            top: calc(100% + 10px);
            right: 0;
            width: 380px;
            max-height: 500px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
            z-index: 1000;
            overflow: hidden;
            border: 1px solid #e5e7eb;
        }
        
        .notification-header {
            padding: 20px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafafa;
        }
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
        }
        
        .notification-item {
            display: flex;
            gap: 12px;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            position: relative;
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .notification-item.unread {
            background: #f8fafc;
            border-color: #e5e7eb;
        }
        
        .notification-item.read {
            background: white;
            opacity: 0.8;
        }
        
        .notification-item:hover {
            background: #f3f4f6;
            transform: translateX(-2px);
        }
        
        .notification-item.notification-success {
            border-left: 4px solid #10b981;
        }
        
        .notification-item.notification-danger {
            border-left: 4px solid #ef4444;
        }
        
        .notification-item.notification-warning {
            border-left: 4px solid #f59e0b;
        }
        
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        
        .notification-success .notification-icon {
            background: #d1fae5;
            color: #10b981;
        }
        
        .notification-danger .notification-icon {
            background: #fee2e2;
            color: #ef4444;
        }
        
        .notification-warning .notification-icon {
            background: #fef3c7;
            color: #f59e0b;
        }
        
        .notification-content {
            flex: 1;
            min-width: 0;
        }
        
        .notification-title {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 4px;
            font-size: 14px;
        }
        
        .notification-message {
            font-size: 13px;
            color: #6b7280;
            line-height: 1.5;
            margin-bottom: 6px;
        }
        
        .notification-time {
            font-size: 11px;
            color: #9ca3af;
        }
        
        .notification-mark-read {
            position: absolute;
            top: 10px;
            right: 10px;
            width: 24px;
            height: 24px;
            border-radius: 6px;
            background: #f3f4f6;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6b7280;
            text-decoration: none;
            opacity: 0;
            transition: all 0.3s ease;
            font-size: 10px;
        }
        
        .notification-item:hover .notification-mark-read {
            opacity: 1;
        }
        
        .notification-mark-read:hover {
            background: #9333EA;
            color: white;
        }
        
        .notification-empty {
            padding: 40px 20px;
            text-align: center;
        }
        
        .notification-footer {
            padding: 15px 20px;
            border-top: 1px solid #f3f4f6;
            background: #fafafa;
            text-align: center;
        }
        
        /* Content Cards */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .content-card {
            background: white;
            padding: 25px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            border: 1px solid #f3f4f6;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f3f4f6;
        }
        
        .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #1f2937;
            margin: 0;
        }
        
        .card-action {
            color: #9333EA;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .card-action:hover {
            color: #7C3AED;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            /* Sidebar responsive styles are in company-common.css */
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .content-grid {
                grid-template-columns: 1fr;
            }
            
            .top-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <?php 
    $current_page = 'index.php';
    include 'includes/sidebar.php'; 
    ?>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <h1 class="page-title" style="margin-bottom: 0;">Dashboard</h1>
                    <?php if ($is_halal_certified): ?>
                    <span class="halal-certified-badge-premium">
                        <i class="fas fa-certificate"></i>
                        <span>HALAL CERTIFIED</span>
                    </span>
                    <?php endif; ?>
                </div>
                <p class="page-subtitle">Welcome back, <?php echo htmlspecialchars($user_fullname); ?>!</p>
            </div>
            <div class="top-bar-actions">
                <div class="notification-wrapper" style="position: relative;">
                    <button class="notification-btn" id="notificationBtn" onclick="toggleNotificationDropdown()">
                        <i class="fas fa-bell"></i>
                        <?php if ($has_unread_notifications): ?>
                        <span class="notification-badge"></span>
                        <?php endif; ?>
                    </button>
                    
                    <!-- Notification Dropdown -->
                    <div class="notification-dropdown" id="notificationDropdown" style="display: none;">
                        <div class="notification-header">
                            <h6 style="margin: 0; font-weight: 600; color: #1f2937;">Notifications</h6>
                            <?php if (count($notifications) > 0): ?>
                            <a href="?mark_read=all" style="font-size: 12px; color: #9333EA; text-decoration: none;">Mark all as read</a>
                            <?php endif; ?>
                        </div>
                        <div class="notification-list">
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach ($notifications as $index => $notification): ?>
                                <div class="notification-item <?php echo $notification['read'] ? 'read' : 'unread'; ?> notification-<?php echo $notification['type']; ?>"
                                     onclick="window.location.href='?mark_read=1&notif_id=<?php echo htmlspecialchars($notification['id']); ?>'"
                                     style="cursor: pointer;">
                                    <div class="notification-icon">
                                        <i class="fas fa-<?php echo $notification['icon']; ?>"></i>
                                    </div>
                                    <div class="notification-content">
                                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                                        <div class="notification-time"><?php echo htmlspecialchars($notification['time']); ?></div>
                                    </div>
                                    <?php if (!$notification['read']): ?>
                                    <a href="?mark_read=1&notif_id=<?php echo htmlspecialchars($notification['id']); ?>" 
                                       onclick="event.stopPropagation();"
                                       class="notification-mark-read" 
                                       title="Mark as read">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notification-empty">
                                    <i class="fas fa-bell-slash" style="font-size: 32px; color: #d1d5db; margin-bottom: 10px;"></i>
                                    <p style="color: #6b7280; margin: 0;">No notifications</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (count($notifications) > 0): ?>
                        <div class="notification-footer">
                            <a href="halal-certification.php" style="color: #9333EA; text-decoration: none; font-size: 14px; font-weight: 500;">
                                View Certification Status <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo htmlspecialchars($company_name); ?></div>
                        <div class="stat-label">Company Name</div>
                    </div>
                    <div class="stat-icon gradient-purple">
                        <i class="fas fa-building"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $total_users_row['count'] ?? '0'; ?></div>
                        <div class="stat-label">Company Users</div>
                    </div>
                    <div class="stat-icon gradient-blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo $is_halal_certified ? 'Yes' : 'Pending'; ?></div>
                        <div class="stat-label">Halal Status</div>
                    </div>
                    <div class="stat-icon <?php echo $is_halal_certified ? 'gradient-green' : 'gradient-orange'; ?>">
                        <i class="fas fa-<?php echo $is_halal_certified ? 'check-circle' : 'clock'; ?>"></i>
                    </div>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-card-header">
                    <div>
                        <div class="stat-value"><?php echo htmlspecialchars($company_type); ?></div>
                        <div class="stat-label">Company Type</div>
                    </div>
                    <div class="stat-icon gradient-purple">
                        <i class="fas fa-tag"></i>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Content Grid -->
        <div class="content-grid">
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Quick Actions</h3>
                </div>
                <div class="d-flex flex-column gap-3">
                    <a href="halal-certification.php" class="btn btn-lg" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); color: white; border: none; border-radius: 12px; padding: 15px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                        <i class="fas fa-certificate me-2"></i>Manage Halal Certification
                    </a>
                    <a href="cms.php" class="btn btn-lg" style="background: #f3f4f6; color: #1f2937; border: none; border-radius: 12px; padding: 15px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                        <i class="fas fa-file-alt me-2"></i>Content Management
                    </a>
                    <a href="company-users.php" class="btn btn-lg" style="background: #f3f4f6; color: #1f2937; border: none; border-radius: 12px; padding: 15px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                        <i class="fas fa-users me-2"></i>Manage Company Users
                    </a>
                    <a href="profile.php" class="btn btn-lg" style="background: #f3f4f6; color: #1f2937; border: none; border-radius: 12px; padding: 15px; text-decoration: none; font-weight: 600; transition: all 0.3s ease;">
                        <i class="fas fa-user-circle me-2"></i>Edit Company Profile
                    </a>
                </div>
            </div>
            
            <div class="content-card">
                <div class="card-header">
                    <h3 class="card-title">Company Information</h3>
                </div>
                <div class="d-flex flex-column gap-3">
                    <div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Company Name</div>
                        <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($company_name); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Company Type</div>
                        <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($company_type); ?></div>
                    </div>
                    <div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Status</div>
                        <div>
                            <span style="padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; background: <?php echo $is_halal_certified ? '#d1fae5' : '#fef3c7'; ?>; color: <?php echo $is_halal_certified ? '#065f46' : '#92400e'; ?>;">
                                <?php echo htmlspecialchars($cert_status); ?>
                            </span>
                        </div>
                    </div>
                    <?php if (!empty($company_row['email'])): ?>
                    <div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Email</div>
                        <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($company_row['email']); ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($company_row['contant_no'])): ?>
                    <div>
                        <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Contact</div>
                        <div style="font-weight: 600; color: #1f2937;"><?php echo htmlspecialchars($company_row['contant_no']); ?></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Toggle notification dropdown
        function toggleNotificationDropdown() {
            const dropdown = document.getElementById('notificationDropdown');
            if (dropdown.style.display === 'none' || dropdown.style.display === '') {
                dropdown.style.display = 'block';
            } else {
                dropdown.style.display = 'none';
            }
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(event) {
            const notificationWrapper = document.querySelector('.notification-wrapper');
            const dropdown = document.getElementById('notificationDropdown');
            const btn = document.getElementById('notificationBtn');
            
            if (!notificationWrapper.contains(event.target)) {
                dropdown.style.display = 'none';
            }
        });
        
        // Auto-hide success notifications after 5 seconds (if approved)
        <?php if ($cert_status == 'Halal-Certified' || $cert_status == '4' || $cert_status == 4): ?>
        setTimeout(function() {
            const approvedNotif = document.querySelector('.notification-success');
            if (approvedNotif && !approvedNotif.classList.contains('read')) {
                // Could auto-mark as read or just remove highlight
            }
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>

