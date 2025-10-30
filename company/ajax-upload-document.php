<?php
// AJAX endpoint: Upload application document (JSON response)
// Expects: POST multipart/form-data with fields:
// - application_id
// - document_type
// - document_file (file)

header('Cache-Control: no-cache, must-revalidate');

require_once '../common/connection.php';
require_once '../common/randomstrings.php';

function json_response($ok, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $ok, 'message' => $message], $extra));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(false, 'Invalid request method.');
}

// Validate inputs
$app_id = mysqli_real_escape_string($conn, $_POST['application_id'] ?? '');
$doc_type = mysqli_real_escape_string($conn, $_POST['document_type'] ?? '');

if (empty($app_id) || empty($doc_type)) {
    json_response(false, 'Invalid application or document type.');
}

if (!isset($_FILES['document_file'])) {
    json_response(false, 'No file uploaded.');
}

// Fetch checklist info
$checklist_info_query = mysqli_query($conn, "SELECT * FROM tbl_document_checklist WHERE document_type = '$doc_type' LIMIT 1");
$checklist_info = mysqli_fetch_assoc($checklist_info_query);
if (!$checklist_info) {
    json_response(false, 'Invalid document type.');
}

$file = $_FILES['document_file'];
$file_tmp = $file['tmp_name'];
$file_size = $file['size'];
$file_name = $file['name'];
$file_error = $file['error'];

$max_size_bytes = ($checklist_info['max_file_size_mb'] ?? 10) * 1024 * 1024;
$allowed_exts = explode(',', $checklist_info['file_types_allowed'] ?? 'pdf');
$allowed_exts = array_map('trim', $allowed_exts);
$file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

if ($file_error !== UPLOAD_ERR_OK) {
    json_response(false, 'File upload error occurred.');
}
if ($file_size > $max_size_bytes) {
    json_response(false, 'Maximum file size is ' . ($checklist_info['max_file_size_mb'] ?? 10) . 'MB.');
}
if (!in_array($file_ext, $allowed_exts)) {
    json_response(false, 'Allowed formats: ' . strtoupper(implode(', ', $allowed_exts)));
}

// Build storage path
$doc_storage_base = '../uploads/application_documents/';
$new_filename = 'doc_' . $doc_type . '_' . time() . '_' . uniqid() . '.' . $file_ext;
$file_path = $doc_storage_base . $app_id . '/' . $new_filename;

if (!is_dir(dirname($file_path))) {
    @mkdir(dirname($file_path), 0777, true);
}

if (!move_uploaded_file($file_tmp, $file_path)) {
    json_response(false, 'Failed to move uploaded file.');
}

// Versioning
$existing_doc_query = mysqli_query($conn, "SELECT document_id, version_number FROM tbl_application_documents WHERE application_id = '$app_id' AND document_type = '$doc_type' ORDER BY version_number DESC LIMIT 1");
$existing_doc = mysqli_fetch_assoc($existing_doc_query);
$version = $existing_doc ? ((int)$existing_doc['version_number'] + 1) : 1;
if ($existing_doc) {
    mysqli_query($conn, "UPDATE tbl_application_documents SET upload_status = 'Pending' WHERE document_id = '" . $existing_doc['document_id'] . "'");
}

$document_id = generate_string($permitted_chars, 25);
$insert_doc_query = "INSERT INTO tbl_application_documents (document_id, application_id, document_type, document_name, file_path, file_size, file_type, is_required, upload_status, version_number, date_added) VALUES ('$document_id', '$app_id', '$doc_type', '" . mysqli_real_escape_string($conn, $checklist_info['document_name']) . "', '$file_path', '$file_size', '$file_ext', '" . ($checklist_info['is_required'] ?? 1) . "', 'Pending', '$version', NOW())";

if (!mysqli_query($conn, $insert_doc_query)) {
    @unlink($file_path);
    json_response(false, 'Error saving document: ' . addslashes(mysqli_error($conn)));
}

json_response(true, 'Your document has been uploaded successfully and is pending review.', [
    'uploaded' => [
        'document_id' => $document_id,
        'file_path' => $file_path,
        'upload_status' => 'Pending',
        'date_added' => date('Y-m-d H:i:s'),
        'date_added_readable' => date('M d, Y g:i A')
    ]
]);


