<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

check_login();

$company_types = ['Establishment', 'Accommodation', 'Tourist Spot', 'Prayer Facility'];
if (!in_array($_SESSION['user_role'], $company_types)) {
    header("Location: ../login.php");
    exit();
}

// Handle logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit();
}

$useraccount_id = $_SESSION['user_id'];
$company_id = $_SESSION['company_id'];

$company_query = mysqli_query($conn, 
    "SELECT c.*, ut.usertype FROM tbl_useraccount ua
     LEFT JOIN tbl_company c ON ua.company_id = c.company_id
     LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
     WHERE ua.useraccount_id = '$useraccount_id'");
$company_row = mysqli_fetch_assoc($company_query);

$company_user_query = mysqli_query($conn,
    "SELECT cu.* FROM tbl_useraccount ua
     LEFT JOIN tbl_company_user cu ON ua.company_user_id = cu.company_user_id
     WHERE ua.useraccount_id = '$useraccount_id'");
$company_user_row = mysqli_fetch_assoc($company_user_query);

// Get all company users (same company, same usertype)
$all_users_query = mysqli_query($conn,
    "SELECT cu.*, ua.username, ua.useraccount_id, ua.status_id as account_status, s.status
     FROM tbl_company_user cu
     LEFT JOIN tbl_useraccount ua ON cu.company_user_id = ua.company_user_id
     LEFT JOIN tbl_status s ON cu.status_id = s.status_id
     WHERE cu.company_id = '$company_id' AND cu.usertype_id = (SELECT usertype_id FROM tbl_company WHERE company_id = '$company_id')
     ORDER BY cu.date_added DESC");

$user_fullname = trim(($company_user_row['firstname'] ?? '') . ' ' . ($company_user_row['middlename'] ?? '') . ' ' . ($company_user_row['lastname'] ?? ''));
$user_fullname = $user_fullname ?: 'Company User';

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle Add User
if (isset($_POST['add_user'])) {
    $firstname = mysqli_real_escape_string($conn, $_POST['firstname'] ?? '');
    $middlename = mysqli_real_escape_string($conn, $_POST['middlename'] ?? '');
    $lastname = mysqli_real_escape_string($conn, $_POST['lastname'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact_no'] ?? '');
    $position = mysqli_real_escape_string($conn, $_POST['position'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['username'] ?? '');
    $password = mysqli_real_escape_string($conn, $_POST['password'] ?? '');
    
    // Check if username already exists
    $check_username = mysqli_query($conn, "SELECT useraccount_id FROM tbl_useraccount WHERE username = '$username'");
    if (mysqli_num_rows($check_username) > 0) {
        $error_message = "Username already exists. Please choose a different username.";
    } else {
        // Check if email already exists
        $check_email = mysqli_query($conn, "SELECT company_user_id FROM tbl_company_user WHERE email = '$email'");
        if (mysqli_num_rows($check_email) > 0) {
            $error_message = "Email already exists. Please use a different email.";
        } else {
            // Get company usertype_id
            $usertype_query = mysqli_query($conn, "SELECT usertype_id FROM tbl_company WHERE company_id = '$company_id'");
            $usertype_row = mysqli_fetch_assoc($usertype_query);
            $usertype_id = $usertype_row['usertype_id'];
            
            // Generate unique IDs
            include '../common/randomstrings.php';
            if (!function_exists('generateRandomString')) {
                function generateRandomString($length = 25) {
                    $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
                    $randomString = '';
                    for ($i = 0; $i < $length; $i++) {
                        $randomString .= $characters[mt_rand(0, strlen($characters) - 1)];
                    }
                    return $randomString;
                }
            }
            $company_user_id = generateRandomString(25);
            $useraccount_id_new = generateRandomString(25);
            
            // Start transaction
            mysqli_autocommit($conn, FALSE);
            
            try {
                // Insert into tbl_company_user
                $insert_company_user = "INSERT INTO tbl_company_user 
                    (company_user_id, company_id, firstname, middlename, lastname, email, contact_no, position, usertype_id, status_id, date_added) 
                    VALUES ('$company_user_id', '$company_id', '$firstname', '$middlename', '$lastname', '$email', '$contact_no', '$position', '$usertype_id', '1', NOW())";
                
                if (!mysqli_query($conn, $insert_company_user)) {
                    throw new Exception("Error creating company user: " . mysqli_error($conn));
                }
                
                // Insert into tbl_useraccount
                $insert_useraccount = "INSERT INTO tbl_useraccount 
                    (useraccount_id, username, password, company_id, company_user_id, user_role, status_id, date_added) 
                    VALUES ('$useraccount_id_new', '$username', '$password', '$company_id', '$company_user_id', 
                    (SELECT usertype FROM tbl_usertype WHERE usertype_id = '$usertype_id'), '1', NOW())";
                
                if (!mysqli_query($conn, $insert_useraccount)) {
                    throw new Exception("Error creating user account: " . mysqli_error($conn));
                }
                
                mysqli_commit($conn);
                $success_message = "User added successfully!";
                
                // Refresh page to show new user with toast notification
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'User Added!',
                        text: 'User has been added successfully.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'company-users.php';
                    });
                </script>";
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error_message = $e->getMessage();
            }
            
            mysqli_autocommit($conn, TRUE);
        }
    }
}

// Handle Edit User
if (isset($_POST['edit_user'])) {
    $edit_user_id = mysqli_real_escape_string($conn, $_POST['edit_user_id'] ?? '');
    $firstname = mysqli_real_escape_string($conn, $_POST['edit_firstname'] ?? '');
    $middlename = mysqli_real_escape_string($conn, $_POST['edit_middlename'] ?? '');
    $lastname = mysqli_real_escape_string($conn, $_POST['edit_lastname'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['edit_email'] ?? '');
    $contact_no = mysqli_real_escape_string($conn, $_POST['edit_contact_no'] ?? '');
    $position = mysqli_real_escape_string($conn, $_POST['edit_position'] ?? '');
    $username = mysqli_real_escape_string($conn, $_POST['edit_username'] ?? '');
    $password = mysqli_real_escape_string($conn, $_POST['edit_password'] ?? '');
    
    // Get company_user_id from useraccount
    $get_company_user_id = mysqli_query($conn, "SELECT company_user_id FROM tbl_useraccount WHERE useraccount_id = '$edit_user_id'");
    if ($user_row = mysqli_fetch_assoc($get_company_user_id)) {
        $target_company_user_id = $user_row['company_user_id'];
        
        // Update tbl_company_user
        $update_company_user = "UPDATE tbl_company_user SET 
            firstname = '$firstname', 
            middlename = '$middlename', 
            lastname = '$lastname', 
            email = '$email', 
            contact_no = '$contact_no', 
            position = '$position',
            date_updated = NOW()
            WHERE company_user_id = '$target_company_user_id'";
        
        if (mysqli_query($conn, $update_company_user)) {
            // Update tbl_useraccount
            $update_useraccount = "UPDATE tbl_useraccount SET username = '$username'";
            if (!empty($password)) {
                $update_useraccount .= ", password = '$password'";
            }
            $update_useraccount .= " WHERE useraccount_id = '$edit_user_id'";
            
            if (mysqli_query($conn, $update_useraccount)) {
                $success_message = "User updated successfully!";
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'User Updated!',
                        text: 'User information has been updated successfully.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'company-users.php';
                    });
                </script>";
            } else {
                $error_message = "Error updating user account: " . mysqli_error($conn);
            }
        } else {
            $error_message = "Error updating company user: " . mysqli_error($conn);
        }
    }
}

// Handle Archive/Unarchive User
if (isset($_GET['archive_user']) || isset($_GET['unarchive_user'])) {
    $target_useraccount_id = mysqli_real_escape_string($conn, $_GET['archive_user'] ?? $_GET['unarchive_user'] ?? '');
    
    // Get company_user_id
    $get_company_user_id = mysqli_query($conn, "SELECT company_user_id FROM tbl_useraccount WHERE useraccount_id = '$target_useraccount_id'");
    if ($user_row = mysqli_fetch_assoc($get_company_user_id)) {
        $target_company_user_id = $user_row['company_user_id'];
        
        if (isset($_GET['archive_user'])) {
            // Archive: Set status_id to 3 (Archived)
            $update_status = "UPDATE tbl_company_user SET status_id = '3' WHERE company_user_id = '$target_company_user_id'";
            $update_account_status = "UPDATE tbl_useraccount SET status_id = '3' WHERE useraccount_id = '$target_useraccount_id'";
            
            if (mysqli_query($conn, $update_status) && mysqli_query($conn, $update_account_status)) {
                $success_message = "User archived successfully. They will not be able to login.";
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    Swal.fire({
                        icon: 'warning',
                        title: 'User Archived',
                        text: 'User has been archived successfully. They will not be able to login anymore.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'company-users.php';
                    });
                </script>";
            } else {
                $error_message = "Error archiving user: " . mysqli_error($conn);
            }
        } else if (isset($_GET['unarchive_user'])) {
            // Unarchive: Set status_id back to 1 (Active)
            $update_status = "UPDATE tbl_company_user SET status_id = '1' WHERE company_user_id = '$target_company_user_id'";
            $update_account_status = "UPDATE tbl_useraccount SET status_id = '1' WHERE useraccount_id = '$target_useraccount_id'";
            
            if (mysqli_query($conn, $update_status) && mysqli_query($conn, $update_account_status)) {
                $success_message = "User unarchived successfully. They can now login again.";
                echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
                echo "<script>
                    Swal.fire({
                        icon: 'success',
                        title: 'User Unarchived',
                        text: 'User has been unarchived successfully. They can now login again.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    }).then(() => {
                        window.location.href = 'company-users.php';
                    });
                </script>";
            } else {
                $error_message = "Error unarchiving user: " . mysqli_error($conn);
            }
        }
    }
}

// Refresh users query after operations
$all_users_query = mysqli_query($conn,
    "SELECT cu.*, ua.username, ua.useraccount_id, ua.status_id as account_status, s.status
     FROM tbl_company_user cu
     LEFT JOIN tbl_useraccount ua ON cu.company_user_id = ua.company_user_id
     LEFT JOIN tbl_status s ON cu.status_id = s.status_id
     WHERE cu.company_id = '$company_id' AND cu.usertype_id = (SELECT usertype_id FROM tbl_company WHERE company_id = '$company_id')
     ORDER BY cu.date_added DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management | HalalGuide</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="css/company-common.css">
</head>
<body>
    <?php 
    $current_page = 'company-users.php';
    include 'includes/sidebar.php'; 
    ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">User Management</h1>
                <p class="page-subtitle">Manage company users with the same user type</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">
                <i class="fas fa-user-plus me-2"></i>Add User
            </button>
        </div>
        
        <div class="content-card">
            <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>
            
            <div class="card-header">
                <h3 class="card-title">Company Users</h3>
                <span class="badge bg-primary"><?php echo mysqli_num_rows($all_users_query); ?> Users</span>
            </div>
            
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Position</th>
                            <th>Username</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (mysqli_num_rows($all_users_query) > 0): ?>
                            <?php while ($user = mysqli_fetch_assoc($all_users_query)): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm me-2" style="width: 36px; height: 36px; border-radius: 8px; background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600;">
                                            <?php echo strtoupper(substr(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''), 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div style="font-weight: 600;"><?php echo htmlspecialchars(trim(($user['firstname'] ?? '') . ' ' . ($user['middlename'] ?? '') . ' ' . ($user['lastname'] ?? ''))); ?></div>
                                            <div style="font-size: 12px; color: #6b7280;"><?php echo htmlspecialchars($user['contact_no'] ?? 'N/A'); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['position'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars($user['username'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $is_archived = ($user['status_id'] == '3' || $user['account_status'] == '3' || ($user['status'] ?? '') == 'Archived');
                                    ?>
                                    <span class="badge <?php echo $is_archived ? 'bg-secondary' : (($user['status'] == 'Active' || $user['account_status'] == '1') ? 'bg-success' : 'bg-warning'); ?>">
                                        <?php echo htmlspecialchars($user['status'] ?? ($is_archived ? 'Archived' : 'Active')); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-primary" title="Edit" onclick="editUser('<?php echo htmlspecialchars($user['useraccount_id']); ?>', '<?php echo htmlspecialchars($user['firstname'] ?? ''); ?>', '<?php echo htmlspecialchars($user['middlename'] ?? ''); ?>', '<?php echo htmlspecialchars($user['lastname'] ?? ''); ?>', '<?php echo htmlspecialchars($user['email'] ?? ''); ?>', '<?php echo htmlspecialchars($user['contact_no'] ?? ''); ?>', '<?php echo htmlspecialchars($user['position'] ?? ''); ?>', '<?php echo htmlspecialchars($user['username'] ?? ''); ?>')">
                                            <i class="fas fa-edit"></i>
                                        </button>
                                        <?php if ($is_archived): ?>
                                        <button class="btn btn-outline-success" title="Unarchive" onclick="unarchiveUser('<?php echo htmlspecialchars($user['useraccount_id']); ?>')">
                                            <i class="fas fa-undo"></i>
                                        </button>
                                        <?php else: ?>
                                        <button class="btn btn-outline-warning" title="Archive" onclick="archiveUser('<?php echo htmlspecialchars($user['useraccount_id']); ?>')">
                                            <i class="fas fa-archive"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-users fa-3x mb-3 d-block" style="color: #d1d5db;"></i>
                                    <p>No company users found. Add your first user!</p>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Company User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="">
                        <div class="mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="firstname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="middlename" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="lastname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="contact_no" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position <span class="text-danger">*</span></label>
                            <input type="text" name="position" class="form-control" placeholder="e.g., Manager, Staff, Admin" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password <span class="text-danger">*</span></label>
                            <input type="password" name="password" class="form-control" required minlength="6">
                            <small class="text-muted">Minimum 6 characters</small>
                        </div>
                        <button type="submit" name="add_user" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">Add User</button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-user-edit me-2"></i>Edit User</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="" id="editUserForm">
                        <input type="hidden" name="edit_user_id" id="edit_user_id">
                        <div class="mb-3">
                            <label class="form-label">First Name <span class="text-danger">*</span></label>
                            <input type="text" name="edit_firstname" id="edit_firstname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Middle Name</label>
                            <input type="text" name="edit_middlename" id="edit_middlename" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Last Name <span class="text-danger">*</span></label>
                            <input type="text" name="edit_lastname" id="edit_lastname" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email <span class="text-danger">*</span></label>
                            <input type="email" name="edit_email" id="edit_email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Contact Number</label>
                            <input type="text" name="edit_contact_no" id="edit_contact_no" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Position <span class="text-danger">*</span></label>
                            <input type="text" name="edit_position" id="edit_position" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Username <span class="text-danger">*</span></label>
                            <input type="text" name="edit_username" id="edit_username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="edit_password" id="edit_password" class="form-control" minlength="6">
                            <small class="text-muted">Leave blank to keep current password</small>
                        </div>
                        <button type="submit" name="edit_user" class="btn btn-primary w-100" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">
                            <i class="fas fa-save me-2"></i>Save Changes
                        </button>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Edit User Function
        function editUser(useraccountId, firstname, middlename, lastname, email, contactNo, position, username) {
            document.getElementById('edit_user_id').value = useraccountId;
            document.getElementById('edit_firstname').value = firstname || '';
            document.getElementById('edit_middlename').value = middlename || '';
            document.getElementById('edit_lastname').value = lastname || '';
            document.getElementById('edit_email').value = email || '';
            document.getElementById('edit_contact_no').value = contactNo || '';
            document.getElementById('edit_position').value = position || '';
            document.getElementById('edit_username').value = username || '';
            document.getElementById('edit_password').value = '';
            
            const editModal = new bootstrap.Modal(document.getElementById('editUserModal'));
            editModal.show();
        }
        
        // Archive User Function
        function archiveUser(useraccountId) {
            Swal.fire({
                title: 'Archive User?',
                text: 'Are you sure you want to archive this user? They will not be able to login anymore.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, archive it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Archiving...',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    window.location.href = '?archive_user=' + encodeURIComponent(useraccountId);
                }
            });
        }
        
        // Unarchive User Function
        function unarchiveUser(useraccountId) {
            Swal.fire({
                title: 'Unarchive User?',
                text: 'Are you sure you want to unarchive this user? They will be able to login again.',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#28a745',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, unarchive!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Unarchiving...',
                        text: 'Please wait...',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    window.location.href = '?unarchive_user=' + encodeURIComponent(useraccountId);
                }
            });
        }
    </script>
</body>
</html>

