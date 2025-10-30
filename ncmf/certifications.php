<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

// Check login and access
check_login();
check_access('Super Admin');

$superadmin_id = $_SESSION['superadmin_id'] ?? $_SESSION['user_id'];
$superadmin_name = $_SESSION['superadmin_name'] ?? 'Super Admin';

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$organization_filter = isset($_GET['organization_id']) ? mysqli_real_escape_string($conn, $_GET['organization_id']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query for ALL approved certifications across ALL organizations
$where_clause = "ca.current_status = 'Approved'";

if (!empty($organization_filter)) {
    $where_clause .= " AND ca.organization_id = '$organization_filter'";
}
if (!empty($search)) {
    $where_clause .= " AND (c.company_name LIKE '%$search%' OR ca.certificate_number LIKE '%$search%' OR ca.application_number LIKE '%$search%' OR o.organization_name LIKE '%$search%')";
}

// Filter by expiry status
if ($status_filter == 'active') {
    $where_clause .= " AND (ca.certificate_expiry_date IS NULL OR ca.certificate_expiry_date > NOW())";
} elseif ($status_filter == 'expiring') {
    $where_clause .= " AND ca.certificate_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";
} elseif ($status_filter == 'expired') {
    $where_clause .= " AND ca.certificate_expiry_date < NOW()";
}

// Join address tables using the correct structure
// Address -> Barangay -> City/Municipality -> Province
$address_joins = "
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode";

// Get all certifications
$certifications_query = "SELECT 
    ca.*,
    c.company_name,
    c.email as company_email,
    c.contant_no as company_contact,
    c.usertype_id,
    ut.usertype,
    o.organization_name,
    o.organization_id,
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
LEFT JOIN tbl_organization o ON ca.organization_id = o.organization_id
LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
$address_joins
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
    COUNT(CASE WHEN ca.certificate_expiry_date < NOW() THEN 1 END) as expired_certifications,
    COUNT(DISTINCT ca.organization_id) as total_organizations
FROM tbl_certification_application ca
WHERE ca.current_status = 'Approved'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get all organizations for filter dropdown
$orgs_query = "SELECT organization_id, organization_name FROM tbl_organization WHERE status_id = (SELECT status_id FROM tbl_status WHERE status = 'Active' LIMIT 1) ORDER BY organization_name";
$orgs_result = mysqli_query($conn, $orgs_query);
$organizations = [];
if ($orgs_result) {
    while ($row = mysqli_fetch_assoc($orgs_result)) {
        $organizations[] = $row;
    }
}

$current_page = 'certifications.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifications | Super Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        .certificate-badge {
            display: inline-block;
            padding: 4px 10px;
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
            color: white;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .expiring-badge {
            background: linear-gradient(135deg, #ff9800 0%, #f57c00 100%);
        }
        
        .expired-badge {
            background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%);
        }
        
        .active-badge {
            background: linear-gradient(135deg, #4caf50 0%, #45a049 100%);
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .cert-info {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .org-name {
            color: #667eea;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h2>All Certifications</h2>
                <p>View all halal certifications across all certifying bodies</p>
            </div>
        </div>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-certificate text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['total_certifications'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Total Certifications</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #48bb78 0%, #38a169 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-check-circle text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['active_certifications'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Active Certifications</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-exclamation-triangle text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['expiring_soon'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Expiring Soon</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #f44336 0%, #d32f2f 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-times-circle text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['expired_certifications'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Expired</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filters -->
        <div class="content-card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by company name, certificate number, or organization..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Certifying Body</label>
                    <select name="organization_id" class="form-control">
                        <option value="">All Organizations</option>
                        <?php foreach ($organizations as $org): ?>
                            <option value="<?php echo $org['organization_id']; ?>" <?php echo ($organization_filter == $org['organization_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($org['organization_name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="expiring" <?php echo ($status_filter == 'expiring') ? 'selected' : ''; ?>>Expiring Soon</option>
                        <option value="expired" <?php echo ($status_filter == 'expired') ? 'selected' : ''; ?>>Expired</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Filter
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Certifications Table -->
        <div class="content-card">
            <h3 class="mb-4" style="font-size: 20px; font-weight: 700; color: #1a202c;">
                Certifications List
                <span class="badge bg-secondary" style="font-size: 14px;"><?php echo count($certifications); ?> Result(s)</span>
            </h3>
            
            <?php if (empty($certifications)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-certificate fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No certifications found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Certificate Number</th>
                                <th>Company</th>
                                <th>Type</th>
                                <th>Certifying Body</th>
                                <th>Issue Date</th>
                                <th>Expiry Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($certifications as $cert): ?>
                                <tr>
                                    <td>
                                        <div class="cert-info">
                                            <i class="fas fa-certificate" style="color: #4caf50;"></i>
                                            <strong><?php echo htmlspecialchars($cert['certificate_number'] ?? $cert['application_number']); ?></strong>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($cert['company_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($cert['full_address']); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($cert['usertype']); ?></span>
                                    </td>
                                    <td>
                                        <span class="org-name"><?php echo htmlspecialchars($cert['organization_name'] ?? 'N/A'); ?></span>
                                    </td>
                                    <td>
                                        <?php echo $cert['certificate_issue_date'] ? date('M d, Y', strtotime($cert['certificate_issue_date'])) : ($cert['approved_date'] ? date('M d, Y', strtotime($cert['approved_date'])) : 'N/A'); ?>
                                    </td>
                                    <td>
                                        <?php echo $cert['certificate_expiry_date'] ? date('M d, Y', strtotime($cert['certificate_expiry_date'])) : '<span class="text-muted">No Expiry</span>'; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $expiry_status = $cert['expiry_status'];
                                        $badge_class = 'active-badge';
                                        if ($expiry_status == 'Expiring Soon') $badge_class = 'expiring-badge';
                                        if ($expiry_status == 'Expired') $badge_class = 'expired-badge';
                                        ?>
                                        <span class="certificate-badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($expiry_status); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</body>
</html>

