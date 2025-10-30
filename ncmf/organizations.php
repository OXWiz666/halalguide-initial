<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

// Check login and access
check_login();
check_access('Super Admin');

$superadmin_id = $_SESSION['superadmin_id'] ?? $_SESSION['user_id'];
$superadmin_name = $_SESSION['superadmin_name'] ?? 'Super Admin';

// Handle status change (Activate/Deactivate)
if (isset($_POST['change_status'])) {
    $organization_id = mysqli_real_escape_string($conn, $_POST['organization_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    
    mysqli_begin_transaction($conn);
    try {
        // Get status ID
        $status_query = mysqli_query($conn, "SELECT status_id FROM tbl_status WHERE status = '$new_status' LIMIT 1");
        $status_row = mysqli_fetch_assoc($status_query);
        $status_id = $status_row['status_id'] ?? 1;
        
        // Update organization status
        $update_org = "UPDATE tbl_organization SET status_id = '$status_id' WHERE organization_id = '$organization_id'";
        if (!mysqli_query($conn, $update_org)) {
            throw new Exception("Failed to update organization: " . mysqli_error($conn));
        }
        
        // Update admin status
        $update_admin = "UPDATE tbl_admin SET status_id = '$status_id' WHERE organization_id = '$organization_id'";
        if (!mysqli_query($conn, $update_admin)) {
            throw new Exception("Failed to update admin: " . mysqli_error($conn));
        }
        
        // Update user account status
        $update_account = "UPDATE tbl_useraccount ua 
                          JOIN tbl_admin a ON ua.admin_id = a.admin_id 
                          SET ua.status_id = '$status_id' 
                          WHERE a.organization_id = '$organization_id'";
        if (!mysqli_query($conn, $update_account)) {
            throw new Exception("Failed to update user account: " . mysqli_error($conn));
        }
        
        mysqli_commit($conn);
        $success_message = "Certifying Body status updated successfully!";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = "Error: " . $e->getMessage();
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Build query for all organizations
$where_clause = "1=1"; // Show all organizations

if (!empty($status_filter)) {
    if ($status_filter == 'active') {
        $where_clause .= " AND o.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Active' LIMIT 1)";
    } elseif ($status_filter == 'inactive') {
        $where_clause .= " AND o.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Inactive' LIMIT 1)";
    }
}

if (!empty($search)) {
    $where_clause .= " AND (o.organization_name LIKE '%$search%' OR a.firstname LIKE '%$search%' OR a.lastname LIKE '%$search%' OR o.email LIKE '%$search%' OR o.contact_no LIKE '%$search%')";
}

// Get all organizations
$organizations_query = "SELECT 
    o.*,
    a.admin_id,
    a.firstname,
    a.lastname,
    a.email as admin_email,
    a.contact_no as admin_contact,
    ua.username,
    s.status,
    s.status_id,
    (SELECT COUNT(*) FROM tbl_certification_application WHERE organization_id = o.organization_id AND current_status = 'Approved') as total_certifications,
    (SELECT COUNT(*) FROM tbl_certification_application WHERE organization_id = o.organization_id AND current_status = 'Submitted') as pending_applications
FROM tbl_organization o
LEFT JOIN tbl_admin a ON o.organization_id = a.organization_id
LEFT JOIN tbl_useraccount ua ON a.admin_id = ua.admin_id
LEFT JOIN tbl_status s ON o.status_id = s.status_id
WHERE $where_clause
ORDER BY 
    CASE WHEN o.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Active' LIMIT 1) THEN 1 ELSE 2 END,
    o.organization_name";

$organizations_result = mysqli_query($conn, $organizations_query);
$organizations = [];

if ($organizations_result) {
    while ($row = mysqli_fetch_assoc($organizations_result)) {
        $organizations[] = $row;
    }
}

// Get statistics
$stats_query = "SELECT 
    COUNT(*) as total_organizations,
    COUNT(CASE WHEN o.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Active' LIMIT 1) THEN 1 END) as active_organizations,
    COUNT(CASE WHEN o.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Inactive' LIMIT 1) THEN 1 END) as inactive_organizations,
    (SELECT COUNT(*) FROM tbl_certification_application WHERE current_status = 'Approved') as total_certifications
FROM tbl_organization o";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

$current_page = 'organizations.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certifying Bodies | Super Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        .status-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-active {
            background: linear-gradient(135deg, #48bb78 0%, #38a169 100%);
            color: white;
        }
        
        .status-inactive {
            background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%);
            color: white;
        }
        
        .table-hover tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .org-stats {
            display: flex;
            gap: 15px;
            align-items: center;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 5px;
            font-size: 14px;
            color: #718096;
        }
        
        .stat-item i {
            color: #667eea;
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h2>Certifying Bodies</h2>
                <p>Manage all Halal Certifying Bodies (HCB) and organizations</p>
            </div>
        </div>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-building text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['total_organizations'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Total Organizations</div>
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
                                <?php echo $stats['active_organizations'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Active Organizations</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #ed8936 0%, #dd6b20 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-clock text-white" style="font-size: 24px;"></i>
                        </div>
                        <div>
                            <div style="font-size: 32px; font-weight: 700; color: #1a202c;">
                                <?php echo $stats['inactive_organizations'] ?? 0; ?>
                            </div>
                            <div style="font-size: 14px; color: #718096;">Pending/Inactive</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-3">
                <div class="content-card">
                    <div class="d-flex align-items-center">
                        <div class="me-3" style="width: 50px; height: 50px; background: linear-gradient(135deg, #9f7aea 0%, #805ad5 100%); border-radius: 12px; display: flex; align-items: center; justify-content: center;">
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
        </div>
        
        <!-- Filters -->
        <div class="content-card mb-4">
            <form method="GET" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Search by organization name, admin name, email, or contact..." value="<?php echo htmlspecialchars($search); ?>">
                </div>
                
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="">All Status</option>
                        <option value="active" <?php echo ($status_filter == 'active') ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo ($status_filter == 'inactive') ? 'selected' : ''; ?>>Inactive/Pending</option>
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
        
        <!-- Organizations Table -->
        <div class="content-card">
            <h3 class="mb-4" style="font-size: 20px; font-weight: 700; color: #1a202c;">
                Organizations List
                <span class="badge bg-secondary" style="font-size: 14px;"><?php echo count($organizations); ?> Result(s)</span>
            </h3>
            
            <?php if (empty($organizations)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-building fa-3x text-muted mb-3"></i>
                    <p class="text-muted">No certifying bodies found.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Organization Name</th>
                                <th>Contact Information</th>
                                <th>Admin</th>
                                <th>Statistics</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($organizations as $org): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($org['organization_name']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($org['address'] ?? 'Address not specified'); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <small><i class="fas fa-phone"></i> <?php echo htmlspecialchars($org['contact_no'] ?? 'N/A'); ?></small>
                                            <br>
                                            <small><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($org['email'] ?? 'N/A'); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?php echo htmlspecialchars($org['firstname'] . ' ' . $org['lastname']); ?></strong>
                                            <br>
                                            <small class="text-muted"><?php echo htmlspecialchars($org['username'] ?? 'N/A'); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="org-stats">
                                            <div class="stat-item">
                                                <i class="fas fa-certificate"></i>
                                                <span><?php echo $org['total_certifications']; ?> Certifications</span>
                                            </div>
                                            <?php if ($org['pending_applications'] > 0): ?>
                                                <div class="stat-item">
                                                    <i class="fas fa-file-alt"></i>
                                                    <span><?php echo $org['pending_applications']; ?> Pending</span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $is_active = ($org['status'] == 'Active');
                                        $badge_class = $is_active ? 'status-active' : 'status-inactive';
                                        ?>
                                        <span class="status-badge <?php echo $badge_class; ?>">
                                            <?php echo htmlspecialchars($org['status'] ?? 'Unknown'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to change the status of this organization?');">
                                            <input type="hidden" name="organization_id" value="<?php echo $org['organization_id']; ?>">
                                            <input type="hidden" name="new_status" value="<?php echo $is_active ? 'Inactive' : 'Active'; ?>">
                                            <button type="submit" name="change_status" class="btn btn-sm <?php echo $is_active ? 'btn-warning' : 'btn-success'; ?>">
                                                <i class="fas fa-<?php echo $is_active ? 'ban' : 'check'; ?>"></i>
                                                <?php echo $is_active ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
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
    
    <script>
        <?php if (isset($success_message)): ?>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: '<?php echo addslashes($success_message); ?>',
                timer: 3000,
                showConfirmButton: false
            });
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: '<?php echo addslashes($error_message); ?>'
            });
        <?php endif; ?>
    </script>
</body>
</html>

