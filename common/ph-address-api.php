<?php
/**
 * Philippine Address API
 * Provides address data from database reference tables
 * Compatible with philippine-address-selector
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

include 'connection.php';

$type = isset($_GET['type']) ? mysqli_real_escape_string($conn, $_GET['type']) : '';

switch ($type) {
    case 'regions':
        // Try to get regions from refregion table first, if it exists
        $regionTableExists = mysqli_query($conn, "SHOW TABLES LIKE 'refregion'");
        if (mysqli_num_rows($regionTableExists) > 0) {
            $query = "SELECT DISTINCT regCode as region_code, regDesc as region_name 
                      FROM refregion 
                      WHERE regCode IS NOT NULL AND regCode != ''
                      ORDER BY regDesc";
        } else {
            // Fallback: Get unique regions from refprovince
            $query = "SELECT DISTINCT regCode as region_code, 
                      CASE regCode
                          WHEN '01' THEN 'Ilocos Region (Region I)'
                          WHEN '02' THEN 'Cagayan Valley (Region II)'
                          WHEN '03' THEN 'Central Luzon (Region III)'
                          WHEN '04' THEN 'CALABARZON (Region IV-A)'
                          WHEN '05' THEN 'MIMAROPA (Region IV-B)'
                          WHEN '06' THEN 'Bicol Region (Region V)'
                          WHEN '07' THEN 'Western Visayas (Region VI)'
                          WHEN '08' THEN 'Central Visayas (Region VII)'
                          WHEN '09' THEN 'Eastern Visayas (Region VIII)'
                          WHEN '10' THEN 'Zamboanga Peninsula (Region IX)'
                          WHEN '11' THEN 'Northern Mindanao (Region X)'
                          WHEN '12' THEN 'SOCCSKSARGEN (Region XII)'
                          WHEN '13' THEN 'CARAGA (Region XIII)'
                          WHEN '14' THEN 'National Capital Region (NCR)'
                          WHEN '15' THEN 'Cordillera Administrative Region (CAR)'
                          WHEN '16' THEN 'Autonomous Region in Muslim Mindanao (ARMM)'
                          WHEN '17' THEN 'Davao Region (Region XI)'
                          ELSE CONCAT('Region ', regCode)
                      END as region_name
                      FROM refprovince 
                      WHERE regCode IS NOT NULL AND regCode != ''
                      GROUP BY regCode
                      ORDER BY regCode";
        }
        
        $result = mysqli_query($conn, $query);
        $regions = [];
        
        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $regions[] = [
                    'regCode' => $row['region_code'],
                    'region_name' => $row['region_name'],
                    'regionDesc' => $row['region_name']
                ];
            }
        }
        
        echo json_encode(['success' => true, 'regions' => $regions]);
        break;
        
    case 'provinces':
        $regionCode = isset($_GET['region_code']) ? mysqli_real_escape_string($conn, $_GET['region_code']) : '';
        
        if (empty($regionCode)) {
            echo json_encode(['success' => false, 'message' => 'Region code required']);
            exit;
        }
        
        $query = "SELECT DISTINCT provCode, provDesc, regCode 
                  FROM refprovince 
                  WHERE regCode = '$regionCode' 
                  ORDER BY provDesc";
        $result = mysqli_query($conn, $query);
        $provinces = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $provinces[] = [
                'provCode' => $row['provCode'],
                'province_code' => $row['provCode'],
                'provDesc' => $row['provDesc'],
                'province_name' => $row['provDesc']
            ];
        }
        
        echo json_encode(['success' => true, 'provinces' => $provinces]);
        break;
        
    case 'cities':
        $regionCode = isset($_GET['region_code']) ? mysqli_real_escape_string($conn, $_GET['region_code']) : '';
        $provinceCode = isset($_GET['province_code']) ? mysqli_real_escape_string($conn, $_GET['province_code']) : '';
        
        if (empty($regionCode) || empty($provinceCode)) {
            echo json_encode(['success' => false, 'message' => 'Region and province codes required']);
            exit;
        }
        
        $query = "SELECT DISTINCT citymunCode, citymunDesc, provCode, regCode 
                  FROM refcitymun 
                  WHERE regCode = '$regionCode' AND provCode = '$provinceCode'
                  ORDER BY citymunDesc";
        $result = mysqli_query($conn, $query);
        $cities = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $cities[] = [
                'citymunCode' => $row['citymunCode'],
                'city_code' => $row['citymunCode'],
                'citymunDesc' => $row['citymunDesc'],
                'city_name' => $row['citymunDesc']
            ];
        }
        
        echo json_encode(['success' => true, 'cities' => $cities]);
        break;
        
    case 'barangays':
        $regionCode = isset($_GET['region_code']) ? mysqli_real_escape_string($conn, $_GET['region_code']) : '';
        $provinceCode = isset($_GET['province_code']) ? mysqli_real_escape_string($conn, $_GET['province_code']) : '';
        $cityCode = isset($_GET['city_code']) ? mysqli_real_escape_string($conn, $_GET['city_code']) : '';
        
        if (empty($regionCode) || empty($provinceCode) || empty($cityCode)) {
            echo json_encode(['success' => false, 'message' => 'Region, province, and city codes required']);
            exit;
        }
        
        $query = "SELECT brgyCode, brgyDesc, citymunCode, provCode, regCode 
                  FROM refbrgy 
                  WHERE regCode = '$regionCode' AND provCode = '$provinceCode' AND citymunCode = '$cityCode'
                  ORDER BY brgyDesc";
        $result = mysqli_query($conn, $query);
        $barangays = [];
        
        while ($row = mysqli_fetch_assoc($result)) {
            $barangays[] = [
                'brgyCode' => $row['brgyCode'],
                'barangay_code' => $row['brgyCode'],
                'brgyDesc' => $row['brgyDesc'],
                'barangay_name' => $row['brgyDesc']
            ];
        }
        
        echo json_encode(['success' => true, 'barangays' => $barangays]);
        break;
        
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid request type']);
        break;
}
?>
