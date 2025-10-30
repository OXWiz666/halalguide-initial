<?php
include '../common/session.php';
include '../common/connection.php';
include '../common/randomstrings.php';

date_default_timezone_set('Asia/Manila');

check_login();
check_access('Admin');

$admin_id = $_SESSION['admin_id'];
$organization_id = $_SESSION['organization_id'];

// Logout handler
if (isset($_GET['logout'])) {
    logout();
}

$application_id = mysqli_real_escape_string($conn, $_GET['id'] ?? '');

if (empty($application_id)) {
    header("Location: applications.php");
    exit();
}

// Get application details
$app_query = "SELECT 
    ca.*,
    c.company_name,
    c.company_description,
    c.email as company_email,
    c.contant_no as company_contact,
    a.other as address_line1,
    b.brgyDesc,
    cm.citymunDesc,
    p.provDesc,
    r.regDesc
FROM tbl_certification_application ca
LEFT JOIN tbl_company c ON ca.company_id = c.company_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode
LEFT JOIN refregion r ON p.regCode = r.regCode
WHERE ca.application_id = '$application_id' AND ca.organization_id = '$organization_id'";

$app_result = mysqli_query($conn, $app_query);
if (!$app_result || mysqli_num_rows($app_result) == 0) {
    header("Location: applications.php");
    exit();
}

$application = mysqli_fetch_assoc($app_result);

// Get organization info for header
$org_query = mysqli_query($conn, "SELECT * FROM tbl_organization WHERE organization_id = '$organization_id'");
$org_row = mysqli_fetch_assoc($org_query);

// Handle form submission
if (isset($_POST['submit_evaluation'])) {
    // Check if evaluation already exists
    $existing_query = mysqli_query($conn, "SELECT evaluation_id FROM tbl_application_evaluation WHERE application_id = '$application_id'");
    $existing = mysqli_fetch_assoc($existing_query);
    
    $evaluation_id = $existing ? $existing['evaluation_id'] : generate_string($permitted_chars, 25);
    
    // Build address
    $address_parts = array_filter([
        $application['address_line1'],
        $application['brgyDesc'],
        $application['citymunDesc'],
        $application['provDesc'],
        $application['regDesc']
    ]);
    $full_address = implode(', ', $address_parts);
    
    mysqli_begin_transaction($conn);
    
    try {
        // Insert or update evaluation
        $company_name = mysqli_real_escape_string($conn, $_POST['company_name'] ?? $application['company_name']);
        $company_address = mysqli_real_escape_string($conn, $full_address);
        $nature_of_business = mysqli_real_escape_string($conn, $_POST['nature_of_business'] ?? '');
        $product_lines = mysqli_real_escape_string($conn, $_POST['product_lines'] ?? '');
        $scope = mysqli_real_escape_string($conn, $_POST['scope'] ?? '');
        $comments = mysqli_real_escape_string($conn, $_POST['comments_recommendation'] ?? '');
        $evaluated_by_name = mysqli_real_escape_string($conn, $_POST['evaluated_by_name'] ?? '');
        $evaluated_by_position = mysqli_real_escape_string($conn, $_POST['evaluated_by_position'] ?? '');
        $reviewed_by_name = mysqli_real_escape_string($conn, $_POST['reviewed_by_name'] ?? '');
        $reviewed_by_position = mysqli_real_escape_string($conn, $_POST['reviewed_by_position'] ?? '');
        $noted_by_name = mysqli_real_escape_string($conn, $_POST['noted_by_name'] ?? '');
        $noted_by_position = mysqli_real_escape_string($conn, $_POST['noted_by_position'] ?? '');
        
        if ($existing) {
            $eval_query = "UPDATE tbl_application_evaluation SET 
                company_name = '$company_name',
                company_address = '$company_address',
                nature_of_business = '$nature_of_business',
                product_lines = '$product_lines',
                scope = '$scope',
                comments_recommendation = '$comments',
                evaluated_by_name = '$evaluated_by_name',
                evaluated_by_position = '$evaluated_by_position',
                reviewed_by_name = '$reviewed_by_name',
                reviewed_by_position = '$reviewed_by_position',
                noted_by_name = '$noted_by_name',
                noted_by_position = '$noted_by_position',
                date_updated = NOW()
                WHERE evaluation_id = '$evaluation_id'";
        } else {
            $eval_query = "INSERT INTO tbl_application_evaluation 
                (evaluation_id, application_id, evaluated_by, company_name, company_address, 
                 nature_of_business, product_lines, scope, comments_recommendation,
                 evaluated_by_name, evaluated_by_position, reviewed_by_name, reviewed_by_position,
                 noted_by_name, noted_by_position, evaluation_date)
                VALUES ('$evaluation_id', '$application_id', '$admin_id', '$company_name', '$company_address',
                        '$nature_of_business', '$product_lines', '$scope', '$comments',
                        '$evaluated_by_name', '$evaluated_by_position', '$reviewed_by_name', '$reviewed_by_position',
                        '$noted_by_name', '$noted_by_position', NOW())";
        }
        
        if (!mysqli_query($conn, $eval_query)) {
            throw new Exception("Error saving evaluation: " . mysqli_error($conn));
        }
        
        // Save checklist items
        // Delete existing items first
        mysqli_query($conn, "DELETE FROM tbl_evaluation_checklist_items WHERE evaluation_id = '$evaluation_id'");
        
        // Insert checklist items
        $checklist_items = [
            ['number' => 1, 'particular' => 'Letter of Intent', 'sub_items' => []],
            ['number' => 2, 'particular' => 'Filled-out Application Form', 'sub_items' => []],
            ['number' => 3, 'particular' => '2 pcs, 2"x2" Recent ID picture of Applicant or Authorized Representative', 'sub_items' => []],
            ['number' => 4, 'particular' => 'Letter of Authority or Special Power of Attorney of Authorized Representative', 'sub_items' => []],
            ['number' => 5, 'particular' => 'Proof of Legal Personality', 'sub_items' => [
                'a. DTI Business Name Registration I (for single proprietorship), or',
                'b. SEC Registration Certificate (for corporations, partnerships and associations), or',
                '   i. Articles of Incorporation & By-Laws',
                'c. CDA Registration Certificate (for Coops)',
                'd. Certification from MAO of CAO for small farmers'
            ]],
            ['number' => 6, 'particular' => 'Permits and Licenses', 'sub_items' => [
                'a. City/Municipal Business Permit',
                'b. City/Municipal Sanitary Permit',
                'c. DENR ECC or CNC of Environmental Permit',
                'd. License to Operate (LTO) issued by:',
                '   i. FDA (for food, beverages, cosmetics, etc.)',
                '   ii. DA/BPI for agri products',
                '   iii. DA/BFAR for fishery products',
                '   iv. DA/BAFS for fertilizers and pesticides',
                '   v. DA/BAI for feed mills',
                '   vi. DA/NMIS for dressing plants & slaughterhouses',
                '   vii. DA/PCA for coconut products & by-products',
                '   viii. Others, pls. specify:'
            ]],
            ['number' => 7, 'particular' => 'Other Certifications (if any, ISO/HACCP/GMP)', 'sub_items' => []],
            ['number' => 8, 'particular' => 'Certificate of Attendance from any Halal-related events (briefings/symposium/orientation/trainings/seminars/conferences, etc.)', 'sub_items' => []],
            ['number' => 9, 'particular' => 'Halal Assurance System/Manual', 'sub_items' => []],
            ['number' => 10, 'particular' => 'Halal Assurance Officer', 'sub_items' => []]
        ];
        
        foreach ($checklist_items as $item) {
            $item_number = $item['number'];
            $particular = mysqli_real_escape_string($conn, $item['particular']);
            $sub_items_json = !empty($item['sub_items']) ? json_encode($item['sub_items']) : NULL;
            $answer = mysqli_real_escape_string($conn, $_POST['checklist_' . $item_number . '_answer'] ?? '');
            $remarks = mysqli_real_escape_string($conn, $_POST['checklist_' . $item_number . '_remarks'] ?? '');
            
            $insert_item = "INSERT INTO tbl_evaluation_checklist_items 
                (evaluation_id, item_number, particular, sub_items, answer, remarks) 
                VALUES ('$evaluation_id', '$item_number', '$particular', " . 
                ($sub_items_json ? "'$sub_items_json'" : "NULL") . ", '$answer', '$remarks')";
            
            if (!mysqli_query($conn, $insert_item)) {
                throw new Exception("Error saving checklist item: " . mysqli_error($conn));
            }
        }
        
        mysqli_commit($conn);
        $success_message = "Evaluation checklist saved successfully!";
        
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error_message = $e->getMessage();
    }
}

// Get existing evaluation if any
$eval_query = mysqli_query($conn, "SELECT * FROM tbl_application_evaluation WHERE application_id = '$application_id'");
$evaluation = mysqli_fetch_assoc($eval_query);

// Get checklist items if evaluation exists
$checklist_items_data = [];
if ($evaluation) {
    $items_query = mysqli_query($conn, "SELECT * FROM tbl_evaluation_checklist_items WHERE evaluation_id = '" . $evaluation['evaluation_id'] . "' ORDER BY item_number ASC");
    while ($item = mysqli_fetch_assoc($items_query)) {
        $checklist_items_data[$item['item_number']] = $item;
    }
}

// Define checklist items (same as above)
$checklist_items = [
    ['number' => 1, 'particular' => 'Letter of Intent', 'sub_items' => []],
    ['number' => 2, 'particular' => 'Filled-out Application Form', 'sub_items' => []],
    ['number' => 3, 'particular' => '2 pcs, 2"x2" Recent ID picture of Applicant or Authorized Representative', 'sub_items' => []],
    ['number' => 4, 'particular' => 'Letter of Authority or Special Power of Attorney of Authorized Representative', 'sub_items' => []],
    ['number' => 5, 'particular' => 'Proof of Legal Personality', 'sub_items' => [
        'a. DTI Business Name Registration I (for single proprietorship), or',
        'b. SEC Registration Certificate (for corporations, partnerships and associations), or',
        '   i. Articles of Incorporation & By-Laws',
        'c. CDA Registration Certificate (for Coops)',
        'd. Certification from MAO of CAO for small farmers'
    ]],
    ['number' => 6, 'particular' => 'Permits and Licenses', 'sub_items' => [
        'a. City/Municipal Business Permit',
        'b. City/Municipal Sanitary Permit',
        'c. DENR ECC or CNC of Environmental Permit',
        'd. License to Operate (LTO) issued by:',
        '   i. FDA (for food, beverages, cosmetics, etc.)',
        '   ii. DA/BPI for agri products',
        '   iii. DA/BFAR for fishery products',
        '   iv. DA/BAFS for fertilizers and pesticides',
        '   v. DA/BAI for feed mills',
        '   vi. DA/NMIS for dressing plants & slaughterhouses',
        '   vii. DA/PCA for coconut products & by-products',
        '   viii. Others, pls. specify:'
    ]],
    ['number' => 7, 'particular' => 'Other Certifications (if any, ISO/HACCP/GMP)', 'sub_items' => []],
    ['number' => 8, 'particular' => 'Certificate of Attendance from any Halal-related events (briefings/symposium/orientation/trainings/seminars/conferences, etc.)', 'sub_items' => []],
    ['number' => 9, 'particular' => 'Halal Assurance System/Manual', 'sub_items' => []],
    ['number' => 10, 'particular' => 'Halal Assurance Officer', 'sub_items' => []]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Applicant Evaluation Checklist | HCB Portal</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: #f7fafc;
        }
        
        .evaluation-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .document-header {
            text-align: center;
            padding: 30px 0;
            border-bottom: 2px solid #333;
            margin-bottom: 30px;
        }
        
        .header-logos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .logo-left, .logo-right {
            width: 120px;
            height: 120px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            position: relative;
            flex-shrink: 0;
            overflow: hidden;
        }
        
        .logo-left {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            border: 2px solid #333;
        }
        
        .logo-right {
            background: linear-gradient(135deg, #065f46 0%, #047857 100%);
            border: 2px solid #065f46;
        }
        
        .logo-left img,
        .logo-right img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 8px;
        }
        
        .logo-content {
            text-align: center;
            color: #000;
            font-weight: 700;
            font-size: 11px;
            line-height: 1.2;
            padding: 10px;
        }
        
        .logo-content.right {
            color: #fff;
        }
        
        .document-title {
            font-size: 24px;
            font-weight: 700;
            margin: 20px 0 10px;
            text-align: center;
        }
        
        .document-subtitle {
            font-size: 14px;
            font-style: italic;
            color: #666;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .document-info {
            font-size: 12px;
            text-align: center;
            color: #666;
            margin-bottom: 20px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: 700;
            margin: 25px 0 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #333;
        }
        
        .form-section {
            margin-bottom: 25px;
        }
        
        .form-label {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 14px;
        }
        
        .form-control, .form-select {
            border: 1px solid #333;
            border-radius: 4px;
            padding: 8px 12px;
        }
        
        .checklist-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        
        .checklist-table th,
        .checklist-table td {
            border: 1px solid #333;
            padding: 10px;
            text-align: left;
            vertical-align: top;
        }
        
        .checklist-table th {
            background: #f0f0f0;
            font-weight: 700;
            text-align: center;
        }
        
        .checklist-table .particular-col {
            width: 55%;
        }
        
        .checklist-table .yes-col,
        .checklist-table .no-col {
            width: 8%;
            text-align: center;
        }
        
        .checklist-table .remarks-col {
            width: 29%;
        }
        
        .checklist-table input[type="radio"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .sub-items {
            margin-left: 20px;
            margin-top: 5px;
            font-size: 12px;
            color: #555;
        }
        
        .sub-items li {
            margin-bottom: 3px;
        }
        
        .signature-section {
            margin-top: 50px;
            display: flex;
            justify-content: space-around;
            padding: 20px 0;
        }
        
        .signature-box {
            text-align: center;
            min-width: 220px;
            flex: 1;
            max-width: 280px;
        }
        
        .signature-line {
            border-top: 1px solid #333;
            margin-top: 80px;
            padding-top: 5px;
            min-height: 20px;
        }
        
        .signature-box .form-control {
            text-align: center;
            border: none;
            border-bottom: 1px solid #333;
            border-radius: 0;
            background: transparent;
        }
        
        .signature-box .form-control:focus {
            outline: none;
            border-bottom: 2px solid #333;
        }
        
        @media print {
            .no-print {
                display: none;
            }
            .evaluation-container {
                box-shadow: none;
                padding: 0;
            }
        }
    </style>
</head>
<body>
    <?php 
    $current_page = 'evaluation-checklist.php';
    include 'includes/sidebar.php'; 
    ?>
    
    <div class="main-content">
        <!-- Success/Error Messages -->
        <?php if (isset($success_message)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Back Button -->
        <div class="mb-3 no-print">
            <a href="application-details.php?id=<?php echo $application_id; ?>" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Application Details
            </a>
            <button onclick="window.print()" class="btn btn-primary ms-2">
                <i class="fas fa-print me-2"></i>Print Checklist
            </button>
        </div>
        
        <div class="evaluation-container">
            <!-- Document Header -->
            <div class="document-header">
                <div class="header-logos">
                    <!-- Left Logo - MinHA Logo -->
                    <div class="logo-left">
                        <?php
                        $minha_logo = '../assets2/images/minha_logo.png';
                        $minha_exists = file_exists($minha_logo);
                        ?>
                        <?php if ($minha_exists): ?>
                            <img src="<?php echo $minha_logo; ?>" alt="MinHA Logo" 
                                 style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <div class="logo-content">
                                <div style="font-weight: bold; font-size: 13px;">HALAL</div>
                                <div style="display: flex; justify-content: space-around; margin-top: 5px; font-size: 10px;">
                                    <span>Mindanao</span>
                                    <span>Authority</span>
                                </div>
                                <div style="margin-top: 5px; font-size: 10px;">Inc.</div>
                                <div style="margin-top: 8px; font-size: 9px; font-weight: normal;">Philippines</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Center Content -->
                    <div style="flex: 1; text-align: center; padding: 0 20px;">
                        <h4 style="margin: 0; font-weight: bold; font-size: 18px; color: #000;">Mindanao Halal Authority (MinHA), Inc.</h4>
                        <p style="margin: 5px 0; font-style: italic; font-size: 12px; color: #333;">"The first NCMF accredited Halal Certifying Body"</p>
                        <p style="margin: 3px 0; font-size: 11px; color: #666;">SEC Registration No. CN200630625</p>
                        <p style="margin: 3px 0; font-size: 11px; color: #666;">NCMF-Accreditation Certificate No. 001-1213</p>
                    </div>
                    
                    <!-- Right Logo - PH Halal Logo -->
                    <div class="logo-right">
                        <?php
                        $ph_logo = '../assets2/images/ph_halal_logo.png';
                        $ph_exists = file_exists($ph_logo);
                        ?>
                        <?php if ($ph_exists): ?>
                            <img src="<?php echo $ph_logo; ?>" alt="PH Halal Logo" 
                                 style="max-width: 100%; max-height: 100%; object-fit: contain;">
                        <?php else: ?>
                            <div class="logo-content right">
                                <div style="font-weight: bold; font-size: 12px; margin-bottom: 5px;">PHILIPPINES</div>
                                <div style="font-size: 14px; font-weight: bold;">HALAL</div>
                                <div style="margin-top: 8px; font-size: 9px;">MinHA, Inc.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Title -->
            <div style="text-align: center; margin: 30px 0;">
                <h2 style="font-weight: 700; letter-spacing: 2px; margin-bottom: 0;">APPLICANT EVALUATION CHECKLIST</h2>
            </div>
            
            <form method="POST" action="">
                <!-- Company Information Section -->
                <div class="form-section">
                    <div class="section-title">Company Information</div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Name of Company:</label>
                            <input type="text" class="form-control" name="company_name" 
                                   value="<?php echo htmlspecialchars($evaluation['company_name'] ?? $application['company_name']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Address:</label>
                            <textarea class="form-control" name="company_address" rows="2" readonly><?php 
                                if ($evaluation && $evaluation['company_address']) {
                                    echo htmlspecialchars($evaluation['company_address']);
                                } else {
                                    $address_parts = array_filter([
                                        $application['address_line1'],
                                        $application['brgyDesc'],
                                        $application['citymunDesc'],
                                        $application['provDesc'],
                                        $application['regDesc']
                                    ]);
                                    echo htmlspecialchars(implode(', ', $address_parts) ?: 'Address not specified');
                                }
                            ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-12">
                            <label class="form-label">Nature of Business:</label>
                            <textarea class="form-control" name="nature_of_business" rows="2"
                                      placeholder="Describe the nature of business..."><?php echo htmlspecialchars($evaluation['nature_of_business'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Product Lines:</label>
                            <textarea class="form-control" name="product_lines" rows="2"
                                      placeholder="List product lines..."><?php echo htmlspecialchars($evaluation['product_lines'] ?? ''); ?></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Scope:</label>
                            <textarea class="form-control" name="scope" rows="2"
                                      placeholder="Describe the scope..."><?php echo htmlspecialchars($evaluation['scope'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>
                
                <!-- Particulars Checklist Section -->
                <div class="form-section">
                    <div class="section-title">Particulars Checklist</div>
                    
                    <table class="checklist-table">
                        <thead>
                            <tr>
                                <th class="particular-col">Particulars</th>
                                <th class="yes-col">Yes</th>
                                <th class="no-col">No</th>
                                <th class="remarks-col">Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($checklist_items as $item): 
                                $item_data = $checklist_items_data[$item['number']] ?? null;
                                $answer = $item_data['answer'] ?? '';
                                $remarks = $item_data['remarks'] ?? '';
                            ?>
                            <tr>
                                <td class="particular-col">
                                    <strong><?php echo $item['number']; ?>. <?php echo htmlspecialchars($item['particular']); ?></strong>
                                    <?php if (!empty($item['sub_items'])): ?>
                                    <ul class="sub-items">
                                        <?php foreach ($item['sub_items'] as $sub_item): ?>
                                        <li><?php echo htmlspecialchars($sub_item); ?></li>
                                        <?php endforeach; ?>
                                    </ul>
                                    <?php endif; ?>
                                </td>
                                <td class="yes-col">
                                    <input type="radio" name="checklist_<?php echo $item['number']; ?>_answer" 
                                           value="Yes" <?php echo $answer == 'Yes' ? 'checked' : ''; ?> required>
                                </td>
                                <td class="no-col">
                                    <input type="radio" name="checklist_<?php echo $item['number']; ?>_answer" 
                                           value="No" <?php echo $answer == 'No' ? 'checked' : ''; ?>>
                                </td>
                                <td class="remarks-col">
                                    <input type="text" class="form-control" 
                                           name="checklist_<?php echo $item['number']; ?>_remarks" 
                                           value="<?php echo htmlspecialchars($remarks); ?>"
                                           placeholder="Remarks...">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Comments/Recommendation Section -->
                <div class="form-section">
                    <div class="section-title">Comments/Recommendation</div>
                    <textarea class="form-control" name="comments_recommendation" rows="6"
                              placeholder="Enter comments or recommendations..."><?php echo htmlspecialchars($evaluation['comments_recommendation'] ?? ''); ?></textarea>
                </div>
                
                <!-- Signatures Section -->
                <div class="form-section">
                    <div class="signature-section">
                        <div class="signature-box">
                            <div class="form-label" style="font-weight: 600; margin-bottom: 10px;">Evaluated by:</div>
                            <div class="signature-line">
                                <input type="text" class="form-control" 
                                       name="evaluated_by_name" 
                                       value="<?php echo htmlspecialchars($evaluation['evaluated_by_name'] ?? ''); ?>"
                                       placeholder="Signature">
                            </div>
                            <input type="text" class="form-control mt-3" 
                                   name="evaluated_by_position" 
                                   value="<?php echo htmlspecialchars($evaluation['evaluated_by_position'] ?? ''); ?>"
                                   placeholder="Position/Title"
                                   style="font-size: 12px; border-bottom: 1px solid #333;">
                        </div>
                        
                        <div class="signature-box">
                            <div class="form-label" style="font-weight: 600; margin-bottom: 10px;">Reviewed by:</div>
                            <div class="signature-line">
                                <input type="text" class="form-control" 
                                       name="reviewed_by_name" 
                                       value="<?php echo htmlspecialchars($evaluation['reviewed_by_name'] ?? ''); ?>"
                                       placeholder="Signature">
                            </div>
                            <input type="text" class="form-control mt-3" 
                                   name="reviewed_by_position" 
                                   value="<?php echo htmlspecialchars($evaluation['reviewed_by_position'] ?? ''); ?>"
                                   placeholder="Position/Title"
                                   style="font-size: 12px; border-bottom: 1px solid #333;">
                        </div>
                        
                        <div class="signature-box">
                            <div class="form-label" style="font-weight: 600; margin-bottom: 10px;">Noted by:</div>
                            <div class="signature-line">
                                <input type="text" class="form-control" 
                                       name="noted_by_name" 
                                       value="<?php echo htmlspecialchars($evaluation['noted_by_name'] ?? ''); ?>"
                                       placeholder="Signature">
                            </div>
                            <input type="text" class="form-control mt-3" 
                                   name="noted_by_position" 
                                   value="<?php echo htmlspecialchars($evaluation['noted_by_position'] ?? ''); ?>"
                                   placeholder="Position/Title"
                                   style="font-size: 12px; border-bottom: 1px solid #333;">
                        </div>
                    </div>
                </div>
                
                <div class="text-center mt-4 mb-4 no-print">
                    <button type="submit" name="submit_evaluation" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Save Evaluation Checklist
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        <?php if (isset($success_message)): ?>
        Swal.fire({
            icon: 'success',
            title: 'Saved!',
            text: '<?php echo addslashes($success_message); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true
        });
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: '<?php echo addslashes($error_message); ?>',
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true
        });
        <?php endif; ?>
    </script>
</body>
</html>

