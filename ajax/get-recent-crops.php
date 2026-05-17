<?php
/**
 * Get Recent Crops
 * Returns recent crops for farmer dashboard
 */

header('Content-Type: application/json');

// Include necessary files
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit;
}

$userId = $_SESSION['user_id'];
$db = new Database();

try {
    // Get recent crops
    $crops = $db->resultSet(
        "SELECT 
            id,
            crop_name, 
            variety,
            status, 
            planting_date,
            expected_harvest_date,
            area
        FROM crop_data 
        WHERE farmer_id = ? 
        ORDER BY 
            CASE 
                WHEN planting_date IS NULL OR planting_date = '0000-00-00' THEN 1
                ELSE 0
            END,
            planting_date DESC 
        LIMIT 6",
        [$userId]
    );
    
    // Clean up dates
    foreach ($crops as &$crop) {
        if (empty($crop['planting_date']) || $crop['planting_date'] == '0000-00-00') {
            $crop['planting_date'] = null;
        }
        if (empty($crop['expected_harvest_date']) || $crop['expected_harvest_date'] == '0000-00-00') {
            $crop['expected_harvest_date'] = null;
        }
        if (empty($crop['status'])) {
            $crop['status'] = 'growing';
        }
    }
    
    echo json_encode([
        'success' => true,
        'crops' => $crops,
        'count' => count($crops),
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch crops',
        'message' => $e->getMessage()
    ]);
}
