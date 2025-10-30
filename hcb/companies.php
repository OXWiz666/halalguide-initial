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
$usertype_filter = isset($_GET['usertype']) ? mysqli_real_escape_string($conn, $_GET['usertype']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Pagination
$per_page = 20; // Items per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $per_page;

// Build query
$where_clause = "1=1";
if (!empty($status_filter)) {
    $where_clause .= " AND c.status_id = '$status_filter'";
}
if (!empty($usertype_filter)) {
    $where_clause .= " AND c.usertype_id = '$usertype_filter'";
}
if (!empty($search)) {
    $where_clause .= " AND (c.company_name LIKE '%$search%' OR c.company_description LIKE '%$search%' OR c.email LIKE '%$search%')";
}

// Get total count for pagination
$count_query = "SELECT COUNT(DISTINCT c.company_id) as total
FROM tbl_company c
LEFT JOIN tbl_status s ON c.status_id = s.status_id
LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode
LEFT JOIN refregion r ON p.regCode = r.regCode
LEFT JOIN tbl_certification_application ca ON c.company_id = ca.company_id AND ca.organization_id = '$organization_id'
WHERE $where_clause";

$count_result = mysqli_query($conn, $count_query);
$total_companies = 0;
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_companies = $count_row['total'];
}

$total_pages = ceil($total_companies / $per_page);

// Get companies with applications (if any) - with pagination
$companies_query = "SELECT 
    c.*,
    s.status,
    ut.usertype,
    a.other as address_line,
    b.brgyDesc,
    cm.citymunDesc,
    p.provDesc,
    r.regDesc,
    COUNT(DISTINCT ca.application_id) as total_applications,
    COUNT(DISTINCT CASE WHEN ca.current_status = 'Approved' THEN ca.application_id END) as approved_applications,
    MAX(CASE WHEN ca.current_status = 'Approved' THEN ca.certificate_expiry_date END) as latest_expiry_date,
    CASE 
        WHEN a.other IS NOT NULL AND b.brgyDesc IS NOT NULL AND cm.citymunDesc IS NOT NULL AND p.provDesc IS NOT NULL
        THEN CONCAT(COALESCE(a.other, ''), ', ', COALESCE(b.brgyDesc, ''), ', ', COALESCE(cm.citymunDesc, ''), ', ', COALESCE(p.provDesc, ''))
        ELSE COALESCE(a.other, 'Address not specified')
    END as full_address
FROM tbl_company c
LEFT JOIN tbl_status s ON c.status_id = s.status_id
LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode
LEFT JOIN refregion r ON p.regCode = r.regCode
LEFT JOIN tbl_certification_application ca ON c.company_id = ca.company_id AND ca.organization_id = '$organization_id'
WHERE $where_clause
GROUP BY c.company_id
ORDER BY c.date_added DESC
LIMIT $per_page OFFSET $offset";

$companies_result = mysqli_query($conn, $companies_query);
$companies = [];

if ($companies_result) {
    while ($row = mysqli_fetch_assoc($companies_result)) {
        $companies[] = $row;
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(DISTINCT c.company_id) as total_companies,
    COUNT(DISTINCT CASE WHEN c.status_id = 4 THEN c.company_id END) as certified_companies,
    COUNT(DISTINCT CASE WHEN c.status_id = 1 THEN c.company_id END) as active_companies,
    COUNT(DISTINCT CASE WHEN ca.current_status = 'Approved' AND ca.certificate_expiry_date < DATE_ADD(NOW(), INTERVAL 30 DAY) 
        AND ca.certificate_expiry_date > NOW() THEN c.company_id END) as expiring_soon
FROM tbl_company c
LEFT JOIN tbl_certification_application ca ON c.company_id = ca.company_id AND ca.organization_id = '$organization_id'";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get status counts for filters
$status_counts_query = "SELECT 
    c.status_id,
    s.status,
    COUNT(*) as count
FROM tbl_company c
LEFT JOIN tbl_status s ON c.status_id = s.status_id
GROUP BY c.status_id, s.status";
$status_counts_result = mysqli_query($conn, $status_counts_query);
$status_counts = [];
while ($row = mysqli_fetch_assoc($status_counts_result)) {
    $status_counts[$row['status_id']] = ['status' => $row['status'], 'count' => $row['count']];
}

// Usertype names
$usertype_names = [
    3 => 'Establishment',
    4 => 'Accommodation',
    5 => 'Tourist Spot',
    6 => 'Prayer Facility'
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Companies | HCB Portal</title>
    
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
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.active { background: #c6f6d5; color: #48bb78; }
        .status-badge.inactive { background: #fed7d7; color: #f56565; }
        .status-badge.certified { background: #e0e7ff; color: #667eea; }
        
        .companies-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .companies-table table {
            margin: 0;
        }
        
        .companies-table thead th {
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #2d3748;
            padding: 15px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .companies-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .companies-table tbody tr:hover {
            background: #f7fafc;
        }
        
        .companies-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table-actions {
            display: flex;
            gap: 8px;
        }
        
        .table-actions .btn {
            padding: 6px 12px;
            font-size: 12px;
        }
        
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
        
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 5px;
        }
        
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
                <h2>Companies</h2>
                <p>Manage registered companies and establishments</p>
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
        
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['total_companies'] ?? 0; ?></div>
                <div class="stat-label">Total Companies</div>
            </div>
            <div class="stat-card" style="border-left-color: #48bb78;">
                <div class="stat-value" style="color: #48bb78;"><?php echo $stats['certified_companies'] ?? 0; ?></div>
                <div class="stat-label">Halal Certified</div>
            </div>
            <div class="stat-card" style="border-left-color: #ed8936;">
                <div class="stat-value" style="color: #ed8936;"><?php echo $stats['expiring_soon'] ?? 0; ?></div>
                <div class="stat-label">Expiring Soon (30 days)</div>
            </div>
            <div class="stat-card" style="border-left-color: #4299e1;">
                <div class="stat-value" style="color: #4299e1;"><?php echo $stats['active_companies'] ?? 0; ?></div>
                <div class="stat-label">Active Companies</div>
            </div>
        </div>
        
        <!-- Search and Filters -->
        <div class="content-card">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <input type="text" name="search" class="form-control" placeholder="Search companies..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-3">
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <?php foreach ($status_counts as $sid => $data): ?>
                        <option value="<?php echo $sid; ?>" <?php echo $status_filter == $sid ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($data['status']); ?> (<?php echo $data['count']; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <select name="usertype" class="form-select">
                        <option value="">All Types</option>
                        <option value="3" <?php echo $usertype_filter == '3' ? 'selected' : ''; ?>>Establishment</option>
                        <option value="4" <?php echo $usertype_filter == '4' ? 'selected' : ''; ?>>Accommodation</option>
                        <option value="5" <?php echo $usertype_filter == '5' ? 'selected' : ''; ?>>Tourist Spot</option>
                        <option value="6" <?php echo $usertype_filter == '6' ? 'selected' : ''; ?>>Prayer Facility</option>
                    </select>
                </div>
                <div class="col-md-12">
                    <button type="submit" class="btn btn-primary">Filter</button>
                    <a href="companies.php" class="btn btn-outline-secondary">Clear</a>
                </div>
            </form>
        </div>
        
        <!-- Companies List -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Companies (<?php echo $total_companies; ?>)</h5>
                <small class="text-muted">Showing <?php echo count($companies); ?> of <?php echo $total_companies; ?> companies</small>
            </div>
            
            <?php if (empty($companies)): ?>
            <div class="text-center py-5">
                <i class="fas fa-building fa-3x text-muted mb-3"></i>
                <p class="text-muted">No companies found.</p>
            </div>
            <?php else: ?>
            
            <div class="companies-table">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Company Name</th>
                            <th>Type</th>
                            <th>Contact</th>
                            <th>Address</th>
                            <th>Status</th>
                            <th>Applications</th>
                            <th>Certifications</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($companies as $company): ?>
                        <tr>
                            <td>
                                <strong><?php echo htmlspecialchars($company['company_name']); ?></strong>
                                <?php if (!empty($company['company_description'])): ?>
                                <br><small class="text-muted"><?php echo htmlspecialchars(substr($company['company_description'], 0, 80)); ?><?php echo strlen($company['company_description']) > 80 ? '...' : ''; ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($company['usertype'] ?? 'Unknown'); ?></span>
                            </td>
                            <td>
                                <?php if (!empty($company['email'])): ?>
                                <div><i class="fas fa-envelope text-muted"></i> <small><?php echo htmlspecialchars($company['email']); ?></small></div>
                                <?php endif; ?>
                                <?php if (!empty($company['contant_no']) || !empty($company['tel_no'])): ?>
                                <div><i class="fas fa-phone text-muted"></i> <small><?php echo htmlspecialchars($company['contant_no'] ?? $company['tel_no'] ?? ''); ?></small></div>
                                <?php endif; ?>
                                <?php if (empty($company['email']) && empty($company['contant_no']) && empty($company['tel_no'])): ?>
                                <small class="text-muted">N/A</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars(substr($company['full_address'], 0, 50)); ?><?php echo strlen($company['full_address']) > 50 ? '...' : ''; ?></small>
                            </td>
                            <td>
                                <span class="status-badge <?php 
                                    echo $company['status_id'] == 1 ? 'active' : 
                                        ($company['status_id'] == 4 ? 'certified' : 'inactive'); 
                                ?>">
                                    <?php echo htmlspecialchars($company['status']); ?>
                                </span>
                            </td>
                            <td>
                                <strong><?php echo $company['total_applications'] ?? 0; ?></strong>
                            </td>
                            <td>
                                <?php if ($company['approved_applications'] > 0): ?>
                                <div>
                                    <strong><?php echo $company['approved_applications']; ?></strong>
                                    <?php if ($company['latest_expiry_date']): ?>
                                    <br><small class="text-muted">Exp: <?php echo date('M d, Y', strtotime($company['latest_expiry_date'])); ?></small>
                                    <?php endif; ?>
                                </div>
                                <?php else: ?>
                                <small class="text-muted">-</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <a href="applications.php?company_id=<?php echo $company['company_id']; ?>" class="btn btn-sm btn-primary" title="View Applications">
                                        <i class="fas fa-file-alt"></i>
                                    </a>
                                    <?php if ($company['approved_applications'] > 0): ?>
                                    <a href="certifications.php?company_id=<?php echo $company['company_id']; ?>" class="btn btn-sm btn-outline-primary" title="View Certifications">
                                        <i class="fas fa-certificate"></i>
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php
                    // Build query string for pagination links
                    $query_params = [];
                    if (!empty($status_filter)) $query_params['status'] = $status_filter;
                    if (!empty($usertype_filter)) $query_params['usertype'] = $usertype_filter;
                    if (!empty($search)) $query_params['search'] = $search;
                    $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
                    ?>
                    
                    <!-- Previous Button -->
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $query_string; ?>" aria-label="Previous">
                            <span aria-hidden="true">&laquo;</span>
                        </a>
                    </li>
                    
                    <?php
                    // Show page numbers
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    if ($start_page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=1<?php echo $query_string; ?>">1</a>
                    </li>
                    <?php if ($start_page > 2): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                    <?php endif; ?>
                    <?php endif; ?>
                    
                    <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $query_string; ?>"><?php echo $i; ?></a>
                    </li>
                    <?php endfor; ?>
                    
                    <?php if ($end_page < $total_pages): ?>
                    <?php if ($end_page < $total_pages - 1): ?>
                    <li class="page-item disabled">
                        <span class="page-link">...</span>
                    </li>
                    <?php endif; ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $total_pages; ?><?php echo $query_string; ?>"><?php echo $total_pages; ?></a>
                    </li>
                    <?php endif; ?>
                    
                    <!-- Next Button -->
                    <li class="page-item <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $query_string; ?>" aria-label="Next">
                            <span aria-hidden="true">&raquo;</span>
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
            
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

