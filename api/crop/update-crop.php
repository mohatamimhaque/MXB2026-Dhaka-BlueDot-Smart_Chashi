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
    
    $cropId = intval($data['cropId'] ?? 0);
    $cropName = trim($data['cropName'] ?? '');
    $variety = trim($data['variety'] ?? '');
    $area = floatval($data['area'] ?? 0);
    $plantedDate = $data['plantedDate'] ?? '';
    $status = $data['status'] ?? 'growing';
    $expectedHarvest = !empty($data['expectedHarvest']) ? $data['expectedHarvest'] : null;
    
    if ($cropId <= 0 || empty($cropName) || $area <= 0 || empty($plantedDate)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Please fill all required fields']);
        exit;
    }
    
    $db = new Database();
    
    // Verify ownership
    $exists = $db->single("SELECT crop_id FROM crop_data WHERE crop_id = ? AND farmer_id = ?", [$cropId, $_SESSION['user_id']]);
    
    if (!$exists) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access denied']);
        exit;
    }
    
    $sql = "UPDATE crop_data SET 
            crop_name = ?, 
            variety = ?, 
            area_hectares = ?, 
            planted_date = ?, 
            status = ?,
            expected_harvest = ?
            WHERE crop_id = ? AND farmer_id = ?";
    
    $db->query($sql);
    $db->bind(1, $cropName);
    $db->bind(2, $variety);
    $db->bind(3, $area);
    $db->bind(4, $plantedDate);
    $db->bind(5, $status);
    $db->bind(6, $expectedHarvest);
    $db->bind(7, $cropId);
    $db->bind(8, $_SESSION['user_id']);
    
    if ($db->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Crop updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update crop']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Update crop error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
