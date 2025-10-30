<?php
include '../common/session.php';
include '../common/connection.php';
include '../common/randomstrings.php';

date_default_timezone_set('Asia/Manila');

// Check login - allow all company user types
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

// Get company information
$company_query = mysqli_query($conn, 
    "SELECT c.*, ut.usertype, s.status, s.status_id
     FROM tbl_useraccount ua
     LEFT JOIN tbl_company c ON ua.company_id = c.company_id
     LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
     LEFT JOIN tbl_status s ON c.status_id = s.status_id
     WHERE ua.useraccount_id = '$useraccount_id'");
$company_row = mysqli_fetch_assoc($company_query);

// Get company user information
$company_user_query = mysqli_query($conn,
    "SELECT cu.* FROM tbl_useraccount ua
     LEFT JOIN tbl_company_user cu ON ua.company_user_id = cu.company_user_id
     WHERE ua.useraccount_id = '$useraccount_id'");
$company_user_row = mysqli_fetch_assoc($company_user_query);

$company_name = $company_row['company_name'] ?? 'Company Name';
$cert_status = $company_row['status'] ?? 'Not-Certified';
$is_halal_certified = ($cert_status == 'Halal-Certified' || $cert_status == '4' || $cert_status == 4);
$user_fullname = trim(($company_user_row['firstname'] ?? '') . ' ' . ($company_user_row['middlename'] ?? '') . ' ' . ($company_user_row['lastname'] ?? ''));
$user_fullname = $user_fullname ?: 'Company User';

// Build full company address for prefilling the application form
$company_full_address = '';
if (!empty($company_row['address_id'])) {
    $addr_q = mysqli_query($conn, "SELECT a.other as address_line, b.brgyDesc, cm.citymunDesc, p.provDesc
                                   FROM tbl_address a
                                   LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
                                   LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
                                   LEFT JOIN refprovince p ON cm.provCode = p.provCode
                                   WHERE a.address_id = '" . mysqli_real_escape_string($conn, $company_row['address_id']) . "'");
    if ($addr_q && mysqli_num_rows($addr_q) > 0) {
        $addr = mysqli_fetch_assoc($addr_q);
        $parts = [];
        if (!empty($addr['address_line'])) $parts[] = $addr['address_line'];
        if (!empty($addr['brgyDesc'])) $parts[] = $addr['brgyDesc'];
        if (!empty($addr['citymunDesc'])) $parts[] = $addr['citymunDesc'];
        if (!empty($addr['provDesc'])) $parts[] = $addr['provDesc'];
        $company_full_address = implode(', ', $parts);
    }
}
// Fallback
if (empty($company_full_address) && !empty($company_row['address_line'])) {
    $company_full_address = $company_row['address_line'];
}

// Get company's active application
$application_query = mysqli_query($conn, 
    "SELECT * FROM tbl_certification_application 
     WHERE company_id = '$company_id' 
     AND current_status NOT IN ('Approved', 'Rejected')
     ORDER BY submitted_date DESC LIMIT 1");
$application = mysqli_fetch_assoc($application_query);
$application_id = $application['application_id'] ?? null;

// Get document checklist
$checklist_query = mysqli_query($conn, 
    "SELECT * FROM tbl_document_checklist WHERE status_id = 1 ORDER BY display_order ASC");
$document_checklist = [];
while ($row = mysqli_fetch_assoc($checklist_query)) {
    $document_checklist[] = $row;
}

// Get uploaded documents for this application (get latest version of each document type)
$uploaded_documents = [];
if ($application_id) {
    $docs_query = mysqli_query($conn, 
        "SELECT d1.* 
         FROM tbl_application_documents d1
         INNER JOIN (
             SELECT document_type, MAX(date_added) as max_date
             FROM tbl_application_documents
             WHERE application_id = '$application_id'
             GROUP BY document_type
         ) d2 ON d1.document_type = d2.document_type AND d1.date_added = d2.max_date
         WHERE d1.application_id = '$application_id'
         ORDER BY d1.date_added DESC");
    while ($doc = mysqli_fetch_assoc($docs_query)) {
        $uploaded_documents[$doc['document_type']] = $doc;
    }
}

// Create document storage directory
$doc_storage_base = '../uploads/application_documents/';
if ($application_id) {
    $doc_storage = $doc_storage_base . $application_id . '/';
    if (!is_dir($doc_storage)) {
        mkdir($doc_storage, 0777, true);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Halal Certification | HalalGuide</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <link rel="stylesheet" href="css/company-common.css">
</head>
<body>
    <?php 
    $current_page = 'halal-certification.php';
    include 'includes/sidebar.php'; 
    ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div>
                <div class="d-flex align-items-center gap-3 mb-2">
                    <h1 class="page-title" style="margin-bottom: 0;">Halal Certification</h1>
                    <?php if ($is_halal_certified): ?>
                    <span class="halal-certified-badge-premium">
                        <i class="fas fa-certificate"></i>
                        <span>HALAL CERTIFIED</span>
                    </span>
                    <?php endif; ?>
                </div>
                <p class="page-subtitle">Manage your halal certification status</p>
            </div>
            <?php if (!$is_halal_certified): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#halalApplicationModal" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none; padding: 12px 24px;">
                <i class="fas fa-file-alt me-2"></i>Halal Application
            </button>
            <?php endif; ?>
        </div>
        
        <style>
        /* Halal Certified Badge - Premium Style */
        .halal-certified-badge-premium {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border-radius: 25px;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.5px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            animation: pulse-glow 2s ease-in-out infinite;
            text-transform: uppercase;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }
        
        .halal-certified-badge-premium i {
            font-size: 16px;
            animation: rotate-certificate 3s linear infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% {
                box-shadow: 0 4px 15px rgba(16, 185, 129, 0.4);
            }
            50% {
                box-shadow: 0 4px 25px rgba(16, 185, 129, 0.6);
            }
        }
        
        @keyframes rotate-certificate {
            0%, 100% {
                transform: rotate(0deg);
            }
            25% {
                transform: rotate(-5deg);
            }
            75% {
                transform: rotate(5deg);
            }
        }
        </style>
        
        <!-- Certification Status card moved next to Application Information below -->
        
        <?php if ($application && $application_id): 
            // Show Application Information
            $uploaded_count = count($uploaded_documents);
            $required_count = count(array_filter($document_checklist, fn($doc) => $doc['is_required']));
            $uploaded_required = count(array_filter($uploaded_documents, function($doc) use ($document_checklist) {
                foreach ($document_checklist as $req) {
                    if ($req['document_type'] == $doc['document_type'] && $req['is_required']) {
                        return true;
                    }
                }
                return false;
            }));
        ?>
        <!-- Application Information and Certification Status side-by-side -->
        <div class="row g-3 align-items-start mt-4">
        <div class="col-12 col-lg-8">
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Application Information</h3>
            </div>
            
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Application Number</label>
                        <div><strong><?php echo htmlspecialchars($application['application_number']); ?></strong></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Application Status</label>
                        <div>
                            <span class="status-badge badge-warning">
                                <?php echo htmlspecialchars($application['current_status']); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Application Type</label>
                        <div><?php echo htmlspecialchars($application['application_type']); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Submitted Date</label>
                        <div><?php echo date('F d, Y', strtotime($application['submitted_date'])); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Document Upload Progress:</strong> 
                <?php echo $uploaded_required; ?> of <?php echo $required_count; ?> required documents uploaded.
                <?php if ($uploaded_required < $required_count): ?>
                <br><small>Please upload all required documents to proceed with your application review.</small>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <div class="col-12 col-lg-4">
        <div class="content-card" style="position: sticky; top: 20px;">
            <div class="card-header">
                <h3 class="card-title">Certification Status</h3>
            </div>
            
            <div class="cert-status-display">
                <div class="status-badge-large <?php echo $is_halal_certified ? 'certified' : ($cert_status == 'Pending' ? 'pending' : 'not-certified'); ?>">
                    <i class="fas fa-<?php echo $is_halal_certified ? 'check-circle' : ($cert_status == 'Pending' ? 'clock' : 'times-circle'); ?>"></i>
                    <div>
                        <div class="status-title"><?php 
                            if ($is_halal_certified) {
                                echo 'Halal Certified';
                            } else if ($cert_status == 'Pending') {
                                echo 'Pending Certification';
                            } else {
                                echo 'Not Certified';
                            }
                        ?></div>
                        <div class="status-subtitle">Current Status: <?php echo htmlspecialchars($cert_status); ?></div>
                    </div>
                </div>
                
                <?php if (!$is_halal_certified): ?>
                <div class="alert alert-info mt-4">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Certification Required:</strong> Your company needs to be certified by a Halal Certifying Body to display the Halal Certified badge.
                    <?php if ($cert_status != 'Pending'): ?>
                    Click the "Halal Application" button above to start your certification process.
                    <?php else: ?>
                    Your application is being reviewed by the certifying body.
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        </div>

        <!-- Required Documents and Summary -->
        <div class="row g-3 align-items-start">
        <div class="col-12 col-md-8">
        
        <!-- Required Documents Section -->
        <div class="content-card mt-4">
            <div class="card-header">
                <h3 class="card-title">
                    <i class="fas fa-file-upload me-2"></i>Required Documents
                </h3>
            </div>
            
            <div class="document-requirements">
                <?php if (empty($document_checklist)): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    No document requirements available. Please contact the certifying body.
                </div>
                <?php else: ?>
                
                <?php foreach ($document_checklist as $doc_req): 
                    $doc_type = $doc_req['document_type'];
                    $is_uploaded = isset($uploaded_documents[$doc_type]);
                    $uploaded_doc = $uploaded_documents[$doc_type] ?? null;
                    $file_allowed = explode(',', $doc_req['file_types_allowed'] ?? 'pdf');
                    $max_size = $doc_req['max_file_size_mb'] ?? 10;
                ?>
                <div class="document-requirement-item <?php echo $is_uploaded ? 'uploaded' : 'pending'; ?>" 
                     style="padding: 20px; margin-bottom: 20px; border-left: 4px solid <?php echo $is_uploaded ? '#48bb78' : '#ed8936'; ?>; background: #f7fafc; border-radius: 8px;">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <?php if ($is_uploaded): ?>
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                <?php else: ?>
                                    <i class="fas fa-clock text-warning me-2"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($doc_req['document_name']); ?>
                                <?php if ($doc_req['is_required']): ?>
                                    <span class="badge bg-danger ms-2">Required</span>
                                <?php endif; ?>
                            </h5>
                            <?php if ($doc_req['description']): ?>
                            <p class="text-muted small mb-2"><?php echo htmlspecialchars($doc_req['description']); ?></p>
                            <?php endif; ?>
                            <div class="document-requirements-info">
                                <small class="text-muted">
                                    <i class="fas fa-file me-1"></i>Allowed: <?php echo strtoupper(implode(', ', $file_allowed)); ?>
                                    <span class="mx-2">•</span>
                                    <i class="fas fa-weight me-1"></i>Max Size: <?php echo $max_size; ?>MB
                                </small>
                            </div>
                            
                            <?php if ($is_uploaded && $uploaded_doc): ?>
                            <div class="uploaded-document-info mt-3 p-3 bg-light rounded">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>Uploaded:</strong> 
                                        <span><?php echo date('M d, Y g:i A', strtotime($uploaded_doc['date_added'])); ?></span>
                                        <?php if ($uploaded_doc['upload_status'] == 'Rejected'): ?>
                                        <span class="badge bg-danger ms-2">Rejected</span>
                                        <?php elseif ($uploaded_doc['upload_status'] == 'Pending'): ?>
                                        <span class="badge bg-warning ms-2">Pending Review</span>
                                        <?php else: ?>
                                        <span class="badge bg-success ms-2">Approved</span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <a href="view-document.php?id=<?php echo htmlspecialchars($uploaded_doc['document_id']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="fas fa-download"></i> View
                                        </a>
                                        <?php if ($uploaded_doc['upload_status'] != 'Rejected' && $uploaded_doc['upload_status'] != 'Uploaded'): ?>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger" 
                                                onclick="deleteDocument('<?php echo $uploaded_doc['document_id']; ?>')">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php if ($uploaded_doc['rejection_reason']): ?>
                                <div class="alert alert-danger mt-2 mb-0 small">
                                    <strong>Rejection Reason:</strong> <?php echo htmlspecialchars($uploaded_doc['rejection_reason']); ?>
                                </div>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if (!$is_uploaded || ($is_uploaded && $uploaded_doc && $uploaded_doc['upload_status'] == 'Rejected')): ?>
                    <div class="document-upload-form mt-3">
                        <form method="POST" enctype="multipart/form-data" class="document-upload-form-inline" id="uploadForm_<?php echo $doc_type; ?>" data-doc-type="<?php echo htmlspecialchars($doc_type); ?>" onsubmit="return confirmUploadAjax(event, '<?php echo htmlspecialchars($doc_req['document_name'], ENT_QUOTES); ?>', this);">
                            <input type="hidden" name="application_id" value="<?php echo $application_id; ?>">
                            <input type="hidden" name="document_type" value="<?php echo htmlspecialchars($doc_type); ?>">
                            
                            <div class="input-group">
                                <input type="file" 
                                       name="document_file" 
                                       class="form-control" 
                                       accept="<?php echo '.' . implode(',.', $file_allowed); ?>"
                                       required
                                       onchange="validateFile(this, <?php echo $max_size; ?>, <?php echo json_encode($file_allowed); ?>)">
                                <button type="submit" name="upload_document" class="btn btn-primary">
                                    <i class="fas fa-upload me-1"></i>Upload
                                </button>
                            </div>
                            <small class="text-muted d-block mt-1">
                                Maximum file size: <?php echo $max_size; ?>MB. Allowed formats: <?php echo strtoupper(implode(', ', $file_allowed)); ?>
                            </small>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                
                <?php endif; ?>
            </div>
        </div>
        </div> <!-- /.col-8 -->
        <?php endif; ?>

        <?php
        // Requirements Summary panel (right side)
        $summary_items = [];
        foreach ($document_checklist as $doc_req) {
            $dtype = $doc_req['document_type'];
            $name = $doc_req['document_name'];
            $status = 'Not Uploaded';
            if (isset($uploaded_documents[$dtype])) {
                $s = $uploaded_documents[$dtype]['upload_status'];
                if ($s === 'Uploaded') { $status = 'Approved'; }
                elseif ($s === 'Rejected') { $status = 'Rejected'; }
                else { $status = 'Pending'; }
            }
            $summary_items[] = [ 'type' => $dtype, 'name' => $name, 'status' => $status ];
        }
        ?>
        <div class="col-12 col-md-4">
        <div class="content-card" style="position: sticky; top: 20px;">
            <div class="card-header" style="border-bottom: 2px solid #e2e8f0; margin-bottom: 10px;">
                <h3 class="card-title"><i class="fas fa-list-check me-2"></i>Requirements Summary</h3>
            </div>
            <ul style="list-style: none; padding: 0; margin: 0;">
                <?php foreach ($summary_items as $item): 
                    $status = $item['status'];
                    $color = ($status === 'Approved') ? '#16a34a' : (($status === 'Rejected') ? '#dc2626' : '#1f2937');
                    $icon = ($status === 'Approved') ? 'fa-check-circle' : (($status === 'Rejected') ? 'fa-times-circle' : 'fa-circle');
                ?>
                <li id="summary-item_<?php echo htmlspecialchars($item['type']); ?>" style="display: flex; align-items: center; gap: 10px; padding: 6px 0; color: <?php echo $color; ?>;">
                    <i class="fas <?php echo $icon; ?>" style="color: <?php echo $color; ?>;"></i>
                    <span><?php echo htmlspecialchars($item['name']); ?></span>
                    <span style="margin-left: auto; font-size: 12px; color: <?php echo $color; ?>; font-weight: 600;">
                        <?php echo htmlspecialchars($status); ?>
                    </span>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        </div>
        </div> <!-- /.row -->
        
        <div class="content-card mt-4">
            <div class="card-header">
                <h3 class="card-title">Certification Information</h3>
            </div>
            
            <div class="row">
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Company Name</label>
                        <div><?php echo htmlspecialchars($company_name); ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Certification Status</label>
                        <div>
                            <span class="status-badge <?php 
                                if ($is_halal_certified) {
                                    echo 'badge-success';
                                } else if ($cert_status == 'Pending') {
                                    echo 'badge-warning';
                                } else {
                                    echo 'badge-danger';
                                }
                            ?>">
                                <?php echo htmlspecialchars($cert_status); ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Certification Date</label>
                        <div><?php echo !empty($company_row['date_added']) ? date('F d, Y', strtotime($company_row['date_added'])) : 'N/A'; ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="info-item">
                        <label>Last Updated</label>
                        <div><?php echo !empty($company_row['date_added']) ? date('F d, Y', strtotime($company_row['date_added'])) : 'N/A'; ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Halal Application Modal -->
    <div class="modal fade" id="halalApplicationModal" tabindex="-1">
        <div class="modal-dialog modal-xl" style="max-width: 1000px;">
            <div class="modal-content" style="border-radius: 0;">
                <div class="modal-header" style="background: white; border-bottom: 3px double #000; padding: 20px 30px;">
                    <div style="width: 100%;">
                        <!-- Header with Logos -->
                        <div style="display: flex; align-items: center; justify-content: center; gap: 30px; margin-bottom: 15px; flex-wrap: wrap;">
                            <!-- Left Logo -->
                            <div style="flex-shrink: 0; text-align: center;">
                                <?php
                                $minha_logo = '../assets2/images/minha_logo.png';
                                $minha_exists = file_exists($minha_logo);
                                ?>
                                <div style="width: 100px; height: 120px; background: white; padding: 5px; border: 1px solid #ddd; display: inline-flex; align-items: center; justify-content: center;">
                                    <?php if ($minha_exists): ?>
                                        <img src="<?php echo $minha_logo; ?>" 
                                             alt="Mindanao Halal Authority Logo" 
                                             style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: linear-gradient(135deg, #065f46 0%, #047857 100%); display: flex; flex-direction: column; justify-content: center; align-items: center; color: white; font-weight: bold;">
                                            <div style="font-size: 24px; margin-bottom: 5px;">حلال</div>
                                            <div style="font-size: 12px;">MinHA</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Center Content -->
                            <div style="text-align: center; flex: 0 1 auto; min-width: 300px; max-width: 500px;">
                                <h4 style="margin: 0; font-weight: bold; font-size: 18px; color: #000;">Mindanao Halal Authority (MinHA), Inc.</h4>
                                <p style="margin: 5px 0; font-style: italic; font-size: 12px; color: #333;">"The first NCMF accredited Halal Certifying Body"</p>
                                <p style="margin: 3px 0; font-size: 11px; color: #666;">SEC Registration No. CN200630625</p>
                                <p style="margin: 3px 0; font-size: 11px; color: #666;">NCMF-Accreditation Certificate No. 001-1213</p>
                            </div>
                            <!-- Right Logo -->
                            <div style="text-align: center; flex-shrink: 0;">
                                <?php
                                $ph_logo = '../assets2/images/ph_halal_logo.png';
                                $ph_exists = file_exists($ph_logo);
                                ?>
                                <div style="width: 100px; height: 100px; border-radius: 50%; border: 2px solid #065f46; background: white; display: inline-flex; align-items: center; justify-content: center; overflow: hidden;">
                                    <?php if ($ph_exists): ?>
                                        <img src="<?php echo $ph_logo; ?>" 
                                             alt="PHILIPPINES HALAL Official Logo" 
                                             style="max-width: 100%; max-height: 100%; object-fit: contain;">
                                    <?php else: ?>
                                        <div style="width: 100%; height: 100%; background: white; display: flex; align-items: center; justify-content: center; border: 3px solid #065f46; border-radius: 50%;">
                                            <div style="color: #065f46; font-weight: bold; font-size: 28px;">حلال</div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <p style="margin: 5px 0 0 0; font-size: 12px; font-weight: bold; color: #000;">MinHA, Inc.</p>
                            </div>
                        </div>
                        <div style="border-top: 3px double #000; margin-top: 15px; padding-top: 15px;">
                            <h2 style="text-align: center; margin: 0; font-weight: bold; font-size: 20px; letter-spacing: 2px; color: #000;">APPLICATION FOR HALAL CERTIFICATION</h2>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" style="position: absolute; top: 10px; right: 15px; opacity: 0.5;"></button>
                </div>
                <div class="modal-body" style="padding: 30px; background: #fafafa;">
                    <form method="post" action="" id="halalApplicationForm">
                        <input type="hidden" name="company_id" value="<?php echo htmlspecialchars($company_id); ?>">
                        
                        <!-- Type of Application -->
                        <?php $hasExistingApplication = !empty($application_id); ?>
                        <div class="mb-3" style="display: flex; align-items: center; gap: 20px;">
                            <label style="font-weight: bold; min-width: 150px; color: #000;">Type of Application:</label>
                            <div style="display: flex; gap: 20px;">
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="app_type" value="New" <?php echo $hasExistingApplication ? 'disabled' : 'checked'; ?>> New
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="app_type" value="Renewal" <?php echo $hasExistingApplication ? 'checked' : ''; ?>> Renewal
                                </label>
                            </div>
                            <label style="margin-left: auto; display: flex; align-items: center; gap: 5px;">
                                Date: <input type="date" name="application_date" class="form-control form-control-sm" value="<?php echo date('Y-m-d'); ?>" style="width: 150px; display: inline-block;" required>
                            </label>
                        </div>
                        <!-- Select Certifying Body -->
                        <div class="mb-3">
                            <label class="form-label" style="font-weight: bold; color:#000;">Certifying Body<span class="required">*</span></label>
                            <select name="selected_organization_id" class="form-control" required>
                                <option value="" selected disabled>Select a certifying body</option>
                                <?php
                                $orgs_res = mysqli_query($conn, "SELECT organization_id, organization_name FROM tbl_organization WHERE status_id = 1 ORDER BY organization_name");
                                while ($o = mysqli_fetch_assoc($orgs_res)) {
                                    echo '<option value="' . htmlspecialchars($o['organization_id']) . '">' . htmlspecialchars($o['organization_name']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <?php if ($hasExistingApplication): ?>
                        <div class="alert alert-info" style="padding:10px; margin-bottom:15px;">
                            You already have an existing application in process. Submitting a new application is disabled. You may submit a Renewal when applicable.
                        </div>
                        <?php endif; ?>
                        
                        <hr style="border-top: 2px solid #000; margin: 20px 0;">
                        
                        <!-- Company Information -->
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #000;">1. Name of Company:</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($company_name); ?>" readonly style="background: #f3f4f6; border: 1px solid #000;">
                        </div>
                        
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #000;">2. Business Address:</label>
                            <input type="text" name="business_address" class="form-control" placeholder="Enter complete business address..." value="<?php echo htmlspecialchars($company_full_address); ?>" required style="border: 1px solid #000;">
                        </div>
                        
                        <!-- Contact Numbers -->
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 8px; color: #000;">3. Contact Numbers:</label>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label style="font-size: 13px;">Landline No.:</label>
                                    <input type="text" name="landline" class="form-control form-control-sm" placeholder="(083) 123-4567" style="border: 1px solid #000;">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label style="font-size: 13px;">E-Mail:</label>
                                    <input type="email" name="application_email" class="form-control form-control-sm" value="<?php echo htmlspecialchars($company_row['email'] ?? ''); ?>" required style="border: 1px solid #000;">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label style="font-size: 13px;">Mobile No.:</label>
                                    <input type="text" name="application_contact" class="form-control form-control-sm" value="<?php echo htmlspecialchars($company_row['contant_no'] ?? ''); ?>" required pattern="^09[0-9]{9}$" title="Mobile number must start with 09 and be 11 digits" style="border: 1px solid #000;">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label style="font-size: 13px;">Fax No.:</label>
                                    <input type="text" name="fax_no" class="form-control form-control-sm" placeholder="(083) 123-4567" style="border: 1px solid #000;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Contact Person -->
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 5px; color: #000;">4. Contact Person:</label>
                            <div class="row">
                                <div class="col-md-8 mb-2">
                                    <input type="text" name="contact_person" class="form-control form-control-sm" placeholder="Full Name" value="<?php echo htmlspecialchars($user_fullname); ?>" required style="border: 1px solid #000;">
                                </div>
                                <div class="col-md-4 mb-2">
                                    <label style="font-size: 13px;">Position:</label>
                                    <input type="text" name="contact_position" class="form-control form-control-sm" placeholder="e.g., Manager" value="<?php echo htmlspecialchars($company_user_row['position'] ?? 'Owner/Manager'); ?>" style="border: 1px solid #000;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Legal Personality -->
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 8px; color: #000;">5. Legal Personality:</label>
                            <div style="display: flex; gap: 20px;">
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="legal_personality" value="Sole Proprietorship" required> Sole Proprietorship
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="legal_personality" value="Partnership"> Partnership
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="legal_personality" value="Corporation"> Corporation
                                </label>
                            </div>
                        </div>
                        
                        <!-- Category -->
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 8px; color: #000;">6. Category:</label>
                            <div style="display: flex; gap: 20px;">
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="category" value="Micro" required> Micro
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="category" value="Small"> Small
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="category" value="Medium"> Medium
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="radio" name="category" value="Large"> Large
                                </label>
                            </div>
                        </div>
                        
                        <!-- Type of Business -->
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 8px; color: #000;">7. Type of Business:</label>
                            <div class="row">
                                <div class="col-md-6 mb-2">
                                    <label style="font-size: 13px;">Food (Please specify):</label>
                                    <input type="text" name="business_food" class="form-control form-control-sm" placeholder="e.g., Restaurant, Bakery, Food Manufacturing" style="border: 1px solid #000;">
                                </div>
                                <div class="col-md-6 mb-2">
                                    <label style="font-size: 13px;">Non-Food (Please specify):</label>
                                    <input type="text" name="business_nonfood" class="form-control form-control-sm" placeholder="e.g., Cosmetics, Pharmaceuticals" style="border: 1px solid #000;">
                                </div>
                            </div>
                        </div>
                        
                        <!-- Products to be certified -->
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 8px; color: #000;">8. Products to be certified:</label>
                            <div class="mb-2">
                                <label style="font-size: 13px;">a.</label>
                                <input type="text" name="product_a" class="form-control form-control-sm d-inline-block" style="width: calc(100% - 30px); margin-left: 10px; border: 1px solid #000;">
                            </div>
                            <div class="mb-2">
                                <label style="font-size: 13px;">b.</label>
                                <input type="text" name="product_b" class="form-control form-control-sm d-inline-block" style="width: calc(100% - 30px); margin-left: 10px; border: 1px solid #000;">
                            </div>
                            <div class="mb-2">
                                <label style="font-size: 13px;">c.</label>
                                <input type="text" name="product_c" class="form-control form-control-sm d-inline-block" style="width: calc(100% - 30px); margin-left: 10px; border: 1px solid #000;">
                            </div>
                            <small style="font-size: 11px; color: #666; font-style: italic;">(Use additional sheets if necessary)</small>
                        </div>
                        
                        <!-- Product Characteristics -->
                        <div class="mb-3">
                            <label style="font-weight: bold; display: block; margin-bottom: 8px; color: #000;">Products processed in the plant are...</label>
                            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" name="product_porkfree" value="1"> Pork-free
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" name="product_meatfree" value="1"> Meat-free
                                </label>
                                <label style="display: flex; align-items: center; gap: 5px;">
                                    <input type="checkbox" name="product_alcoholfree" value="1"> Alcohol-free
                                </label>
                            </div>
                        </div>
                        
                        <hr style="border-top: 2px solid #000; margin: 20px 0;">
                        
                        <!-- Confidentiality Note -->
                        <div class="mb-3" style="background: #fff3cd; padding: 10px; border-left: 4px solid #f59e0b;">
                            <p style="margin: 0; font-size: 12px; font-style: italic; color: #856404;">
                                <strong>Note:</strong> The Mindanao Halal Authority, Inc. guarantees that all information submitted regarding the company will be treated with utmost confidentiality and shall not be disclosed to anyone without the permission of the company.
                            </p>
                        </div>
                        
                        <!-- Signature Section -->
                        <div class="row mt-4">
                            <div class="col-md-6">
                                <div style="border-top: 1px solid #000; padding-top: 10px; min-height: 80px;">
                                    <p style="margin: 0; font-size: 12px; font-weight: bold;">Signature over Printed Name of Applicant/Representative</p>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div style="border-top: 1px solid #000; padding-top: 10px; min-height: 80px;">
                                    <p style="margin: 0; font-size: 12px; font-weight: bold;">Position</p>
                                    <input type="text" name="applicant_position" class="form-control form-control-sm mt-2" placeholder="Enter your position" style="border: 1px solid #000;">
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer" style="border-top: 3px double #000; padding: 20px 30px; background: white;">
                    <!-- Footer Contact Info -->
                    <div style="width: 100%; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #ddd;">
                        <div style="text-align: center; font-size: 11px; color: #666;">
                            <p style="margin: 2px 0;"><strong>Address:</strong> Block 17, Lot 18, Poland St., VSM Heights Subdivision Phase 2, Barangay San Isidro, General Santos City, 9500</p>
                            <p style="margin: 2px 0;"><strong>Phone:</strong> +639638377326 / (083) 305-9476</p>
                            <p style="margin: 2px 0;"><strong>Email:</strong> mindanaohalalauthority@gmail.com</p>
                        </div>
                    </div>
                    <div style="display: flex; justify-content: space-between; width: 100%;">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border: 1px solid #000;">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" form="halalApplicationForm" name="submit_application" class="btn btn-primary" id="submitApplicationBtn" style="background: #065f46; border: 1px solid #000; color: white;">
                            <i class="fas fa-paper-plane me-2"></i>Submit Application
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        (function(){
            const hasExisting = <?php echo $hasExistingApplication ? 'true' : 'false'; ?>;
            const newRadio = document.querySelector('input[name="app_type"][value="New"]');
            const renewalRadio = document.querySelector('input[name="app_type"][value="Renewal"]');
            const submitBtn = document.getElementById('submitApplicationBtn');

            function updateSubmitVisibility(){
                if (!newRadio || !renewalRadio || !submitBtn) return;
                const isNew = newRadio.checked;
                // If there is an existing application and "New" is selected, hide submit
                if (hasExisting && isNew){
                    submitBtn.style.display = 'none';
                } else {
                    submitBtn.style.display = '';
                }
            }

            if (hasExisting && newRadio){
                // Enforce Renewal when existing app: disable New and select Renewal
                newRadio.disabled = true;
                if (renewalRadio) renewalRadio.checked = true;
            }

            if (newRadio) newRadio.addEventListener('change', updateSubmitVisibility);
            if (renewalRadio) renewalRadio.addEventListener('change', updateSubmitVisibility);
            updateSubmitVisibility();
        })();
    </script>
    <?php
    // Handle document upload
    if (isset($_POST['upload_document']) && isset($_FILES['document_file'])) {
        $isAjax = isset($_POST['ajax']) && $_POST['ajax'] === '1';
        $app_id = mysqli_real_escape_string($conn, $_POST['application_id'] ?? '');
        $doc_type = mysqli_real_escape_string($conn, $_POST['document_type'] ?? '');
        
        if (empty($app_id) || empty($doc_type)) {
            if ($isAjax) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Invalid application or document type.']);
                exit;
            }
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: 'Invalid application or document type.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            </script>";
        } else {
            // Get document checklist info
            $checklist_info_query = mysqli_query($conn, 
                "SELECT * FROM tbl_document_checklist WHERE document_type = '$doc_type' LIMIT 1");
            $checklist_info = mysqli_fetch_assoc($checklist_info_query);
            
            if (!$checklist_info) {
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Invalid document type.']);
                    exit;
                }
                echo "<script>
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: 'Invalid document type.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 3000
                    });
                </script>";
            } else {
                $file = $_FILES['document_file'];
                $file_tmp = $file['tmp_name'];
                $file_size = $file['size'];
                $file_name = $file['name'];
                $file_error = $file['error'];
                
                // Validate file
                $max_size_bytes = ($checklist_info['max_file_size_mb'] ?? 10) * 1024 * 1024;
                $allowed_exts = explode(',', $checklist_info['file_types_allowed'] ?? 'pdf');
                $allowed_exts = array_map('trim', $allowed_exts);
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                if ($file_error != UPLOAD_ERR_OK) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'File upload error occurred.']);
                        exit;
                    }
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: 'File upload error occurred.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    </script>";
                } elseif ($file_size > $max_size_bytes) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Maximum file size is ' . ($checklist_info['max_file_size_mb'] ?? 10) . 'MB.']);
                        exit;
                    }
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'File Too Large',
                            text: 'Maximum file size is " . ($checklist_info['max_file_size_mb'] ?? 10) . "MB.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    </script>";
                } elseif (!in_array($file_ext, $allowed_exts)) {
                    if ($isAjax) {
                        header('Content-Type: application/json');
                        echo json_encode(['success' => false, 'message' => 'Allowed formats: ' . strtoupper(implode(', ', $allowed_exts))]);
                        exit;
                    }
                    echo "<script>
                        Swal.fire({
                            icon: 'error',
                            title: 'Invalid File Type',
                            text: 'Allowed formats: " . strtoupper(implode(', ', $allowed_exts)) . "',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 3000
                        });
                    </script>";
                } else {
                    // Generate unique filename
                    $new_filename = 'doc_' . $doc_type . '_' . time() . '_' . uniqid() . '.' . $file_ext;
                    $file_path = $doc_storage_base . $app_id . '/' . $new_filename;
                    
                    // Ensure directory exists
                    if (!is_dir(dirname($file_path))) {
                        mkdir(dirname($file_path), 0777, true);
                    }
                    
                    if (move_uploaded_file($file_tmp, $file_path)) {
                        // Check if document already exists
                        $existing_doc_query = mysqli_query($conn, 
                            "SELECT document_id, version_number FROM tbl_application_documents 
                             WHERE application_id = '$app_id' AND document_type = '$doc_type'
                             ORDER BY version_number DESC LIMIT 1");
                        $existing_doc = mysqli_fetch_assoc($existing_doc_query);
                        
                        $version = 1;
                        if ($existing_doc) {
                            $version = $existing_doc['version_number'] + 1;
                            // Archive old version
                            mysqli_query($conn, 
                                "UPDATE tbl_application_documents SET upload_status = 'Pending' 
                                 WHERE document_id = '" . $existing_doc['document_id'] . "'");
                        }
                        
                        // Insert new document
                        $document_id = generate_string($permitted_chars, 25);
                        $insert_doc_query = "INSERT INTO tbl_application_documents 
                            (document_id, application_id, document_type, document_name, file_path, 
                             file_size, file_type, is_required, upload_status, version_number, date_added)
                            VALUES ('$document_id', '$app_id', '$doc_type', '" . 
                            mysqli_real_escape_string($conn, $checklist_info['document_name']) . "', 
                            '$file_path', '$file_size', '$file_ext', '" . 
                            ($checklist_info['is_required'] ?? 1) . "', 'Pending', '$version', NOW())";
                        
                        if (mysqli_query($conn, $insert_doc_query)) {
                            if ($isAjax) {
                                header('Content-Type: application/json');
                                echo json_encode([
                                    'success' => true,
                                    'message' => 'Your document has been uploaded successfully and is pending review.',
                                    'uploaded' => [
                                        'document_id' => $document_id,
                                        'file_path' => $file_path,
                                        'upload_status' => 'Pending',
                                        'date_added' => date('Y-m-d H:i:s'),
                                        'date_added_readable' => date('M d, Y g:i A')
                                    ]
                                ]);
                                exit;
                            }
                            echo "<script>
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Document Uploaded!',
                                    text: 'Your document has been uploaded successfully and is pending review.',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000
                                }).then(() => {
                                    window.location.reload();
                                });
                            </script>";
                        } else {
                            unlink($file_path); // Delete uploaded file if DB insert fails
                            if ($isAjax) {
                                header('Content-Type: application/json');
                                echo json_encode(['success' => false, 'message' => 'Error saving document: ' . addslashes(mysqli_error($conn))]);
                                exit;
                            }
                            echo "<script>
                                Swal.fire({
                                    icon: 'error',
                                    title: 'Upload Failed',
                                    text: 'Error saving document: " . addslashes(mysqli_error($conn)) . "',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 4000
                                });
                            </script>";
                        }
                    } else {
                        if ($isAjax) {
                            header('Content-Type: application/json');
                            echo json_encode(['success' => false, 'message' => 'Failed to move uploaded file.']);
                            exit;
                        }
                        echo "<script>
                            Swal.fire({
                                icon: 'error',
                                title: 'Upload Failed',
                                text: 'Failed to move uploaded file.',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 3000
                            });
                        </script>";
                    }
                }
            }
        }
    }
    
    // Handle document deletion
    if (isset($_GET['delete_document'])) {
        $doc_id = mysqli_real_escape_string($conn, $_GET['delete_document']);
        $doc_query = mysqli_query($conn, 
            "SELECT file_path, application_id FROM tbl_application_documents WHERE document_id = '$doc_id'");
        if ($doc_data = mysqli_fetch_assoc($doc_query)) {
            // Delete file
            if (file_exists($doc_data['file_path'])) {
                unlink($doc_data['file_path']);
            }
            // Delete from database
            mysqli_query($conn, "DELETE FROM tbl_application_documents WHERE document_id = '$doc_id'");
            
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Document Deleted',
                    text: 'Document has been deleted successfully.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 2000
                }).then(() => {
                    window.location.href = 'halal-certification.php';
                });
            </script>";
        }
    }
    
    // Handle form submission
    if (isset($_POST['submit_application'])) {
        // Get all form data
        $application_date = mysqli_real_escape_string($conn, $_POST['application_date'] ?? date('Y-m-d'));
        $app_type = mysqli_real_escape_string($conn, $_POST['app_type'] ?? 'New');
        $business_address = mysqli_real_escape_string($conn, $_POST['business_address'] ?? '');
        $application_email = mysqli_real_escape_string($conn, $_POST['application_email'] ?? '');
        $application_contact = mysqli_real_escape_string($conn, $_POST['application_contact'] ?? '');
        $landline = mysqli_real_escape_string($conn, $_POST['landline'] ?? '');
        $fax_no = mysqli_real_escape_string($conn, $_POST['fax_no'] ?? '');
        $contact_person = mysqli_real_escape_string($conn, $_POST['contact_person'] ?? '');
        $contact_position = mysqli_real_escape_string($conn, $_POST['contact_position'] ?? '');
        $legal_personality = mysqli_real_escape_string($conn, $_POST['legal_personality'] ?? '');
        $category = mysqli_real_escape_string($conn, $_POST['category'] ?? '');
        $business_food = mysqli_real_escape_string($conn, $_POST['business_food'] ?? '');
        $business_nonfood = mysqli_real_escape_string($conn, $_POST['business_nonfood'] ?? '');
        $product_a = mysqli_real_escape_string($conn, $_POST['product_a'] ?? '');
        $product_b = mysqli_real_escape_string($conn, $_POST['product_b'] ?? '');
        $product_c = mysqli_real_escape_string($conn, $_POST['product_c'] ?? '');
        $product_porkfree = isset($_POST['product_porkfree']) ? 1 : 0;
        $product_meatfree = isset($_POST['product_meatfree']) ? 1 : 0;
        $product_alcoholfree = isset($_POST['product_alcoholfree']) ? 1 : 0;
        $applicant_position = mysqli_real_escape_string($conn, $_POST['applicant_position'] ?? '');
        
        // Get selected certifying body from form
        $organization_id = mysqli_real_escape_string($conn, $_POST['selected_organization_id'] ?? '');
        if (empty($organization_id)) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Select Certifying Body',
                    text: 'Please choose an available certifying body before submitting the application.',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'OK'
                });
            </script>";
            exit();
        }
        
        if (empty($organization_id)) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: 'No certifying body organization found. Please contact administrator.',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'OK'
                });
            </script>";
            exit();
        }
        
        // Check if application already exists for this company
        $existing_app_query = mysqli_query($conn, 
            "SELECT application_id FROM tbl_certification_application 
             WHERE company_id = '$company_id' AND current_status NOT IN ('Approved', 'Rejected')");
        
        if (mysqli_num_rows($existing_app_query) > 0) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                Swal.fire({
                    icon: 'warning',
                    title: 'Application Already Exists',
                    text: 'You already have a pending application. Please wait for it to be processed.',
                    confirmButtonColor: '#065f46',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'halal-certification.php';
                });
            </script>";
            exit();
        }
        
        // Validate application form required fields (server-side)
        $appErrors = [];
        if (empty($legal_personality)) { $appErrors[] = 'Please select Legal Personality.'; }
        if (empty($category)) { $appErrors[] = 'Please select Category.'; }
        if (empty($contact_person)) { $appErrors[] = 'Please enter Contact Person.'; }
        if (!empty($application_contact) && !preg_match('/^09[0-9]{9}$/', $application_contact)) { $appErrors[] = 'Mobile No. must be 11 digits starting with 09.'; }
        if (empty($business_food) && empty($business_nonfood)) { $appErrors[] = 'Please specify at least one Type of Business (Food or Non-Food).'; }
        if (empty($product_a) && empty($product_b) && empty($product_c)) { $appErrors[] = 'Please enter at least one Product to be certified.'; }
        if (!empty($appErrors)) {
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Incomplete Application',
                    html: '<ul style=\'text-align:left;\'>" . implode("", array_map(function($m){return "<li>" . addslashes($m) . "</li>";}, $appErrors)) . "</ul>',
                    confirmButtonColor: '#d33'
                }).then(() => { history.back(); });
            </script>";
            exit();
        }

        // Generate application ID and number
        $application_id = generate_string($permitted_chars, 25);
        $application_number = 'APP-' . date('Y') . '-' . strtoupper(substr(generate_string($permitted_chars, 8), 0, 8));
        
        // Ensure unique application number
        $num_check = mysqli_query($conn, "SELECT application_id FROM tbl_certification_application WHERE application_number = '$application_number'");
        while (mysqli_num_rows($num_check) > 0) {
            $application_number = 'APP-' . date('Y') . '-' . strtoupper(substr(generate_string($permitted_chars, 8), 0, 8));
            $num_check = mysqli_query($conn, "SELECT application_id FROM tbl_certification_application WHERE application_number = '$application_number'");
        }
        
        mysqli_begin_transaction($conn);
        
        try {
            // Insert into tbl_certification_application - Default status is "Under Review"
            $insert_app_query = "INSERT INTO tbl_certification_application 
                (application_id, company_id, organization_id, application_number, application_type, 
                 current_status, submitted_date, status_id, date_added)
                VALUES ('$application_id', '$company_id', '$organization_id', '$application_number', '$app_type',
                        'Under Review', NOW(), 1, NOW())";
            
            if (!mysqli_query($conn, $insert_app_query)) {
                throw new Exception("Error creating application: " . mysqli_error($conn));
            }

            // Persist the filled Halal Application form details (if support table exists)
            $form_table_exists_rs = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_application_form'");
            if ($form_table_exists_rs && mysqli_num_rows($form_table_exists_rs) > 0) {
                $insert_form_sql = "INSERT INTO tbl_application_form (
                        application_id, application_date, business_address, landline, fax_no,
                        application_email, application_contact, contact_person, contact_position,
                        legal_personality, category, business_food, business_nonfood,
                        product_a, product_b, product_c,
                        product_porkfree, product_meatfree, product_alcoholfree,
                        applicant_position, date_added
                    ) VALUES (
                        '$application_id', '$application_date', '$business_address',
                        " . ($landline !== '' ? "'$landline'" : 'NULL') . ",
                        " . ($fax_no !== '' ? "'$fax_no'" : 'NULL') . ",
                        '$application_email', '$application_contact',
                        '$contact_person', " . ($contact_position !== '' ? "'$contact_position'" : 'NULL') . ",
                        " . ($legal_personality !== '' ? "'$legal_personality'" : 'NULL') . ",
                        " . ($category !== '' ? "'$category'" : 'NULL') . ",
                        " . ($business_food !== '' ? "'$business_food'" : 'NULL') . ",
                        " . ($business_nonfood !== '' ? "'$business_nonfood'" : 'NULL') . ",
                        " . ($product_a !== '' ? "'$product_a'" : 'NULL') . ",
                        " . ($product_b !== '' ? "'$product_b'" : 'NULL') . ",
                        " . ($product_c !== '' ? "'$product_c'" : 'NULL') . ",
                        " . (int)$product_porkfree . ", " . (int)$product_meatfree . ", " . (int)$product_alcoholfree . ",
                        " . ($applicant_position !== '' ? "'$applicant_position'" : 'NULL') . ", NOW()
                    )";
                if (!mysqli_query($conn, $insert_form_sql)) {
                    throw new Exception("Error saving application form: " . mysqli_error($conn));
                }
            }
            
            // Update company status to Pending (or update cert_status if that column exists)
            $status_check = mysqli_query($conn, "SELECT status_id FROM tbl_status WHERE status = 'Pending' LIMIT 1");
            if ($status_row = mysqli_fetch_assoc($status_check)) {
                $pending_status_id = $status_row['status_id'];
                $update_query = "UPDATE tbl_company SET status_id = '$pending_status_id'";
                
                // Also update cert_status if column exists
                $cert_status_check = mysqli_query($conn, "SHOW COLUMNS FROM tbl_company LIKE 'cert_status'");
                if (mysqli_num_rows($cert_status_check) > 0) {
                    $update_query .= ", cert_status = 'Pending'";
                }
                
                $update_query .= " WHERE company_id = '$company_id'";
                
                if (!mysqli_query($conn, $update_query)) {
                    throw new Exception("Error updating company status: " . mysqli_error($conn));
                }
            }
            
            // Create notification for HCB
            $notification_id = generate_string($permitted_chars, 25);
            $notif_query = "INSERT INTO tbl_application_notifications 
                (notification_id, application_id, notification_type, recipient_type, recipient_id, 
                 subject, message, date_added) 
                VALUES ('$notification_id', '$application_id', 'New Application', 'Certifying Body', 
                        '$organization_id', 'New Application Received', 
                        'A new certification application has been submitted by " . addslashes($company_name) . "', NOW())";
            
            mysqli_query($conn, $notif_query); // Don't fail if notification fails
            
            mysqli_commit($conn);
            
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                Swal.fire({
                    icon: 'success',
                    title: 'Application Submitted!',
                    html: 'Your Halal Certification application has been submitted successfully.<br><br><strong>Application Number: $application_number</strong><br><strong>Status: Submitted</strong><br>Please wait for review and approval.',
                    confirmButtonColor: '#065f46',
                    confirmButtonText: 'OK'
                }).then(() => {
                    window.location.href = 'halal-certification.php';
                });
            </script>";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            echo "<script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>";
            echo "<script>
                Swal.fire({
                    icon: 'error',
                    title: 'Submission Failed',
                    text: 'Error submitting application: " . addslashes($e->getMessage()) . "',
                    confirmButtonColor: '#d33',
                    confirmButtonText: 'OK'
                });
            </script>";
        }
    }
    ?>
    
    <script>
        // File validation before upload
        function validateFile(input, maxSizeMB, allowedExts) {
            const file = input.files[0];
            if (!file) return;
            
            const fileSizeMB = file.size / (1024 * 1024);
            const fileExt = file.name.split('.').pop().toLowerCase();
            const maxSize = maxSizeMB || 10;
            const allowed = allowedExts || ['pdf'];
            
            if (fileSizeMB > maxSize) {
                Swal.fire({
                    icon: 'error',
                    title: 'File Too Large',
                    text: `Maximum file size is ${maxSize}MB. Your file is ${fileSizeMB.toFixed(2)}MB.`,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000
                });
                input.value = '';
                return false;
            }
            
            if (!allowed.includes(fileExt)) {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid File Type',
                    text: `Allowed formats: ${allowed.map(e => e.toUpperCase()).join(', ')}`,
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 4000
                });
                input.value = '';
                return false;
            }
            
            return true;
        }
        
        // Confirm and upload via AJAX (no page refresh)
        function confirmUploadAjax(event, documentName, form) {
            event.preventDefault();
            const fileInput = form.querySelector('input[type="file"]');
            const file = fileInput.files[0];
            if (!file) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No File Selected',
                    text: 'Please select a file to upload.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
                return false;
            }

            const fileSizeMB = (file.size / (1024 * 1024)).toFixed(2);
            const fileName = file.name;

            Swal.fire({
                title: 'Confirm Upload',
                html: `
                    <p>Are you sure you want to upload this document?</p>
                    <div style="text-align: left; background: #f7fafc; padding: 15px; border-radius: 8px; margin-top: 10px;">
                        <p style="margin: 5px 0;"><strong>Document:</strong> ${documentName}</p>
                        <p style="margin: 5px 0;"><strong>File Name:</strong> ${fileName}</p>
                        <p style="margin: 5px 0;"><strong>File Size:</strong> ${fileSizeMB} MB</p>
                    </div>
                    <p style="margin-top: 15px; font-size: 12px; color: #666;">This document will be sent for review by the certifying body.</p>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#48bb78',
                cancelButtonColor: '#a0aec0',
                confirmButtonText: '<i class="fas fa-upload me-2"></i>Yes, Upload Document',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                reverseButtons: true
            }).then(async (result) => {
                if (!result.isConfirmed) return false;
                
                // Show loading
                Swal.fire({
                    title: 'Uploading...',
                    html: 'Please wait while we upload your document.',
                    allowOutsideClick: false,
                    allowEscapeKey: false,
                    didOpen: () => Swal.showLoading()
                });

                try {
                    const formData = new FormData(form);
                    formData.append('upload_document', '1');

                    const response = await fetch('ajax-upload-document.php', {
                        method: 'POST',
                        body: formData
                    });
                    const data = await response.json();

                    if (data && data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Document Uploaded!',
                            text: data.message || 'Your document has been uploaded and is pending review.',
                            confirmButtonColor: '#48bb78'
                        });

                        // Update UI without reload
                        const docItem = form.closest('.document-requirement-item');
                        if (docItem) {
                            docItem.classList.remove('pending');
                            docItem.classList.add('uploaded');
                        }
                        // Replace the form area with uploaded info
                        const container = form.parentElement;
                        if (container && data.uploaded) {
                            container.innerHTML = `
                                <div class="uploaded-document-info mt-3 p-3 bg-light rounded">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Uploaded:</strong>
                                            <span>${data.uploaded.date_added_readable}</span>
                                            <span class="badge bg-warning ms-2">Pending Review</span>
                                        </div>
                                        <div>
                                            <a href="${data.uploaded.file_path}" target="_blank" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-download"></i> View
                                            </a>
                                        </div>
                                    </div>
                                </div>`;
                        }

                        // Update Requirements Summary item to Pending (no reload)
                        const docType = form.getAttribute('data-doc-type');
                        if (docType) {
                            const li = document.getElementById('summary-item_' + docType);
                            if (li) {
                                li.style.color = '#1f2937';
                                const icon = li.querySelector('i');
                                if (icon) {
                                    icon.className = 'fas fa-circle';
                                    icon.style.color = '#1f2937';
                                }
                                const statusSpan = li.querySelector('span:last-child');
                                if (statusSpan) {
                                    statusSpan.textContent = 'Pending';
                                    statusSpan.style.color = '#1f2937';
                                }
                            }
                        }
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: (data && data.message) ? data.message : 'An error occurred while uploading.',
                            confirmButtonColor: '#d33'
                        });
                    }
                } catch (e) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Failed',
                        text: 'Network or server error occurred. Please try again.',
                        confirmButtonColor: '#d33'
                    });
                }

                return false;
            });

            return false;
        }
        
        // Delete document function
        function deleteDocument(documentId) {
            Swal.fire({
                title: 'Delete Document?',
                text: 'Are you sure you want to delete this document? You can upload a new one after deletion.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'halal-certification.php?delete_document=' + documentId;
                }
            });
        }

        // Client-side validation for Application form
        (function() {
            const form = document.getElementById('halalApplicationForm');
            if (!form) return;
            form.addEventListener('submit', function(e) {
                const errors = [];
                const legal = form.querySelector('input[name="legal_personality"]:checked');
                const cat = form.querySelector('input[name="category"]:checked');
                const contactPerson = form.querySelector('input[name="contact_person"]').value.trim();
                const mobile = form.querySelector('input[name="application_contact"]').value.trim();
                const food = form.querySelector('input[name="business_food"]').value.trim();
                const nonfood = form.querySelector('input[name="business_nonfood"]').value.trim();
                const prodA = form.querySelector('input[name="product_a"]').value.trim();
                const prodB = form.querySelector('input[name="product_b"]').value.trim();
                const prodC = form.querySelector('input[name="product_c"]').value.trim();

                if (!legal) errors.push('Please select Legal Personality.');
                if (!cat) errors.push('Please select Category.');
                if (!contactPerson) errors.push('Please enter Contact Person.');
                if (!/^09\d{9}$/.test(mobile)) errors.push('Mobile No. must start with 09 and be 11 digits.');
                if (!food && !nonfood) errors.push('Specify at least one Type of Business (Food or Non-Food).');
                if (!prodA && !prodB && !prodC) errors.push('Enter at least one Product to be certified.');

                if (errors.length) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Incomplete Application',
                        html: '<ul style="text-align:left;">' + errors.map(m => '<li>' + m + '</li>').join('') + '</ul>'
                    });
                }
            });
        })();
    </script>
</body>
</html>

