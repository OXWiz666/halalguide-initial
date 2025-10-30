<?php
include '../common/session.php';
include '../common/connection.php';
include '../common/randomstrings.php';

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

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateProfile'])) {
    $company_name = mysqli_real_escape_string($conn, $_POST['company_name'] ?? '');
    $company_description = mysqli_real_escape_string($conn, $_POST['company_description'] ?? '');
    $contact_no = mysqli_real_escape_string($conn, $_POST['contact_no'] ?? '');
    $tel_no = mysqli_real_escape_string($conn, $_POST['tel_no'] ?? '');
    $email = mysqli_real_escape_string($conn, $_POST['email'] ?? '');
    $address_text = mysqli_real_escape_string($conn, $_POST['address'] ?? '');
    $has_prayer_faci = isset($_POST['has_prayer_faci']) ? 1 : 0;

    // Ensure company exists
    $existing = mysqli_query($conn, "SELECT address_id FROM tbl_company WHERE company_id = '$company_id' LIMIT 1");
    $addr_row = $existing ? mysqli_fetch_assoc($existing) : null;
    $address_id = $addr_row['address_id'] ?? null;

    // Upsert address.other when user typed something
    if ($address_text !== '') {
        if (!empty($address_id)) {
            mysqli_query($conn, "UPDATE tbl_address SET other = '$address_text' WHERE address_id = '$address_id'");
        } else {
            $new_address_id = generate_string($permitted_chars, 25);
            $now = date('Y-m-d H:i:s');
            mysqli_query($conn, "INSERT INTO tbl_address (address_id, other, date_added) VALUES ('$new_address_id', '$address_text', '$now')");
            $address_id = $new_address_id;
        }
    }

    // Build company update
    $sets = [];
    $sets[] = "company_name = '$company_name'";
    $sets[] = "company_description = '$company_description'";
    $sets[] = "contant_no = '$contact_no'";
    $sets[] = "tel_no = '$tel_no'";
    $sets[] = "email = '$email'";
    $sets[] = "has_prayer_faci = $has_prayer_faci";
    if (!empty($address_id)) { $sets[] = "address_id = '$address_id'"; }
    $set_sql = implode(', ', $sets);

    mysqli_query($conn, "UPDATE tbl_company SET $set_sql WHERE company_id = '$company_id'");

    // Redirect to prevent resubmission and show status
    header('Location: profile.php?updated=1');
    exit();
}

$company_query = mysqli_query($conn, 
    "SELECT c.*, ut.usertype, s.status, a.other as address_line,
     b.brgyDesc, cm.citymunDesc, p.provDesc, r.regDesc
     FROM tbl_useraccount ua
     LEFT JOIN tbl_company c ON ua.company_id = c.company_id
     LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
     LEFT JOIN tbl_status s ON c.status_id = s.status_id
     LEFT JOIN tbl_address a ON c.address_id = a.address_id
     LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
     LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
     LEFT JOIN refprovince p ON cm.provCode = p.provCode
     LEFT JOIN refregion r ON p.regCode = r.regCode
     WHERE ua.useraccount_id = '$useraccount_id'");
$company_row = mysqli_fetch_assoc($company_query);

$company_user_query = mysqli_query($conn,
    "SELECT cu.* FROM tbl_useraccount ua
     LEFT JOIN tbl_company_user cu ON ua.company_user_id = cu.company_user_id
     WHERE ua.useraccount_id = '$useraccount_id'");
$company_user_row = mysqli_fetch_assoc($company_user_query);

$user_fullname = trim(($company_user_row['firstname'] ?? '') . ' ' . ($company_user_row['middlename'] ?? '') . ' ' . ($company_user_row['lastname'] ?? ''));
$user_fullname = $user_fullname ?: 'Company User';

// Build full address
$full_address = '';
if (!empty($company_row['address_line'])) {
    $full_address = $company_row['address_line'];
    if (!empty($company_row['brgyDesc'])) $full_address .= ', ' . $company_row['brgyDesc'];
    if (!empty($company_row['citymunDesc'])) $full_address .= ', ' . $company_row['citymunDesc'];
    if (!empty($company_row['provDesc'])) $full_address .= ', ' . $company_row['provDesc'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Profile | HalalGuide</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/company-common.css">
</head>
<body>
    <?php 
    $current_page = 'profile.php';
    include 'includes/sidebar.php'; 
    ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Company Profile</h1>
                <p class="page-subtitle">Manage your company information</p>
            </div>
            <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success mb-0" role="alert" style="border-radius: 10px;">
                <i class="fas fa-check-circle me-1"></i> Profile updated successfully.
            </div>
            <?php endif; ?>
        </div>
        
        <form method="post" action="">
            <div class="row">
                <div class="col-md-8">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Company Information</h3>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Name <span class="text-danger">*</span></label>
                            <input type="text" name="company_name" class="form-control" value="<?php echo htmlspecialchars($company_row['company_name'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Description</label>
                            <textarea name="company_description" class="form-control" rows="5"><?php echo htmlspecialchars($company_row['company_description'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Contact Number</label>
                                <input type="text" name="contact_no" class="form-control" value="<?php echo htmlspecialchars($company_row['contant_no'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Telephone Number</label>
                                <input type="text" name="tel_no" class="form-control" value="<?php echo htmlspecialchars($company_row['tel_no'] ?? ''); ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email Address <span class="text-danger">*</span></label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($company_row['email'] ?? ''); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Address</label>
                            <input type="text" name="address" class="form-control" value="<?php echo htmlspecialchars($full_address); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Company Type</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($company_row['usertype'] ?? ''); ?>" disabled>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input type="checkbox" name="has_prayer_faci" class="form-check-input" id="hasPrayer" <?php echo ($company_row['has_prayer_faci'] ?? 0) ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="hasPrayer">Our establishment has prayer facilities</label>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Status</h3>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Current Status</label>
                            <div>
                                <span class="status-badge <?php echo ($company_row['status'] == 'Halal-Certified' || $company_row['status'] == 'Active') ? 'badge-success' : 'badge-warning'; ?>">
                                    <?php echo htmlspecialchars($company_row['status'] ?? 'Active'); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="content-card">
                        <div class="card-header">
                            <h3 class="card-title">Quick Stats</h3>
                        </div>
                        <div class="d-flex flex-column gap-3">
                            <div>
                                <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Registered Date</div>
                                <div style="font-weight: 600; color: #1f2937;">
                                    <?php echo !empty($company_row['date_added']) ? date('F d, Y', strtotime($company_row['date_added'])) : 'N/A'; ?>
                                </div>
                            </div>
                            <div>
                                <div style="font-size: 12px; color: #6b7280; margin-bottom: 5px;">Company ID</div>
                                <div style="font-weight: 600; color: #1f2937; font-size: 13px;">
                                    <?php echo htmlspecialchars($company_id); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="text-end mt-4">
                <button type="submit" name="updateProfile" class="btn btn-primary btn-lg" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none; padding: 12px 30px;">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

