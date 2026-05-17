<?php
/**
 * SmartChashi - Get My Reports AJAX Handler
 * Returns farmer's own disease reports
 */

require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$currentUser = getCurrentUser();
if ($currentUser['role'] !== 'farmer') {
    echo json_encode(['success' => false, 'message' => 'Access denied']);
    exit;
}

$db = new Database();

try {
    // Get stats
    $stats = [
        'total' => 0,
        'detected' => 0,
        'treating' => 0,
        'cured' => 0,
        'failed' => 0
    ];
    
    $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE user_id = ?", [$currentUser['user_id']]);
    $stats['total'] = $result['count'] ?? 0;
    
    // detected or NULL status = pending
    $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE user_id = ? AND (status = 'detected' OR status IS NULL OR status = '')", [$currentUser['user_id']]);
    $stats['detected'] = $result['count'] ?? 0;
    
    $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE user_id = ? AND status = 'treating'", [$currentUser['user_id']]);
    $stats['treating'] = $result['count'] ?? 0;
    
    $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE user_id = ? AND status = 'cured'", [$currentUser['user_id']]);
    $stats['cured'] = $result['count'] ?? 0;
    
    $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE user_id = ? AND status = 'failed'", [$currentUser['user_id']]);
    $stats['failed'] = $result['count'] ?? 0;
    
    // Get reports
    $query = "SELECT dr.*, c.crop_name, DATE_FORMAT(dr.detected_date, '%M %d, %Y') as formatted_date
              FROM disease_reports dr
              LEFT JOIN crop_data c ON dr.crop_id = c.crop_id
              WHERE dr.user_id = ?
              ORDER BY dr.detected_date DESC";
    
    $reports = $db->resultSet($query, [$currentUser['user_id']]) ?: [];
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'reports' => $reports
    ]);
    
} catch (Exception $e) {
    error_log('Get my reports error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => __('error_occurred')]);
}
