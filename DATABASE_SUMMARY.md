# HalalGuide Database - Quick Summary

## 📊 Database Overview

**Total Tables:** 18  
**Database Size:** ~44,297 lines  
**Location Data:** Complete Philippine geographic hierarchy (42K+ barangays)

---

## 🏗️ Main Structure

### Current Tables (10 Core + 4 Reference + 4 Support)

```
┌─────────────────────────────────────────────────────┐
│  AUTHENTICATION HIERARCHY                           │
├─────────────────────────────────────────────────────┤
│  tbl_accreditation (NCMF)                          │
│       ↓                                             │
│  tbl_superadmin (Super Admins)                     │
│       ↓                                             │
│  tbl_organization (MHA, HCB)                       │
│       ↓                                             │
│  tbl_admin (Organization Admins)                   │
│       ↓ [Certifies]                                │
│  tbl_company (ALL SERVICES)                        │
│       ├─ Type 3: Halal Establishments              │
│       ├─ Type 4: Accommodation                     │
│       ├─ Type 5: Tourist Spots                     │
│       └─ Type 6: Prayer Facilities                 │
└─────────────────────────────────────────────────────┘
```

---

## 🎯 Key Tables for Landing Page

### 1. **tbl_company** - Most Important!
This ONE table handles ALL four service types:

| usertype_id | Service Type | What It Includes |
|-------------|--------------|------------------|
| 3 | **Halal Establishments** | Restaurants, cafes, food businesses |
| 4 | **Accommodation** | Hotels, resorts, inns, lodges |
| 5 | **Tourist Spots** | Destinations, attractions, landmarks |
| 6 | **Prayer Facilities** | Mosques, prayer rooms, Islamic centers |

**Columns:**
- `company_name` - Name of establishment
- `company_description` - Details about the service
- `contant_no`, `tel_no`, `email` - Contact info
- `address_id` - Links to full Philippine address
- `usertype_id` - Determines service type (3/4/5/6)
- `status_id` - Active/Inactive/Halal-Certified
- `has_prayer_faci` - Boolean flag (1 = has prayer room)

### 2. **tbl_address** + Reference Tables
Complete address system:
```
tbl_address
    → refbrgy (42,029 barangays)
        → refcitymun (1,647 cities/municipalities)
            → refprovince (88 provinces)
                → refregion (17 regions)
```

### 3. **tbl_status**
```
1 - Active
2 - Inactive
3 - Archived
4 - Halal-Certified  ← Important for filtering
5 - Not Halal-Certified
```

---

## 🚨 Critical Issues Found

### 🔴 SECURITY - URGENT!
1. **Passwords stored in PLAIN TEXT** ❌
   ```sql
   -- Current accounts (everyone can see passwords!)
   Username: ncmf | Password: ncmf
   Username: admin | Password: admin
   ```
   **Fix:** Must implement password_hash() immediately

2. **No Foreign Key Constraints** ❌
   - Tables not properly linked
   - Risk of orphaned data

### 🟡 MISSING TABLES - Needed for Landing Page

| Missing Table | Why You Need It |
|---------------|-----------------|
| **tbl_tourist** | Store tourist user profiles |
| **tbl_reviews** | User ratings & reviews (⭐⭐⭐⭐⭐) |
| **tbl_favorites** | Bookmark favorite places |
| **tbl_company_images** | Multiple photos per establishment |
| **tbl_certifications** | Track halal certificates |
| **tbl_operating_hours** | Business hours (Mon-Sun) |
| **tbl_amenities** | WiFi, Parking, Prayer Room, etc. |
| **tbl_categories** | Cuisine types, hotel categories |

---

## 📝 Sample Data Currently in Database

### Accreditation Body
- **National Commission on Muslim Filipinos (NCMF)**
  - Contact: 09112345678
  - Email: ncmf@gmail.com

### Organization
- **Mindanao Halal Authority (MHA)**
  - Contact: 09111234567
  - Admin: Earl Tolero

### Users
```
Super Admin: akeshi123@gmail.com (Akeshi Kotaro Osano)
Admin: tolero@gmail.com (Earl Tolero)
```

### **NO ESTABLISHMENTS YET!** ⚠️
The `tbl_company` table is currently **EMPTY**.  
You need to add sample establishments, hotels, tourist spots, and prayer facilities!

---

## 🔧 What You Need to Do for Landing Page

### Phase 1: Add Sample Data (Required for testing)
```sql
-- Add sample halal restaurants
INSERT INTO tbl_company VALUES
('COMP001', 'Halal Bistro Manila', 'Authentic Filipino halal cuisine...', '09171234567', NULL, 'halalbi@email.com', 'ADDRESS_ID', 3, 1, 1, NOW());

-- Add sample hotels
INSERT INTO tbl_company VALUES
('COMP002', 'Mindanao Islamic Hotel', 'Muslim-friendly hotel...', '09181234567', NULL, 'info@hotel.com', 'ADDRESS_ID', 4, 1, 1, NOW());

-- Add sample tourist spots
INSERT INTO tbl_company VALUES
('COMP003', 'Grand Mosque of Marawi', 'Historic Islamic landmark...', '09191234567', NULL, 'info@mosque.com', 'ADDRESS_ID', 5, 1, 1, NOW());

-- Add sample prayer facilities
INSERT INTO tbl_company VALUES
('COMP004', 'SM Mall Prayer Room', '24/7 prayer facility...', '09201234567', NULL, 'prayer@sm.com', 'ADDRESS_ID', 6, 1, 1, NOW());
```

### Phase 2: Create Missing Tables
Run the SQL scripts provided in `DATABASE_ANALYSIS.md` to add:
- Tourist profiles
- Reviews system
- Image gallery
- Operating hours
- Amenities

### Phase 3: PHP Integration
Create API endpoints in PHP:

**File: `api/get_establishments.php`**
```php
<?php
// Get all halal establishments
$sql = "SELECT c.*, a.other, b.brgyDesc, cm.citymunDesc 
        FROM tbl_company c
        LEFT JOIN tbl_address a ON c.address_id = a.address_id
        LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
        LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
        WHERE c.usertype_id = 3 AND c.status_id = 1
        ORDER BY c.date_added DESC";
?>
```

**File: `api/get_accommodations.php`**
```php
<?php
// Get all accommodations
$sql = "SELECT * FROM tbl_company 
        WHERE usertype_id = 4 AND status_id = 1";
?>
```

**File: `api/get_tourist_spots.php`**
```php
<?php
// Get all tourist spots
$sql = "SELECT * FROM tbl_company 
        WHERE usertype_id = 5 AND status_id = 1";
?>
```

**File: `api/get_prayer_facilities.php`**
```php
<?php
// Get all prayer facilities
$sql = "SELECT * FROM tbl_company 
        WHERE usertype_id = 6 AND status_id = 1";
?>
```

### Phase 4: Update Landing Page
Modify `index.php` service cards to fetch real data from database instead of static content.

---

## 📊 Expected Data Flow

```
Landing Page (index.php)
    ↓ AJAX Request
API Endpoint (api/get_establishments.php)
    ↓ SQL Query
Database (tbl_company + tbl_address)
    ↓ JSON Response
Landing Page (Display Cards)
```

---

## 🎨 Database Schema Visualization

### Service Types Distribution
```
tbl_company
├── usertype_id = 3 (Establishments) 🍽️
│   ├── Restaurants
│   ├── Cafes
│   └── Food Courts
│
├── usertype_id = 4 (Accommodation) 🏨
│   ├── Hotels
│   ├── Resorts
│   └── Lodges
│
├── usertype_id = 5 (Tourist Spots) 🗺️
│   ├── Landmarks
│   ├── Museums
│   └── Parks
│
└── usertype_id = 6 (Prayer Facilities) 🕌
    ├── Mosques
    ├── Prayer Rooms
    └── Islamic Centers
```

---

## 🚀 Quick Start Commands

### 1. Import Database
```bash
# Open phpMyAdmin or use command line
mysql -u root -p
CREATE DATABASE halalguide;
USE halalguide;
source C:/xampp/htdocs/capstone/final/halalguide-initial/db/halalguide.sql;
```

### 2. Test Database Connection (Create: `db/config.php`)
```php
<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "halalguide";

$conn = new mysqli($host, $username, $password, $database);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

echo "Connected successfully!";
?>
```

### 3. Test Query
```php
<?php
include 'db/config.php';

$sql = "SELECT * FROM tbl_company WHERE usertype_id = 3";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        echo "Name: " . $row["company_name"] . "<br>";
    }
} else {
    echo "No establishments found. Please add sample data!";
}
?>
```

---

## 📚 Important Files

1. **DATABASE_ANALYSIS.md** - Complete detailed analysis (67KB)
2. **db/halalguide.sql** - The actual database file
3. **index.php** - Landing page (uses assets2 folder)
4. **README.md** - Landing page documentation

---

## 🔗 Quick Reference Links

### Current Sample Accounts
```
Super Admin Login:
- Username: ncmf
- Password: ncmf
- Access Level: Highest (Accreditation Body)

Organization Admin Login:
- Username: admin
- Password: admin
- Access Level: Organization Management
```

⚠️ **Remember:** These passwords are visible in plain text! Change immediately in production.

---

## ✅ Next Steps Checklist

- [ ] Import database into phpMyAdmin
- [ ] Test database connection
- [ ] Add sample establishments data
- [ ] Create missing tables (tourist, reviews, images)
- [ ] Fix password security (implement hashing)
- [ ] Create PHP API endpoints
- [ ] Connect landing page to database
- [ ] Add foreign key constraints
- [ ] Create admin panel for data management
- [ ] Add image upload functionality
- [ ] Implement search functionality
- [ ] Add review and rating system

---

## 💡 Pro Tips

1. **Always backup** before making changes
2. **Test queries** in phpMyAdmin first
3. **Use prepared statements** to prevent SQL injection
4. **Add indexes** on frequently searched columns
5. **Implement caching** for better performance
6. **Add pagination** when displaying lists
7. **Validate all inputs** server-side
8. **Log all errors** for debugging

---

**Created:** October 21, 2025  
**For:** HalalGuide Landing Page Integration  
**Status:** Database Ready - Needs Sample Data

