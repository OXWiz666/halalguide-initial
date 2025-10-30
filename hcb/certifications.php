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

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$company_filter = isset($_GET['company_id']) ? mysqli_real_escape_string($conn, $_GET['company_id']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query for approved applications (certifications)
$where_clause = "ca.organization_id = '$organization_id' AND ca.current_status = 'Approved'";

if (!empty($company_filter)) {
    $where_clause .= " AND ca.company_id = '$company_filter'";
}
if (!empty($search)) {
    $where_clause .= " AND (c.company_name LIKE '%$search%' OR ca.certificate_number LIKE '%$search%' OR ca.application_number LIKE '%$search%')";
}

// Filter by expiry status
if ($status_filter == 'active') {
    $where_clause .= " AND (ca.certificate_expiry_date IS NULL OR ca.certificate_expiry_date > NOW())";
} elseif ($status_filter == 'expiring') {
    $where_clause .= " AND ca.certificate_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";
} elseif ($status_filter == 'expired') {
    $where_clause .= " AND ca.certificate_expiry_date < NOW()";
}

// Get certifications
$certifications_query = "SELECT 
    ca.*,
    c.company_name,
    c.email as company_email,
    c.contant_no as company_contact,
    c.usertype_id,
    ut.usertype,
    a.other as address_line,
    b.brgyDesc,
    cm.citymunDesc,
    p.provDesc,
    CASE 
        WHEN a.other IS NOT NULL AND b.brgyDesc IS NOT NULL AND cm.citymunDesc IS NOT NULL AND p.provDesc IS NOT NULL
        THEN CONCAT(COALESCE(a.other, ''), ', ', COALESCE(b.brgyDesc, ''), ', ', COALESCE(cm.citymunDesc, ''), ', ', COALESCE(p.provDesc, ''))
        ELSE COALESCE(a.other, 'Address not specified')
    END as full_address,
    CASE
        WHEN ca.certificate_expiry_date IS NULL THEN 'No Expiry'
        WHEN ca.certificate_expiry_date < NOW() THEN 'Expired'
        WHEN ca.certificate_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 'Expiring Soon'
        ELSE 'Active'
    END as expiry_status
FROM tbl_certification_application ca
LEFT JOIN tbl_company c ON ca.company_id = c.company_id
LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode
WHERE $where_clause
ORDER BY ca.certificate_issue_date DESC, ca.date_added DESC";

$certifications_result = mysqli_query($conn, $certifications_query);
$certifications = [];

if ($certifications_result) {
    while ($row = mysqli_fetch_assoc($certifications_result)) {
        $certifications[] = $row;
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_certifications,
    COUNT(CASE WHEN ca.certificate_expiry_date > NOW() OR ca.certificate_expiry_date IS NULL THEN 1 END) as active_certifications,
    COUNT(CASE WHEN ca.certificate_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 END) as expiring_soon,
    COUNT(CASE WHEN ca.certificate_expiry_date < NOW() THEN 1 END) as expired_certifications
FROM tbl_certification_application ca
WHERE ca.organization_id = '$organization_id' AND ca.current_status = 'Approved'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifications | HCB Portal</title>
    
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
        
        .certification-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
            border-left: 4px solid #48bb78;
            position: relative;
        }
        
        .certification-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .certification-card.expiring {
            border-left-color: #ed8936;
        }
        
        .certification-card.expired {
            border-left-color: #f56565;
        }
        
        .certificate-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .expiry-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .expiry-badge.active { background: #c6f6d5; color: #48bb78; }
        .expiry-badge.expiring { background: #feebc8; color: #ed8936; }
        .expiry-badge.expired { background: #fed7d7; color: #f56565; }
        
        .filter-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        
        .filter-tab {
            padding: 10px 20px;
            border-radius: 8px;
            background: white;
            border: 2px solid #e2e8f0;
            text-decoration: none;
            color: #4a5568;
            font-weight: 500;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
        }
        
        .filter-tab.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-color: transparent;
        }
        
        .filter-tab .badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
        }
        
        .filter-tab:not(.active) .badge {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
            border-left: 4px solid #667eea;
        }
        
        .stat-card.success { border-left-color: #48bb78; }
        .stat-card.warning { border-left-color: #ed8936; }
        .stat-card.danger { border-left-color: #f56565; }
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 5px;
        }
        
        .stat-card.success .stat-value { color: #48bb78; }
        .stat-card.warning .stat-value { color: #ed8936; }
        .stat-card.danger .stat-value { color: #f56565; }
        
        .stat-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h2>Certifications</h2>
                <p>Manage issued halal certifications</p>
            </div>
            
            <div class="user-menu d-flex align-items-center" style="gap: 10px;">
                <a class="btn btn-sm btn-primary no-print"
                   href="print-certifications.php?status=<?php echo urlencode($status_filter); ?>&search=<?php echo urlencode($search); ?>"
                   target="_blank" rel="noopener">
                    <i class="fas fa-print"></i> Print
                </a>
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
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_certifications'] ?? 0; ?></div>
                <div class="stat-label">Total Certifications</div>
            </div>
            <div class="stat-card success">
                <div class="stat-value"><?php echo $stats['active_certifications'] ?? 0; ?></div>
                <div class="stat-label">Active Certifications</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-value"><?php echo $stats['expiring_soon'] ?? 0; ?></div>
                <div class="stat-label">Expiring Soon (30 days)</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-value"><?php echo $stats['expired_certifications'] ?? 0; ?></div>
                <div class="stat-label">Expired Certifications</div>
            </div>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="certifications.php" class="filter-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                All Certifications
                <span class="badge"><?php echo count($certifications); ?></span>
            </a>
            <a href="certifications.php?status=active" class="filter-tab <?php echo $status_filter == 'active' ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                Active
                <span class="badge"><?php 
                    $active_count = 0;
                    foreach ($certifications as $cert) {
                        if ($cert['expiry_status'] == 'Active' || $cert['expiry_status'] == 'No Expiry') $active_count++;
                    }
                    echo $active_count;
                ?></span>
            </a>
            <a href="certifications.php?status=expiring" class="filter-tab <?php echo $status_filter == 'expiring' ? 'active' : ''; ?>">
                <i class="fas fa-exclamation-triangle"></i>
                Expiring Soon
                <span class="badge"><?php echo $stats['expiring_soon'] ?? 0; ?></span>
            </a>
            <a href="certifications.php?status=expired" class="filter-tab <?php echo $status_filter == 'expired' ? 'active' : ''; ?>">
                <i class="fas fa-times-circle"></i>
                Expired
                <span class="badge"><?php echo $stats['expired_certifications'] ?? 0; ?></span>
            </a>
        </div>
        
        <!-- Search -->
        <div class="content-card mb-3">
            <form method="GET" class="row g-3">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <div class="col-md-10">
                    <input type="text" name="search" class="form-control" placeholder="Search by company name, certificate number, or application number..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary w-100">Search</button>
                </div>
            </form>
        </div>
        
        <!-- Certifications List -->
        <div class="content-card" id="hcbCertsPrint">
            <h5 class="mb-4">Certifications (<?php echo count($certifications); ?>)</h5>
            
            <?php if (empty($certifications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-certificate fa-3x text-muted mb-3"></i>
                <p class="text-muted">No certifications found.</p>
            </div>
            <?php else: ?>
            
            <?php foreach ($certifications as $cert): ?>
            <div class="certification-card <?php 
                echo $cert['expiry_status'] == 'Expired' ? 'expired' : 
                    ($cert['expiry_status'] == 'Expiring Soon' ? 'expiring' : ''); 
            ?>">
                <div class="d-flex justify-content-between align-items-start mb-3">
                    <div class="flex-grow-1">
                        <div class="mb-2">
                            <span class="certificate-badge">
                                <i class="fas fa-certificate"></i>
                                <?php echo htmlspecialchars($cert['certificate_number'] ?? $cert['application_number']); ?>
                            </span>
                        </div>
                        <h5 class="mb-1"><?php echo htmlspecialchars($cert['company_name']); ?></h5>
                        <p class="text-muted mb-2 small">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($cert['usertype'] ?? 'Unknown'); ?>
                            <span class="mx-2">•</span>
                            <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($cert['full_address']); ?>
                        </p>
                    </div>
                    <div class="text-end">
                        <span class="expiry-badge <?php echo strtolower(str_replace(' ', '-', $cert['expiry_status'])); ?>">
                            <?php if ($cert['expiry_status'] == 'Expired'): ?>
                                <i class="fas fa-times-circle"></i>
                            <?php elseif ($cert['expiry_status'] == 'Expiring Soon'): ?>
                                <i class="fas fa-exclamation-triangle"></i>
                            <?php else: ?>
                                <i class="fas fa-check-circle"></i>
                            <?php endif; ?>
                            <?php echo $cert['expiry_status']; ?>
                        </span>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-3">
                        <small class="text-muted d-block">Application Type</small>
                        <p class="mb-0"><strong><?php echo htmlspecialchars($cert['application_type'] ?? 'New'); ?></strong></p>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Issue Date</small>
                        <p class="mb-0">
                            <i class="fas fa-calendar-check text-muted"></i> 
                            <?php echo $cert['certificate_issue_date'] ? date('M d, Y', strtotime($cert['certificate_issue_date'])) : date('M d, Y', strtotime($cert['approved_date'])); ?>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Expiry Date</small>
                        <p class="mb-0">
                            <i class="fas fa-calendar-times text-muted"></i> 
                            <?php echo $cert['certificate_expiry_date'] ? date('M d, Y', strtotime($cert['certificate_expiry_date'])) : 'No Expiry'; ?>
                        </p>
                    </div>
                    <div class="col-md-3">
                        <small class="text-muted d-block">Application Number</small>
                        <p class="mb-0">
                            <i class="fas fa-hashtag text-muted"></i> 
                            <?php echo htmlspecialchars($cert['application_number']); ?>
                        </p>
                    </div>
                </div>
                
                <div class="row mb-3">
                    <div class="col-md-6">
                        <small class="text-muted d-block">Contact Information</small>
                        <p class="mb-1">
                            <i class="fas fa-envelope text-muted"></i> 
                            <?php echo htmlspecialchars($cert['company_email'] ?? 'N/A'); ?>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-phone text-muted"></i> 
                            <?php echo htmlspecialchars($cert['company_contact'] ?? 'N/A'); ?>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted d-block">Review Information</small>
                        <p class="mb-1">
                            <i class="fas fa-user-check text-muted"></i> 
                            Approved by: <strong>Admin</strong>
                        </p>
                        <p class="mb-0">
                            <i class="fas fa-calendar text-muted"></i> 
                            Approved: <?php echo $cert['approved_date'] ? date('M d, Y g:i A', strtotime($cert['approved_date'])) : 'N/A'; ?>
                        </p>
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <a href="application-details.php?id=<?php echo $cert['application_id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> View Details
                    </a>
                    <a href="companies.php?company_id=<?php echo $cert['company_id']; ?>" class="btn btn-sm btn-outline-primary">
                        <i class="fas fa-building"></i> View Company
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

