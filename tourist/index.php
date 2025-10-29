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


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HalalGuide - Your Trusted Muslim Travel Companion</title>
    <meta name="description" content="Discover halal-certified establishments, accommodations, tourist spots, and prayer facilities for Muslim travelers">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- AOS Animation -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../assets2/css/style.css">
    
    <style>
        /* User Dropdown Styles */
        .user-dropdown {
            position: relative;
            display: inline-block;
        }
        
        .user-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            background: linear-gradient(135deg, #2ECC71 0%, #27AE60 100%);
            color: white;
            border: none;
            border-radius: 25px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
        }
        
        .user-btn:hover {
            background: linear-gradient(135deg, #27AE60 0%, #229954 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(46, 204, 113, 0.4);
        }
        
        .user-btn i:first-child {
            font-size: 18px;
        }
        
        .user-btn i:last-child {
            font-size: 12px;
            transition: transform 0.3s ease;
        }
        
        .user-btn.active i:last-child {
            transform: rotate(180deg);
        }
        
        .dropdown-menu {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
            min-width: 200px;
            opacity: 0;
            visibility: hidden;
            transform: translateY(-10px);
            transition: all 0.3s ease;
            z-index: 1000;
            border: 1px solid #e0e0e0;
        }
        
        .dropdown-menu.show {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }
        
        .dropdown-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 16px;
            color: #333;
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .dropdown-item:last-child {
            border-bottom: none;
        }
        
        .dropdown-item:hover {
            background: #f8f9fa;
            color: #2ECC71;
        }
        
        .dropdown-item.logout:hover {
            background: #ffe6e6;
            color: #dc3545;
        }
        
        .dropdown-item i {
            width: 16px;
            text-align: center;
        }
        
        .dropdown-divider {
            height: 1px;
            background: #e0e0e0;
            margin: 8px 0;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .user-btn span {
                display: none;
            }
            
            .user-btn {
                padding: 10px;
                border-radius: 50%;
                width: 40px;
                height: 40px;
                justify-content: center;
            }
            
            .dropdown-menu {
                right: -10px;
                min-width: 180px;
            }
        }
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
                        <li><a href="#home" class="nav-link active">Home</a></li>
                        <li><a href="#services" class="nav-link">Services</a></li>
                        <li><a href="#about" class="nav-link">About</a></li>
                        <li><a href="#testimonials" class="nav-link">Reviews</a></li>
                        <li><a href="#contact" class="nav-link">Contact</a></li>
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
                                <a href="?logout=1" class="dropdown-item logout">
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
                        <a href="#" class="btn-service">View Establishments</a>
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
                        <a href="#" class="btn-service">Find Hotels</a>
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
                        <a href="#" class="btn-service">Discover Places</a>
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
                        <a href="#" class="btn-service">Find Prayer Rooms</a>
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
                    <img src="../assets2/images/about.jpg" alt="About HalalGuide">
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
                        <span class="stat-number" data-count="500">0</span>
                        <span class="stat-label">Halal Restaurants</span>
                    </div>
                </div>
                
                <div class="stat-item" data-aos="fade-up" data-aos-delay="200">
                    <div class="stat-icon">
                        <i class="fas fa-hotel"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number" data-count="200">0</span>
                        <span class="stat-label">Hotels & Resorts</span>
                    </div>
                </div>
                
                <div class="stat-item" data-aos="fade-up" data-aos-delay="300">
                    <div class="stat-icon">
                        <i class="fas fa-map-marked-alt"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number" data-count="150">0</span>
                        <span class="stat-label">Tourist Destinations</span>
                    </div>
                </div>
                
                <div class="stat-item" data-aos="fade-up" data-aos-delay="400">
                    <div class="stat-icon">
                        <i class="fas fa-mosque"></i>
                    </div>
                    <div class="stat-content">
                        <span class="stat-number" data-count="300">0</span>
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
            </div>
            
            <div class="testimonials-slider">
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="100">
                    <div class="testimonial-header">
                        <img src="../assets2/images/person_1.jpg" alt="User" class="testimonial-avatar">
                        <div class="testimonial-info">
                            <h4 class="testimonial-name">Fatima Ahmed</h4>
                            <div class="testimonial-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="testimonial-text">"HalalGuide made our family vacation stress-free! We easily found halal restaurants and prayer facilities everywhere we went. Highly recommended!"</p>
                    <div class="testimonial-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Dubai, UAE</span>
                    </div>
                </div>
                
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="200">
                    <div class="testimonial-header">
                        <img src="../assets2/images/person_2.jpg" alt="User" class="testimonial-avatar">
                        <div class="testimonial-info">
                            <h4 class="testimonial-name">Mohammed Hassan</h4>
                            <div class="testimonial-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="testimonial-text">"As a frequent traveler, HalalGuide has become my go-to app. The verification system gives me confidence that all establishments are truly halal-certified."</p>
                    <div class="testimonial-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>London, UK</span>
                    </div>
                </div>
                
                <div class="testimonial-card" data-aos="fade-up" data-aos-delay="300">
                    <div class="testimonial-header">
                        <img src="../assets2/images/person_3.jpg" alt="User" class="testimonial-avatar">
                        <div class="testimonial-info">
                            <h4 class="testimonial-name">Aisha Rahman</h4>
                            <div class="testimonial-rating">
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                                <i class="fas fa-star"></i>
                            </div>
                        </div>
                    </div>
                    <p class="testimonial-text">"The prayer facility locator is a game-changer! I never have to worry about finding a place to pray during my travels anymore. JazakAllah khair!"</p>
                    <div class="testimonial-location">
                        <i class="fas fa-map-marker-alt"></i>
                        <span>Kuala Lumpur, Malaysia</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <div class="container">
            <div class="cta-content" data-aos="zoom-in">
                <h2 class="cta-title">Ready to Start Your Halal Journey?</h2>
                <p class="cta-text">Join thousands of Muslim travelers who trust HalalGuide for their travel needs</p>
                <div class="cta-buttons">
                    <a href="#" class="btn-cta-primary">Get Started Free</a>
                    <a href="#contact" class="btn-cta-secondary">Contact Us</a>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="contact" id="contact">
        <div class="container">
            <div class="section-header" data-aos="fade-up">
                <span class="section-badge">Contact Us</span>
                <h2 class="section-title">Get In Touch</h2>
                <p class="section-subtitle">Have questions? We'd love to hear from you</p>
            </div>
            
            <div class="contact-wrapper">
                <div class="contact-info" data-aos="fade-right">
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Address</h4>
                            <p>123 Halal Street, Islamic Center<br>City, Country 12345</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Phone</h4>
                            <p>+1 234 567 8900<br>+1 234 567 8901</p>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="contact-details">
                            <h4>Email</h4>
                            <p>info@halalguide.com<br>support@halalguide.com</p>
                        </div>
                    </div>
                    
                    <div class="social-links">
                        <a href="#" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <form class="contact-form" data-aos="fade-left">
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Your Name" required>
                    </div>
                    <div class="form-group">
                        <input type="email" class="form-control" placeholder="Your Email" required>
                    </div>
                    <div class="form-group">
                        <input type="text" class="form-control" placeholder="Subject" required>
                    </div>
                    <div class="form-group">
                        <textarea class="form-control" rows="5" placeholder="Your Message" required></textarea>
                    </div>
                    <button type="submit" class="btn-submit">Send Message</button>
                </form>
            </div>
        </div>
    </section>

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
                        <li><a href="#contact">Contact</a></li>
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
    
    <!-- Custom JS -->
    <script src="../assets2/js/main.js"></script>
    
    <script>
        // User Dropdown Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const userBtn = document.getElementById('userBtn');
            const dropdownMenu = document.getElementById('dropdownMenu');
            
            // Toggle dropdown
            userBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                dropdownMenu.classList.toggle('show');
                userBtn.classList.toggle('active');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(e) {
                if (!userBtn.contains(e.target) && !dropdownMenu.contains(e.target)) {
                    dropdownMenu.classList.remove('show');
                    userBtn.classList.remove('active');
                }
            });
            
            // Close dropdown when clicking on dropdown items
            const dropdownItems = document.querySelectorAll('.dropdown-item');
            dropdownItems.forEach(item => {
                item.addEventListener('click', function() {
                    dropdownMenu.classList.remove('show');
                    userBtn.classList.remove('active');
                });
            });
        });
    </script>
</body>
</html>

