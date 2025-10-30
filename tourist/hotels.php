<?php
include '../common/session.php';
include '../common/connection.php';

// Check login and access
check_login();
check_access('Tourist');

$useraccount_id = $_SESSION['user_id'];

$user_query = mysqli_query($conn, 
"SELECT cu.*, ua.* FROM tbl_useraccount ua 
LEFT JOIN tbl_tourist cu ON ua.tourist_id = cu.tourist_id 
WHERE ua.useraccount_id = '$useraccount_id'; ");
$user_row = mysqli_fetch_assoc($user_query);

// Get search parameter
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// Query hotels (usertype_id = 4)
$where_clause = "c.usertype_id = 4 AND c.status_id IN (1, 4)";
if (!empty($search)) {
    $search_escaped = mysqli_real_escape_string($conn, $search);
    $where_clause .= " AND (c.company_name LIKE '%$search_escaped%' OR c.company_description LIKE '%$search_escaped%')";
}

$query = "SELECT 
    c.*,
    a.other,
    b.brgyDesc,
    cm.citymunDesc,
    p.provDesc,
    r.regDesc,
    s.status,
    CASE 
        WHEN a.other IS NOT NULL AND b.brgyDesc IS NOT NULL AND cm.citymunDesc IS NOT NULL AND p.provDesc IS NOT NULL
        THEN CONCAT(COALESCE(a.other, ''), ', ', COALESCE(b.brgyDesc, ''), ', ', COALESCE(cm.citymunDesc, ''), ', ', COALESCE(p.provDesc, ''))
        ELSE COALESCE(a.other, 'Address not specified')
    END as full_address
FROM tbl_company c
LEFT JOIN tbl_status s ON c.status_id = s.status_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode
LEFT JOIN refregion r ON p.regCode = r.regCode
WHERE $where_clause
ORDER BY c.status_id DESC, c.date_added DESC";

$result = mysqli_query($conn, $query);
$hotels = [];
if ($result) {
    while($row = mysqli_fetch_assoc($result)) {
        if (empty($row['full_address'])) {
            $row['full_address'] = 'Address not available';
        }
        $hotels[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Muslim-Friendly Hotels - HalalGuide</title>
    <link rel="icon" type="image/png" href="../assets2/images/ph_halal_logo.png">
    <link rel="apple-touch-icon" href="../assets2/images/ph_halal_logo.png">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets2/css/style.css">
    <link rel="stylesheet" href="css/tourist-common.css">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar" id="navbar">
        <div class="container">
            <div class="nav-wrapper">
                <div class="logo">
                    <i class="fas fa-mosque"></i>
                    <span>HalalGuide</span>
                </div>
                
                <div class="nav-menu" id="navMenu">
                    <ul class="nav-links">
                        <li><a href="index.php" class="nav-link">Home</a></li>
                        <li><a href="establishments.php" class="nav-link">Establishments</a></li>
                        <li><a href="hotels.php" class="nav-link active">Hotels</a></li>
                        <li><a href="tourist-spots.php" class="nav-link">Tourist Spots</a></li>
                        <li><a href="prayer-facilities.php" class="nav-link">Prayer Facilities</a></li>
                    </ul>
                    <div class="nav-buttons">
                        <div class="user-dropdown">
                            <button class="user-btn" id="userBtn">
                                <i class="fas fa-user-circle"></i>
                                <span><?php echo htmlspecialchars($user_row['firstname'] . ' ' . $user_row['lastname']); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdownMenu">
                                <a href="index.php" class="dropdown-item">
                                    <i class="fas fa-home"></i>
                                    <span>Dashboard</span>
                                </a>
                                <a href="../home.php" class="dropdown-item logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Page Header -->
    <div class="page-header">
        <div class="container">
            <h1><i class="fas fa-hotel"></i> Muslim-Friendly Hotels</h1>
            <p>Find accommodations that cater to Muslim travelers with prayer facilities and halal food</p>
            
            <!-- Search Bar -->
            <form method="GET" action="" class="search-bar">
                <input type="text" name="search" placeholder="Search hotels..." value="<?php echo htmlspecialchars($search); ?>">
                <button type="submit"><i class="fas fa-search"></i> Search</button>
            </form>
        </div>
    </div>

    <!-- Results -->
    <div class="container">
        <?php if (count($hotels) > 0): ?>
            <div class="results-count">
                Found <?php echo count($hotels); ?> hotel(s)
            </div>
            
            <div class="establishments-grid">
                <?php foreach ($hotels as $hotel): ?>
                    <?php
                    // Get company images
                    $company_images = [];
                    $company_image_storage = '../uploads/company/images/' . $hotel['company_id'] . '/';
                    if (is_dir($company_image_storage)) {
                        $img_files = scandir($company_image_storage);
                        foreach ($img_files as $img_file) {
                            if ($img_file != '.' && $img_file != '..' && preg_match('/\.(jpg|jpeg|png|gif)$/i', $img_file)) {
                                $company_images[] = '../uploads/company/images/' . $hotel['company_id'] . '/' . $img_file;
                            }
                        }
                    }
                    $primary_image = !empty($company_images) ? $company_images[0] : null;
                    ?>
                    <div class="establishment-card" data-aos="fade-up">
                        <div class="establishment-image" style="<?php echo $primary_image ? 'background-image: url(' . htmlspecialchars($primary_image) . '); background-size: cover; background-position: center;' : ''; ?>">
                            <?php if (!$primary_image): ?>
                                <i class="fas fa-hotel"></i>
                            <?php endif; ?>
                        </div>
                        <div class="establishment-content">
                            <div class="establishment-header">
                                <h3 class="establishment-name"><?php echo htmlspecialchars($hotel['company_name']); ?></h3>
                                <div>
                                    <?php if ($hotel['status_id'] == 4): ?>
                                        <span class="halal-badge"><i class="fas fa-certificate"></i> Certified</span>
                                    <?php endif; ?>
                                    <?php if ($hotel['has_prayer_faci'] == 1): ?>
                                        <span class="prayer-badge"><i class="fas fa-mosque"></i> Prayer Room</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <p class="establishment-description">
                                <?php echo htmlspecialchars($hotel['company_description'] ?: 'No description available'); ?>
                            </p>
                            
                            <?php if (!empty($hotel['full_address'])): ?>
                                <div class="establishment-address">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <span><?php echo htmlspecialchars($hotel['full_address']); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="establishment-contact">
                                <?php if ($hotel['contant_no'] || $hotel['tel_no']): ?>
                                    <div class="contact-item">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($hotel['contant_no'] ?: $hotel['tel_no']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if ($hotel['email']): ?>
                                    <div class="contact-item">
                                        <i class="fas fa-envelope"></i>
                                        <span><?php echo htmlspecialchars($hotel['email']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="establishment-footer">
                                <a href="../pages/map/map.html?return=tourist&search=<?php echo urlencode($hotel['company_name']); ?>" class="btn-view-map">
                                    <i class="fas fa-map-marked-alt"></i> View on Map
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="no-results">
                <i class="fas fa-hotel"></i>
                <h3>No hotels found</h3>
                <p><?php echo !empty($search) ? 'Try a different search term or browse all hotels.' : 'No Muslim-friendly hotels are available at the moment. Check back soon or register your accommodation to get listed.'; ?></p>
                <?php if (empty($search)): ?>
                    <a href="../pages/map/map.html?return=tourist" class="btn-view-map">
                        <i class="fas fa-map-marked-alt"></i> Explore on Map
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-bottom">
                <p>&copy; 2025 HalalGuide. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init();
        
        // User Dropdown
        document.addEventListener('DOMContentLoaded', function() {
            const userBtn = document.getElementById('userBtn');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            userBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
                userBtn.classList.toggle('active');
            });
            
            document.addEventListener('click', function(e) {
                if (!userBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                    userBtn.classList.remove('active');
                }
            });
        });
        
        // Logout handler
        <?php if (isset($_GET['logout'])): ?>
            window.location.href = '../home.php?logout=1';
        <?php endif; ?>
    </script>
</body>
</html>

