<?php
// AJAX endpoint to approve/reject an uploaded document requirement
// Input (POST): document_id, review_action (Uploaded|Rejected), review_notes (optional)
// Output (JSON): { success: bool, message: string, data?: {...} }

header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json');

require_once '../common/session.php';
require_once '../common/connection.php';
require_once '../common/randomstrings.php';

try {
    check_login();
    check_access('Admin');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$admin_id = $_SESSION['admin_id'] ?? '';
$organization_id = $_SESSION['organization_id'] ?? '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$document_id = mysqli_real_escape_string($conn, $_POST['document_id'] ?? '');
$review_action = mysqli_real_escape_string($conn, $_POST['review_action'] ?? '');
$review_notes = mysqli_real_escape_string($conn, $_POST['review_notes'] ?? '');

if (empty($document_id) || ($review_action !== 'Uploaded' && $review_action !== 'Rejected')) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

// Fetch document/application details (to verify access and prepare notification)
$doc_query = "SELECT d.*, dc.document_name, ca.application_id, ca.application_number, ca.company_id
              FROM tbl_application_documents d
              LEFT JOIN tbl_document_checklist dc ON d.document_type = dc.document_type
              LEFT JOIN tbl_certification_application ca ON d.application_id = ca.application_id
              WHERE d.document_id = '$document_id' AND ca.organization_id = '$organization_id' LIMIT 1";
$doc_result = mysqli_query($conn, $doc_query);
if (!$doc_result || mysqli_num_rows($doc_result) === 0) {
    echo json_encode(['success' => false, 'message' => 'Document not found or access denied']);
    exit;
}

$doc_data = mysqli_fetch_assoc($doc_result);

mysqli_begin_transaction($conn);
try {
    // Update status
    $update_query = "UPDATE tbl_application_documents SET upload_status = '$review_action', reviewed_by = '$admin_id', reviewed_date = NOW(), rejection_reason = " . ($review_action === 'Rejected' ? "'$review_notes'" : "NULL") . " WHERE document_id = '$document_id'";
    if (!mysqli_query($conn, $update_query)) {
        throw new Exception('Error updating document review: ' . mysqli_error($conn));
    }

    // Notification to company
    $subject = $review_action === 'Uploaded' ? ('Document Approved: ' . $doc_data['document_name']) : ('Document Rejected: ' . $doc_data['document_name']);
    $message = $review_action === 'Uploaded'
        ? ("Your document '" . $doc_data['document_name'] . "' for application #" . $doc_data['application_number'] . " has been approved by the certifying body.")
        : ("Your document '" . $doc_data['document_name'] . "' for application #" . $doc_data['application_number'] . " has been rejected. " . (!empty($review_notes) ? ('Reason: ' . $review_notes) : 'Please review and resubmit.'));

    $notification_id = generate_string($permitted_chars, 25);
    $subject_esc = mysqli_real_escape_string($conn, $subject);
    $message_esc = mysqli_real_escape_string($conn, $message);

    $notif_query = "INSERT INTO tbl_application_notifications (notification_id, application_id, notification_type, recipient_type, recipient_id, subject, message, date_added) VALUES ('$notification_id', '" . $doc_data['application_id'] . "', 'Document Review', 'Company', '" . $doc_data['company_id'] . "', '$subject_esc', '$message_esc', NOW())";
    mysqli_query($conn, $notif_query); // best-effort

    mysqli_commit($conn);

    echo json_encode([
        'success' => true,
        'message' => $review_action === 'Uploaded' ? 'Document approved successfully.' : 'Document rejected successfully.',
        'data' => [
            'upload_status' => $review_action,
            'rejection_reason' => $review_action === 'Rejected' ? $review_notes : null,
            'reviewed_date' => date('M d, Y g:i A')
        ]
    ]);
} catch (Exception $e) {
    mysqli_rollback($conn);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}


