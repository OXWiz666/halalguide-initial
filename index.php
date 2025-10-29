<?php
include 'common/connection.php';
include 'common/session.php';

date_default_timezone_set("Asia/Manila");

// Redirect to login if not logged in
check_login_index();

$user_role = $_SESSION['user_role'];

switch ($user_role) {
    case 'Super Admin':
        header("Location: ncmf/index.php");
        exit();
        break;
    case 'Admin':
        header("Location: hcb/index.php");
        exit();
        break;
    case 'Establishment':
        header("Location: company/index.php");
        exit();
        break;
    case 'Prayer Facility':
        header("Location: company/index.php");
        exit();
        break;
    case 'Tourist':
        header("Location: tourist/index.php");
        exit();
        break;
    case 'Accommodation':
        header("Location: company/index.php");
        exit();
        break;
    case 'Tourist Spot':
        header("Location: company/index.php");
        exit();
        break;
    default:
        // Handle unauthorized access or redirect to login
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit();
        break;
}
?>
