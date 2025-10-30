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
    COALESCE(a.latitude, 0) as lat,
    COALESCE(a.longitude, 0) as lng,
    CONCAT(
        COALESCE(a.other, ''), 
        CASE WHEN a.other IS NOT NULL AND a.other != '' THEN ', ' ELSE '' END,
        COALESCE(b.brgyDesc, ''), 
        CASE WHEN b.brgyDesc IS NOT NULL AND b.brgyDesc != '' THEN ', ' ELSE '' END,
        COALESCE(cm.citymunDesc, ''), 
        CASE WHEN cm.citymunDesc IS NOT NULL AND cm.citymunDesc != '' THEN ', ' ELSE '' END,
        COALESCE(p.provDesc, '')
    ) as full_address,
    a.region_code,
    a.province_code,
    a.citymunCode,
    a.brgyCode,
    a.other as street_address
FROM tbl_company c
LEFT JOIN tbl_usertype ut ON c.usertype_id = ut.usertype_id
LEFT JOIN tbl_address a ON c.address_id = a.address_id
LEFT JOIN refbrgy b ON a.brgyCode = b.brgyCode
LEFT JOIN refcitymun cm ON a.citymunCode = cm.citymunCode AND a.province_code = cm.provCode
LEFT JOIN refprovince p ON a.province_code = p.provCode
WHERE $where_clause
ORDER BY 
    CASE WHEN c.status_id = 4 THEN 1 ELSE 2 END, -- Prioritize Halal-Certified
    c.company_name";

$result = mysqli_query($conn, $query);

$places = [];

if ($result) {
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
        
        // If no coordinates, use fallback location so company still appears on map
        // Companies without coordinates will appear at approximate Philippines center
        // (This ensures all registered companies are visible, even without geocoded addresses)
        if ($place_lat === null || $place_lng === null) {
            // Use approximate Philippines center as fallback
            // All companies should appear on map, even without exact coordinates
            $place_lat = 12.8797; // Approximate Philippines center
            $place_lng = 121.7740;
            
            // Note: In production, consider implementing geocoding service to convert
            // addresses to coordinates for better accuracy
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
            'distance' => null
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
    'places' => $places
]);
?>

