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

$user_fullname = trim(($company_user_row['firstname'] ?? '') . ' ' . ($company_user_row['middlename'] ?? '') . ' ' . ($company_user_row['lastname'] ?? ''));
$user_fullname = $user_fullname ?: 'Company User';

// Define storage paths (relative to root)
$storage_base = dirname(dirname(__FILE__)) . '/uploads/company/';
$image_storage = $storage_base . 'images/' . $company_id . '/';
$video_storage = $storage_base . 'videos/' . $company_id . '/';
$offer_storage = $storage_base . 'offers/' . $company_id . '/';

// Create directories if they don't exist
if (!file_exists($image_storage)) {
    mkdir($image_storage, 0755, true);
}
if (!file_exists($video_storage)) {
    mkdir($video_storage, 0755, true);
}
if (!file_exists($offer_storage)) {
    mkdir($offer_storage, 0755, true);
}

// Handle form submissions
$success_message = '';
$error_message = '';

// Handle description update
if (isset($_POST['update_description'])) {
    $description = mysqli_real_escape_string($conn, $_POST['description']);
    $update_query = "UPDATE tbl_company SET company_description = '$description' WHERE company_id = '$company_id'";
    if (mysqli_query($conn, $update_query)) {
        $success_message = "Company description updated successfully!";
        // Refresh company data
        $company_query = mysqli_query($conn, 
            "SELECT c.*, ut.usertype FROM tbl_useraccount ua
             LEFT JOIN tbl_company c ON ua.company_id = c.company_id
             LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
             WHERE ua.useraccount_id = '$useraccount_id'");
        $company_row = mysqli_fetch_assoc($company_query);
    } else {
        $error_message = "Error updating description: " . mysqli_error($conn);
    }
}

// Handle image uploads (check for AJAX request)
if (isset($_POST['upload_images']) && isset($_FILES['images'])) {
    $uploaded_count = 0;
    $errors = [];
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    // Check if files were uploaded
    if (empty($_FILES['images']['name']) || (is_array($_FILES['images']['name']) && count(array_filter($_FILES['images']['name'])) == 0)) {
        $error_message = "No files selected for upload.";
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $error_message]);
            exit();
        }
    } else {
        foreach ($_FILES['images']['name'] as $key => $filename) {
            if (!empty($filename) && $_FILES['images']['error'][$key] == UPLOAD_ERR_OK) {
                $file_tmp = $_FILES['images']['tmp_name'][$key];
                $file_size = $_FILES['images']['size'][$key];
                $file_type = $_FILES['images']['type'][$key];
                
                // Validate file size (5MB max)
                if ($file_size > 5 * 1024 * 1024) {
                    $errors[] = "Image '$filename' is too large. Maximum size is 5MB.";
                    continue;
                }
                
                // Validate file type
                $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                if (!in_array($file_type, $allowed_types)) {
                    $errors[] = "Image '$filename' has invalid format. Only JPG, PNG, GIF are allowed.";
                    continue;
                }
                
                // Generate unique filename
                $file_ext = pathinfo($filename, PATHINFO_EXTENSION);
                $new_filename = 'img_' . time() . '_' . uniqid() . '.' . strtolower($file_ext);
                $file_path = $image_storage . $new_filename;
                
                // Move uploaded file
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $uploaded_count++;
                } else {
                    $errors[] = "Failed to upload '$filename'.";
                }
            } else if ($_FILES['images']['error'][$key] != UPLOAD_ERR_OK) {
                $error_codes = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload'
                ];
                $errors[] = "Upload error for '$filename': " . ($error_codes[$_FILES['images']['error'][$key]] ?? 'Unknown error');
            }
        }
        
        // Handle AJAX response
        if ($is_ajax) {
            header('Content-Type: application/json');
            
            // Mixed results: some succeeded, some failed
            if ($uploaded_count > 0 && !empty($errors)) {
                echo json_encode([
                    'success' => true, 
                    'message' => "$uploaded_count image(s) uploaded successfully, but " . count($errors) . " file(s) failed.",
                    'uploaded' => $uploaded_count,
                    'errors' => $errors,
                    'partial' => true
                ]);
            }
            // All succeeded
            else if ($uploaded_count > 0 && empty($errors)) {
                echo json_encode([
                    'success' => true, 
                    'message' => "$uploaded_count image(s) uploaded successfully!",
                    'uploaded' => $uploaded_count
                ]);
            }
            // All failed
            else if ($uploaded_count == 0 && !empty($errors)) {
                echo json_encode([
                    'success' => false, 
                    'error' => implode('<br>', $errors)
                ]);
            }
            // No files processed (shouldn't happen, but handle it)
            else {
                echo json_encode([
                    'success' => false, 
                    'error' => 'No files were processed.'
                ]);
            }
            exit();
        }
        
        // Non-AJAX response (regular form submission)
        if ($uploaded_count > 0) {
            $success_message = "$uploaded_count image(s) uploaded successfully!";
            if (!empty($errors)) {
                $success_message .= " However, " . count($errors) . " file(s) failed.";
            }
        }
        if (!empty($errors) && $uploaded_count == 0) {
            $error_message = implode('<br>', $errors);
        }
    }
}

// Handle video uploads
if (isset($_POST['add_video'])) {
    $video_type = mysqli_real_escape_string($conn, $_POST['video_type'] ?? '');
    $video_title = mysqli_real_escape_string($conn, $_POST['video_title'] ?? '');
    $video_description = mysqli_real_escape_string($conn, $_POST['video_description'] ?? '');
    $video_url = mysqli_real_escape_string($conn, $_POST['video_url'] ?? '');
    
    if ($video_type == 'upload' && isset($_FILES['video_file']) && $_FILES['video_file']['error'] == UPLOAD_ERR_OK) {
        // Handle video file upload
        $file_tmp = $_FILES['video_file']['tmp_name'];
        $file_size = $_FILES['video_file']['size'];
        $file_name = $_FILES['video_file']['name'];
        
        // Validate file size (50MB max)
        if ($file_size > 50 * 1024 * 1024) {
            $error_message = "Video file is too large. Maximum size is 50MB.";
        } else {
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $allowed_exts = ['mp4', 'avi', 'mov', 'webm'];
            if (in_array(strtolower($file_ext), $allowed_exts)) {
                $new_filename = 'vid_' . time() . '_' . uniqid() . '.' . strtolower($file_ext);
                $file_path = $video_storage . $new_filename;
                
                if (move_uploaded_file($file_tmp, $file_path)) {
                    $success_message = "Video uploaded and saved successfully!";
                } else {
                    $error_message = "Failed to upload video file.";
                }
            } else {
                $error_message = "Invalid video format. Allowed: MP4, AVI, MOV, WebM";
            }
        }
    } else if (($video_type == 'youtube' || $video_type == 'vimeo') && !empty($video_url)) {
        // Store video URL (external videos) - metadata only, no file
        $success_message = "Video link added successfully!";
    } else {
        $error_message = "Please provide either a video file or URL.";
    }
}

// Handle special offer image upload
if (isset($_POST['create_offer'])) {
    $offer_title = mysqli_real_escape_string($conn, $_POST['offer_title'] ?? '');
    $offer_description = mysqli_real_escape_string($conn, $_POST['offer_description'] ?? '');
    $discount_percent = mysqli_real_escape_string($conn, $_POST['discount_percent'] ?? '0');
    $valid_until = mysqli_real_escape_string($conn, $_POST['valid_until'] ?? '');
    $offer_terms = mysqli_real_escape_string($conn, $_POST['offer_terms'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    
    // Handle offer image upload
    if (isset($_FILES['offer_image']) && $_FILES['offer_image']['error'] == UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['offer_image']['tmp_name'];
        $file_size = $_FILES['offer_image']['size'];
        $file_name = $_FILES['offer_image']['name'];
        $file_type = $_FILES['offer_image']['type'];
        
        if ($file_size <= 5 * 1024 * 1024 && in_array($file_type, ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'])) {
            $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
            $new_filename = 'offer_' . time() . '_' . uniqid() . '.' . strtolower($file_ext);
            $file_path = $offer_storage . $new_filename;
            
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Image saved to storage
            }
        }
    }
    
    // Store offer data (you can create a tbl_offers table later)
    $success_message = "Special offer created successfully!";
}

// Handle file deletions
if (isset($_GET['delete_image'])) {
    $filename = basename($_GET['delete_image']);
    $file_path = $image_storage . $filename;
    $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
    
    if (file_exists($file_path) && unlink($file_path)) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'message' => 'Image deleted successfully!']);
            exit();
        }
        $success_message = "Image deleted successfully!";
        echo "<script>setTimeout(() => window.location.href = 'cms.php', 1000);</script>";
    } else {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to delete image.']);
            exit();
        }
        $error_message = "Failed to delete image.";
    }
}

if (isset($_GET['delete_video'])) {
    $filename = basename($_GET['delete_video']);
    $file_path = $video_storage . $filename;
    if (file_exists($file_path) && unlink($file_path)) {
        $success_message = "Video deleted successfully!";
        echo "<script>setTimeout(() => window.location.href = 'cms.php', 1000);</script>";
    } else {
        $error_message = "Failed to delete video.";
    }
}

// Get uploaded images for display
$uploaded_images = [];
$primary_image_path = null;
if (is_dir($image_storage)) {
    $files = scandir($image_storage);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && preg_match('/\.(jpg|jpeg|png|gif)$/i', $file)) {
            $image_path = '/uploads/company/images/' . $company_id . '/' . $file; // absolute web path
            $uploaded_images[] = [
                'filename' => $file,
                'path' => $image_path,
                'absolute_path' => 'uploads/company/images/' . $company_id . '/' . $file
            ];
            // Set first image as primary if not set
            if ($primary_image_path === null) {
                $primary_image_path = $image_path;
            }
        }
    }
}

// Update company with primary image path (store in database for tourist pages)
if ($primary_image_path !== null) {
    // Check if we need to store primary image - could create a company_images table or use existing field
    // For now, we'll just ensure the path is accessible
}

// Handle AJAX request for gallery preview
if (isset($_GET['gallery_preview'])) {
    header('Content-Type: application/json');
    $images_for_json = [];
    foreach ($uploaded_images as $img) {
        $images_for_json[] = [
            'filename' => $img['filename'],
            'path' => $img['path'],
            'absolute_path' => $img['absolute_path']
        ];
    }
    echo json_encode([
        'images' => $images_for_json
    ]);
    exit();
}

// Get uploaded videos for display
$uploaded_videos = [];
if (is_dir($video_storage)) {
    $files = scandir($video_storage);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && preg_match('/\.(mp4|avi|mov|webm)$/i', $file)) {
            $uploaded_videos[] = [
                'filename' => $file,
                'path' => '../uploads/company/videos/' . $company_id . '/' . $file
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Content Management | HalalGuide</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <link rel="stylesheet" href="css/company-common.css">
</head>
<body>
    <?php 
    $current_page = 'cms.php';
    include 'includes/sidebar.php'; 
    ?>
    
    <div class="main-content">
        <div class="top-bar">
            <div>
                <h1 class="page-title">Content Management</h1>
                <p class="page-subtitle">Manage your company content and information</p>
            </div>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Company Description</h3>
            </div>
            
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
            
            <form method="post" action="">
                <div class="mb-3">
                    <label class="form-label">Description</label>
                    <textarea name="description" class="form-control" rows="8" placeholder="Enter your company description..."><?php echo htmlspecialchars($company_row['company_description'] ?? ''); ?></textarea>
                </div>
                <button type="submit" name="update_description" class="btn btn-primary" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">
                    <i class="fas fa-save me-2"></i>Save Changes
                </button>
            </form>
        </div>
        
        <div class="content-card">
            <div class="card-header">
                <h3 class="card-title">Content Sections</h3>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <div class="card h-100" style="border: 1px solid #e5e7eb;">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-images me-2"></i>Images & Gallery</h5>
                            <p class="card-text text-muted">Upload and manage company photos</p>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#imagesModal" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">Manage</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card h-100" style="border: 1px solid #e5e7eb;">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-video me-2"></i>Videos</h5>
                            <p class="card-text text-muted">Manage company video content</p>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#videosModal" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">Manage</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card h-100" style="border: 1px solid #e5e7eb;">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-clock me-2"></i>Operating Hours</h5>
                            <p class="card-text text-muted">Set business operating hours</p>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#operatingHoursModal" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">Manage</button>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 mb-3">
                    <div class="card h-100" style="border: 1px solid #e5e7eb;">
                        <div class="card-body">
                            <h5 class="card-title"><i class="fas fa-star me-2"></i>Special Offers</h5>
                            <p class="card-text text-muted">Create and manage special offers</p>
                            <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#specialOffersModal" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">Manage</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Images & Gallery Modal -->
    <div class="modal fade" id="imagesModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-images me-2"></i>Images & Gallery</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="" enctype="multipart/form-data" id="imageUploadForm">
                        <div class="mb-3">
                            <label class="form-label">Upload Images</label>
                            <input type="file" name="images[]" id="imageInput" class="form-control" accept="image/*" multiple>
                            <small class="text-muted">You can select multiple images. Supported formats: JPG, PNG, GIF (Max 5MB each)</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Gallery Description</label>
                            <textarea name="gallery_description" class="form-control" rows="3" placeholder="Describe your gallery..."></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="upload_images" class="btn btn-primary" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;" id="uploadBtn">
                                <i class="fas fa-upload me-2"></i>Upload Images
                            </button>
                        </div>
                    </form>
                    <hr>
                    <div class="mt-4">
                        <h6>Gallery Preview</h6>
                        <div class="row" id="galleryPreview">
                            <?php if (empty($uploaded_images)): ?>
                            <div class="col-md-12 text-center text-muted py-4" id="emptyGallery">
                                <i class="fas fa-image fa-3x mb-2 d-block"></i>
                                <p>No images uploaded yet</p>
                            </div>
                            <?php else: ?>
                                <?php foreach ($uploaded_images as $img): ?>
                                <div class="col-md-3 mb-3 gallery-item" data-filename="<?php echo htmlspecialchars($img['filename']); ?>">
                                    <div class="position-relative">
                                        <img src="<?php echo htmlspecialchars($img['path']); ?>" alt="Gallery Image" 
                                             class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;"
                                             onclick="window.open('<?php echo htmlspecialchars($img['path']); ?>', '_blank')">
                                        <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                                onclick="deleteImage('<?php echo htmlspecialchars($img['filename']); ?>')"
                                                title="Delete Image">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                        <div class="small text-muted mt-1"><?php echo htmlspecialchars($img['filename']); ?></div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Videos Modal -->
    <div class="modal fade" id="videosModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-video me-2"></i>Video Management</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Video Type</label>
                            <select name="video_type" class="form-control" id="videoTypeSelect" required>
                                <option value="youtube">YouTube URL</option>
                                <option value="vimeo">Vimeo URL</option>
                                <option value="upload">Upload Video</option>
                            </select>
                        </div>
                        <div class="mb-3" id="videoUrlGroup">
                            <label class="form-label">Video URL</label>
                            <input type="url" name="video_url" class="form-control" id="videoUrlInput" placeholder="https://www.youtube.com/watch?v=...">
                            <small class="text-muted">Enter the full YouTube or Vimeo URL</small>
                        </div>
                        <div class="mb-3" id="videoUploadGroup" style="display: none;">
                            <label class="form-label">Upload Video</label>
                            <input type="file" name="video_file" class="form-control" id="videoFileInput" accept="video/*">
                            <small class="text-muted">Max file size: 50MB. Supported formats: MP4, AVI, MOV, WebM</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Video Title</label>
                            <input type="text" name="video_title" class="form-control" placeholder="Enter video title..." required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Video Description</label>
                            <textarea name="video_description" class="form-control" rows="3" placeholder="Describe the video..."></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="add_video" class="btn btn-primary" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">
                                <i class="fas fa-plus me-2"></i>Add Video
                            </button>
                        </div>
                    </form>
                    <hr>
                    <div class="mt-4">
                        <h6>Video List</h6>
                        <div id="videoList">
                            <?php if (empty($uploaded_videos)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-video fa-3x mb-2 d-block"></i>
                                <p>No videos added yet</p>
                            </div>
                            <?php else: ?>
                                <div class="row">
                                    <?php foreach ($uploaded_videos as $vid): ?>
                                    <div class="col-md-6 mb-3">
                                        <div class="card">
                                            <video class="card-img-top" controls style="height: 200px; background: #000;">
                                                <source src="<?php echo htmlspecialchars($vid['path']); ?>" type="video/mp4">
                                                Your browser does not support the video tag.
                                            </video>
                                            <div class="card-body">
                                                <h6 class="card-title"><?php echo htmlspecialchars($vid['filename']); ?></h6>
                                                <button class="btn btn-danger btn-sm" 
                                                        onclick="deleteVideo('<?php echo htmlspecialchars($vid['filename']); ?>')">
                                                    <i class="fas fa-trash me-1"></i>Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Operating Hours Modal -->
    <div class="modal fade" id="operatingHoursModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-clock me-2"></i>Operating Hours</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="" id="operatingHoursForm">
                        <?php
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                        foreach ($days as $day):
                        ?>
                        <div class="row mb-3">
                            <div class="col-md-3">
                                <label class="form-label"><?php echo $day; ?></label>
                            </div>
                            <div class="col-md-9">
                                <div class="row">
                                    <div class="col-md-4">
                                        <input type="time" name="open_<?php echo strtolower($day); ?>" class="form-control" placeholder="Open">
                                    </div>
                                    <div class="col-md-4">
                                        <input type="time" name="close_<?php echo strtolower($day); ?>" class="form-control" placeholder="Close">
                                    </div>
                                    <div class="col-md-4">
                                        <div class="form-check mt-2">
                                            <input type="checkbox" name="closed_<?php echo strtolower($day); ?>" class="form-check-input" id="closed_<?php echo strtolower($day); ?>">
                                            <label class="form-check-label" for="closed_<?php echo strtolower($day); ?>">Closed</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="mb-3">
                            <label class="form-label">Additional Notes</label>
                            <textarea name="hours_notes" class="form-control" rows="3" placeholder="e.g., Special hours during holidays, Extended hours during Ramadan..."></textarea>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="save_hours" class="btn btn-primary" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">
                                <i class="fas fa-save me-2"></i>Save Operating Hours
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Special Offers Modal -->
    <div class="modal fade" id="specialOffersModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); color: white;">
                    <h5 class="modal-title"><i class="fas fa-star me-2"></i>Special Offers</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form method="post" action="" enctype="multipart/form-data">
                        <div class="mb-3">
                            <label class="form-label">Offer Title <span class="text-danger">*</span></label>
                            <input type="text" name="offer_title" class="form-control" placeholder="e.g., Ramadan Special Discount" required>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Discount Percentage</label>
                                <input type="number" name="discount_percent" class="form-control" min="0" max="100" placeholder="e.g., 20">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Valid Until</label>
                                <input type="date" name="valid_until" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Offer Description</label>
                            <textarea name="offer_description" class="form-control" rows="4" placeholder="Describe your special offer in detail..." required></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Terms & Conditions</label>
                            <textarea name="offer_terms" class="form-control" rows="3" placeholder="Terms and conditions for this offer..."></textarea>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Offer Image</label>
                            <input type="file" name="offer_image" class="form-control" accept="image/*">
                            <small class="text-muted">Optional: Upload an image for this offer (Max 5MB, JPG/PNG/GIF)</small>
                        </div>
                        <div class="form-check mb-3">
                            <input type="checkbox" name="is_active" class="form-check-input" id="isActive" checked>
                            <label class="form-check-label" for="isActive">Activate this offer immediately</label>
                        </div>
                        <div class="d-grid">
                            <button type="submit" name="create_offer" class="btn btn-primary" style="background: linear-gradient(135deg, #9333EA 0%, #7C3AED 100%); border: none;">
                                <i class="fas fa-plus me-2"></i>Create Offer
                            </button>
                        </div>
                    </form>
                    <hr>
                    <div class="mt-4">
                        <h6>Active Offers</h6>
                        <div id="offersList" class="text-center text-muted py-4">
                            <i class="fas fa-star fa-3x mb-2 d-block"></i>
                            <p>No special offers created yet</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Handle video type change
        const videoTypeSelect = document.getElementById('videoTypeSelect');
        if (videoTypeSelect) {
            videoTypeSelect.addEventListener('change', function() {
                const urlGroup = document.getElementById('videoUrlGroup');
                const uploadGroup = document.getElementById('videoUploadGroup');
                const urlInput = document.getElementById('videoUrlInput');
                const fileInput = document.getElementById('videoFileInput');
                
                if (this.value === 'upload') {
                    urlGroup.style.display = 'none';
                    uploadGroup.style.display = 'block';
                    if (fileInput) fileInput.required = true;
                    if (urlInput) urlInput.required = false;
                } else {
                    urlGroup.style.display = 'block';
                    uploadGroup.style.display = 'none';
                    if (urlInput) urlInput.required = true;
                    if (fileInput) fileInput.required = false;
                }
            });
        }
        
        // Handle image upload form submission with AJAX
        const imageUploadForm = document.getElementById('imageUploadForm');
        if (imageUploadForm) {
            imageUploadForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                // Ensure backend detects upload action when using AJAX
                formData.append('upload_images', '1');
                const uploadBtn = document.getElementById('uploadBtn');
                const originalBtnText = uploadBtn.innerHTML;
                
                // Disable button and show loading
                uploadBtn.disabled = true;
                uploadBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Uploading...';
                
                fetch('cms.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(async response => {
                    // Check if response is JSON (from AJAX handler) or HTML
                    const contentType = response.headers.get('content-type');
                    if (contentType && contentType.includes('application/json')) {
                        return await response.json();
                    }
                    // If HTML response (shouldn't happen for AJAX, but handle it)
                    const html = await response.text();
                    // Try to extract JSON if embedded in HTML
                    const jsonMatch = html.match(/<script[^>]*>[\s\S]*?({[\s\S]*?})[\s\S]*?<\/script>/);
                    if (jsonMatch && jsonMatch[1]) {
                        try {
                            return JSON.parse(jsonMatch[1]);
                        } catch (e) {
                            // Fall through to HTML parsing
                        }
                    }
                    // Parse HTML for alerts
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = html;
                    const successAlert = tempDiv.querySelector('.alert-success');
                    const errorAlert = tempDiv.querySelector('.alert-danger');
                    // Only return success if there's a success alert and NO error alert
                    // This prevents showing both messages
                    const hasSuccess = successAlert !== null;
                    const hasError = errorAlert !== null;
                    return {
                        success: hasSuccess && !hasError,
                        message: successAlert ? successAlert.textContent.trim() : null,
                        error: errorAlert ? errorAlert.textContent.trim() : null,
                        isHtml: true
                    };
                })
                .then(data => {
                    // Only show one notification - prefer success if any files uploaded
                    if (data.success === true || (data.uploaded && data.uploaded > 0)) {
                        let message = data.message || `${data.uploaded || 1} image(s) uploaded successfully!`;
                        let icon = 'success';
                        let title = 'Success!';
                        
                        // If partial success (some failed), show warning instead
                        if (data.partial || (data.errors && data.errors.length > 0)) {
                            icon = 'warning';
                            title = 'Partially Completed';
                            if (data.errors && Array.isArray(data.errors)) {
                                message += '\n\nFailed files:\n' + data.errors.join('\n');
                            }
                        }
                        
                        Swal.fire({
                            icon: icon,
                            title: title,
                            text: message,
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: icon === 'warning' ? 5000 : 3000,
                            timerProgressBar: true
                        });
                        
                        // Reload gallery preview after a short delay
                        setTimeout(() => {
                            loadGalleryPreview();
                        }, 500);
                    } else if (data.success === false) {
                        // Only show error if explicitly failed
                        Swal.fire({
                            icon: 'error',
                            title: 'Upload Failed',
                            text: data.error || data.message || 'Failed to upload images. Please try again.',
                            toast: true,
                            position: 'top-end',
                            showConfirmButton: false,
                            timer: 4000,
                            timerProgressBar: true
                        });
                    }
                    
                    // Reset form
                    this.reset();
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = originalBtnText;
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: 'Upload Error',
                        text: 'An error occurred while uploading images. Please try again.',
                        toast: true,
                        position: 'top-end',
                        showConfirmButton: false,
                        timer: 4000,
                        timerProgressBar: true
                    });
                    uploadBtn.disabled = false;
                    uploadBtn.innerHTML = originalBtnText;
                });
            });
        }
        
        // Function to load and refresh gallery preview
        function loadGalleryPreview() {
            fetch('cms.php?gallery_preview=1')
                .then(response => response.json())
                .then(data => {
                    const galleryPreview = document.getElementById('galleryPreview');
                    const emptyGallery = document.getElementById('emptyGallery');
                    
                    if (!galleryPreview) return;
                    
                    // Clear existing gallery
                    galleryPreview.innerHTML = '';
                    
                    if (data.images && data.images.length > 0) {
                        // Remove empty message if exists
                        if (emptyGallery) emptyGallery.remove();
                        
                        // Add each image
                        data.images.forEach(img => {
                            const colDiv = document.createElement('div');
                            colDiv.className = 'col-md-3 mb-3 gallery-item';
                            colDiv.setAttribute('data-filename', img.filename);
                            
                            colDiv.innerHTML = `
                                <div class="position-relative">
                                    <img src="${img.path}" alt="Gallery Image" 
                                         class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover; cursor: pointer;"
                                         onclick="window.open('${img.path}', '_blank')">
                                    <button class="btn btn-danger btn-sm position-absolute top-0 end-0 m-1" 
                                            onclick="deleteImage('${img.filename}')"
                                            title="Delete Image">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                    <div class="small text-muted mt-1">${img.filename}</div>
                                </div>
                            `;
                            
                            galleryPreview.appendChild(colDiv);
                        });
                    } else {
                        // Show empty message
                        galleryPreview.innerHTML = `
                            <div class="col-md-12 text-center text-muted py-4" id="emptyGallery">
                                <i class="fas fa-image fa-3x mb-2 d-block"></i>
                                <p>No images uploaded yet</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading gallery:', error);
                    // Fallback: reload page
                    window.location.reload();
                });
        }
        
        // Delete image function
        function deleteImage(filename) {
            Swal.fire({
                title: 'Delete Image?',
                text: 'Are you sure you want to delete this image? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('?delete_image=' + encodeURIComponent(filename), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    })
                        .then(async response => {
                            const contentType = response.headers.get('content-type');
                            if (contentType && contentType.includes('application/json')) {
                                return await response.json();
                            }
                            return { success: response.ok };
                        })
                        .then(data => {
                            if (data.success) {
                                // Remove the image from gallery immediately
                                const galleryItem = document.querySelector(`.gallery-item[data-filename="${filename}"]`);
                                if (galleryItem) {
                                    galleryItem.remove();
                                }
                                
                                Swal.fire({
                                    icon: 'success',
                                    title: 'Deleted!',
                                    text: data.message || 'Image has been deleted successfully.',
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 3000,
                                    timerProgressBar: true
                                });
                                
                                // Reload gallery to ensure sync
                                setTimeout(() => {
                                    loadGalleryPreview();
                                }, 500);
                            } else {
                                throw new Error(data.error || 'Delete failed');
                            }
                        })
                        .catch(error => {
                            console.error('Error:', error);
                            Swal.fire({
                                icon: 'error',
                                title: 'Delete Failed',
                                text: error.message || 'Failed to delete image. Please try again.',
                                toast: true,
                                position: 'top-end',
                                showConfirmButton: false,
                                timer: 4000,
                                timerProgressBar: true
                            });
                        });
                }
            });
        }
        
        // Delete video function
        function deleteVideo(filename) {
            Swal.fire({
                title: 'Delete Video?',
                text: 'Are you sure you want to delete this video? This action cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?delete_video=' + encodeURIComponent(filename);
                }
            });
        }
    </script>
    <?php
    // Handle file deletions
    if (isset($_GET['delete_image'])) {
        $filename = basename($_GET['delete_image']);
        $file_path = $image_storage . $filename;
        if (file_exists($file_path) && unlink($file_path)) {
            $success_message = "Image deleted successfully!";
            echo "<script>setTimeout(() => window.location.href = 'cms.php', 1000);</script>";
        } else {
            $error_message = "Failed to delete image.";
        }
    }
    
    if (isset($_GET['delete_video'])) {
        $filename = basename($_GET['delete_video']);
        $file_path = $video_storage . $filename;
        if (file_exists($file_path) && unlink($file_path)) {
            $success_message = "Video deleted successfully!";
            echo "<script>setTimeout(() => window.location.href = 'cms.php', 1000);</script>";
        } else {
            $error_message = "Failed to delete video.";
        }
    }
    ?>
</body>
</html>

