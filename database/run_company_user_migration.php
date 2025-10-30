<?php
/**
 * Company User Table Migration Script
 * 
 * This script creates the tbl_company_user table and updates tbl_useraccount
 * Run this once to set up the new company user structure
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

// Read and execute the create company_user table SQL
echo "📝 Creating tbl_company_user table...\n";

$createTableSQL = "
CREATE TABLE IF NOT EXISTS `tbl_company_user` (
  `company_user_id` varchar(25) NOT NULL,
  `company_id` varchar(25) DEFAULT NULL,
  `firstname` varchar(250) DEFAULT NULL,
  `middlename` varchar(250) DEFAULT NULL,
  `lastname` varchar(250) DEFAULT NULL,
  `gender` varchar(15) DEFAULT NULL,
  `contact_no` varchar(15) DEFAULT NULL,
  `email` varchar(50) DEFAULT NULL,
  `position` varchar(100) DEFAULT NULL COMMENT 'Job position/title (e.g., Manager, Owner, Admin)',
  `usertype_id` int(11) DEFAULT NULL COMMENT 'Links to tbl_usertype (3=Establishment, 4=Accommodation, 5=Tourist Spot, 6=Prayer Facility)',
  `status_id` int(11) DEFAULT NULL COMMENT 'Links to tbl_status',
  `date_added` datetime DEFAULT NULL,
  `date_updated` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`company_user_id`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_email` (`email`),
  KEY `idx_status_id` (`status_id`),
  KEY `idx_usertype_id` (`usertype_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci COMMENT='Stores company user/manager personal information';
";

if (mysqli_query($conn, $createTableSQL)) {
    echo "✅ Table tbl_company_user created successfully\n\n";
} else {
    $error = mysqli_error($conn);
    if (strpos($error, "already exists") !== false) {
        echo "ℹ️  Table tbl_company_user already exists, skipping...\n\n";
    } else {
        die("❌ Error creating table: $error\n");
    }
}

// Check if company_user_id column exists in tbl_useraccount
echo "📝 Checking tbl_useraccount structure...\n";
$checkColumn = mysqli_query($conn, "SHOW COLUMNS FROM `tbl_useraccount` LIKE 'company_user_id'");

if (mysqli_num_rows($checkColumn) == 0) {
    echo "📝 Adding company_user_id column to tbl_useraccount...\n";
    
    $alterSQL = "
    ALTER TABLE `tbl_useraccount` 
    ADD COLUMN `company_user_id` varchar(25) DEFAULT NULL AFTER `company_id`,
    ADD KEY `idx_company_user_id` (`company_user_id`);
    ";
    
    if (mysqli_query($conn, $alterSQL)) {
        echo "✅ Column company_user_id added successfully\n\n";
    } else {
        die("❌ Error adding column: " . mysqli_error($conn) . "\n");
    }
} else {
    echo "ℹ️  Column company_user_id already exists, skipping...\n\n";
}

echo "✅ Migration completed successfully!\n\n";
echo "📋 Summary:\n";
echo "   - tbl_company_user table created/verified\n";
echo "   - tbl_useraccount updated with company_user_id column\n";
echo "   - All indexes created\n\n";

mysqli_close($conn);
?>

