<?php
require_once '../common/session.php';
require_once '../common/connection.php';

date_default_timezone_set('Asia/Manila');

check_login();
check_access('Admin');

$organization_id = $_SESSION['organization_id'];
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$single_id = isset($_GET['id']) ? mysqli_real_escape_string($conn, $_GET['id']) : '';

// Organization
$org = mysqli_fetch_assoc(mysqli_query($conn, "SELECT organization_name FROM tbl_organization WHERE organization_id = '$organization_id'"));
$org_name = $org['organization_name'] ?? 'Halal Certification Board';

$where = "ca.organization_id = '$organization_id' AND ca.current_status = 'Approved'";
if (!empty($search)) {
    $where .= " AND (c.company_name LIKE '%$search%' OR ca.certificate_number LIKE '%$search%' OR ca.application_number LIKE '%$search%')";
}
if ($status_filter == 'active') {
    $where .= " AND (ca.certificate_expiry_date IS NULL OR ca.certificate_expiry_date > NOW())";
} elseif ($status_filter == 'expiring') {
    $where .= " AND ca.certificate_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";
} elseif ($status_filter == 'expired') {
    $where .= " AND ca.certificate_expiry_date < NOW()";
}
if (!empty($single_id)) {
    $where .= " AND ca.application_id = '$single_id'";
}

$sql = "SELECT ca.*, c.company_name, c.email as company_email, c.contant_no as company_contact,
                ut.usertype,
                a.other as address_line, b.brgyDesc, cm.citymunDesc, p.provDesc
        FROM tbl_certification_application ca
        LEFT JOIN tbl_company c ON ca.company_id = c.company_id
        LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
        LEFT JOIN tbl_address a ON c.address_id = a.address_id
        LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
        LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
        LEFT JOIN refprovince p ON cm.provCode = p.provCode
        WHERE $where
        ORDER BY ca.certificate_issue_date DESC, ca.date_added DESC";

$rs = mysqli_query($conn, $sql);
$rows = [];
while ($r = mysqli_fetch_assoc($rs)) { $rows[] = $r; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>HCB Certifications</title>
  <style>
    @page { size: A4; margin: 14mm 12mm; }
    body { font-family: "Inter", Arial, Helvetica, sans-serif; color: #111827; }
    .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 16px; }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand img { height: 44px; }
    .brand-title { font-weight: 800; font-size: 16px; }
    .brand-sub { color: #4b5563; font-size: 12px; }
    .meta { text-align: right; font-size: 12px; color: #374151; }
    h2 { margin: 6px 0 0 0; font-size: 18px; }
    table { width: 100%; border-collapse: collapse; font-size: 12px; }
    th, td { border: 1px solid #e5e7eb; padding: 6px 8px; vertical-align: top; }
    th { background: #f3f4f6; text-align: left; }
  </style>
</head>
<body onload="window.print()">
  <div class="header">
    <div class="brand">
      <img src="../assets2/images/ph_halal_logo.png" alt="Logo">
      <div>
        <div class="brand-title">National Commission on Muslim Filipinos</div>
        <div class="brand-sub"><?php echo htmlspecialchars($org_name); ?> • Halal Certification Board</div>
      </div>
    </div>
    <div class="meta">
      <div><strong>Report:</strong> Certifications</div>
      <?php if (!empty($status_filter)) { echo '<div>Status filter: ' . htmlspecialchars(ucfirst($status_filter)) . '</div>'; } ?>
      <div><?php echo date('M d, Y g:i A'); ?></div>
    </div>
  </div>

  <h2>Certifications</h2>
  <table>
    <thead>
      <tr>
        <th style="width: 22%;">Company</th>
        <th style="width: 16%;">Certificate / Application</th>
        <th style="width: 24%;">Address</th>
        <th style="width: 14%;">Issue Date</th>
        <th style="width: 14%;">Expiry Date</th>
        <th style="width: 10%;">Type</th>
      </tr>
    </thead>
    <tbody>
      <?php if (empty($rows)): ?>
        <tr><td colspan="6" style="text-align:center; color:#6b7280;">No records found</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?php echo htmlspecialchars($r['company_name']); ?><br><small><?php echo htmlspecialchars($r['company_email'] ?: $r['company_contact']); ?></small></td>
          <td>
            <?php echo htmlspecialchars($r['certificate_number'] ?: '—'); ?><br>
            <small>#<?php echo htmlspecialchars($r['application_number']); ?></small>
          </td>
          <td><?php echo htmlspecialchars(trim(($r['address_line']? $r['address_line'] . ', ' : '') . ($r['brgyDesc']? $r['brgyDesc'] . ', ' : '') . ($r['citymunDesc']? $r['citymunDesc'] . ', ' : '') . ($r['provDesc'] ?? ''))); ?></td>
          <td><?php echo $r['certificate_issue_date'] ? date('M d, Y', strtotime($r['certificate_issue_date'])) : ($r['approved_date'] ? date('M d, Y', strtotime($r['approved_date'])) : '—'); ?></td>
          <td><?php echo $r['certificate_expiry_date'] ? date('M d, Y', strtotime($r['certificate_expiry_date'])) : 'No Expiry'; ?></td>
          <td><?php echo htmlspecialchars($r['application_type'] ?: 'New'); ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</body>
</html>

<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

check_login();
check_access('Admin');

$organization_id = $_SESSION['organization_id'];
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$where = "ca.organization_id = '$organization_id' AND ca.current_status = 'Approved'";
if (!empty($search)) {
  $where .= " AND (c.company_name LIKE '%$search%' OR ca.certificate_number LIKE '%$search%' OR ca.application_number LIKE '%$search%')";
}
if ($status_filter == 'active') {
  $where .= " AND (ca.certificate_expiry_date IS NULL OR ca.certificate_expiry_date > NOW())";
} elseif ($status_filter == 'expiring') {
  $where .= " AND ca.certificate_expiry_date BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";
} elseif ($status_filter == 'expired') {
  $where .= " AND ca.certificate_expiry_date < NOW()";
}

$sql = "SELECT ca.*, c.company_name, c.email as company_email, c.contant_no as company_contact,
        a.other as address_line, b.brgyDesc, cm.citymunDesc, p.provDesc,
        o.organization_name
        FROM tbl_certification_application ca
        LEFT JOIN tbl_company c ON ca.company_id = c.company_id
        LEFT JOIN tbl_address a ON c.address_id = a.address_id
        LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
        LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
        LEFT JOIN refprovince p ON cm.provCode = p.provCode
        LEFT JOIN tbl_organization o ON ca.organization_id = o.organization_id
        WHERE $where
        ORDER BY ca.certificate_issue_date DESC, ca.date_added DESC";
$rs = mysqli_query($conn, $sql);
$rows = [];
while ($r = mysqli_fetch_assoc($rs)) { $rows[] = $r; }

function safe($v) { return htmlspecialchars($v ?? ''); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Print Certification</title>
  <style>
    @page { size: A4; margin: 12mm; }
    body { font-family: "Times New Roman", Georgia, serif; color: #111; }
    .certificate { position: relative; width: 100%; min-height: 265mm; padding: 18mm 16mm; box-sizing: border-box; border: 10px solid #000; background: #fff; page-break-after: always; }
    .border-inner { position: absolute; inset: 8mm; border: 2px solid #000; pointer-events: none; }
    .header { text-align: center; margin-bottom: 10mm; }
    .header .org-top { font-size: 12px; }
    .title-ar { font-family: 'Scheherazade', 'Times New Roman', serif; font-size: 16px; margin-top: 2mm; }
    .title-main { font-size: 28px; font-weight: bold; margin-top: 3mm; text-decoration: underline; }
    .section { margin: 6mm 0; }
    .label { font-size: 12px; color: #333; }
    .company-name { font-size: 22px; font-weight: 800; letter-spacing: .5px; }
    .address { font-size: 13px; font-weight: 700; }
    .body-text { font-size: 12px; line-height: 1.6; text-align: justify; }
    .footer { display: flex; justify-content: space-between; align-items: center; margin-top: 16mm; }
    .sign { text-align: center; }
    .sign .line { width: 70mm; border-top: 1px solid #000; margin: 10mm auto 2mm; }
    .seal { width: 38mm; height: 38mm; border: 3px solid #b08900; border-radius: 50%; display:flex;align-items:center;justify-content:center; font-weight: bold; color:#b08900; }
    .logos { position: absolute; top: 16mm; left: 16mm; right: 16mm; display: flex; justify-content: space-between; align-items: center; }
    .logos img { height: 26mm; }
  </style>
</head>
<body onload="window.print()">
  <?php if (empty($rows)): ?>
    <div style="text-align:center; margin-top:40mm; font-family:Arial; color:#555;">No approved certifications to print.</div>
  <?php else: foreach ($rows as $it):
    $full_address = trim(($it['address_line']? $it['address_line'] . ', ' : '') . ($it['brgyDesc']? $it['brgyDesc'] . ', ' : '') . ($it['citymunDesc']? $it['citymunDesc'] . ', ' : '') . ($it['provDesc'] ?? ''));
    $issue = $it['certificate_issue_date'] ? date('F d, Y', strtotime($it['certificate_issue_date'])) : '';
    $expiry = $it['certificate_expiry_date'] ? date('F d, Y', strtotime($it['certificate_expiry_date'])) : '';
  ?>
  <div class="certificate">
    <div class="border-inner"></div>
    <div class="logos">
      <img src="../assets2/images/ph_halal_logo.png" alt="Halal Logo" />
      <img src="../assets2/images/ph_halal_logo.png" alt="Halal Logo" />
    </div>
    <div class="header">
      <div class="org-top">Halal International Chamber of Commerce and Industries of the Philippines, Inc.</div>
      <div class="title-ar">غرفة التجارة الدولية للحلال في الفلبين</div>
      <div class="title-main">Halal Certificate</div>
    </div>
    <div class="section" style="text-align:center;">
      <div class="label">This is to certify that the following is HALAL:</div>
      <div class="company-name"><?php echo safe($it['company_name']); ?></div>
      <div class="label" style="margin-top:6mm;">with official address at</div>
      <div class="address"><?php echo safe(strtoupper($full_address)); ?></div>
    </div>
    <div class="section">
      <div class="body-text">
        This certificate is being issued after the manufacturing processes of the above-captioned establishment were subjected to a thorough halal audit and found to be in conformity with the Philippine National Standard on Halal and subsequently deliberated upon and finally approved by the Shari'ah Advisory Council (Ulama Council) of this Halal International Chamber of Commerce and Industries of the Philippines, Inc.
        The HICCIP reserves the right to suspend, withdraw, or revoke this certification prior to its expiration period if the subsequent production of the foregoing halal-certified establishment is in deliberate disregard of the Philippine National Standard on Halal and the HICCIP Halal Certification Guidelines.
      </div>
    </div>
    <div class="section" style="display:flex; justify-content:space-between;">
      <div>
        <div class="label">Date Issued:</div>
        <div style="font-weight:700;"><?php echo safe($issue); ?></div>
      </div>
      <div>
        <div class="label">Valid Until:</div>
        <div style="font-weight:700;"><?php echo safe($expiry); ?></div>
      </div>
      <div>
        <div class="label">Certificate No.</div>
        <div style="font-weight:700;"><?php echo safe($it['certificate_number']); ?></div>
      </div>
    </div>
    <div class="footer">
      <div class="seal">H A L A L</div>
      <div class="sign">
        <div class="line"></div>
        <div style="font-size:12px; font-weight:700;">Chairman, Ulama Council</div>
        <div style="font-size:11px; color:#444;">HICCIP</div>
      </div>
      <div class="seal">HICCIP</div>
    </div>
    <div style="position:absolute; bottom:10mm; left:16mm; right:16mm; text-align:center; font-size:10px; color:#333;">
      This certificate pertains to: <?php echo safe($it['organization_name']); ?> • Printed on <?php echo date('F d, Y'); ?>
    </div>
  </div>
  <?php endforeach; endif; ?>
</body>
</html>



