<?php
include '../common/session.php';
include '../common/connection.php';

// Check login and access
check_login();
check_access('Tourist');

// Logout handler
if (isset($_GET['logout'])) {
    logout();
}


$useraccount_id = $_SESSION['user_id'];

$company_user_query = mysqli_query($conn, 
"SELECT cu.*, ua.* FROM tbl_useraccount ua 
LEFT JOIN tbl_tourist cu ON ua.tourist_id = cu.tourist_id 
WHERE ua.useraccount_id = '$useraccount_id'; ");
$company_user_row = mysqli_fetch_assoc($company_user_query);


$stats = [
    'establishments' => 0,
    'hotels' => 0,
    'spots' => 0,
    'prayers' => 0
];

// Aggregate live counts for each service
$count_query = mysqli_query($conn, "SELECT usertype_id, COUNT(*) as total FROM tbl_company WHERE usertype_id IN (3,4,5,6) AND status_id IN (1,4) GROUP BY usertype_id");
if ($count_query) {
    while ($row = mysqli_fetch_assoc($count_query)) {
        if ($row['usertype_id'] == 3) $stats['establishments'] = (int)$row['total'];
        if ($row['usertype_id'] == 4) $stats['hotels'] = (int)$row['total'];
        if ($row['usertype_id'] == 5) $stats['spots'] = (int)$row['total'];
        if ($row['usertype_id'] == 6) $stats['prayers'] = (int)$row['total'];
    }
}

// (Removed) featured listings test block

// Helper: find the first uploaded image for a given company
function find_company_image($companyId) {
    $dir = '../uploads/company/images/' . $companyId . '/';
    if (is_dir($dir)) {
        $files = scandir($dir);
        foreach ($files as $f) {
            if ($f !== '.' && $f !== '..' && preg_match('/\.(jpg|jpeg|png|gif|jfif)$/i', $f)) {
                return $dir . $f;
            }
        }
    }
    return null;
}

// About image: force a static, bundled halal-themed image (no DB lookup)
$about_image = '../assets2/images/about-1.jpg';

// Testimonial avatars: collect up to three real images from uploads
$testimonial_images = [];
$qT = mysqli_query($conn, "SELECT company_id FROM tbl_company WHERE usertype_id IN (3,4,5,6) AND status_id IN (1,4) ORDER BY date_added DESC LIMIT 50");
if ($qT) {
    while ($row = mysqli_fetch_assoc($qT)) {
        $img = find_company_image($row['company_id']);
        if ($img) { $testimonial_images[] = $img; }
        if (count($testimonial_images) >= 3) break;
    }
}

// Fetch latest approved feedbacks for testimonials
$feedbacks = [];
$tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_feedback'");
if ($tbl_check && mysqli_num_rows($tbl_check) > 0) {
    $fbq = mysqli_query($conn, "SELECT feedback_id, display_name, rating, comment, created_at FROM tbl_feedback WHERE is_approved = 1 ORDER BY created_at DESC LIMIT 9");
    if ($fbq) { while ($r = mysqli_fetch_assoc($fbq)) { $feedbacks[] = $r; } }
}

// Use a neutral random-people SVG avatar instead of profile images
$avatar_svg = 'data:image/svg+xml;utf8,' . rawurlencode('<svg width="60" height="60" viewBox="0 0 60 60" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0%" stop-color="#22c55e"/><stop offset="100%" stop-color="#16a34a"/></linearGradient></defs><circle cx="30" cy="30" r="28" fill="url(#g)"/><g fill="#fff" opacity="0.95"><circle cx="30" cy="25" r="9"/><path d="M30 36c-11 0-18 6-18 12 0 1.1.9 2 2 2h32c1.1 0 2-.9 2-2 0-6-7-12-18-12z"/></g></svg>');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HalalGuide - Your Trusted Muslim Travel Companion</title>
    <meta name="description" content="Discover halal-certified establishments, accommodations, tourist spots, and prayer facilities for Muslim travelers">
    <link rel="icon" type="image/png" href="../assets2/images/ph_halal_logo.png">
    <link rel="apple-touch-icon" href="../assets2/images/ph_halal_logo.png">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <!-- SweetAlert2 (toasts) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets2/css/style.css">
    <link rel="stylesheet" href="css/tourist-common.css">
    <style>
        /* Feedback Modal UI */
        .feedback-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.45); backdrop-filter: blur(2px); display: none; align-items: center; justify-content: center; z-index: 2000; }
        .feedback-overlay.show { display: flex; }
        .feedback-card { width: 92%; max-width: 560px; background: #fff; border-radius: 16px; box-shadow: 0 20px 60px rgba(0,0,0,0.25); overflow: hidden; }
        .feedback-header { padding: 18px 20px; background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%); color: #fff; display: flex; align-items: center; justify-content: space-between; }
        .feedback-title { margin: 0; font-size: 18px; font-weight: 700; }
        .feedback-close { background: rgba(255,255,255,0.2); border: 0; color: #fff; width: 34px; height: 34px; border-radius: 10px; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .feedback-body { padding: 18px 20px; }
        .feedback-row { margin-bottom: 14px; }
        .feedback-label { display: block; font-weight: 600; margin-bottom: 6px; color: #374151; }
        .feedback-static { padding: 10px; border: 1px solid #e5e7eb; border-radius: 10px; background: #f9fafb; }
        .feedback-input, .feedback-textarea { width: 100%; padding: 12px; border: 1px solid #e5e7eb; border-radius: 10px; outline: none; transition: box-shadow .2s, border-color .2s; }
        .feedback-input:focus, .feedback-textarea:focus { border-color: #22c55e; box-shadow: 0 0 0 4px rgba(34,197,94,.15); }
        .rating-stars i { font-size: 22px; color: #f59e0b; cursor: pointer; transition: transform .1s; }
        .rating-stars i:hover { transform: scale(1.1); }
        .feedback-footer { display: flex; justify-content: flex-end; gap: 10px; padding: 0 20px 20px; }
        .btn-outline { padding: 10px 16px; border-radius: 10px; border: 2px solid #a7f3d0; background: #f0fdf4; color: #15803d; font-weight: 600; cursor: pointer; }
        .btn-outline:hover { background: #dcfce7; }
        .btn-gradient { padding: 10px 18px; border-radius: 10px; border: 0; background: linear-gradient(135deg,#22c55e 0%, #16a34a 100%); color: #fff; font-weight: 700; cursor: pointer; box-shadow: 0 4px 14px rgba(34,197,94,.35); }
        .btn-gradient:hover { filter: brightness(1.05); }
        .char-hint { font-size: 12px; color: #6b7280; text-align: right; margin-top: 4px; }
    </style>
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
                        <li><a href="#home" class="nav-link">Home</a></li>
                        <li><a href="#services" class="nav-link">Services</a></li>
                        <li><a href="../pages/map/map.html?return=tourist" class="nav-link">Halal Certified Near Me</a></li>
                        <li><a href="#about" class="nav-link">About</a></li>
                    </ul>
                    <div class="nav-buttons">
                        <div class="user-dropdown">
                            <button class="user-btn" id="userBtn">
                                <i class="fas fa-user-circle"></i>
                                <span><?php echo htmlspecialchars($company_user_row['firstname'] . ' ' . $company_user_row['lastname']); ?></span>
                                <i class="fas fa-chevron-down"></i>
                            </button>
                            <div class="dropdown-menu" id="dropdownMenu">
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-user"></i>
                                    <span>Profile</span>
                                </a>
                                <a href="#" class="dropdown-item">
                                    <i class="fas fa-cog"></i>
                                    <span>Settings</span>
                                </a>
                                <div class="dropdown-divider"></div>
                                <a href="../home.php" class="dropdown-item logout">
                                    <i class="fas fa-sign-out-alt"></i>
                                    <span>Logout</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="hamburger" id="hamburger">
                    <span></span>
                    <span></span>
                    <span></span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero" id="home">
        <div class="hero-slider">
            <div class="hero-slide active" style="background-image: url('../assets2/images/bg_1.jpg');">
                <div class="hero-overlay"></div>
            </div>
            <div class="hero-slide" style="background-image: url('../assets2/images/bg_2.jpg');">
                <div class="hero-overlay"></div>
            </div>
            <div class="hero-slide" style="background-image: url('../assets2/images/bg_3.jpg');">
                <div class="hero-overlay"></div>
            </div>
        </div>
        
        <div class="container">
            <div class="hero-content" data-aos="fade-up">
                <h1 class="hero-title">Discover Halal-Friendly Destinations</h1>
                <p class="hero-subtitle">Your trusted companion for Muslim-friendly travel experiences worldwide</p>
                <div class="hero-buttons">
                    <a href="#services" class="btn-hero-primary">Explore Now</a>
                    <a href="#about" class="btn-hero-secondary">Learn More</a>
                </div>
                
                <!-- Quick Search -->
                <div class="quick-search" data-aos="fade-up" data-aos-delay="200">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="Search for destinations, restaurants, hotels...">
                        <button class="btn-search">Search</button>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Slider Controls -->
        <div class="slider-controls">
            <button class="slider-btn prev-btn" id="prevBtn"><i class="fas fa-chevron-left"></i></button>
            <button class="slider-btn next-btn" id="nextBtn"><i class="fas fa-chevron-right"></i></button>
        </div>
        
        <!-- Slider Indicators -->
        <div class="slider-indicators">
            <span class="indicator active" data-slide="0"></span>
            <span class="indicator" data-slide="1"></span>
            <span class="indicator" data-slide="2"></span>
        </div>
    </section>

    

    <!-- Services Section -->
    <section class="services" id="services">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-badge">Our Services</span>
                <h2 class="section-title">Explore Muslim-Friendly Services</h2>
                <p class="section-subtitle">Everything you need for a comfortable and halal-compliant journey</p>
            </div>
            
            <div class="services-grid">
                <!-- Halal Establishments -->
                <div class="service-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="service-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="service-content">
                        <h3 class="service-title">Halal Establishments</h3>
                        <p class="service-description">Discover certified halal restaurants, cafes, and food establishments trusted by the Muslim community.</p>
                        <ul class="service-features">
                            <li><i class="fas fa-check"></i> Verified Halal Certification</li>
                            <li><i class="fas fa-check"></i> Authentic Reviews</li>
                            <li><i class="fas fa-check"></i> Menu Details</li>
                        </ul>
                        <a href="establishments.php" class="btn-service">View Establishments (<?php echo $stats['establishments']; ?>)</a>
                    </div>
                </div>
                
                <!-- Accommodation -->
                <div class="service-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="service-icon">
                        <i class="fas fa-hotel"></i>
                    </div>
                    <div class="service-content">
                        <h3 class="service-title">Muslim-Friendly Accommodation</h3>
                        <p class="service-description">Find hotels and accommodations that cater to Muslim travelers with prayer facilities and halal food.</p>
                        <ul class="service-features">
                            <li><i class="fas fa-check"></i> Prayer Rooms Available</li>
                            <li><i class="fas fa-check"></i> Halal Food Options</li>
                            <li><i class="fas fa-check"></i> Qibla Direction</li>
                        </ul>
                        <a href="hotels.php" class="btn-service">Find Hotels (<?php echo $stats['hotels']; ?>)</a>
                    </div>
                </div>
                
                <!-- Tourist Spots -->
                <div class="service-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="service-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="service-content">
                        <h3 class="service-title">Tourist Spots</h3>
                        <p class="service-description">Explore breathtaking destinations and attractions that respect Islamic values and traditions.</p>
                        <ul class="service-features">
                            <li><i class="fas fa-check"></i> Family-Friendly Locations</li>
                            <li><i class="fas fa-check"></i> Cultural Experiences</li>
                            <li><i class="fas fa-check"></i> Guided Tours Available</li>
                        </ul>
                        <a href="tourist-spots.php" class="btn-service">Discover Places (<?php echo $stats['spots']; ?>)</a>
                    </div>
                </div>
                
                <!-- Prayer Facilities -->
                <div class="service-card" data-aos="fade-up" data-aos-delay="400">
                    <div class="service-icon">
                        <i class="fas fa-mosque"></i>
                    </div>
                    <div class="service-content">
                        <h3 class="service-title">Prayer Facilities</h3>
                        <p class="service-description">Locate nearby mosques and prayer rooms wherever you travel, ensuring you never miss your prayers.</p>
                        <ul class="service-features">
                            <li><i class="fas fa-check"></i> Prayer Time Notifications</li>
                            <li><i class="fas fa-check"></i> Nearby Mosques</li>
                            <li><i class="fas fa-check"></i> Prayer Room Locations</li>
                        </ul>
                        <a href="prayer-facilities.php" class="btn-service">Find Prayer Rooms (<?php echo $stats['prayers']; ?>)</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" id="about">
        <div class="container">
            <div class="about-wrapper">
                <div class="about-image" data-aos="fade-right">
                    <img src="../assets2/images/about-1.jpg" alt="About HalalGuide" loading="lazy" decoding="async" style="width:100%;height:auto;border-radius:12px;object-fit:cover;">
                    <div class="about-badge">
                        <div class="badge-content">
                            <span class="badge-number">1000+</span>
                            <span class="badge-text">Verified Locations</span>
                        </div>
                    </div>
                </div>
                
                <div class="about-content" data-aos="fade-left">
                    <span class="section-badge">About Us</span>
                    <h2 class="section-title">Your Trusted Muslim Travel Companion</h2>
                    <p class="about-text">HalalGuide is dedicated to making travel easier and more comfortable for Muslim tourists worldwide. We provide comprehensive information about halal-certified establishments, Muslim-friendly accommodations, and prayer facilities.</p>
                    
                    <div class="about-features">
                        <div class="about-feature">
                            <div class="feature-icon">
                                <i class="fas fa-certificate"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Verified & Certified</h4>
                                <p>All listings are thoroughly verified and halal-certified by recognized authorities.</p>
                            </div>
                        </div>
                        
                        <div class="about-feature">
                            <div class="feature-icon">
                                <i class="fas fa-users"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Community Driven</h4>
                                <p>Real reviews and ratings from the Muslim community you can trust.</p>
                            </div>
                        </div>
                        
                        <div class="about-feature">
                            <div class="feature-icon">
                                <i class="fas fa-globe"></i>
                            </div>
                            <div class="feature-content">
                                <h4>Global Coverage</h4>
                                <p>Extensive database covering destinations around the world.</p>
                            </div>
                        </div>
                    </div>
                    
                    <a href="#services" class="btn-primary">Explore Services</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="stats">
        <div class="container">
            <div class="stats-grid">
                <div class="stat-item" data-aos="fade-up" data-aos-delay="100">
                    <div class="stat-icon">
                        <i class="fas fa-utensils"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number" data-count="<?php echo (int)$stats['establishments']; ?>"><?php echo (int)$stats['establishments']; ?></span>
                        <span class="stat-label">Halal Restaurants</span>
                    </div>
                </div>
                
                <div class="stat-item" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-icon">
                        <i class="fas fa-hotel"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number" data-count="<?php echo (int)$stats['hotels']; ?>"><?php echo (int)$stats['hotels']; ?></span>
                        <span class="stat-label">Hotels & Resorts</span>
                    </div>
                </div>
                
                <div class="stat-item" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number" data-count="<?php echo (int)$stats['spots']; ?>"><?php echo (int)$stats['spots']; ?></span>
                        <span class="stat-label">Tourist Destinations</span>
                    </div>
                </div>
                
                <div class="stat-item" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-icon">
                        <i class="fas fa-mosque"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number" data-count="<?php echo (int)$stats['prayers']; ?>"><?php echo (int)$stats['prayers']; ?></span>
                        <span class="stat-label">Prayer Facilities</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials" id="testimonials">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-badge">Testimonials</span>
                <h2 class="section-title">What Our Users Say</h2>
                <p class="section-subtitle">Real experiences from Muslim travelers around the world</p>
                <div style="margin-top:12px;">
                    <button id="openFeedbackBtn" class="btn-cta-primary">Share Your Experience</button>
                </div>
            </div>
            
            <div class="testimonials-slider">
                <?php if (!empty($feedbacks)): $delay=100; foreach ($feedbacks as $fb): ?>
                    <div class="testimonial-card" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                        <div class="testimonial-header">
                            <img src="<?php echo $avatar_svg; ?>" alt="User" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h4 class="testimonial-name"><?php echo htmlspecialchars($fb['display_name'] ?: 'Tourist User'); ?></h4>
                                <div class="testimonial-rating">
                                    <?php for($i=0;$i<5;$i++): ?>
                                        <i class="fas fa-star" style="<?php echo $i < (int)$fb['rating'] ? '' : 'opacity:0.2'; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                            </div>
                        </div>
                        <p class="testimonial-text">"<?php echo htmlspecialchars($fb['comment']); ?>"</p>
                        <div class="testimonial-location">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('M j, Y', strtotime($fb['created_at'])); ?></span>
                        </div>
                    </div>
                <?php $delay+=100; endforeach; else: ?>
                    <div class="testimonial-card" data-aos="fade-up" data-aos-delay="100">
                        <div class="testimonial-header">
                            <img src="<?php echo $avatar_svg; ?>" alt="User" class="testimonial-avatar">
                            <div class="testimonial-info">
                                <h4 class="testimonial-name">Welcome!</h4>
                                <div class="testimonial-rating">
                                    <i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i><i class="fas fa-star"></i>
                                </div>
                            </div>
                        </div>
                        <p class="testimonial-text">Be the first to share your experience with HalalGuide.</p>
                        <div class="testimonial-location">
                            <i class="fas fa-info-circle"></i>
                            <span>Your feedback appears here after submission.</span>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Feedback Modal -->
    <div id="feedbackModal" class="feedback-overlay">
        <div class="feedback-card">
            <div class="feedback-header">
                <h3 class="feedback-title"><i class="fas fa-comment-dots"></i> Share Your Experience</h3>
                <button type="button" id="closeFeedbackBtn" class="feedback-close"><i class="fas fa-times"></i></button>
            </div>
            <div class="feedback-body">
                <p style="margin:0 0 12px 0; color:#6b7280;">Your feedback helps other Muslim travelers.</p>
                <form id="feedbackForm">
                    <div class="feedback-row">
                        <label class="feedback-label">Posting as</label>
                        <div class="feedback-static"><?php echo htmlspecialchars(($company_user_row['firstname'] ?? '') . ' ' . ($company_user_row['lastname'] ?? '')); ?></div>
                    </div>
                    <div class="feedback-row">
                        <label class="feedback-label">Rating</label>
                        <div id="ratingStars" class="rating-stars">
                            <i data-v="1" class="fas fa-star"></i>
                            <i data-v="2" class="fas fa-star"></i>
                            <i data-v="3" class="fas fa-star"></i>
                            <i data-v="4" class="fas fa-star"></i>
                            <i data-v="5" class="fas fa-star"></i>
                        </div>
                        <input type="hidden" name="rating" id="ratingInput" value="5">
                    </div>
                    <div class="feedback-row">
                        <label class="feedback-label">Comment</label>
                        <textarea name="comment" rows="4" class="feedback-textarea" maxlength="500" required></textarea>
                        <div class="char-hint"><span id="charCount">0</span>/500</div>
                    </div>
                </form>
            </div>
            <div class="feedback-footer">
                <button type="button" id="cancelFeedbackBtn" class="btn-outline"><i class="fas fa-times"></i> Cancel</button>
                <button type="submit" form="feedbackForm" class="btn-gradient"><i class="fas fa-paper-plane"></i> Submit</button>
            </div>
        </div>
    </div>


    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-section" data-aos="fade-up" data-aos-delay="100">
                    <div class="footer-logo">
                        <i class="fas fa-mosque"></i>
                        <span>HalalGuide</span>
                    </div>
                    <p class="footer-text">Your trusted companion for Muslim-friendly travel experiences worldwide. Making halal travel easier and more accessible.</p>
                    <div class="footer-social">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-section" data-aos="fade-up" data-aos-delay="200">
                    <h4 class="footer-title">Quick Links</h4>
                    <ul class="footer-links">
                        <li><a href="#home">Home</a></li>
                        <li><a href="#services">Services</a></li>
                        <li><a href="#about">About Us</a></li>
                        <li><a href="#testimonials">Reviews</a></li>
                    </ul>
                </div>
                
                <div class="footer-section" data-aos="fade-up" data-aos-delay="300">
                    <h4 class="footer-title">Services</h4>
                    <ul class="footer-links">
                        <li><a href="#">Halal Establishments</a></li>
                        <li><a href="#">Accommodation</a></li>
                        <li><a href="#">Tourist Spots</a></li>
                        <li><a href="#">Prayer Facilities</a></li>
                        <li><a href="#">Travel Guide</a></li>
                    </ul>
                </div>
                
                <div class="footer-section" data-aos="fade-up" data-aos-delay="400">
                    <h4 class="footer-title">Newsletter</h4>
                    <p class="footer-text">Subscribe to get updates about new locations and travel tips.</p>
                    <form class="newsletter-form">
                        <input type="email" class="newsletter-input" placeholder="Your email" required>
                        <button type="submit" class="newsletter-btn">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2025 HalalGuide. All rights reserved.</p>
                <div class="footer-bottom-links">
                    <a href="#">Privacy Policy</a>
                    <a href="#">Terms of Service</a>
                    <a href="#">Cookie Policy</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scroll to Top Button -->
    <button class="scroll-top" id="scrollTop">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- AOS Animation JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Custom JS -->
    <script src="../assets2/js/main.js"></script>
    
    <script>
        // Immediately override alert for service-related messages (before page loads)
        const originalAlert = window.alert;
        let alertBlocked = false;
        
        // Override service button handlers - remove all click interceptors
        document.addEventListener('DOMContentLoaded', function() {
            // Remove all event listeners from service buttons
            setTimeout(function() {
                const serviceButtons = document.querySelectorAll('.btn-service');
                serviceButtons.forEach(button => {
                    // Clone to remove all event listeners attached by other scripts
                    const parent = button.parentNode;
                    const href = button.getAttribute('href'); // Preserve href
                    const classes = button.getAttribute('class'); // Preserve classes
                    const text = button.textContent; // Preserve text
                    
                    // Create new button that works as normal link
                    const newButton = document.createElement('a');
                    newButton.href = href;
                    newButton.className = classes;
                    newButton.textContent = text;
                    
                    parent.replaceChild(newButton, button);
                });
            }, 100);
        });
        
        // Also override on window load (after all scripts)
        window.addEventListener('load', function() {
            const serviceButtons = document.querySelectorAll('.btn-service');
            serviceButtons.forEach(button => {
                // Force normal link behavior
                button.setAttribute('data-no-intercept', 'true');
            });
        });
        
        document.addEventListener('DOMContentLoaded', function() {
            
            // User Dropdown Functionality
            const userBtn = document.getElementById('userBtn');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            // Toggle dropdown
            if (userBtn) {
                userBtn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    dropdownMenu.classList.toggle('show');
                    userBtn.classList.toggle('active');
                });
            }
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (userBtn && dropdownMenu) {
                    if (!userBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                        dropdownMenu.classList.remove('show');
                        userBtn.classList.remove('active');
                    }
                }
            });
            
            // Close dropdown when clicking on dropdown items
            const dropdownItems = document.querySelectorAll('.dropdown-item');
            dropdownItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (dropdownMenu) {
                        dropdownMenu.classList.remove('show');
                    }
                    if (userBtn) {
                        userBtn.classList.remove('active');
                    }
                });
            });
        });

        // Feedback modal + submit
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('feedbackModal');
            const openBtn = document.getElementById('openFeedbackBtn');
            const closeBtn = document.getElementById('closeFeedbackBtn');
            const cancelBtn = document.getElementById('cancelFeedbackBtn');
            const form = document.getElementById('feedbackForm');
            const stars = document.querySelectorAll('#ratingStars i');
            const ratingInput = document.getElementById('ratingInput');
            const textarea = form ? form.querySelector('textarea[name="comment"]') : null;
            const charCount = document.getElementById('charCount');

            if (openBtn) openBtn.addEventListener('click', () => { modal.classList.add('show'); });
            if (closeBtn) closeBtn.addEventListener('click', () => { modal.classList.remove('show'); });
            if (cancelBtn) cancelBtn.addEventListener('click', () => { modal.classList.remove('show'); });
            if (modal) modal.addEventListener('click', (e) => { if (e.target === modal) modal.classList.remove('show'); });

            stars.forEach(star => {
                star.addEventListener('click', () => {
                    const v = parseInt(star.getAttribute('data-v'));
                    ratingInput.value = v;
                    stars.forEach((s, i) => { s.style.opacity = (i < v) ? '1' : '0.3'; });
                });
                // Hover preview
                star.addEventListener('mouseenter', () => {
                    const v = parseInt(star.getAttribute('data-v'));
                    stars.forEach((s, i) => { s.style.opacity = (i < v) ? '1' : '0.3'; });
                });
                star.addEventListener('mouseleave', () => {
                    const v = parseInt(ratingInput.value || '0');
                    stars.forEach((s, i) => { s.style.opacity = (i < v) ? '1' : '0.3'; });
                });
            });

            if (textarea && charCount) {
                const updateCount = () => { charCount.textContent = String(textarea.value.length); };
                textarea.addEventListener('input', updateCount);
                updateCount();
            }

            if (form) {
                form.addEventListener('submit', async function(e) {
                    e.preventDefault();
                    const fd = new FormData(form);
                    const resp = await fetch('submit_feedback.php', { method: 'POST', body: fd });
                    const data = await resp.json().catch(() => ({ success:false, message:'Unexpected response' }));
                    if (data.success) {
                        modal.style.display = 'none';
                        Swal.fire({ icon: 'success', title: 'Thanks for your feedback!', toast: true, position: 'top-end', timer: 2500, showConfirmButton: false })
                            .then(() => window.location.reload());
                    } else {
                        Swal.fire({ icon: 'error', title: 'Unable to submit', text: data.message || 'Failed to submit feedback.' });
                    }
                });
            }
        });
    </script>
</body>
</html>

