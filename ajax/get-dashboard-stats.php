<?php
/**
 * Get Dashboard Statistics
 * Returns real-time statistics for farmer dashboard
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
    // Get farmer statistics
    $stats = [];
    
    // Total crops
    $result = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE farmer_id = ?", [$userId]);
    $stats['total_crops'] = $result['count'] ?? 0;
    
    // Active crops
    $result = $db->single("SELECT COUNT(*) as count FROM crop_data WHERE farmer_id = ? AND status = 'growing'", [$userId]);
    $stats['active_crops'] = $result['count'] ?? 0;
    
    // Total yield
    $result = $db->single("SELECT SUM(actual_yield) as total FROM crop_data WHERE farmer_id = ? AND actual_yield IS NOT NULL", [$userId]);
    $stats['total_yield'] = $result['total'] ?? 0;
    
    // Disease reports
    $result = $db->single("SELECT COUNT(*) as count FROM disease_reports WHERE user_id = ?", [$userId]);
    $stats['disease_reports'] = $result['count'] ?? 0;
    
    // Marketplace products
    $result = $db->single("SELECT COUNT(*) as count FROM marketplace_products WHERE seller_id = ?", [$userId]);
    $stats['marketplace_products'] = $result['count'] ?? 0;
    
    // Community posts
    $result = $db->single("SELECT COUNT(*) as count FROM community_posts WHERE user_id = ?", [$userId]);
    $stats['community_posts'] = $result['count'] ?? 0;
    
    echo json_encode([
        'success' => true,
        'stats' => $stats,
        'timestamp' => time()
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch statistics',
        'message' => $e->getMessage()
    ]);
}
