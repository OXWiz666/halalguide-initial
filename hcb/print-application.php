<?php
include '../common/session.php';
include '../common/connection.php';

date_default_timezone_set('Asia/Manila');

check_login();
check_access('Admin');

$organization_id = $_SESSION['organization_id'];
$application_id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');
if (empty($application_id)) {
    die('Application not specified');
}

// Organization info
$org = mysqli_fetch_assoc(mysqli_query($conn, "SELECT organization_name FROM tbl_organization WHERE organization_id = '$organization_id'"));
$org_name = $org['organization_name'] ?? 'Halal Certification Board';

// Application details (same base as application-details.php)
$form_table_exists = false;
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_application_form'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) { $form_table_exists = true; }

$select_form = '';
$join_form = '';
if ($form_table_exists) {
    $select_form = ",
        af.application_date as form_application_date,
        af.business_address as form_business_address,
        af.landline as form_landline,
        af.fax_no as form_fax_no,
        af.application_email as form_email,
        af.application_contact as form_contact,
        af.contact_person as form_contact_person,
        af.contact_position as form_contact_position,
        af.legal_personality as form_legal_personality,
        af.category as form_category,
        af.business_food as form_business_food,
        af.business_nonfood as form_business_nonfood,
        af.product_a as form_product_a,
        af.product_b as form_product_b,
        af.product_c as form_product_c,
        af.product_porkfree as form_product_porkfree,
        af.product_meatfree as form_product_meatfree,
        af.product_alcoholfree as form_product_alcoholfree,
        af.applicant_position as form_applicant_position";
    $join_form = " LEFT JOIN tbl_application_form af ON af.application_id = ca.application_id";
}

$sql = "SELECT 
    ca.*, c.company_name, c.company_description, c.email as company_email, c.contant_no as company_contact, c.tel_no,
    a.other as address_line1, b.brgyDesc, cm.citymunDesc, p.provDesc" . $select_form . "
FROM tbl_certification_application ca
LEFT JOIN tbl_company c ON ca.company_id = c.company_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode" . $join_form . "
WHERE ca.application_id = '$application_id' AND ca.organization_id = '$organization_id'";

$res = mysqli_query($conn, $sql);
if (!$res || mysqli_num_rows($res) == 0) {
    die('Application not found');
}
$app = mysqli_fetch_assoc($res);

// Documents
$docs_rs = mysqli_query($conn, "SELECT d.*, dc.document_name FROM tbl_application_documents d LEFT JOIN tbl_document_checklist dc ON d.document_type = dc.document_type WHERE d.application_id = '$application_id' ORDER BY dc.display_order ASC, d.date_added DESC");
$docs = [];
while ($row = mysqli_fetch_assoc($docs_rs)) { $docs[] = $row; }

function safe($v) { return htmlspecialchars($v ?? ''); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Application Print | <?php echo safe($app['application_number']); ?></title>
  <style>
    @page { size: A4; margin: 16mm 14mm; }
    body { font-family: "Inter", Arial, Helvetica, sans-serif; color: #111827; }
    .header { display: flex; align-items: center; justify-content: space-between; border-bottom: 2px solid #111827; padding-bottom: 8px; margin-bottom: 16px; }
    .brand { display: flex; align-items: center; gap: 12px; }
    .brand img { height: 44px; }
    .brand-title { font-weight: 800; font-size: 16px; }
    .brand-sub { color: #4b5563; font-size: 12px; }
    .meta { text-align: right; font-size: 12px; color: #374151; }
    h2 { margin: 6px 0 0 0; font-size: 18px; }
    .section { margin: 14px 0; }
    .section h3 { font-size: 14px; margin: 0 0 8px 0; border-bottom: 1px solid #e5e7eb; padding-bottom: 6px; }
    .grid { display: grid; grid-template-columns: 160px 1fr; row-gap: 6px; column-gap: 10px; font-size: 12px; }
    .label { color: #6b7280; }
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
        <div class="brand-sub"><?php echo safe($org_name); ?> • Halal Certification Board</div>
      </div>
    </div>
    <div class="meta">
      <div><strong>Application:</strong> <?php echo safe($app['application_number']); ?></div>
      <?php if (!empty($app['certificate_number'])): ?>
      <div><strong>Certificate:</strong> <?php echo safe($app['certificate_number']); ?></div>
      <?php endif; ?>
      <div><?php echo date('M d, Y g:i A'); ?></div>
    </div>
  </div>

  <div class="section" style="margin-top:6px;">
    <div style="text-align:center; font-weight:800; letter-spacing:1px; margin:6px 0 12px 0;">APPLICATION FOR HALAL CERTIFICATION</div>
    <div style="display:flex; justify-content:space-between; align-items:center; font-size:12px; margin-bottom:10px;">
      <div>Type of Application: <strong><?php echo safe($app['application_type'] ?? 'New'); ?></strong></div>
      <div>Date: <strong><?php echo isset($app['form_application_date']) && $app['form_application_date'] ? date('m/d/Y', strtotime($app['form_application_date'])) : (isset($app['submitted_date']) && $app['submitted_date'] ? date('m/d/Y', strtotime($app['submitted_date'])) : date('m/d/Y')); ?></strong></div>
    </div>
    <div style="border:1px solid #e5e7eb; padding:10px 12px; border-radius:6px; background:#f9fafb; margin-bottom:10px; font-size:12px; color:#374151;">
      You already have an existing application in process. Submitting a new application is disabled. You may submit a Renewal when applicable.
    </div>

    <div class="section">
      <div style="font-weight:700; font-size:12px; margin-bottom:6px;">1. Name of Company:</div>
      <input value="<?php echo safe($app['company_name'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
    </div>
    <div class="section">
      <div style="font-weight:700; font-size:12px; margin-bottom:6px;">2. Business Address:</div>
      <input value="<?php echo safe(($app['form_business_address'] ?? '') ?: trim(((($app['address_line1'] ?? '') ? $app['address_line1'] . ', ' : '') . (($app['brgyDesc'] ?? '') ? $app['brgyDesc'] . ', ' : '') . (($app['citymunDesc'] ?? '') ? $app['citymunDesc'] . ', ' : '') . ($app['provDesc'] ?? '')))); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
    </div>
    <div class="section">
      <div style="font-weight:700; font-size:12px; margin-bottom:6px;">3. Contact Numbers:</div>
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
        <div>
          <div class="label">Landline No.:</div>
          <input value="<?php echo safe($app['form_landline'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        </div>
        <div>
          <div class="label">E-Mail:</div>
          <input value="<?php echo safe(($app['form_email'] ?? '') ?: ($app['company_email'] ?? '')); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        </div>
        <div>
          <div class="label">Mobile No.:</div>
          <input value="<?php echo safe(($app['form_contact'] ?? '') ?: ($app['company_contact'] ?? '')); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        </div>
        <div>
          <div class="label">Fax No.:</div>
          <input value="<?php echo safe($app['form_fax_no'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        </div>
      </div>
    </div>
    <div class="section">
      <div style="font-weight:700; font-size:12px; margin-bottom:6px;">4. Contact Person:</div>
      <div style="display:grid; grid-template-columns: 2fr 1fr; gap:10px;">
        <input value="<?php echo safe($app['form_contact_person'] ?? ''); ?>" style="border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        <div>
          <div class="label">Position:</div>
          <input value="<?php echo safe($app['form_contact_position'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        </div>
      </div>
    </div>
    <div class="section">
      <div style="font-weight:700; font-size:12px; margin-bottom:6px;">5. Legal Personality:</div>
      <div style="display:flex; gap:14px; font-size:12px;">
        <?php $lp = strtolower($app['form_legal_personality'] ?? ''); ?>
        <label><input type="checkbox" <?php echo $lp=='sole proprietorship'?'checked':''; ?> /> Sole Proprietorship</label>
        <label><input type="checkbox" <?php echo $lp=='partnership'?'checked':''; ?> /> Partnership</label>
        <label><input type="checkbox" <?php echo $lp=='corporation'?'checked':''; ?> /> Corporation</label>
      </div>
    </div>
    <div class="section">
      <div style="font-weight:700; font-size:12px; margin-bottom:6px;">6. Category:</div>
      <div style="display:flex; gap:14px; font-size:12px;">
        <?php $cat = strtolower($app['form_category'] ?? ''); ?>
        <label><input type="checkbox" <?php echo $cat=='micro'?'checked':''; ?> /> Micro</label>
        <label><input type="checkbox" <?php echo $cat=='small'?'checked':''; ?> /> Small</label>
        <label><input type="checkbox" <?php echo $cat=='medium'?'checked':''; ?> /> Medium</label>
        <label><input type="checkbox" <?php echo $cat=='large'?'checked':''; ?> /> Large</label>
      </div>
    </div>
    <div class="section">
      <div style="font-weight:700; font-size:12px; margin-bottom:6px;">7. Type of Business:</div>
      <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
        <div>
          <div class="label">Food (Please specify):</div>
          <input value="<?php echo safe($app['form_business_food'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        </div>
        <div>
          <div class="label">Non-Food (Please specify):</div>
          <input value="<?php echo safe($app['form_business_nonfood'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        </div>
      </div>
      <div style="margin-top:8px; display:flex; gap:14px; font-size:12px;">
        <?php 
          $attrs = [
            'Pork-free' => (int)($app['form_product_porkfree'] ?? 0),
            'Meat-free' => (int)($app['form_product_meatfree'] ?? 0),
            'Alcohol-free' => (int)($app['form_product_alcoholfree'] ?? 0)
          ];
        ?>
        <?php foreach ($attrs as $k=>$v): ?>
          <label><input type="checkbox" <?php echo $v ? 'checked' : ''; ?> /> <?php echo $k; ?></label>
        <?php endforeach; ?>
      </div>
    </div>
    <div class="section">
      <div style="font-weight:700; font-size:12px; margin-bottom:6px;">8. Products to be certified:</div>
      <div style="display:grid; grid-template-columns: 1fr; gap:6px;">
        <input value="<?php echo safe($app['form_product_a'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        <input value="<?php echo safe($app['form_product_b'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
        <input value="<?php echo safe($app['form_product_c'] ?? ''); ?>" style="width:100%; border:1px solid #9ca3af; padding:6px 10px; border-radius:4px;" />
      </div>
    </div>
  </div>

  <div class="section">
    <h3>Documents</h3>
    <table>
      <thead>
        <tr>
          <th style="width: 35%;">Document</th>
          <th style="width: 20%;">Status</th>
          <th>Remarks</th>
          <th style="width: 22%;">Uploaded</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($docs)): ?>
        <tr><td colspan="4" style="text-align:center; color:#6b7280;">No documents uploaded</td></tr>
        <?php else: foreach ($docs as $d): ?>
        <tr>
          <td><?php echo safe($d['document_name'] ?: $d['document_type']); ?></td>
          <td><?php echo safe($d['upload_status']); ?></td>
          <td><?php echo safe($d['rejection_reason']); ?></td>
          <td><?php echo $d['date_added'] ? date('M d, Y g:i A', strtotime($d['date_added'])) : '—'; ?></td>
        </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</body>
</html>


