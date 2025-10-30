<?php
/**
 * SMS Verification Table Migration Script
 * 
 * This script creates the tbl_sms_verification table
 * Run this once to set up the SMS verification system
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Build the correct path to connection.php
$rootPath = dirname(__DIR__);
include $rootPath . '/common/connection.php';

if (!$conn) {
    die("❌ Database connection failed: " . mysqli_connect_error() . "\n");
}

echo "✅ Database connected successfully\n\n";

// Read and execute the create SMS verification table SQL
echo "📝 Creating tbl_sms_verification table...\n";

$createTableSQL = file_get_contents(__DIR__ . '/create_sms_verification_table.sql');

if (empty($createTableSQL)) {
    die("❌ Could not read create_sms_verification_table.sql file\n");
}

// Execute the SQL
if (mysqli_multi_query($conn, $createTableSQL)) {
    do {
        // Store first result set
        if ($result = mysqli_store_result($conn)) {
            mysqli_free_result($result);
        }
        // Move to next result set
    } while (mysqli_next_result($conn));
    
    echo "✅ Table tbl_sms_verification created successfully!\n\n";
    
    // Verify table exists
    $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_sms_verification'");
    if (mysqli_num_rows($checkTable) > 0) {
        echo "✅ Verification: tbl_sms_verification table exists in database\n";
        
        // Show table structure
        echo "\n📊 Table structure:\n";
        $describe = mysqli_query($conn, "DESCRIBE tbl_sms_verification");
        echo "Columns:\n";
        while ($row = mysqli_fetch_assoc($describe)) {
            echo "  - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    } else {
        echo "⚠️  Warning: Table may not have been created properly\n";
    }
    
} else {
    die("❌ Error creating table: " . mysqli_error($conn) . "\n");
}

mysqli_close($conn);
echo "\n✅ Migration completed successfully!\n";
?>

