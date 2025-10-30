<?php
// AJAX endpoint: returns latest documents per document_type for an application
// Input: GET application_id
// Output: JSON { success: bool, documents: { [document_type]: { document_id, upload_status, file_path, date_added, rejection_reason } } }

header('Cache-Control: no-cache, must-revalidate');
header('Content-Type: application/json');

require_once '../common/session.php';
require_once '../common/connection.php';

try {
    check_login();
    check_access('Admin');
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$application_id = mysqli_real_escape_string($conn, $_GET['application_id'] ?? '');
if (empty($application_id)) {
    echo json_encode(['success' => false, 'message' => 'Missing application_id']);
    exit;
}

// Get latest uploaded documents per type for this application
$docs_sql = "SELECT d1.* FROM tbl_application_documents d1 INNER JOIN (SELECT document_type, MAX(date_added) AS max_date FROM tbl_application_documents WHERE application_id = '$application_id' GROUP BY document_type) d2 ON d1.document_type = d2.document_type AND d1.date_added = d2.max_date WHERE d1.application_id = '$application_id'";

$result = mysqli_query($conn, $docs_sql);
$documents = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $documents[$row['document_type']] = [
            'document_id' => $row['document_id'],
            'upload_status' => $row['upload_status'],
            'file_path' => $row['file_path'],
            'date_added' => $row['date_added'],
            'rejection_reason' => $row['rejection_reason'] ?? null
        ];
    }
}

echo json_encode(['success' => true, 'documents' => $documents]);
exit;


