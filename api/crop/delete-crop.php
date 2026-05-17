<?php
require_once __DIR__ . '/../../config/config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');

try {
    // Get JSON input
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    // Check session
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized']);
        exit;
    }
    
    // Get crop ID
    $cropId = $data['cropId'] ?? null;
    
    if (!$cropId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Crop ID is required']);
        exit;
    }
    
    // Delete crop
    $db = new Database();
    $sql = "DELETE FROM crop_data WHERE crop_id = ? AND farmer_id = ?";
    $db->query($sql);
    $db->bind(1, $cropId);
    $db->bind(2, $_SESSION['user_id']);
    
    if ($db->execute()) {
        http_response_code(200);
        echo json_encode(['success' => true, 'message' => 'Crop deleted successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to delete crop']);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    error_log('Delete crop error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred']);
}
?>
