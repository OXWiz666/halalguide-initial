<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

// Check login and access
check_login();
check_access('Admin');

$admin_id = $_SESSION['admin_id'];
$organization_id = $_SESSION['organization_id'];

// Get organization info
$org_query = mysqli_query($conn, "SELECT * FROM tbl_organization WHERE organization_id = '$organization_id'");
$org_row = mysqli_fetch_assoc($org_query);
$organization_name = $org_row['organization_name'] ?? 'Certifying Body';

// Get date range filter
$date_from = isset($_GET['date_from']) ? mysqli_real_escape_string($conn, $_GET['date_from']) : date('Y-m-01'); // First day of current month
$date_to = isset($_GET['date_to']) ? mysqli_real_escape_string($conn, $_GET['date_to']) : date('Y-m-d'); // Today

// Overall Statistics - Optimized with separate queries
// Total companies
$total_companies_query = "SELECT COUNT(*) as total_companies FROM tbl_company";
$total_companies_result = mysqli_query($conn, $total_companies_query);
$total_companies = mysqli_fetch_assoc($total_companies_result)['total_companies'] ?? 0;

// Certified companies
$certified_companies_query = "SELECT COUNT(*) as certified_companies FROM tbl_company WHERE status_id = 4";
$certified_companies_result = mysqli_query($conn, $certified_companies_query);
$certified_companies = mysqli_fetch_assoc($certified_companies_result)['certified_companies'] ?? 0;

// Application statistics (single optimized query)
$app_stats_query = "SELECT 
    COUNT(*) as total_applications,
    SUM(CASE WHEN current_status = 'Approved' THEN 1 ELSE 0 END) as approved_applications,
    SUM(CASE WHEN current_status = 'Rejected' THEN 1 ELSE 0 END) as rejected_applications,
    SUM(CASE WHEN current_status = 'Under Review' THEN 1 ELSE 0 END) as pending_applications,
    SUM(CASE WHEN current_status = 'Approved' AND (certificate_expiry_date IS NULL OR certificate_expiry_date > NOW()) THEN 1 ELSE 0 END) as active_certifications,
    SUM(CASE WHEN current_status = 'Approved' AND certificate_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon,
    SUM(CASE WHEN current_status = 'Approved' AND certificate_expiry_date < NOW() THEN 1 ELSE 0 END) as expired_certifications
FROM tbl_certification_application
WHERE organization_id = '$organization_id'";
$app_stats_result = mysqli_query($conn, $app_stats_query);
$app_stats = mysqli_fetch_assoc($app_stats_result);

// Combine results
$overall_stats = [
    'total_companies' => $total_companies,
    'certified_companies' => $certified_companies,
    'total_applications' => $app_stats['total_applications'] ?? 0,
    'approved_applications' => $app_stats['approved_applications'] ?? 0,
    'rejected_applications' => $app_stats['rejected_applications'] ?? 0,
    'pending_applications' => $app_stats['pending_applications'] ?? 0,
    'active_certifications' => $app_stats['active_certifications'] ?? 0,
    'expiring_soon' => $app_stats['expiring_soon'] ?? 0,
    'expired_certifications' => $app_stats['expired_certifications'] ?? 0
];

// Application Status Breakdown
$status_breakdown_query = "SELECT 
    current_status,
    COUNT(*) as count
FROM tbl_certification_application
WHERE organization_id = '$organization_id'
GROUP BY current_status
ORDER BY count DESC";
$status_breakdown_result = mysqli_query($conn, $status_breakdown_query);
$status_breakdown = [];
while ($row = mysqli_fetch_assoc($status_breakdown_result)) {
    $status_breakdown[$row['current_status']] = $row['count'];
}

// Applications by Month (Last 12 months)
$monthly_applications_query = "SELECT 
    DATE_FORMAT(submitted_date, '%Y-%m') as month,
    COUNT(*) as count
FROM tbl_certification_application
WHERE organization_id = '$organization_id'
    AND submitted_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
GROUP BY DATE_FORMAT(submitted_date, '%Y-%m')
ORDER BY month ASC";
$monthly_applications_result = mysqli_query($conn, $monthly_applications_query);
$monthly_applications = [];
$monthly_labels = [];
$monthly_data = [];
while ($row = mysqli_fetch_assoc($monthly_applications_result)) {
    $monthly_applications[$row['month']] = $row['count'];
    $monthly_labels[] = date('M Y', strtotime($row['month'] . '-01'));
    $monthly_data[] = $row['count'];
}

// Applications by Type
$type_breakdown_query = "SELECT 
    ca.application_type,
    COUNT(*) as count
FROM tbl_certification_application ca
WHERE ca.organization_id = '$organization_id'
GROUP BY ca.application_type
ORDER BY count DESC";
$type_breakdown_result = mysqli_query($conn, $type_breakdown_query);
$type_breakdown = [];
while ($row = mysqli_fetch_assoc($type_breakdown_result)) {
    $type_breakdown[$row['application_type']] = $row['count'];
}

// Companies by Type - Optimized with JOIN instead of EXISTS
$company_type_query = "SELECT 
    ut.usertype,
    COUNT(DISTINCT c.company_id) as count
FROM tbl_company c
INNER JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
INNER JOIN tbl_certification_application ca ON c.company_id = ca.company_id AND ca.organization_id = '$organization_id'
GROUP BY ut.usertype
ORDER BY count DESC";
$company_type_result = mysqli_query($conn, $company_type_query);
$company_types = [];
while ($row = mysqli_fetch_assoc($company_type_result)) {
    $company_types[$row['usertype']] = $row['count'];
}

// Recent Activity (Last 30 days) - Optimized with UNION and simpler date check
$date_threshold = date('Y-m-d H:i:s', strtotime('-30 days'));
$recent_activity_query = "SELECT 
    ca.*,
    c.company_name,
    CASE
        WHEN ca.current_status = 'Approved' THEN CONCAT('Application approved for ', c.company_name)
        WHEN ca.current_status = 'Rejected' THEN CONCAT('Application rejected for ', c.company_name)
        WHEN ca.current_status = 'Under Review' THEN CONCAT('Application under review for ', c.company_name)
        ELSE CONCAT('Application status changed for ', c.company_name)
    END as activity_description,
    COALESCE(
        NULLIF(ca.approved_date, '0000-00-00 00:00:00'),
        NULLIF(ca.rejected_date, '0000-00-00 00:00:00'),
        NULLIF(ca.reviewed_date, '0000-00-00 00:00:00'),
        NULLIF(ca.date_updated, '0000-00-00 00:00:00'),
        ca.submitted_date
    ) as activity_date
FROM tbl_certification_application ca
LEFT JOIN tbl_company c ON ca.company_id = c.company_id
WHERE ca.organization_id = '$organization_id'
    AND (
        (ca.approved_date IS NOT NULL AND ca.approved_date >= '$date_threshold') OR
        (ca.rejected_date IS NOT NULL AND ca.rejected_date >= '$date_threshold') OR
        (ca.reviewed_date IS NOT NULL AND ca.reviewed_date >= '$date_threshold') OR
        (ca.date_updated IS NOT NULL AND ca.date_updated >= '$date_threshold') OR
        (ca.submitted_date >= '$date_threshold')
    )
ORDER BY activity_date DESC
LIMIT 10";
$recent_activity_result = mysqli_query($conn, $recent_activity_query);
$recent_activities = [];
if ($recent_activity_result) {
    while ($row = mysqli_fetch_assoc($recent_activity_result)) {
        $recent_activities[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics | HCB Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    
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
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #667eea;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
        }
        
        .stat-card.primary { border-left-color: #667eea; }
        .stat-card.success { border-left-color: #48bb78; }
        .stat-card.warning { border-left-color: #ed8936; }
        .stat-card.danger { border-left-color: #f56565; }
        .stat-card.info { border-left-color: #4299e1; }
        
        .stat-icon {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            margin-bottom: 12px;
        }
        
        .stat-card.primary .stat-icon { background: rgba(102, 126, 234, 0.1); color: #667eea; }
        .stat-card.success .stat-icon { background: rgba(72, 187, 120, 0.1); color: #48bb78; }
        .stat-card.warning .stat-icon { background: rgba(237, 137, 54, 0.1); color: #ed8936; }
        .stat-card.danger .stat-icon { background: rgba(245, 101, 101, 0.1); color: #f56565; }
        .stat-card.info .stat-icon { background: rgba(66, 153, 225, 0.1); color: #4299e1; }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 4px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
        }
        
        .chart-container {
            position: relative;
            height: 250px;
            margin-bottom: 20px;
        }
        
        .chart-small {
            position: relative;
            height: 200px !important;
            margin-bottom: 15px;
        }
        
        canvas {
            max-height: 100%;
        }
        
        .activity-item {
            padding: 15px;
            border-left: 3px solid #e2e8f0;
            margin-bottom: 10px;
            background: white;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            border-left-color: #667eea;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        }
        
        .activity-item.approved { border-left-color: #48bb78; }
        .activity-item.rejected { border-left-color: #f56565; }
        .activity-item.review { border-left-color: #ed8936; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h2>Reports & Analytics</h2>
                <p>Comprehensive insights into your certification operations</p>
            </div>
            
            <div class="user-menu">
                <div class="user-dropdown" style="display: flex; align-items: center; gap: 12px; padding: 8px 15px; border-radius: 10px; background: #f7fafc;">
                    <div class="user-avatar" style="width: 38px; height: 38px; border-radius: 10px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 14px;">
                        <?php echo strtoupper(substr($organization_name, 0, 2)); ?>
                    </div>
                    <div class="user-info">
                        <h6 style="font-size: 14px; font-weight: 600; margin: 0; color: #2d3748;"><?php echo htmlspecialchars($organization_name); ?></h6>
                        <p style="font-size: 12px; color: #a0aec0; margin: 0;">Admin</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Overall Statistics -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-building"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['total_companies'] ?? 0; ?></div>
                <div class="stat-label">Total Companies</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-certificate"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['certified_companies'] ?? 0; ?></div>
                <div class="stat-label">Certified Companies</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['total_applications'] ?? 0; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['approved_applications'] ?? 0; ?></div>
                <div class="stat-label">Approved Applications</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-icon">
                    <i class="fas fa-times-circle"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['rejected_applications'] ?? 0; ?></div>
                <div class="stat-label">Rejected Applications</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-value"><?php echo $overall_stats['pending_applications'] ?? 0; ?></div>
                <div class="stat-label">Pending Applications</div>
            </div>
        </div>
        
        <!-- Charts Row 1: Status and Type -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="mb-3">Applications by Status</h5>
                    <div class="chart-small">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="mb-3">Applications by Type</h5>
                    <div class="chart-small">
                        <canvas id="typeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row 2: Monthly Trends and Company Types -->
        <div class="row mb-3">
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="mb-3">Application Trends (Last 12 Months)</h5>
                    <div class="chart-small">
                        <canvas id="monthlyChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="content-card">
                    <h5 class="mb-3">Companies by Type</h5>
                    <div class="chart-small">
                        <canvas id="companyTypeChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Recent Activity -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="content-card">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="mb-0">Recent Activity</h5>
                        <a href="applications.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    
                    <?php if (empty($recent_activities)): ?>
                    <p class="text-muted text-center py-4">No recent activity</p>
                    <?php else: ?>
                    <div style="max-height: 300px; overflow-y: auto;">
                        <?php foreach ($recent_activities as $activity): ?>
                        <div class="activity-item <?php 
                            echo $activity['current_status'] == 'Approved' ? 'approved' : 
                                ($activity['current_status'] == 'Rejected' ? 'rejected' : 'review'); 
                        ?>">
                            <p class="mb-1"><strong><?php echo htmlspecialchars($activity['activity_description']); ?></strong></p>
                            <p class="mb-1 small text-muted">
                                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($activity['application_number']); ?>
                                <span class="mx-2">•</span>
                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y g:i A', strtotime($activity['activity_date'])); ?>
                            </p>
                            <span class="badge bg-<?php 
                                echo $activity['current_status'] == 'Approved' ? 'success' : 
                                    ($activity['current_status'] == 'Rejected' ? 'danger' : 'warning'); 
                            ?>">
                                <?php echo htmlspecialchars($activity['current_status']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Status Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($status_breakdown)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($status_breakdown)); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#48bb78',
                        '#ed8936',
                        '#4299e1',
                        '#f56565',
                        '#a0aec0'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Type Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($type_breakdown)); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_values($type_breakdown)); ?>,
                    backgroundColor: '#667eea'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Monthly Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($monthly_labels); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode($monthly_data); ?>,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Company Type Chart
        const companyTypeCtx = document.getElementById('companyTypeChart').getContext('2d');
        new Chart(companyTypeCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($company_types)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($company_types)); ?>,
                    backgroundColor: [
                        '#667eea',
                        '#48bb78',
                        '#ed8936',
                        '#4299e1'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html>

