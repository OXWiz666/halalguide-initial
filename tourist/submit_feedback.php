<?php
include '../common/session.php';
include '../common/connection.php';

header('Content-Type: application/json');

// Require tourist login
check_login();
check_access('Tourist');

$useraccount_id = $_SESSION['user_id'];

// Ensure feedback table exists (idempotent)
$create = "CREATE TABLE IF NOT EXISTS tbl_feedback (
  feedback_id INT AUTO_INCREMENT PRIMARY KEY,
  useraccount_id INT NOT NULL,
  display_name VARCHAR(100) NULL,
  rating TINYINT NOT NULL,
  comment TEXT NOT NULL,
  is_approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (useraccount_id),
  INDEX (is_approved)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
mysqli_query($conn, $create);

// Read input
$data = $_POST;
if (empty($data)) {
  // Support JSON payloads
  $raw = file_get_contents('php://input');
  if ($raw) { $data = json_decode($raw, true) ?: []; }
}

$rating = isset($data['rating']) ? (int)$data['rating'] : 0;
$comment = isset($data['comment']) ? trim($data['comment']) : '';
// Resolve tourist display name from useraccount table (ignore client-provided name)
$name_q = mysqli_query($conn, "SELECT firstname, lastname FROM tbl_useraccount WHERE useraccount_id = " . (int)$useraccount_id . " LIMIT 1");
$name_r = $name_q ? mysqli_fetch_assoc($name_q) : null;
$display_name = $name_r ? trim(($name_r['firstname'] ?? '') . ' ' . ($name_r['lastname'] ?? '')) : '';

if ($rating < 1 || $rating > 5) {
  echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5.']);
  exit;
}
if ($comment === '' || strlen($comment) < 5) {
  echo json_encode(['success' => false, 'message' => 'Please share a brief comment (min 5 characters).']);
  exit;
}

$stmt = mysqli_prepare($conn, "INSERT INTO tbl_feedback (useraccount_id, display_name, rating, comment) VALUES (?, ?, ?, ?)");
mysqli_stmt_bind_param($stmt, 'isis', $useraccount_id, $display_name, $rating, $comment);
$ok = mysqli_stmt_execute($stmt);

if ($ok) {
  echo json_encode(['success' => true, 'message' => 'Thanks for your feedback!']);
} else {
  echo json_encode(['success' => false, 'message' => 'Failed to save feedback.']);
}
?>


