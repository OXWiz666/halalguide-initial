<?php
/**
 * Map API Endpoint - Provides Halal Certified Establishments Data
 * This endpoint returns JSON data for the map to display
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

include '../../common/connection.php';

date_default_timezone_set('Asia/Manila');

// Simple geocoder using OpenStreetMap Nominatim for missing coordinates
function geocode_address($street, $barangay, $city, $province) {
    $parts = [];
    if (!empty($street)) { $parts[] = $street; }
    if (!empty($barangay)) { $parts[] = $barangay; }
    if (!empty($city)) { $parts[] = $city; }
    if (!empty($province)) { $parts[] = $province; }
    $parts[] = 'Philippines';
    $query = implode(', ', $parts);

    $url = 'https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' . urlencode($query);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['User-Agent: HalalGuide/1.0 (+https://example.com)']);
    $resp = curl_exec($ch);
    curl_close($ch);
    if ($resp) {
        $data = json_decode($resp, true);
        if (is_array($data) && count($data) > 0 && isset($data[0]['lat']) && isset($data[0]['lon'])) {
            return [floatval($data[0]['lat']), floatval($data[0]['lon'])];
        }
    }
    return [null, null];
}

// Get filter parameters
$type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : 'all';
$lat = isset($_GET['lat']) ? floatval($_GET['lat']) : null;
$lng = isset($_GET['lng']) ? floatval($_GET['lng']) : null;
$radius = isset($_GET['radius']) ? floatval($_GET['radius']) : 50; // km

// Map filter type to usertype_id
$type_mapping = [
    'establishment' => 3,    // Establishment
    'hotel' => 4,             // Accommodation/Hotel
    'tourist_spot' => 5,      // Tourist Spot
    'prayer_facility' => 6    // Prayer Facility
];

// Build query - Show ALL registered establishments including:
// - Halal-Certified (status_id = 4) → Shows "Halal Certified" badge
// - Non-Halal Certified (status_id = 1, 2, 3, 5, NULL, etc. - ANY status except 4) → Shows "Non-Halal Certified" badge
// - ALL registered companies from the system (regardless of status_id)
$where_clause = "c.company_id IS NOT NULL"; // Show all companies (including those with NULL status_id)

if ($type !== 'all' && isset($type_mapping[$type])) {
    $where_clause .= " AND c.usertype_id = " . $type_mapping[$type];
}

// Detect optional latitude/longitude columns in tbl_address
$has_lat_column = false;
$has_lng_column = false;
$col_chk_lat = mysqli_query($conn, "SHOW COLUMNS FROM tbl_address LIKE 'latitude'");
if ($col_chk_lat && mysqli_num_rows($col_chk_lat) > 0) { $has_lat_column = true; }
$col_chk_lng = mysqli_query($conn, "SHOW COLUMNS FROM tbl_address LIKE 'longitude'");
if ($col_chk_lng && mysqli_num_rows($col_chk_lng) > 0) { $has_lng_column = true; }

$latField = $has_lat_column ? 'a.latitude' : '0';
$lngField = $has_lng_column ? 'a.longitude' : '0';

// Query to get establishments with coordinates
// Updated to use direct address table joins for proper address display
$query = "SELECT 
    c.company_id,
    c.company_name,
    c.company_description,
    c.contant_no,
    c.tel_no,
    c.email,
    c.usertype_id,
    c.status_id,
    c.has_prayer_faci,
    ut.usertype,
    $latField as lat,
    $lngField as lng,
    CONCAT(
        COALESCE(a.other, ''), 
        CASE WHEN a.other IS NOT NULL AND a.other != '' THEN ', ' ELSE '' END,
        COALESCE(b.brgyDesc, ''), 
        CASE WHEN b.brgyDesc IS NOT NULL AND b.brgyDesc != '' THEN ', ' ELSE '' END,
        COALESCE(cm.citymunDesc, ''), 
        CASE WHEN cm.citymunDesc IS NOT NULL AND cm.citymunDesc != '' THEN ', ' ELSE '' END,
        COALESCE(p.provDesc, '')
    ) as full_address,
    a.other as street_address,
    r.regDesc as region_name,
    p.provDesc as province_name,
    cm.citymunDesc as city_name,
    b.brgyDesc as barangay_name
FROM tbl_company c
LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON b.citymunCode = cm.citymunCode
LEFT JOIN refprovince p ON cm.provCode = p.provCode
LEFT JOIN refregion r ON p.regCode = r.regCode
WHERE $where_clause
ORDER BY 
    CASE WHEN c.status_id = 4 THEN 1 ELSE 2 END, -- Prioritize Halal-Certified
    c.company_name";

$result = mysqli_query($conn, $query);
$db_error = mysqli_error($conn);

$places = [];

if ($result) {
    // Pre-check if operating hours table exists
    $hours_table_exists = false;
    $tbl_check = mysqli_query($conn, "SHOW TABLES LIKE 'tbl_operating_hours'");
    if ($tbl_check && mysqli_num_rows($tbl_check) > 0) { $hours_table_exists = true; }

    while ($row = mysqli_fetch_assoc($result)) {
        $place_type = 'establishment'; // Default fallback
        
        // Map usertype_id to map type
        switch ($row['usertype_id']) {
            case 3:
                $place_type = 'establishment';
                break;
            case 4:
                $place_type = 'hotel';
                break;
            case 5:
                $place_type = 'tourist_spot';
                break;
            case 6:
                $place_type = 'prayer_facility';
                break;
            default:
                $place_type = 'establishment'; // Default fallback
                break;
        }
        
        // Use stored coordinates if available
        $place_lat = !empty($row['lat']) && $row['lat'] != 0 ? floatval($row['lat']) : null;
        $place_lng = !empty($row['lng']) && $row['lng'] != 0 ? floatval($row['lng']) : null;
        
        // If no coordinates, try server-side geocoding using Nominatim
        if ($place_lat === null || $place_lng === null || ($place_lat == 0 && $place_lng == 0)) {
            // Improve geocoding accuracy by including province/region context
            $street = $row['street_address'] ?? ($row['other'] ?? '');
            $barangay = $row['barangay_name'] ?? '';
            $city = $row['city_name'] ?? '';
            $province = $row['province_name'] ?? '';

            // Prefer precise coords from geocoder
            list($gLat, $gLng) = geocode_address($street, $barangay, $city, $province);
            if ($gLat !== null && $gLng !== null) {
                $place_lat = $gLat;
                $place_lng = $gLng;
            } else {
                // Province-aware fallback: keep markers within South Cotabato/Region XII when applicable
                $region = strtoupper($row['region_name'] ?? '');
                $prov = strtoupper($row['province_name'] ?? '');
                if (strpos($prov, 'SOUTH COTABATO') !== false || strpos($region, 'XII') !== false || strpos($region, 'SOCCSKSARGEN') !== false) {
                    // South Cotabato center
                    $place_lat = 6.5969;
                    $place_lng = 124.7825;
                } else {
                    // Conservative national fallback so entry is still visible
                    $place_lat = 12.8797;
                    $place_lng = 121.7740;
                }
            }
        }
        
        // Build today's hours string if table exists
        $hours_today = null;
        $hours_week = [];
        if ($hours_table_exists) {
            $dow = (int)date('w'); // 0=Sunday
            $hours_rs = mysqli_query($conn, "SELECT day_of_week, open_time, close_time, is_closed, is_24_hours 
                                             FROM tbl_operating_hours WHERE company_id='".$row['company_id']."'");
            if ($hours_rs) {
                while ($h = mysqli_fetch_assoc($hours_rs)) {
                    $label = $h['is_closed'] ? 'Closed' : ($h['is_24_hours'] ? 'Open 24 hours' :
                        (substr($h['open_time'],0,5) . ' - ' . substr($h['close_time'],0,5)));
                    $hours_week[(int)$h['day_of_week']] = $label;
                }
                if (isset($hours_week[$dow])) { $hours_today = $hours_week[$dow]; }
            }
        }

        $place = [
            'id' => $row['company_id'],
            'name' => $row['company_name'],
            'type' => $place_type,
            'address' => $row['full_address'] ?: 'Address not specified',
            'lat' => $place_lat,
            'lng' => $place_lng,
            'phone' => $row['contant_no'] ?: $row['tel_no'] ?: 'N/A',
            'email' => $row['email'] ?: 'N/A',
            'description' => $row['company_description'] ?: 'No description available',
            'certified' => ($row['status_id'] == 4), // Halal-Certified (status_id = 4 ONLY)
            'non_certified' => ($row['status_id'] != 4), // Non-Halal Certified (EVERYTHING except status_id = 4, including NULL, Active(1), Inactive(2), etc.)
            'status_id' => $row['status_id'], // Include status_id for reference
            'status_label' => ($row['status_id'] == 4) ? 'Halal-Certified' : 'Non-Halal Certified', // Status label: Always shows either Halal-Certified or Non-Halal Certified
            'has_prayer_facility' => ($row['has_prayer_faci'] == 1),
            'distance' => null,
            'region' => $row['region_name'] ?? null,
            'province' => $row['province_name'] ?? null,
            'city' => $row['city_name'] ?? null,
            'barangay' => $row['barangay_name'] ?? null,
            'hours_today' => $hours_today,
            'hours_week' => $hours_week
        ];
        
        // Calculate distance if user location provided (for display only, not filtering)
        if ($lat && $lng && $place['lat'] && $place['lng']) {
            // Haversine formula for distance calculation
            $earthRadius = 6371; // km
            $dLat = deg2rad($place['lat'] - $lat);
            $dLng = deg2rad($place['lng'] - $lng);
            $a = sin($dLat/2) * sin($dLat/2) +
                 cos(deg2rad($lat)) * cos(deg2rad($place['lat'])) *
                 sin($dLng/2) * sin($dLng/2);
            $c = 2 * atan2(sqrt($a), sqrt(1-$a));
            $distance = $earthRadius * $c;
            
            // Always include place, just add distance for display
            $place['distance'] = round($distance, 2);
            $places[] = $place;
        } else {
            // No location available, include all without distance
            $places[] = $place;
        }
    }
}

// Sort by distance if available (but include all regardless)
if ($lat && $lng) {
    usort($places, function($a, $b) {
        // Prioritize halal-certified, then by distance
        if ($a['certified'] && !$b['certified']) return -1;
        if (!$a['certified'] && $b['certified']) return 1;
        
        if ($a['distance'] === null) return 1;
        if ($b['distance'] === null) return -1;
        return $a['distance'] <=> $b['distance'];
    });
}

echo json_encode([
    'success' => true,
    'count' => count($places),
    'places' => $places,
    'sql_error' => $db_error ? $db_error : null
]);
?>

