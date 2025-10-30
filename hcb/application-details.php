<?php
include '../common/session.php';
include '../common/connection.php';
include '../common/randomstrings.php';

date_default_timezone_set('Asia/Manila');

check_login();
check_access('Admin');

$admin_id = $_SESSION['admin_id'];
$organization_id = $_SESSION['organization_id'];

$application_id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if (empty($application_id)) {
    header("Location: applications.php");
    exit();
}

// Determine if application form table exists to avoid fatal errors on older schemas
$form_table_exists = false;
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_application_form'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $form_table_exists = true;
}

// Build dynamic SELECT and JOIN parts
$select_form = '';
$join_form = '';
if ($form_table_exists) {
    $select_form = ",
    af.application_date as form_application_date,
    af.business_address as form_business_address,
    af.landline as form_landline,
    af.fax_no as form_fax_no,
    af.application_email as form_email,
    af.application_contact as form_contact,
    af.contact_person as form_contact_person,
    af.contact_position as form_contact_position,
    af.legal_personality as form_legal_personality,
    af.category as form_category,
    af.business_food as form_business_food,
    af.business_nonfood as form_business_nonfood,
    af.product_a as form_product_a,
    af.product_b as form_product_b,
    af.product_c as form_product_c,
    af.product_porkfree as form_product_porkfree,
    af.product_meatfree as form_product_meatfree,
    af.product_alcoholfree as form_product_alcoholfree,
    af.applicant_position as form_applicant_position";
    $join_form = "\nLEFT JOIN tbl_application_form af ON af.application_id = ca.application_id";
}

// Get application details
$app_query = "SELECT 
    ca.*,
    c.company_name,
    c.company_description,
    c.email as company_email,
    c.contant_no as company_contact,
    c.tel_no,
    a.other as address_line1,
    b.brgyDesc,
    cm.citymunDesc,
    p.provDesc" .
    $select_form .
"\nFROM tbl_certification_application ca
LEFT JOIN tbl_company c ON ca.company_id = c.company_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode" .
    $join_form .
"\nWHERE ca.application_id = '$application_id' AND ca.organization_id = '$organization_id'";

$app_result = mysqli_query($conn, $app_query);
if (!$app_result || mysqli_num_rows($app_result) == 0) {
    header("Location: applications.php");
    exit();
}

$application = mysqli_fetch_assoc($app_result);

// Handle document approval/rejection
if (isset($_POST['review_document'])) {
    $document_id = mysqli_real_escape_string($conn, $_POST['document_id']);
    $review_action = mysqli_real_escape_string($conn, $_POST['review_action']);
    $review_notes = mysqli_real_escape_string($conn, $_POST['review_notes'] ?? '');
    
    // Get document details
    $doc_query = "SELECT d.*, dc.document_name, ca.company_id, ca.application_number, c.company_name 
                  FROM tbl_application_documents d
                  LEFT JOIN tbl_document_checklist dc ON d.document_type = dc.document_type
                  LEFT JOIN tbl_certification_application ca ON d.application_id = ca.application_id
                  LEFT JOIN tbl_company c ON ca.company_id = c.company_id
                  WHERE d.document_id = '$document_id' AND ca.organization_id = '$organization_id'";
    $doc_result = mysqli_query($conn, $doc_query);
    
    if ($doc_result && mysqli_num_rows($doc_result) > 0) {
        $doc_data = mysqli_fetch_assoc($doc_result);
        
        mysqli_begin_transaction($conn);
        
        try {
            // Update document status
            $update_query = "UPDATE tbl_application_documents SET 
                upload_status = '$review_action',
                reviewed_by = '$admin_id',
                reviewed_date = NOW()";
            
            if ($review_action == 'Rejected') {
                $update_query .= ", rejection_reason = '$review_notes'";
            } else {
                $update_query .= ", rejection_reason = NULL";
            }
            
            $update_query .= " WHERE document_id = '$document_id'";
            
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Error updating document review: " . mysqli_error($conn));
            }
            
            // Create notification for company
            $notification_id = generate_string($permitted_chars, 25);
            $company_id = mysqli_real_escape_string($conn, $doc_data['company_id']);
            $document_name = $doc_data['document_name'];
            $application_number = $doc_data['application_number'];
            $application_id_escaped = mysqli_real_escape_string($conn, $doc_data['application_id']);
            
            // Build subject and message
            $subject = $review_action == 'Uploaded' 
                ? "Document Approved: " . $document_name
                : "Document Rejected: " . $document_name;
            
            if ($review_action == 'Uploaded') {
                $message = "Your document '" . $document_name . "' for application #" . $application_number . " has been approved by the certifying body.";
            } else {
                $rejection_reason_text = !empty($review_notes) ? "Reason: " . $review_notes : "Please review and resubmit.";
                $message = "Your document '" . $document_name . "' for application #" . $application_number . " has been rejected. " . $rejection_reason_text;
            }
            
            // Escape the final strings for SQL (only once)
            $subject = mysqli_real_escape_string($conn, $subject);
            $message = mysqli_real_escape_string($conn, $message);
            
            $notif_query = "INSERT INTO tbl_application_notifications 
                (notification_id, application_id, notification_type, recipient_type, recipient_id, subject, message, date_added) 
                VALUES ('$notification_id', '$application_id_escaped', 'Document Review', 'Company', '$company_id', '$subject', '$message', NOW())";
            
            if (!mysqli_query($conn, $notif_query)) {
                throw new Exception("Error creating notification: " . mysqli_error($conn));
            }
            
            mysqli_commit($conn);
            $success_message = $review_action == 'Uploaded' 
                ? "Document approved successfully! Company has been notified." 
                : "Document rejected. Company has been notified.";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    } else {
        $error_message = "Document not found or access denied.";
    }
}

// Get application documents
$docs_query = "SELECT 
    d.*,
    dc.document_name,
    dc.description,
    dc.is_required as checklist_required
FROM tbl_application_documents d
LEFT JOIN tbl_document_checklist dc ON d.document_type = dc.document_type
WHERE d.application_id = '$application_id'
ORDER BY d.date_added DESC";

$docs_result = mysqli_query($conn, $docs_query);
$documents = [];
while ($row = mysqli_fetch_assoc($docs_result)) {
    $documents[] = $row;
}

// Get required documents checklist
$checklist_query = "SELECT * FROM tbl_document_checklist WHERE status_id = 1 ORDER BY display_order ASC";
$checklist_result = mysqli_query($conn, $checklist_query);
$checklist = [];
$uploaded_types = array_column($documents, 'document_type');
while ($row = mysqli_fetch_assoc($checklist_result)) {
    $row['is_uploaded'] = in_array($row['document_type'], $uploaded_types);
    $checklist[] = $row;
}

// Get site visits query (defined here for reuse)
$visits_query = "SELECT 
    v.*,
    ua.username as assigned_to_name
FROM tbl_application_visits v
LEFT JOIN tbl_useraccount ua ON v.assigned_to = ua.useraccount_id
WHERE v.application_id = '$application_id'
ORDER BY v.scheduled_date DESC";

// Get initial visits data
$visits_result = mysqli_query($conn, $visits_query);
$visits = [];
while ($row = mysqli_fetch_assoc($visits_result)) {
    $visits[] = $row;
}

// Handle site visit scheduling
if (isset($_POST['schedule_visit'])) {
    $visit_date = mysqli_real_escape_string($conn, $_POST['visit_date']);
    $visit_time = mysqli_real_escape_string($conn, $_POST['visit_time']);
    $visit_type = mysqli_real_escape_string($conn, $_POST['visit_type']);
    $notes = mysqli_real_escape_string($conn, $_POST['visit_notes'] ?? '');
    
    $scheduled_datetime = $visit_date . ' ' . $visit_time . ':00';
    
    $visit_id = generate_string($permitted_chars, 25);
    
    mysqli_begin_transaction($conn);
    
    try {
        $insert_query = "INSERT INTO tbl_application_visits 
            (visit_id, application_id, visit_type, scheduled_date, scheduled_by, visit_status, notes, date_added) 
            VALUES ('$visit_id', '$application_id', '$visit_type', '$scheduled_datetime', '$admin_id', 'Scheduled', '$notes', NOW())";
        
        if (!mysqli_query($conn, $insert_query)) {
            throw new Exception("Error scheduling visit: " . mysqli_error($conn));
        }
        
        // Update application status if scheduling initial visit
        if ($visit_type == 'Initial') {
            $update_app_query = "UPDATE tbl_certification_application SET current_status = 'Scheduled for Visit', date_updated = NOW() WHERE application_id = '$application_id'";
            if (!mysqli_query($conn, $update_app_query)) {
                throw new Exception("Error updating application status: " . mysqli_error($conn));
            }
            
            // Add status history
            $history_id = generate_string($permitted_chars, 25);
            $current_status = $application['current_status'];
            $history_query = "INSERT INTO tbl_application_status_history 
                (history_id, application_id, previous_status, new_status, changed_by, changed_by_type, change_reason, date_changed) 
                VALUES ('$history_id', '$application_id', '$current_status', 'Scheduled for Visit', '$admin_id', 'Admin', 'Site visit scheduled', NOW())";
            mysqli_query($conn, $history_query);
        }
        
        // Create notification
        $notification_id = generate_string($permitted_chars, 25);
        $company_id = $application['company_id'];
        $app_number = $application['application_number'];
        
        $subject = "Site Visit Scheduled";
        $message = "A site visit has been scheduled for your certification application #$app_number on " . date('F d, Y g:i A', strtotime($scheduled_datetime)) . ". Visit Type: $visit_type.";
        if (!empty($notes)) {
            $message .= " Notes: $notes";
        }
        
        $notif_query = "INSERT INTO tbl_application_notifications 
            (notification_id, application_id, notification_type, recipient_type, recipient_id, subject, message, date_added) 
            VALUES ('$notification_id', '$application_id', 'Visit Scheduled', 'Company', '$company_id', '$subject', '$message', NOW())";
        
        mysqli_query($conn, $notif_query); // Don't fail if notification fails
        
        mysqli_commit($conn);
        $success_message = "Site visit scheduled successfully! Company has been notified.";
        
        // Refresh application data
        $app_result = mysqli_query($conn, $app_query);
        $application = mysqli_fetch_assoc($app_result);
        
        // Refresh visits data
        $visits_result = mysqli_query($conn, $visits_query);
        $visits = [];
        while ($row = mysqli_fetch_assoc($visits_result)) {
            $visits[] = $row;
        }
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
    }
}

// Get status history
$history_query = "SELECT 
    h.*,
    ua.username as changed_by_name
FROM tbl_application_status_history h
LEFT JOIN tbl_useraccount ua ON h.changed_by = ua.useraccount_id
WHERE h.application_id = '$application_id'
ORDER BY h.date_changed DESC";

$history_result = mysqli_query($conn, $history_query);
$history = [];
while ($row = mysqli_fetch_assoc($history_result)) {
    $history[] = $row;
}

// Status configuration
$status_config = [
    'Submitted' => ['color' => '#667eea', 'icon' => 'fa-file-alt', 'badge' => 'primary'],
    'Under Review' => ['color' => '#ed8936', 'icon' => 'fa-search', 'badge' => 'warning'],
    'Scheduled for Visit' => ['color' => '#48bb78', 'icon' => 'fa-calendar-check', 'badge' => 'success'],
    'Final Review' => ['color' => '#4299e1', 'icon' => 'fa-clipboard-check', 'badge' => 'info'],
    'Approved' => ['color' => '#48bb78', 'icon' => 'fa-check-circle', 'badge' => 'success'],
    'Rejected' => ['color' => '#f56565', 'icon' => 'fa-times-circle', 'badge' => 'danger']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Details | HCB Portal</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
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
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 25px;
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .page-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 28px;
        }
        
        .page-header .subtitle {
            opacity: 0.9;
            margin-top: 8px;
        }
        
        .info-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: all 0.3s;
            border-top: 3px solid transparent;
        }
        
        .info-card:hover {
            box-shadow: 0 4px 16px rgba(0, 0, 0, 0.12);
            transform: translateY(-2px);
        }
        
        .info-card.primary { border-top-color: #667eea; }
        .info-card.success { border-top-color: #48bb78; }
        .info-card.warning { border-top-color: #ed8936; }
        .info-card.info { border-top-color: #4299e1; }
        
        .card-header-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #e2e8f0;
        }
        
        .card-header-section h5 {
            margin: 0;
            font-weight: 700;
            font-size: 18px;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-header-section h5 i {
            color: #667eea;
        }
        
        .info-label {
            font-size: 11px;
            color: #a0aec0;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
            font-weight: 600;
        }
        
        .info-value {
            font-size: 15px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 20px;
            line-height: 1.5;
        }
        
        .info-value i {
            color: #667eea;
            margin-right: 8px;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .status-badge.primary { background: #e0e7ff; color: #667eea; }
        .status-badge.warning { background: #feebc8; color: #ed8936; }
        .status-badge.success { background: #c6f6d5; color: #48bb78; }
        .status-badge.info { background: #bee3f8; color: #4299e1; }
        .status-badge.danger { background: #fed7d7; color: #f56565; }
        
        .document-item {
            background: #f7fafc;
            border-radius: 12px;
            padding: 18px;
            margin-bottom: 15px;
            border-left: 4px solid #e2e8f0;
            transition: all 0.3s;
        }
        
        .document-item:hover {
            background: #edf2f7;
            transform: translateX(5px);
        }
        
        .document-item.uploaded {
            border-left-color: #48bb78;
            background: #f0fdf4;
        }
        
        .document-item.pending {
            border-left-color: #ed8936;
            background: #fff7ed;
        }
        
        .document-item.rejected {
            border-left-color: #f56565;
            background: #fef2f2;
        }
        
        .document-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 10px;
        }
        
        .document-status-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
        }
        
        .document-status-icon.success {
            background: #c6f6d5;
            color: #48bb78;
        }
        
        .document-status-icon.pending {
            background: #feebc8;
            color: #ed8936;
        }
        
        .document-status-icon.rejected {
            background: #fed7d7;
            color: #f56565;
        }
        
        .visit-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 15px;
            border-left: 4px solid #4299e1;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
            transition: all 0.3s;
        }
        
        .visit-card:hover {
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .visit-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 15px;
        }
        
        .history-item {
            padding: 18px;
            border-left: 4px solid #e2e8f0;
            margin-bottom: 12px;
            background: white;
            border-radius: 12px;
            transition: all 0.3s;
            position: relative;
        }
        
        .history-item:hover {
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }
        
        .history-item.new {
            border-left-color: <?php echo $status_config[$application['current_status']]['color'] ?? '#667eea'; ?>;
            background: linear-gradient(to right, <?php echo $status_config[$application['current_status']]['color'] ?? '#667eea'; ?>08, white);
        }
        
        .action-btn-group {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        
        .action-btn-group .btn {
            padding: 12px 20px;
            font-weight: 600;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .action-btn-group .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }
        
        .timeline {
            position: relative;
            padding-left: 40px;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 3px;
            background: linear-gradient(to bottom, #667eea, #764ba2);
            border-radius: 2px;
        }
        
        .timeline-item {
            position: relative;
            padding-bottom: 30px;
            display: flex;
            align-items: start;
            gap: 15px;
        }
        
        .timeline-item:last-child {
            padding-bottom: 0;
        }
        
        .timeline-item i {
            position: absolute;
            left: -37px;
            width: 32px;
            height: 32px;
            border-radius: 50%;
            background: #e2e8f0;
            color: #a0aec0;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 14px;
            z-index: 2;
            border: 3px solid white;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }
        
        .timeline-item.completed i {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.4);
        }
        
        .timeline-item-content {
            flex: 1;
        }
        
        .timeline-item-content strong {
            display: block;
            font-size: 15px;
            color: #1a202c;
            margin-bottom: 5px;
        }
        
        .timeline-item-content .small {
            color: #718096;
            font-size: 13px;
        }
        
        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 12px;
            padding: 20px;
            color: white;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .stat-box .value {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-box .label {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .quick-info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .quick-info-item {
            background: #f7fafc;
            border-radius: 10px;
            padding: 15px;
            text-align: center;
        }
        
        .quick-info-item .value {
            font-size: 20px;
            font-weight: 700;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .quick-info-item .label {
            font-size: 12px;
            color: #718096;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <?php 
    $current_page = 'application-details.php';
    include 'includes/sidebar.php'; 
    ?>
    
    <div class="main-content">
        <!-- Success/Error Messages - Hidden, handled by SweetAlert -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show d-none" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show d-none" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Back Button -->
        <div class="mb-4">
            <a href="applications.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-arrow-left me-2"></i>Back to Applications
            </a>
        </div>
        
        <!-- Application Header -->
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <h2><?php echo htmlspecialchars($application['company_name']); ?></h2>
                    <div class="subtitle">
                        <i class="fas fa-hashtag me-2"></i>Application #<?php echo htmlspecialchars($application['application_number']); ?>
                        <?php if (!empty($application['certificate_number'])): ?>
                        <span class="ms-3">
                            <i class="fas fa-certificate me-2"></i>Certificate: <?php echo htmlspecialchars($application['certificate_number']); ?>
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="d-flex align-items-center" style="gap: 10px;">
                    <a class="btn btn-sm btn-outline-light no-print" href="print-application.php?id=<?php echo $application_id; ?>" target="_blank" rel="noopener">
                        <i class="fas fa-print me-1"></i> Print
                    </a>
                    <span class="status-badge" style="background: rgba(255, 255, 255, 0.2); color: white; border: 2px solid rgba(255, 255, 255, 0.3);">
                        <i class="fas <?php echo $status_config[$application['current_status']]['icon'] ?? 'fa-circle'; ?>"></i>
                        <?php echo htmlspecialchars($application['current_status']); ?>
                    </span>
                </div>
            </div>
        </div>
        
        <div id="hcbPrintArea">
        <div class="row">
            <!-- Left Column -->
            <div class="col-lg-8">
                <!-- Company Information -->
                <div class="info-card primary">
                    <div class="card-header-section">
                        <h5><i class="fas fa-building"></i>Company Information</h5>
                    </div>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="info-label">Company Name</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['company_name']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Application Type</div>
                            <div class="info-value"><?php echo htmlspecialchars($application['application_type']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Email</div>
                            <div class="info-value">
                                <i class="fas fa-envelope me-2 text-muted"></i>
                                <?php echo htmlspecialchars($application['company_email'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="info-label">Contact</div>
                            <div class="info-value">
                                <i class="fas fa-phone me-2 text-muted"></i>
                                <?php echo htmlspecialchars($application['company_contact'] ?? $application['tel_no'] ?? 'N/A'); ?>
                            </div>
                        </div>
                        <div class="col-12">
                            <div class="info-label">Address</div>
                            <div class="info-value">
                                <?php 
                                $address_parts = array_filter([
                                    $application['address_line1'],
                                    $application['brgyDesc'],
                                    $application['citymunDesc'],
                                    $application['provDesc']
                                ]);
                                echo htmlspecialchars(implode(', ', $address_parts) ?: 'Address not specified');
                                ?>
                            </div>
                        </div>
                        <?php if ($application['company_description']): ?>
                        <div class="col-12">
                            <div class="info-label">Description</div>
                            <div class="info-value" style="font-weight: 400;"><?php echo htmlspecialchars($application['company_description']); ?></div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Documents Section -->
                <div class="info-card success">
                    <div class="card-header-section">
                        <h5><i class="fas fa-file-alt"></i>Documents</h5>
                        <div>
                            <?php 
                            $uploaded_count = count(array_filter($checklist, fn($doc) => $doc['is_uploaded']));
                            $total_count = count($checklist);
                            ?>
                            <span class="badge bg-success" style="font-size: 13px; padding: 8px 12px;">
                                <?php echo "$uploaded_count/$total_count uploaded"; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Document Checklist -->
                    <?php foreach ($checklist as $doc_req): ?>
                    <div class="document-item <?php echo $doc_req['is_uploaded'] ? 'uploaded' : 'pending'; ?>" data-document-id="<?php echo $uploaded_doc['document_id'] ?? ''; ?>">
                        <div class="document-header">
                            <div class="document-status-icon <?php echo $doc_req['is_uploaded'] ? 'success' : 'pending'; ?>">
                                <i class="fas <?php echo $doc_req['is_uploaded'] ? 'fa-check-circle' : 'fa-clock'; ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1" style="font-weight: 700; color: #1a202c;">
                                    <?php echo htmlspecialchars($doc_req['document_name']); ?>
                                    <?php if ($doc_req['is_required']): ?>
                                        <span class="badge bg-danger ms-2" style="font-size: 10px;">Required</span>
                                    <?php endif; ?>
                                </h6>
                                <?php if ($doc_req['description']): ?>
                                <p class="text-muted small mb-1"><?php echo htmlspecialchars($doc_req['description']); ?></p>
                                <?php endif; ?>
                                
                                <!-- Show uploaded document if exists -->
                                <?php 
                                $uploaded_doc = array_filter($documents, fn($d) => $d['document_type'] == $doc_req['document_type']);
                                $uploaded_doc = !empty($uploaded_doc) ? array_values($uploaded_doc)[0] : null;
                                ?>
                                <?php if ($uploaded_doc): ?>
                                <div class="mt-2">
                                    <div class="d-flex align-items-center gap-2">
                                        <a href="<?php echo htmlspecialchars($uploaded_doc['file_path']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i> View Document
                                        </a>
                                        <span class="badge bg-<?php 
                                            echo $uploaded_doc['upload_status'] == 'Uploaded' ? 'success' : 
                                                ($uploaded_doc['upload_status'] == 'Rejected' ? 'danger' : 'warning'); 
                                        ?>" style="font-size: 12px; padding: 6px 12px;">
                                            <i class="fas <?php 
                                                echo $uploaded_doc['upload_status'] == 'Uploaded' ? 'fa-check-circle' : 
                                                    ($uploaded_doc['upload_status'] == 'Rejected' ? 'fa-times-circle' : 'fa-clock'); 
                                            ?> me-1"></i>
                                            <?php echo htmlspecialchars($uploaded_doc['upload_status'] == 'Uploaded' ? 'Approved' : $uploaded_doc['upload_status']); ?>
                                        </span>
                                        
                                        <?php if ($uploaded_doc['upload_status'] == 'Pending'): ?>
                                        <button class="btn btn-sm btn-success" 
                                                onclick="reviewDocument(this, '<?php echo $uploaded_doc['document_id']; ?>', '<?php echo htmlspecialchars($uploaded_doc['document_name']); ?>', 'approve')"
                                                title="Approve Document">
                                            <i class="fas fa-check me-1"></i> Approve
                                        </button>
                                        <button class="btn btn-sm btn-danger" 
                                                onclick="reviewDocument(this, '<?php echo $uploaded_doc['document_id']; ?>', '<?php echo htmlspecialchars($uploaded_doc['document_name']); ?>', 'reject')"
                                                title="Reject Document">
                                            <i class="fas fa-times me-1"></i> Reject
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($uploaded_doc['rejection_reason']): ?>
                                    <div class="alert alert-danger mt-2 mb-0 small ajax-rejection-reason">
                                        <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($uploaded_doc['rejection_reason']); ?>
                                    </div>
                                    <?php endif; ?>
                                    <small class="text-muted d-block mt-1">
                                        Uploaded: <?php echo date('M d, Y g:i A', strtotime($uploaded_doc['date_added'])); ?>
                                    </small>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                
                <!-- Site Visits -->
                <div class="info-card info">
                    <div class="card-header-section">
                        <h5><i class="fas fa-calendar-check"></i>Site Visits</h5>
                        <button class="btn btn-sm btn-success" onclick="scheduleVisit('<?php echo $application_id; ?>')">
                            <i class="fas fa-plus me-2"></i>Schedule Visit
                        </button>
                    </div>
                    
                    <?php if (empty($visits)): ?>
                    <p class="text-muted">No site visits scheduled yet.</p>
                    <?php else: ?>
                    <?php foreach ($visits as $visit): ?>
                    <div class="visit-card">
                        <div class="visit-header">
                            <div>
                                <h6 class="mb-2" style="font-weight: 700; color: #1a202c;">
                                    <i class="fas fa-calendar text-primary me-2"></i>
                                    <?php echo date('F d, Y g:i A', strtotime($visit['scheduled_date'])); ?>
                                </h6>
                                <div class="d-flex gap-2 flex-wrap">
                                    <span class="badge bg-<?php 
                                        echo $visit['visit_status'] == 'Completed' ? 'success' : 
                                            ($visit['visit_status'] == 'Cancelled' ? 'danger' : 
                                            ($visit['visit_status'] == 'In Progress' ? 'warning' : 'info')); 
                                    ?>" style="font-size: 12px; padding: 6px 12px;">
                                        <?php echo htmlspecialchars($visit['visit_status']); ?>
                                    </span>
                                    <span class="badge bg-secondary" style="font-size: 12px; padding: 6px 12px;">
                                        <?php echo htmlspecialchars($visit['visit_type']); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                        <?php if ($visit['visit_findings']): ?>
                        <div class="mt-2">
                            <strong>Findings:</strong>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($visit['visit_findings'])); ?></p>
                        </div>
                        <?php endif; ?>
                        <?php if ($visit['compliance_score'] !== null): ?>
                        <div class="mt-2">
                            <strong>Compliance Score:</strong> 
                            <span class="badge bg-<?php echo $visit['compliance_score'] >= 70 ? 'success' : ($visit['compliance_score'] >= 50 ? 'warning' : 'danger'); ?>">
                                <?php echo $visit['compliance_score']; ?>/100
                            </span>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                
                <!-- Status History -->
                <div class="info-card warning">
                    <div class="card-header-section">
                        <h5><i class="fas fa-history"></i>Status History</h5>
                    </div>
                    
                    <?php if (empty($history)): ?>
                    <p class="text-muted">No status changes recorded.</p>
                    <?php else: ?>
                    <?php foreach ($history as $h): ?>
                    <div class="history-item <?php echo $h['new_status'] == $application['current_status'] ? 'new' : ''; ?>">
                        <div class="d-flex align-items-start gap-3">
                            <div class="flex-grow-1">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <span class="badge bg-secondary" style="font-size: 11px;">
                                        <?php echo htmlspecialchars($h['previous_status']); ?>
                                    </span>
                                    <i class="fas fa-arrow-right text-muted" style="font-size: 12px;"></i>
                                    <span class="badge bg-primary" style="font-size: 11px;">
                                        <?php echo htmlspecialchars($h['new_status']); ?>
                                    </span>
                                </div>
                                <div class="small text-muted mb-2">
                                    <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($h['changed_by_name'] ?? 'System'); ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-clock me-1"></i><?php echo date('M d, Y g:i A', strtotime($h['date_changed'])); ?>
                                </div>
                                <?php if ($h['change_reason']): ?>
                                <div class="mt-2 p-3 bg-light rounded" style="border-left: 3px solid #667eea;">
                                    <strong style="font-size: 12px; color: #667eea;">Reason:</strong>
                                    <p class="mb-0 mt-1" style="font-size: 14px;"><?php echo htmlspecialchars($h['change_reason']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Right Column - Actions -->
            <div class="col-lg-4">
                <!-- Quick Stats -->
                <div class="quick-info-grid">
                    <div class="quick-info-item">
                        <div class="value"><?php echo count($documents); ?></div>
                        <div class="label">Documents</div>
                    </div>
                    <div class="quick-info-item">
                        <div class="value"><?php echo count($visits); ?></div>
                        <div class="label">Site Visits</div>
                    </div>
                </div>
                
                <div class="info-card">
                    <div class="card-header-section">
                        <h5><i class="fas fa-bolt"></i>Quick Actions</h5>
                    </div>
                    
                    <div class="action-btn-group">
                        <a href="evaluation-checklist.php?id=<?php echo $application_id; ?>" class="btn btn-info">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Evaluation Checklist</span>
                        </a>
                        
                        <?php if ($application['current_status'] == 'Submitted' || $application['current_status'] == 'Under Review'): ?>
                        <button class="btn btn-primary" onclick="updateStatus('<?php echo $application_id; ?>', '<?php echo $application['current_status']; ?>')">
                            <i class="fas fa-edit"></i>
                            <span>Update Status</span>
                        </button>
                        <?php endif; ?>
                        
                        <?php if ($application['current_status'] == 'Final Review'): ?>
                        <button class="btn btn-success" onclick="finalDecision('<?php echo $application_id; ?>')">
                            <i class="fas fa-gavel"></i>
                            <span>Final Decision</span>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Application Timeline -->
                <div class="info-card">
                    <div class="card-header-section">
                        <h5><i class="fas fa-stream"></i>Application Timeline</h5>
                    </div>
                    <div class="timeline">
                        <div class="timeline-item <?php echo strtotime($application['submitted_date']) <= time() ? 'completed' : ''; ?>">
                            <i class="fas fa-file-alt"></i>
                            <div class="timeline-item-content">
                                <strong>Submitted</strong>
                                <div class="small"><?php echo date('M d, Y', strtotime($application['submitted_date'])); ?></div>
                            </div>
                        </div>
                        
                        <?php if ($application['reviewed_date']): ?>
                        <div class="timeline-item completed">
                            <i class="fas fa-search"></i>
                            <div class="timeline-item-content">
                                <strong>Under Review</strong>
                                <div class="small"><?php echo date('M d, Y', strtotime($application['reviewed_date'])); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($visits)): ?>
                        <div class="timeline-item <?php echo array_filter($visits, fn($v) => $v['visit_status'] == 'Completed') ? 'completed' : ''; ?>">
                            <i class="fas fa-calendar-check"></i>
                            <div class="timeline-item-content">
                                <strong>Site Visit</strong>
                                <div class="small"><?php echo count($visits); ?> visit(s)</div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($application['current_status'] == 'Final Review' || $application['approved_date'] || $application['rejected_date']): ?>
                        <div class="timeline-item <?php echo $application['approved_date'] || $application['rejected_date'] ? 'completed' : ''; ?>">
                            <i class="fas fa-clipboard-check"></i>
                            <div class="timeline-item-content">
                                <strong>Final Review</strong>
                                <?php if ($application['approved_date'] || $application['rejected_date']): ?>
                                <div class="small">
                                    <?php echo date('M d, Y', strtotime($application['approved_date'] ?? $application['rejected_date'])); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($application['approved_date']): ?>
                        <div class="timeline-item completed">
                            <i class="fas fa-check-circle"></i>
                            <div class="timeline-item-content">
                                <strong>Approved</strong>
                                <div class="small"><?php echo date('M d, Y', strtotime($application['approved_date'])); ?></div>
                                <?php if ($application['certificate_number']): ?>
                                <div class="small mt-2">
                                    <span class="badge bg-success">Certificate: <?php echo htmlspecialchars($application['certificate_number']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($application['rejected_date']): ?>
                        <div class="timeline-item completed">
                            <i class="fas fa-times-circle"></i>
                            <div class="timeline-item-content">
                                <strong>Rejected</strong>
                                <div class="small"><?php echo date('M d, Y', strtotime($application['rejected_date'])); ?></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        </div> <!-- /#hcbPrintArea -->
    </div>
    
    <!-- Schedule Visit Modal -->
    <div class="modal fade" id="visitModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule Site Visit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" id="visit_application_id" value="<?php echo $application_id; ?>">
                        
                        <div class="mb-3">
                            <label class="form-label">Visit Type <span class="text-danger">*</span></label>
                            <select class="form-select" name="visit_type" required>
                                <option value="">Select visit type...</option>
                                <option value="Initial">Initial Visit</option>
                                <option value="Follow-up">Follow-up Visit</option>
                                <option value="Final">Final Visit</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" name="visit_date" required min="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Time <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" name="visit_time" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="visit_notes" rows="3" placeholder="Additional notes for the visit (optional)..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="schedule_visit" class="btn btn-success">
                            <i class="fas fa-calendar-plus me-2"></i>Schedule Visit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        function reviewDocument(triggerEl, documentId, documentName, action) {
            const isApprove = action === 'approve';
            const config = isApprove ? {
                title: 'Approve Document?',
                html: `<p>Are you sure you want to approve the document:</p><p><strong>${documentName}</strong></p><p class="text-muted small">The company will be notified of this approval.</p>`,
                icon: 'question',
                confirmButtonText: 'Yes, Approve',
                confirmButtonColor: '#48bb78'
            } : {
                title: 'Reject Document?',
                html: `<p>Please provide a reason for rejecting:</p><p><strong>${documentName}</strong></p>`,
                icon: 'warning',
                input: 'textarea',
                inputPlaceholder: 'Enter rejection reason (required)...',
                inputAttributes: { required: true, minlength: 10 },
                confirmButtonText: 'Yes, Reject',
                confirmButtonColor: '#f56565'
            };
            Swal.fire({
                ...config,
                showCancelButton: true,
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                inputValidator: (value) => {
                    if (!isApprove && (!value || value.length < 10)) {
                        return 'Please provide a reason (at least 10 characters)';
                    }
                }
            }).then(async (result) => {
                if (!result.isConfirmed) return;
                try {
                    Swal.fire({ title: 'Saving...', html: 'Updating document status', allowOutsideClick: false, allowEscapeKey: false, didOpen: () => Swal.showLoading() });
                    const fd = new FormData();
                    fd.append('document_id', documentId);
                    fd.append('review_action', isApprove ? 'Uploaded' : 'Rejected');
                    if (!isApprove) fd.append('review_notes', result.value || '');
                    const res = await fetch('ajax-review-document.php', { method: 'POST', body: fd });
                    const data = await res.json();
                    if (!data || !data.success) throw new Error((data && data.message) || 'Request failed');
                    Swal.close();
                    // Update row UI
                    // Prefer the row from the clicked button; fallback to attribute lookup
                    let row = triggerEl ? triggerEl.closest('.document-item') : null;
                    if (!row) {
                        row = document.querySelector(`[data-document-id="${documentId}"]`);
                    }
                    if (row) {
                        row.classList.remove('pending', 'uploaded', 'rejected');
                        row.classList.add(isApprove ? 'uploaded' : 'rejected');
                        const badge = row.querySelector('.badge');
                        if (badge) {
                            badge.className = 'badge ' + (isApprove ? 'bg-success' : 'bg-danger');
                            badge.textContent = isApprove ? 'Approved' : 'Rejected';
                        }
                        const rej = row.querySelector('.ajax-rejection-reason');
                        if (!isApprove) {
                            if (rej) {
                                rej.style.display = '';
                                rej.innerHTML = '<strong>Rejection Reason:</strong> ' + (result.value || 'Please review and resubmit.');
                            }
                        } else if (rej) {
                            rej.remove();
                        }
                        // Remove Approve/Reject buttons after final decision (approve or reject)
                        const approveBtn = row.querySelector('button.btn.btn-sm.btn-success');
                        const rejectBtn = row.querySelector('button.btn.btn-sm.btn-danger');
                        if (approveBtn) approveBtn.remove();
                        if (rejectBtn) rejectBtn.remove();
                    }
                    Swal.fire({ icon: 'success', title: 'Success!', text: data.message, confirmButtonColor: '#48bb78' });
                } catch (e) {
                    Swal.fire({ icon: 'error', title: 'Error', text: e.message || 'Failed to update', confirmButtonColor: '#f56565' });
                }
            });
        }
        
        function scheduleVisit(applicationId) {
            document.getElementById('visit_application_id').value = applicationId;
            // Set minimum date to today
            const visitDateInput = document.querySelector('#visitModal input[name="visit_date"]');
            if (visitDateInput) {
                visitDateInput.min = new Date().toISOString().split('T')[0];
            }
            new bootstrap.Modal(document.getElementById('visitModal')).show();
        }
        
        function updateStatus(applicationId, currentStatus) {
            window.location.href = 'applications.php?update_status=' + applicationId;
        }
        
        function finalDecision(applicationId) {
            window.location.href = 'applications.php?final_decision=' + applicationId;
        }
        
        function printApplication() {
            window.print();
        }
        
        // Auto-dismiss success/error messages
        <?php if (isset($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($success_message); ?>',
            showConfirmButton: true,
            confirmButtonText: 'OK',
            confirmButtonColor: '#48bb78'
        }).then(() => {
            // Remove the success message from URL to prevent re-display on refresh
            if (window.history.replaceState) {
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo addslashes($error_message); ?>',
            showConfirmButton: true,
            confirmButtonText: 'OK',
            confirmButtonColor: '#f56565'
        });
        <?php endif; ?>
    </script>
    
</body>
</html>

