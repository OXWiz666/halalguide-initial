# HalalGuide Database Analysis

## Overview
This document provides a comprehensive analysis of the `halalguide.sql` database structure for the HalalGuide Muslim Travel Companion application.

**Database Name:** `halalguide`  
**Generated:** October 21, 2025  
**Server:** MariaDB 10.4.32  
**Character Set:** utf8mb4

---

## Database Structure Summary

### 📊 Table Categories

The database consists of **18 tables** organized into three main categories:

1. **Reference Tables (4)** - Philippine geographic data
2. **Core Application Tables (10)** - Main application functionality
3. **Support Tables (4)** - Status, types, and configurations

---

## 1. Reference Tables (Philippine Geographic Data)

These tables contain comprehensive Philippine location data for address management.

### 1.1 `refregion`
**Purpose:** Stores Philippine region information

| Column | Type | Description |
|--------|------|-------------|
| id | INT(11) | Primary Key, Auto Increment |
| psgcCode | VARCHAR(255) | Philippine Standard Geographic Code |
| regDesc | TEXT | Region Description (e.g., "REGION III (CENTRAL LUZON)") |
| regCode | VARCHAR(255) | Region Code (e.g., "03") |

**Data:** 17 Philippine regions

---

### 1.2 `refprovince`
**Purpose:** Stores Philippine province information

| Column | Type | Description |
|--------|------|-------------|
| id | INT(11) | Primary Key, Auto Increment |
| psgcCode | VARCHAR(255) | Philippine Standard Geographic Code |
| provDesc | TEXT | Province Description |
| regCode | VARCHAR(255) | Foreign Key to refregion |
| provCode | VARCHAR(255) | Province Code |

**Data:** 88 Philippine provinces

---

### 1.3 `refcitymun`
**Purpose:** Stores cities and municipalities

| Column | Type | Description |
|--------|------|-------------|
| id | INT(255) | Primary Key, Auto Increment |
| psgcCode | VARCHAR(255) | Philippine Standard Geographic Code |
| citymunDesc | TEXT | City/Municipality Description |
| regCode | VARCHAR(255) | Foreign Key to refregion |
| provCode | VARCHAR(255) | Foreign Key to refprovince |
| citymunCode | VARCHAR(255) | City/Municipality Code |

**Data:** 1,647 cities and municipalities

---

### 1.4 `refbrgy`
**Purpose:** Stores barangay (village) information

| Column | Type | Description |
|--------|------|-------------|
| brgy_id | INT(11) | Primary Key, Auto Increment |
| brgyCode | VARCHAR(255) | Barangay Code |
| brgyDesc | TEXT | Barangay Description |
| regCode | VARCHAR(255) | Foreign Key to refregion |
| provCode | VARCHAR(255) | Foreign Key to refprovince |
| citymunCode | VARCHAR(255) | Foreign Key to refcitymun |

**Data:** 42,029 barangays

---

## 2. Core Application Tables

### 2.1 `tbl_accreditation`
**Purpose:** Stores halal certifying/accreditation bodies (e.g., NCMF)

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| accreditation_id | VARCHAR(250) | PRIMARY | Unique identifier for accreditation body |
| accreditation_name | VARCHAR(250) | | Name of certifying body |
| acc_contact_no | VARCHAR(15) | | Contact number |
| acc_email | VARCHAR(50) | | Email address |
| address_id | VARCHAR(25) | FOREIGN | Links to tbl_address |
| date_added | DATETIME | | Registration date |

**Sample Data:**
- National Commission on Muslim Filipinos (NCMF)

**Use Case:** Manages organizations that provide halal certification

---

### 2.2 `tbl_address`
**Purpose:** Stores complete address information for all entities

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| address_id | VARCHAR(25) | PRIMARY | Unique address identifier |
| brgyCode | VARCHAR(25) | FOREIGN | Links to refbrgy |
| other | VARCHAR(500) | | Additional address details (building, street, landmarks) |
| date_added | DATETIME | | Date address was added |

**Use Case:** Centralized address management for all entities (companies, organizations, accreditation bodies)

---

### 2.3 `tbl_superadmin`
**Purpose:** Stores super administrator accounts (highest privilege level)

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| superadmin_id | VARCHAR(250) | PRIMARY | Unique superadmin identifier |
| accreditation_id | VARCHAR(25) | FOREIGN | Links to tbl_accreditation |
| firstname | VARCHAR(250) | | First name |
| middlename | VARCHAR(250) | | Middle name |
| lastname | VARCHAR(250) | | Last name |
| gender | VARCHAR(15) | | Gender |
| contact_no | VARCHAR(15) | | Contact number |
| email | VARCHAR(50) | | Email address |
| status_id | INT(11) | FOREIGN | Links to tbl_status |
| date_added | DATETIME | | Registration date |

**Sample Data:**
- Akeshi Kotaro Osano (NCMF Super Admin)

**Use Case:** Highest level administrators managing accreditation bodies

---

### 2.4 `tbl_organization`
**Purpose:** Stores halal certifying organizations (e.g., MHA, HCB)

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| organization_id | VARCHAR(25) | PRIMARY | Unique organization identifier |
| organization_name | VARCHAR(250) | | Organization name |
| description | VARCHAR(800) | | Organization description |
| contant_no | VARCHAR(15) | | Contact number (typo in DB) |
| tel_no | VARCHAR(20) | | Telephone number |
| address_id | VARCHAR(25) | FOREIGN | Links to tbl_address |
| location | VARCHAR(800) | | Location details |
| status_id | INT(11) | FOREIGN | Links to tbl_status |
| date_added | DATETIME | | Registration date |

**Sample Data:**
- Mindanao Halal Authority (MHA)

**Use Case:** Organizations that certify halal establishments

---

### 2.5 `tbl_admin`
**Purpose:** Stores organization administrators

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| admin_id | VARCHAR(25) | PRIMARY | Unique admin identifier |
| organization_id | VARCHAR(25) | FOREIGN | Links to tbl_organization |
| firstname | VARCHAR(250) | | First name |
| middlename | VARCHAR(250) | | Middle name |
| lastname | VARCHAR(250) | | Last name |
| gender | VARCHAR(15) | | Gender |
| contact_no | VARCHAR(15) | | Contact number |
| email | VARCHAR(50) | | Email address |
| status_id | INT(11) | FOREIGN | Links to tbl_status |
| date_added | DATETIME | | Registration date |

**Sample Data:**
- Earl Tolero (MHA Admin)

**Use Case:** Admins managing organizations that certify establishments

---

### 2.6 `tbl_company`
**Purpose:** Stores halal establishments, accommodations, tourist spots, and prayer facilities

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| company_id | VARCHAR(25) | PRIMARY | Unique company identifier |
| company_name | VARCHAR(250) | | Company/establishment name |
| company_description | VARCHAR(800) | | Description |
| contant_no | VARCHAR(15) | | Contact number (typo in DB) |
| tel_no | VARCHAR(20) | | Telephone number |
| email | VARCHAR(50) | | Email address |
| address_id | VARCHAR(25) | FOREIGN | Links to tbl_address |
| usertype_id | INT(11) | FOREIGN | Links to tbl_usertype (3=Establishment, 4=Accommodation, 5=Tourist Spot, 6=Prayer Facility) |
| status_id | INT(11) | FOREIGN | Links to tbl_status |
| has_prayer_faci | INT(11) | | Boolean flag: Has prayer facility (0/1) |
| date_added | DATETIME | | Registration date |

**Use Case:** Main table for all service providers (restaurants, hotels, tourist spots, mosques)

**Important:** This table handles multiple types of entities:
- **Establishment (usertype_id = 3):** Halal restaurants, cafes, food businesses
- **Accommodation (usertype_id = 4):** Hotels, resorts, lodges
- **Tourist Spot (usertype_id = 5):** Destinations, attractions
- **Prayer Facility (usertype_id = 6):** Mosques, prayer rooms

---

### 2.7 `tbl_company_person`
**Purpose:** Stores contact persons for companies/establishments

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| company_person_id | VARCHAR(25) | PRIMARY | Unique identifier |
| company_id | VARCHAR(25) | FOREIGN | Links to tbl_company |
| firstname | VARCHAR(50) | | First name |
| middlename | VARCHAR(50) | | Middle name |
| lastname | VARCHAR(50) | | Last name |
| gender | VARCHAR(15) | | Gender |
| contact_no | VARCHAR(15) | | Contact number |
| email | VARCHAR(50) | | Email address |
| status_id | INT(11) | FOREIGN | Links to tbl_status |
| date_added | DATETIME | | Registration date |

**Use Case:** Manages authorized representatives for establishments

---

### 2.8 `tbl_useraccount`
**Purpose:** Stores login credentials for all user types

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| useraccount_id | VARCHAR(25) | PRIMARY | Unique account identifier |
| username | VARCHAR(50) | | Login username |
| password | VARCHAR(50) | | Password (⚠️ Plain text - needs hashing) |
| superadmin_id | VARCHAR(25) | FOREIGN | Links to tbl_superadmin (if super admin) |
| admin_id | VARCHAR(25) | FOREIGN | Links to tbl_admin (if admin) |
| company_id | VARCHAR(25) | FOREIGN | Links to tbl_company (if business user) |
| usertype_id | INT(11) | FOREIGN | Links to tbl_usertype |
| status_id | INT(11) | FOREIGN | Links to tbl_status |
| date_added | DATETIME | | Account creation date |

**Sample Accounts:**
```
Username: ncmf | Password: ncmf | Type: Super Admin
Username: admin | Password: admin | Type: Admin
```

**⚠️ Security Concern:** Passwords are stored in plain text. Should be hashed using bcrypt or similar.

---

### 2.9 `tbl_status`
**Purpose:** Stores status types for all entities

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| status_id | INT(11) | PRIMARY | Unique status identifier |
| status | VARCHAR(50) | | Status name |

**Status Types:**
```
1 - Active
2 - Inactive
3 - Archived
4 - Halal-Certified
5 - Not Halal-Certified
```

**Use Case:** Universal status management across all tables

---

### 2.10 `tbl_usertype`
**Purpose:** Defines user/entity types in the system

| Column | Type | Key | Description |
|--------|------|-----|-------------|
| userty | INT(11) | PRIMARY | Unique usertype identifier (typo in column name) |
| usertype | VARCHAR(50) | | Usertype name |

**User Types:**
```
1 - Super Admin (NCMF level)
2 - Admin (Organization level)
3 - Establishment (Restaurants, cafes)
4 - Accommodation (Hotels, lodges)
5 - Tourist Spot (Attractions, destinations)
6 - Prayer Facility (Mosques, prayer rooms)
7 - Tourist (End users/travelers)
```

---

## 3. Database Relationships

### Entity Relationship Overview

```
tbl_accreditation (NCMF)
    ↓
tbl_superadmin (Super Admins)
    ↓
tbl_organization (MHA, HCB)
    ↓
tbl_admin (Org Admins)
    ↓
[Certifies] → tbl_company (Establishments, Hotels, Tourist Spots, Prayer Facilities)
                  ↓
              tbl_company_person (Contact Persons)

tbl_address ← Links to all entities needing location
    ↓
refbrgy → refcitymun → refprovince → refregion

tbl_useraccount ← Links to superadmin, admin, or company for authentication
```

---

## 4. Key Insights & Analysis

### 4.1 Architectural Strengths ✅

1. **Hierarchical Structure:** Clear chain of command from accreditation → super admin → organization → admin → company
2. **Flexible Address System:** Uses Philippine geographic hierarchy for precise location tracking
3. **Multi-Entity Support:** Single company table handles multiple business types via usertype_id
4. **Status Management:** Centralized status table for consistent state management
5. **Comprehensive Data:** Full Philippine geographic data (42K+ barangays)

### 4.2 Identified Issues & Recommendations ⚠️

#### Security Issues
1. **Plain Text Passwords** ❌  
   - **Issue:** Passwords stored without encryption
   - **Risk:** Critical security vulnerability
   - **Fix:** Implement bcrypt/password_hash() with minimum cost of 10
   
2. **No Password Policies** ❌  
   - **Issue:** No minimum length, complexity requirements
   - **Fix:** Implement validation (8+ chars, uppercase, lowercase, numbers, symbols)

3. **No Session Management Tables** ❌  
   - **Issue:** No session tracking, remember me tokens
   - **Fix:** Add session management table with token expiration

#### Data Integrity Issues
1. **Column Name Typos** ⚠️  
   - `contant_no` should be `contact_no` (in tbl_company, tbl_organization)
   - `userty` should be `usertype_id` (in tbl_usertype)
   
2. **Inconsistent VARCHAR Lengths** ⚠️  
   - `superadmin_id` is VARCHAR(250), others are VARCHAR(25)
   - **Fix:** Standardize to VARCHAR(25) for all ID fields

3. **No Foreign Key Constraints** ❌  
   - **Issue:** No enforced relationships between tables
   - **Risk:** Orphaned records, data inconsistency
   - **Fix:** Add proper FK constraints with CASCADE options

#### Missing Critical Tables

1. **Tourist/User Profile Table** ❌  
   - **Need:** Table for tourist users (usertype 7)
   - **Should include:** Profile info, preferences, saved locations
   
2. **Reviews/Ratings Table** ❌  
   - **Need:** User reviews for establishments
   - **Should include:** rating (1-5), comment, date, user_id, company_id
   
3. **Favorites/Bookmarks Table** ❌  
   - **Need:** Save favorite establishments
   - **Should include:** user_id, company_id, date_added
   
4. **Images/Gallery Table** ❌  
   - **Need:** Store multiple images per establishment
   - **Should include:** image_path, company_id, is_primary, display_order
   
5. **Certification Table** ❌  
   - **Need:** Track halal certifications for companies
   - **Should include:** certificate_number, issue_date, expiry_date, organization_id, company_id
   
6. **Operating Hours Table** ❌  
   - **Need:** Store business hours
   - **Should include:** company_id, day_of_week, open_time, close_time
   
7. **Amenities/Features Table** ❌  
   - **Need:** Track establishment amenities
   - **Should include:** Prayer room, Halal kitchen, Parking, WiFi, etc.
   
8. **Categories/Tags Table** ❌  
   - **Need:** Categorize establishments (Filipino cuisine, Chinese, etc.)
   - **Should include:** category_name, category_type, company_id

9. **Audit Trail Table** ❌  
   - **Need:** Track all changes for accountability
   - **Should include:** user_id, action, table_name, record_id, old_value, new_value, timestamp

10. **Notification Table** ❌  
    - **Need:** User notifications
    - **Should include:** user_id, message, is_read, date_sent

---

## 5. Recommended Database Enhancements

### 5.1 New Tables to Add

#### `tbl_tourist`
```sql
CREATE TABLE `tbl_tourist` (
  `tourist_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `firstname` VARCHAR(250) NOT NULL,
  `middlename` VARCHAR(250),
  `lastname` VARCHAR(250) NOT NULL,
  `gender` VARCHAR(15),
  `birthdate` DATE,
  `contact_no` VARCHAR(15),
  `email` VARCHAR(50) NOT NULL UNIQUE,
  `profile_image` VARCHAR(255),
  `nationality` VARCHAR(100),
  `address_id` VARCHAR(25),
  `status_id` INT(11) DEFAULT 1,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (address_id) REFERENCES tbl_address(address_id),
  FOREIGN KEY (status_id) REFERENCES tbl_status(status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_reviews`
```sql
CREATE TABLE `tbl_reviews` (
  `review_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `company_id` VARCHAR(25) NOT NULL,
  `tourist_id` VARCHAR(25) NOT NULL,
  `rating` INT(1) CHECK (rating BETWEEN 1 AND 5),
  `review_text` TEXT,
  `visit_date` DATE,
  `is_verified` TINYINT(1) DEFAULT 0,
  `helpful_count` INT DEFAULT 0,
  `status_id` INT(11) DEFAULT 1,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES tbl_company(company_id) ON DELETE CASCADE,
  FOREIGN KEY (tourist_id) REFERENCES tbl_tourist(tourist_id) ON DELETE CASCADE,
  FOREIGN KEY (status_id) REFERENCES tbl_status(status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_favorites`
```sql
CREATE TABLE `tbl_favorites` (
  `favorite_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `tourist_id` VARCHAR(25) NOT NULL,
  `company_id` VARCHAR(25) NOT NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (tourist_id) REFERENCES tbl_tourist(tourist_id) ON DELETE CASCADE,
  FOREIGN KEY (company_id) REFERENCES tbl_company(company_id) ON DELETE CASCADE,
  UNIQUE KEY unique_favorite (tourist_id, company_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_company_images`
```sql
CREATE TABLE `tbl_company_images` (
  `image_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `company_id` VARCHAR(25) NOT NULL,
  `image_path` VARCHAR(500) NOT NULL,
  `image_caption` VARCHAR(255),
  `is_primary` TINYINT(1) DEFAULT 0,
  `display_order` INT DEFAULT 0,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES tbl_company(company_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_certifications`
```sql
CREATE TABLE `tbl_certifications` (
  `certification_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `company_id` VARCHAR(25) NOT NULL,
  `organization_id` VARCHAR(25) NOT NULL,
  `certificate_number` VARCHAR(100) NOT NULL UNIQUE,
  `issue_date` DATE NOT NULL,
  `expiry_date` DATE NOT NULL,
  `certificate_image` VARCHAR(500),
  `status_id` INT(11) DEFAULT 4, -- Halal-Certified
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES tbl_company(company_id) ON DELETE CASCADE,
  FOREIGN KEY (organization_id) REFERENCES tbl_organization(organization_id),
  FOREIGN KEY (status_id) REFERENCES tbl_status(status_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_operating_hours`
```sql
CREATE TABLE `tbl_operating_hours` (
  `hours_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `company_id` VARCHAR(25) NOT NULL,
  `day_of_week` INT(1) CHECK (day_of_week BETWEEN 0 AND 6), -- 0=Sunday, 6=Saturday
  `open_time` TIME,
  `close_time` TIME,
  `is_closed` TINYINT(1) DEFAULT 0,
  `is_24_hours` TINYINT(1) DEFAULT 0,
  FOREIGN KEY (company_id) REFERENCES tbl_company(company_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_amenities`
```sql
CREATE TABLE `tbl_amenities` (
  `amenity_id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `amenity_name` VARCHAR(100) NOT NULL,
  `amenity_icon` VARCHAR(50), -- FontAwesome icon class
  `amenity_category` VARCHAR(50) -- 'facility', 'service', 'safety'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_company_amenities`
```sql
CREATE TABLE `tbl_company_amenities` (
  `company_amenity_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `company_id` VARCHAR(25) NOT NULL,
  `amenity_id` INT(11) NOT NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES tbl_company(company_id) ON DELETE CASCADE,
  FOREIGN KEY (amenity_id) REFERENCES tbl_amenities(amenity_id) ON DELETE CASCADE,
  UNIQUE KEY unique_company_amenity (company_id, amenity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_categories`
```sql
CREATE TABLE `tbl_categories` (
  `category_id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `category_name` VARCHAR(100) NOT NULL,
  `category_type` VARCHAR(50), -- 'cuisine', 'accommodation_type', 'tourist_spot_type'
  `parent_category_id` INT(11), -- For subcategories
  `icon_class` VARCHAR(50),
  FOREIGN KEY (parent_category_id) REFERENCES tbl_categories(category_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_company_categories`
```sql
CREATE TABLE `tbl_company_categories` (
  `company_category_id` VARCHAR(25) NOT NULL PRIMARY KEY,
  `company_id` VARCHAR(25) NOT NULL,
  `category_id` INT(11) NOT NULL,
  `date_added` DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES tbl_company(company_id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES tbl_categories(category_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

#### `tbl_audit_log`
```sql
CREATE TABLE `tbl_audit_log` (
  `log_id` INT(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `user_id` VARCHAR(25),
  `usertype_id` INT(11),
  `action` VARCHAR(50), -- 'CREATE', 'UPDATE', 'DELETE', 'LOGIN', 'LOGOUT'
  `table_name` VARCHAR(100),
  `record_id` VARCHAR(25),
  `old_value` TEXT,
  `new_value` TEXT,
  `ip_address` VARCHAR(50),
  `user_agent` VARCHAR(255),
  `timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

### 5.2 Security Improvements

#### Password Hashing Migration Script
```php
<?php
// Migrate existing passwords to hashed versions
$users = $conn->query("SELECT useraccount_id, password FROM tbl_useraccount");
foreach ($users as $user) {
    $hashed = password_hash($user['password'], PASSWORD_BCRYPT, ['cost' => 12]);
    $conn->prepare("UPDATE tbl_useraccount SET password = ? WHERE useraccount_id = ?")
         ->execute([$hashed, $user['useraccount_id']]);
}
?>
```

#### Add Additional Status Types
```sql
INSERT INTO tbl_status (status_id, status) VALUES
(6, 'Pending Approval'),
(7, 'Rejected'),
(8, 'Suspended'),
(9, 'Expired'),
(10, 'Under Review');
```

---

## 6. Implementation Priority

### Phase 1: Critical (Immediate)
1. ✅ Add password hashing
2. ✅ Add foreign key constraints
3. ✅ Create tbl_tourist
4. ✅ Fix column name typos
5. ✅ Add tbl_certifications

### Phase 2: High Priority (Week 1-2)
1. ✅ Add tbl_reviews
2. ✅ Add tbl_favorites
3. ✅ Add tbl_company_images
4. ✅ Add tbl_operating_hours
5. ✅ Add amenities tables

### Phase 3: Medium Priority (Week 3-4)
1. ✅ Add categories system
2. ✅ Add audit logging
3. ✅ Add session management
4. ✅ Add notification system
5. ✅ Implement search optimization (indexes)

### Phase 4: Enhancement (Month 2)
1. ✅ Add analytics tables
2. ✅ Add booking/reservation system (if needed)
3. ✅ Add messaging system
4. ✅ Add multi-language support tables
5. ✅ Add SEO meta data tables

---

## 7. Query Optimization Recommendations

### Add Indexes for Better Performance
```sql
-- Frequently searched fields
ALTER TABLE tbl_company ADD INDEX idx_usertype_status (usertype_id, status_id);
ALTER TABLE tbl_company ADD INDEX idx_email (email);
ALTER TABLE tbl_useraccount ADD INDEX idx_username (username);
ALTER TABLE tbl_reviews ADD INDEX idx_company_rating (company_id, rating);
ALTER TABLE tbl_address ADD INDEX idx_brgyCode (brgyCode);

-- Full-text search for company names and descriptions
ALTER TABLE tbl_company ADD FULLTEXT idx_fulltext_search (company_name, company_description);
```

---

## 8. Sample Queries for Landing Page

### Get All Halal Establishments with Location
```sql
SELECT 
    c.company_id,
    c.company_name,
    c.company_description,
    c.has_prayer_faci,
    a.other as address_detail,
    b.brgyDesc,
    cm.citymunDesc,
    p.provDesc,
    r.regDesc,
    s.status,
    ut.usertype
FROM tbl_company c
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode
LEFT JOIN refregion r ON p.regCode = r.regCode
LEFT JOIN tbl_status s ON c.status_id = s.status_id
LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.userty
WHERE c.usertype_id = 3 -- Establishments only
AND c.status_id = 1 -- Active only
ORDER BY c.date_added DESC;
```

### Get Companies by Type with Count
```sql
SELECT 
    ut.usertype,
    COUNT(*) as count,
    SUM(CASE WHEN c.status_id = 1 THEN 1 ELSE 0 END) as active_count,
    SUM(CASE WHEN c.status_id = 4 THEN 1 ELSE 0 END) as certified_count
FROM tbl_company c
JOIN tbl_usertype ut ON c.usertype_id = ut.userty
GROUP BY ut.usertype;
```

### Search Companies with Full Address
```sql
SELECT 
    c.*,
    CONCAT(
        a.other, ', ',
        b.brgyDesc, ', ',
        cm.citymunDesc, ', ',
        p.provDesc
    ) as full_address
FROM tbl_company c
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode
WHERE c.company_name LIKE '%search_term%'
OR c.company_description LIKE '%search_term%'
OR a.other LIKE '%search_term%'
LIMIT 20;
```

---

## 9. Conclusion

### Strengths
- ✅ Well-structured hierarchical organization
- ✅ Comprehensive Philippine geographic data
- ✅ Flexible multi-entity design
- ✅ Clear separation of concerns

### Areas for Improvement
- ❌ Security vulnerabilities (passwords)
- ❌ Missing essential tables (reviews, images, etc.)
- ❌ No foreign key constraints
- ❌ Limited tourist/user functionality
- ❌ No audit trail

### Next Steps
1. Implement critical security fixes immediately
2. Add missing essential tables
3. Create proper foreign key relationships
4. Develop comprehensive API for landing page
5. Implement search and filter functionality
6. Add image upload and management system
7. Build review and rating system

---

## 10. Contact & Support

For database-related questions or suggestions, please refer to:
- Database Schema Updates: Track in version control
- Performance Issues: Add appropriate indexes
- Security Concerns: Follow OWASP guidelines
- Data Integrity: Implement proper constraints

**Last Updated:** October 21, 2025  
**Version:** 1.0  
**Author:** Database Analysis for HalalGuide Project

