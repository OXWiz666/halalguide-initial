<?php
include '../common/session.php';
include '../common/connection.php';
include '../common/randomstrings.php';

date_default_timezone_set('Asia/Manila');

// Check login and access
check_login();
check_access('Admin');

// Logout handler
if (isset($_GET['logout'])) {
    logout();
}

$admin_id = $_SESSION['admin_id'];
$organization_id = $_SESSION['organization_id'];

// Get organization info
$org_query = mysqli_query($conn, "SELECT * FROM tbl_organization WHERE organization_id = '$organization_id'");
$org_row = mysqli_fetch_assoc($org_query);
$organization_name = $org_row['organization_name'] ?? 'Certifying Body';

// Handle status updates
if (isset($_POST['update_status'])) {
    $application_id = mysqli_real_escape_string($conn, $_POST['application_id']);
    $new_status = mysqli_real_escape_string($conn, $_POST['new_status']);
    $change_reason = mysqli_real_escape_string($conn, $_POST['change_reason'] ?? '');
    
    // Get current status
    $current_query = mysqli_query($conn, "SELECT current_status FROM tbl_certification_application WHERE application_id = '$application_id'");
    $current_row = mysqli_fetch_assoc($current_query);
    $previous_status = $current_row['current_status'] ?? '';
    
    // Validate required documents before approving
    if ($new_status == 'Approved') {
        // Check if evaluation checklist is completed
        $eval_check = mysqli_query($conn, "SELECT evaluation_id FROM tbl_application_evaluation WHERE application_id = '$application_id'");
        $evaluation_exists = mysqli_fetch_assoc($eval_check);
        
        if (!$evaluation_exists) {
            $error_message = "Cannot approve application. The evaluation checklist must be completed first. Please complete the evaluation checklist before approving.";
        } else {
            // Check if all checklist items are completed (must have 10 items with answers)
            $checklist_count_query = "SELECT COUNT(*) as item_count FROM tbl_evaluation_checklist_items 
                                      WHERE evaluation_id = '" . $evaluation_exists['evaluation_id'] . "' 
                                      AND answer IS NOT NULL AND answer != ''";
            $checklist_count_result = mysqli_query($conn, $checklist_count_query);
            $checklist_count_row = mysqli_fetch_assoc($checklist_count_result);
            $completed_items = $checklist_count_row['item_count'] ?? 0;
            
            // There should be 10 checklist items total
            $total_items_query = "SELECT COUNT(*) as total_count FROM tbl_evaluation_checklist_items 
                                  WHERE evaluation_id = '" . $evaluation_exists['evaluation_id'] . "'";
            $total_items_result = mysqli_query($conn, $total_items_query);
            $total_items_row = mysqli_fetch_assoc($total_items_result);
            $total_items = $total_items_row['total_count'] ?? 0;
            
            if ($total_items < 10 || $completed_items < 10) {
                $error_message = "Cannot approve application. The evaluation checklist is incomplete. All 10 checklist items must be completed with answers before approval.";
            }
        }
        
        // Get all required documents from checklist
        $required_docs_query = "SELECT document_type, document_name FROM tbl_document_checklist WHERE status_id = 1 AND is_required = 1";
        $required_docs_result = mysqli_query($conn, $required_docs_query);
        $required_documents = [];
        while ($doc_row = mysqli_fetch_assoc($required_docs_result)) {
            $required_documents[$doc_row['document_type']] = $doc_row['document_name'];
        }
        
        if (!empty($required_documents) && !isset($error_message)) {
            // Get all uploaded and approved documents for this application
            // Get latest version of each document type
            $uploaded_docs_query = "SELECT d1.* 
                                     FROM tbl_application_documents d1
                                     INNER JOIN (
                                         SELECT document_type, MAX(date_added) as max_date
                                         FROM tbl_application_documents
                                         WHERE application_id = '$application_id'
                                         GROUP BY document_type
                                     ) d2 ON d1.document_type = d2.document_type AND d1.date_added = d2.max_date
                                     WHERE d1.application_id = '$application_id'";
            $uploaded_docs_result = mysqli_query($conn, $uploaded_docs_query);
            
            $approved_docs = [];
            $missing_docs = [];
            $pending_docs = [];
            $pending_docs_names = [];
            
            while ($uploaded_doc = mysqli_fetch_assoc($uploaded_docs_result)) {
                $approved_docs[] = $uploaded_doc['document_type'];
                
                // Check if it's approved
                if ($uploaded_doc['upload_status'] != 'Uploaded') {
                    $pending_docs[] = $uploaded_doc['document_type'];
                }
            }
            
            // Check for missing required documents
            foreach ($required_documents as $doc_type => $doc_name) {
                if (!in_array($doc_type, $approved_docs)) {
                    $missing_docs[] = $doc_name;
                } elseif (in_array($doc_type, $pending_docs)) {
                    $pending_docs_names[] = $doc_name;
                }
            }
            
            // If there are missing or pending documents, prevent approval
            if (!empty($missing_docs) || !empty($pending_docs_names)) {
                $error_list = [];
                if (!empty($missing_docs)) {
                    $error_list[] = "Missing required documents: " . implode(", ", $missing_docs);
                }
                if (!empty($pending_docs_names)) {
                    $error_list[] = "Pending approval documents: " . implode(", ", $pending_docs_names);
                }
                
                $existing_error = isset($error_message) ? $error_message . "\n\n" : "";
                $error_message = $existing_error . "Cannot approve application. Please complete all required documents first.\n\n" . implode("\n", $error_list);
            }
        }
    }
    
    // If validation failed, don't proceed
    if (isset($error_message)) {
        // Error message will be displayed, skip status update
    } else {
        // Update application status
        $update_query = "UPDATE tbl_certification_application SET 
            current_status = '$new_status',
            date_updated = NOW()";
        
        // Set specific fields based on status
        if ($new_status == 'Under Review') {
            $update_query .= ", reviewed_by = '$admin_id', reviewed_date = NOW()";
        } elseif ($new_status == 'Approved') {
            $update_query .= ", approved_by = '$admin_id', approved_date = NOW()";
            // Generate certificate number if not exists
            $cert_check = mysqli_query($conn, "SELECT certificate_number FROM tbl_certification_application WHERE application_id = '$application_id'");
            $cert_row = mysqli_fetch_assoc($cert_check);
            if (empty($cert_row['certificate_number'])) {
                $certificate_number = 'HCB-' . date('Y') . '-' . strtoupper(substr(generate_string($permitted_chars, 8), 0, 8));
                $update_query .= ", certificate_number = '$certificate_number', certificate_issue_date = CURDATE(), certificate_expiry_date = DATE_ADD(CURDATE(), INTERVAL 1 YEAR)";
            }
        } elseif ($new_status == 'Rejected') {
            $update_query .= ", rejected_by = '$admin_id', rejected_date = NOW(), rejection_reason = '$change_reason'";
        }
        
        $update_query .= " WHERE application_id = '$application_id'";
        
        mysqli_begin_transaction($conn);
        
        try {
            // Update application
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Error updating application: " . mysqli_error($conn));
            }
            
            // If approved, update company status to Halal-Certified
            if ($new_status == 'Approved') {
                $app_query = mysqli_query($conn, "SELECT company_id FROM tbl_certification_application WHERE application_id = '$application_id'");
                $app_row = mysqli_fetch_assoc($app_query);
                $company_id = $app_row['company_id'] ?? '';
                
                if ($company_id) {
                    $cert_status_id = 4; // Halal-Certified status
                    $company_update = "UPDATE tbl_company SET status_id = '$cert_status_id'";
                    // Also update cert_status if column exists
                    $cert_status_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_company LIKE 'cert_status'");
                    if (mysqli_num_rows($cert_status_check) > 0) {
                        $company_update .= ", cert_status = 'Halal-Certified'";
                    }
                    $company_update .= " WHERE company_id = '$company_id'";
                    mysqli_query($conn, $company_update);
                }
            }
            
            // Add to status history
            $history_id = generate_string($permitted_chars, 25);
            $history_query = "INSERT INTO tbl_application_status_history 
                (history_id, application_id, previous_status, new_status, changed_by, changed_by_type, change_reason, date_changed) 
                VALUES ('$history_id', '$application_id', '$previous_status', '$new_status', '$admin_id', 'Admin', '$change_reason', NOW())";
            
            if (!mysqli_query($conn, $history_query)) {
                throw new Exception("Error creating history: " . mysqli_error($conn));
            }
            
            // Create notification
            $notification_id = generate_string($permitted_chars, 25);
            $app_query = mysqli_query($conn, "SELECT company_id, application_number, certificate_number FROM tbl_certification_application WHERE application_id = '$application_id'");
            $app_row = mysqli_fetch_assoc($app_query);
            $company_id = $app_row['company_id'] ?? '';
            $app_number = $app_row['application_number'] ?? '';
            $cert_number = $app_row['certificate_number'] ?? '';
            
            if ($new_status == 'Approved') {
                $subject = "Application Approved - Certificate Issued";
                $message = "Congratulations! Your certification application #$app_number has been approved. ";
                if (!empty($cert_number)) {
                    $message .= "Certificate Number: $cert_number. ";
                }
                $message .= "Your company is now Halal-Certified.";
            } else {
                $subject = "Application Status Updated";
                $message = "Your certification application #$app_number status has been changed from $previous_status to $new_status.";
                if ($new_status == 'Rejected' && !empty($change_reason)) {
                    $message .= " Reason: $change_reason";
                }
            }
            
            $notif_query = "INSERT INTO tbl_application_notifications 
                (notification_id, application_id, notification_type, recipient_type, recipient_id, subject, message, date_added) 
                VALUES ('$notification_id', '$application_id', 'Status Change', 'Company', '$company_id', '$subject', '$message', NOW())";
            
            mysqli_query($conn, $notif_query); // Don't fail if notification fails
            
            mysqli_commit($conn);
            $success_message = "Status updated successfully!";
        
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    }
}

// Handle document approval/rejection
if (isset($_POST['review_document'])) {
    $document_id = mysqli_real_escape_string($conn, $_POST['document_id']);
    $review_action = mysqli_real_escape_string($conn, $_POST['review_action']);
    $review_notes = mysqli_real_escape_string($conn, $_POST['review_notes'] ?? '');
    
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
    
    if (mysqli_query($conn, $update_query)) {
        $success_message = "Document review updated successfully!";
    } else {
        $error_message = "Error updating document review: " . mysqli_error($conn);
    }
}

// Handle site visit scheduling
if (isset($_POST['schedule_visit'])) {
    $application_id = mysqli_real_escape_string($conn, $_POST['application_id']);
    $visit_date = mysqli_real_escape_string($conn, $_POST['visit_date']);
    $visit_time = mysqli_real_escape_string($conn, $_POST['visit_time']);
    $visit_type = mysqli_real_escape_string($conn, $_POST['visit_type']);
    $notes = mysqli_real_escape_string($conn, $_POST['visit_notes'] ?? '');
    
    $scheduled_datetime = $visit_date . ' ' . $visit_time . ':00';
    
    $visit_id = generate_string($permitted_chars, 25);
    
    $insert_query = "INSERT INTO tbl_application_visits 
        (visit_id, application_id, visit_type, scheduled_date, scheduled_by, visit_status, notes, date_added) 
        VALUES ('$visit_id', '$application_id', '$visit_type', '$scheduled_datetime', '$admin_id', 'Scheduled', '$notes', NOW())";
    
    if (mysqli_query($conn, $insert_query)) {
        // Update application status if scheduling initial visit
        if ($visit_type == 'Initial') {
            mysqli_query($conn, "UPDATE tbl_certification_application SET current_status = 'Scheduled for Visit', date_updated = NOW() WHERE application_id = '$application_id'");
        }
        
        // Create notification
        $notification_id = generate_string($permitted_chars, 25);
        $app_query = mysqli_query($conn, "SELECT company_id FROM tbl_certification_application WHERE application_id = '$application_id'");
        $app_row = mysqli_fetch_assoc($app_query);
        $company_id = $app_row['company_id'] ?? '';
        
        $subject = "Site Visit Scheduled";
        $message = "A site visit has been scheduled for your certification application on " . date('F d, Y g:i A', strtotime($scheduled_datetime)) . ".";
        
        $notif_query = "INSERT INTO tbl_application_notifications 
            (notification_id, application_id, notification_type, recipient_type, recipient_id, subject, message, date_added) 
            VALUES ('$notification_id', '$application_id', 'Visit Scheduled', 'Company', '$company_id', '$subject', '$message', NOW())";
        
        mysqli_query($conn, $notif_query);
        
        $success_message = "Site visit scheduled successfully!";
    } else {
        $error_message = "Error scheduling visit: " . mysqli_error($conn);
    }
}

// Handle final approval/rejection
if (isset($_POST['final_decision'])) {
    $application_id = mysqli_real_escape_string($conn, $_POST['application_id']);
    $decision = mysqli_real_escape_string($conn, $_POST['decision']);
    $certificate_number = mysqli_real_escape_string($conn, $_POST['certificate_number'] ?? '');
    $certificate_issue_date = mysqli_real_escape_string($conn, $_POST['certificate_issue_date'] ?? '');
    $certificate_expiry_date = mysqli_real_escape_string($conn, $_POST['certificate_expiry_date'] ?? '');
    $decision_reason = mysqli_real_escape_string($conn, $_POST['decision_reason'] ?? '');
    
    // Validate required documents before approving
    if ($decision == 'Approve') {
        // Check if evaluation checklist is completed
        $eval_check = mysqli_query($conn, "SELECT evaluation_id FROM tbl_application_evaluation WHERE application_id = '$application_id'");
        $evaluation_exists = mysqli_fetch_assoc($eval_check);
        
        if (!$evaluation_exists) {
            $error_message = "Cannot approve application. The evaluation checklist must be completed first. Please complete the evaluation checklist before approving.";
        } else {
            // Check if all checklist items are completed (must have 10 items with answers)
            $checklist_count_query = "SELECT COUNT(*) as item_count FROM tbl_evaluation_checklist_items 
                                      WHERE evaluation_id = '" . $evaluation_exists['evaluation_id'] . "' 
                                      AND answer IS NOT NULL AND answer != ''";
            $checklist_count_result = mysqli_query($conn, $checklist_count_query);
            $checklist_count_row = mysqli_fetch_assoc($checklist_count_result);
            $completed_items = $checklist_count_row['item_count'] ?? 0;
            
            // There should be 10 checklist items total
            $total_items_query = "SELECT COUNT(*) as total_count FROM tbl_evaluation_checklist_items 
                                  WHERE evaluation_id = '" . $evaluation_exists['evaluation_id'] . "'";
            $total_items_result = mysqli_query($conn, $total_items_query);
            $total_items_row = mysqli_fetch_assoc($total_items_result);
            $total_items = $total_items_row['total_count'] ?? 0;
            
            if ($total_items < 10 || $completed_items < 10) {
                $error_message = "Cannot approve application. The evaluation checklist is incomplete. All 10 checklist items must be completed with answers before approval.";
            }
        }
        
        // Get all required documents from checklist
        $required_docs_query = "SELECT document_type, document_name FROM tbl_document_checklist WHERE status_id = 1 AND is_required = 1";
        $required_docs_result = mysqli_query($conn, $required_docs_query);
        $required_documents = [];
        while ($doc_row = mysqli_fetch_assoc($required_docs_result)) {
            $required_documents[$doc_row['document_type']] = $doc_row['document_name'];
        }
        
        if (!empty($required_documents) && !isset($error_message)) {
            // Get all uploaded and approved documents for this application
            // Get latest version of each document type
            $uploaded_docs_query = "SELECT d1.* 
                                     FROM tbl_application_documents d1
                                     INNER JOIN (
                                         SELECT document_type, MAX(date_added) as max_date
                                         FROM tbl_application_documents
                                         WHERE application_id = '$application_id'
                                         GROUP BY document_type
                                     ) d2 ON d1.document_type = d2.document_type AND d1.date_added = d2.max_date
                                     WHERE d1.application_id = '$application_id'";
            $uploaded_docs_result = mysqli_query($conn, $uploaded_docs_query);
            
            $approved_docs = [];
            $missing_docs = [];
            $pending_docs = [];
            $pending_docs_names = [];
            
            while ($uploaded_doc = mysqli_fetch_assoc($uploaded_docs_result)) {
                $approved_docs[] = $uploaded_doc['document_type'];
                
                // Check if it's approved
                if ($uploaded_doc['upload_status'] != 'Uploaded') {
                    $pending_docs[] = $uploaded_doc['document_type'];
                }
            }
            
            // Check for missing required documents
            foreach ($required_documents as $doc_type => $doc_name) {
                if (!in_array($doc_type, $approved_docs)) {
                    $missing_docs[] = $doc_name;
                } elseif (in_array($doc_type, $pending_docs)) {
                    $pending_docs_names[] = $doc_name;
                }
            }
            
            // If there are missing or pending documents, prevent approval
            if (!empty($missing_docs) || !empty($pending_docs_names)) {
                $error_list = [];
                if (!empty($missing_docs)) {
                    $error_list[] = "Missing required documents: " . implode(", ", $missing_docs);
                }
                if (!empty($pending_docs_names)) {
                    $error_list[] = "Pending approval documents: " . implode(", ", $pending_docs_names);
                }
                
                $existing_error = isset($error_message) ? $error_message . "\n\n" : "";
                $error_message = $existing_error . "Cannot approve application. Please complete all required documents first.\n\n" . implode("\n", $error_list);
            }
        }
    }
    
    // If validation failed, don't proceed
    if (isset($error_message)) {
        // Error message will be displayed, skip approval
    } else {
        mysqli_begin_transaction($conn);
        
        try {
            if ($decision == 'Approve') {
            // Generate certificate number if not provided
            if (empty($certificate_number)) {
                $certificate_number = 'HCB-' . date('Y') . '-' . strtoupper(substr(generate_string($permitted_chars, 8), 0, 8));
            }
            
            // Set expiry date if not provided (1 year from issue)
            if (empty($certificate_expiry_date) && !empty($certificate_issue_date)) {
                $cert_expiry = date('Y-m-d', strtotime($certificate_issue_date . ' +1 year'));
            } else {
                $cert_expiry = $certificate_expiry_date;
            }
            
            // Update application - HCB directly approves (no Super Admin needed for company certifications)
            $update_query = "UPDATE tbl_certification_application SET 
                current_status = 'Approved',
                approved_by = '$admin_id',
                approved_date = NOW(),
                certificate_number = '$certificate_number',
                certificate_issue_date = '$certificate_issue_date',
                certificate_expiry_date = '$cert_expiry',
                date_updated = NOW()
                WHERE application_id = '$application_id'";
            
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Error approving application: " . mysqli_error($conn));
            }
            
            // Update company status to Halal-Certified
            $app_query = mysqli_query($conn, "SELECT company_id FROM tbl_certification_application WHERE application_id = '$application_id'");
            $app_row = mysqli_fetch_assoc($app_query);
            $company_id = $app_row['company_id'] ?? '';
            
            if ($company_id) {
                $cert_status_id = 4; // Halal-Certified status
                $company_update = "UPDATE tbl_company SET status_id = '$cert_status_id'";
                $cert_status_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_company LIKE 'cert_status'");
                if (mysqli_num_rows($cert_status_check) > 0) {
                    $company_update .= ", cert_status = 'Halal-Certified'";
                }
                $company_update .= " WHERE company_id = '$company_id'";
                mysqli_query($conn, $company_update);
            }
            
        } else {
            // Reject application
            $update_query = "UPDATE tbl_certification_application SET 
                current_status = 'Rejected',
                rejected_by = '$admin_id',
                rejected_date = NOW(),
                rejection_reason = '$decision_reason',
                date_updated = NOW()
                WHERE application_id = '$application_id'";
            
            if (!mysqli_query($conn, $update_query)) {
                throw new Exception("Error rejecting application: " . mysqli_error($conn));
            }
        }
        
        // Add to status history
        $history_id = generate_string($permitted_chars, 25);
        $current_query = mysqli_query($conn, "SELECT current_status FROM tbl_certification_application WHERE application_id = '$application_id'");
        $current_row = mysqli_fetch_assoc($current_query);
        $previous_status = $current_row['current_status'] ?? '';
        
        $new_status = $decision == 'Approve' ? 'Approved' : 'Rejected';
        
        $history_query = "INSERT INTO tbl_application_status_history 
            (history_id, application_id, previous_status, new_status, changed_by, changed_by_type, change_reason, date_changed) 
            VALUES ('$history_id', '$application_id', '$previous_status', '$new_status', '$admin_id', 'Admin', '$decision_reason', NOW())";
        
        if (!mysqli_query($conn, $history_query)) {
            throw new Exception("Error creating history: " . mysqli_error($conn));
        }
        
        // Create notification
        $notification_id = generate_string($permitted_chars, 25);
        $app_query = mysqli_query($conn, "SELECT company_id FROM tbl_certification_application WHERE application_id = '$application_id'");
        $app_row = mysqli_fetch_assoc($app_query);
        $company_id = $app_row['company_id'] ?? '';
        
        // Create notification
        $subject = $decision == 'Approve' ? "Application Approved - Certificate Issued" : "Application Rejected";
        $message = $decision == 'Approve' 
            ? "Congratulations! Your certification application has been approved. Certificate Number: $certificate_number. Valid until: " . date('M d, Y', strtotime($cert_expiry)) . "."
            : "Your certification application has been rejected. Reason: $decision_reason";
        
        $notif_query = "INSERT INTO tbl_application_notifications 
            (notification_id, application_id, notification_type, recipient_type, recipient_id, subject, message, date_added) 
            VALUES ('$notification_id', '$application_id', 'Final Decision', 'Company', '$company_id', '$subject', '$message', NOW())";
        
        mysqli_query($conn, $notif_query);
        
        mysqli_commit($conn);
        $success_message = $decision == 'Approve' ? "Application approved successfully! Certificate Number: $certificate_number" : "Application rejected.";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Pagination
$per_page = 15; // Items per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page); // Ensure page is at least 1
$offset = ($page - 1) * $per_page;

// Build query
$where_clause = "ca.organization_id = '$organization_id'";
if (!empty($status_filter)) {
    $where_clause .= " AND ca.current_status = '$status_filter'";
}
if (!empty($search)) {
    $where_clause .= " AND (c.company_name LIKE '%$search%' OR ca.application_number LIKE '%$search%' OR ca.certificate_number LIKE '%$search%')";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total
FROM tbl_certification_application ca
LEFT JOIN tbl_company c ON ca.company_id = c.company_id
WHERE $where_clause";

$count_result = mysqli_query($conn, $count_query);
$total_applications = 0;
if ($count_result) {
    $count_row = mysqli_fetch_assoc($count_result);
    $total_applications = $count_row['total'];
}

$total_pages = ceil($total_applications / $per_page);

// Get applications with pagination
$applications_query = "SELECT 
    ca.*,
    c.company_name,
    c.email as company_email,
    c.contant_no as company_contact,
    c.usertype_id,
    ut.usertype,
    ua.username as reviewed_by_username
FROM tbl_certification_application ca
LEFT JOIN tbl_company c ON ca.company_id = c.company_id
LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
LEFT JOIN tbl_useraccount ua ON ca.reviewed_by = ua.useraccount_id
WHERE $where_clause
ORDER BY ca.submitted_date DESC, ca.date_added DESC
LIMIT $per_page OFFSET $offset";

$applications_result = mysqli_query($conn, $applications_query);
$applications = [];

if ($applications_result) {
    while ($row = mysqli_fetch_assoc($applications_result)) {
        $applications[] = $row;
    }
}

// Get status counts for filter badges
$status_counts_query = "SELECT 
    current_status,
    COUNT(*) as count
FROM tbl_certification_application
WHERE organization_id = '$organization_id'
GROUP BY current_status";
$status_counts_result = mysqli_query($conn, $status_counts_query);
$status_counts = [];
while ($row = mysqli_fetch_assoc($status_counts_result)) {
    $status_counts[$row['current_status']] = $row['count'];
}

// Status definitions with colors and icons
$status_config = [
    'Submitted' => ['color' => '#667eea', 'icon' => 'fa-file-alt', 'badge' => 'primary'],
    'Under Review' => ['color' => '#ed8936', 'icon' => 'fa-search', 'badge' => 'warning'],
    'Scheduled for Visit' => ['color' => '#48bb78', 'icon' => 'fa-calendar-check', 'badge' => 'success'],
    'Final Review' => ['color' => '#4299e1', 'icon' => 'fa-clipboard-check', 'badge' => 'info'],
    'Approved' => ['color' => '#48bb78', 'icon' => 'fa-check-circle', 'badge' => 'success'],
    'Rejected' => ['color' => '#f56565', 'icon' => 'fa-times-circle', 'badge' => 'danger']
];

// Status workflow (allowed transitions)
$status_workflow = [
    'Under Review' => ['Scheduled for Visit', 'Approved', 'Rejected'],
    'Scheduled for Visit' => ['Final Review', 'Scheduled for Visit', 'Approved', 'Rejected'], // Can schedule follow-up
    'Final Review' => ['Approved', 'Rejected'],
    'Submitted' => ['Under Review', 'Rejected'] // Keep for backward compatibility
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certification Applications | HCB Portal</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 -->
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
        
        /* Sidebar and main content styles included in sidebar.php */
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-badge.primary { background: #e0e7ff; color: #667eea; }
        .status-badge.warning { background: #feebc8; color: #ed8936; }
        .status-badge.success { background: #c6f6d5; color: #48bb78; }
        .status-badge.info { background: #bee3f8; color: #4299e1; }
        .status-badge.danger { background: #fed7d7; color: #f56565; }
        
        /* Statistics Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
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
        .stat-card.info { border-left-color: #4299e1; }
        .stat-card.danger { border-left-color: #f56565; }
        
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
        .stat-card.info .stat-icon { background: rgba(66, 153, 225, 0.1); color: #4299e1; }
        .stat-card.danger .stat-icon { background: rgba(245, 101, 101, 0.1); color: #f56565; }
        
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
        
        .applications-table {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .applications-table table {
            margin: 0;
        }
        
        .applications-table thead th {
            background: #f7fafc;
            border-bottom: 2px solid #e2e8f0;
            font-weight: 600;
            color: #2d3748;
            padding: 15px;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .applications-table tbody td {
            padding: 15px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: middle;
        }
        
        .applications-table tbody tr:hover {
            background: #f7fafc;
        }
        
        .applications-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .table-actions {
            display: flex;
            gap: 6px;
            flex-wrap: wrap;
        }
        
        .table-actions .btn {
            padding: 5px 10px;
            font-size: 11px;
            white-space: nowrap;
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
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
        
        .filter-tab:hover {
            border-color: #667eea;
            color: #667eea;
            transform: translateY(-2px);
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.15);
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
            font-weight: 600;
        }
        
        .filter-tab:not(.active) .badge {
            background: #e2e8f0;
            color: #4a5568;
        }
        
        .search-section {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        }
    </style>
    
    <!-- Sidebar Styles (reuse from index.php) -->
    <link rel="stylesheet" href="styles_sidebar.php" onerror="this.onerror=null;">
</head>
<body>
    <?php 
    // Include sidebar - we'll need to create a reusable sidebar component
    include 'includes/sidebar.php'; 
    ?>
    
    <div class="main-content">
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="page-title">
                <h2>Certification Applications</h2>
                <p>Manage and review halal certification applications</p>
            </div>
            
            <div class="user-menu">
                <button class="notification-btn" style="position: relative; width: 40px; height: 40px; border-radius: 10px; background: #f7fafc; border: none; color: #4a5568; display: flex; align-items: center; justify-content: center; transition: all 0.3s;">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge" style="position: absolute; top: -5px; right: -5px; width: 18px; height: 18px; background: #f56565; border-radius: 50%; font-size: 10px; color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">0</span>
                </button>
                
                <div class="user-dropdown" style="display: flex; align-items: center; gap: 12px; padding: 8px 15px; border-radius: 10px; background: #f7fafc; cursor: pointer; transition: all 0.3s;">
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
        
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card primary">
                <div class="stat-icon">
                    <i class="fas fa-file-alt"></i>
                </div>
                <div class="stat-value"><?php echo $total_applications; ?></div>
                <div class="stat-label">Total Applications</div>
            </div>
            
            <?php 
            $status_stats = [
                'Submitted' => ['icon' => 'fa-file-alt', 'class' => 'primary'],
                'Under Review' => ['icon' => 'fa-search', 'class' => 'warning'],
                'Approved' => ['icon' => 'fa-check-circle', 'class' => 'success'],
                'Rejected' => ['icon' => 'fa-times-circle', 'class' => 'danger']
            ];
            foreach ($status_stats as $stat_status => $stat_info):
                $count = $status_counts[$stat_status] ?? 0;
            ?>
            <div class="stat-card <?php echo $stat_info['class']; ?>">
                <div class="stat-icon">
                    <i class="fas <?php echo $stat_info['icon']; ?>"></i>
                </div>
                <div class="stat-value"><?php echo $count; ?></div>
                <div class="stat-label"><?php echo $stat_status; ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Search and Filter Section -->
        <div class="search-section">
            <form method="GET" class="row g-3">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($status_filter); ?>">
                <div class="col-md-8">
                    <div class="input-group">
                        <span class="input-group-text bg-white"><i class="fas fa-search text-muted"></i></span>
                        <input type="text" name="search" class="form-control" placeholder="Search by company name, application number, or certificate number..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Search
                    </button>
                </div>
            </form>
        </div>
        
        <!-- Filter Tabs -->
        <div class="filter-tabs">
            <a href="applications.php<?php echo !empty($search) ? '?search=' . urlencode($search) : ''; ?>" class="filter-tab <?php echo empty($status_filter) ? 'active' : ''; ?>">
                <i class="fas fa-list"></i>
                All Applications
                <span class="badge"><?php echo $total_applications; ?></span>
            </a>
            <?php foreach ($status_config as $status => $config): ?>
            <a href="applications.php?status=<?php echo urlencode($status); ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
               class="filter-tab <?php echo $status_filter == $status ? 'active' : ''; ?>">
                <i class="fas <?php echo $config['icon']; ?>"></i>
                <?php echo $status; ?>
                <span class="badge"><?php echo $status_counts[$status] ?? 0; ?></span>
            </a>
            <?php endforeach; ?>
        </div>
        
        <!-- Applications Table -->
        <div class="content-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0">Applications (<?php echo $total_applications; ?>)</h5>
                <small class="text-muted">Showing <?php echo count($applications); ?> of <?php echo $total_applications; ?> applications</small>
            </div>
            
            <?php if (empty($applications)): ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                <p class="text-muted">No applications found.</p>
            </div>
            <?php else: ?>
            
            <div class="applications-table">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Application #</th>
                            <th>Company</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Submitted</th>
                            <th>Contact</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($applications as $app): ?>
                        <tr>
                            <td>
                                <strong class="text-primary"><?php echo htmlspecialchars($app['application_number']); ?></strong>
                                <?php if (!empty($app['certificate_number'])): ?>
                                <br><small class="text-success"><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($app['certificate_number']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($app['company_name']); ?></strong>
                                <br><small class="text-muted"><?php echo htmlspecialchars($app['usertype'] ?? 'N/A'); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($app['application_type']); ?></span>
                            </td>
                            <td>
                                <span class="status-badge <?php echo $status_config[$app['current_status']]['badge'] ?? 'primary'; ?>">
                                    <i class="fas <?php echo $status_config[$app['current_status']]['icon'] ?? 'fa-circle'; ?>"></i>
                                    <?php echo htmlspecialchars($app['current_status']); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo date('M d, Y', strtotime($app['submitted_date'])); ?></small>
                                <?php if ($app['reviewed_date']): ?>
                                <br><small class="text-muted">Reviewed: <?php echo date('M d', strtotime($app['reviewed_date'])); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($app['company_email'])): ?>
                                <div><i class="fas fa-envelope text-muted"></i> <small><?php echo htmlspecialchars(substr($app['company_email'], 0, 25)); ?><?php echo strlen($app['company_email']) > 25 ? '...' : ''; ?></small></div>
                                <?php endif; ?>
                                <?php if (!empty($app['company_contact'])): ?>
                                <div><i class="fas fa-phone text-muted"></i> <small><?php echo htmlspecialchars($app['company_contact']); ?></small></div>
                                <?php endif; ?>
                                <?php if (empty($app['company_email']) && empty($app['company_contact'])): ?>
                                <small class="text-muted">N/A</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="table-actions">
                                    <button type="button" class="btn btn-sm btn-outline-primary" 
                                            onclick="viewApplication('<?php echo $app['application_id']; ?>')" title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    
                                    <?php if (isset($status_workflow[$app['current_status']])): ?>
                                    <button type="button" class="btn btn-sm btn-primary" 
                                            onclick="updateStatus('<?php echo $app['application_id']; ?>', '<?php echo $app['current_status']; ?>')" title="Update Status">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['current_status'] == 'Under Review' || $app['current_status'] == 'Scheduled for Visit'): ?>
                                    <button type="button" class="btn btn-sm btn-success" 
                                            onclick="scheduleVisit('<?php echo $app['application_id']; ?>')" title="Schedule Visit">
                                        <i class="fas fa-calendar-plus"></i>
                                    </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($app['current_status'] == 'Final Review'): ?>
                                    <button type="button" class="btn btn-sm btn-warning" 
                                            onclick="finalDecision('<?php echo $app['application_id']; ?>')" title="Final Decision">
                                        <i class="fas fa-gavel"></i>
                                    </button>
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
    
    <!-- Status Update Modal -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Update Application Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" id="status_application_id">
                        <input type="hidden" name="previous_status" id="status_previous_status">
                        
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <input type="text" class="form-control" id="status_current_status" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Status</label>
                            <select class="form-select" name="new_status" id="status_new_status" required>
                                <option value="">Select new status...</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason/Notes</label>
                            <textarea class="form-control" name="change_reason" rows="3" placeholder="Enter reason for status change..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_status" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
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
                        <input type="hidden" name="application_id" id="visit_application_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Visit Type</label>
                            <select class="form-select" name="visit_type" required>
                                <option value="Initial">Initial Visit</option>
                                <option value="Follow-up">Follow-up Visit</option>
                                <option value="Final">Final Visit</option>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Date</label>
                                <input type="date" class="form-control" name="visit_date" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Visit Time</label>
                                <input type="time" class="form-control" name="visit_time" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="visit_notes" rows="3" placeholder="Additional notes for the visit..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="schedule_visit" class="btn btn-success">Schedule Visit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Final Decision Modal -->
    <div class="modal fade" id="decisionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Final Decision</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="application_id" id="decision_application_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Decision</label>
                            <select class="form-select" name="decision" id="decision_type" required onchange="toggleCertificateFields()">
                                <option value="">Select decision...</option>
                                <option value="Approve">Approve</option>
                                <option value="Reject">Reject</option>
                            </select>
                        </div>
                        
                        <div id="certificate_fields" style="display: none;">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Certificate Number</label>
                                    <input type="text" class="form-control" name="certificate_number" placeholder="Auto-generated if left blank">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Issue Date</label>
                                    <input type="date" class="form-control" name="certificate_issue_date" value="<?php echo date('Y-m-d'); ?>">
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Expiry Date</label>
                                <input type="date" class="form-control" name="certificate_expiry_date" placeholder="Leave blank for auto (1 year from issue)">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Reason/Notes</label>
                            <textarea class="form-control" name="decision_reason" rows="4" 
                                      placeholder="Enter approval/rejection reason..." required></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="final_decision" class="btn btn-success">Submit Decision</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- SweetAlert2 -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Status workflow mapping
        const statusWorkflow = <?php echo json_encode($status_workflow); ?>;
        
        function updateStatus(applicationId, currentStatus) {
            document.getElementById('status_application_id').value = applicationId;
            document.getElementById('status_previous_status').value = currentStatus;
            document.getElementById('status_current_status').value = currentStatus;
            
            const newStatusSelect = document.getElementById('status_new_status');
            newStatusSelect.innerHTML = '<option value="">Select new status...</option>';
            
            if (statusWorkflow[currentStatus]) {
                statusWorkflow[currentStatus].forEach(status => {
                    const option = document.createElement('option');
                    option.value = status;
                    option.textContent = status;
                    newStatusSelect.appendChild(option);
                });
            }
            
            new bootstrap.Modal(document.getElementById('statusModal')).show();
        }
        
        function scheduleVisit(applicationId) {
            document.getElementById('visit_application_id').value = applicationId;
            new bootstrap.Modal(document.getElementById('visitModal')).show();
        }
        
        function finalDecision(applicationId) {
            document.getElementById('decision_application_id').value = applicationId;
            new bootstrap.Modal(document.getElementById('decisionModal')).show();
        }
        
        function toggleCertificateFields() {
            const decisionType = document.getElementById('decision_type').value;
            const certFields = document.getElementById('certificate_fields');
            certFields.style.display = decisionType === 'Approve' ? 'block' : 'none';
        }
        
        function viewApplication(applicationId) {
            // Will implement detailed view in next step
            window.location.href = 'application-details.php?id=' + applicationId;
        }
        
        // Auto-dismiss success messages
        <?php if (isset($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Success!',
            text: '<?php echo addslashes($success_message); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo addslashes($error_message); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
        <?php endif; ?>
    </script>
</body>
</html>

