<?php
include '../common/session.php';
include '../common/connection.php';
include '../common/randomstrings.php';

date_default_timezone_set('Asia/Manila');

check_login();
check_access('Super Admin');

$superadmin_id = $_SESSION['superadmin_id'] ?? $_SESSION['user_id'];

// Handle approval/rejection of HCB registration
if (isset($_POST['approve_hcb'])) {
    $organization_id = mysqli_real_escape_string($conn, $_POST['organization_id']);
    $decision = mysqli_real_escape_string($conn, $_POST['decision']);
    $approval_notes = mysqli_real_escape_string($conn, $_POST['approval_notes'] ?? '');
    
    mysqli_begin_transaction($conn);
    try {
        // Get organization details
        $org_query = mysqli_query($conn, "SELECT * FROM tbl_organization WHERE organization_id = '$organization_id'");
        $org_data = mysqli_fetch_assoc($org_query);
        
        if (!$org_data) {
            throw new Exception("Organization not found");
        }
        
        if ($decision == 'Approve') {
            // Get Active status ID
            $active_status_query = mysqli_query($conn, "SELECT status_id FROM tbl_status WHERE status = 'Active' LIMIT 1");
            $active_status_row = mysqli_fetch_assoc($active_status_query);
            $active_status_id = $active_status_row['status_id'] ?? 1;
            
            // Update organization status to Active
            $update_org = "UPDATE tbl_organization SET status_id = '$active_status_id' WHERE organization_id = '$organization_id'";
            if (!mysqli_query($conn, $update_org)) {
                throw new Exception("Failed to update organization: " . mysqli_error($conn));
            }
            
            // Update admin status to Active
            $update_admin = "UPDATE tbl_admin SET status_id = '$active_status_id' WHERE organization_id = '$organization_id'";
            if (!mysqli_query($conn, $update_admin)) {
                throw new Exception("Failed to update admin: " . mysqli_error($conn));
            }
            
            // Update user account status to Active
            $update_account = "UPDATE tbl_useraccount ua 
                              JOIN tbl_admin a ON ua.admin_id = a.admin_id 
                              SET ua.status_id = '$active_status_id' 
                              WHERE a.organization_id = '$organization_id'";
            if (!mysqli_query($conn, $update_account)) {
                throw new Exception("Failed to update user account: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            $success_message = "HCB registration approved successfully! Organization is now active.";
            
        } else if ($decision == 'Reject') {
            if (empty($approval_notes)) {
                throw new Exception("Rejection reason is required.");
            }
            
            // Optionally, you can delete the records or keep them with Inactive status
            // For now, we'll keep them as Inactive (already set during registration)
            // You could add a rejection_reason field to tbl_organization if needed
            
            mysqli_commit($conn);
            $success_message = "HCB registration rejected.";
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = "Error: " . $e->getMessage();
    }
}

// Fetch pending HCB registrations (status_id = 2 or Inactive)
$pending_orgs = [];

$sql = "SELECT 
    o.*,
    a.admin_id,
    a.firstname,
    a.lastname,
    a.email as admin_email,
    a.contact_no as admin_contact,
    ua.username,
    s.status
FROM tbl_organization o
LEFT JOIN tbl_admin a ON o.organization_id = a.organization_id
LEFT JOIN tbl_useraccount ua ON a.admin_id = ua.admin_id
LEFT JOIN tbl_status s ON o.status_id = s.status_id
WHERE o.status_id = (SELECT status_id FROM tbl_status WHERE status = 'Inactive' LIMIT 1)
ORDER BY o.date_added DESC";

$result = mysqli_query($conn, $sql);
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $pending_orgs[] = $row;
    }
} else {
    $error_message = "Error fetching registrations: " . mysqli_error($conn);
}

$current_page = 'hcb-registrations.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HCB Registrations | Super Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h2>HCB Registrations</h2>
                <p>Review and approve Certifying Body registration applications</p>
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
        
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h3 style="font-size: 20px; font-weight: 700; margin: 0;">Pending HCB Registrations</h3>
                <span class="badge bg-warning" style="font-size: 14px; padding: 8px 16px;">
                    <?php echo count($pending_orgs); ?> Application(s) Pending Review
                </span>
            </div>
            
            <?php if (empty($pending_orgs)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-inbox" style="font-size: 64px; color: #cbd5e0; margin-bottom: 20px;"></i>
                    <h4 style="color: #718096; margin-bottom: 10px;">No Pending Registrations</h4>
                    <p style="color: #a0aec0;">There are no HCB registration applications waiting for review.</p>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($pending_orgs as $org): ?>
                    <div class="col-md-6 mb-4">
                        <div class="card" style="border-left: 4px solid #667eea;">
                            <div class="card-body">
                                <h5 class="card-title">
                                    <i class="fas fa-building me-2 text-primary"></i>
                                    <?php echo htmlspecialchars($org['organization_name']); ?>
                                </h5>
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-calendar me-2"></i>
                                    Applied: <?php echo date('M d, Y', strtotime($org['date_added'])); ?>
                                </p>
                                
                                <div class="mb-3">
                                    <strong>Admin Details:</strong><br>
                                    <small>
                                        <i class="fas fa-user me-2"></i>
                                        <?php echo htmlspecialchars($org['firstname'] . ' ' . $org['lastname']); ?><br>
                                        <i class="fas fa-envelope me-2"></i>
                                        <?php echo htmlspecialchars($org['admin_email']); ?><br>
                                        <i class="fas fa-phone me-2"></i>
                                        <?php echo htmlspecialchars($org['admin_contact'] ?? 'N/A'); ?><br>
                                        <i class="fas fa-user-tag me-2"></i>
                                        Username: <?php echo htmlspecialchars($org['username']); ?>
                                    </small>
                                </div>
                                
                                <?php if ($org['address']): ?>
                                <div class="mb-3">
                                    <strong>Address:</strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($org['address']); ?></small>
                                </div>
                                <?php endif; ?>
                                
                                <div class="d-flex gap-2">
                                    <button class="btn btn-success btn-sm" onclick="approveHCB('<?php echo htmlspecialchars($org['organization_id']); ?>', '<?php echo htmlspecialchars($org['organization_name']); ?>')">
                                        <i class="fas fa-check-circle"></i> Approve
                                    </button>
                                    <button class="btn btn-danger btn-sm" onclick="rejectHCB('<?php echo htmlspecialchars($org['organization_id']); ?>', '<?php echo htmlspecialchars($org['organization_name']); ?>')">
                                        <i class="fas fa-times-circle"></i> Reject
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Approval Modal -->
    <div class="modal fade" id="approvalModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Approve HCB Registration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="organization_id" id="approve_org_id">
                        <input type="hidden" name="decision" value="Approve">
                        
                        <p>Are you sure you want to approve <strong id="approve_org_name"></strong> as a Certifying Body?</p>
                        <p class="text-muted small">This will activate the organization, admin, and user account.</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="approval_notes" class="form-control" rows="3" placeholder="Add any approval notes..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_hcb" class="btn btn-success">Approve Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Rejection Modal -->
    <div class="modal fade" id="rejectionModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header bg-danger text-white">
                        <h5 class="modal-title">Reject HCB Registration</h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="organization_id" id="reject_org_id">
                        <input type="hidden" name="decision" value="Reject">
                        
                        <p>Are you sure you want to reject the registration for <strong id="reject_org_name"></strong>?</p>
                        
                        <div class="mb-3">
                            <label class="form-label">Rejection Reason <span class="text-danger">*</span></label>
                            <textarea name="approval_notes" class="form-control" rows="4" placeholder="Please provide a reason for rejection..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="approve_hcb" class="btn btn-danger">Reject Registration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        function approveHCB(orgId, orgName) {
            document.getElementById('approve_org_id').value = orgId;
            document.getElementById('approve_org_name').textContent = orgName;
            new bootstrap.Modal(document.getElementById('approvalModal')).show();
        }
        
        function rejectHCB(orgId, orgName) {
            document.getElementById('reject_org_id').value = orgId;
            document.getElementById('reject_org_name').textContent = orgName;
            new bootstrap.Modal(document.getElementById('rejectionModal')).show();
        }
    </script>
</body>
</html>

