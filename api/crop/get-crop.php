<?php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    $cropId = isset($_GET['id']) ? intval($_GET['id']) : 0;
    
    if ($cropId <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid crop ID']);
        exit;
    }
    
    $db = new Database();
    $crop = $db->single("SELECT * FROM crop_data WHERE crop_id = ? AND farmer_id = ?", [$cropId, $_SESSION['user_id']]);
    
    if ($crop) {
        http_response_code(200);
        echo json_encode(['success' => true, 'crop' => $crop]);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Crop not found']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Get crop error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
