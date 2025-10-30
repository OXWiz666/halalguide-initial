<?php
/**
 * Complete Registration
 * Finalizes registration after phone verification
 */

session_start();
include 'connection.php';
include 'randomstrings.php';

// Check if phone is verified and registration data exists
if (!isset($_SESSION['phone_verified']) || !$_SESSION['phone_verified']) {
    header('Location: verify-phone.php');
    exit;
}

if (!isset($_SESSION['pending_registration'])) {
    header('Location: ../registration.php');
    exit;
}

$registration_data = $_SESSION['pending_registration'];
$user_type = $registration_data['user_type'] ?? 'Tourist';

// Complete the registration based on user type
$currentDateTime = date('Y-m-d H:i:s');
$success = false;
$error = null;

try {
    mysqli_autocommit($conn, FALSE);
    
    if ($user_type === 'Tourist') {
        // Complete tourist registration
        $tourist_id = $registration_data['tourist_id'];
        $useraccount_id = $registration_data['useraccount_id'];
        
        // Escape data for SQL
        $tourist_id = mysqli_real_escape_string($conn, $registration_data['tourist_id']);
        $firstname = mysqli_real_escape_string($conn, $registration_data['firstname']);
        $middlename = !empty($registration_data['middlename']) ? mysqli_real_escape_string($conn, $registration_data['middlename']) : '';
        $lastname = mysqli_real_escape_string($conn, $registration_data['lastname']);
        $gender = mysqli_real_escape_string($conn, $registration_data['gender']);
        $contact_no = mysqli_real_escape_string($conn, $registration_data['contact_no']);
        $email = mysqli_real_escape_string($conn, $registration_data['email']);
        $username = mysqli_real_escape_string($conn, $registration_data['username']);
        $password = mysqli_real_escape_string($conn, $registration_data['password']);
        $useraccount_id = mysqli_real_escape_string($conn, $registration_data['useraccount_id']);
        
        // Insert tourist record
        $middlename_val = !empty($middlename) ? "'$middlename'" : "NULL";
        $insert_tourist = mysqli_query($conn, "INSERT INTO tbl_tourist 
            (tourist_id, firstname, middlename, lastname, gender, contact_no, email, status_id, date_added)
            VALUES ('$tourist_id', '$firstname', $middlename_val, '$lastname', '$gender', '$contact_no', '$email', 
            (SELECT status_id FROM tbl_status WHERE status = 'Active'), '$currentDateTime')");
        
        if (!$insert_tourist) {
            throw new Exception("Failed to create tourist record: " . mysqli_error($conn));
        }
        
        // Insert user account
        $insert_account = mysqli_query($conn, "INSERT INTO tbl_useraccount 
            (useraccount_id, username, password, tourist_id, usertype_id, status_id, date_added)
            VALUES ('$useraccount_id', 
            '{$registration_data['username']}', 
            '{$registration_data['password']}', 
            '$tourist_id', 
            (SELECT usertype_id FROM tbl_usertype WHERE usertype = 'Tourist'),
            (SELECT status_id FROM tbl_status WHERE status = 'Active'), 
            '$currentDateTime')");
        
        if (!$insert_account) {
            throw new Exception("Failed to create user account: " . mysqli_error($conn));
        }
        
    } elseif ($user_type === 'Company') {
        // Complete company registration
        $company_id = $registration_data['company_id'];
        $useraccount_id = $registration_data['useraccount_id'];
        $address_id = $registration_data['address_id'];
        $company_user_id = $registration_data['company_user_id'];
        $company_person_id = $registration_data['company_person_id'];
        
        // Insert address - Escape all values properly
        $region_code_val = !empty($registration_data['region_code']) 
            ? "'" . mysqli_real_escape_string($conn, $registration_data['region_code']) . "'" 
            : "NULL";
        $province_code_val = !empty($registration_data['province_code']) 
            ? "'" . mysqli_real_escape_string($conn, $registration_data['province_code']) . "'" 
            : "NULL";
        $citymun_code_val = !empty($registration_data['citymun_code']) 
            ? "'" . mysqli_real_escape_string($conn, $registration_data['citymun_code']) . "'" 
            : "NULL";
        $brgy_code_val = !empty($registration_data['brgy_code']) 
            ? "'" . mysqli_real_escape_string($conn, $registration_data['brgy_code']) . "'" 
            : "NULL";
        $address_line_val = !empty($registration_data['address_line']) 
            ? "'" . mysqli_real_escape_string($conn, $registration_data['address_line']) . "'" 
            : "NULL";
        
// Insert minimal fields required by current schema
$insert_address_sql = "INSERT INTO tbl_address (address_id, brgyCode, other, date_added) VALUES ('{$address_id}', {$brgy_code_val}, {$address_line_val}, '{$currentDateTime}')";
$insert_address = mysqli_query($conn, $insert_address_sql);
        
        if (!$insert_address) {
            $error_msg = "Failed to create address record: " . mysqli_error($conn);
            error_log("Company registration error: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        // Insert company - Use contact_no (which is now the same as cp_contact_no)
        // Escape all values to prevent SQL injection
        $company_name_escaped = mysqli_real_escape_string($conn, $registration_data['company_name']);
        $company_description_escaped = !empty($registration_data['company_description']) 
            ? "'" . mysqli_real_escape_string($conn, $registration_data['company_description']) . "'" 
            : "NULL";
        $contact_no_escaped = mysqli_real_escape_string($conn, $registration_data['contact_no']);
        $email_escaped = mysqli_real_escape_string($conn, $registration_data['email']);
        $has_prayer_faci = isset($registration_data['has_prayer_faci']) ? (int)$registration_data['has_prayer_faci'] : 0;
        $usertype_id = (int)$registration_data['usertype_id'];
        
        $insert_company = mysqli_query($conn, "INSERT INTO tbl_company 
            (company_id, company_name, company_description, contant_no, tel_no, email, address_id, usertype_id, status_id, has_prayer_faci, date_added)
            VALUES ('$company_id', 
            '$company_name_escaped', 
            $company_description_escaped, 
            '$contact_no_escaped', 
            NULL, 
            '$email_escaped', 
            '$address_id', 
            $usertype_id, 
            (SELECT status_id FROM tbl_status WHERE status = 'Active'), 
            $has_prayer_faci, 
            '$currentDateTime')");
        
        if (!$insert_company) {
            $error_msg = "Failed to create company record: " . mysqli_error($conn);
            error_log("Company registration error: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        // Insert company user - Escape all values properly
        $cp_firstname_escaped = mysqli_real_escape_string($conn, $registration_data['cp_firstname']);
        $cp_middlename_escaped = !empty($registration_data['cp_middlename']) 
            ? "'" . mysqli_real_escape_string($conn, $registration_data['cp_middlename']) . "'" 
            : "NULL";
        $cp_lastname_escaped = mysqli_real_escape_string($conn, $registration_data['cp_lastname']);
        $cp_gender_escaped = mysqli_real_escape_string($conn, $registration_data['cp_gender']);
        $cp_contact_no_escaped = mysqli_real_escape_string($conn, $registration_data['cp_contact_no']);
        $cp_email_escaped = !empty($registration_data['cp_email']) 
            ? "'" . mysqli_real_escape_string($conn, $registration_data['cp_email']) . "'" 
            : "NULL";
        
        $insert_company_user = mysqli_query($conn, "INSERT INTO tbl_company_user 
            (company_user_id, company_id, firstname, middlename, lastname, gender, contact_no, email, position, usertype_id, status_id, date_added)
            VALUES ('$company_user_id', 
            '$company_id', 
            '$cp_firstname_escaped', 
            $cp_middlename_escaped, 
            '$cp_lastname_escaped', 
            '$cp_gender_escaped', 
            '$cp_contact_no_escaped', 
            $cp_email_escaped, 
            'Owner/Manager', 
            $usertype_id, 
            (SELECT status_id FROM tbl_status WHERE status = 'Active'), 
            '$currentDateTime')");
        
        if (!$insert_company_user) {
            $error_msg = "Failed to create company user record: " . mysqli_error($conn);
            error_log("Company registration error: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        // Insert company person - Use already escaped values
        $insert_company_person = mysqli_query($conn, "INSERT INTO tbl_company_person 
            (company_person_id, company_id, firstname, middlename, lastname, gender, contact_no, email, status_id, date_added)
            VALUES ('$company_person_id', 
            '$company_id', 
            '$cp_firstname_escaped', 
            $cp_middlename_escaped, 
            '$cp_lastname_escaped', 
            '$cp_gender_escaped', 
            '$cp_contact_no_escaped', 
            $cp_email_escaped, 
            (SELECT status_id FROM tbl_status WHERE status = 'Active'), 
            '$currentDateTime')");
        
        if (!$insert_company_person) {
            $error_msg = "Failed to create company person record: " . mysqli_error($conn);
            error_log("Company registration error: " . $error_msg);
            throw new Exception($error_msg);
        }
        
        // Insert user account - Escape username and password
        $username_escaped = mysqli_real_escape_string($conn, $registration_data['username']);
        $password_escaped = mysqli_real_escape_string($conn, $registration_data['password']);
        
        $insert_account = mysqli_query($conn, "INSERT INTO tbl_useraccount 
            (useraccount_id, username, password, company_id, company_user_id, usertype_id, status_id, date_added)
            VALUES ('$useraccount_id', 
            '$username_escaped', 
            '$password_escaped', 
            '$company_id', 
            '$company_user_id', 
            $usertype_id, 
            (SELECT status_id FROM tbl_status WHERE status = 'Active'), 
            '$currentDateTime')");
        
        if (!$insert_account) {
            $error_msg = "Failed to create user account: " . mysqli_error($conn);
            error_log("Company registration error: " . $error_msg);
            throw new Exception($error_msg);
        }
        
    } elseif ($user_type === 'HCB') {
        // Complete HCB registration
        $organization_id = $registration_data['organization_id'];
        $admin_id = $registration_data['admin_id'];
        $useraccount_id = $registration_data['useraccount_id'];
        
        // Get inactive status ID
        $pending_status_id = mysqli_query($conn, "SELECT status_id FROM tbl_status WHERE status = 'Inactive' LIMIT 1");
        $pending_row = mysqli_fetch_assoc($pending_status_id);
        $pending_status = $pending_row['status_id'] ?? 2;
        
        // Insert organization
        $insert_org = mysqli_query($conn, "INSERT INTO tbl_organization 
            (organization_id, organization_name, contact_no, email, address, status_id, date_added)
            VALUES ('$organization_id', 
            '{$registration_data['organization_name']}', 
            '{$registration_data['contact_no']}', 
            '{$registration_data['email']}', 
            " . (!empty($registration_data['address']) ? "'{$registration_data['address']}'" : "NULL") . ", 
            '$pending_status', 
            '$currentDateTime')");
        
        if (!$insert_org) {
            throw new Exception("Failed to create organization record: " . mysqli_error($conn));
        }
        
        // Insert admin
        $insert_admin = mysqli_query($conn, "INSERT INTO tbl_admin 
            (admin_id, firstname, lastname, email, contact_no, organization_id, status_id, date_added)
            VALUES ('$admin_id', 
            '{$registration_data['firstname']}', 
            '{$registration_data['lastname']}', 
            '{$registration_data['email']}', 
            '{$registration_data['contact_no']}', 
            '$organization_id', 
            '$pending_status', 
            '$currentDateTime')");
        
        if (!$insert_admin) {
            throw new Exception("Failed to create admin record: " . mysqli_error($conn));
        }
        
        // Insert user account
        $insert_account = mysqli_query($conn, "INSERT INTO tbl_useraccount 
            (useraccount_id, username, password, admin_id, usertype_id, status_id, date_added)
            VALUES ('$useraccount_id', 
            '{$registration_data['username']}', 
            '{$registration_data['password']}', 
            '$admin_id', 
            (SELECT usertype_id FROM tbl_usertype WHERE usertype = 'Admin'),
            '$pending_status', 
            '$currentDateTime')");
        
        if (!$insert_account) {
            throw new Exception("Failed to create user account: " . mysqli_error($conn));
        }
    }
    
    mysqli_commit($conn);
    $success = true;
    
    // Clear session data
    unset($_SESSION['pending_registration']);
    unset($_SESSION['phone_verified']);
    unset($_SESSION['sms_verification_id']);
    unset($_SESSION['auto_send_attempted']);
    unset($_SESSION['sms_sent']);
    unset($_SESSION['sms_error']);
    
} catch (Exception $e) {
    mysqli_rollback($conn);
    $error = $e->getMessage();
}

mysqli_autocommit($conn, TRUE);

// Redirect based on result
if ($success) {
    // Redirect to appropriate login page
    if ($user_type === 'HCB') {
        header('Location: ../hcb/login.php?registered=1');
    } elseif ($user_type === 'Company') {
        header('Location: ../company/login.php?registered=1');
    } else {
        header('Location: ../login.php?registered=1');
    }
    exit;
} else {
    // Error occurred - redirect to login with error message instead of back to registration
    $_SESSION['registration_error'] = $error;
    
    if ($user_type === 'HCB') {
        // Redirect to HCB login with error message instead of registration
        header('Location: ../hcb/login.php?error=1');
    } elseif ($user_type === 'Company') {
        // Redirect to Company login with error message instead of registration
        header('Location: ../company/login.php?error=1');
    } else {
        header('Location: ../login.php?error=1');
    }
    exit;
}
?>

