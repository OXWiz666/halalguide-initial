# Philippine Address Selector Implementation Guide

This guide documents the implementation of the Philippine Address Selector based on [philippine-address-selector](https://github.com/wilfredpine/philippine-address-selector) by wilfredpine, using data from [isaacdarcilla/philippine-addresses](https://github.com/isaacdarcilla/philippine-addresses).

## 📋 Table of Contents

1. [Overview](#overview)
2. [Database Setup](#database-setup)
3. [Files Created](#files-created)
4. [Integration Steps](#integration-steps)
5. [Usage](#usage)
6. [Map Integration](#map-integration)
7. [Troubleshooting](#troubleshooting)

---

## 🎯 Overview

The Philippine Address Selector provides a cascading dropdown system for selecting:
- **Region** → **Province** → **City/Municipality** → **Barangay**

All data is fetched from your existing database reference tables (`refprovince`, `refcitymun`, `refbrgy`), ensuring compatibility with your current database structure.

---

## 🗄️ Database Setup

### Step 1: Update `tbl_address` Table

Run the SQL migration to add necessary columns:

```bash
mysql -u your_username -p your_database < database/update_address_table.sql
```

**Or manually execute:**

```sql
ALTER TABLE `tbl_address` 
ADD COLUMN IF NOT EXISTS `region_code` VARCHAR(10) NULL COMMENT 'Region Code',
ADD COLUMN IF NOT EXISTS `province_code` VARCHAR(10) NULL COMMENT 'Province Code',
ADD COLUMN IF NOT EXISTS `citymunCode` VARCHAR(10) NULL COMMENT 'City/Municipality Code',
ADD COLUMN IF NOT EXISTS `latitude` DECIMAL(10, 8) NULL COMMENT 'GPS Latitude',
ADD COLUMN IF NOT EXISTS `longitude` DECIMAL(11, 8) NULL COMMENT 'GPS Longitude';

-- Note: brgyCode column should already exist
```

### Step 2: Verify Database Tables

Ensure you have these reference tables:
- `refprovince` - Contains provinces with `regCode`, `provCode`, `provDesc`
- `refcitymun` - Contains cities/municipalities with `regCode`, `provCode`, `citymunCode`, `citymunDesc`
- `refbrgy` - Contains barangays with `regCode`, `provCode`, `citymunCode`, `brgyCode`, `brgyDesc`

---

## 📁 Files Created

### 1. **`common/ph-address-api.php`**
- PHP API endpoint that serves address data from database
- Endpoints:
  - `?type=regions` - Get all regions
  - `?type=provinces&region_code=XX` - Get provinces in a region
  - `?type=cities&region_code=XX&province_code=YY` - Get cities in a province
  - `?type=barangays&region_code=XX&province_code=YY&city_code=ZZ` - Get barangays in a city

### 2. **`assets2/js/ph-address-selector.js`**
- JavaScript library for address selector functionality
- Handles cascading dropdowns
- Auto-updates address preview
- Integrates with existing database structure

### 3. **`database/update_address_table.sql`**
- SQL migration script to update `tbl_address` table
- Adds columns for region, province, city, and coordinates

---

## 🔌 Integration Steps

### Step 1: Run Database Migration

```sql
-- Execute the update script
source database/update_address_table.sql;
```

### Step 2: Include JavaScript in Your Form

In your registration form (`company/company.php`), the script is already included:

```html
<!-- jQuery (required) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- Philippine Address Selector -->
<script src="../assets2/js/ph-address-selector.js"></script>
```

### Step 3: Add HTML Elements

The form should include these select elements:

```html
<!-- Region -->
<select name="region_code" id="region" class="form-control" required>
    <option value="" selected disabled>Select Region</option>
</select>
<input type="hidden" name="region_text" id="region-text">

<!-- Province -->
<select name="province_code" id="province" class="form-control" required disabled>
    <option value="" selected disabled>Select Province</option>
</select>
<input type="hidden" name="province_text" id="province-text">

<!-- City/Municipality -->
<select name="citymun_code" id="city" class="form-control" required disabled>
    <option value="" selected disabled>Select City/Municipality</option>
</select>
<input type="hidden" name="city_text" id="city-text">

<!-- Barangay -->
<select name="brgy_code" id="barangay" class="form-control" required disabled>
    <option value="" selected disabled>Select Barangay</option>
</select>
<input type="hidden" name="barangay_text" id="barangay-text">

<!-- Street Address (optional) -->
<input type="text" name="address_line" id="address-line" class="form-control" placeholder="Street, Building, etc.">

<!-- Address Preview -->
<div id="full-address-display"></div>
<input type="hidden" name="full_address" id="full-address-hidden">
```

### Step 4: Update Backend Processing

In your PHP file (e.g., `company/company.php`), capture all address components:

```php
// Address components from Philippine Address Selector
$address_line = mysqli_real_escape_string($conn, trim($_POST['address_line'] ?? ''));
$region_code = mysqli_real_escape_string($conn, trim($_POST['region_code'] ?? ''));
$province_code = mysqli_real_escape_string($conn, trim($_POST['province_code'] ?? ''));
$citymun_code = mysqli_real_escape_string($conn, trim($_POST['citymun_code'] ?? ''));
$brgy_code = mysqli_real_escape_string($conn, trim($_POST['brgy_code'] ?? ''));

// Insert into tbl_address
$insert_address = mysqli_query($conn, "INSERT INTO tbl_address 
    (address_id, region_code, province_code, citymunCode, brgyCode, other, date_added)
    VALUES ('$address_id', 
        " . (!empty($region_code) ? "'$region_code'" : "NULL") . ", 
        " . (!empty($province_code) ? "'$province_code'" : "NULL") . ", 
        " . (!empty($citymun_code) ? "'$citymun_code'" : "NULL") . ", 
        " . (!empty($brgy_code) ? "'$brgy_code'" : "NULL") . ", 
        " . (!empty($address_line) ? "'$address_line'" : "NULL") . ", 
        NOW())");
```

---

## 💻 Usage

### Initialization

The address selector initializes automatically on page load:

```javascript
// Automatic initialization when document is ready
$(document).ready(function() {
    phAddressSelector.init();
});
```

### Manual Usage

You can also manually trigger actions:

```javascript
// Load regions
phAddressSelector.loadRegions();

// Load provinces for a region
phAddressSelector.loadProvinces('12'); // SOCCSKSARGEN

// Load cities for a province
phAddressSelector.loadCities('12', '1298'); // SOCCSKSARGEN, South Cotabato

// Load barangays for a city
phAddressSelector.loadBarangays('12', '1298', '129801'); // Koronadal City
```

### Address Preview

The full address is automatically displayed in the `#full-address-display` element and stored in `#full-address-hidden`:

**Format:** `[Street], [Barangay], [City], [Province], [Region]`

---

## 🗺️ Map Integration

### Address Display in Map

The map API (`pages/map/map-api.php`) has been updated to properly fetch and display addresses:

```php
// Address is constructed from joined reference tables
CONCAT(
    COALESCE(a.other, ''), 
    CASE WHEN a.other IS NOT NULL AND a.other != '' THEN ', ' ELSE '' END,
    COALESCE(b.brgyDesc, ''), 
    CASE WHEN b.brgyDesc IS NOT NULL AND b.brgyDesc != '' THEN ', ' ELSE '' END,
    COALESCE(cm.citymunDesc, ''), 
    CASE WHEN cm.citymunDesc IS NOT NULL AND cm.citymunDesc != '' THEN ', ' ELSE '' END,
    COALESCE(p.provDesc, '')
) as full_address
```

### Geocoding (Future Enhancement)

For addresses without coordinates, you can implement geocoding:

```php
// Example geocoding function (requires API key)
function geocodeAddress($address) {
    // Use Google Maps Geocoding API or similar
    // Store lat/lng in tbl_address.latitude and tbl_address.longitude
}
```

---

## 🐛 Troubleshooting

### Issue: Dropdowns not loading

**Possible Causes:**
1. API endpoint not accessible
2. Database connection issue
3. JavaScript errors in console

**Solutions:**
1. Check browser console for errors
2. Verify `common/ph-address-api.php` is accessible
3. Test API endpoint directly: `common/ph-address-api.php?type=regions`
4. Verify database connection in `common/connection.php`

### Issue: "Select Province" dropdown disabled

**Solution:** Ensure Region is selected first. The cascading dropdowns are dependent.

### Issue: Address not saving correctly

**Solution:** 
1. Verify all address fields are included in form submission
2. Check PHP receives all POST values
3. Verify database columns exist (run migration script)

### Issue: Map not showing addresses

**Solution:**
1. Verify address is properly saved with codes (not just text)
2. Check map API query includes proper JOINs
3. Verify reference tables have data

---

## 📝 Data Source

The address data comes from:
- **Reference Tables**: Your database tables (`refprovince`, `refcitymun`, `refbrgy`)
- **PSA PSGC**: Philippine Standard Geographic Code
- **Source**: Data aligned with PSA (Philippine Statistics Authority) classifications

**Note**: The system uses your existing database reference tables instead of external JSON files for better integration and performance.

---

## 🔒 Validation

The form includes validation:
- All address components (Region, Province, City, Barangay) are **required**
- Street address is **optional** but recommended
- Validation occurs both client-side (required attributes) and server-side

---

## 🚀 Features

✅ **Cascading Dropdowns** - Region → Province → City → Barangay  
✅ **Database Integration** - Uses existing reference tables  
✅ **Auto-Address Preview** - Shows complete address as user selects  
✅ **Proper Storage** - Saves codes for database integrity  
✅ **Map Compatible** - Addresses work seamlessly with map display  
✅ **Validation** - Ensures complete addresses before submission  
✅ **User-Friendly** - Clear labels and disabled states guide users  

---

## 📚 References

- **GitHub Repository**: [wilfredpine/philippine-address-selector](https://github.com/wilfredpine/philippine-address-selector)
- **Data Source**: [isaacdarcilla/philippine-addresses](https://github.com/isaacdarcilla/philippine-addresses)
- **PSA PSGC**: [Philippine Statistics Authority - PSGC](https://www.psa.gov.ph/classification/psgc)

---

## 📝 Next Steps (Optional Enhancements)

1. **Geocoding Integration** - Automatically get lat/lng when address is saved
2. **Address Autocomplete** - Add search/autocomplete for faster selection
3. **Address Validation** - Verify address completeness before saving
4. **Bulk Import** - Allow importing addresses from CSV/Excel
5. **Address History** - Track address changes for companies

---

**Implementation Date**: 2024  
**Version**: 1.0.0  
**Maintainer**: HalalGuide Development Team
