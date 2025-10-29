# HalalGuide - Muslim Travel Companion Landing Page

A modern, responsive landing page for HalalGuide - your trusted companion for Muslim-friendly travel experiences worldwide.

## 🌟 Features

### Navigation Modules
- **Halal Establishments** - Discover certified halal restaurants and food establishments
- **Muslim-Friendly Accommodation** - Find hotels with prayer facilities and halal food
- **Tourist Spots** - Explore family-friendly destinations respecting Islamic values
- **Prayer Facilities** - Locate nearby mosques and prayer rooms

### Design Features
- ✨ Modern, clean, and responsive design
- 🎨 Beautiful gradient colors and smooth animations
- 📱 Mobile-friendly navigation with hamburger menu
- 🖼️ Automatic hero image slider
- 💫 Scroll animations using AOS library
- 📊 Animated statistics counters
- 💬 Testimonials section with user reviews
- 📧 Contact form and newsletter subscription
- 🔝 Scroll-to-top button
- 🎯 Smooth scrolling navigation

## 📁 File Structure

```
halalguide-initial/
├── index.html           # Main landing page
├── assets/
│   ├── css/
│   │   └── style.css   # Custom styles with animations
│   ├── js/
│   │   └── main.js     # Interactive features and functionality
│   └── images/         # All image assets
│       ├── bg_1.jpg    # Hero slider images
│       ├── bg_2.jpg
│       ├── bg_3.jpg
│       ├── hotel-resto-*.jpg
│       ├── destination-*.jpg
│       ├── person-*.jpg
│       └── ...
└── README.md
```

## 🚀 Getting Started

### Prerequisites
- Any modern web browser (Chrome, Firefox, Safari, Edge)
- Web server (XAMPP, WAMP, or similar) for local testing

### Installation

1. **Navigate to the project folder:**
   ```
   C:\xampp\htdocs\capstone\final\halalguide-initial\
   ```

2. **Start your web server (XAMPP/WAMP)**

3. **Open in browser:**
   ```
   http://localhost/capstone/final/halalguide-initial/
   ```

### External Dependencies (Loaded via CDN)

The landing page uses the following external libraries:
- **Google Fonts (Poppins)** - Modern, clean typography
- **Font Awesome 6.4.0** - Icons throughout the site
- **AOS (Animate On Scroll)** - Smooth scroll animations

All dependencies are loaded via CDN, so an internet connection is required.

## 🎨 Color Scheme

- **Primary Color:** `#2ecc71` (Green)
- **Secondary Color:** `#27ae60` (Dark Green)
- **Accent Color:** `#f39c12` (Orange)
- **Dark Color:** `#2c3e50` (Navy Blue)
- **Light Color:** `#ecf0f1` (Light Gray)

## 📱 Responsive Breakpoints

- **Desktop:** > 968px
- **Tablet:** 576px - 968px
- **Mobile:** < 576px

## ✨ Key Sections

### 1. Hero Section
- Auto-rotating image slider with 3 images
- Search functionality for quick access
- Call-to-action buttons
- Manual navigation controls and indicators

### 2. Services Section
- 4 main service cards with hover effects
- Icon-based visual representation
- Feature lists for each service
- Call-to-action buttons

### 3. About Section
- Information about HalalGuide
- Feature highlights with icons
- Statistics badge (1000+ verified locations)

### 4. Statistics Section
- Animated counters for:
  - 500+ Halal Restaurants
  - 200+ Hotels & Resorts
  - 150+ Tourist Destinations
  - 300+ Prayer Facilities

### 5. Testimonials Section
- Real user reviews
- 5-star rating display
- Location information

### 6. CTA (Call to Action) Section
- Encourages user sign-up
- Prominent action buttons

### 7. Contact Section
- Contact information display
- Contact form with validation
- Social media links

### 8. Footer
- Quick links navigation
- Services menu
- Newsletter subscription
- Social media integration
- Copyright information

## 🔧 Customization

### Changing Colors
Edit the CSS variables in `assets/css/style.css`:
```css
:root {
    --primary-color: #2ecc71;
    --secondary-color: #27ae60;
    --accent-color: #f39c12;
    /* ... more variables */
}
```

### Adding/Removing Slider Images
In `index.html`, find the hero-slider section and add/remove slides:
```html
<div class="hero-slide" style="background-image: url('assets/images/your-image.jpg');">
    <div class="hero-overlay"></div>
</div>
```

Don't forget to add corresponding indicators!

### Modifying Statistics
Update the `data-count` attribute in the stats section:
```html
<span class="stat-number" data-count="500">0</span>
```

## 🎯 Interactive Features

### JavaScript Functionality
- **Smooth Scrolling:** Click any navigation link for smooth scroll
- **Active Link Highlighting:** Auto-updates based on scroll position
- **Mobile Menu:** Hamburger menu for mobile devices
- **Hero Slider:** Auto-rotating with manual controls
- **Stats Counter:** Animates numbers when scrolled into view
- **Parallax Effect:** Hero content has subtle parallax on scroll
- **Form Validation:** Basic validation for contact and newsletter forms
- **Scroll to Top:** Button appears after scrolling 300px

### Keyboard Shortcuts
- **Escape:** Close mobile menu
- **Left/Right Arrows:** Navigate hero slider
- **Tab:** Navigate using keyboard (enhanced accessibility)

## 🌐 Browser Support

- ✅ Chrome (latest)
- ✅ Firefox (latest)
- ✅ Safari (latest)
- ✅ Edge (latest)
- ✅ Opera (latest)

## 📊 Performance Optimizations

- Debounced scroll event listeners
- Throttled resize handlers
- Lazy loading ready (IntersectionObserver)
- Optimized CSS animations
- Minimal dependencies

## 🔒 Security Notes

When implementing backend functionality:
- Validate all form inputs on the server side
- Sanitize user input before database operations
- Use HTTPS in production
- Implement CSRF protection for forms
- Add reCAPTCHA for spam prevention

## 🚀 Next Steps

To make this a fully functional application:

1. **Backend Integration**
   - Connect forms to backend API
   - Implement search functionality
   - Add database for storing data

2. **Additional Pages**
   - Create individual service pages
   - Add user registration/login system
   - Build admin dashboard

3. **Features to Add**
   - User reviews and ratings
   - Location-based services (GPS)
   - Prayer time calculator
   - Qibla direction finder
   - Multi-language support

4. **SEO Optimization**
   - Add meta tags
   - Implement structured data
   - Create sitemap.xml
   - Optimize images

## 📝 License

This project is created for educational purposes.

## 👨‍💻 Support

For any questions or support, please contact:
- Email: info@halalguide.com
- Website: [Coming Soon]

---

**Made with ❤️ for the Muslim community**

🕌 HalalGuide - Your Trusted Muslim Travel Companion

