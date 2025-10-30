<?php
/**
 * Certification Application System Database Migration
 * Run this script to create all necessary tables for the certification system
 */

$root_path = dirname(__DIR__);
require_once $root_path . '/common/connection.php';

// Read the SQL file
$sql_file = $root_path . '/database/create_certification_system_tables.sql';

if (!file_exists($sql_file)) {
    die("Error: SQL file not found at: $sql_file\n");
}

$sql_content = file_get_contents($sql_file);

// Remove comments (single-line and multi-line)
$sql_content = preg_replace('/--.*$/m', '', $sql_content);
$sql_content = preg_replace('/\/\*.*?\*\//s', '', $sql_content);

// Split SQL statements by semicolon
$statements = explode(';', $sql_content);

$success_count = 0;
$error_count = 0;
$errors = [];

foreach ($statements as $statement) {
    $statement = trim($statement);
    
    // Skip empty statements
    if (empty($statement)) {
        continue;
    }
    
    // Execute the statement
    if (mysqli_query($conn, $statement)) {
        $success_count++;
    } else {
        $error_count++;
        $error_msg = mysqli_error($conn);
        // Only log actual errors (not "table already exists" type messages)
        if (!empty($error_msg) && strpos($error_msg, 'already exists') === false) {
            $errors[] = [
                'statement' => substr($statement, 0, 100) . '...',
                'error' => $error_msg
            ];
        } else {
            $success_count++; // Table exists is considered success
        }
    }
}

echo "=== Certification System Database Migration ===\n\n";
echo "Successfully executed: $success_count statements\n";
echo "Errors: $error_count statements\n\n";

if (!empty($errors)) {
    echo "=== Errors ===\n";
    foreach ($errors as $error) {
        echo "Error: " . $error['error'] . "\n";
        echo "Statement: " . $error['statement'] . "\n\n";
    }
} else {
    echo "✓ All tables created successfully!\n";
}

mysqli_close($conn);
?>

