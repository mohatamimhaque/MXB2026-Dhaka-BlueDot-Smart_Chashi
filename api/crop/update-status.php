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
    $status = trim($data['status'] ?? '');
    
    if ($cropId <= 0 || empty($status)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid data']);
        exit;
    }
    
    $validStatuses = ['planning', 'growing', 'harvesting', 'harvested'];
    if (!in_array($status, $validStatuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
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
    
    $sql = "UPDATE crop_data SET status = ? WHERE crop_id = ? AND farmer_id = ?";
    $db->query($sql);
    $db->bind(1, $status);
    $db->bind(2, $cropId);
    $db->bind(3, $_SESSION['user_id']);
    
    if ($db->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update status']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Update status error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error']);
}
