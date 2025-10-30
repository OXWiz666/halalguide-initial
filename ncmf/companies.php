<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

check_login();
check_access('Super Admin');

$current_page = 'companies.php';

$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, trim($_GET['search'])) : '';

$where = '1=1';
if (!empty($search)) {
    $q = "%$search%";
    $where .= " AND (c.company_name LIKE '$q' OR c.email LIKE '$q' OR a.other LIKE '$q')";
}

$sql = "SELECT c.company_id, c.company_name, c.email, c.contant_no, c.usertype_id, ut.usertype,
                a.other as address_line, b.brgyDesc, cm.citymunDesc, p.provDesc, s.status
        FROM tbl_company c
        LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
        LEFT JOIN tbl_status s ON c.status_id = s.status_id
        LEFT JOIN tbl_address a ON c.address_id = a.address_id
        LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
        LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
        LEFT JOIN refprovince p ON cm.provCode = p.provCode
        WHERE $where
        ORDER BY c.date_added DESC";

$rs = mysqli_query($conn, $sql);
$companies = [];
while ($row = mysqli_fetch_assoc($rs)) { $companies[] = $row; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NCMF | Company Listing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Use the same base styles/font as the rest of the app (AdminLTE) -->
    <link rel="stylesheet" href="../dist/css/adminlte.min.css">
    <style>
        body { font-family: "Source Sans Pro", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol"; }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title">
                <h2>Company Listing</h2>
                <p>Manage all registered companies</p>
            </div>
            <form method="get" action="" style="display:flex; gap:10px; align-items:center;">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search companies..." style="padding:8px 12px;border:1px solid #e2e8f0;border-radius:8px;">
                <button class="menu-item" style="padding:8px 14px; background:#667eea;color:#fff;border:none;border-radius:8px;cursor:pointer;">Search</button>
            </form>
        </div>

        <div class="content-card">
            <div style="overflow:auto;">
            <table style="width:100%; border-collapse:collapse;">
                <thead>
                    <tr style="background:#f7fafc;">
                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Company</th>
                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Address</th>
                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Contact</th>
                        <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($companies)): ?>
                        <tr><td colspan="5" style="text-align:center;color:#718096;padding:20px;">No companies found</td></tr>
                    <?php else: foreach ($companies as $c): ?>
                        <tr>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                <strong><?php echo htmlspecialchars($c['company_name']); ?></strong><br>
                                <small><?php echo htmlspecialchars($c['email']); ?></small>
                            </td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                <?php echo htmlspecialchars($c['usertype'] ?? ''); ?>
                            </td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                <?php 
                                    $addr = trim(($c['address_line']? $c['address_line'] . ', ' : '') . ($c['brgyDesc']? $c['brgyDesc'] . ', ' : '') . ($c['citymunDesc']? $c['citymunDesc'] . ', ' : '') . ($c['provDesc'] ?? ''));
                                    echo htmlspecialchars($addr);
                                ?>
                            </td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                <?php echo htmlspecialchars($c['contant_no'] ?: '—'); ?>
                            </td>
                            <td style="padding:10px;border-bottom:1px solid #f1f5f9;">
                                <?php echo htmlspecialchars($c['status'] ?? ''); ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>
</body>
</html>


