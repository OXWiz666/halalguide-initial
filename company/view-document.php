<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

check_login();

$useraccount_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];

// Get document ID
$document_id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if (empty($document_id)) {
    http_response_code(404);
    die('Document not found');
}

// Get document info and verify ownership
$doc_query = mysqli_query($conn, 
    "SELECT d.*, ca.company_id 
     FROM tbl_application_documents d
     LEFT JOIN tbl_certification_application ca ON d.application_id = ca.application_id
     WHERE d.document_id = '$document_id' AND ca.company_id = '$company_id'");

if (!$doc_query || mysqli_num_rows($doc_query) == 0) {
    http_response_code(403);
    die('Access denied');
}

$document = mysqli_fetch_assoc($doc_query);
$file_path = $document['file_path'];

// Verify file exists
if (!file_exists($file_path)) {
    http_response_code(404);
    die('File not found');
}

// Get file info
$file_info = pathinfo($file_path);
$mime_types = [
    'pdf' => 'application/pdf',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'xls' => 'application/vnd.ms-excel',
    'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
];

$extension = strtolower($file_info['extension'] ?? '');
$mime_type = $mime_types[$extension] ?? 'application/octet-stream';

// Output file
header('Content-Type: ' . $mime_type);
header('Content-Disposition: inline; filename="' . basename($file_path) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: private, max-age=0, must-revalidate');
header('Pragma: public');

readfile($file_path);
exit();
?>

