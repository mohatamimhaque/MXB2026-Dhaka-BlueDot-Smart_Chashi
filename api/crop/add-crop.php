<?php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $cropName = trim($data['cropName'] ?? '');
    $variety = trim($data['variety'] ?? '');
    $area = $data['area'] ?? 0;
    $plantedDate = $data['plantedDate'] ?? date('Y-m-d');
    $status = $data['status'] ?? 'growing';
    $expectedHarvest = !empty($data['expectedHarvest']) ? $data['expectedHarvest'] : null;
    
    if (empty($cropName) || empty($area) || empty($plantedDate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit;
    }
    
    $db = new Database();
    $sql = "INSERT INTO crop_data (farmer_id, crop_name, variety, area_hectares, planted_date, status, expected_harvest, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";
    $db->query($sql);
    $db->bind(1, $_SESSION['user_id']);
    $db->bind(2, $cropName);
    $db->bind(3, $variety);
    $db->bind(4, $area);
    $db->bind(5, $plantedDate);
    $db->bind(6, $status);
    $db->bind(7, $expectedHarvest);
    
    if ($db->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Crop added successfully']);
        exit;
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to add crop']);
        exit;
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Add crop error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
    exit;
}
